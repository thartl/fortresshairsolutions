<?php

/**
 * Retrieve Help Doc.
 *
 * @package     Osim\CoreFunctionality
 * @since       1.0.0
 * @author      thartl
 * @link        https://osiminteractive.com
 * @license     GNU General Public License 2.0+
 */

namespace Osim\CoreFunctionality;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


function get_help_doc() {

	$post_id  = $_POST['post_id'];
	$response = render_help_doc( intval( $post_id ) );

	wp_send_json_success( $response, 'help-data-ok' );
	wp_die();
}


function render_help_doc( $post_id ) {

	$post_title   = get_the_title( intval( $post_id ) );
	$post_content = get_the_content( null, false, intval( $post_id ) );
	$post_content = apply_filters( 'the_content', $post_content );

	$content = '<div class="help-doc">';
	$content .= '<h2>' . $post_title . '</h2>';
	$content .= '<section class="content">' . $post_content . '</section>';
	$content .= '</div>';

	return $content;
}
