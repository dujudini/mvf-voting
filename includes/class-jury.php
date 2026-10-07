<?php
/**
 * Júri: atribuição de categorias, cédula de votação (ordenar clipes) e endpoint de gravação.
 */

defined( 'ABSPATH' ) || exit;

class MVF_Jury {

	public static function init(): void {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
		add_action( 'admin_post_mvf_save_jurors', array( __CLASS__, 'save_jurors' ) );
		add_action( 'show_user_profile', array( __CLASS__, 'profile_fields' ) );
		add_action( 'edit_user_profile', array( __CLASS__, 'profile_fields' ) );
		add_action( 'personal_options_update', array( __CLASS__, 'profile_save' ) );
		add_action( 'edit_user_profile_update', array( __CLASS__, 'profile_save' ) );
		add_filter( 'login_redirect', array( __CLASS__, 'login_redirect' ), 10, 3 );
	}

	/* ---------------------------------------------------------------------
	 * Dados da cédula
	 * ------------------------------------------------------------------- */

	/**
	 * @return array{id:int,status:string,updated_at:?string,submitted_at:?string,items:array<int,int>}|null items = clip_id => posição
	 */
	public static function get_ballot( int $category_id, int $juror_id ): ?array {
		global $wpdb;
		$ballots = mvf_table( 'jury_ballots' );
		$items   = mvf_table( 'jury_ballot_items' );
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$ballots} WHERE category_id = %d AND juror_id = %d", $category_id, $juror_id ), ARRAY_A );
		if ( ! $row ) {
			return null;
		}
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT clip_id, position FROM {$items} WHERE ballot_id = %d ORDER BY position ASC", $row['id'] ), ARRAY_A );
		// phpcs:enable
		$map = array();
		foreach ( $rows as $r ) {
			$map[ (int) $r['clip_id'] ] = (int) $r['position'];
		}
		return array(
			'id'           => (int) $row['id'],
			'status'       => (string) $row['status'],
			'updated_at'   => $row['updated_at'],
			'submitted_at' => $row['submitted_at'],
			'items'        => $map,
		);
	}

	/**
	 * Clipes da categoria na ordem da cédula (clipes novos, fora da cédula, vão para o fim).
	 *
	 * @return int[]
	 */
	public static function ordered_clips( int $category_id, ?array $ballot ): array {
		$clips = mvf_get_category_clip_ids( $category_id );
		if ( ! $ballot || ! $ballot['items'] ) {
			return $clips;
		}
		$pos = $ballot['items'];
		usort(
			$clips,
			static function ( $a, $b ) use ( $pos ) {
				return ( $pos[ $a ] ?? PHP_INT_MAX ) <=> ( $pos[ $b ] ?? PHP_INT_MAX );
			}
		);
		return $clips;
	}

	/**
	 * A cédula enviada ainda corresponde exatamente aos clipes atuais da categoria?
	 */
	public static function ballot_matches_clips( array $ballot, int $category_id ): bool {
		$current = mvf_get_category_clip_ids( $category_id );
		$given   = array_keys( $ballot['items'] );
		sort( $current );
		sort( $given );
		return $current === $given;
	}

	/* ---------------------------------------------------------------------
	 * REST
	 * ------------------------------------------------------------------- */

	public static function routes(): void {
		register_rest_route(
			'mvf/v1',
			'/ballot',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'save_ballot' ),
				'permission_callback' => static function () {
					return current_user_can( 'mvf_vote_jury' );
				},
				'args'                => array(
					'category_id' => array(
						'type'     => 'integer',
						'required' => true,
					),
					'clip_ids'    => array(
						'type'     => 'array',
						'items'    => array( 'type' => 'integer' ),
						'required' => true,
					),
					'submit'      => array(
						'type'    => 'boolean',
						'default' => false,
					),
				),
			)
		);
	}

	public static function save_ballot( WP_REST_Request $request ) {
		global $wpdb;

		$juror_id    = get_current_user_id();
		$category_id = (int) $request['category_id'];

		if ( ! mvf_juror_is_assigned( $juror_id, $category_id ) ) {
			return new WP_Error( 'mvf_forbidden', __( 'Você não é jurado desta categoria.', 'mvf-voting' ), array( 'status' => 403 ) );
		}
		$edition_id = mvf_category_edition_id( $category_id );
		$period     = MVF_Periods::status( $edition_id, 'jury' );
		if ( ! $period['open'] ) {
			return new WP_Error( 'mvf_closed', MVF_Periods::message( $period ), array( 'status' => 403 ) );
		}

		$given    = array_map( 'intval', (array) $request['clip_ids'] );
		$expected = mvf_get_category_clip_ids( $category_id );
		if ( count( $given ) !== count( array_unique( $given ) ) || count( $given ) !== count( $expected ) || array_diff( $expected, $given ) ) {
			return new WP_Error( 'mvf_mismatch', __( 'A lista de clipes desta categoria mudou. Recarregue a página e ordene novamente.', 'mvf-voting' ), array( 'status' => 409 ) );
		}

		$submit  = (bool) $request['submit'];
		$now     = current_time( 'mysql', true );
		$ballots = mvf_table( 'jury_ballots' );
		$items   = mvf_table( 'jury_ballot_items' );
		$current = self::get_ballot( $category_id, $juror_id );

		$wpdb->query( 'START TRANSACTION' );

		$data = array(
			'edition_id'   => $edition_id,
			'category_id'  => $category_id,
			'juror_id'     => $juror_id,
			'status'       => $submit ? 'submitted' : 'draft',
			'updated_at'   => $now,
			'submitted_at' => $submit ? $now : null,
		);

		if ( $current ) {
			$ok        = false !== $wpdb->update( $ballots, $data, array( 'id' => $current['id'] ) );
			$ballot_id = $current['id'];
		} else {
			$ok        = (bool) $wpdb->insert( $ballots, $data );
			$ballot_id = (int) $wpdb->insert_id;
		}

		if ( $ok ) {
			$wpdb->delete( $items, array( 'ballot_id' => $ballot_id ), array( '%d' ) );
			foreach ( array_values( $given ) as $i => $clip_id ) {
				$ok = $ok && $wpdb->insert(
					$items,
					array(
						'ballot_id' => $ballot_id,
						'clip_id'   => $clip_id,
						'position'  => $i + 1,
					),
					array( '%d', '%d', '%d' )
				);
			}
		}

		if ( ! $ok ) {
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error( 'mvf_db', __( 'Não foi possível salvar. Tente novamente.', 'mvf-voting' ), array( 'status' => 500 ) );
		}
		$wpdb->query( 'COMMIT' );

		return rest_ensure_response(
			array(
				'ok'      => true,
				'status'  => $data['status'],
				'message' => $submit
					/* translators: %s: data e hora */
					? sprintf( __( 'Votação enviada em %s. Você pode alterar até o fim do período do júri.', 'mvf-voting' ), mvf_format_gmt( $now ) )
					/* translators: %s: data e hora */
					: sprintf( __( 'Rascunho salvo em %s. Lembre-se de enviar a votação.', 'mvf-voting' ), mvf_format_gmt( $now ) ),
			)
		);
	}

	/* ---------------------------------------------------------------------
	 * Tela do jurado ("Minha votação")
	 * ------------------------------------------------------------------- */

	public static function enqueue_ballot_assets(): void {
		wp_enqueue_style( 'mvf-admin', MVF_URL . 'assets/css/admin.css', array(), MVF_VERSION );
		wp_enqueue_script( 'mvf-jury', MVF_URL . 'assets/js/jury-ballot.js', array( 'jquery', 'jquery-ui-sortable' ), MVF_VERSION, true );
		wp_localize_script(
			'mvf-jury',
			'MVF_JURY',
			array(
				'endpoint' => esc_url_raw( rest_url( 'mvf/v1/ballot' ) ),
				'nonce'    => wp_create_nonce( 'wp_rest' ),
				'points'   => mvf_points_table(),
				'i18n'     => array(
					'saving'  => __( 'Salvando…', 'mvf-voting' ),
					'error'   => __( 'Não foi possível salvar. Tente novamente.', 'mvf-voting' ),
					'confirm' => __( 'Enviar a votação com esta ordem?', 'mvf-voting' ),
					'update'  => __( 'Atualizar votação enviada', 'mvf-voting' ),
					'pts'     => __( 'pts', 'mvf-voting' ),
					'sent'    => __( 'Enviada', 'mvf-voting' ),
				),
			)
		);
	}

	public static function render_ballot_page(): void {
		if ( ! current_user_can( 'mvf_vote_jury' ) ) {
			wp_die( esc_html__( 'Acesso negado.', 'mvf-voting' ) );
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$category_id = isset( $_GET['categoria'] ) ? absint( $_GET['categoria'] ) : 0;
		if ( $category_id ) {
			self::render_ballot( $category_id );
		} else {
			self::render_ballot_list();
		}
	}

	private static function status_label( ?array $ballot ): string {
		if ( ! $ballot ) {
			return '<span class="mvf-badge mvf-badge--pending">' . esc_html__( 'Pendente', 'mvf-voting' ) . '</span>';
		}
		if ( 'submitted' === $ballot['status'] ) {
			return '<span class="mvf-badge mvf-badge--open">' . esc_html(
				/* translators: %s: data */
				sprintf( __( 'Enviada em %s', 'mvf-voting' ), mvf_format_gmt( $ballot['submitted_at'] ) )
			) . '</span>';
		}
		return '<span class="mvf-badge mvf-badge--draft">' . esc_html__( 'Rascunho (não enviada)', 'mvf-voting' ) . '</span>';
	}

	private static function render_ballot_list(): void {
		$juror_id = get_current_user_id();
		$by_ed    = array();
		foreach ( mvf_juror_categories( $juror_id ) as $cat_id ) {
			$cat = get_term( $cat_id, MVF_TAX_CAT );
			if ( ! $cat || is_wp_error( $cat ) ) {
				continue;
			}
			$by_ed[ mvf_category_edition_id( $cat_id ) ][] = $cat;
		}
		krsort( $by_ed );
		?>
		<div class="wrap mvf-admin">
			<h1><?php esc_html_e( 'Minha votação', 'mvf-voting' ); ?></h1>
			<?php if ( ! $by_ed ) : ?>
				<p><?php esc_html_e( 'Você ainda não foi designado para nenhuma categoria.', 'mvf-voting' ); ?></p>
			<?php endif; ?>
			<?php
			foreach ( $by_ed as $edition_id => $cats ) :
				$edition = get_term( $edition_id, MVF_TAX_EDITION );
				if ( ! $edition || is_wp_error( $edition ) ) {
					continue;
				}
				$period = MVF_Periods::status( $edition_id, 'jury' );
				?>
				<h2><?php echo esc_html( $edition->name ); ?></h2>
				<p class="mvf-status-line mvf-status-line--<?php echo esc_attr( $period['state'] ); ?>"><?php echo esc_html( MVF_Periods::message( $period ) ); ?></p>
				<table class="widefat striped mvf-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Categoria', 'mvf-voting' ); ?></th>
							<th><?php esc_html_e( 'Clipes', 'mvf-voting' ); ?></th>
							<th><?php esc_html_e( 'Status', 'mvf-voting' ); ?></th>
							<th></th>
						</tr>
					</thead>
					<tbody>
						<?php
						foreach ( $cats as $cat ) :
							$ballot = self::get_ballot( $cat->term_id, $juror_id );
							$url    = admin_url( 'admin.php?page=mvf-jury&categoria=' . $cat->term_id );
							?>
							<tr>
								<td><strong><?php echo esc_html( $cat->name ); ?></strong></td>
								<td><?php echo esc_html( (string) count( mvf_get_category_clip_ids( $cat->term_id ) ) ); ?></td>
								<td><?php echo self::status_label( $ballot ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
								<td>
									<a class="button <?php echo ( $period['open'] && ( ! $ballot || 'submitted' !== $ballot['status'] ) ) ? 'button-primary' : ''; ?>" href="<?php echo esc_url( $url ); ?>">
										<?php echo $period['open'] ? esc_html__( 'Votar', 'mvf-voting' ) : esc_html__( 'Ver', 'mvf-voting' ); ?>
									</a>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endforeach; ?>
		</div>
		<?php
	}

	private static function render_ballot( int $category_id ): void {
		$juror_id = get_current_user_id();
		$category = get_term( $category_id, MVF_TAX_CAT );
		if ( ! $category || is_wp_error( $category ) || ! mvf_juror_is_assigned( $juror_id, $category_id ) ) {
			wp_die( esc_html__( 'Você não é jurado desta categoria.', 'mvf-voting' ), '', array( 'back_link' => true ) );
		}
		$edition_id = mvf_category_edition_id( $category_id );
		$edition    = get_term( $edition_id, MVF_TAX_EDITION );
		$period     = MVF_Periods::status( $edition_id, 'jury' );
		$ballot     = self::get_ballot( $category_id, $juror_id );
		$clips      = self::ordered_clips( $category_id, $ballot );
		$readonly   = ! $period['open'];
		$submitted  = $ballot && 'submitted' === $ballot['status'];
		$changed    = $ballot && ! self::ballot_matches_clips( $ballot, $category_id );
		?>
		<div class="wrap mvf-admin mvf-ballot" data-category="<?php echo esc_attr( $category_id ); ?>" data-status="<?php echo esc_attr( $ballot['status'] ?? 'none' ); ?>" data-readonly="<?php echo $readonly ? '1' : '0'; ?>">
			<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=mvf-jury' ) ); ?>">&larr; <?php esc_html_e( 'Minhas categorias', 'mvf-voting' ); ?></a></p>
			<h1>
				<?php echo esc_html( $category->name ); ?>
				<?php if ( $edition && ! is_wp_error( $edition ) ) : ?>
					<span class="mvf-subtitle"><?php echo esc_html( $edition->name ); ?></span>
				<?php endif; ?>
			</h1>
			<p class="mvf-status-line mvf-status-line--<?php echo esc_attr( $period['state'] ); ?>"><?php echo esc_html( MVF_Periods::message( $period ) ); ?></p>
			<p class="mvf-ballot-status"><?php echo self::status_label( $ballot ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>

			<?php if ( $changed && ! $readonly ) : ?>
				<div class="notice notice-warning inline"><p><?php esc_html_e( 'Os clipes desta categoria mudaram desde a sua última gravação. Revise a ordem e envie novamente.', 'mvf-voting' ); ?></p></div>
			<?php endif; ?>

			<?php if ( ! $clips ) : ?>
				<p><?php esc_html_e( 'Esta categoria ainda não tem clipes publicados.', 'mvf-voting' ); ?></p>
			<?php else : ?>
				<?php if ( ! $readonly ) : ?>
					<p class="description"><?php esc_html_e( 'Arraste os clipes (ou use as setas) para ordenar do seu favorito (1º lugar) até o último. Clique na imagem para assistir.', 'mvf-voting' ); ?></p>
				<?php endif; ?>

				<ol class="mvf-ballot-list" id="mvf-ballot-list">
					<?php
					foreach ( $clips as $i => $clip_id ) :
						$yt  = (string) get_post_meta( $clip_id, 'youtube_id', true );
						$pos = $i + 1;
						?>
						<li class="mvf-ballot-item" data-clip="<?php echo esc_attr( $clip_id ); ?>">
							<span class="mvf-ballot-item__handle" aria-hidden="true"><?php echo $readonly ? '' : '⠿'; ?></span>
							<span class="mvf-ballot-item__pos"><?php echo esc_html( $pos . 'º' ); ?></span>
							<span class="mvf-ballot-item__pts"><?php echo esc_html( mvf_points_for_position( $pos ) . ' ' . __( 'pts', 'mvf-voting' ) ); ?></span>
							<button type="button" class="mvf-ballot-item__thumb" data-youtube="<?php echo esc_attr( $yt ); ?>" title="<?php esc_attr_e( 'Assistir', 'mvf-voting' ); ?>">
								<img src="<?php echo esc_url( mvf_clip_thumb_url( $clip_id, 'thumbnail' ) ); ?>" alt="">
							</button>
							<span class="mvf-ballot-item__info">
								<strong><?php echo esc_html( get_the_title( $clip_id ) ); ?></strong>
								<span><?php echo esc_html( (string) get_post_meta( $clip_id, 'artista', true ) ); ?></span>
							</span>
							<?php if ( ! $readonly ) : ?>
								<span class="mvf-ballot-item__moves">
									<button type="button" class="button mvf-move" data-dir="-1" aria-label="<?php esc_attr_e( 'Subir', 'mvf-voting' ); ?>">↑</button>
									<button type="button" class="button mvf-move" data-dir="1" aria-label="<?php esc_attr_e( 'Descer', 'mvf-voting' ); ?>">↓</button>
								</span>
							<?php endif; ?>
						</li>
					<?php endforeach; ?>
				</ol>

				<?php if ( ! $readonly ) : ?>
					<p class="mvf-ballot-actions">
						<button type="button" class="button button-large mvf-save-draft" <?php echo $submitted ? 'hidden' : ''; ?>><?php esc_html_e( 'Salvar rascunho', 'mvf-voting' ); ?></button>
						<button type="button" class="button button-primary button-large mvf-submit">
							<?php echo $submitted ? esc_html__( 'Atualizar votação enviada', 'mvf-voting' ) : esc_html__( 'Enviar votação', 'mvf-voting' ); ?>
						</button>
						<span class="mvf-ballot-msg" aria-live="polite"></span>
					</p>
				<?php endif; ?>
			<?php endif; ?>

			<div class="mvf-modal" hidden>
				<div class="mvf-modal__inner" role="dialog" aria-modal="true">
					<button type="button" class="mvf-modal__close" aria-label="<?php esc_attr_e( 'Fechar', 'mvf-voting' ); ?>">&times;</button>
					<div class="mvf-modal__player"></div>
				</div>
			</div>
		</div>
		<?php
	}

	/* ---------------------------------------------------------------------
	 * Admin: atribuição de jurados
	 * ------------------------------------------------------------------- */

	public static function render_jurors_page(): void {
		if ( ! current_user_can( 'mvf_manage' ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$edition_id = isset( $_GET['edicao'] ) ? absint( $_GET['edicao'] ) : mvf_active_edition_id();
		$categories = mvf_get_edition_categories( $edition_id );
		$jurors     = mvf_get_jurors();
		?>
		<div class="wrap mvf-admin">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Jurados', 'mvf-voting' ); ?></h1>
			<a class="page-title-action" href="<?php echo esc_url( admin_url( 'user-new.php' ) ); ?>"><?php esc_html_e( 'Adicionar usuário', 'mvf-voting' ); ?></a>
			<hr class="wp-header-end">
			<?php mvf_render_notices(); ?>
			<p class="description"><?php esc_html_e( 'Jurados são usuários com a função "Jurado MVF". Marque as categorias em que cada um vota.', 'mvf-voting' ); ?></p>

			<?php MVF_Admin_Results::edition_switcher( 'mvf-jurors', $edition_id ); ?>

			<?php if ( ! $jurors ) : ?>
				<p><?php esc_html_e( 'Nenhum jurado cadastrado ainda.', 'mvf-voting' ); ?></p>
			<?php elseif ( ! $categories ) : ?>
				<p><?php esc_html_e( 'Esta edição não tem categorias.', 'mvf-voting' ); ?></p>
			<?php else : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="mvf_save_jurors">
					<input type="hidden" name="edicao" value="<?php echo esc_attr( $edition_id ); ?>">
					<?php wp_nonce_field( 'mvf_save_jurors' ); ?>
					<div class="mvf-scroll">
						<table class="widefat striped mvf-table mvf-matrix">
							<thead>
								<tr>
									<th><?php esc_html_e( 'Jurado', 'mvf-voting' ); ?></th>
									<?php foreach ( $categories as $cat ) : ?>
										<th class="mvf-center"><?php echo esc_html( $cat->name ); ?></th>
									<?php endforeach; ?>
								</tr>
							</thead>
							<tbody>
								<?php
								foreach ( $jurors as $juror ) :
									$assigned = mvf_juror_categories( $juror->ID );
									?>
									<tr>
										<td>
											<input type="hidden" name="jurors[]" value="<?php echo esc_attr( $juror->ID ); ?>">
											<strong><?php echo esc_html( $juror->display_name ); ?></strong><br>
											<small><?php echo esc_html( $juror->user_email ); ?></small>
										</td>
										<?php foreach ( $categories as $cat ) : ?>
											<td class="mvf-center">
												<input type="checkbox" name="assign[<?php echo esc_attr( $juror->ID ); ?>][]" value="<?php echo esc_attr( $cat->term_id ); ?>" <?php checked( in_array( $cat->term_id, $assigned, true ) ); ?>
													aria-label="<?php echo esc_attr( $juror->display_name . ' – ' . $cat->name ); ?>">
											</td>
										<?php endforeach; ?>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
					<?php submit_button( __( 'Salvar atribuições', 'mvf-voting' ) ); ?>
				</form>
			<?php endif; ?>
		</div>
		<?php
	}

	public static function save_jurors(): void {
		if ( ! current_user_can( 'mvf_manage' ) ) {
			wp_die( esc_html__( 'Acesso negado.', 'mvf-voting' ) );
		}
		check_admin_referer( 'mvf_save_jurors' );

		$edition_id = isset( $_POST['edicao'] ) ? absint( $_POST['edicao'] ) : 0;
		$edition_cats = wp_list_pluck( mvf_get_edition_categories( $edition_id ), 'term_id' );
		$edition_cats = array_map( 'intval', $edition_cats );
		$jurors = isset( $_POST['jurors'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['jurors'] ) ) : array();
		$assign = isset( $_POST['assign'] ) && is_array( $_POST['assign'] ) ? wp_unslash( $_POST['assign'] ) : array();

		foreach ( $jurors as $juror_id ) {
			if ( ! user_can( $juror_id, 'mvf_vote_jury' ) ) {
				continue;
			}
			// Mantém as categorias de outras edições e substitui as desta.
			$keep   = array_diff( mvf_juror_categories( $juror_id ), $edition_cats );
			$chosen = array_intersect( array_map( 'absint', (array) ( $assign[ $juror_id ] ?? array() ) ), $edition_cats );
			update_user_meta( $juror_id, 'mvf_categorias', array_values( array_unique( array_merge( $keep, $chosen ) ) ) );
		}

		mvf_add_notice( __( 'Atribuições salvas.', 'mvf-voting' ), 'success' );
		wp_safe_redirect( admin_url( 'admin.php?page=mvf-jurors&edicao=' . $edition_id ) );
		exit;
	}

	/* ---------------------------------------------------------------------
	 * Perfil do usuário
	 * ------------------------------------------------------------------- */

	public static function profile_fields( WP_User $user ): void {
		if ( ! current_user_can( 'mvf_manage' ) || ! user_can( $user, 'mvf_vote_jury' ) ) {
			return;
		}
		$assigned = mvf_juror_categories( $user->ID );
		?>
		<h2><?php esc_html_e( 'MVF: categorias do jurado', 'mvf-voting' ); ?></h2>
		<?php wp_nonce_field( 'mvf_profile', 'mvf_profile_nonce' ); ?>
		<table class="form-table" role="presentation">
			<?php foreach ( mvf_get_editions() as $ed ) : ?>
				<tr>
					<th scope="row"><?php echo esc_html( $ed->name ); ?></th>
					<td>
						<?php foreach ( mvf_get_edition_categories( $ed->term_id ) as $cat ) : ?>
							<label style="display:block">
								<input type="checkbox" name="mvf_categorias[]" value="<?php echo esc_attr( $cat->term_id ); ?>" <?php checked( in_array( $cat->term_id, $assigned, true ) ); ?>>
								<?php echo esc_html( $cat->name ); ?>
							</label>
						<?php endforeach; ?>
					</td>
				</tr>
			<?php endforeach; ?>
		</table>
		<?php
	}

	public static function profile_save( int $user_id ): void {
		if ( ! current_user_can( 'mvf_manage' ) || ! isset( $_POST['mvf_profile_nonce'] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['mvf_profile_nonce'] ) ), 'mvf_profile' ) ) {
			return;
		}
		$cats = isset( $_POST['mvf_categorias'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['mvf_categorias'] ) ) : array();
		$cats = array_values( array_filter( $cats, static fn( $id ) => term_exists( $id, MVF_TAX_CAT ) ) );
		update_user_meta( $user_id, 'mvf_categorias', $cats );
	}

	/**
	 * Jurado cai direto na tela de votação ao fazer login.
	 */
	public static function login_redirect( $redirect_to, $requested, $user ) {
		if ( $user instanceof WP_User && user_can( $user, 'mvf_vote_jury' ) && ! user_can( $user, 'mvf_manage' ) ) {
			if ( ! $requested || admin_url() === $requested || admin_url( 'index.php' ) === $requested ) {
				return admin_url( 'admin.php?page=mvf-jury' );
			}
		}
		return $redirect_to;
	}
}
