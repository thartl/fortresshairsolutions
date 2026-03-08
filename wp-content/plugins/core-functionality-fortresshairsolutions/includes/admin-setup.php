<?php

/**
 * Sets up custom fields for cmb2.
 *
 * @package     Osim\CoreFunctionality
 * @since       1.0.0
 * @author      thartl
 * @license     GNU General Public License 2.0+
 */

namespace Osim\CoreFunctionality;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


add_action( 'admin_enqueue_scripts', __NAMESPACE__ . '\load_admin_scripts_and_styles' );
/** @noinspection PhpUnused */
/**
 * Enqueue admin assets.
 *
 * @return void
 * @since   1.0.0
 *
 */
function load_admin_scripts_and_styles() {

	$asset_file = 'assets/css/admin-style.css';
	wp_enqueue_style(
		'core-admin-style',
		OSIM_CORE_PLUGIN_DIR_URL . $asset_file,
		array(),
		filemtime( OSIM_CORE_PLUGIN_DIR . '/' . $asset_file )
	);

	if ( ! empty( $_REQUEST['page'] ) && $_REQUEST['page'] === 'help-docs' ) {

		wp_enqueue_script(
			'fast-image-zoom',
			OSIM_CORE_PLUGIN_DIR_URL . 'assets/js/fast-image-zoom.js',
			[ 'jquery' ]
		);

		wp_enqueue_style(
			'pw-help-docs',
			OSIM_CORE_PLUGIN_DIR_URL . 'assets/css/help-docs.css',
			[],
			filemtime( OSIM_CORE_PLUGIN_DIR . '/assets/css/help-docs.css' )
		);

		// Also attempt to load all front-end styles that might be needed for custom block demos etc.
		// todo: check how this works in block themes - do we need to get theme.json etc. into this?
		wp_enqueue_style( 'wp-block-library' );
		wp_enqueue_style( 'wp-block-library-theme' );

		add_filter( 'should_load_block_editor_scripts_and_styles', '__return_true' );
		wp_enqueue_registered_block_scripts_and_styles();

		if ( ! empty( $_REQUEST['hdid'] ) ) {
			$post_id = (int) $_REQUEST['hdid'];
			$post    = get_post( $post_id );

			if ( $post instanceof WP_Post ) {
				$blocks = parse_blocks( $post->post_content );
				foreach ( $blocks as $block ) {
					$block_type = WP_Block_Type_Registry::get_instance()->get_registered( $block['blockName'] );
					if ( $block_type ) {
						enqueue_block_assets( $block_type, $block );
					}
				}
			}
		}

		if ( function_exists( 'wp_enqueue_global_styles' ) ) {
			wp_enqueue_global_styles();
		}

		// todo: also enqueue Ollie base styles?
		wp_enqueue_style( 'theme-style', get_theme_file_uri( '/assets/css/main.css' ), array(), filemtime( get_theme_file_path( '/assets/css/main.css' ) ) );
	}
}


add_action( 'admin_menu', __NAMESPACE__ . '\add_help_page' );
/**
 * Add Help page.
 *
 * @return void
 */
function add_help_page() {

	add_dashboard_page(
		'Help',
		'Help',
		'manage_options',
		'help-docs',
		__NAMESPACE__ . '\render_help_page',
		10
	);
}


/**
 * Include Help page view file.
 *
 * @return void
 */
function render_help_page() {

	include_once OSIM_CORE_PLUGIN_DIR . '/views/help-page.php';
}


add_action( 'admin_bar_menu', __NAMESPACE__ . '\add_links_to_admin_bar', 2100 );
/**
 * Adds links to the admin bar.
 *
 * @return void
 */
function add_links_to_admin_bar() {

	global $wp_admin_bar;

	// Help docs
	$wp_admin_bar->add_menu(
		array(
			'id'    => 'pw_help_docs',
			'title' => 'HELP',
			'href'  => esc_url( home_url( '/' ) ) . 'wp-admin/index.php?page=help-docs',
		)
	);
}


add_filter( 'pre_option_image_default_size', __NAMESPACE__ . '\help_docs_image_default_size' );
/**
 * Help docs: Adjust default size for inserted images.
 *
 * @param $value
 *
 * @return mixed|string
 */
function help_docs_image_default_size( $value ) {

	global $current_screen;

	if ( isset( $current_screen ) && $current_screen->id == 'help_doc' ) {

		return 'full';
	}

	return $value;
}
