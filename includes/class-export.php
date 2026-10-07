<?php
/**
 * Exportação CSV (separador ";" e BOM UTF-8 para abrir direto no Excel em português).
 */

defined( 'ABSPATH' ) || exit;

class MVF_Export {

	public static function init(): void {
		add_action( 'admin_post_mvf_export', array( __CLASS__, 'export' ) );
	}

	public static function export(): void {
		if ( ! current_user_can( 'mvf_manage' ) ) {
			wp_die( esc_html__( 'Acesso negado.', 'mvf-voting' ) );
		}
		check_admin_referer( 'mvf_export' );

		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$type        = isset( $_GET['tipo'] ) ? sanitize_key( $_GET['tipo'] ) : 'public';
		$edition_id  = isset( $_GET['edicao'] ) ? absint( $_GET['edicao'] ) : 0;
		$category_id = isset( $_GET['categoria'] ) ? absint( $_GET['categoria'] ) : 0;
		// phpcs:enable

		$edition    = get_term( $edition_id, MVF_TAX_EDITION );
		$categories = mvf_get_edition_categories( $edition_id );
		if ( $category_id ) {
			$categories = array_values( array_filter( $categories, static fn( $c ) => $c->term_id === $category_id ) );
		}
		$slug = ( $edition && ! is_wp_error( $edition ) ) ? $edition->slug : 'edicao';
		if ( 1 === count( $categories ) && $category_id ) {
			$slug .= '-' . $categories[0]->slug;
		}

		$labels = array(
			'public' => 'ranking-publico',
			'jury'   => 'ranking-juri',
			'votes'  => 'votos-publico',
		);
		$type = isset( $labels[ $type ] ) ? $type : 'public';

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="mvf-' . $labels[ $type ] . '-' . sanitize_file_name( $slug ) . '-' . gmdate( 'Ymd-His' ) . '.csv"' );

		$out = fopen( 'php://output', 'w' );
		fwrite( $out, "\xEF\xBB\xBF" );

		switch ( $type ) {
			case 'jury':
				self::jury( $out, $categories );
				break;
			case 'votes':
				self::votes( $out, $edition_id, $category_id );
				break;
			default:
				self::public_ranking( $out, $categories );
		}

		fclose( $out );
		exit;
	}

	private static function row( $out, array $fields ): void {
		// Títulos do WP vêm com entidades HTML (&#8211; etc.); no CSV queremos o texto puro.
		$fields = array_map(
			static fn( $v ) => is_string( $v ) ? html_entity_decode( $v, ENT_QUOTES, 'UTF-8' ) : $v,
			$fields
		);
		fputcsv( $out, $fields, ';', '"', '\\' );
	}

	private static function public_ranking( $out, array $categories ): void {
		self::row( $out, array( 'Categoria', 'Posição', 'Clipe', 'Artista', 'Direção', 'Votos válidos', 'Percentual', 'ID do clipe' ) );
		foreach ( $categories as $cat ) {
			$ranking = MVF_Results::public_ranking( $cat->term_id );
			foreach ( $ranking['rows'] as $row ) {
				self::row(
					$out,
					array(
						$cat->name,
						$row['rank'],
						$row['title'],
						get_post_meta( $row['clip_id'], 'artista', true ),
						get_post_meta( $row['clip_id'], 'diretor', true ),
						$row['votes'],
						number_format( $row['percent'], 1, ',', '' ) . '%',
						$row['clip_id'],
					)
				);
			}
		}
	}

	private static function jury( $out, array $categories ): void {
		// Uma coluna por jurado de qualquer categoria exportada, com a posição dada ao clipe.
		$rankings = array();
		$jurors   = array();
		foreach ( $categories as $cat ) {
			$rankings[ $cat->term_id ] = MVF_Results::jury_ranking( $cat->term_id );
			foreach ( array_keys( $rankings[ $cat->term_id ]['ballots'] ) as $juror_id ) {
				if ( ! isset( $jurors[ $juror_id ] ) ) {
					$u                   = get_userdata( $juror_id );
					$jurors[ $juror_id ] = $u ? $u->display_name : '#' . $juror_id;
				}
			}
		}

		$header = array( 'Categoria', 'Posição', 'Clipe', 'Artista', 'Pontos' );
		for ( $p = 1; $p <= MVF_MAX_CLIPS; $p++ ) {
			$header[] = $p . 'º lugares';
		}
		foreach ( $jurors as $name ) {
			$header[] = 'Jurado: ' . $name;
		}
		self::row( $out, $header );

		foreach ( $categories as $cat ) {
			foreach ( $rankings[ $cat->term_id ]['rows'] as $row ) {
				$line = array( $cat->name, $row['rank'], $row['title'], get_post_meta( $row['clip_id'], 'artista', true ), $row['points'] );
				for ( $p = 1; $p <= MVF_MAX_CLIPS; $p++ ) {
					$line[] = $row['placements'][ $p ] ?? 0;
				}
				foreach ( array_keys( $jurors ) as $juror_id ) {
					$line[] = isset( $row['by_juror'][ $juror_id ] ) ? $row['by_juror'][ $juror_id ] . 'º' : '';
				}
				self::row( $out, $line );
			}
		}
	}

	private static function votes( $out, int $edition_id, int $category_id ): void {
		global $wpdb;
		$table     = mvf_table( 'public_votes' );
		$cat_names = wp_list_pluck( mvf_get_edition_categories( $edition_id ), 'name', 'term_id' );
		$titles    = array();

		self::row( $out, array( 'ID', 'Data/hora', 'Categoria', 'Clipe', 'ID do clipe', 'IP (hash)', 'Navegador (hash)', 'Cookie', 'Válido' ) );

		$last_id = 0;
		do {
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$rows = $category_id
				? $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE edition_id = %d AND category_id = %d AND id > %d ORDER BY id ASC LIMIT 5000", $edition_id, $category_id, $last_id ), ARRAY_A )
				: $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE edition_id = %d AND id > %d ORDER BY id ASC LIMIT 5000", $edition_id, $last_id ), ARRAY_A );
			// phpcs:enable
			foreach ( $rows as $v ) {
				$clip_id = (int) $v['clip_id'];
				if ( ! isset( $titles[ $clip_id ] ) ) {
					$titles[ $clip_id ] = get_the_title( $clip_id );
				}
				self::row(
					$out,
					array(
						$v['id'],
						mvf_format_gmt( $v['created_at'], 'd/m/Y H:i:s' ),
						$cat_names[ (int) $v['category_id'] ] ?? $v['category_id'],
						$titles[ $clip_id ],
						$clip_id,
						$v['ip_hash'],
						$v['ua_hash'],
						$v['cookie_id'],
						$v['is_valid'] ? 'sim' : 'não',
					)
				);
				$last_id = (int) $v['id'];
			}
			if ( function_exists( 'wp_cache_flush_runtime' ) ) {
				wp_cache_flush_runtime();
			}
		} while ( count( $rows ) === 5000 );
	}
}
