import {Button, PanelBody} from '@wordpress/components';
import {useBlockProps, InnerBlocks, RichText, InspectorControls, PanelColorSettings} from '@wordpress/block-editor';
import {SelectControl} from '@wordpress/components';
import {createBlock} from '@wordpress/blocks';
import {dispatch, select} from '@wordpress/data';
import {useEffect} from '@wordpress/element';
import './editor.scss';

const Edit = ( {attributes, setAttributes, clientId} ) => {
	const {
		tabs,
		activeTab,
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

	const blockProps = useBlockProps( {
		className: 'osim-tab-wrapper',
	} );

	useEffect( () => {
		if ( tabs.length && !activeTab ) {
			setActiveTab( tabs[0].uid );
		}
	}, [tabs] );

	const setActiveTab = ( uid ) => {
		setAttributes( {activeTab: uid} );
		const parentBlock = select( 'core/block-editor' ).getBlock( clientId );

		parentBlock.innerBlocks.forEach( ( innerBlock ) => {
			dispatch( 'core/block-editor' ).updateBlockAttributes(
				innerBlock.clientId,
				{
					activeTab: uid,
				}
			);
		} );
	};

	const tabTitleChange = ( newValue ) => {
		setAttributes( {
			tabs: [
				...tabs.map( ( t ) => {
					return t.uid === activeTab
						? {
							...t,
							title: newValue,
						}
						: t;
				} ),
			],
		} );
	};

	/**
	 * Create a tab.
	 */
	const addNewTab = () => {
		const tab = createBlock( 'osim/tab' );
		const position = tabs.length;
		dispatch( 'core/block-editor' ).insertBlock( tab, position, clientId );
		setAttributes( {
			tabs: [
				...tabs,
				{
					uid: tab.clientId,
					title: `Tab ${tabs.length + 1}`,
				},
			],
		} );
		setActiveTab( tab.clientId );
	};

	const ArrowUpIcon = () => (
		<svg viewBox="0 0 24 24" width="24" height="24" aria-hidden="true" focusable="false">
			<path d="M6.5 12.4L12 8l5.5 4.4-.9 1.2L12 10l-4.5 3.6-1-1.2z"/>
		</svg>
	);

	const ArrowDownIcon = () => (
		<svg viewBox="0 0 24 24" width="24" height="24" aria-hidden="true" focusable="false">
			<path d="M17.5 11.6L12 16l-5.5-4.4.9-1.2L12 14l4.5-3.6 1 1.2z"/>
		</svg>
	);

	/**
	 * Move a tab one position up or down.
	 * @param {string} uid   Tab UID to move
	 * @param {number} delta -1 → up, +1 → down
	 */
	const moveTab = ( uid, delta ) => {
		const index = tabs.findIndex( ( t ) => t.uid === uid );
		if ( index < 0 ) {
			return;
		}

		const newIndex = index + delta;
		if ( newIndex < 0 || newIndex >= tabs.length ) {
			return;
		}

		/* ── 1. Identify child block ─────────────────────────────────────── */
		const {getBlock, getBlockIndex} = select( 'core/block-editor' );
		const parent = getBlock( clientId );          // Tabs block
		const childBlk = parent.innerBlocks[index];
		const childId = childBlk.clientId;

		/* save current lock so we can restore later */
		const originalLock = childBlk.attributes?.lock || {};

		/* ── 2. Unlock .move if needed ───────────────────────────────────── */
		if ( originalLock.move ) {
			dispatch( 'core/block-editor' ).updateBlockAttributes(
				childId,
				{lock: {...originalLock, move: false}}
			);
		}

		/* ── 3. Move the block within the same parent ───────────────────── */
		const currentPos = getBlockIndex( childId, clientId );
		const targetPos = currentPos + delta;

		dispatch( 'core/block-editor' ).moveBlockToPosition(
			childId,        // block to move
			clientId,       // fromRootClientId
			clientId,       // toRootClientId (same list)
			targetPos       // new index
		);

		/* ── 4. Re-lock .move so the panel can’t be dragged manually ────── */
		dispatch( 'core/block-editor' ).updateBlockAttributes(
			childId,
			{lock: {...originalLock, move: true}}
		);

		/* ── 5. Sync the tabs attribute ─────────────────────────────────── */
		const newTabs = [...tabs];
		const [moved] = newTabs.splice( index, 1 );
		newTabs.splice( index + delta, 0, moved );
		setAttributes( {tabs: newTabs} );
	};

	/**
	 * Delete a tab (nav label + content panel) by its UID.
	 */
	const deleteTab = ( uid ) => {
		// ── 1. Locate the child block that owns this UID ─────────────────────────
		const parent = select( 'core/block-editor' ).getBlock( clientId );
		const childBlk = parent?.innerBlocks.find(
			( b ) => b.attributes?.uid === uid
		);

		if ( childBlk ) {
			// If the block is locked, temporarily unlock it so removeBlocks works.
			if ( childBlk.attributes?.lock?.remove ) {
				dispatch( 'core/block-editor' ).updateBlockAttributes(
					childBlk.clientId,
					{lock: {move: true, remove: false}}
				);
			}

			// Remove the block.
			dispatch( 'core/block-editor' ).removeBlocks( childBlk.clientId );
		}

		// ── 2. Drop the label from <Tabs> attributes without leaving holes ────────
		const newTabs = tabs.filter( ( t ) => t.uid !== uid );
		setAttributes( {tabs: newTabs} );

		// ── 3. Fix activeTab pointer ──────────────────────────────────────────────
		if ( activeTab === uid ) {
			setAttributes( {activeTab: newTabs[0]?.uid || ''} );
		}
	};

	return (
		<>
			<InspectorControls>
				<PanelBody title="Manage tabs" initialOpen={true}>
					{tabs?.length ? (
						<ul className="osim-tabs__sidebar-list">
							{tabs.map( ( {uid, title}, i ) => (
								<li key={uid} className="osim-tabs__sidebar-item">
									{/* Title pill */}
									<Button
										className="osim-tabs__title-btn"
										variant="secondary"
										disabled
									>
										{title || `Tab ${i + 1}`}
									</Button>
									{/* Up */}
									<Button
										variant="secondary"
										icon={<ArrowUpIcon/>}
										label="Move tab up"
										disabled={i === 0}
										onClick={() => moveTab( uid, -1 )}
										className="osim-tabs__reorder-btn"
									/>
									{/* Down */}
									<Button
										variant="secondary"
										icon={<ArrowDownIcon/>}
										label="Move tab down"
										disabled={i === tabs.length - 1}
										onClick={() => moveTab( uid, 1 )}
										className="osim-tabs__reorder-btn"
									/>
									{/* Delete */}
									<Button
										icon="no-alt"
										label="Delete tab"
										variant="tertiary"
										isDestructive         // gives WP’s red
										onClick={() => deleteTab( uid )}
										className="osim-tabs__delete-btn"
									/>
								</li>
							) )}
						</ul>
					) : (
						<p>No tabs yet – use “Add Tab”.</p>
					)}
				</PanelBody>
			</InspectorControls>
			<InspectorControls group="styles">
				<PanelColorSettings
					title="Tab colors"
					initialOpen={false}
					colorSettings={[
						{
							value: tabBackgroundColor,
							onChange: ( value ) => setAttributes( { tabBackgroundColor: value || undefined } ),
							label: 'Tab background',
						},
						{
							value: tabTextColor,
							onChange: ( value ) => setAttributes( { tabTextColor: value || undefined } ),
							label: 'Tab text',
						},
					]}
				/>
				<PanelColorSettings
					title="Active tab colors"
					initialOpen={false}
					colorSettings={[
						{
							value: tabActiveBackgroundColor,
							onChange: ( value ) => setAttributes( { tabActiveBackgroundColor: value || undefined } ),
							label: 'Active tab background',
						},
						{
							value: tabActiveTextColor,
							onChange: ( value ) => setAttributes( { tabActiveTextColor: value || undefined } ),
							label: 'Active tab text',
						},
					]}
				/>
				<PanelBody
					title="Typography"
					initialOpen={false}
				>
					<SelectControl
						label="Tab weight"
						value={tabFontWeight || ''}
						options={[
							{ label: 'Default', value: '' },
							{ label: 'Normal (400)', value: '400' },
							{ label: 'Medium (500)', value: '500' },
							{ label: 'Semi-bold (600)', value: '600' },
							{ label: 'Bold (700)', value: '700' },
						]}
						onChange={( value ) => setAttributes( { tabFontWeight: value || undefined } )}
					/>
					<SelectControl
						label="Active tab weight"
						value={tabActiveFontWeight || ''}
						options={[
							{ label: 'Default', value: '' },
							{ label: 'Normal (400)', value: '400' },
							{ label: 'Medium (500)', value: '500' },
							{ label: 'Semi-bold (600)', value: '600' },
							{ label: 'Bold (700)', value: '700' },
						]}
						onChange={( value ) => setAttributes( { tabActiveFontWeight: value || undefined } )}
					/>
				</PanelBody>
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
			<div {...blockProps} style={{...blockProps.style, ...customStyle}}>
				<div className={'osim-tabs-nav'}>
					{tabs.map( ( tab ) => {
						return (
							<div
								key={tab.uid}
								className={'osim-tab-item'}
								role="tab"
								tabIndex="0"
								onClick={() => setActiveTab( tab.uid )}
							>
								<div
									className={`osim-tab-link${
										tab.uid === activeTab
											? ' is-active'
											: ''
									}`}
								>
									<RichText
										tagName="div"
										value={tab.title}
										onChange={tabTitleChange}
									/>
								</div>
							</div>
						);
					} )}
					<Button
						variant={'primary'}
						icon={'plus'}
						className={'osim-add-tab'}
						onClick={addNewTab}
					> Add Tab</Button>
				</div>
				<div className={'osim-tab-content'}>
					<InnerBlocks
						allowedBlocks={['osim/tab']}
						renderAppender={false}
					/>
				</div>
			</div>
		</>
	);
};

export default Edit;
