const fold = ( text ) =>
	String( text )
		.toLocaleLowerCase()
		.normalize( 'NFD' )
		.replace( /[̀-ͯ]/g, '' )
		.replace( /đ/g, 'd' )
		.replace( /ß/g, 'ss' );

export default function initCareers() {
	const root = document.querySelector( '[data-jobs]' );
	const form = root?.querySelector( '[data-job-form]' );
	if ( ! form ) {
		return;
	}

	const cards = [ ...root.querySelectorAll( '.job-card' ) ];
	const count = root.querySelector( '[data-job-count] strong' );
	const empty = root.querySelector( '[data-job-empty]' );
	const resets = [ ...root.querySelectorAll( '[data-job-reset]' ) ];
	const keyword = form.elements.namedItem( 'q' );
	const location = form.elements.namedItem( 'noi-lam-viec' );
	const department = form.elements.namedItem( 'phong-ban' );
	let timer = null;

	function render( updateUrl = true ) {
		const words = fold( keyword.value.trim() ).split( /\s+/ ).filter( Boolean );
		let shown = 0;

		cards.forEach( ( card ) => {
			const match =
				( location.value === '' || card.dataset.location === location.value ) &&
				( department.value === '' || card.dataset.department === department.value ) &&
				words.every( ( word ) => card.dataset.search.includes( word ) );
			card.hidden = ! match;
			if ( match ) {
				shown += 1;
				const number = card.querySelector( '.job-number' );
				if ( number ) {
					number.textContent = String( shown ).padStart( 2, '0' );
				}
			}
		} );

		if ( count ) {
			count.textContent = String( shown ).padStart( 2, '0' );
		}
		if ( empty ) {
			empty.hidden = shown > 0;
		}

		const active = keyword.value.trim() !== '' || location.value !== '' || department.value !== '';
		resets.forEach( ( reset ) => {
			if ( reset.classList.contains( 'job-reset' ) ) {
				reset.hidden = ! active;
			}
		} );

		if ( updateUrl ) {
			const url = new URL( window.location.href );
			[
				[ 'q', keyword.value.trim() ],
				[ 'noi-lam-viec', location.value ],
				[ 'phong-ban', department.value ],
			].forEach( ( [ key, value ] ) => {
				if ( value ) {
					url.searchParams.set( key, value );
				} else {
					url.searchParams.delete( key );
				}
			} );
			try {
				window.history.replaceState( null, '', url );
			} catch {
			}
		}
	}

	function readUrl() {
		const params = new URL( window.location.href ).searchParams;
		keyword.value = ( params.get( 'q' ) ?? '' ).slice( 0, 100 );
		const pick = ( select, value ) => {
			select.value = [ ...select.options ].some( ( option ) => option.value === value ) ? value : '';
		};
		pick( location, params.get( 'noi-lam-viec' ) ?? '' );
		pick( department, params.get( 'phong-ban' ) ?? '' );
		render( false );
	}

	form.addEventListener( 'submit', ( event ) => {
		event.preventDefault();
		window.clearTimeout( timer );
		render();
	} );
	keyword.addEventListener( 'input', () => {
		window.clearTimeout( timer );
		timer = window.setTimeout( render, 180 );
	} );
	[ location, department ].forEach( ( select ) => select.addEventListener( 'change', () => render() ) );

	resets.forEach( ( reset ) =>
		reset.addEventListener( 'click', ( event ) => {
			event.preventDefault();
			window.clearTimeout( timer );
			form.reset();
			keyword.value = '';
			location.value = '';
			department.value = '';
			render();
			keyword.focus();
		} )
	);

	window.addEventListener( 'popstate', readUrl );
	readUrl();
}
