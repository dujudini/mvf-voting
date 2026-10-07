<?php
/**
 * Telas de admin: Resultados, Progresso do júri e Auditoria de votos.
 */

defined( 'ABSPATH' ) || exit;

class MVF_Admin_Results {

	const PER_PAGE = 50;

	public static function init(): void {
		add_action( 'admin_post_mvf_audit', array( __CLASS__, 'audit_action' ) );
	}

	/**
	 * Seletor de edição (GET) reaproveitado nas telas.
	 */
	public static function edition_switcher( string $page, int $edition_id, array $keep = array() ): void {
		$editions = mvf_get_editions();
		if ( ! $editions ) {
			echo '<p>' . esc_html__( 'Nenhuma edição cadastrada.', 'mvf-voting' ) . '</p>';
			return;
		}
		?>
		<form method="get" class="mvf-inline-filter">
			<input type="hidden" name="page" value="<?php echo esc_attr( $page ); ?>">
			<?php foreach ( $keep as $k => $v ) : ?>
				<input type="hidden" name="<?php echo esc_attr( $k ); ?>" value="<?php echo esc_attr( $v ); ?>">
			<?php endforeach; ?>
			<label for="mvf-ed-switch"><?php esc_html_e( 'Edição:', 'mvf-voting' ); ?></label>
			<select id="mvf-ed-switch" name="edicao" onchange="this.form.submit()">
				<?php foreach ( $editions as $ed ) : ?>
					<option value="<?php echo esc_attr( $ed->term_id ); ?>" <?php selected( $edition_id, $ed->term_id ); ?>><?php echo esc_html( $ed->name ); ?></option>
				<?php endforeach; ?>
			</select>
			<noscript><button class="button"><?php esc_html_e( 'Filtrar', 'mvf-voting' ); ?></button></noscript>
		</form>
		<?php
	}

	private static function get_int( string $key, int $default = 0 ): int {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return isset( $_GET[ $key ] ) ? absint( $_GET[ $key ] ) : $default;
	}

