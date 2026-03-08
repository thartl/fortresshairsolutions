import {__} from '@wordpress/i18n';
import {useEffect, useMemo} from '@wordpress/element';
import {useSelect, dispatch} from '@wordpress/data';
import {InspectorControls, useBlockProps, store as blockEditorStore} from '@wordpress/block-editor';
import {
	PanelBody,
	TextControl,
	ToggleControl,
	TextareaControl,
	RangeControl,
	ColorPalette,
	Button,
} from '@wordpress/components';

import './editor.scss';

const getInjectedRegistry = () => {
	const reg = globalThis?.PWIRE_QUICK_FORM?.data?.registry;
	return reg && typeof reg === 'object' ? reg : null;
};

const getFieldOrder = () => {
	const reg = getInjectedRegistry();
	if ( reg?.order && Array.isArray( reg.order ) && reg.order.length ) {
		return reg.order;
	}
	return [];
};

const getRegistryFieldDefaults = ( key ) => {
	const reg = getInjectedRegistry();
	const fields = reg?.fields && typeof reg.fields === 'object' ? reg.fields : null;
	const def = fields?.[key];
	if ( !def || typeof def !== 'object' ) {
		return null;
	}

	// Map PHP registry shape -> editor expected shape
	return {
		type: typeof def.type === 'string' ? def.type : 'text',
		label: typeof def.label === 'string' ? def.label : '',
		required: !!def.required,
		enabled: !!def.enabled,
		autocomplete: typeof def.autocomplete === 'string' ? def.autocomplete : '',
	};
};

const getFieldDefaults = ( key ) => {
	return getRegistryFieldDefaults( key ) || {};
};

const FIELD_ORDER = getFieldOrder();

const stripInlineHtml = ( maybeHtml ) => {
	const raw = typeof maybeHtml === 'string' ? maybeHtml : '';

	if ( !raw || raw.indexOf( '<' ) === -1 ) {
		return raw;
	}

	if ( typeof document === 'undefined' ) {
		return raw.replace( /<[^>]*>/g, '' );
	}

	const tmp = document.createElement( 'div' );
	tmp.innerHTML = raw;

	return ( tmp.textContent || tmp.innerText || '' ).trim();
};

const getConfigVersion = () => {
	const reg = getInjectedRegistry();
	const v = reg?.configVersion;
	return typeof v === 'string' ? v : '';
};

const getAdminNoticeForVersion = ( version ) => {
	const reg = getInjectedRegistry();
	const notices = reg?.adminNotices;

	if ( !version || !notices || typeof notices !== 'object' ) {
		return null;
	}

	const notice = notices[version];
	return notice && typeof notice === 'object' ? notice : null;
};

const makeUid = () => {
	const cryptoObj = globalThis.crypto;

	if ( cryptoObj?.randomUUID ) {
		return cryptoObj.randomUUID();
	}

	if ( cryptoObj?.getRandomValues ) {
		const bytes = new Uint8Array( 16 );
		cryptoObj.getRandomValues( bytes );

		// Set version (4) and variant (10)
		bytes[8] = ( bytes[8] & 0x3f ) | 0x80;
		bytes[6] = ( bytes[6] & 0x0f ) | 0x40;

		const hex = Array.from( bytes, ( b ) => b.toString( 16 ).padStart( 2, '0' ) ).join( '' );
		return `${hex.slice( 0, 8 )}-${hex.slice( 8, 12 )}-${hex.slice( 12, 16 )}-${hex.slice( 16, 20 )}-${hex.slice(
			20 )}`;
	}

	throw new Error( 'Secure random values are not available in this environment.' );
};

