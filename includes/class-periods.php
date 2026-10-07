<?php
/**
 * Status (aberta/fechada) das votações de uma edição.
 *
 * Cada edição tem, para "public" e "jury": início, fim e um override manual (auto | open | closed).
 */

defined( 'ABSPATH' ) || exit;

class MVF_Periods {

	/**
	 * @param string $type 'public' ou 'jury'.
	 * @return array{open: bool, state: string, start: ?int, end: ?int}
	 */
	public static function status( int $edition_id, string $type ): array {
		$start    = mvf_local_to_timestamp( (string) get_term_meta( $edition_id, $type . '_start', true ) );
		$end      = mvf_local_to_timestamp( (string) get_term_meta( $edition_id, $type . '_end', true ) );
		$override = (string) get_term_meta( $edition_id, $type . '_status', true );
		$now      = time();

		if ( ! $edition_id ) {
			$state = 'closed';
		} elseif ( 'open' === $override ) {
			$state = 'open';
		} elseif ( 'closed' === $override ) {
			$state = 'closed';
		} elseif ( $start && $now < $start ) {
			$state = 'not_started';
		} elseif ( $end && $now > $end ) {
			$state = 'ended';
		} else {
			$state = 'open';
		}

		return array(
			'open'  => 'open' === $state,
			'state' => $state,
			'start' => $start,
			'end'   => $end,
		);
	}

	public static function is_open( int $edition_id, string $type ): bool {
		return self::status( $edition_id, $type )['open'];
	}

	/**
	 * Texto curto para exibir ao público/jurado.
	 */
	public static function message( array $status ): string {
		switch ( $status['state'] ) {
			case 'open':
				return $status['end']
					/* translators: %s: data e hora */
					? sprintf( __( 'Votação aberta até %s.', 'mvf-voting' ), mvf_format_timestamp( $status['end'] ) )
					: __( 'Votação aberta.', 'mvf-voting' );
			case 'not_started':
				/* translators: %s: data e hora */
				return sprintf( __( 'A votação abre em %s.', 'mvf-voting' ), mvf_format_timestamp( $status['start'] ) );
			case 'ended':
				return __( 'Votação encerrada.', 'mvf-voting' );
			default:
				return __( 'Votação fechada no momento.', 'mvf-voting' );
		}
	}
}
