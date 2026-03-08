<?php
/**
 * Editor config
 **/

declare( strict_types=1 );

namespace ParkdaleWire\OllieChild;

defined( 'ABSPATH' ) || exit;


if ( is_admin() ) {
	add_action( 'after_setup_theme', __NAMESPACE__ . '\editor_setup', 20 );
	/**
	 * Set up the block editor.
	 *
	 * @return void
	 */
	function editor_setup(): void {
		add_editor_style( THEME_STYLE_REL_PATH );

		// Disable the Block Directory (remote block suggestions) in the editor.
		remove_action( 'enqueue_block_editor_assets', 'wp_enqueue_editor_block_directory_assets' );
	}

	/**
	 * Remove Open Verse integration.
	 */
	add_filter( 'block_editor_settings_all', __NAMESPACE__ . '\disable_openverse', 10, 2 );
	function disable_openverse( $editor_settings, $block_editor_context ) {
		$editor_settings['enableOpenverseMediaCategory'] = false;

		return $editor_settings;
	}

//	add_action( 'enqueue_block_editor_assets', __NAMESPACE__ . '\editor_scripts' );
//	/**
//	 * Manage Gutenberg blocks and block styles
//	 */
//	function editor_scripts() {
//
//		wp_enqueue_script(
//			EDITOR_SCRIPT_HANDLE,
//			get_stylesheet_directory_uri() . '/' . EDITOR_SCRIPT_REL_PATH,
//			array( 'wp-blocks', 'wp-hooks', 'wp-dom' ),
//			get_asset_version( EDITOR_SCRIPT_REL_PATH ),
//			true
//		);
//	}

	add_filter( 'rest_post_dispatch', __NAMESPACE__ . '\filter_ollie_patterns_from_rest', 10, 3 );
	/**
	 * Hide Ollie patterns from the editor inserter REST payload without unregistering them.
	 *
	 * @param mixed           $response Response object.
	 * @param WP_REST_Server  $server   REST server instance.
	 * @param WP_REST_Request $request  Current request.
	 *
	 * @return mixed
	 */
	function filter_ollie_patterns_from_rest( $response, $server, $request ) {
		if ( ! $request instanceof \WP_REST_Request ) {
			return $response;
		}

		if ( '/wp/v2/block-patterns/patterns' !== $request->get_route() ) {
			return $response;
		}

		if ( is_wp_error( $response ) || ! $response instanceof \WP_REST_Response ) {
			return $response;
		}

		$data = $response->get_data();

		if ( ! is_array( $data ) ) {
			return $response;
		}

		$data = array_values(
			array_filter(
				$data,
				static function ( $pattern ) {
					return ! (
						is_array( $pattern ) &&
						isset( $pattern['name'] ) &&
						is_string( $pattern['name'] ) &&
						strpos( $pattern['name'], 'ollie/' ) === 0
					);
				}
			)
		);

		$response->set_data( $data );

		return $response;
	}
}
