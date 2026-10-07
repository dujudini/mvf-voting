<?php
/**
 * Funções utilitárias compartilhadas pelos módulos.
 */

defined( 'ABSPATH' ) || exit;

const MVF_CLIP_TYPE   = 'mvf_clip';
const MVF_TAX_EDITION = 'mvf_edicao';
const MVF_TAX_CAT     = 'mvf_categoria';
const MVF_MAX_CLIPS   = 10;

/**
 * Extrai o ID de 11 caracteres de uma URL do YouTube (watch, youtu.be, shorts, embed, live) ou de um ID puro.
 */
function mvf_parse_youtube_id( string $url ): string {
	$url = trim( $url );
	if ( '' === $url ) {
		return '';
	}
	if ( preg_match( '/^[A-Za-z0-9_-]{11}$/', $url ) ) {
		return $url;
	}
	$pattern = '~(?:youtube(?:-nocookie)?\.com/(?:watch\?(?:.*&)?v=|embed/|shorts/|live/|v/)|youtu\.be/)([A-Za-z0-9_-]{11})~i';
	if ( preg_match( $pattern, $url, $m ) ) {
		return $m[1];
	}
	return '';
}

function mvf_youtube_thumb( string $youtube_id ): string {
	return $youtube_id ? 'https://i.ytimg.com/vi/' . rawurlencode( $youtube_id ) . '/hqdefault.jpg' : '';
}

/**
 * Thumbnail do clipe: imagem destacada se existir, senão a do YouTube.
 */
function mvf_clip_thumb_url( int $clip_id, string $size = 'medium_large' ): string {
	$img = get_the_post_thumbnail_url( $clip_id, $size );
	if ( $img ) {
		return $img;
	}
	return mvf_youtube_thumb( (string) get_post_meta( $clip_id, 'youtube_id', true ) );
}

function mvf_settings_defaults(): array {
	return array(
		'active_edition'       => 0,
		'turnstile_site_key'   => '',
		'turnstile_secret_key' => '',
		'behind_cloudflare'    => 0,
		'rate_limit_seconds'   => 5,
		'points'               => array( 25, 18, 15, 12, 10, 8, 6, 4, 2, 1 ),
		'privacy_text'         => '',
		'delete_data'          => 0,
	);
}

function mvf_settings(): array {
	$saved = get_option( 'mvf_settings', array() );
	return wp_parse_args( is_array( $saved ) ? $saved : array(), mvf_settings_defaults() );
}

function mvf_setting( string $key ) {
	$s = mvf_settings();
	return $s[ $key ] ?? null;
}

/**
 * Tabela de pontos do júri indexada a partir de 1 (posição => pontos).
 */
function mvf_points_table(): array {
	$points = array_values( array_map( 'intval', (array) mvf_setting( 'points' ) ) );
	$table  = array();
	foreach ( $points as $i => $p ) {
		$table[ $i + 1 ] = $p;
	}
	return $table;
}

function mvf_points_for_position( int $position ): int {
	$table = mvf_points_table();
	return $table[ $position ] ?? 0;
}

function mvf_active_edition_id(): int {
	$id = (int) mvf_setting( 'active_edition' );
	if ( $id && term_exists( $id, MVF_TAX_EDITION ) ) {
		return $id;
	}
	// Sem edição configurada: usa a mais recente.
	$terms = get_terms(
		array(
			'taxonomy'   => MVF_TAX_EDITION,
			'hide_empty' => false,
			'orderby'    => 'term_id',
			'order'      => 'DESC',
			'number'     => 1,
			'fields'     => 'ids',
		)
	);
	return ( ! is_wp_error( $terms ) && $terms ) ? (int) $terms[0] : 0;
}

/**
 * @return WP_Term[]
 */
function mvf_get_editions(): array {
	$terms = get_terms(
		array(
			'taxonomy'   => MVF_TAX_EDITION,
			'hide_empty' => false,
			'orderby'    => 'name',
			'order'      => 'DESC',
		)
	);
	return is_wp_error( $terms ) ? array() : $terms;
}

/**
 * Categorias de uma edição, ordenadas pelo campo "ordem" e depois pelo nome.
 *
 * @return WP_Term[]
 */
function mvf_get_edition_categories( int $edition_id ): array {
	if ( ! $edition_id ) {
		return array();
	}
	$terms = get_terms(
		array(
			'taxonomy'   => MVF_TAX_CAT,
			'hide_empty' => false,
			'meta_query' => array(
				array(
					'key'   => 'edicao_id',
					'value' => $edition_id,
				),
			),
		)
	);
	if ( is_wp_error( $terms ) ) {
		return array();
	}
	usort(
		$terms,
		static function ( $a, $b ) {
			$oa = (int) get_term_meta( $a->term_id, 'ordem', true );
			$ob = (int) get_term_meta( $b->term_id, 'ordem', true );
			return $oa <=> $ob ?: strcasecmp( $a->name, $b->name );
		}
	);
	return $terms;
}

