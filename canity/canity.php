<?php
/**
 * Plugin Name:       CANITY
 * Plugin URI:        https://github.com/sprintwerk/canity-wordpress-plugin
 * Description:       Official CANITY WordPress plugin to display services, events, and packages from the CANITY Partner API using a shortcode or Gutenberg block.
 * Version:           1.1.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            sprintwerk
 * Author URI:        https://www.sprintwerk.de
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       canity
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CANITY_VERSION', '1.1.0' );
define( 'CANITY_PLUGIN_FILE', __FILE__ );
define( 'CANITY_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'CANITY_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

if ( ! defined( 'CANITY_API_BASE' ) ) {
	define( 'CANITY_API_BASE', 'https://canity.de/api' );
}

if ( ! defined( 'CANITY_PUBLIC_BASE' ) ) {
	define( 'CANITY_PUBLIC_BASE', 'https://canity.de' );
}

require_once CANITY_PLUGIN_DIR . 'includes/class-canity-helpers.php';
require_once CANITY_PLUGIN_DIR . 'includes/class-canity-item-helpers.php';
require_once CANITY_PLUGIN_DIR . 'includes/class-canity-date-helpers.php';
require_once CANITY_PLUGIN_DIR . 'includes/class-canity-api.php';
require_once CANITY_PLUGIN_DIR . 'includes/class-canity-settings.php';
require_once CANITY_PLUGIN_DIR . 'includes/class-canity-detail.php';
require_once CANITY_PLUGIN_DIR . 'includes/class-canity-rest.php';
require_once CANITY_PLUGIN_DIR . 'includes/class-canity-shortcodes.php';
require_once CANITY_PLUGIN_DIR . 'includes/class-canity-block.php';
require_once CANITY_PLUGIN_DIR . 'includes/class-canity-assets.php';

add_action( 'plugins_loaded', static function () {
	Canity_Settings::init();
	Canity_Detail::init();
	Canity_REST::init();
	Canity_Shortcodes::init();
	Canity_Block::init();
	Canity_Assets::init();
} );
