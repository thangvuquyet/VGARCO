export default function initContact() {
	const form = document.querySelector( '#contact-form' );
	if ( ! form ) {
		return;
	}

	const { to, subject, body } = form.dataset;
	const fallback = form.querySelector( '[data-fallback]' );
	const draft = form.querySelector( '[data-draft]' );
	const status = form.querySelector( '[data-status]' );
	const fields = {
		name: form.elements.namedItem( 'name' ),
		phone: form.elements.namedItem( 'phone' ),
		email: form.elements.namedItem( 'email' ),
		message: form.elements.namedItem( 'message' ),
		consent: form.elements.namedItem( 'consent' ),
	};

	const errorOf = ( field ) => form.querySelector( `#${ field.id }-error` );

	function setError( field, message ) {
		const holder = errorOf( field );
		if ( holder ) {
			holder.textContent = message;
		}
		field.setAttribute( 'aria-invalid', message ? 'true' : 'false' );
	}

	function validate() {
		let first = null;
		const fail = ( field, key ) => {
			setError( field, form.dataset[ key ] );
			first ??= field;
		};

		[ fields.name, fields.message ].forEach( ( field ) => {
			setError( field, '' );
			if ( field.value.trim() === '' ) {
				fail( field, 'msgRequired' );
			}
		} );

		setError( fields.email, '' );
		if ( fields.email.value.trim() === '' ) {
			fail( fields.email, 'msgRequired' );
		} else if ( ! fields.email.checkValidity() ) {
			fail( fields.email, 'msgEmail' );
		}

		setError( fields.consent, '' );
		if ( ! fields.consent.checked ) {
			fail( fields.consent, 'msgConsent' );
		}

		return first;
	}

	function compose() {
		const value = ( field ) => field.value.trim();
		const text = body
			.replace( '%name%', value( fields.name ) )
			.replace( '%phone%', value( fields.phone ) )
			.replace( '%email%', value( fields.email ) )
			.replace( '%message%', value( fields.message ) );
		return { subject: subject.replace( '%s', value( fields.name ) ), text };
	}

	Object.values( fields ).forEach( ( field ) => {
		field.addEventListener( 'input', () => setError( field, '' ) );
		field.addEventListener( 'change', () => setError( field, '' ) );
	} );

	form.addEventListener( 'submit', ( event ) => {
		event.preventDefault();
		status.textContent = '';

		const invalid = validate();
		if ( invalid ) {
			invalid.focus();
			return;
		}

		const { subject: title, text } = compose();
		draft.value = `${ title }\n\n${ text }`;
		fallback.hidden = false;

		window.location.href = `mailto:${ to }?subject=${ encodeURIComponent( title ) }&body=${ encodeURIComponent( text ) }`;
	} );

	form.querySelector( '[data-copy]' )?.addEventListener( 'click', async () => {
		try {
			await navigator.clipboard.writeText( draft.value );
			status.textContent = form.dataset.msgCopied;
		} catch {
			draft.focus();
			draft.select();
			status.textContent = form.dataset.msgCopyFailed;
		}
	} );
}
