<?php
/**
 * Custom post type Help Docs
 *
 * @package     Osim\CoreFunctionality
 * @since       1.0.0
 * @author      thartl
 * @link        https://osiminteractive.com
 * @license     GNU General Public License 2.0+
 */

namespace Osim\CoreFunctionality;


/**
 * Help Docs custom post type + helpers
 *
 * Based on: https://github.com/billerickson/Core-Functionality/blob/master/inc/cpt-testimonial.php
 *
 * @since 2.0.0
 */
class Help_Docs {

	/**
	 * Initialize all the things
	 *
	 * @since 2.0.0
	 */
	function __construct() {

		// Actions
		add_action( 'init', array( $this, 'register_cpt' ) );

		// Yep, "gettext" is a filter...
		add_action( 'gettext', array( $this, 'title_placeholder' ) );
	}

	/**
	 * Register the taxonomies
	 *
	 * @since 2.0.0
	 */
	function register_tax() {

		$labels = array(
			'name'                       => 'Help Categories',
			'singular_name'              => 'Help Category',
			'search_items'               => 'Search Help Categories',
			'popular_items'              => 'Popular Help Categories',
			'all_items'                  => 'All Help Categories',
			'parent_item'                => 'Parent Help Category',
			'parent_item_colon'          => 'Parent Help Category:',
			'edit_item'                  => 'Edit Help Category',
			'update_item'                => 'Update Help Category',
			'add_new_item'               => 'Add New Help Category',
			'new_item_name'              => 'New Help Category',
			'separate_items_with_commas' => 'Separate Help Categories with commas',
			'add_or_remove_items'        => 'Add or remove Help Categories',
			'choose_from_most_used'      => 'Choose from most used Help Categories',
			'menu_name'                  => 'Categories',
		);

		$args = array(
			'labels'             => $labels,
			'publicly_queryable' => true,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'show_in_nav_menus'  => true,
			'show_in_rest'       => true,
			'show_in_quick_edit' => false,
			'show_tagcloud'      => false,
			'hierarchical'       => true,
			'rewrite'            => array( 'slug' => 'help_docs/categories', 'with_front' => false ),
			'query_var'          => true,
			'show_admin_column'  => true,
		);

		register_taxonomy( 'help_docs-cat', array( 'help_doc' ), $args );
	}

	/**
	 * Register the custom post type
	 *
	 * @since 2.0.0
	 */
	function register_cpt() {

		$labels = array(
			'name'               => 'Help Docs',
			'singular_name'      => 'Help Doc',
			'add_new'            => 'Add New',
			'add_new_item'       => 'Add New Help Doc',
			'edit_item'          => 'Edit Help Doc',
			'new_item'           => 'New Help Doc',
			'view_item'          => 'View Help Doc',
			'search_items'       => 'Search Help Docs',
			'not_found'          => 'No Help Docs found',
			'not_found_in_trash' => 'No Help Docs found in Trash',
			'parent_item_colon'  => 'Parent Help Doc:',
			'menu_name'          => 'Edit Help Docs',
		);

		$args = array(
			'labels'              => $labels,
			'hierarchical'        => true,
			'supports'            => array( 'title', 'editor', 'revisions', 'page-attributes' ),
			'show_in_rest'        => true, // Also enables block editor
//			'public'              => false, // set individually as exclude_from_search, publicly_queryable, show_in_nav_menus, and show_ui
			'show_ui'             => true,
			'show_in_menu'        => true,
			'menu_position'       => 2,
			'show_in_nav_menus'   => false,
			'publicly_queryable'  => false,
			'exclude_from_search' => true,
			'has_archive'         => false,
			'query_var'           => true,
			'can_export'          => true,
//			'rewrite'             => array( 'slug' => 'help_doc', 'with_front' => false ),
			'menu_icon'           => 'dashicons-admin-page', // https://developer.wordpress.org/resource/dashicons/
		);

		register_post_type( 'help_doc', $args );
	}

	/**
	 * Change the default title placeholder text
	 *
	 * @param string $translation
	 *
	 * @return string Customized translation for title
	 * @since 2.0.0
	 * @global array $post
	 */
	function title_placeholder( $translation ) {

		global $post;
		if ( isset( $post ) && 'help_doc' == $post->post_type && 'Add title' == $translation ) {
			$translation = "Enter Help Doc title";
		}

		return $translation;

	}
}


new Help_Docs();