const remFromPx = ( value ) => {
	if ( typeof value === 'number' && !Number.isNaN( value ) ) {
		return `${value / 16}rem`;
	}

	if ( typeof value === 'string' ) {
		const trimmed = value.trim();

		if ( !trimmed ) {
			return '';
		}

		if ( /rem$/i.test( trimmed ) ) {
			return trimmed;
		}

		const pxMatch = trimmed.match( /^(-?\d+(?:\.\d+)?)px$/i );
		if ( pxMatch ) {
			return `${parseFloat( pxMatch[1] ) / 16}rem`;
		}

		if ( !Number.isNaN( Number( trimmed ) ) ) {
			return `${Number( trimmed ) / 16}rem`;
		}

		return trimmed;
	}

	return '';
};

const normalizeLength = ( value ) => remFromPx( value ) || '';
const toEm = ( value ) => {
	if ( typeof value === 'number' && !Number.isNaN( value ) ) {
		return `${value}em`;
	}

	if ( typeof value === 'string' ) {
		const trimmed = value.trim();
		if ( !trimmed ) {
			return '';
		}
		if ( /em$/i.test( trimmed ) ) {
			return trimmed;
		}
		if ( !Number.isNaN( Number( trimmed ) ) ) {
			return `${Number( trimmed )}em`;
		}
		return trimmed;
	}

	return '';
};

/**
 * Build a minimal override object for a single field by diffing against defaults.
 * Only these keys are stored in block content.
 */
const buildMinimalFieldOverride = ( key, effective ) => {
	const defaults = getFieldDefaults( key ) || {};
	const out = {};

	const keys = ['label', 'enabled', 'required', 'autocomplete'];
	keys.forEach( ( prop ) => {
		if ( !Object.prototype.hasOwnProperty.call( effective, prop ) ) {
			return;
		}

		const defVal = defaults[prop];
		const effVal = effective[prop];

		if ( prop === 'label' || prop === 'autocomplete' ) {
			const a = typeof effVal === 'string' ? effVal : '';
			const b = typeof defVal === 'string' ? defVal : '';
			if ( a !== b ) {
				out[prop] = a;
			}
			return;
		}

		// enabled/required
		const a = !!effVal;
		const b = !!defVal;
		if ( a !== b ) {
			out[prop] = a;
		}
	} );

	return out;
};

/**
 * Return a pruned "fields" overrides object:
 * - only allowlisted field keys (FIELD_ORDER)
 * - only diffs vs defaults
 * - remove empty objects
 */
const pruneFieldsOverrides = ( overrides ) => {
	const src = overrides && typeof overrides === 'object' ? overrides : {};
	const next = {};

	FIELD_ORDER.forEach( ( key ) => {
		const ov = src?.[key] && typeof src[key] === 'object' ? src[key] : null;
		if ( !ov ) {
			return;
		}

		// Compute effective (defaults + overrides), then diff back to minimal override.
		const effective = {...getFieldDefaults( key ), ...ov};
		const minimal = buildMinimalFieldOverride( key, effective );

		if ( Object.keys( minimal ).length ) {
			next[key] = minimal;
		}
	} );

	return next;
};

const updateField = ( fields, key, patch ) => {
	const currentOverrides = fields && typeof fields === 'object' ? fields : {};
	const currentEffective = {...getFieldDefaults( key ), ...( currentOverrides?.[key] || {} )};
	const nextEffective = {...currentEffective, ...patch};

	const minimalForKey = buildMinimalFieldOverride( key, nextEffective );

	const nextOverrides = {...currentOverrides};
	if ( Object.keys( minimalForKey ).length ) {
		nextOverrides[key] = minimalForKey;
	}
	else {
		delete nextOverrides[key];
	}

	return pruneFieldsOverrides( nextOverrides );
};

const ColorControl = ( {label, value, onChange, colors} ) => {
	return (
		<div className="pwire-quick-form__color-control">
			<div style={{marginBottom: '0.5rem', fontSize: '0.75rem'}}>{label}</div>
			<ColorPalette
				colors={colors}
				value={value || undefined}
				onChange={( next ) => onChange( next || '' )}
				clearable
				disableCustomColors={false}
			/>
		</div>
	);
};

