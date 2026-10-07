<?php
/**
 * Verificação server-side do Cloudflare Turnstile.
 */

defined( 'ABSPATH' ) || exit;

class MVF_Turnstile {

	const VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';
	const SCRIPT_URL = 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit&onload=mvfTurnstileReady';

	public static function enabled(): bool {
		return '' !== (string) mvf_setting( 'turnstile_site_key' ) && '' !== (string) mvf_setting( 'turnstile_secret_key' );
	}

	public static function verify( string $token, string $ip ): bool {
		if ( ! self::enabled() ) {
			return true;
		}
		if ( '' === $token || strlen( $token ) > 2048 ) {
			return false;
		}
		$response = wp_remote_post(
			self::VERIFY_URL,
			array(
				'timeout' => 10,
				'body'    => array(
					'secret'   => mvf_setting( 'turnstile_secret_key' ),
					'response' => $token,
					'remoteip' => $ip,
				),
			)
		);
		if ( is_wp_error( $response ) ) {
			return false;
		}
		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		return is_array( $body ) && ! empty( $body['success'] );
	}
}
