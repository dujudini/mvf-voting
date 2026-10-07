<?php
/**
 * Identificação anônima do votante: IP e user agent com hash, cookie de auditoria.
 */

defined( 'ABSPATH' ) || exit;

class MVF_Request {

	const COOKIE = 'mvf_vid';

	public static function ip(): string {
		if ( mvf_setting( 'behind_cloudflare' ) && ! empty( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_CF_CONNECTING_IP'] ) );
			if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
				return $ip;
			}
		}
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '0.0.0.0';
	}

	public static function hash( string $value ): string {
		return hash_hmac( 'sha256', $value, wp_salt( 'auth' ) );
	}

	public static function ip_hash(): string {
		return self::hash( self::ip() );
	}

	public static function ua_hash(): string {
		$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
		return '' === $ua ? '' : self::hash( $ua );
	}

	/**
	 * UUID do navegador. Serve só para auditoria; não bloqueia votos.
	 */
	public static function cookie_id(): string {
		$id = isset( $_COOKIE[ self::COOKIE ] ) ? sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE ] ) ) : '';
		if ( wp_is_uuid( $id ) ) {
			return $id;
		}
		$id = wp_generate_uuid4();
		if ( ! headers_sent() ) {
			setcookie(
				self::COOKIE,
				$id,
				array(
					'expires'  => time() + YEAR_IN_SECONDS,
					'path'     => COOKIEPATH ? COOKIEPATH : '/',
					'domain'   => COOKIE_DOMAIN ? COOKIE_DOMAIN : '',
					'secure'   => is_ssl(),
					'httponly' => true,
					'samesite' => 'Lax',
				)
			);
		}
		return $id;
	}
}
