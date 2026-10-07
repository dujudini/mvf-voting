<?php
/**
 * Bootstrap: inicializa os módulos e monta os menus do admin.
 */

defined( 'ABSPATH' ) || exit;

class MVF_Plugin {

	public static function init(): void {
		load_plugin_textdomain( 'mvf-voting', false, dirname( plugin_basename( MVF_FILE ) ) . '/languages' );

		MVF_Activator::maybe_upgrade();
		MVF_Post_Types::init();
		MVF_Settings::init();
		MVF_Clip_Admin::init();
		MVF_Taxonomy_Admin::init();
		MVF_Public_Vote::init();
		MVF_Jury::init();
		MVF_Admin_Results::init();
		MVF_Export::init();
		MVF_Shortcodes::init();

		add_action( 'admin_menu', array( __CLASS__, 'menu_top' ), 9 );
		add_action( 'admin_menu', array( __CLASS__, 'menu_sub' ), 20 );
		add_filter( 'parent_file', array( __CLASS__, 'parent_file' ) );
		add_filter( 'submenu_file', array( __CLASS__, 'submenu_file' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'admin_assets' ) );
		add_action( 'admin_notices', array( __CLASS__, 'notices' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( MVF_FILE ), array( __CLASS__, 'action_links' ) );
	}

	public static function menu_top(): void {
		add_menu_page(
			__( 'MVF Votação', 'mvf-voting' ),
			__( 'MVF Votação', 'mvf-voting' ),
			'mvf_manage',
			'mvf',
			array( 'MVF_Admin_Results', 'render_results' ),
			'dashicons-video-alt3',
			26
		);
		add_submenu_page( 'mvf', __( 'Resultados', 'mvf-voting' ), __( 'Resultados', 'mvf-voting' ), 'mvf_manage', 'mvf', array( 'MVF_Admin_Results', 'render_results' ) );

		// Menu do jurado (admins com mvf_manage não votam como jurado por padrão).
		$hook = add_menu_page(
			__( 'Minha votação', 'mvf-voting' ),
			__( 'Minha votação', 'mvf-voting' ),
			'mvf_vote_jury',
			'mvf-jury',
			array( 'MVF_Jury', 'render_ballot_page' ),
			'dashicons-awards',
			3
		);
		add_action( 'load-' . $hook, static function () {
			add_action( 'admin_enqueue_scripts', array( 'MVF_Jury', 'enqueue_ballot_assets' ) );
		} );
	}

	public static function menu_sub(): void {
		$tax_url = 'edit-tags.php?post_type=' . MVF_CLIP_TYPE . '&taxonomy=';
		add_submenu_page( 'mvf', __( 'Edições', 'mvf-voting' ), __( 'Edições', 'mvf-voting' ), 'mvf_manage', $tax_url . MVF_TAX_EDITION );
		add_submenu_page( 'mvf', __( 'Categorias', 'mvf-voting' ), __( 'Categorias', 'mvf-voting' ), 'mvf_manage', $tax_url . MVF_TAX_CAT );
		add_submenu_page( 'mvf', __( 'Jurados', 'mvf-voting' ), __( 'Jurados', 'mvf-voting' ), 'mvf_manage', 'mvf-jurors', array( 'MVF_Jury', 'render_jurors_page' ) );
		add_submenu_page( 'mvf', __( 'Progresso do júri', 'mvf-voting' ), __( 'Progresso do júri', 'mvf-voting' ), 'mvf_manage', 'mvf-progress', array( 'MVF_Admin_Results', 'render_progress' ) );
		add_submenu_page( 'mvf', __( 'Auditoria de votos', 'mvf-voting' ), __( 'Auditoria de votos', 'mvf-voting' ), 'mvf_manage', 'mvf-audit', array( 'MVF_Admin_Results', 'render_audit' ) );
		add_submenu_page( 'mvf', __( 'Configurações', 'mvf-voting' ), __( 'Configurações', 'mvf-voting' ), 'mvf_manage', 'mvf-settings', array( 'MVF_Settings', 'render' ) );
	}

	/**
	 * Mantém o menu "MVF Votação" aberto nas telas de taxonomia e de edição de clipe.
	 */
	public static function parent_file( $parent_file ) {
		$screen = get_current_screen();
		if ( $screen && ( MVF_CLIP_TYPE === $screen->post_type || in_array( $screen->taxonomy, array( MVF_TAX_EDITION, MVF_TAX_CAT ), true ) ) ) {
			return 'mvf';
		}
		return $parent_file;
	}

	public static function submenu_file( $submenu_file ) {
		$screen = get_current_screen();
		if ( $screen && in_array( $screen->taxonomy, array( MVF_TAX_EDITION, MVF_TAX_CAT ), true ) ) {
			return 'edit-tags.php?post_type=' . MVF_CLIP_TYPE . '&taxonomy=' . $screen->taxonomy;
		}
		if ( $screen && MVF_CLIP_TYPE === $screen->post_type ) {
			return 'edit.php?post_type=' . MVF_CLIP_TYPE;
		}
		return $submenu_file;
	}

	public static function admin_assets( string $hook ): void {
		$screen = get_current_screen();
		$ours   = $screen && (
			false !== strpos( $hook, 'mvf' )
			|| MVF_CLIP_TYPE === $screen->post_type
			|| in_array( $screen->taxonomy, array( MVF_TAX_EDITION, MVF_TAX_CAT ), true )
		);
		if ( ! $ours ) {
			return;
		}
		wp_enqueue_style( 'mvf-admin', MVF_URL . 'assets/css/admin.css', array(), MVF_VERSION );
		if ( MVF_CLIP_TYPE === $screen->post_type && 'post' === $screen->base ) {
			wp_enqueue_script( 'mvf-admin', MVF_URL . 'assets/js/admin.js', array(), MVF_VERSION, true );
		}
	}

	public static function notices(): void {
		// As telas próprias exibem os avisos dentro do .wrap.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['page'] ) && in_array( $_GET['page'], array( 'mvf-jurors', 'mvf-audit' ), true ) ) {
			return;
		}
		mvf_render_notices();
	}

	public static function action_links( array $links ): array {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=mvf-settings' ) ) . '">' . esc_html__( 'Configurações', 'mvf-voting' ) . '</a>' );
		return $links;
	}
}
