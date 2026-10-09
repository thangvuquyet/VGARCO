const MAX_SIZE_DEFAULT = 10 * 1024 * 1024;
const EXTENSIONS = [ 'pdf', 'doc', 'docx' ];

export default function initApply() {
	const form = document.querySelector( '#job-apply-form' );
	if ( ! form ) {
		return;
	}

	const msg = ( key ) => form.dataset[ key ] ?? '';
	const maxSize = Number( form.dataset.maxSize ) || MAX_SIZE_DEFAULT;
	const tokenInput = form.querySelector( '[data-token]' );
	const image = form.querySelector( '[data-captcha-image]' );
	const feedback = form.querySelector( '[data-feedback]' );
	const submit = form.querySelector( '.job-submit' );
	const fileInput = form.elements.namedItem( 'cv' );
	const fileName = form.querySelector( '[data-file-name]' );
	const control = ( name ) => form.elements.namedItem( name );
	const submitLabel = submit.textContent;

	function setError( field, message ) {
		const holder = form.querySelector( `#${ field.id }-error` );
		if ( holder ) {
			holder.textContent = message;
		}
		field.setAttribute( 'aria-invalid', message ? 'true' : 'false' );
	}

	function setCaptcha( data ) {
		if ( data?.token && data?.image ) {
			tokenInput.value = data.token;
			image.src = data.image;
		}
		control( 'captcha' ).value = '';
	}

	async function loadCaptcha() {
		try {
			const response = await fetch( form.dataset.tokenEndpoint, { credentials: 'same-origin', cache: 'no-store' } );
			if ( ! response.ok ) {
				throw new Error( String( response.status ) );
			}
			setCaptcha( await response.json() );
		} catch ( error ) {
			feedback.textContent = msg( '429' === error?.message ? 'msgTokenRate' : 'msgNetwork' );
		}
	}

	function validateFile() {
		const file = fileInput.files[ 0 ];
		if ( ! file ) {
			return msg( 'msgFileRequired' );
		}
		const ext = file.name.split( '.' ).pop().toLowerCase();
		if ( ! EXTENSIONS.includes( ext ) ) {
			return msg( 'msgFileType' );
		}
		if ( file.size > maxSize ) {
			return msg( 'msgFileSize' );
		}
		if ( file.size === 0 ) {
			return msg( 'msgFileInvalid' );
		}
		return '';
	}

	function validate() {
		let first = null;
		const fail = ( field, message ) => {
			setError( field, message );
			first ??= field;
		};

		const name = control( 'name' );
		setError( name, '' );
		if ( name.value.trim() === '' ) {
			fail( name, msg( 'msgRequired' ) );
		}

		const email = control( 'email' );
		setError( email, '' );
		if ( email.value.trim() === '' ) {
			fail( email, msg( 'msgRequired' ) );
		} else if ( ! email.checkValidity() ) {
			fail( email, msg( 'msgEmail' ) );
		}

		const phone = control( 'phone' );
		setError( phone, '' );
		const digits = phone.value.replace( /\D/g, '' );
		if ( phone.value.trim() === '' ) {
			fail( phone, msg( 'msgRequired' ) );
		} else if ( digits.length < 8 || digits.length > 15 || /[^\d\s+().-]/.test( phone.value ) ) {
			fail( phone, msg( 'msgPhone' ) );
		}

		setError( fileInput, '' );
		const fileMessage = validateFile();
		if ( fileMessage ) {
			fail( fileInput, fileMessage );
		}

		const captcha = control( 'captcha' );
		setError( captcha, '' );
		if ( captcha.value.trim() === '' ) {
			fail( captcha, msg( 'msgRequired' ) );
		}

		const consent = control( 'consent' );
		setError( consent, '' );
		if ( ! consent.checked ) {
			fail( consent, msg( 'msgConsent' ) );
		}

		return first;
	}

	const serverMessage = {
		required: 'msgRequired',
		email: 'msgEmail',
		phone: 'msgPhone',
		file_required: 'msgFileRequired',
		file_type: 'msgFileType',
		file_size: 'msgFileSize',
		file_invalid: 'msgFileInvalid',
		captcha: 'msgCaptcha',
		consent: 'msgConsent',
	};

	function showServerErrors( errors ) {
		let first = null;
		Object.entries( errors ?? {} ).forEach( ( [ fieldName, code ] ) => {
			const field = control( fieldName === 'cv' ? 'cv' : fieldName );
			if ( field ) {
				setError( field, msg( serverMessage[ code ] ?? 'msgRejected' ) );
				first ??= field;
			}
		} );
		first?.focus();
	}

	function busy( on ) {
		submit.disabled = on;
		submit.setAttribute( 'aria-busy', String( on ) );
		submit.textContent = on ? msg( 'sending' ) : submitLabel;
	}

	fileInput.addEventListener( 'change', () => {
		fileName.textContent = fileInput.files[ 0 ]?.name ?? msg( 'fileEmpty' );
		setError( fileInput, '' );
	} );

	form.querySelector( '[data-captcha-refresh]' )?.addEventListener( 'click', () => {
		loadCaptcha();
		control( 'captcha' ).focus();
	} );

	[ 'name', 'email', 'phone', 'captcha', 'consent' ].forEach( ( key ) => {
		const field = control( key );
		field.addEventListener( 'input', () => setError( field, '' ) );
		field.addEventListener( 'change', () => setError( field, '' ) );
	} );

	form.addEventListener( 'submit', async ( event ) => {
		event.preventDefault();
		feedback.textContent = '';

		const invalid = validate();
		if ( invalid ) {
			invalid.focus();
			return;
		}

		busy( true );
		try {
			const response = await fetch( form.dataset.endpoint, {
				method: 'POST',
				body: new FormData( form ),
				credentials: 'same-origin',
			} );
			let data = {};
			try {
				data = await response.json();
			} catch {
				data = {};
			}

			if ( response.ok && data.ok ) {
				form.hidden = true;
				const done = document.createElement( 'div' );
				done.className = 'job-apply-done';
				done.setAttribute( 'role', 'status' );
				done.tabIndex = -1;
				done.textContent = msg( 'msgSuccess' );
				form.after( done );
				done.focus();
				form.reset();
				return;
			}

			if ( data.captcha ) {
				setCaptcha( data.captcha );
			}
			if ( data.errors ) {
				showServerErrors( data.errors );
			} else if ( response.status === 429 ) {
				feedback.textContent = msg( 'msgRate' );
			} else if ( response.status === 409 || data.code === 'closed' ) {
				feedback.textContent = msg( 'msgClosed' );
			} else if ( response.status >= 500 ) {
				feedback.textContent = msg( 'msgServer' );
			} else {
				feedback.textContent = msg( 'msgRejected' );
			}
		} catch {
			feedback.textContent = msg( 'msgNetwork' );
		} finally {
			busy( false );
		}
	} );

	form.hidden = false;
	loadCaptcha();
}
