<?php
/**
 * Shortcode [mvf_votacao edicao="slug|id" categoria="slug"].
 */

defined( 'ABSPATH' ) || exit;

class MVF_Shortcodes {

	public static function init(): void {
		add_shortcode( 'mvf_votacao', array( __CLASS__, 'voting' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
	}

	public static function register_assets(): void {
		if ( wp_script_is( 'mvf-public', 'registered' ) ) {
			return;
		}
		wp_register_style( 'mvf-public', MVF_URL . 'assets/css/public.css', array(), MVF_VERSION );
		wp_register_script( 'mvf-public', MVF_URL . 'assets/js/public-vote.js', array(), MVF_VERSION, true );
		wp_register_script( 'mvf-turnstile', MVF_Turnstile::SCRIPT_URL, array( 'mvf-public' ), null, true ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
	}

	private static function resolve_edition( string $value ): int {
		if ( '' === $value ) {
			return mvf_active_edition_id();
		}
		$term = is_numeric( $value ) ? get_term( (int) $value, MVF_TAX_EDITION ) : get_term_by( 'slug', $value, MVF_TAX_EDITION );
		return ( $term && ! is_wp_error( $term ) ) ? (int) $term->term_id : 0;
	}

	public static function voting( $atts ): string {
		$atts = shortcode_atts(
			array(
				'edicao'    => '',
				'categoria' => '',
			),
			$atts,
			'mvf_votacao'
		);

		$edition_id = self::resolve_edition( (string) $atts['edicao'] );
		if ( ! $edition_id ) {
			return '<p class="mvf-empty">' . esc_html__( 'Nenhuma edição de votação configurada.', 'mvf-voting' ) . '</p>';
		}

		$categories = mvf_get_edition_categories( $edition_id );
		if ( '' !== $atts['categoria'] ) {
			$slug       = sanitize_title( $atts['categoria'] );
			$categories = array_values( array_filter( $categories, static fn( $c ) => $c->slug === $slug ) );
		}

		// Monta a lista de clipes de cada categoria e descarta categorias vazias.
		$sections = array();
		foreach ( $categories as $cat ) {
			$clip_ids = mvf_get_category_clip_ids( $cat->term_id );
			if ( $clip_ids ) {
				$sections[] = array(
					'category' => $cat,
					'clips'    => $clip_ids,
				);
			}
		}

		$period = MVF_Periods::status( $edition_id, 'public' );

		// Temas de bloco renderizam o conteúdo antes do wp_enqueue_scripts.
		self::register_assets();
		wp_enqueue_style( 'mvf-public' );
		wp_enqueue_script( 'mvf-public' );
		wp_localize_script(
			'mvf-public',
			'MVF_VOTE',
			array(
				'endpoint' => esc_url_raw( rest_url( 'mvf/v1/vote' ) ),
				'siteKey'  => MVF_Turnstile::enabled() ? (string) mvf_setting( 'turnstile_site_key' ) : '',
				'i18n'     => array(
					'voting'  => __( 'Registrando…', 'mvf-voting' ),
					'vote'    => __( 'Votar', 'mvf-voting' ),
					'again'   => __( 'Votar de novo', 'mvf-voting' ),
					'error'   => __( 'Não foi possível registrar o voto. Tente novamente.', 'mvf-voting' ),
					'human'   => __( 'Confirme que você é humano para registrar o voto.', 'mvf-voting' ),
					'copied'  => __( 'Link copiado!', 'mvf-voting' ),
					'play'    => __( 'Assistir', 'mvf-voting' ),
				),
			)
		);
		if ( MVF_Turnstile::enabled() && $period['open'] ) {
			wp_enqueue_script( 'mvf-turnstile' );
		}

		ob_start();
		mvf_template(
			'voting',
			array(
				'edition_id' => $edition_id,
				'sections'   => $sections,
				'period'     => $period,
				'permalink'  => get_permalink(),
			)
		);
		return (string) ob_get_clean();
	}
}
