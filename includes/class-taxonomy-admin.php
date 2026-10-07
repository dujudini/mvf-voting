<?php
/**
 * Campos extras de Edições (períodos de votação) e Categorias (edição, limite de clipes, ordem).
 */

defined( 'ABSPATH' ) || exit;

class MVF_Taxonomy_Admin {

	public static function init(): void {
		// Edições.
		add_action( MVF_TAX_EDITION . '_add_form_fields', array( __CLASS__, 'edition_add_fields' ) );
		add_action( MVF_TAX_EDITION . '_edit_form_fields', array( __CLASS__, 'edition_edit_fields' ) );
		add_action( 'created_' . MVF_TAX_EDITION, array( __CLASS__, 'edition_created' ) );
		add_action( 'edited_' . MVF_TAX_EDITION, array( __CLASS__, 'edition_save' ) );
		add_filter( 'manage_edit-' . MVF_TAX_EDITION . '_columns', array( __CLASS__, 'edition_columns' ) );
		add_filter( 'manage_' . MVF_TAX_EDITION . '_custom_column', array( __CLASS__, 'edition_column' ), 10, 3 );

		// Categorias.
		add_action( MVF_TAX_CAT . '_add_form_fields', array( __CLASS__, 'category_add_fields' ) );
		add_action( MVF_TAX_CAT . '_edit_form_fields', array( __CLASS__, 'category_edit_fields' ) );
		add_action( 'created_' . MVF_TAX_CAT, array( __CLASS__, 'category_save' ) );
		add_action( 'edited_' . MVF_TAX_CAT, array( __CLASS__, 'category_save' ) );
		add_filter( 'manage_edit-' . MVF_TAX_CAT . '_columns', array( __CLASS__, 'category_columns' ) );
		add_filter( 'manage_' . MVF_TAX_CAT . '_custom_column', array( __CLASS__, 'category_column' ), 10, 3 );
		add_filter( 'pre_insert_term', array( __CLASS__, 'require_category_edition' ), 10, 2 );

		// O mesmo nome de categoria pode existir em edições diferentes: gera slug "nome-edicao".
		add_action( 'wp_ajax_add-tag', array( __CLASS__, 'prepare_category_slug' ), 0 );
		add_action( 'load-edit-tags.php', array( __CLASS__, 'prepare_category_slug' ) );
		add_action( 'load-edit-tags.php', array( __CLASS__, 'category_list_filter' ) );
	}

	/* ---------------------------------------------------------------------
	 * Edições
	 * ------------------------------------------------------------------- */

	private static function period_fields(): array {
		return array(
			'public' => __( 'Votação do público', 'mvf-voting' ),
			'jury'   => __( 'Votação do júri', 'mvf-voting' ),
		);
	}

	private static function datetime_input_value( int $term_id, string $key ): string {
		$v = (string) get_term_meta( $term_id, $key, true );
		return $v ? str_replace( ' ', 'T', $v ) : '';
	}

	private static function render_period_controls( int $term_id, string $type ): void {
		$status = $term_id ? (string) get_term_meta( $term_id, $type . '_status', true ) : 'auto';
		?>
		<div class="mvf-period">
			<label><?php esc_html_e( 'Início', 'mvf-voting' ); ?>
				<input type="datetime-local" name="mvf_period[<?php echo esc_attr( $type ); ?>_start]" value="<?php echo esc_attr( self::datetime_input_value( $term_id, $type . '_start' ) ); ?>">
			</label>
			<label><?php esc_html_e( 'Fim', 'mvf-voting' ); ?>
				<input type="datetime-local" name="mvf_period[<?php echo esc_attr( $type ); ?>_end]" value="<?php echo esc_attr( self::datetime_input_value( $term_id, $type . '_end' ) ); ?>">
			</label>
			<label><?php esc_html_e( 'Status', 'mvf-voting' ); ?>
				<select name="mvf_period[<?php echo esc_attr( $type ); ?>_status]">
					<option value="auto" <?php selected( $status, 'auto' ); ?>><?php esc_html_e( 'Automático (pelas datas)', 'mvf-voting' ); ?></option>
					<option value="open" <?php selected( $status, 'open' ); ?>><?php esc_html_e( 'Forçar aberta', 'mvf-voting' ); ?></option>
					<option value="closed" <?php selected( $status, 'closed' ); ?>><?php esc_html_e( 'Forçar fechada', 'mvf-voting' ); ?></option>
				</select>
			</label>
		</div>
		<?php
	}

