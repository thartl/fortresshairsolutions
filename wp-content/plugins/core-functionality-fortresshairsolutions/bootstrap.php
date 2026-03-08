<?php

/**
 * Core functionality plugin for Ollie Starter.
 *
 * @package     Osim\CoreFunctionality
 * @author      Tomas Hartl
 * @license     GNU General Public License 2.0+
 *
 * @wordpress-plugin
 * Plugin Name: Ollie Starter Core Functionality
 * Description: This contains your site's core functionality so that it is theme-independent. <strong>It should always be activated</strong>.
 * Version:     1.0.2
 * Author:      Tomas Hartl
 * Author URI:  https://osiminteractive.com
 * License:     GPL-2.0+ License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 */

namespace Osim\CoreFunctionality;

defined( 'ABSPATH' ) || exit;


define( 'OSIM_CORE_PLUGIN_DIR', __DIR__ );

define( 'OSIM_CORE_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

$osim_core_plugin_url = plugin_dir_url( __FILE__ );
if ( is_ssl() ) {

	$osim_core_plugin_url = str_replace( 'http://', 'https://', $osim_core_plugin_url );
}
define( 'OSIM_CORE_PLUGIN_DIR_URL', $osim_core_plugin_url );


add_action( 'init', __NAMESPACE__ . '\define_current_user_id', 1 );
function define_current_user_id() {

	define( 'OSIM_CURRENT_USER_ID', get_current_user_id() ); // slightly faster than is_user_logged_in(); 0 if user not logged in
}


/**
 * Load file dependencies.
 *
 * @return void
 */
function load_dependencies() {
	// Public dependencies
	$files = array(
		'includes/setup.php',
		'includes/functions.php',
		'includes/native-blocks/blocks.php',
	);

	// Conditionally load for admin or REST requests, but not htmx requests
	if ( empty( $_SERVER['HTTP_HX_REQUEST'] ) && ( is_admin() || wp_is_json_request() ) ) {
		$files[] = 'includes/custom-post-types/help_docs-CPT.php';
	}

	foreach ( $files as $filename ) {
		/** @noinspection PhpIncludeInspection */
		require_once __DIR__ . '/' . $filename;
	}

	// Admin dependencies
	if ( is_admin() ) {
		$admin_files = array(
			'includes/admin-setup.php',
//			'includes/wp-admin-mods.php',
		);

		foreach ( $admin_files as $filename ) {
			/** @noinspection PhpIncludeInspection */
			require_once __DIR__ . '/' . $filename;
		}

		// Set up ajax for Help Docs
		include_once __DIR__ . '/includes/help-docs-ajax.php';
		add_action( 'wp_ajax_get_help_doc', __NAMESPACE__ . '\get_help_doc' );
	}

	// WP CLI
	if ( defined( 'WP_CLI' ) && WP_CLI ) {
		include_once __DIR__ . '/includes/wp-cli/fluid-command.php';
	}
}

load_dependencies();
