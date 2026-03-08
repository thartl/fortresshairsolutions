document.addEventListener( 'DOMContentLoaded', function () {
  // Handle off-site links and links to PDFs.
  var links = document.querySelectorAll(
      'a[href]:not([href="#"]):not([href^="mailto"]):not([href^="tel"]):not([href^="javascript:"])'
  );

  links.forEach( function ( link ) {
    var suffix = link.href.slice( -4 );

    if ( suffix === '.pdf' || location.hostname !== link.hostname ) {
      link.setAttribute( 'target', '_blank' );
    }
  } );
} );
