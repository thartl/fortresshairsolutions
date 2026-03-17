import {
	InnerBlocks,
	useBlockProps,
	InspectorControls,
	__experimentalColorGradientSettingsDropdown as ColorGradientSettingsDropdown,
	__experimentalUseMultipleOriginColorsAndGradients as useMultipleOriginColorsAndGradients,
	getSpacingPresetCssVar,
	LineHeightControl,
	store as blockEditorStore,
	useSettings,
} from '@wordpress/block-editor';
import {useDispatch, useSelect} from '@wordpress/data';
import {useEffect, useRef} from '@wordpress/element';
import {
	PanelBody,
	ToggleControl,
	SelectControl,
	FontSizePicker,
	RangeControl,
} from '@wordpress/components';
import './editor.scss';

const DEFAULT_FAQ_BORDER_RADIUS = 6;

const FONT_WEIGHT_OPTIONS = [
	{ label: 'Default', value: '' },
	{ label: 'Thin (100)', value: '100' },
	{ label: 'Extra Light (200)', value: '200' },
	{ label: 'Light (300)', value: '300' },
	{ label: 'Regular (400)', value: '400' },
	{ label: 'Medium (500)', value: '500' },
	{ label: 'Semi Bold (600)', value: '600' },
	{ label: 'Bold (700)', value: '700' },
	{ label: 'Extra Bold (800)', value: '800' },
	{ label: 'Black (900)', value: '900' },
];

const ICON_STYLE_OPTIONS = [
	{ label: 'Disclosure triangle', value: 'triangle' },
	{ label: 'Chevron', value: 'chevron' },
];

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

const getFontSizeSlug = ( value ) => {
	const normalized = normalizeValue( value );

	if ( !normalized ) {
		return undefined;
	}

	const presetMatch = normalized.match( /^var:preset\|font-size\|(.+)$/ );
	if ( presetMatch ) {
		return presetMatch[1];
	}

	const cssVarMatch = normalized.match( /^var\(--wp--preset--font-size--([^)]+)\)$/ );
	if ( cssVarMatch ) {
		return cssVarMatch[1];
	}

	return undefined;
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

const getMergedFontSizes = ( fontSizes ) => {
	if ( Array.isArray( fontSizes ) ) {
		return fontSizes;
	}

	if ( !fontSizes || typeof fontSizes !== 'object' ) {
		return [];
	}

	return [ 'default', 'theme', 'custom' ].flatMap( ( origin ) => {
		const group = fontSizes[origin];
		return Array.isArray( group ) ? group : [];
	} );
};

const mergeUniqueFontSizes = ( ...sources ) => {
	const all = sources.flatMap( ( source ) => ( Array.isArray( source ) ? source : [] ) );

	if ( all.length === 0 ) {
		return [];
	}

	const byKey = new Map();

	all.forEach( ( item ) => {
		if ( !item || typeof item !== 'object' ) {
			return;
		}

		const slug = typeof item.slug === 'string' ? item.slug : '';
		const size = typeof item.size === 'string' ? item.size : '';
		const name = typeof item.name === 'string' ? item.name : '';
		const key = slug || size || name;

		if ( !key ) {
			return;
		}

		if ( !byKey.has( key ) ) {
			byKey.set( key, item );
		}
	} );

	return Array.from( byKey.values() );
};

