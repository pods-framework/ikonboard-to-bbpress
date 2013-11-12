<?php
/**
 * The WordPress Plugin Boilerplate.
 *
 * A foundation off of which to build well-documented WordPress plugins that
 * also follow WordPress Coding Standards and PHP best practices.
 *
 * @package   ikonboard-to-bbpress
 * @author    Scott Kingsley Clark <lol@scottkclark.com>, Phil Lewis
 * @license   GPL-2.0+
 * @copyright 2013 Scott Kingsley Clark, Phil Lewis
 *
 * @wordpress-plugin
 * Plugin Name:       Ikonboard to bbPress migration
 * Description:       Ikonboard to bbPress migration
 * Version:           1.0.0
 * Author:            Scott Kingsley Clark <lol@scottkclark.com>, Phil Lewis
 * Text Domain:       ikonboard-to-bbpress
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( !defined( 'WPINC' ) ) {
	die;
}

/*----------------------------------------------------------------------------*
 * Public-Facing Functionality
 *----------------------------------------------------------------------------*/

require_once( plugin_dir_path( __FILE__ ) . '/public/class-ikonboard-to-bbpress.php' );

/*
 * Register hooks that are fired when the plugin is activated or deactivated.
 * When the plugin is deleted, the uninstall.php file is loaded.
 */
register_activation_hook( __FILE__, array( 'IkonboardToBBPress', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'IkonboardToBBPress', 'deactivate' ) );

add_action( 'plugins_loaded', array( 'IkonboardToBBPress', 'get_instance' ) );

/*----------------------------------------------------------------------------*
 * Dashboard and Administrative Functionality
 *----------------------------------------------------------------------------*/

/*
 * If you want to include Ajax within the dashboard, change the following
 * conditional to:
 *
 * if ( is_admin() ) {
 *   ...
 * }
 *
 * The code below is intended to to give the lightest footprint possible.
 */
if ( is_admin() ) {

	require_once( plugin_dir_path( __FILE__ ) . '/admin/class-ikonboard-to-bbpress-admin.php' );
	add_action( 'plugins_loaded', array( 'IkonboardToBBPress_Admin', 'get_instance' ) );

}

/**
 * @param string $message
 */
function time_elapsed ( $message = 'time_elapsed: ' ) {
	static $last = null;

	$now = microtime( true );

	if ( $last != null ) {
		debug_out( "$message: " . ( $now - $last ) );
	}

	$last = $now;
}

/**
 * @param string $message
 */
function debug_out ( $message = "debug out callded" ) {
	echo "$message<br />";
	flush();
	ob_flush();
}