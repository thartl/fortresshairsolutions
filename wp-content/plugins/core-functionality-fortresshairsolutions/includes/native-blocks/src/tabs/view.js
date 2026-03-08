document.addEventListener( 'DOMContentLoaded', () => {
	const wrappers = Array.from( document.querySelectorAll( '.osim-tab-wrapper' ) );

	const activateTab = ( navLinks, panels, tabId ) => {
		navLinks.forEach( ( link ) => {
			link.classList.toggle( 'is-active', link.dataset.tabId === tabId );
		} );

		panels.forEach( ( panel ) => {
			panel.classList.toggle( 'is-active', panel.dataset.tabId === tabId );
		} );
	};

	wrappers.forEach( ( wrapper ) => {
		const navLinks = Array.from( wrapper.querySelectorAll( '.osim-tabs-nav .osim-tab-link' ) );
		const panels = Array.from( wrapper.querySelectorAll( '.osim-tab-content .osim-tab-panel' ) );

		if ( ! navLinks.length || ! panels.length ) {
			return;
		}

		const initialTabId =
			navLinks.find( ( link ) => link.classList.contains( 'is-active' ) )?.dataset.tabId ||
			navLinks[0]?.dataset.tabId;

		if ( initialTabId ) {
			activateTab( navLinks, panels, initialTabId );
		}

		const nav = wrapper.querySelector( '.osim-tabs-nav' );

		if ( ! nav ) {
			return;
		}

		const handleActivate = ( tabId ) => {
			if ( ! tabId ) {
				return;
			}

			activateTab( navLinks, panels, tabId );
		};

		nav.addEventListener( 'click', ( event ) => {
			const tabItem = event.target.closest( '.osim-tab-item' );

			if ( ! tabItem || ! wrapper.contains( tabItem ) ) {
				return;
			}

			const link = tabItem.querySelector( '.osim-tab-link' );
			const tabId = link?.dataset.tabId;
			handleActivate( tabId );
		} );

		nav.addEventListener( 'keydown', ( event ) => {
			if ( event.key !== 'Enter' && event.key !== ' ' ) {
				return;
			}

			const tabItem = event.target.closest( '.osim-tab-item' );

			if ( ! tabItem || ! wrapper.contains( tabItem ) ) {
				return;
			}

			event.preventDefault();
			const link = tabItem.querySelector( '.osim-tab-link' );
			const tabId = link?.dataset.tabId;
			handleActivate( tabId );
		} );
	} );
} );
