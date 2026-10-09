export default function initServices() {
	const root = document.querySelector( '[data-services]' );
	if ( ! root ) {
		return;
	}

	const items = [ ...root.querySelectorAll( '.service-item' ) ];
	const image = root.querySelector( '[data-service-image]' );
	const title = root.querySelector( '[data-service-title]' );
	const number = root.querySelector( '[data-service-number]' );

	function showReference( item ) {
		if ( ! image || ! item.dataset.image ) {
			return;
		}
		image.src = item.dataset.image;
		image.removeAttribute( 'srcset' );
		image.alt = item.dataset.alt ?? '';
		if ( title ) {
			title.textContent = item.dataset.ref ?? '';
		}
		if ( number ) {
			number.textContent = item.dataset.number ?? '';
		}
	}

	items.forEach( ( item ) => {
		item.addEventListener( 'toggle', () => {
			if ( item.open ) {
				showReference( item );
			}
		} );
	} );

	function openFromHash() {
		const target = window.location.hash ? document.getElementById( decodeURIComponent( window.location.hash.slice( 1 ) ) ) : null;
		if ( target && target.classList.contains( 'service-item' ) ) {
			target.open = true;
			showReference( target );
			target.scrollIntoView( { block: 'start' } );
		}
	}

	openFromHash();
	window.addEventListener( 'hashchange', openFromHash );
}