const getFontSizeSettingsFromEditor = ( settings ) => {
	const typography =
		settings?.__experimentalFeatures?.blocks?.['osim/faqs']?.typography ||
		settings?.__experimentalFeatures?.typography ||
		settings?.typography ||
		{};

	return {
		fontSizes: typography.fontSizes,
		customFontSize: typography.customFontSize,
	};
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

export default function Edit( {attributes, setAttributes, clientId} ) {

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
	const blockRef = useRef( null );
	const normalizedIconStyle = normalizeIconStyle( iconStyle );
	const normalizedIconPosition = normalizeIconPosition( iconPosition );
	const rowGap = getRowGap( attributes.style );
	const headingFontSizeSlug = getFontSizeSlug( headingFontSize );
	const headingFontSizeCssValue = getFontSizeCssValue( headingFontSize );
	const headingFontWeightValue = normalizeValue( headingFontWeight );
	const headingLineHeightValue = normalizeValue( headingLineHeight );
	const itemBorderRadiusValue = Number.isFinite( itemBorderRadius )
		? itemBorderRadius
		: DEFAULT_FAQ_BORDER_RADIUS;
	const [fontSizes, customFontSize] = useSettings(
		'typography.fontSizes',
		'typography.customFontSize'
	);
	const editorSettings = useSelect(
		( select ) => select( blockEditorStore )?.getSettings?.() || {},
		[]
	);
	const faqItems = useSelect(
		( select ) => {
			const block = select( blockEditorStore )?.getBlock?.( clientId );
			if ( !block || !Array.isArray( block.innerBlocks ) ) {
				return [];
			}

			return block.innerBlocks
				.filter( ( innerBlock ) => innerBlock?.name === 'osim/faq' )
				.map( ( innerBlock ) => ( {
					clientId: innerBlock.clientId,
					isCollapsed: innerBlock.attributes?.isCollapsed !== false,
				} ) );
		},
		[clientId]
	);
	const {updateBlockAttributes} = useDispatch( blockEditorStore );
	const typographyFromEditor = getFontSizeSettingsFromEditor( editorSettings );
	const mergedFontSizes = mergeUniqueFontSizes(
		getMergedFontSizes( fontSizes ),
		getMergedFontSizes( typographyFromEditor.fontSizes ),
		Array.isArray( editorSettings?.fontSizes ) ? editorSettings.fontSizes : []
	);
	const hasCustomFontSize = typeof customFontSize === 'boolean'
		? customFontSize
		: ( typographyFromEditor.customFontSize !== false );
	const colorGradientSettings = useMultipleOriginColorsAndGradients();
	const hasDropdownColorUi = Boolean( colorGradientSettings?.hasColorsOrGradients );
	const faqClasses = [
		makeCollapsible ? 'collapsible' : '',
		headingBackgroundColor ? 'has-heading-background' : '',
		contentBackgroundColor ? 'has-content-background' : '',
		`icon-${normalizedIconStyle}`,
		`icon-${normalizedIconPosition}`,
		makeCollapsible && accordionMode ? 'accordion-mode' : '',
	].filter( Boolean ).join( ' ' );

	const blockProps = useBlockProps( {
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
			...( headingFontSizeCssValue
				? {'--osim-faqs-heading-font-size': headingFontSizeCssValue}
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

	const BLOCKS_TEMPLATE = [
		['osim/faq'],
	];
	const headingColorSettings = [
		{
			colorValue: headingTextColor,
			label: 'Heading text',
			onColorChange: ( value ) => setAttributes( {headingTextColor: value || undefined} ),
			resetAllFilter: () => setAttributes( {headingTextColor: undefined} ),
		},
		{
			colorValue: headingBackgroundColor,
			label: 'Heading background',
			onColorChange: ( value ) => setAttributes( {headingBackgroundColor: value || undefined} ),
			resetAllFilter: () => setAttributes( {headingBackgroundColor: undefined} ),
		},
	];
	const contentColorSettings = [
		{
			colorValue: contentTextColor,
			label: 'Content text',
			onColorChange: ( value ) => setAttributes( {contentTextColor: value || undefined} ),
			resetAllFilter: () => setAttributes( {contentTextColor: undefined} ),
		},
		{
			colorValue: contentBackgroundColor,
			label: 'Content background',
			onColorChange: ( value ) => setAttributes( {contentBackgroundColor: value || undefined} ),
			resetAllFilter: () => setAttributes( {contentBackgroundColor: undefined} ),
		},
	];
	const colorSettings = [
		...headingColorSettings,
		...contentColorSettings,
	];

	useEffect( () => {
		if ( !makeCollapsible || !accordionMode ) {
			return;
		}

		const openItems = faqItems.filter( ( faqItem ) => faqItem.isCollapsed === false );
		if ( openItems.length <= 1 ) {
			return;
		}

		openItems.slice( 1 ).forEach( ( faqItem ) => {
			updateBlockAttributes( faqItem.clientId, {isCollapsed: true} );
		} );
	}, [makeCollapsible, accordionMode, faqItems, updateBlockAttributes] );

	useEffect( () => {
		const blockElement = blockRef.current;
		if ( !blockElement ) {
			return undefined;
		}

		const itemSelector = '.wp-block-osim-faq';
		const iconHitWidth = 44;
		if ( !makeCollapsible ) {
			return undefined;
		}

		const getFaqItemByClientId = ( itemClientId ) =>
			faqItems.find( ( faqItem ) => faqItem.clientId === itemClientId );

		const setCollapsed = ( itemClientId, isCollapsed ) => {
			if ( !itemClientId ) {
				return;
			}

			updateBlockAttributes( itemClientId, {isCollapsed: Boolean( isCollapsed )} );
		};

		const toggleItemByClientId = ( itemClientId ) => {
			const currentItem = getFaqItemByClientId( itemClientId );
			if ( !currentItem ) {
				return;
			}

			const isCollapsed = currentItem.isCollapsed;

			if ( isCollapsed ) {
				if ( accordionMode ) {
					faqItems.forEach( ( faqItem ) => {
						if ( faqItem.clientId !== itemClientId && faqItem.isCollapsed === false ) {
							setCollapsed( faqItem.clientId, true );
						}
					} );
				}

				setCollapsed( itemClientId, false );
				return;
			}

			setCollapsed( itemClientId, true );
		};

		const onHeadingClick = ( event ) => {
			if ( event.clientX === 0 && event.clientY === 0 ) {
				return;
			}

			const heading = event.target.closest( '.faq-heading' );
			if ( !heading || !blockElement.contains( heading ) ) {
				return;
			}

			const item = heading.closest( itemSelector );
			if ( !item ) {
				return;
			}

			const rect = heading.getBoundingClientRect();
			const isIconClick = normalizedIconPosition === 'right'
				? event.clientX >= rect.right - iconHitWidth
				: event.clientX <= rect.left + iconHitWidth;

			if ( !isIconClick ) {
				return;
			}

			const faqElements = Array.from( blockElement.querySelectorAll( itemSelector ) );
			const itemIndex = faqElements.indexOf( item );
			if ( itemIndex < 0 ) {
				return;
			}

			const itemClientId = faqItems[itemIndex]?.clientId;
			if ( !itemClientId ) {
				return;
			}

			event.preventDefault();
			event.stopPropagation();
			toggleItemByClientId( itemClientId );
		};

		blockElement.addEventListener( 'click', onHeadingClick );
		return () => {
			blockElement.removeEventListener( 'click', onHeadingClick );
		};
	}, [makeCollapsible, accordionMode, normalizedIconPosition, faqItems, updateBlockAttributes] );

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
					<SelectControl
						label="Icon style"
						value={normalizedIconStyle}
						options={ICON_STYLE_OPTIONS}
						disabled={!makeCollapsible}
						onChange={( value ) =>
							setAttributes( {iconStyle: normalizeIconStyle( value )} )
						}
					/>
					<ToggleControl
						label="Display icon on right"
						checked={normalizedIconPosition === 'right'}
						disabled={!makeCollapsible}
						help={
							normalizedIconPosition === 'right'
								? 'Icon appears on the right side of the heading.'
								: 'Icon appears on the left side of the heading.'
						}
						onChange={( value ) =>
							setAttributes( {iconPosition: value ? 'right' : 'left'} )
						}
					/>
					<ToggleControl
						label="Accordion mode"
						checked={Boolean( accordionMode )}
						disabled={!makeCollapsible}
						help="Open one at a time."
						onChange={( value ) =>
							setAttributes( {accordionMode: Boolean( value )} )
						}
					/>
				</PanelBody>
			</InspectorControls>
			<InspectorControls group="styles">
				<PanelBody title="Heading typography" initialOpen={false}>
					<div className="osim-faqs-heading-font-size-control">
						<FontSizePicker
							className="osim-faqs-heading-font-size-picker"
							value={headingFontSizeSlug || headingFontSizeCssValue}
							valueMode={headingFontSizeSlug ? 'slug' : 'literal'}
							onChange={( value, metadata ) => {
								const nextValue = metadata?.slug
									? `var:preset|font-size|${metadata.slug}`
									: normalizeValue( value );
								setAttributes( {headingFontSize: nextValue} );
							}}
							fontSizes={mergedFontSizes.length ? mergedFontSizes : undefined}
							disableCustomFontSizes={!hasCustomFontSize}
							withReset={true}
							withSlider={true}
							size="__unstable-large"
						/>
					</div>
					<SelectControl
						label="Heading font weight"
						value={headingFontWeightValue || ''}
						options={FONT_WEIGHT_OPTIONS}
						onChange={( value ) =>
							setAttributes( {
								headingFontWeight: normalizeValue( value ),
							} )
						}
					/>
					<LineHeightControl
						__unstableInputWidth="auto"
						value={headingLineHeightValue}
						onChange={( value ) =>
							setAttributes( {
								headingLineHeight: normalizeValue( value ),
							} )
						}
						size="__unstable-large"
					/>
				</PanelBody>
				<PanelBody title="Border radius" initialOpen={false}>
					<RangeControl
						label="FAQ border radius"
						value={itemBorderRadiusValue}
						onChange={( value ) =>
							setAttributes( {
								itemBorderRadius: typeof value === 'number'
									? value
									: DEFAULT_FAQ_BORDER_RADIUS,
							} )
						}
						min={0}
						max={40}
						step={1}
						withInputField={true}
						allowReset={true}
						resetFallbackValue={DEFAULT_FAQ_BORDER_RADIUS}
					/>
				</PanelBody>
			</InspectorControls>
			{hasDropdownColorUi && (
				<InspectorControls group="color">
					{colorSettings.map( ( setting ) => (
						<ColorGradientSettingsDropdown
							key={`faqs-color-${setting.label}`}
							__experimentalIsRenderedInSidebar
							panelId={clientId}
							settings={[
								{
									...setting,
									isShownByDefault: true,
									enableAlpha: true,
									clearable: true,
								},
							]}
							{...colorGradientSettings}
						/>
					) )}
				</InspectorControls>
			)}
			<div {...blockProps} ref={blockRef}>
				<InnerBlocks
					template={BLOCKS_TEMPLATE}
				/>
			</div>
		</>
	);
}
