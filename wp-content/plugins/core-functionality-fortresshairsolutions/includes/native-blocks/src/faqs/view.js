document.addEventListener( 'DOMContentLoaded', () => {
	const selector = '.wp-block-osim-faqs.collapsible .wp-block-osim-faq';
	const animationDuration = 300;
	const activeAnimations = new WeakMap();
	const reduceMotion = window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;

	const cleanupAnimation = ( content ) => {
		const existing = activeAnimations.get( content );

		if ( ! existing ) {
			return;
		}

		content.removeEventListener( 'transitionend', existing.handler );
		existing.finalize();
		activeAnimations.delete( content );
	};

	const animateCollapse = ( content ) => {
		cleanupAnimation( content );

		// Respect users who prefer reduced motion.
		if ( reduceMotion ) {
			content.style.display = 'none';
			content.style.removeProperty( 'height' );
			content.style.removeProperty( 'padding-top' );
			content.style.removeProperty( 'padding-bottom' );
			content.style.removeProperty( 'overflow' );
			content.style.removeProperty( 'transition' );
			return;
		}

		const { paddingTop, paddingBottom, display } = window.getComputedStyle( content );
		const startHeight = content.getBoundingClientRect().height;

		content.style.display = display === 'none' ? 'block' : display;
		content.style.overflow = 'hidden';
		content.style.height = `${ startHeight }px`;
		content.style.paddingTop = paddingTop;
		content.style.paddingBottom = paddingBottom;
		content.style.transition = `height ${ animationDuration }ms ease, padding ${ animationDuration }ms ease`;

		// Force reflow before starting the transition.
		content.offsetHeight;

		content.style.height = '0px';
		content.style.paddingTop = '0px';
		content.style.paddingBottom = '0px';

		const finalize = () => {
			if ( timeoutId ) {
				window.clearTimeout( timeoutId );
			}

			content.style.display = 'none';
			content.style.removeProperty( 'height' );
			content.style.removeProperty( 'padding-top' );
			content.style.removeProperty( 'padding-bottom' );
			content.style.removeProperty( 'overflow' );
			content.style.removeProperty( 'transition' );
		};

		const onTransitionEnd = ( event ) => {
			if ( event.target !== content || event.propertyName !== 'height' ) {
				return;
			}

			content.removeEventListener( 'transitionend', onTransitionEnd );
			finalize();
			activeAnimations.delete( content );
		};

		const timeoutId = window.setTimeout( () => {
			content.removeEventListener( 'transitionend', onTransitionEnd );
			finalize();
			activeAnimations.delete( content );
		}, animationDuration + 50 );

		activeAnimations.set( content, { handler: onTransitionEnd, finalize, timeoutId } );
		content.addEventListener( 'transitionend', onTransitionEnd );
	};

	const animateExpand = ( content ) => {
		cleanupAnimation( content );

		const { paddingTop, paddingBottom, display } = window.getComputedStyle( content );
		const targetDisplay = display === 'none' ? 'block' : display;

		if ( reduceMotion ) {
			content.style.display = targetDisplay;
			content.style.removeProperty( 'height' );
			content.style.removeProperty( 'padding-top' );
			content.style.removeProperty( 'padding-bottom' );
			content.style.removeProperty( 'overflow' );
			content.style.removeProperty( 'transition' );
			return;
		}

		// Show for measurement without flashing content.
		const prevVisibility = content.style.visibility;
		content.style.display = targetDisplay;
		content.style.visibility = 'hidden';
		const targetHeight = content.scrollHeight;
		content.style.visibility = prevVisibility;

		content.style.overflow = 'hidden';
		content.style.transition = 'none';
		content.style.height = '0px';
		content.style.paddingTop = '0px';
		content.style.paddingBottom = '0px';

		// Commit the start state before enabling transitions.
		content.offsetHeight;

		content.style.transition = `height ${ animationDuration }ms ease, padding ${ animationDuration }ms ease`;
		content.style.height = `${ targetHeight }px`;
		content.style.paddingTop = paddingTop;
		content.style.paddingBottom = paddingBottom;

		const finalize = () => {
			if ( timeoutId ) {
				window.clearTimeout( timeoutId );
			}

			content.style.removeProperty( 'height' );
			content.style.removeProperty( 'padding-top' );
			content.style.removeProperty( 'padding-bottom' );
			content.style.removeProperty( 'overflow' );
			content.style.removeProperty( 'transition' );
			content.style.display = targetDisplay;
		};

		const onTransitionEnd = ( event ) => {
			if ( event.target !== content || event.propertyName !== 'height' ) {
				return;
			}

			content.removeEventListener( 'transitionend', onTransitionEnd );
			finalize();
			activeAnimations.delete( content );
		};

		const timeoutId = window.setTimeout( () => {
			content.removeEventListener( 'transitionend', onTransitionEnd );
			finalize();
			activeAnimations.delete( content );
		}, animationDuration + 50 );

		activeAnimations.set( content, { handler: onTransitionEnd, finalize, timeoutId } );
		content.addEventListener( 'transitionend', onTransitionEnd );
	};

	const setCollapsedState = ( item, isCollapsed ) => {
		if ( !item ) {
			return;
		}

		item.classList.toggle( 'collapsed', isCollapsed );
		item.dataset.collapsed = isCollapsed ? 'true' : 'false';
	};

	document.querySelectorAll( '.wp-block-osim-faqs' ).forEach( ( faqsBlock ) => {
		faqsBlock.dataset.collapsible = faqsBlock.classList.contains( 'collapsible' ) ? 'true' : 'false';
		faqsBlock.dataset.accordionMode = faqsBlock.classList.contains( 'accordion-mode' ) ? 'true' : 'false';
		faqsBlock.dataset.iconStyle = faqsBlock.classList.contains( 'icon-chevron' ) ? 'chevron' : 'triangle';
		faqsBlock.dataset.iconPosition = faqsBlock.classList.contains( 'icon-right' ) ? 'right' : 'left';
	} );

	document.querySelectorAll( `${ selector } .faq-heading` ).forEach( ( header ) => {
		const item = header.closest( selector );
		const content = item?.querySelector( '.faq-content' );
		const faqsBlock = item?.closest( '.wp-block-osim-faqs.collapsible' );

		if ( ! item || ! content || !faqsBlock ) {
			return;
		}

		const collapseItem = ( nextItem ) => {
			if ( !nextItem ) {
				return;
			}

			const nextContent = nextItem.querySelector( '.faq-content' );
			if ( !nextContent ) {
				return;
			}

			setCollapsedState( nextItem, true );
			animateCollapse( nextContent );
		};

		// Ensure initial state aligns with the marker class.
		if ( item.classList.contains( 'collapsed' ) ) {
			setCollapsedState( item, true );
			content.style.display = 'none';
		} else {
			setCollapsedState( item, false );
		}

		header.addEventListener( 'click', () => {
			const isCollapsed = item.classList.contains( 'collapsed' );

			if ( isCollapsed ) {
				if ( faqsBlock.classList.contains( 'accordion-mode' ) ) {
					faqsBlock.querySelectorAll( '.wp-block-osim-faq' ).forEach( ( sibling ) => {
						if ( sibling !== item && !sibling.classList.contains( 'collapsed' ) ) {
							collapseItem( sibling );
						}
					} );
				}

				setCollapsedState( item, false );
				animateExpand( content );
			} else {
				collapseItem( item );
			}
		} );
	} );
} );
