<?php

/**
 * General functions.
 *
 * @package     Osim\CoreFunctionality
 * @since       1.0.0
 * @author      thartl
 * @license     GNU General Public License 2.0+
 */

namespace Osim\CoreFunctionality;

defined( 'ABSPATH' ) || exit;


add_action( 'init', __NAMESPACE__ . '\register_footer_shortcodes' );
/**
 * Registers footer shortcodes.
 *
 * Adds the 'year' shortcode to display the current year in the footer.
 *
 * @return void
 */
function register_footer_shortcodes(): void {
	add_shortcode( 'year', __NAMESPACE__ . '\sc_year' );
}

function sc_year(): string {
	return wp_date( 'Y' );
}


add_filter( 'baguettebox_enqueue_assets', __NAMESPACE__ . '\maybe_load_baguettebox_assets', 999 );
/**
 * Only load BaguetteBox assets if post-content contains a gallery block or an image block with images linked to media.
 *
 * @param bool $enqueue_assets Indicator whether the assets should be enqueued.
 *
 * @return bool True if the BaguetteBox assets should be enqueued, false otherwise.
 */
function maybe_load_baguettebox_assets( bool $enqueue_assets ): bool {
	if ( ! is_singular() ) {
		return false;
	}

	if ( ! $enqueue_assets ) {
		return false;
	}

	global $post;

	if ( ! ( $post instanceof \WP_Post ) ) {
		return false;
	}

	$content = $post->post_content;

	if ( ! has_block( 'core/gallery', $content ) && ! has_block( 'core/image', $content ) ) {
		return false;
	}

	return str_contains( $content, '"linkDestination":"media"' );
}


/**
 * Get user roles.
 *
 * @param  int|null $user_id Optional. User ID. Defaults to current user's ID.
 *
 * @return string[] Array of user roles.
 */
//function get_user_roles( $user_id = null ) {
//
//	$user_id = $user_id ?: get_current_user_id();
//	$user    = get_user_by( 'id', $user_id );
//
//	return $user->roles ?? [];
//}
