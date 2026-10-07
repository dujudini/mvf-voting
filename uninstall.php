<?php
/**
 * Remove dados do plugin somente se a opção "Apagar dados ao desinstalar" estiver marcada.
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$mvf_settings = get_option( 'mvf_settings', array() );
if ( empty( $mvf_settings['delete_data'] ) ) {
	return;
}

global $wpdb;

foreach ( array( 'public_votes', 'jury_ballots', 'jury_ballot_items' ) as $mvf_table ) {
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}mvf_{$mvf_table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
}

// Clipes.
$mvf_clips = get_posts(
	array(
		'post_type'   => 'mvf_clip',
		'post_status' => 'any',
		'numberposts' => -1,
		'fields'      => 'ids',
	)
);
foreach ( $mvf_clips as $mvf_clip ) {
	wp_delete_post( $mvf_clip, true );
}

// Edições e categorias (as taxonomias precisam estar registradas para wp_delete_term).
foreach ( array( 'mvf_edicao', 'mvf_categoria' ) as $mvf_tax ) {
	register_taxonomy( $mvf_tax, 'mvf_clip' );
	$mvf_terms = get_terms(
		array(
			'taxonomy'   => $mvf_tax,
			'hide_empty' => false,
			'fields'     => 'ids',
		)
	);
	if ( ! is_wp_error( $mvf_terms ) ) {
		foreach ( $mvf_terms as $mvf_term ) {
			wp_delete_term( $mvf_term, $mvf_tax );
		}
	}
}

delete_metadata( 'user', 0, 'mvf_categorias', '', true );
delete_option( 'mvf_settings' );
delete_option( 'mvf_db_version' );

remove_role( 'mvf_jurado' );
$mvf_admin = get_role( 'administrator' );
if ( $mvf_admin ) {
	$mvf_admin->remove_cap( 'mvf_manage' );
}
