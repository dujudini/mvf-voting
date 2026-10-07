<?php
/**
 * Cálculo dos rankings do público e do júri.
 */

defined( 'ABSPATH' ) || exit;

class MVF_Results {

	/**
	 * Ranking do público de uma categoria (só votos válidos).
	 *
	 * @return array{total:int, rows:array<int,array{rank:int,clip_id:int,title:string,votes:int,percent:float}>}
	 */
	public static function public_ranking( int $category_id ): array {
		global $wpdb;
		$table = mvf_table( 'public_votes' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$counts = $wpdb->get_results( $wpdb->prepare( "SELECT clip_id, COUNT(*) AS votes FROM {$table} WHERE category_id = %d AND is_valid = 1 GROUP BY clip_id", $category_id ), ARRAY_A );

		$votes = array();
		foreach ( $counts as $c ) {
			$votes[ (int) $c['clip_id'] ] = (int) $c['votes'];
		}
		// Inclui clipes sem voto e clipes que saíram da categoria mas receberam votos.
		$clip_ids = array_unique( array_merge( mvf_get_category_clip_ids( $category_id ), array_keys( $votes ) ) );
		$total    = array_sum( $votes );

		$rows = array();
		foreach ( $clip_ids as $clip_id ) {
			$rows[] = array(
				'clip_id' => (int) $clip_id,
				'title'   => get_the_title( $clip_id ),
				'votes'   => $votes[ $clip_id ] ?? 0,
				'percent' => $total ? round( 100 * ( $votes[ $clip_id ] ?? 0 ) / $total, 1 ) : 0.0,
			);
		}
		usort(
			$rows,
			static fn( $a, $b ) => $b['votes'] <=> $a['votes'] ?: strcasecmp( $a['title'], $b['title'] )
		);

		// Empates dividem a posição (1, 2, 2, 4).
		$prev = null;
		foreach ( $rows as $i => &$row ) {
			$row['rank'] = ( null !== $prev && $prev['votes'] === $row['votes'] ) ? $prev['rank'] : $i + 1;
			$prev        = $row;
		}
		unset( $row );

		return array(
			'total' => $total,
			'rows'  => $rows,
		);
	}

	/**
	 * Cédulas enviadas por jurados ainda designados para a categoria.
	 *
	 * @return array<int,array{juror_id:int,submitted_at:string,items:array<int,int>}>
	 */
	public static function submitted_ballots( int $category_id ): array {
		global $wpdb;
		$ballots = mvf_table( 'jury_ballots' );
		$items   = mvf_table( 'jury_ballot_items' );
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT b.id, b.juror_id, b.submitted_at, i.clip_id, i.position
				FROM {$ballots} b INNER JOIN {$items} i ON i.ballot_id = b.id
				WHERE b.category_id = %d AND b.status = 'submitted'
				ORDER BY b.juror_id, i.position",
				$category_id
			),
			ARRAY_A
		);
		// phpcs:enable

		$out = array();
		foreach ( $rows as $r ) {
			$juror = (int) $r['juror_id'];
			if ( ! isset( $out[ $juror ] ) ) {
				if ( ! mvf_juror_is_assigned( $juror, $category_id ) ) {
					continue;
				}
				$out[ $juror ] = array(
					'juror_id'     => $juror,
					'submitted_at' => (string) $r['submitted_at'],
					'items'        => array(),
				);
			}
			$out[ $juror ]['items'][ (int) $r['clip_id'] ] = (int) $r['position'];
		}
		return $out;
	}

	/**
	 * Ranking do júri com pontuação por posição e desempate por contagem de colocações (estilo F1).
	 *
	 * @return array{ballots:array, positions:int, rows:array<int,array{rank:int,clip_id:int,title:string,points:int,placements:array<int,int>,by_juror:array<int,int>}>}
	 */
	public static function jury_ranking( int $category_id ): array {
		$ballots  = self::submitted_ballots( $category_id );
		$clip_ids = mvf_get_category_clip_ids( $category_id );
		$max_pos  = max( 1, count( $clip_ids ) );

		$data = array();
		$init = static function ( int $clip_id ) use ( &$data ) {
			if ( ! isset( $data[ $clip_id ] ) ) {
				$data[ $clip_id ] = array(
					'clip_id'    => $clip_id,
					'title'      => get_the_title( $clip_id ),
					'points'     => 0,
					'placements' => array(),
					'by_juror'   => array(),
				);
			}
		};
		foreach ( $clip_ids as $clip_id ) {
			$init( $clip_id );
		}

		foreach ( $ballots as $ballot ) {
			foreach ( $ballot['items'] as $clip_id => $position ) {
				$init( $clip_id );
				$max_pos = max( $max_pos, $position );
				$data[ $clip_id ]['points']                            += mvf_points_for_position( $position );
				$data[ $clip_id ]['placements'][ $position ]            = ( $data[ $clip_id ]['placements'][ $position ] ?? 0 ) + 1;
				$data[ $clip_id ]['by_juror'][ $ballot['juror_id'] ]    = $position;
			}
		}

		$compare = static function ( $a, $b ) use ( $max_pos ) {
			if ( $a['points'] !== $b['points'] ) {
				return $b['points'] <=> $a['points'];
			}
			for ( $p = 1; $p <= $max_pos; $p++ ) {
				$diff = ( $b['placements'][ $p ] ?? 0 ) <=> ( $a['placements'][ $p ] ?? 0 );
				if ( 0 !== $diff ) {
					return $diff;
				}
			}
			return 0;
		};

		$rows = array_values( $data );
		usort(
			$rows,
			static fn( $a, $b ) => $compare( $a, $b ) ?: strcasecmp( $a['title'], $b['title'] )
		);

		$prev = null;
		foreach ( $rows as $i => &$row ) {
			$row['rank'] = ( null !== $prev && 0 === $compare( $prev, $row ) ) ? $prev['rank'] : $i + 1;
			$prev        = $row;
		}
		unset( $row );

		return array(
			'ballots'   => $ballots,
			'positions' => $max_pos,
			'rows'      => $rows,
		);
	}
}
