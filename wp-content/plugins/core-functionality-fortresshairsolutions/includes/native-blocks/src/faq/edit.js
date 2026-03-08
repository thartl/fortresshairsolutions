import {useBlockProps, InnerBlocks} from '@wordpress/block-editor';
import './editor.scss';

export default function Edit() {

	const blockProps = useBlockProps();

	const BLOCKS_TEMPLATE = [
		['core/heading', { level: 2, className: 'faq-heading', placeholder: 'Enter question...', lock: { remove: true, move: true } }],
		[
			'core/group', { className: 'faq-content', lock: { remove: true, move: true } }, [
				['core/paragraph', { placeholder: 'Enter answer...' }],
		  ]
		],
	];

	return (
		<div {...blockProps}>
			<InnerBlocks
				template={BLOCKS_TEMPLATE}
				renderAppender={ false }
				// templateLock="all"  -- we need the ability to add paragraphs, thus cannot use any available
				// property [contentOnly,all,insert] -- since they all prevent insertion
				// and "insert" cannot be overriden by individual "lock" attributes
			/>
		</div>
	);
}
