import {useBlockProps, InnerBlocks} from '@wordpress/block-editor';
import {useEffect} from '@wordpress/element';
import {useSelect} from '@wordpress/data';

const Edit = ( {attributes, setAttributes, clientId} ) => {
	const {uid, activeTab} = attributes;

	useEffect( () => {
		if ( !uid ) {
			setAttributes( {uid: clientId} );
		}
	}, [] );

	const hasInnerBlocks = useSelect(
		( select ) => {
			const {getBlock} = select( 'core/block-editor' );
			const block = getBlock( clientId );
			return block && block.innerBlocks.length > 0;
		},
		[clientId]
	);

	const display = activeTab === uid ? 'block' : 'none';
	return (
		<div {...useBlockProps()}>
			<div className={'osim-tab-panel'} style={{display}}>
				<InnerBlocks
					renderAppender={
						hasInnerBlocks
							? false
							: () => (
								<InnerBlocks.ButtonBlockAppender
									parentClientId={clientId}   // always pass parent ID in a custom renderAppender
								/>
							)
					}
				/>
			</div>
		</div>
	);
};

export default Edit;