	private static function get_str( string $key, string $default = '' ): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return isset( $_GET[ $key ] ) ? sanitize_text_field( wp_unslash( $_GET[ $key ] ) ) : $default;
	}

	private static function export_url( string $type, int $edition_id, int $category_id ): string {
		return wp_nonce_url(
			admin_url( 'admin-post.php?action=mvf_export&tipo=' . $type . '&edicao=' . $edition_id . '&categoria=' . $category_id ),
			'mvf_export'
		);
	}

	/* ---------------------------------------------------------------------
	 * Resultados
	 * ------------------------------------------------------------------- */

	public static function render_results(): void {
		if ( ! current_user_can( 'mvf_manage' ) ) {
			return;
		}
		$edition_id  = self::get_int( 'edicao', mvf_active_edition_id() );
		$type        = 'jury' === self::get_str( 'tipo' ) ? 'jury' : 'public';
		$category_id = self::get_int( 'categoria' );
		$categories  = mvf_get_edition_categories( $edition_id );
		$shown       = $category_id ? array_values( array_filter( $categories, static fn( $c ) => $c->term_id === $category_id ) ) : $categories;
		$base        = admin_url( 'admin.php?page=mvf&edicao=' . $edition_id . '&categoria=' . $category_id );
		?>
		<div class="wrap mvf-admin">
			<h1><?php esc_html_e( 'Resultados', 'mvf-voting' ); ?></h1>
			<p class="description"><?php esc_html_e( 'Os rankings são visíveis apenas aqui no painel; nada é exibido no site.', 'mvf-voting' ); ?></p>

			<?php if ( ! $edition_id ) : ?>
				<p><?php esc_html_e( 'Cadastre uma edição para começar.', 'mvf-voting' ); ?></p>
			</div>
				<?php
				return;
			endif;
			?>

			<form method="get" class="mvf-inline-filter">
				<input type="hidden" name="page" value="mvf">
				<input type="hidden" name="tipo" value="<?php echo esc_attr( $type ); ?>">
				<label for="mvf-r-ed"><?php esc_html_e( 'Edição:', 'mvf-voting' ); ?></label>
				<select id="mvf-r-ed" name="edicao" onchange="this.form.categoria.value=0;this.form.submit()">
					<?php foreach ( mvf_get_editions() as $ed ) : ?>
						<option value="<?php echo esc_attr( $ed->term_id ); ?>" <?php selected( $edition_id, $ed->term_id ); ?>><?php echo esc_html( $ed->name ); ?></option>
					<?php endforeach; ?>
				</select>
				<label for="mvf-r-cat"><?php esc_html_e( 'Categoria:', 'mvf-voting' ); ?></label>
				<select id="mvf-r-cat" name="categoria" onchange="this.form.submit()">
					<option value="0"><?php esc_html_e( 'Todas', 'mvf-voting' ); ?></option>
					<?php foreach ( $categories as $cat ) : ?>
						<option value="<?php echo esc_attr( $cat->term_id ); ?>" <?php selected( $category_id, $cat->term_id ); ?>><?php echo esc_html( $cat->name ); ?></option>
					<?php endforeach; ?>
				</select>
				<noscript><button class="button"><?php esc_html_e( 'Filtrar', 'mvf-voting' ); ?></button></noscript>
			</form>

			<nav class="nav-tab-wrapper">
				<a href="<?php echo esc_url( $base . '&tipo=public' ); ?>" class="nav-tab <?php echo 'public' === $type ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Voto do público', 'mvf-voting' ); ?></a>
				<a href="<?php echo esc_url( $base . '&tipo=jury' ); ?>" class="nav-tab <?php echo 'jury' === $type ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Voto do júri', 'mvf-voting' ); ?></a>
			</nav>

			<p class="mvf-export-links">
				<a class="button" href="<?php echo esc_url( self::export_url( $type, $edition_id, $category_id ) ); ?>"><?php esc_html_e( 'Exportar este ranking (CSV)', 'mvf-voting' ); ?></a>
				<?php if ( 'public' === $type ) : ?>
					<a class="button" href="<?php echo esc_url( self::export_url( 'votes', $edition_id, $category_id ) ); ?>"><?php esc_html_e( 'Exportar votos brutos (CSV)', 'mvf-voting' ); ?></a>
				<?php endif; ?>
			</p>

			<?php if ( ! $shown ) : ?>
				<p><?php esc_html_e( 'Nenhuma categoria nesta edição.', 'mvf-voting' ); ?></p>
			<?php endif; ?>

			<?php
			foreach ( $shown as $cat ) {
				if ( 'jury' === $type ) {
					self::render_jury_table( $cat );
				} else {
					self::render_public_table( $cat );
				}
			}
			?>
		</div>
		<?php
	}

	private static function render_public_table( WP_Term $cat ): void {
		$ranking = MVF_Results::public_ranking( $cat->term_id );
		$top     = $ranking['rows'] ? max( 1, $ranking['rows'][0]['votes'] ) : 1;
		?>
		<h2 class="mvf-cat-heading">
			<?php echo esc_html( $cat->name ); ?>
			<span class="mvf-subtitle">
				<?php
				/* translators: %s: número de votos */
				echo esc_html( sprintf( _n( '%s voto válido', '%s votos válidos', $ranking['total'], 'mvf-voting' ), number_format_i18n( $ranking['total'] ) ) );
				?>
			</span>
		</h2>
		<table class="widefat striped mvf-table mvf-results">
			<thead>
				<tr>
					<th class="mvf-col-rank"><?php esc_html_e( 'Pos.', 'mvf-voting' ); ?></th>
					<th><?php esc_html_e( 'Clipe', 'mvf-voting' ); ?></th>
					<th><?php esc_html_e( 'Artista', 'mvf-voting' ); ?></th>
					<th class="mvf-col-num"><?php esc_html_e( 'Votos', 'mvf-voting' ); ?></th>
					<th class="mvf-col-bar">%</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $ranking['rows'] as $row ) : ?>
					<tr>
						<td class="mvf-col-rank"><?php echo esc_html( $row['rank'] . 'º' ); ?></td>
						<td><a href="<?php echo esc_url( (string) get_edit_post_link( $row['clip_id'] ) ); ?>"><?php echo esc_html( $row['title'] ); ?></a></td>
						<td><?php echo esc_html( (string) get_post_meta( $row['clip_id'], 'artista', true ) ); ?></td>
						<td class="mvf-col-num"><?php echo esc_html( number_format_i18n( $row['votes'] ) ); ?></td>
						<td class="mvf-col-bar">
							<span class="mvf-bar"><span style="width:<?php echo esc_attr( (string) round( 100 * $row['votes'] / $top, 1 ) ); ?>%"></span></span>
							<?php echo esc_html( number_format_i18n( $row['percent'], 1 ) . '%' ); ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	private static function render_jury_table( WP_Term $cat ): void {
		$ranking  = MVF_Results::jury_ranking( $cat->term_id );
		$assigned = array_filter( mvf_get_jurors(), static fn( $u ) => in_array( $cat->term_id, mvf_juror_categories( $u->ID ), true ) );
		$sent     = count( $ranking['ballots'] );
		$cols     = min( $ranking['positions'], MVF_MAX_CLIPS );
		$jurors   = array();
		foreach ( array_keys( $ranking['ballots'] ) as $juror_id ) {
			$u                   = get_userdata( $juror_id );
			$jurors[ $juror_id ] = $u ? $u->display_name : '#' . $juror_id;
		}
		?>
		<h2 class="mvf-cat-heading">
			<?php echo esc_html( $cat->name ); ?>
			<span class="mvf-subtitle">
				<?php
				/* translators: 1: enviadas, 2: designados */
				echo esc_html( sprintf( __( '%1$d de %2$d jurado(s) enviaram', 'mvf-voting' ), $sent, count( $assigned ) ) );
				?>
			</span>
		</h2>
		<div class="mvf-scroll">
			<table class="widefat striped mvf-table mvf-results">
				<thead>
					<tr>
						<th class="mvf-col-rank"><?php esc_html_e( 'Pos.', 'mvf-voting' ); ?></th>
						<th><?php esc_html_e( 'Clipe', 'mvf-voting' ); ?></th>
						<th class="mvf-col-num"><?php esc_html_e( 'Pontos', 'mvf-voting' ); ?></th>
						<?php for ( $p = 1; $p <= $cols; $p++ ) : ?>
							<th class="mvf-col-num" title="<?php echo esc_attr( sprintf( /* translators: %d: posição */ __( 'Vezes em %dº lugar', 'mvf-voting' ), $p ) ); ?>"><?php echo esc_html( $p . 'º' ); ?></th>
						<?php endfor; ?>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $ranking['rows'] as $row ) : ?>
						<tr>
							<td class="mvf-col-rank"><?php echo esc_html( $row['rank'] . 'º' ); ?></td>
							<td><a href="<?php echo esc_url( (string) get_edit_post_link( $row['clip_id'] ) ); ?>"><?php echo esc_html( $row['title'] ); ?></a></td>
							<td class="mvf-col-num"><strong><?php echo esc_html( (string) $row['points'] ); ?></strong></td>
							<?php for ( $p = 1; $p <= $cols; $p++ ) : ?>
								<td class="mvf-col-num mvf-muted-zero"><?php echo esc_html( (string) ( $row['placements'][ $p ] ?? 0 ) ); ?></td>
							<?php endfor; ?>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<p class="description"><?php esc_html_e( 'Desempate: quem tem mais 1º lugares; persistindo, mais 2º lugares, e assim por diante.', 'mvf-voting' ); ?></p>

		<?php if ( $jurors ) : ?>
			<details class="mvf-details">
				<summary><?php esc_html_e( 'Posição dada por cada jurado', 'mvf-voting' ); ?></summary>
				<div class="mvf-scroll">
					<table class="widefat striped mvf-table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Clipe', 'mvf-voting' ); ?></th>
								<?php foreach ( $jurors as $name ) : ?>
									<th class="mvf-col-num"><?php echo esc_html( $name ); ?></th>
								<?php endforeach; ?>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $ranking['rows'] as $row ) : ?>
								<tr>
									<td><?php echo esc_html( $row['title'] ); ?></td>
									<?php foreach ( array_keys( $jurors ) as $juror_id ) : ?>
										<td class="mvf-col-num"><?php echo isset( $row['by_juror'][ $juror_id ] ) ? esc_html( $row['by_juror'][ $juror_id ] . 'º' ) : '—'; ?></td>
									<?php endforeach; ?>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			</details>
		<?php endif; ?>
		<?php
	}

	/* ---------------------------------------------------------------------
	 * Progresso do júri
	 * ------------------------------------------------------------------- */

	public static function render_progress(): void {
		if ( ! current_user_can( 'mvf_manage' ) ) {
			return;
		}
		$edition_id = self::get_int( 'edicao', mvf_active_edition_id() );
		$categories = mvf_get_edition_categories( $edition_id );
		$jurors     = mvf_get_jurors();
		$period     = MVF_Periods::status( $edition_id, 'jury' );
		$totals     = array();
		?>
		<div class="wrap mvf-admin">
			<h1><?php esc_html_e( 'Progresso do júri', 'mvf-voting' ); ?></h1>
			<?php self::edition_switcher( 'mvf-progress', $edition_id ); ?>
			<p class="mvf-status-line mvf-status-line--<?php echo esc_attr( $period['state'] ); ?>"><?php echo esc_html( MVF_Periods::message( $period ) ); ?></p>

			<?php if ( ! $jurors || ! $categories ) : ?>
				<p><?php esc_html_e( 'Cadastre jurados e categorias para acompanhar o progresso.', 'mvf-voting' ); ?></p>
			<?php else : ?>
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
									<td><strong><?php echo esc_html( $juror->display_name ); ?></strong><br><small><?php echo esc_html( $juror->user_email ); ?></small></td>
									<?php
									foreach ( $categories as $cat ) :
										$cid = $cat->term_id;
										$totals[ $cid ] = $totals[ $cid ] ?? array(
											'assigned' => 0,
											'sent'     => 0,
										);
										if ( ! in_array( $cid, $assigned, true ) ) {
											echo '<td class="mvf-center mvf-na">—</td>';
											continue;
										}
										++$totals[ $cid ]['assigned'];
										$ballot = MVF_Jury::get_ballot( $cid, $juror->ID );
										?>
										<td class="mvf-center">
											<?php
											if ( ! $ballot ) {
												echo '<span class="mvf-badge mvf-badge--pending">' . esc_html__( 'Pendente', 'mvf-voting' ) . '</span>';
											} elseif ( 'submitted' === $ballot['status'] ) {
												++$totals[ $cid ]['sent'];
												echo '<span class="mvf-badge mvf-badge--open">' . esc_html__( 'Enviada', 'mvf-voting' ) . '</span><br><small>' . esc_html( mvf_format_gmt( $ballot['submitted_at'] ) ) . '</small>';
												if ( ! MVF_Jury::ballot_matches_clips( $ballot, $cid ) ) {
													echo '<br><small class="mvf-warn">' . esc_html__( '⚠ clipes mudaram depois do envio', 'mvf-voting' ) . '</small>';
												}
											} else {
												echo '<span class="mvf-badge mvf-badge--draft">' . esc_html__( 'Rascunho', 'mvf-voting' ) . '</span>';
											}
											?>
										</td>
									<?php endforeach; ?>
								</tr>
							<?php endforeach; ?>
						</tbody>
						<tfoot>
							<tr>
								<th><?php esc_html_e( 'Enviadas', 'mvf-voting' ); ?></th>
								<?php foreach ( $categories as $cat ) : ?>
									<?php $t = $totals[ $cat->term_id ] ?? array( 'assigned' => 0, 'sent' => 0 ); ?>
									<th class="mvf-center"><?php echo esc_html( $t['sent'] . ' / ' . $t['assigned'] ); ?></th>
								<?php endforeach; ?>
							</tr>
						</tfoot>
					</table>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/* ---------------------------------------------------------------------
	 * Auditoria de votos do público
	 * ------------------------------------------------------------------- */

	private static function audit_filters_from( array $src ): array {
		$f = array(
			'edicao'    => absint( $src['edicao'] ?? 0 ),
			'categoria' => absint( $src['categoria'] ?? 0 ),
			'clipe'     => absint( $src['clipe'] ?? 0 ),
			'ip'        => preg_replace( '/[^a-f0-9]/', '', strtolower( (string) ( $src['ip'] ?? '' ) ) ),
			'de'        => preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) ( $src['de'] ?? '' ) ) ? $src['de'] : '',
			'ate'       => preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) ( $src['ate'] ?? '' ) ) ? $src['ate'] : '',
			'status'    => in_array( (string) ( $src['status'] ?? '' ), array( 'valid', 'invalid' ), true ) ? $src['status'] : '',
		);
		if ( ! $f['edicao'] ) {
			$f['edicao'] = mvf_active_edition_id();
		}
		return $f;
	}

	/**
	 * @return array{0:string,1:array} Cláusula WHERE com placeholders e valores.
	 */
	private static function audit_where( array $f ): array {
		global $wpdb;
		$sql  = array( 'edition_id = %d' );
		$args = array( $f['edicao'] );
		if ( $f['categoria'] ) {
			$sql[]  = 'category_id = %d';
			$args[] = $f['categoria'];
		}
		if ( $f['clipe'] ) {
			$sql[]  = 'clip_id = %d';
			$args[] = $f['clipe'];
		}
		if ( $f['ip'] ) {
			$sql[]  = 'ip_hash LIKE %s';
			$args[] = $wpdb->esc_like( $f['ip'] ) . '%';
		}
		if ( $f['de'] ) {
			$sql[]  = 'created_at >= %s';
			$args[] = get_gmt_from_date( $f['de'] . ' 00:00:00' );
		}
		if ( $f['ate'] ) {
			$sql[]  = 'created_at <= %s';
			$args[] = get_gmt_from_date( $f['ate'] . ' 23:59:59' );
		}
		if ( 'valid' === $f['status'] ) {
			$sql[] = 'is_valid = 1';
		} elseif ( 'invalid' === $f['status'] ) {
			$sql[] = 'is_valid = 0';
		}
		return array( implode( ' AND ', $sql ), $args );
	}

	public static function render_audit(): void {
		global $wpdb;
		if ( ! current_user_can( 'mvf_manage' ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$f     = self::audit_filters_from( wp_unslash( $_GET ) );
		$paged = max( 1, self::get_int( 'paged', 1 ) );
		$table = mvf_table( 'public_votes' );

		list( $where, $args ) = self::audit_where( $f );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
		$summary = $wpdb->get_row( $wpdb->prepare( "SELECT COUNT(*) AS total, COALESCE(SUM(is_valid),0) AS valid, COUNT(DISTINCT ip_hash) AS ips FROM {$table} WHERE {$where}", $args ), ARRAY_A );
		$top_ips = $wpdb->get_results( $wpdb->prepare( "SELECT ip_hash, COUNT(*) AS total, COALESCE(SUM(is_valid),0) AS valid, MIN(created_at) AS first_at, MAX(created_at) AS last_at FROM {$table} WHERE {$where} GROUP BY ip_hash ORDER BY total DESC LIMIT 15", $args ), ARRAY_A );
		$hours   = $wpdb->get_results( $wpdb->prepare( "SELECT SUBSTRING(created_at, 1, 13) AS h, COUNT(*) AS total FROM {$table} WHERE {$where} GROUP BY h ORDER BY h DESC LIMIT 48", $args ), ARRAY_A );
		$votes   = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE {$where} ORDER BY id DESC LIMIT %d OFFSET %d", array_merge( $args, array( self::PER_PAGE, ( $paged - 1 ) * self::PER_PAGE ) ) ), ARRAY_A );
		// phpcs:enable

		$total      = (int) $summary['total'];
		$pages      = max( 1, (int) ceil( $total / self::PER_PAGE ) );
		$categories = mvf_get_edition_categories( $f['edicao'] );
		$cat_names  = wp_list_pluck( $categories, 'name', 'term_id' );
		$filter_qs  = http_build_query( array_filter( $f ) );
		$filter_url = static function ( array $extra ) use ( $f ) {
			return admin_url( 'admin.php?page=mvf-audit&' . http_build_query( array_filter( array_merge( $f, $extra ) ) ) );
		};
		$max_hour = $hours ? max( array_map( 'intval', wp_list_pluck( $hours, 'total' ) ) ) : 1;
		?>
		<div class="wrap mvf-admin">
			<h1><?php esc_html_e( 'Auditoria de votos do público', 'mvf-voting' ); ?></h1>
			<?php mvf_render_notices(); ?>
			<p class="description"><?php esc_html_e( 'IPs e navegadores são guardados apenas como hash. Votos invalidados deixam de contar no ranking, mas não são apagados e podem ser revalidados.', 'mvf-voting' ); ?></p>

			<form method="get" class="mvf-audit-filters">
				<input type="hidden" name="page" value="mvf-audit">
				<label><?php esc_html_e( 'Edição', 'mvf-voting' ); ?>
					<select name="edicao">
						<?php foreach ( mvf_get_editions() as $ed ) : ?>
							<option value="<?php echo esc_attr( $ed->term_id ); ?>" <?php selected( $f['edicao'], $ed->term_id ); ?>><?php echo esc_html( $ed->name ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
				<label><?php esc_html_e( 'Categoria', 'mvf-voting' ); ?>
					<select name="categoria">
						<option value="0"><?php esc_html_e( 'Todas', 'mvf-voting' ); ?></option>
						<?php foreach ( $categories as $cat ) : ?>
							<option value="<?php echo esc_attr( $cat->term_id ); ?>" <?php selected( $f['categoria'], $cat->term_id ); ?>><?php echo esc_html( $cat->name ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
				<label><?php esc_html_e( 'ID do clipe', 'mvf-voting' ); ?>
					<input type="number" name="clipe" value="<?php echo esc_attr( $f['clipe'] ?: '' ); ?>" class="small-text">
				</label>
				<label><?php esc_html_e( 'IP (hash)', 'mvf-voting' ); ?>
					<input type="text" name="ip" value="<?php echo esc_attr( $f['ip'] ); ?>" class="regular-text code" style="width:10em">
				</label>
				<label><?php esc_html_e( 'De', 'mvf-voting' ); ?> <input type="date" name="de" value="<?php echo esc_attr( $f['de'] ); ?>"></label>
				<label><?php esc_html_e( 'Até', 'mvf-voting' ); ?> <input type="date" name="ate" value="<?php echo esc_attr( $f['ate'] ); ?>"></label>
				<label><?php esc_html_e( 'Status', 'mvf-voting' ); ?>
					<select name="status">
						<option value=""><?php esc_html_e( 'Todos', 'mvf-voting' ); ?></option>
						<option value="valid" <?php selected( $f['status'], 'valid' ); ?>><?php esc_html_e( 'Válidos', 'mvf-voting' ); ?></option>
						<option value="invalid" <?php selected( $f['status'], 'invalid' ); ?>><?php esc_html_e( 'Invalidados', 'mvf-voting' ); ?></option>
					</select>
				</label>
				<button class="button"><?php esc_html_e( 'Filtrar', 'mvf-voting' ); ?></button>
				<a class="button-link" href="<?php echo esc_url( admin_url( 'admin.php?page=mvf-audit&edicao=' . $f['edicao'] ) ); ?>"><?php esc_html_e( 'Limpar', 'mvf-voting' ); ?></a>
			</form>

			<div class="mvf-cards">
				<div class="mvf-stat"><span><?php esc_html_e( 'Votos (filtro)', 'mvf-voting' ); ?></span><strong><?php echo esc_html( number_format_i18n( $total ) ); ?></strong></div>
				<div class="mvf-stat"><span><?php esc_html_e( 'Válidos', 'mvf-voting' ); ?></span><strong><?php echo esc_html( number_format_i18n( (int) $summary['valid'] ) ); ?></strong></div>
				<div class="mvf-stat"><span><?php esc_html_e( 'Invalidados', 'mvf-voting' ); ?></span><strong><?php echo esc_html( number_format_i18n( $total - (int) $summary['valid'] ) ); ?></strong></div>
				<div class="mvf-stat"><span><?php esc_html_e( 'IPs distintos', 'mvf-voting' ); ?></span><strong><?php echo esc_html( number_format_i18n( (int) $summary['ips'] ) ); ?></strong></div>
			</div>

			<div class="mvf-audit-columns">
				<div>
					<h2><?php esc_html_e( 'IPs com mais votos', 'mvf-voting' ); ?></h2>
					<table class="widefat striped mvf-table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'IP (hash)', 'mvf-voting' ); ?></th>
								<th class="mvf-col-num"><?php esc_html_e( 'Votos', 'mvf-voting' ); ?></th>
								<th class="mvf-col-num"><?php esc_html_e( 'Válidos', 'mvf-voting' ); ?></th>
								<th><?php esc_html_e( 'Período', 'mvf-voting' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $top_ips as $ip ) : ?>
								<tr>
									<td><a class="code" href="<?php echo esc_url( $filter_url( array( 'ip' => $ip['ip_hash'], 'paged' => 0 ) ) ); ?>"><?php echo esc_html( substr( $ip['ip_hash'], 0, 12 ) ); ?>…</a></td>
									<td class="mvf-col-num"><?php echo esc_html( number_format_i18n( (int) $ip['total'] ) ); ?></td>
									<td class="mvf-col-num"><?php echo esc_html( number_format_i18n( (int) $ip['valid'] ) ); ?></td>
									<td><small><?php echo esc_html( mvf_format_gmt( $ip['first_at'], 'd/m H:i' ) . ' → ' . mvf_format_gmt( $ip['last_at'], 'd/m H:i' ) ); ?></small></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
				<div>
					<h2><?php esc_html_e( 'Votos por hora (últimas 48h com votos)', 'mvf-voting' ); ?></h2>
					<table class="widefat striped mvf-table mvf-hours">
						<tbody>
							<?php foreach ( $hours as $h ) : ?>
								<tr>
									<td class="mvf-col-hour"><?php echo esc_html( mvf_format_gmt( $h['h'] . ':00:00', 'd/m H\h' ) ); ?></td>
									<td class="mvf-col-bar">
										<span class="mvf-bar"><span style="width:<?php echo esc_attr( (string) round( 100 * (int) $h['total'] / max( 1, $max_hour ), 1 ) ); ?>%"></span></span>
									</td>
									<td class="mvf-col-num"><?php echo esc_html( number_format_i18n( (int) $h['total'] ) ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			</div>

			<h2><?php esc_html_e( 'Votos', 'mvf-voting' ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="mvf_audit">
				<input type="hidden" name="filters" value="<?php echo esc_attr( $filter_qs ); ?>">
				<?php wp_nonce_field( 'mvf_audit' ); ?>
				<div class="tablenav top">
					<div class="alignleft actions">
						<select name="op">
							<option value="invalidate"><?php esc_html_e( 'Invalidar', 'mvf-voting' ); ?></option>
							<option value="validate"><?php esc_html_e( 'Revalidar', 'mvf-voting' ); ?></option>
						</select>
						<button class="button" name="scope" value="selected"><?php esc_html_e( 'Aplicar aos selecionados', 'mvf-voting' ); ?></button>
						<button class="button" name="scope" value="filtered" onclick="return confirm('<?php echo esc_js( sprintf( /* translators: %s: número */ __( 'Aplicar a todos os %s votos do filtro atual?', 'mvf-voting' ), number_format_i18n( $total ) ) ); ?>')">
							<?php
							/* translators: %s: número */
							echo esc_html( sprintf( __( 'Aplicar a todos do filtro (%s)', 'mvf-voting' ), number_format_i18n( $total ) ) );
							?>
						</button>
					</div>
					<div class="tablenav-pages">
						<?php
						echo wp_kses_post(
							(string) paginate_links(
								array(
									'base'    => add_query_arg( 'paged', '%#%', $filter_url( array() ) ),
									'format'  => '',
									'current' => $paged,
									'total'   => $pages,
								)
							)
						);
						?>
					</div>
				</div>
				<table class="widefat striped mvf-table">
					<thead>
						<tr>
							<td class="check-column"><input type="checkbox" onclick="document.querySelectorAll('.mvf-vote-cb').forEach(c => c.checked = this.checked)"></td>
							<th><?php esc_html_e( 'Data', 'mvf-voting' ); ?></th>
							<th><?php esc_html_e( 'Categoria', 'mvf-voting' ); ?></th>
							<th><?php esc_html_e( 'Clipe', 'mvf-voting' ); ?></th>
							<th><?php esc_html_e( 'IP (hash)', 'mvf-voting' ); ?></th>
							<th><?php esc_html_e( 'Navegador', 'mvf-voting' ); ?></th>
							<th><?php esc_html_e( 'Cookie', 'mvf-voting' ); ?></th>
							<th><?php esc_html_e( 'Status', 'mvf-voting' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( ! $votes ) : ?>
							<tr><td colspan="8"><?php esc_html_e( 'Nenhum voto encontrado.', 'mvf-voting' ); ?></td></tr>
						<?php endif; ?>
						<?php foreach ( $votes as $v ) : ?>
							<tr class="<?php echo $v['is_valid'] ? '' : 'mvf-invalid'; ?>">
								<th scope="row" class="check-column"><input type="checkbox" class="mvf-vote-cb" name="ids[]" value="<?php echo esc_attr( $v['id'] ); ?>"></th>
								<td><?php echo esc_html( mvf_format_gmt( $v['created_at'], 'd/m/Y H:i:s' ) ); ?></td>
								<td><?php echo esc_html( $cat_names[ (int) $v['category_id'] ] ?? '#' . $v['category_id'] ); ?></td>
								<td><a href="<?php echo esc_url( $filter_url( array( 'clipe' => (int) $v['clip_id'], 'paged' => 0 ) ) ); ?>"><?php echo esc_html( get_the_title( (int) $v['clip_id'] ) ); ?></a></td>
								<td><a class="code" href="<?php echo esc_url( $filter_url( array( 'ip' => $v['ip_hash'], 'paged' => 0 ) ) ); ?>"><?php echo esc_html( substr( $v['ip_hash'], 0, 12 ) ); ?></a></td>
								<td class="code"><?php echo esc_html( substr( $v['ua_hash'], 0, 8 ) ); ?></td>
								<td class="code"><?php echo esc_html( substr( $v['cookie_id'], 0, 8 ) ); ?></td>
								<td><?php echo $v['is_valid'] ? esc_html__( 'Válido', 'mvf-voting' ) : '<strong>' . esc_html__( 'Invalidado', 'mvf-voting' ) . '</strong>'; ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</form>
		</div>
		<?php
	}

	public static function audit_action(): void {
		global $wpdb;
		if ( ! current_user_can( 'mvf_manage' ) ) {
			wp_die( esc_html__( 'Acesso negado.', 'mvf-voting' ) );
		}
		check_admin_referer( 'mvf_audit' );

		parse_str( isset( $_POST['filters'] ) ? sanitize_text_field( wp_unslash( $_POST['filters'] ) ) : '', $raw );
		$f     = self::audit_filters_from( $raw );
		$valid = ( isset( $_POST['op'] ) && 'validate' === $_POST['op'] ) ? 1 : 0;
		$scope = isset( $_POST['scope'] ) ? sanitize_key( $_POST['scope'] ) : 'selected';
		$table = mvf_table( 'public_votes' );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
		if ( 'filtered' === $scope ) {
			list( $where, $args ) = self::audit_where( $f );
			$changed = $wpdb->query( $wpdb->prepare( "UPDATE {$table} SET is_valid = %d WHERE {$where}", array_merge( array( $valid ), $args ) ) );
		} else {
			$ids = isset( $_POST['ids'] ) ? array_filter( array_map( 'absint', (array) wp_unslash( $_POST['ids'] ) ) ) : array();
			$changed = 0;
			if ( $ids ) {
				$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
				$changed      = $wpdb->query( $wpdb->prepare( "UPDATE {$table} SET is_valid = %d WHERE id IN ({$placeholders})", array_merge( array( $valid ), $ids ) ) );
			}
		}
		// phpcs:enable

		mvf_add_notice(
			sprintf(
				/* translators: %d: número de votos */
				$valid ? __( '%d voto(s) revalidado(s).', 'mvf-voting' ) : __( '%d voto(s) invalidado(s).', 'mvf-voting' ),
				(int) $changed
			),
			'success'
		);
		wp_safe_redirect( admin_url( 'admin.php?page=mvf-audit&' . http_build_query( array_filter( $f ) ) ) );
		exit;
	}
}
