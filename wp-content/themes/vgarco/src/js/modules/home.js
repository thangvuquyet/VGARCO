const INTERVAL = 5600;

export default function initHome() {
	const reduced = window.matchMedia( '(prefers-reduced-motion: reduce)' );
	const hero = document.querySelector( '[data-hero]' );
	const images = hero ? [ ...hero.querySelectorAll( '.showcase-image' ) ] : [];
	const captions = hero ? [ ...hero.querySelectorAll( '.showcase-caption' ) ] : [];
	const dotsBox = hero?.querySelector( '[data-hero-dots]' );
	const dots = dotsBox ? [ ...dotsBox.querySelectorAll( '[data-hero-dot]' ) ] : [];
	const toggle = document.querySelector( '.motion-toggle' );

	let index = 0;
	let timer = null;
	let hovering = false;
	let focusing = false;

	const userPaused = () => document.body.classList.contains( 'is-paused' );

	function show( next ) {
		index = ( next + images.length ) % images.length;
		images.forEach( ( image, i ) => image.classList.toggle( 'is-active', i === index ) );
		captions.forEach( ( caption, i ) => caption.classList.toggle( 'is-active', i === index ) );
		dots.forEach( ( dot, i ) => {
			if ( i === index ) {
				dot.setAttribute( 'aria-current', 'true' );
			} else {
				dot.removeAttribute( 'aria-current' );
			}
		} );
	}

	function stop() {
		window.clearInterval( timer );
		timer = null;
	}

	function start() {
		if ( images.length > 1 && ! timer && ! userPaused() && ! hovering && ! focusing && ! document.hidden ) {
			timer = window.setInterval( () => show( index + 1 ), INTERVAL );
		}
	}

	function setPaused( paused ) {
		document.body.classList.toggle( 'is-paused', paused );
		if ( toggle ) {
			toggle.setAttribute( 'aria-pressed', String( paused ) );
			toggle.setAttribute( 'aria-label', paused ? toggle.dataset.labelPlay : toggle.dataset.labelPause );
		}
		if ( paused ) {
			stop();
		} else {
			start();
		}
	}

	toggle?.addEventListener( 'click', () => setPaused( ! userPaused() ) );
	reduced.addEventListener( 'change', ( event ) => setPaused( event.matches ) );

	document.addEventListener( 'visibilitychange', () => {
		if ( document.hidden ) {
			stop();
		} else {
			start();
		}
	} );

	if ( hero ) {
		hero.addEventListener( 'pointerenter', ( event ) => {
			if ( event.pointerType === 'mouse' ) {
				hovering = true;
				stop();
			}
		} );
		hero.addEventListener( 'pointerleave', () => {
			hovering = false;
			start();
		} );
		hero.addEventListener( 'focusin', ( event ) => {
			if ( event.target instanceof Element && event.target.matches( ':focus-visible' ) ) {
				focusing = true;
				stop();
			}
		} );
		hero.addEventListener( 'focusout', ( event ) => {
			if ( ! ( event.relatedTarget instanceof Node ) || ! hero.contains( event.relatedTarget ) ) {
				focusing = false;
				start();
			}
		} );
	}

	if ( dotsBox && images.length > 1 ) {
		dots.forEach( ( dot, i ) =>
			dot.addEventListener( 'click', () => {
				show( i );
				stop();
				start();
			} )
		);
		dotsBox.hidden = false;
	}

	setPaused( reduced.matches );
}