const collectUidOwners = ( blocks ) => {
	const owners = {};

	const walk = ( list ) => {
		( list || [] ).forEach( ( b ) => {
			const u = b?.attributes?.uid;
			if ( typeof u === 'string' && u && !owners[u] ) {
				owners[u] = b.clientId;
			}
			if ( b?.innerBlocks?.length ) {
				walk( b.innerBlocks );
			}
		} );
	};

	walk( blocks );

	return owners;
};

export default function Edit( props ) {
	const {attributes, setAttributes, clientId} = props;

	const {
		uid,
		ackVersion,
		fields,
		submitLabel,
		confirmationMessage,
		submitTextColor,
		submitBgColor,
		submitBgHoverColor,
		fieldGap,
		labelColor,
		fieldBgColor,
		fieldBorderColor,
		fieldBorderWidth,
		fieldBorderRadius,
		labelFontSize,
		notificationEmails,
	} = attributes;

	const configVersion = getConfigVersion();
	const adminNotice = getAdminNoticeForVersion( configVersion );

	const shouldShowNotice =
		configVersion &&
		adminNotice?.message &&
		ackVersion !== configVersion;

	useEffect(() => {
		if (!configVersion) {
			return;
		}

		// Only auto-ack when there is NO notice at all
		const shouldAutoAck =
			!adminNotice &&
			ackVersion !== configVersion;

		if (shouldAutoAck) {
			setAttributes({ ackVersion: configVersion });
		}
	}, [configVersion, ackVersion, adminNotice]);

	const blocksNeedingAck = useSelect( ( select ) => {
		const blocks = select( blockEditorStore )?.getBlocks?.() || [];

		const needing = [];

		const walk = ( list ) => {
			( list || [] ).forEach( ( b ) => {
				if ( b?.name === 'pwire/quick-form' ) {
					const av =
						typeof b?.attributes?.ackVersion === 'string'
							? b.attributes.ackVersion
							: '';

					if (
						configVersion &&
						adminNotice?.requireAck &&
						av !== configVersion
					) {
						needing.push( b.clientId );
					}
				}

				if ( b?.innerBlocks?.length ) {
					walk( b.innerBlocks );
				}
			} );
		};

		walk( blocks );

		return needing;
	}, [configVersion, adminNotice] );

	useEffect( () => {
		const lockKey = 'pwire-quick-form-ack';
		const noticeId = 'pwire-quick-form-ack-notice';

		if ( blocksNeedingAck.length ) {
			dispatch( 'core/editor' )?.lockPostSaving?.( lockKey );

			dispatch( 'core/notices' )?.createNotice(
				'error',
				__( 'This page cannot be saved until all Quick Form blocks acknowledge configuration changes.', 'pwire' ),
				{
					id: noticeId,
					isDismissible: false,
				}
			);
		}
		else {
			dispatch( 'core/editor' )?.unlockPostSaving?.( lockKey );
			dispatch( 'core/notices' )?.removeNotice?.( noticeId );
		}
	}, [blocksNeedingAck] );

	const uidOwners = useSelect( ( select ) => {
		const blocks = select( blockEditorStore )?.getBlocks?.() || [];
		return collectUidOwners( blocks );
	}, [] );

	useEffect( () => {
		if ( !uid ) {
			setAttributes( {uid: makeUid()} );
			return;
		}

		const owner = uidOwners?.[uid] || '';
		if ( owner && owner !== clientId ) {
			setAttributes( {uid: makeUid()} );
		}
	}, [uid, uidOwners, clientId] );

	const paletteColors = useSelect( ( select ) => {
		const settings = select( 'core/block-editor' )?.getSettings?.();
		return settings?.colors || [];
	}, [] );

	const normalizedFields = useMemo( () => {
		const out = {};
		FIELD_ORDER.forEach( ( key ) => {
			out[key] = {...getFieldDefaults( key ), ...( fields?.[key] || {} )};
		} );
		return out;
	}, [fields] );

	const activeFieldKeys = useMemo(
		() => FIELD_ORDER.filter( ( key ) => normalizedFields[key]?.enabled ),
		[normalizedFields]
	);

	const wrapperStyle = {
		'--pwire-quick-form-field-gap': normalizeLength( fieldGap ),
		'--pwire-quick-form-label-color': labelColor || '',
		'--pwire-quick-form-field-bg': fieldBgColor || '',
		'--pwire-quick-form-field-border-color': fieldBorderColor || '',
		'--pwire-quick-form-field-border-width': normalizeLength( fieldBorderWidth ),
		'--pwire-quick-form-field-border-radius': normalizeLength( fieldBorderRadius ),
		'--pwire-quick-form-label-font-size': toEm( labelFontSize ) || '',
		...( submitTextColor ? {'--pwire-quick-form-submit-text-color': submitTextColor} : {} ),
		...( submitBgColor ? {'--pwire-quick-form-submit-bg-color': submitBgColor} : {} ),
		...( submitBgHoverColor ? {'--pwire-quick-form-submit-bg-hover-color': submitBgHoverColor} : {} ),
	};

	const fieldGapRem =
		typeof fieldGap === 'number' ? fieldGap / 16 : 1.25;

	const fieldBorderWidthRem =
		typeof fieldBorderWidth === 'number' ? fieldBorderWidth / 16 : 1 / 16;
	const fieldBorderRadiusRem =
		typeof fieldBorderRadius === 'number' ? fieldBorderRadius / 16 : 0.1875;
	const labelFontSizeEm =
		typeof labelFontSize === 'number' ? labelFontSize : 1;

	const blockProps = useBlockProps( {
		className: [
			'pwire-quick-form',
			submitTextColor ? 'has-submit-text-color' : '',
			submitBgColor ? 'has-submit-bg-color' : '',
			submitBgHoverColor ? 'has-submit-bg-hover-color' : '',
		].filter( Boolean ).join( ' ' ),
		style: wrapperStyle,
	} );

	return (
		<>
			<InspectorControls>
				{shouldShowNotice && adminNotice?.message && (
					<PanelBody title={__( 'Important Notice', 'pwire' )} initialOpen>
						<div style={{
							marginBottom: '1rem',
							padding: '0.75rem',
							background: '#fff4e5',
							border: '1px solid #f0b849',
							borderRadius: '4px'
						}}
						>
							<p style={{marginTop: 0}}>
								{adminNotice.message}
							</p>
							<Button
								variant="primary"
								isDestructive
								onClick={() => {
									if ( !configVersion ) {
										return;
									}
									setAttributes( {ackVersion: configVersion} );
								}}
							>
								{__( 'Acknowledge changes', 'pwire' )}
							</Button>
						</div>
					</PanelBody>
					)}
					<PanelBody title={__( 'Configuration', 'pwire' )} initialOpen={false}>
						{FIELD_ORDER.map( ( key ) => {
							const f = normalizedFields[key];
							const enabled = !!f.enabled;

							return (
								<div
									key={key}
									style={{
										padding: '0.75rem 0',
										borderTop: '0.0625rem solid rgba(0,0,0,0.08)',
									}}
								>
									<div style={{display: 'flex', justifyContent: 'space-between', alignItems: 'center'}}>
										<strong style={{fontSize: '0.75rem'}}>{key.toUpperCase()}</strong>
										<ToggleControl
											label=""
											checked={enabled}
											onChange={( nextEnabled ) => {
												setAttributes( {
													fields: updateField( fields, key, {enabled: !!nextEnabled} ),
												} );
											}}
										/>
									</div>
								</div>
							);
						} )}
					</PanelBody>
					<PanelBody title={__( 'Fields', 'pwire' )} initialOpen={false}>
						{activeFieldKeys.length === 0 && (
							<p style={{fontSize: '0.875rem', margin: 0}}>
								{__( 'Activate a field in Configuration to edit its settings.', 'pwire' )}
							</p>
						)}
						{activeFieldKeys.map( ( key ) => {
							const f = normalizedFields[key];
							const fieldType = typeof f?.type === 'string' ? f.type : 'text';
							const isCheckbox = fieldType === 'checkbox';

							return (
								<div
									key={key}
									style={{
									padding: '0.75rem 0',
									borderTop: '0.0625rem solid rgba(0,0,0,0.08)',
								}}
								>
									<div style={{display: 'flex', justifyContent: 'space-between'}}>
										<strong style={{fontSize: '0.75rem'}}>{key.toUpperCase()}</strong>
									</div>

									{isCheckbox ? (
										<TextareaControl
											label={__( 'Label', 'pwire' )}
											help={__( 'Supports inline HTML: <a>, <strong>, <em>.', 'pwire' )}
											value={f.label || ''}
											onChange={( nextLabel ) => {
												setAttributes( {
													fields: updateField( fields, key, {label: nextLabel} ),
												} );
											}}
										/>
									) : (
										<TextControl
											label={__( 'Label', 'pwire' )}
											value={f.label || ''}
											onChange={( nextLabel ) => {
												setAttributes( {
													fields: updateField( fields, key, {label: nextLabel} ),
												} );
											}}
										/>
									)}

									<ToggleControl
										label={__( 'Required', 'pwire' )}
										checked={!!f.required}
										onChange={( nextRequired ) => {
											setAttributes( {
												fields: updateField( fields, key, {required: !!nextRequired} ),
											} );
										}}
									/>
							</div>
						);
					} )}
				</PanelBody>

				<PanelBody title={__( 'Submit & Confirmation', 'pwire' )} initialOpen={false}>
					<TextControl
						label={__( 'Submit button label', 'pwire' )}
						value={submitLabel || ''}
						onChange={( next ) => setAttributes( {submitLabel: next} )}
					/>
					<ColorControl
						label={__( 'Submit text color', 'pwire' )}
						value={submitTextColor}
						onChange={( next ) => setAttributes( {submitTextColor: next} )}
						colors={paletteColors}
					/>
					<div style={{height: '0.75rem'}}/>
					<ColorControl
						label={__( 'Submit background color', 'pwire' )}
						value={submitBgColor}
						onChange={( next ) => setAttributes( {submitBgColor: next} )}
						colors={paletteColors}
					/>
					<div style={{height: '0.75rem'}}/>
					<ColorControl
						label={__( 'Submit hover background color', 'pwire' )}
						value={submitBgHoverColor}
						onChange={( next ) => setAttributes( {submitBgHoverColor: next} )}
						colors={paletteColors}
					/>
					<TextareaControl
						label={__( 'Confirmation message', 'pwire' )}
						value={confirmationMessage || ''}
						onChange={( next ) => setAttributes( {confirmationMessage: next} )}
					/>
				</PanelBody>

				<PanelBody title={__( 'Field Layout', 'pwire' )} initialOpen={false}>
					<RangeControl
						label={__( 'Gap between fields (rem)', 'pwire' )}
						value={fieldGapRem}
						onChange={( next ) =>
							setAttributes( {
								fieldGap: typeof next === 'number' ? next * 16 : 20,
							} )
						}
						min={0}
						max={5}
						step={0.0625}
					/>
				</PanelBody>

				<PanelBody title={__( 'Field Styles', 'pwire' )} initialOpen={false}>
					<ColorControl
						label={__( 'Label color', 'pwire' )}
						value={labelColor}
						onChange={( next ) => setAttributes( {labelColor: next} )}
						colors={paletteColors}
					/>
					<div style={{height: '0.75rem'}}/>
					<ColorControl
						label={__( 'Field background', 'pwire' )}
						value={fieldBgColor}
						onChange={( next ) => setAttributes( {fieldBgColor: next} )}
						colors={paletteColors}
					/>
					<div style={{height: '0.75rem'}}/>
					<ColorControl
						label={__( 'Field border color', 'pwire' )}
						value={fieldBorderColor}
						onChange={( next ) => setAttributes( {fieldBorderColor: next} )}
						colors={paletteColors}
					/>

					<div style={{height: '1rem'}}/>

					<RangeControl
						label={__( 'Field border width (rem)', 'pwire' )}
						value={fieldBorderWidthRem}
						onChange={( next ) =>
							setAttributes( {
								fieldBorderWidth: typeof next === 'number' ? next * 16 : 1,
							} )
						}
						min={0}
						max={0.5}
						step={0.0625}
					/>

					<RangeControl
						label={__( 'Field border radius (rem)', 'pwire' )}
						value={fieldBorderRadiusRem}
						onChange={( next ) =>
							setAttributes( {
								fieldBorderRadius: typeof next === 'number' ? next * 16 : 3,
							} )
						}
						min={0}
						max={2.5}
						step={0.0625}
					/>
				</PanelBody>

				<PanelBody title={__( 'Typography', 'pwire' )} initialOpen={false}>
					<RangeControl
						label={__( 'Label font size (em)', 'pwire' )}
						value={labelFontSizeEm}
						onChange={( next ) =>
							setAttributes( {
								labelFontSize: typeof next === 'number' ? next : 1,
							} )
						}
						min={0.5}
						max={3}
						step={0.05}
					/>
				</PanelBody>

				<PanelBody title={__( 'Notifications', 'pwire' )} initialOpen={false}>
					<TextControl
						label={__( 'Notification emails', 'pwire' )}
						value={notificationEmails || ''}
						onChange={( next ) => setAttributes( {notificationEmails: next} )}
						help={__(
							'Comma-separated list. Leave blank to use the site admin email.',
							'pwire'
						)}
					/>
				</PanelBody>
			</InspectorControls>

			<div {...blockProps}>
				<div className="pwire-quick-form__editor-preview">
					{FIELD_ORDER.filter( ( key ) => normalizedFields[key]?.enabled ).map( ( key ) => {
						const f = normalizedFields[key];
						const id = `pwire-quick-form-${clientId}-${key}`;
						const fieldType = typeof f?.type === 'string' ? f.type : 'text';
						const isTextarea = fieldType === 'textarea';
						const isCheckbox = fieldType === 'checkbox';
						const previewLabel = isCheckbox
							? stripInlineHtml( f.label || key )
							: ( f.label || key );

						return (
							<div
								key={key}
								className={`pwire-quick-form__field${isCheckbox ? ' pwire-quick-form__field--checkbox' : ''}`}
							>
								{isCheckbox ? (
									<label className="pwire-quick-form__checkbox-label" htmlFor={id}>
										<input
											id={id}
											className="pwire-quick-form__input pwire-quick-form__input--checkbox"
											type="checkbox"
											disabled
										/>
										<span className="pwire-quick-form__checkbox-text">
											{previewLabel}
											{f.required ? (
												<span className="pwire-quick-form__required"> *</span>
											) : null}
										</span>
									</label>
								) : (
									<>
										<label className="pwire-quick-form__label" htmlFor={id}>
											{previewLabel}
											{f.required ? (
												<span className="pwire-quick-form__required"> *</span>
											) : null}
										</label>
										{isTextarea ? (
											<textarea
												id={id}
												className="pwire-quick-form__input"
												rows={4}
												disabled
											/>
										) : (
											<input
												id={id}
												className="pwire-quick-form__input"
												type={fieldType === 'email' ? 'email' : fieldType === 'tel' ? 'tel' : 'text'}
												disabled
											/>
										)}
									</>
								)}
							</div>
						);
					} )}

					<div className="pwire-quick-form__actions">
						<button type="button" className="pwire-quick-form__button wp-element-button" disabled>
							{submitLabel || __( 'Send', 'pwire' )}
						</button>
					</div>
				</div>
			</div>
		</>
	);
}
