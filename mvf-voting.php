<?php
/**
 * Plugin Name:       MVF Voting
 * Description:       Votação de clipes com voto do público.
 * Version:           2.0.8
 * Requires at least: 6.4
 * Requires PHP:      7.4
 * Text Domain:       mvf-voting
 * Domain Path:       /languages
 * License:           GPL-2.0-or-later
 */

defined( 'ABSPATH' ) || exit;

define( 'MVF_VERSION', '2.0.8' );
define( 'MVF_DB_VERSION', '1' );
define( 'MVF_FILE', __FILE__ );
define( 'MVF_DIR', plugin_dir_path( __FILE__ ) );
define( 'MVF_URL', plugin_dir_url( __FILE__ ) );

require_once MVF_DIR . 'includes/helpers.php';
require_once MVF_DIR . 'includes/class-activator.php';
require_once MVF_DIR . 'includes/class-post-types.php';
require_once MVF_DIR . 'includes/class-settings.php';
require_once MVF_DIR . 'includes/class-request.php';
require_once MVF_DIR . 'includes/class-turnstile.php';
require_once MVF_DIR . 'includes/class-periods.php';
require_once MVF_DIR . 'includes/class-clip-admin.php';
require_once MVF_DIR . 'includes/class-taxonomy-admin.php';
require_once MVF_DIR . 'includes/class-public-vote.php';
require_once MVF_DIR . 'includes/class-jury.php';
require_once MVF_DIR . 'includes/class-results.php';
require_once MVF_DIR . 'includes/class-admin-results.php';
require_once MVF_DIR . 'includes/class-export.php';
require_once MVF_DIR . 'includes/class-shortcodes.php';
require_once MVF_DIR . 'includes/class-plugin.php';

register_activation_hook( __FILE__, array( 'MVF_Activator', 'activate' ) );

add_action( 'plugins_loaded', array( 'MVF_Plugin', 'init' ) );
