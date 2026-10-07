<?php
/**
 * Criação de tabelas, role do júri e capabilities.
 */

defined( 'ABSPATH' ) || exit;

class MVF_Activator {

	public static function activate(): void {
		self::create_tables();
		self::add_roles();
		MVF_Post_Types::register();
		update_option( 'mvf_db_version', MVF_DB_VERSION );
		if ( false === get_option( 'mvf_settings' ) ) {
			add_option( 'mvf_settings', mvf_settings_defaults() );
		}
	}

	/**
	 * Roda em todo carregamento: atualiza o schema quando a versão muda (ex.: plugin atualizado sem reativar).
	 */
	public static function maybe_upgrade(): void {
		if ( get_option( 'mvf_db_version' ) !== MVF_DB_VERSION ) {
			self::create_tables();
			self::add_roles();
			update_option( 'mvf_db_version', MVF_DB_VERSION );
		}
	}

	public static function create_tables(): void {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset = $wpdb->get_charset_collate();
		$votes   = mvf_table( 'public_votes' );
		$ballots = mvf_table( 'jury_ballots' );
		$items   = mvf_table( 'jury_ballot_items' );

		dbDelta(
			"CREATE TABLE {$votes} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  edition_id bigint(20) unsigned NOT NULL,
  category_id bigint(20) unsigned NOT NULL,
  clip_id bigint(20) unsigned NOT NULL,
  ip_hash char(64) NOT NULL,
  ua_hash char(64) NOT NULL DEFAULT '',
  cookie_id varchar(36) NOT NULL DEFAULT '',
  created_at datetime NOT NULL,
  is_valid tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY  (id),
  KEY cat_clip (category_id,clip_id,is_valid),
  KEY ip_time (ip_hash,created_at),
  KEY edition_time (edition_id,created_at)
) {$charset};"
		);

		dbDelta(
			"CREATE TABLE {$ballots} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  edition_id bigint(20) unsigned NOT NULL,
  category_id bigint(20) unsigned NOT NULL,
  juror_id bigint(20) unsigned NOT NULL,
  status varchar(20) NOT NULL DEFAULT 'draft',
  updated_at datetime NOT NULL,
  submitted_at datetime DEFAULT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY cat_juror (category_id,juror_id),
  KEY edition (edition_id)
) {$charset};"
		);

		dbDelta(
			"CREATE TABLE {$items} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  ballot_id bigint(20) unsigned NOT NULL,
  clip_id bigint(20) unsigned NOT NULL,
  position tinyint(3) unsigned NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY ballot_clip (ballot_id,clip_id)
) {$charset};"
		);
	}

	public static function add_roles(): void {
		add_role(
			'mvf_jurado',
			__( 'Jurado MVF', 'mvf-voting' ),
			array(
				'read'          => true,
				'mvf_vote_jury' => true,
			)
		);
		$admin = get_role( 'administrator' );
		if ( $admin ) {
			$admin->add_cap( 'mvf_manage' );
		}
	}
}
