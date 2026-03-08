import {InnerBlocks, useBlockProps} from '@wordpress/block-editor';

const Save = ( {attributes} ) => {
	const {
		tabs,
		tabBackgroundColor,
		tabTextColor,
		tabFontWeight,
		tabActiveBackgroundColor,
		tabActiveTextColor,
		tabActiveFontWeight,
		contentBackgroundColor,
		contentTextColor,
	} = attributes;

	const customStyle = {
		'--osim-tabs-tab-bg': tabBackgroundColor,
		'--osim-tabs-tab-color': tabTextColor,
		'--osim-tabs-tab-weight': tabFontWeight,
		'--osim-tabs-tab-active-bg': tabActiveBackgroundColor,
		'--osim-tabs-tab-active-color': tabActiveTextColor,
		'--osim-tabs-tab-active-weight': tabActiveFontWeight,
		'--osim-tabs-content-bg': contentBackgroundColor,
		'--osim-tabs-content-color': contentTextColor,
	};

	const blockProps = useBlockProps.save( {
		className: 'osim-tab-wrapper',
	} );

	return (
		<div {...blockProps} style={{...blockProps.style, ...customStyle}}>
			<div className={'osim-tabs-nav'}>
				{tabs.map( ( tab ) => {
					return (
						<div
							key={tab.uid}
							className={'osim-tab-item'}
							role="tab"
							tabIndex="0"
						>
							<div
								data-tab-id={tab.uid}
								className={`osim-tab-link`}
							>
								<div>{tab.title}</div>
							</div>
						</div>
					);
				} )}
			</div>
			<div className={'osim-tab-content'}>
				<InnerBlocks.Content/>
			</div>
		</div>
	);
};

export default Save;
