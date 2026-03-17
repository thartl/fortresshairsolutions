import {InnerBlocks, useBlockProps} from '@wordpress/block-editor';

export default function save( {attributes} ) {

	const isCollapsed = attributes?.isCollapsed !== false;

	const blockProps = useBlockProps.save( {
		className: isCollapsed ? 'collapsed' : undefined,
	} );

	return (
		<div {...blockProps}>
			<InnerBlocks.Content/>
		</div>
	);
}
