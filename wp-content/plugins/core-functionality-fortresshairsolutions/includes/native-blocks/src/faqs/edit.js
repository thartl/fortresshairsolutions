import {InnerBlocks, useBlockProps, InspectorControls, PanelColorSettings} from '@wordpress/block-editor';
import {PanelBody, ToggleControl} from '@wordpress/components';
import './editor.scss';

export default function Edit( {attributes, setAttributes} ) {

	const {
		makeCollapsible,
		headingTextColor,
		headingBackgroundColor,
		contentTextColor,
		contentBackgroundColor,
	} = attributes;

	const blockProps = useBlockProps( {
		className: makeCollapsible ? 'collapsible' : undefined,
		style: {
			'--osim-faqs-heading-bg': headingBackgroundColor,
			'--osim-faqs-heading-color': headingTextColor,
			'--osim-faqs-content-bg': contentBackgroundColor,
			'--osim-faqs-content-color': contentTextColor,
		},
	} );

	const BLOCKS_TEMPLATE = [
		['osim/faq'],
	];

	return (
		<>
			<InspectorControls>
				<PanelBody
					title="FAQ Settings"
					initialOpen={true}
				>
					<ToggleControl
						label="Make collapsible"
						checked={makeCollapsible}
						help={
							makeCollapsible
								? 'FAQ items are collapsible.'
								: 'All FAQ items are visible.'
						}
						onChange={( value ) =>
							setAttributes( {makeCollapsible: value} )
						}
					/>
				</PanelBody>
			</InspectorControls>
			<InspectorControls group="styles">
				<PanelColorSettings
					title="Heading colors"
					initialOpen={false}
					colorSettings={[
						{
							value: headingBackgroundColor,
							onChange: ( value ) => setAttributes( { headingBackgroundColor: value || undefined } ),
							label: 'Heading background',
						},
						{
							value: headingTextColor,
							onChange: ( value ) => setAttributes( { headingTextColor: value || undefined } ),
							label: 'Heading text',
						},
					]}
				/>
				<PanelColorSettings
					title="Content colors"
					initialOpen={false}
					colorSettings={[
						{
							value: contentBackgroundColor,
							onChange: ( value ) => setAttributes( { contentBackgroundColor: value || undefined } ),
							label: 'Content background',
						},
						{
							value: contentTextColor,
							onChange: ( value ) => setAttributes( { contentTextColor: value || undefined } ),
							label: 'Content text',
						},
					]}
				/>
			</InspectorControls>
			<div {...blockProps}>
				<InnerBlocks
					template={BLOCKS_TEMPLATE}
				/>
			</div>
		</>
	);
}
