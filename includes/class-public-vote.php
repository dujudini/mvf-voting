<?php
/**
 * Endpoint de voto do público: POST /wp-json/mvf/v1/vote
 */

defined( 'ABSPATH' ) || exit;

class MVF_Public_Vote {

	public static function init(): void {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
	}

	public static function routes(): void {
		register_rest_route(
			'mvf/v1',
			'/vote',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'vote' ),
				'permission_callback' => '__return_true', // Voto anônimo; protegido por Turnstile e rate limit.
				'args'                => array(
					'clip_id'     => array(
						'type'     => 'integer',
						'required' => true,
						'minimum'  => 1,
					),
					'category_id' => array(
						'type'     => 'integer',
						'required' => true,
						'minimum'  => 1,
					),
					'token'       => array(
						'type'    => 'string',
						'default' => '',
					),
				),
			)
		);
	}

	private static function error( string $code, string $message, int $status ): WP_Error {
		return new WP_Error( $code, $message, array( 'status' => $status ) );
	}

	public static function vote( WP_REST_Request $request ) {
		global $wpdb;

		$clip_id     = (int) $request['clip_id'];
		$category_id = (int) $request['category_id'];

		$category = get_term( $category_id, MVF_TAX_CAT );
		if ( ! $category || is_wp_error( $category ) ) {
			return self::error( 'mvf_invalid', __( 'Categoria inválida.', 'mvf-voting' ), 400 );
		}
		$edition_id = mvf_category_edition_id( $category_id );

		$period = MVF_Periods::status( $edition_id, 'public' );
		if ( ! $period['open'] ) {
			return self::error( 'mvf_closed', MVF_Periods::message( $period ), 403 );
		}

		$clip = get_post( $clip_id );
		if ( ! $clip || MVF_CLIP_TYPE !== $clip->post_type || 'publish' !== $clip->post_status || ! has_term( $category_id, MVF_TAX_CAT, $clip ) ) {
			return self::error( 'mvf_invalid', __( 'Clipe inválido para esta categoria.', 'mvf-voting' ), 400 );
		}

		$ip      = MVF_Request::ip();
		$ip_hash = MVF_Request::hash( $ip );
		$rl_key  = 'mvf_rl_' . substr( $ip_hash, 0, 40 );
		$wait    = (int) mvf_setting( 'rate_limit_seconds' );
		if ( $wait > 0 && get_transient( $rl_key ) ) {
			return self::error( 'mvf_slow_down', __( 'Calma! Aguarde alguns segundos antes de votar de novo.', 'mvf-voting' ), 429 );
		}

		if ( ! MVF_Turnstile::verify( (string) $request['token'], $ip ) ) {
			return self::error( 'mvf_captcha', __( 'Não conseguimos confirmar que você é humano. Tente de novo.', 'mvf-voting' ), 403 );
		}

		$inserted = $wpdb->insert(
			mvf_table( 'public_votes' ),
			array(
				'edition_id'  => $edition_id,
				'category_id' => $category_id,
				'clip_id'     => $clip_id,
				'ip_hash'     => $ip_hash,
				'ua_hash'     => MVF_Request::ua_hash(),
				'cookie_id'   => MVF_Request::cookie_id(),
				'created_at'  => current_time( 'mysql', true ),
				'is_valid'    => 1,
			),
			array( '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%d' )
		);
		if ( ! $inserted ) {
			return self::error( 'mvf_db', __( 'Não foi possível registrar o voto. Tente novamente.', 'mvf-voting' ), 500 );
		}

		if ( $wait > 0 ) {
			set_transient( $rl_key, 1, $wait );
		}

		$response = rest_ensure_response(
			array(
				'ok'      => true,
				'message' => __( 'Voto registrado! Você pode votar de novo quando quiser.', 'mvf-voting' ),
			)
		);
		$response->header( 'Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0' );
		return $response;
	}
}
