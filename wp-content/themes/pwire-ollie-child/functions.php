<?php

declare( strict_types=1 );

namespace ParkdaleWire\OllieChild;

defined( 'ABSPATH' ) || exit;


const THEME_STYLE_HANDLE     = 'pwire-ollie-child';
const THEME_STYLE_REL_PATH   = 'assets/css/main.css';
const THEME_SCRIPT_HANDLE    = 'pwire-ollie-child';
const THEME_SCRIPT_REL_PATH  = 'assets/js/dist/global.min.js';
const EDITOR_SCRIPT_HANDLE   = 'pwire-theme-editor';
const EDITOR_SCRIPT_REL_PATH = 'assets/js/dist/editor.min.js';

require_once get_stylesheet_directory() . '/inc/wordpress-config.php';
require_once get_stylesheet_directory() . '/inc/editor-config.php';

/**
 * Determines if the application is running in development mode.
 *
 * @return bool True if the environment type is 'development' or 'local', false otherwise.
 */
function is_dev_mode(): bool {
	return in_array( wp_get_environment_type(), [ 'development', 'local' ], true );
}

/**
 * Retrieves the version of an asset based on the file's modification time (in local/development)
 * or the theme's version (in all other environments).
 *
 * @param string $rel_path The relative path to the asset file.
 *
 * @return string The asset version, either the file's last modified time or the theme version.
 */
function get_asset_version( string $rel_path ): string {
	$theme_ver = (string) wp_get_theme()->get( 'Version' );

	if ( ! is_dev_mode() ) {
		return $theme_ver;
	}

	$file = get_stylesheet_directory() . '/' . ltrim( $rel_path, '/' );

	return file_exists( $file )
		? (string) filemtime( $file )
		: $theme_ver;
}


add_action( 'wp_head', __NAMESPACE__ . '\preload_theme_fonts', 0 );
/**
 * Preload theme fonts for faster rendering.
 *
 * Notes:
 * 1) Preload only what matters most / is visible above fold, i.e., likely upright faces, not italic or bold.
 * 2) Keep these font paths matched to the font sources defined in theme.json.
 *
 * @return void
 */
function preload_theme_fonts(): void {
	$fonts = [
		'assets/fonts/open-sans-normal-latin.woff2',
		'assets/fonts/playfair-display-normal-latin.woff2',
	];

	foreach ( $fonts as $rel ) {
		$href = esc_url( get_theme_file_uri( $rel ) );
		echo '<link rel="preload" href="' . $href . '" as="font" type="font/woff2" crossorigin="anonymous">' . "\n";
	}
}


add_action( 'wp_head', __NAMESPACE__ . '\font_fallbacks_inline', 1 );
/**
 * Fallback font styles for Mona Sans.
 *
 * @return void
 */
function font_fallbacks_inline(): void {

	$file = get_stylesheet_directory() . '/assets/css/font-fallbacks.css';

	if ( ! file_exists( $file ) ) {
		return;
	}

	$css = file_get_contents( $file );

	if ( ! $css ) {
		return;
	}

	echo "<style id=\"pwire-font-fallbacks\">\n";
	echo $css;
	echo "\n</style>\n";
}


add_action( 'wp_enqueue_scripts', __NAMESPACE__ . '\dequeue_parent_styles', 20 );
/**
 * Remove the parent stylesheet (parent reset styles copied to child theme).
 *
 * @return void
 */
function dequeue_parent_styles(): void {
	wp_dequeue_style( 'ollie' );
}


add_action( 'wp_enqueue_scripts', __NAMESPACE__ . '\enqueue_scripts_styles', 20 );
/**
 * Enqueue child theme styles.
 *
 * @return void
 */
function enqueue_scripts_styles(): void {
	wp_enqueue_style(
		THEME_STYLE_HANDLE,
		get_stylesheet_directory_uri() . '/' . THEME_STYLE_REL_PATH,
		[],
		get_asset_version( THEME_STYLE_REL_PATH )
	);

	wp_enqueue_script(
		THEME_SCRIPT_HANDLE,
		get_stylesheet_directory_uri() . '/' . THEME_SCRIPT_REL_PATH,
		[],
		get_asset_version( THEME_SCRIPT_REL_PATH ),
		true
	);
}


add_action( 'init', __NAMESPACE__ . '\enqueue_child_block_styles' );
/**
 * Load custom block styles only when the block is used.
 */
function enqueue_child_block_styles() {

	// Scan the child block styles folder to locate block styles.
	$files = glob( get_stylesheet_directory() . '/assets/css/child-block-styles/*.css' ) ?: [];

	foreach ( $files as $file ) {

		// Get the filename and core block name.
		$filename   = basename( $file, '.css' );
		$block_name = preg_replace( '/^([^-]+)--/', '$1/', $filename, 1 );

		wp_enqueue_block_style(
			$block_name,
			array(
				'handle' => "pwire-block-{$filename}",
				'src'    => get_theme_file_uri( "assets/css/child-block-styles/{$filename}.css" ),
				'path'   => get_theme_file_path( "assets/css/child-block-styles/{$filename}.css" ),
			)
		);
	}
}
