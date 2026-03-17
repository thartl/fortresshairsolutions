import {InnerBlocks, useBlockProps, getSpacingPresetCssVar} from '@wordpress/block-editor';

const DEFAULT_FAQ_BORDER_RADIUS = 6;

const normalizeIconStyle = ( value ) =>
	value === 'chevron' ? 'chevron' : 'triangle';

const normalizeIconPosition = ( value ) =>
	value === 'right' ? 'right' : 'left';

const normalizeValue = ( value ) => {
	if ( value === undefined || value === null ) {
		return undefined;
	}

	if ( typeof value === 'number' ) {
		return String( value );
	}

	if ( typeof value !== 'string' ) {
		return undefined;
	}

	const trimmed = value.trim();

	return trimmed === '' ? undefined : trimmed;
};

const getFontSizeCssValue = ( value ) => {
	const normalized = normalizeValue( value );

	if ( !normalized ) {
		return undefined;
	}

	const presetMatch = normalized.match( /^var:preset\|font-size\|(.+)$/ );
	if ( presetMatch ) {
		return `var(--wp--preset--font-size--${presetMatch[1]})`;
	}

	return normalized;
};

const normalizeSpacingValue = ( value ) => {
	if ( typeof value === 'number' ) {
		return String( value );
	}

	if ( typeof value !== 'string' ) {
		return undefined;
	}

	const trimmed = value.trim();

	if ( trimmed === '' ) {
		return undefined;
	}

	return getSpacingPresetCssVar( trimmed );
};

const getRowGap = ( style ) => {
	const blockGap = style?.spacing?.blockGap;

	if ( typeof blockGap === 'string' ) {
		return normalizeSpacingValue( blockGap );
	}

	if ( !blockGap || typeof blockGap !== 'object' ) {
		return undefined;
	}

	const vertical =
		blockGap.vertical ||
		blockGap.top ||
		blockGap.row ||
		'';

	return normalizeSpacingValue( vertical );
};

export default function save( {attributes} ) {

	const {
		makeCollapsible,
		iconStyle,
		iconPosition,
		accordionMode,
		headingTextColor,
		headingBackgroundColor,
		contentTextColor,
		contentBackgroundColor,
		headingFontSize,
		headingFontWeight,
		headingLineHeight,
		itemBorderRadius,
	} = attributes;
	const normalizedIconStyle = normalizeIconStyle( iconStyle );
	const normalizedIconPosition = normalizeIconPosition( iconPosition );
	const rowGap = getRowGap( attributes.style );
	const headingFontSizeValue = getFontSizeCssValue( headingFontSize );
	const headingFontWeightValue = normalizeValue( headingFontWeight );
	const headingLineHeightValue = normalizeValue( headingLineHeight );
	const itemBorderRadiusValue = Number.isFinite( itemBorderRadius )
		? itemBorderRadius
		: DEFAULT_FAQ_BORDER_RADIUS;

	const faqClasses = [
		makeCollapsible ? 'collapsible' : '',
		headingBackgroundColor ? 'has-heading-background' : '',
		contentBackgroundColor ? 'has-content-background' : '',
		`icon-${normalizedIconStyle}`,
		`icon-${normalizedIconPosition}`,
		makeCollapsible && accordionMode ? 'accordion-mode' : '',
	].filter( Boolean ).join( ' ' );

	const blockProps = useBlockProps.save( {
		className: faqClasses || undefined,
		style: {
			'--osim-faqs-heading-bg': headingBackgroundColor,
			'--osim-faqs-heading-color': headingTextColor,
			'--osim-faqs-content-color': contentTextColor,
			...( contentBackgroundColor
				? {
					'--osim-faqs-content-bg': contentBackgroundColor,
				}
				: {} ),
			...( headingFontSizeValue
				? {'--osim-faqs-heading-font-size': headingFontSizeValue}
				: {} ),
			...( headingFontWeightValue
				? {'--osim-faqs-heading-font-weight': headingFontWeightValue}
				: {} ),
			...( headingLineHeightValue
				? {'--osim-faqs-heading-line-height': headingLineHeightValue}
				: {} ),
			'--osim-faqs-border-radius': `${itemBorderRadiusValue}px`,
			...( rowGap ? {'--osim-faqs-row-gap': rowGap} : {} ),
		},
	} );

	return (
		<div {...blockProps}>
			<InnerBlocks.Content/>
		</div>
	);
}
