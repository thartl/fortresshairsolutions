<?php
/**
 * Registers all blocks.
 *
 * To add a block: create a new directory in blocks/src, set up base files, and modify them.
 * Then run npm start / npm build.
 */

namespace Osim\CoreFunctionality;

defined( 'ABSPATH' ) || exit;


require_once __DIR__ . '/build/quick-form/init.php';

/**
 * Registers the block using the metadata loaded from the `block.json` file.
 * Behind the scenes, it registers also all assets, so they can be enqueued
 * through the block editor in the corresponding context.
 *
 * @see https://developer.wordpress.org/reference/functions/register_block_type/
 */
add_action( 'init', __NAMESPACE__ . '\\register_all_blocks' );
/**
 * Register all blocks defined in blocks/build/ (instances of block.json copied from /src).
 *
 * @return void
 */
function register_all_blocks() {

	foreach ( glob( __DIR__ . '/build/*/block.json' ) as $block_json ) {

		$block_folder = dirname( $block_json );

		register_block_type( $block_folder );
	}
}
