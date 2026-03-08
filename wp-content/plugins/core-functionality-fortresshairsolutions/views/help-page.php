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


$admin_ajax_url = get_admin_url( null, 'admin-ajax.php' );

$docs_args = [
	'post_type'   => 'help_doc',
	'sort_column' => 'menu_order, post_title',
];

$docs = get_pages( $docs_args );


?>
<h1>Help and documentation</h1>
<div id="pw-help-docs">
    <main class="help-docs-content">
        <div class="content">
            <p>Select a help article in the sidebar.</p>
        </div>
        <div class="loading-indicator hidden">
            <div class="inner">
                <div class="lds-ellipsis"><div></div><div></div><div></div><div></div></div>
            </div>
        </div>
    </main>
    <aside class="help-docs-listings">
		<?php
		foreach ( $docs as $doc ) {
			$css_classes = $doc->post_parent ?
				' post-' . $doc->ID . ' child child-of-' . $doc->post_parent :
				' post-' . $doc->ID . ' top_level';
			$link = '<div class="listing' . $css_classes . '"><a href="#" data-id="' . $doc->ID . '">';
			$link .= $doc->post_title;
			$link .= '</a></div>';
			echo $link;
		}
		?>
    </aside>
</div>
<script type="text/javascript">
  ( function( $ ) {

    var admin_ajax_url = '<?php echo $admin_ajax_url; ?>';
    var $mainContent = $( 'main.help-docs-content .content' );
    var $lodingIndicator = $( '.loading-indicator' );
    var imageZoomDestroy;

    // Gets title and content for requested help doc
    function renderHelpDocData( ID ) {

      var data = {
        'action': 'get_help_doc',
        'post_id': ID,
      };

      // Update content
      $.post( admin_ajax_url, data, function( response ) {

        $mainContent.html( response.data );

        $lodingIndicator.addClass( 'hidden' );
        window.requestAnimationFrame( function() {
          $lodingIndicator.css( 'zIndex', '-20' );
        } );

        // Re-init fast-image-zoom
        if ( imageZoomDestroy ) {
          imageZoomDestroy();
        }
        imageZoomDestroy = imageZoom();

      } ).fail(

          function() {
            console.log( 'Could not get Help Doc.' );
          }
      );

    }

    // Click on doc link in sidebar
    $( document ).on( 'click', '.help-docs-listings .listing a', function( e ) {

      e.preventDefault();

      $lodingIndicator.css( 'zIndex', '20' );
      window.requestAnimationFrame( function() {
        $lodingIndicator.removeClass( 'hidden' );
      } );

      var ID = $( e.target ).data().id;

      renderHelpDocData( ID );

      // update URL
      var url = new URL( document.location );
      url.searchParams.set( 'hdid', ID );
      var newUrl = url.href;
      var title = document.title + ' — ' + ID;

      if ( history.pushState ) {
        history.pushState( "", title , newUrl );
      }

    } );

    // Maybe get doc ID from url
    function get_help_doc_from_url() {

      var params = new URLSearchParams( document.location.search.substring( 1 ) );
      var doc_id = params.get( 'hdid' );

      if ( doc_id ) {

        $lodingIndicator.css( 'zIndex', '20' );
        window.requestAnimationFrame( function() {
          $lodingIndicator.removeClass( 'hidden' );
        } );

        renderHelpDocData( doc_id );
      }

    }
    get_help_doc_from_url();

    // Lightbox for images
    $( document ).ready( function() {
      imageZoomDestroy = imageZoom();
    } );


  } )( jQuery );
</script>