function mvf_category_edition_id( int $category_id ): int {
	return (int) get_term_meta( $category_id, 'edicao_id', true );
}

function mvf_category_max_clips( int $category_id ): int {
	$max = (int) get_term_meta( $category_id, 'max_clips', true );
	return ( $max >= 1 && $max <= MVF_MAX_CLIPS ) ? $max : MVF_MAX_CLIPS;
}

/**
 * Clipes de uma categoria.
 *
 * @param string|string[] $status Status considerados ('publish' para a votação; vários para contar o limite).
 * @return int[]
 */
function mvf_get_category_clip_ids( int $category_id, $status = 'publish', int $exclude = 0 ): array {
	$q = new WP_Query(
		array(
			'post_type'              => MVF_CLIP_TYPE,
			'post_status'            => $status,
			'posts_per_page'         => -1,
			'fields'                 => 'ids',
			'orderby'                => array(
				'menu_order' => 'ASC',
				'title'      => 'ASC',
			),
			'post__not_in'           => $exclude ? array( $exclude ) : array(),
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'tax_query'              => array(
				array(
					'taxonomy' => MVF_TAX_CAT,
					'field'    => 'term_id',
					'terms'    => $category_id,
				),
			),
		)
	);
	return array_map( 'intval', $q->posts );
}

/**
 * Quantos clipes ocupam vaga na categoria (qualquer status exceto lixeira/rascunho automático).
 */
function mvf_category_clip_count( int $category_id, int $exclude = 0 ): int {
	return count( mvf_get_category_clip_ids( $category_id, array( 'publish', 'draft', 'pending', 'future', 'private' ), $exclude ) );
}

/**
 * Converte "Y-m-d H:i" no fuso do site para timestamp.
 */
function mvf_local_to_timestamp( string $value ): ?int {
	if ( '' === $value ) {
		return null;
	}
	try {
		$dt = new DateTimeImmutable( $value, wp_timezone() );
		return $dt->getTimestamp();
	} catch ( Exception $e ) {
		return null;
	}
}

function mvf_format_timestamp( ?int $ts ): string {
	return $ts ? wp_date( 'd/m/Y H:i', $ts ) : '';
}

/**
 * Converte data UTC do banco (Y-m-d H:i:s) para exibição no fuso do site.
 */
function mvf_format_gmt( ?string $gmt, string $format = 'd/m/Y H:i' ): string {
	if ( ! $gmt ) {
		return '';
	}
	return get_date_from_gmt( $gmt, $format );
}

/**
 * Categorias atribuídas a um jurado.
 *
 * @return int[]
 */
function mvf_juror_categories( int $user_id ): array {
	$cats = get_user_meta( $user_id, 'mvf_categorias', true );
	return is_array( $cats ) ? array_values( array_unique( array_map( 'intval', $cats ) ) ) : array();
}

function mvf_juror_is_assigned( int $user_id, int $category_id ): bool {
	return user_can( $user_id, 'mvf_vote_jury' ) && in_array( $category_id, mvf_juror_categories( $user_id ), true );
}

/**
 * @return WP_User[]
 */
function mvf_get_jurors(): array {
	return get_users(
		array(
			'capability' => 'mvf_vote_jury',
			'orderby'    => 'display_name',
			'order'      => 'ASC',
		)
	);
}

/**
 * Carrega um template do plugin, permitindo override pelo tema em /mvf-voting/{nome}.php.
 */
function mvf_template( string $name, array $vars = array() ): void {
	$file = locate_template( 'mvf-voting/' . $name . '.php' );
	if ( ! $file ) {
		$file = MVF_DIR . 'templates/' . $name . '.php';
	}
	// phpcs:ignore WordPress.PHP.DontExtract.extract_extract
	extract( $vars, EXTR_SKIP );
	include $file;
}

function mvf_table( string $name ): string {
	global $wpdb;
	return $wpdb->prefix . 'mvf_' . $name;
}

/**
 * Guarda um aviso para exibir no próximo carregamento do admin.
 */
function mvf_add_notice( string $message, string $type = 'error' ): void {
	$key     = 'mvf_notices_' . get_current_user_id();
	$notices = get_transient( $key );
	$notices = is_array( $notices ) ? $notices : array();
	$notices[] = array(
		'message' => $message,
		'type'    => $type,
	);
	set_transient( $key, $notices, 120 );
}

function mvf_render_notices(): void {
	$key     = 'mvf_notices_' . get_current_user_id();
	$notices = get_transient( $key );
	if ( ! is_array( $notices ) || ! $notices ) {
		return;
	}
	delete_transient( $key );
	foreach ( $notices as $n ) {
		printf(
			'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
			esc_attr( $n['type'] ),
			wp_kses_post( $n['message'] )
		);
	}
}