	public static function edition_add_fields(): void {
		wp_nonce_field( 'mvf_term', 'mvf_term_nonce' );
		foreach ( self::period_fields() as $type => $label ) {
			echo '<div class="form-field"><label>' . esc_html( $label ) . '</label>';
			self::render_period_controls( 0, $type );
			echo '</div>';
		}
		$editions = mvf_get_editions();
		if ( $editions ) {
			?>
			<div class="form-field">
				<label for="mvf-clone-from"><?php esc_html_e( 'Copiar categorias de', 'mvf-voting' ); ?></label>
				<select id="mvf-clone-from" name="mvf_clone_from">
					<option value="0"><?php esc_html_e( '— não copiar —', 'mvf-voting' ); ?></option>
					<?php foreach ( $editions as $ed ) : ?>
						<option value="<?php echo esc_attr( $ed->term_id ); ?>"><?php echo esc_html( $ed->name ); ?></option>
					<?php endforeach; ?>
				</select>
				<p><?php esc_html_e( 'Cria nesta edição as mesmas categorias (nome, limite e ordem), sem os clipes.', 'mvf-voting' ); ?></p>
			</div>
			<?php
		}
		echo '<p class="description">' . esc_html__( 'Datas no fuso horário do site. Sem datas e com status automático, a votação fica aberta.', 'mvf-voting' ) . '</p>';
	}

	public static function edition_edit_fields( WP_Term $term ): void {
		wp_nonce_field( 'mvf_term', 'mvf_term_nonce' );
		foreach ( self::period_fields() as $type => $label ) {
			$status = MVF_Periods::status( $term->term_id, $type );
			?>
			<tr class="form-field">
				<th scope="row"><?php echo esc_html( $label ); ?></th>
				<td>
					<?php self::render_period_controls( $term->term_id, $type ); ?>
					<p class="description"><?php echo esc_html( __( 'Agora:', 'mvf-voting' ) . ' ' . MVF_Periods::message( $status ) ); ?></p>
				</td>
			</tr>
			<?php
		}
	}

