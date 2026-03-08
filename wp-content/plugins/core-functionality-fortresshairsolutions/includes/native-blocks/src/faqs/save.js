import {InnerBlocks, useBlockProps} from '@wordpress/block-editor';

export default function save( {attributes} ) {

	const {
		makeCollapsible,
		headingTextColor,
		headingBackgroundColor,
		contentTextColor,
		contentBackgroundColor,
	} = attributes;

	const blockProps = useBlockProps.save( {
		className: makeCollapsible ? 'collapsible' : undefined,
		style: {
			'--osim-faqs-heading-bg': headingBackgroundColor,
			'--osim-faqs-heading-color': headingTextColor,
			'--osim-faqs-content-bg': contentBackgroundColor,
			'--osim-faqs-content-color': contentTextColor,
		},
	} );

	return (
		<div {...blockProps}>
			<InnerBlocks.Content/>
		</div>
	);
}
