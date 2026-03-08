( function ( wp ) {
	const {addFilter} = wp.hooks;
	const {unregisterBlockStyle, registerBlockStyle} = wp.blocks;
	const {domReady} = wp;

	/**
	 * 1) Array of blocks to hide from the inserter (rather than unregister).
	 *    These remain registered internally but won't be user-insertable.
	 */
	// const blocksToHide = [
	// 	'core/rss',
	// ];

	/**
	 * 2) Immediately register a filter to hide blocks before they're displayed in the editor.
	 */
	// addFilter(
	// 	'blocks.registerBlockType',
	// 	'osimpw/hide-core-blocks',
	// 	( settings, blockName ) => {
	// 		if ( blocksToHide.includes( blockName ) ) {
	// 			const supports = settings.supports ?? {};
	// 			return {
	// 				...settings,
	// 				supports: {
	// 					...supports,
	// 					inserter: false,
	// 				},
	// 			};
	// 		}
	// 		return settings;
	// 	}
	// );

	/**
	 * 3) Use domReady JUST for block style unregistration/registration.
	 *    By the time domReady fires, all blocks are registered.
	 */
	// domReady( () => {
	// 	// Unregister certain block styles
	// 	unregisterBlockStyle( 'core/button', ['squared', 'fill'] );
	// 	unregisterBlockStyle( 'core/separator', ['default', 'wide', 'dots'] );
	// 	unregisterBlockStyle( 'core/quote', ['default', 'large', 'plain'] );
	// } );

} )( window.wp );
