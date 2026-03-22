( () => {
	const EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

	const PAGE_LOADED_AT = Date.now();

	const getEl = ( id ) => ( id ? document.getElementById( id ) : null );

	const seedLoadedAt = ( form ) => {
		const input = form.querySelector( 'input[name="pwire_loaded_at"]' );
		if ( !input ) {
			return;
		}

		if ( !input.value ) {
			input.value = String( PAGE_LOADED_AT );
		}
	};

	const seedLoadedAtAll = () => {
		document.querySelectorAll( '.pwire-quick-form__form' ).forEach( seedLoadedAt );
	};

	const getSubmitButton = ( form ) => form.querySelector( '.pwire-quick-form__button' );

	const ensureSpinner = ( buttonEl ) => {
		if ( !buttonEl ) {
			return;
		}

		if ( buttonEl.querySelector( '.pwire-quick-form__spinner' ) ) {
			return;
		}

		const spinner = document.createElement( 'span' );
		spinner.className = 'pwire-quick-form__spinner';
		spinner.setAttribute( 'aria-hidden', 'true' );

		buttonEl.insertBefore( spinner, buttonEl.firstChild );
	};

	const setSubmittingUi = ( form, isSubmitting ) => {
		const btn = getSubmitButton( form );
		if ( !btn ) {
			return;
		}

		ensureSpinner( btn );

		if ( isSubmitting ) {
			form.dataset.pwireSubmitting = '1';
			form.setAttribute( 'aria-busy', 'true' );

			btn.classList.add( 'is-loading' );
			btn.disabled = true;
			btn.setAttribute( 'aria-disabled', 'true' );
			return;
		}

		delete form.dataset.pwireSubmitting;
		form.removeAttribute( 'aria-busy' );

		btn.classList.remove( 'is-loading' );
		btn.disabled = false;
		btn.removeAttribute( 'aria-disabled' );
	};

	const setHidden = ( el, hidden ) => {
		if ( !el ) {
			return;
		}
		if ( hidden ) {
			el.setAttribute( 'hidden', '' );
		}
		else {
			el.removeAttribute( 'hidden' );
		}
	};

	const clearErrors = ( form, errorsEl ) => {
		if ( errorsEl ) {
			errorsEl.textContent = '';
			setHidden( errorsEl, true );
		}

		form.querySelectorAll( '[aria-invalid="true"]' ).forEach( ( el ) => {
			el.removeAttribute( 'aria-invalid' );
		} );
	};

	const showErrors = ( form, errorsEl, errors ) => {
		if ( !errorsEl ) {
			return;
		}

		const normalizeMsg = ( msg ) => {
			if ( typeof msg !== 'string' ) {
				return '';
			}
			const trimmed = msg.trim();
			if ( !trimmed ) {
				return '';
			}
			return trimmed.charAt( 0 ).toUpperCase() + trimmed.slice( 1 );
		};

		const message = document.createElement( 'p' );
		message.className = 'pwire-quick-form__errors-message';
		message.textContent =
			'There was a problem with your submission. Please review the fields below.';

		const ul = document.createElement( 'ul' );
		ul.className = 'pwire-quick-form__errors-list';

		( Array.isArray( errors ) ? errors : [] ).forEach( ( msg ) => {
			const li = document.createElement( 'li' );
			li.textContent = normalizeMsg( msg );
			ul.appendChild( li );
		} );

		errorsEl.textContent = '';
		errorsEl.appendChild( message );
		errorsEl.appendChild( ul );
		setHidden( errorsEl, false );

		if ( typeof errorsEl.scrollIntoView === 'function' ) {
			errorsEl.scrollIntoView( {behavior: 'smooth', block: 'start'} );
		}

		const firstInvalid = form.querySelector( '[aria-invalid="true"]' );
		if ( firstInvalid && typeof firstInvalid.focus === 'function' ) {
			setTimeout( () => {
				firstInvalid.focus();
			}, 700 );
		}
	};

	const validate = ( form ) => {
		const errors = [];

		const inputs = Array.from( form.querySelectorAll( '.pwire-quick-form__input' ) );
		inputs.forEach( ( el ) => {
			const name = ( el.getAttribute( 'name' ) || '' ).toLowerCase();
			const type = ( el.getAttribute( 'type' ) || '' ).toLowerCase();
			const required = el.hasAttribute( 'required' );
			const value = type === 'checkbox'
				? ( el.checked ? '1' : '' )
				: ( el.value || '' ).trim();

			if ( required && !value ) {
				el.setAttribute( 'aria-invalid', 'true' );
				errors.push( `${name || 'Field'} is required.` );
				return;
			}

			if ( type === 'checkbox' ) {
				return;
			}

			if ( value && type === 'email' && !EMAIL_RE.test( value ) ) {
				el.setAttribute( 'aria-invalid', 'true' );
				errors.push( 'Please enter a valid email address.' );
				return;
			}

			if ( value && type === 'tel' ) {
				const digits = value.replace( /[^\d]/g, '' );

				// If user typed something, require enough digits to consider it a phone number.
				if ( digits.length < 7 ) {
					el.setAttribute( 'aria-invalid', 'true' );
					errors.push( 'Please enter a valid phone number.' );
				}
			}
		} );

		return errors;
	};

	const pushGtmSuccess = ( form ) => {
		if ( !window.dataLayer || !Array.isArray( window.dataLayer ) ) {
			return;
		}

		window.dataLayer.push( {
			event: 'quick_form_submit_success',
			form_id: form.getAttribute( 'id' ) || '',
		} );
	};

	const trackZarazLeadSubmit = async () => {
		await window.zaraz?.track( 'quick_form_submit_success' );
	};

	const submitViaRest = async ( form, restUrl ) => {
		const fd = new FormData( form );

		const res = await fetch( restUrl, {
			method: 'POST',
			body: fd,
			credentials: 'same-origin',
			headers: {
				Accept: 'application/json',
			},
		} );

		let json = null;
		try {
			json = await res.json();
		}
		catch ( e ) {
			// noop
		}

		// If we got a structured JSON response, handle it even on HTTP 400.
		if ( json && typeof json === 'object' ) {
			if ( json.success === true ) {
				return {
					ok: true,
					message: json.message || '',
					isSpam: json.is_spam === true,
				};
			}

			if ( json.success === false ) {
				// Keep server-side errors simple: show the server message only.
				const msg =
					( typeof json.message === 'string' && json.message.trim() ) ||
					'Please check the form and try again.';
				return {ok: false, errors: [msg], fallback: false};
			}
		}

		// Otherwise, if the HTTP status is not ok, treat it as a transport/server failure and fallback.
		if ( !res.ok ) {
			return {ok: false, errors: ['Submission failed. Please try again.'], fallback: true};
		}

		return {ok: false, errors: ['Submission failed. Please try again.'], fallback: true};
	};

	const isSameOriginUrl = ( maybeUrl ) => {
		if ( typeof maybeUrl !== 'string' ) {
			return false;
		}

		const trimmed = maybeUrl.trim();
		if ( !trimmed ) {
			return false;
		}

		try {
			const url = new URL( trimmed, window.location.href );
			return url.origin === window.location.origin;
		}
		catch ( e ) {
			return false;
		}
	};

	const enhanceForm = ( form ) => {
		// Prevent double-binding if this script runs more than once.
		if ( form.dataset.pwireEnhanced === '1' ) {
			return;
		}
		form.dataset.pwireEnhanced = '1';

		form.noValidate = true;
		seedLoadedAt( form );

		const restUrl = form.dataset.restUrl || '';
		const statusId = form.dataset.statusId || '';
		const errorsId = form.dataset.errorsId || '';

		const statusEl = getEl( statusId );
		const errorsEl = getEl( errorsId );

		const confirmationMessage =
			form.dataset.confirmationMessage ||
			( statusEl ? statusEl.textContent.trim() : '' ) ||
			'Thank you! We’ll be in touch soon.';

		const handleSubmit = async ( e ) => {
			// Prevent double submissions for BOTH REST and plain POST.
			if ( form.dataset.pwireSubmitting === '1' ) {
				e.preventDefault();
				return;
			}

			clearErrors( form, errorsEl );

			const errs = validate( form );
			if ( errs.length ) {
				e.preventDefault();
				showErrors( form, errorsEl, errs );
				return;
			}

			const loadedAtEl = form.querySelector( 'input[name="pwire_loaded_at"]' );
			const deltaEl = form.querySelector( 'input[name="pwire_delta_ms"]' );

			const loadedAt = loadedAtEl ? parseInt( loadedAtEl.value || '0', 10 ) : 0;
			const now = Date.now();

			if ( deltaEl ) {
				deltaEl.value = loadedAt > 0 ? String( Math.max( 0, now - loadedAt ) ) : '';
			}

			// Set UI immediately so even "plain POST" is locked after first submit.
			setSubmittingUi( form, true );

			// Only do REST enhancement when restUrl is same-origin and fetch exists.
			// Otherwise, allow default form POST (admin-post.php) to proceed.
			if ( !restUrl || typeof fetch !== 'function' || !isSameOriginUrl( restUrl ) ) {
				return;
			}

			e.preventDefault();

			try {
				const result = await submitViaRest( form, restUrl );

				if ( result.ok ) {
					if ( statusEl ) {
						statusEl.textContent = confirmationMessage;
						setHidden( statusEl, false );
					}

					setHidden( errorsEl, true );
					form.setAttribute( 'hidden', '' );

					if ( result.isSpam !== true ) {
						// pushGtmSuccess( form );
						await trackZarazLeadSubmit();
					}

					return;
				}

				showErrors( form, errorsEl, result.errors || ['Please try again.'] );

				if ( result.fallback ) {
					form.removeEventListener( 'submit', handleSubmit );
					form.submit();
				}
			}
			catch ( err ) {
				form.removeEventListener( 'submit', handleSubmit );
				form.submit();
			}
			finally {
				setSubmittingUi( form, false );
			}
		};

		form.addEventListener( 'submit', handleSubmit );
	};

	seedLoadedAtAll();

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', seedLoadedAtAll, {once: true} );
	}

	const forms = document.querySelectorAll( '.pwire-quick-form__form[data-rest-url]' );
	forms.forEach( enhanceForm );
} )();
