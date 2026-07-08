<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @package CANITY
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

require_once plugin_dir_path( __FILE__ ) . 'includes/class-canity-helpers.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-canity-api.php';

Canity_API::flush_cache();

delete_option( 'canity_options' );
delete_option( 'canity_cache_version' );
