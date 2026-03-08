<?php

/**
 * WP back end modifications.
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


add_action( 'pre_get_posts', __NAMESPACE__ . '\orderby_modified_pages' );
/**
 * Set admin default ordering for pages to `modified` and `DESC`.
 *
 * @param $query
 *
 * @return void
 */
function orderby_modified_pages( $query ) {

	if ( isset( $_GET['orderby'] ) || isset( $_GET['order'] ) ) {
		return;
	}

	$allowed_cpts = [
//		'page',
		'help_doc',
	];

	if( is_admin() && in_array( $query->get( 'post_type' ), $allowed_cpts ) ) {

		$query->set( 'orderby', 'modified' );
		$query->set( 'order', 'DESC' );
	}
}
