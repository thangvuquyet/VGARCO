const DEBOUNCE_MS = 200;

let dialog;
let input;
let status;
let list;
let opener = null;
let controller = null;
let timer = 0;
let ready = false;
const cache = new Map();

function safeUrl( value ) {
	try {
		const url = new URL( value, location.href );
		return url.protocol === 'http:' || url.protocol === 'https:' ? url.href : '';
	} catch {
		return '';
	}
}

const normalize = ( text ) => text.trim().replace( /\s+/g, ' ' );
const length = ( text ) => [ ...text ].length;

function setStatus( message ) {
	status.textContent = message;
}

function clearResults() {
	list.replaceChildren();
	list.hidden = true;
}

function showHint() {
	controller?.abort();
	clearResults();
	setStatus( dialog.dataset.msgHint );
}

function render( results ) {
	list.replaceChildren();

	if ( ! results.length ) {
		list.hidden = true;
		setStatus( dialog.dataset.msgEmpty );
		return;
	}

	for ( const result of results ) {
		const href = safeUrl( result.url );
		if ( ! href ) {
			continue;
		}

		const item = document.createElement( 'li' );
		const link = document.createElement( 'a' );
		link.href = href;

		const thumb = result.thumb ? safeUrl( result.thumb ) : '';
		if ( thumb ) {
			const image = document.createElement( 'img' );
			image.src = thumb;
			image.alt = '';
			image.width = 90;
			image.height = 64;
			image.loading = 'lazy';
			link.append( image );
		}

		const text = document.createElement( 'span' );
		text.className = 'search-result-text';
		const title = document.createElement( 'span' );
		title.className = 'search-result-title';
		title.textContent = result.title;
		text.append( title );

		const meta = [ result.location, result.years ].filter( Boolean ).join( ' · ' );
		if ( meta ) {
			const small = document.createElement( 'small' );
			small.textContent = meta;
			text.append( small );
		}

		link.append( text );
		item.append( link );
		list.append( item );
	}

	list.hidden = list.children.length === 0;
	setStatus( results.length === 1 ? dialog.dataset.msgOne : dialog.dataset.msgMany.replace( '%d', String( results.length ) ) );
}

async function search() {
	const query = normalize( input.value );
	if ( length( query ) < Number( dialog.dataset.min || 3 ) ) {
		showHint();
		return;
	}

	const key = query.toLowerCase();
	if ( cache.has( key ) ) {
		render( cache.get( key ) );
		return;
	}

	controller?.abort();
	controller = new AbortController();
	const { signal } = controller;

	const endpoint = new URL( dialog.dataset.endpoint, location.href );
	const url = new URL( endpoint.pathname + endpoint.search, location.origin );
	url.searchParams.set( 'q', query );
	url.searchParams.set( 'lang', dialog.dataset.lang );

	list.setAttribute( 'aria-busy', 'true' );
	setStatus( dialog.dataset.msgLoading );

	try {
		const response = await fetch( url, { signal, headers: { Accept: 'application/json' } } );
		if ( ! response.ok ) {
			throw new Error( String( response.status ) );
		}
		const data = await response.json();
		const results = Array.isArray( data.results ) ? data.results : [];
		cache.set( key, results );
		if ( normalize( input.value ).toLowerCase() === key ) {
			render( results );
		}
	} catch ( error ) {
		if ( error.name === 'AbortError' ) {
			return;
		}
		clearResults();
		setStatus( dialog.dataset.msgError );
	} finally {
		if ( controller?.signal === signal ) {
			list.removeAttribute( 'aria-busy' );
		}
	}
}

function afterClose() {
	clearTimeout( timer );
	controller?.abort();
	opener?.focus();
}

function closeSearch() {
	dialog.close();
	afterClose();
}

function init() {
	if ( ready ) {
		return;
	}
	ready = true;

	dialog = document.querySelector( '[data-search-dialog]' );
	input = dialog.querySelector( '#search-input' );
	status = dialog.querySelector( '#search-status' );
	list = dialog.querySelector( '#search-results' );

	input.addEventListener( 'input', () => {
		clearTimeout( timer );
		timer = setTimeout( search, DEBOUNCE_MS );
	} );

	dialog.querySelector( '[data-search-close]' ).addEventListener( 'click', closeSearch );

	dialog.addEventListener( 'click', ( event ) => {
		if ( event.target === dialog ) {
			closeSearch();
		}
	} );

	dialog.addEventListener( 'close', afterClose );

	dialog.addEventListener( 'keydown', ( event ) => {
		if ( event.key === 'Escape' ) {
			event.preventDefault();
			closeSearch();
			return;
		}
		if ( event.key !== 'ArrowDown' && event.key !== 'ArrowUp' ) {
			return;
		}
		const links = [ ...list.querySelectorAll( 'a' ) ];
		if ( ! links.length ) {
			return;
		}
		const at = links.indexOf( document.activeElement );
		let next;
		if ( event.key === 'ArrowDown' ) {
			next = at === -1 ? links[ 0 ] : links[ at + 1 ];
		} else {
			next = at > 0 ? links[ at - 1 ] : input;
		}
		if ( next ) {
			event.preventDefault();
			next.focus();
		}
	} );
}

export function openSearch( trigger ) {
	init();
	opener = trigger ?? null;

	if ( typeof dialog.showModal !== 'function' ) {
		if ( trigger?.href ) {
			location.href = trigger.href;
		}
		return;
	}

	if ( ! dialog.open ) {
		dialog.showModal();
	}
	input.focus();
	input.select();
}

export default function () {}