	private static function verify_nonce(): bool {
		return isset( $_POST['mvf_term_nonce'] )
			&& wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['mvf_term_nonce'] ) ), 'mvf_term' )
			&& current_user_can( 'mvf_manage' );
	}

	public static function edition_save( int $term_id ): void {
		if ( ! self::verify_nonce() ) {
			return;
		}
		$input = isset( $_POST['mvf_period'] ) && is_array( $_POST['mvf_period'] ) ? wp_unslash( $_POST['mvf_period'] ) : array();
		foreach ( array_keys( self::period_fields() ) as $type ) {
			foreach ( array( 'start', 'end' ) as $edge ) {
				$raw = sanitize_text_field( $input[ $type . '_' . $edge ] ?? '' );
				$val = preg_match( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}/', $raw ) ? str_replace( 'T', ' ', substr( $raw, 0, 16 ) ) : '';
				update_term_meta( $term_id, $type . '_' . $edge, $val );
			}
			$status = sanitize_key( $input[ $type . '_status' ] ?? 'auto' );
			update_term_meta( $term_id, $type . '_status', in_array( $status, array( 'auto', 'open', 'closed' ), true ) ? $status : 'auto' );
		}
	}

	public static function edition_created( int $term_id ): void {
		self::edition_save( $term_id );
		if ( ! self::verify_nonce() || empty( $_POST['mvf_clone_from'] ) ) {
			return;
		}
		$source  = absint( $_POST['mvf_clone_from'] );
		$edition = get_term( $term_id, MVF_TAX_EDITION );
		foreach ( mvf_get_edition_categories( $source ) as $cat ) {
			$new = wp_insert_term(
				$cat->name,
				MVF_TAX_CAT,
				array(
					'slug'        => sanitize_title( $cat->name . '-' . $edition->slug ),
					'description' => $cat->description,
				)
			);
			if ( is_wp_error( $new ) ) {
				continue;
			}
			update_term_meta( $new['term_id'], 'edicao_id', $term_id );
			update_term_meta( $new['term_id'], 'max_clips', mvf_category_max_clips( $cat->term_id ) );
			update_term_meta( $new['term_id'], 'ordem', (int) get_term_meta( $cat->term_id, 'ordem', true ) );
		}
	}

	public static function edition_columns( array $columns ): array {
		unset( $columns['description'], $columns['posts'] );
		$columns['mvf_public']     = __( 'Público', 'mvf-voting' );
		$columns['mvf_jury']       = __( 'Júri', 'mvf-voting' );
		$columns['mvf_categories'] = __( 'Categorias', 'mvf-voting' );
		$columns['posts']          = __( 'Clipes', 'mvf-voting' );
		return $columns;
	}

	public static function edition_column( $content, string $column, int $term_id ) {
		switch ( $column ) {
			case 'mvf_public':
			case 'mvf_jury':
				$status = MVF_Periods::status( $term_id, 'mvf_public' === $column ? 'public' : 'jury' );
				return sprintf(
					'<span class="mvf-badge mvf-badge--%s">%s</span>',
					esc_attr( $status['state'] ),
					esc_html( MVF_Periods::message( $status ) )
				);
			case 'mvf_categories':
				$count = count( mvf_get_edition_categories( $term_id ) );
				$url   = admin_url( 'edit-tags.php?taxonomy=' . MVF_TAX_CAT . '&post_type=' . MVF_CLIP_TYPE . '&mvf_ed=' . $term_id );
				$out   = sprintf( '<a href="%s">%d</a>', esc_url( $url ), $count );
				if ( mvf_active_edition_id() === $term_id ) {
					$out .= ' <span class="mvf-badge mvf-badge--open">' . esc_html__( 'ativa', 'mvf-voting' ) . '</span>';
				}
				return $out;
		}
		return $content;
	}

	/* ---------------------------------------------------------------------
	 * Categorias
	 * ------------------------------------------------------------------- */

	private static function edition_select( int $selected ): void {
		$editions = mvf_get_editions();
		if ( ! $selected ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$selected = isset( $_GET['mvf_ed'] ) ? absint( $_GET['mvf_ed'] ) : mvf_active_edition_id();
		}
		echo '<select name="mvf_edicao_id" id="mvf-edicao-id" required>';
		echo '<option value="">' . esc_html__( '— selecione —', 'mvf-voting' ) . '</option>';
		foreach ( $editions as $ed ) {
			printf( '<option value="%d" %s>%s</option>', (int) $ed->term_id, selected( $selected, $ed->term_id, false ), esc_html( $ed->name ) );
		}
		echo '</select>';
	}

	public static function category_add_fields(): void {
		wp_nonce_field( 'mvf_term', 'mvf_term_nonce' );
		?>
		<div class="form-field form-required">
			<label for="mvf-edicao-id"><?php esc_html_e( 'Edição', 'mvf-voting' ); ?></label>
			<?php self::edition_select( 0 ); ?>
		</div>
		<div class="form-field">
			<label for="mvf-max-clips"><?php esc_html_e( 'Quantidade de clipes', 'mvf-voting' ); ?></label>
			<input type="number" id="mvf-max-clips" name="mvf_max_clips" min="1" max="<?php echo esc_attr( MVF_MAX_CLIPS ); ?>" value="<?php echo esc_attr( MVF_MAX_CLIPS ); ?>">
			<p><?php esc_html_e( 'Máximo de clipes concorrendo nesta categoria (1 a 10).', 'mvf-voting' ); ?></p>
		</div>
		<div class="form-field">
			<label for="mvf-ordem"><?php esc_html_e( 'Ordem de exibição', 'mvf-voting' ); ?></label>
			<input type="number" id="mvf-ordem" name="mvf_ordem" value="0">
		</div>
		<?php
	}

	public static function category_edit_fields( WP_Term $term ): void {
		wp_nonce_field( 'mvf_term', 'mvf_term_nonce' );
		$count = mvf_category_clip_count( $term->term_id );
		?>
		<tr class="form-field form-required">
			<th scope="row"><label for="mvf-edicao-id"><?php esc_html_e( 'Edição', 'mvf-voting' ); ?></label></th>
			<td><?php self::edition_select( mvf_category_edition_id( $term->term_id ) ); ?></td>
		</tr>
		<tr class="form-field">
			<th scope="row"><label for="mvf-max-clips"><?php esc_html_e( 'Quantidade de clipes', 'mvf-voting' ); ?></label></th>
			<td>
				<input type="number" id="mvf-max-clips" name="mvf_max_clips" min="1" max="<?php echo esc_attr( MVF_MAX_CLIPS ); ?>" value="<?php echo esc_attr( mvf_category_max_clips( $term->term_id ) ); ?>">
				<p class="description">
					<?php
					/* translators: %d: número de clipes */
					echo esc_html( sprintf( __( 'Atualmente com %d clipe(s). Reduzir o limite não remove clipes já cadastrados.', 'mvf-voting' ), $count ) );
					?>
				</p>
			</td>
		</tr>
		<tr class="form-field">
			<th scope="row"><label for="mvf-ordem"><?php esc_html_e( 'Ordem de exibição', 'mvf-voting' ); ?></label></th>
			<td><input type="number" id="mvf-ordem" name="mvf_ordem" value="<?php echo esc_attr( (int) get_term_meta( $term->term_id, 'ordem', true ) ); ?>"></td>
		</tr>
		<?php
	}

	public static function category_save( int $term_id ): void {
		if ( ! self::verify_nonce() || ! isset( $_POST['mvf_edicao_id'] ) ) {
			return;
		}
		update_term_meta( $term_id, 'edicao_id', absint( $_POST['mvf_edicao_id'] ) );
		$max = isset( $_POST['mvf_max_clips'] ) ? absint( $_POST['mvf_max_clips'] ) : MVF_MAX_CLIPS;
		update_term_meta( $term_id, 'max_clips', max( 1, min( MVF_MAX_CLIPS, $max ) ) );
		update_term_meta( $term_id, 'ordem', isset( $_POST['mvf_ordem'] ) ? (int) $_POST['mvf_ordem'] : 0 );
	}

	/**
	 * Bloqueia criar categoria pelo formulário sem escolher a edição.
	 */
	public static function require_category_edition( $term, $taxonomy ) {
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		if ( MVF_TAX_CAT === $taxonomy && isset( $_POST['taxonomy'] ) && MVF_TAX_CAT === $_POST['taxonomy'] && isset( $_POST['mvf_term_nonce'] ) && empty( $_POST['mvf_edicao_id'] ) ) {
			return new WP_Error( 'mvf_no_edition', __( 'Selecione a edição da categoria.', 'mvf-voting' ) );
		}
		// phpcs:enable
		return $term;
	}

	public static function prepare_category_slug(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- o próprio WP verifica o nonce de add-tag logo em seguida.
		if ( ! isset( $_POST['action'], $_POST['taxonomy'] ) || 'add-tag' !== $_POST['action'] || MVF_TAX_CAT !== $_POST['taxonomy'] ) {
			return;
		}
		if ( ! empty( $_POST['slug'] ) || empty( $_POST['tag-name'] ) || empty( $_POST['mvf_edicao_id'] ) ) {
			return;
		}
		$edition = get_term( absint( $_POST['mvf_edicao_id'] ), MVF_TAX_EDITION );
		if ( $edition && ! is_wp_error( $edition ) ) {
			$_POST['slug'] = sanitize_title( wp_unslash( $_POST['tag-name'] ) . '-' . $edition->slug );
		}
		// phpcs:enable
	}

	public static function category_columns( array $columns ): array {
		unset( $columns['description'], $columns['posts'] );
		$columns['mvf_edition'] = __( 'Edição', 'mvf-voting' );
		$columns['mvf_clips']   = __( 'Clipes', 'mvf-voting' );
		$columns['mvf_order']   = __( 'Ordem', 'mvf-voting' );
		return $columns;
	}

	public static function category_column( $content, string $column, int $term_id ) {
		switch ( $column ) {
			case 'mvf_edition':
				$ed = get_term( mvf_category_edition_id( $term_id ), MVF_TAX_EDITION );
				return ( $ed && ! is_wp_error( $ed ) ) ? esc_html( $ed->name ) : '<em>' . esc_html__( 'sem edição', 'mvf-voting' ) . '</em>';
			case 'mvf_clips':
				$count = mvf_category_clip_count( $term_id );
				$max   = mvf_category_max_clips( $term_id );
				$term  = get_term( $term_id, MVF_TAX_CAT );
				$url   = admin_url( 'edit.php?post_type=' . MVF_CLIP_TYPE . '&' . MVF_TAX_CAT . '=' . $term->slug );
				return sprintf( '<a href="%s" class="%s">%d / %d</a>', esc_url( $url ), $count >= $max ? 'mvf-full' : '', $count, $max );
			case 'mvf_order':
				return (string) (int) get_term_meta( $term_id, 'ordem', true );
		}
		return $content;
	}

	/**
	 * Filtro por edição na listagem de categorias (?mvf_ed=ID).
	 */
	public static function category_list_filter(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_GET['taxonomy'] ) || MVF_TAX_CAT !== $_GET['taxonomy'] ) {
			return;
		}
		$ed = isset( $_GET['mvf_ed'] ) ? absint( $_GET['mvf_ed'] ) : 0;
		// phpcs:enable
		if ( $ed ) {
			add_filter(
				'get_terms_args',
				static function ( $args, $taxonomies ) use ( $ed ) {
					if ( in_array( MVF_TAX_CAT, (array) $taxonomies, true ) && empty( $args['meta_query'] ) ) {
						$args['meta_query'] = array(
							array(
								'key'   => 'edicao_id',
								'value' => $ed,
							),
						);
					}
					return $args;
				},
				10,
				2
			);
		}
		add_action(
			'all_admin_notices',
			static function () use ( $ed ) {
				?>
				<form method="get" class="mvf-inline-filter">
					<input type="hidden" name="taxonomy" value="<?php echo esc_attr( MVF_TAX_CAT ); ?>">
					<input type="hidden" name="post_type" value="<?php echo esc_attr( MVF_CLIP_TYPE ); ?>">
					<label for="mvf-filter-ed"><?php esc_html_e( 'Mostrar categorias da edição:', 'mvf-voting' ); ?></label>
					<select id="mvf-filter-ed" name="mvf_ed" onchange="this.form.submit()">
						<option value="0"><?php esc_html_e( 'Todas', 'mvf-voting' ); ?></option>
						<?php foreach ( mvf_get_editions() as $edition ) : ?>
							<option value="<?php echo esc_attr( $edition->term_id ); ?>" <?php selected( $ed, $edition->term_id ); ?>><?php echo esc_html( $edition->name ); ?></option>
						<?php endforeach; ?>
					</select>
				</form>
				<?php
			}
		);
	}
}
