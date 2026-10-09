const SWIPE_DISTANCE = 50;
const INTERVAL = 6500;

export default function initProject() {
	const root = document.querySelector( '[data-gallery]' );
	if ( ! root ) {
		return;
	}

	const stage = root.querySelector( '[data-gallery-stage]' );
	const slides = [ ...root.querySelectorAll( '[data-gallery-slide]' ) ];
	const dots = [ ...root.querySelectorAll( '[data-gallery-dot]' ) ];
	const thumbs = [ ...root.querySelectorAll( '[data-gallery-thumb]' ) ];
	const counter = root.querySelector( '[data-gallery-current]' );
	const status = root.querySelector( '[data-gallery-status]' );
	const controls = root.querySelector( '[data-gallery-controls]' );
	const openButton = root.querySelector( '[data-gallery-open]' );
	const lightbox = root.querySelector( '[data-gallery-lightbox]' );
	const total = slides.length;
	const statusFormat = root.dataset.status ?? '%1$d / %2$d: %3$s';

	const toggle = root.querySelector( '[data-gallery-toggle]' );
	const reduced = window.matchMedia( '(prefers-reduced-motion: reduce)' );

	let index = slides.findIndex( ( slide ) => ! slide.hidden );
	index = index < 0 ? 0 : index;

	let timer = null;
	const holds = { user: reduced.matches, hover: false, focus: false, away: true, lightbox: false };

	const pad = ( n ) => String( n ).padStart( 2, '0' );

	function show( next, announce = true ) {
		index = ( ( next % total ) + total ) % total;

		slides.forEach( ( slide, i ) => {
			slide.hidden = i !== index;
			slide.classList.toggle( 'is-active', i === index );
		} );
		dots.forEach( ( dot, i ) => {
			dot.classList.toggle( 'is-active', i === index );
			dot.setAttribute( 'aria-pressed', String( i === index ) );
		} );
		thumbs.forEach( ( thumb, i ) => {
			thumb.classList.toggle( 'is-active', i === index );
			if ( i === index ) {
				thumb.setAttribute( 'aria-current', 'true' );
			} else {
				thumb.removeAttribute( 'aria-current' );
			}
		} );
		if ( counter ) {
			counter.textContent = pad( index + 1 );
		}
		if ( announce && status ) {
			status.textContent = statusFormat
				.replace( '%1$d', String( index + 1 ) )
				.replace( '%2$d', String( total ) )
				.replace( '%3$s', slides[ index ].dataset.label ?? '' );
		}

		[ index + 1, index - 1 ].forEach( ( i ) => {
			const img = slides[ ( ( i % total ) + total ) % total ].querySelector( 'img' );
			if ( img ) {
				img.loading = 'eager';
			}
		} );
	}

	const running = () => total > 1 && ! document.hidden && ! Object.values( holds ).some( Boolean );

	function stopAuto() {
		window.clearTimeout( timer );
		timer = null;
		root.classList.remove( 'is-counting' );
	}

	function startAuto() {
		stopAuto();
		if ( ! running() ) {
			return;
		}
		void root.offsetWidth;
		root.classList.add( 'is-counting' );
		timer = window.setTimeout( () => {
			show( index + 1, false );
			startAuto();
		}, INTERVAL );
	}

	function renderToggle() {
		if ( toggle ) {
			toggle.setAttribute( 'aria-pressed', String( holds.user ) );
			toggle.setAttribute( 'aria-label', holds.user ? toggle.dataset.labelPlay : toggle.dataset.labelPause );
		}
	}

	function setHold( name, value ) {
		if ( holds[ name ] !== value ) {
			holds[ name ] = value;
			startAuto();
		}
	}

	function go( next ) {
		show( next );
		startAuto();
	}

	if ( total > 1 ) {
		if ( controls ) {
			controls.hidden = false;
		}
		root.querySelector( '[data-gallery-prev]' )?.addEventListener( 'click', () => go( index - 1 ) );
		root.querySelector( '[data-gallery-next]' )?.addEventListener( 'click', () => go( index + 1 ) );
		dots.forEach( ( dot, i ) => dot.addEventListener( 'click', () => go( i ) ) );
		thumbs.forEach( ( thumb, i ) => {
			thumb.addEventListener( 'click', ( event ) => {
				event.preventDefault();
				go( i );
			} );
		} );

		stage.addEventListener( 'keydown', ( event ) => {
			const keys = { ArrowLeft: index - 1, ArrowRight: index + 1, Home: 0, End: total - 1 };
			if ( event.key in keys && ! event.altKey && ! event.ctrlKey && ! event.metaKey ) {
				event.preventDefault();
				go( keys[ event.key ] );
			}
		} );

		let startX = null;
		stage.addEventListener( 'pointerdown', ( event ) => {
			startX = event.pointerType === 'mouse' ? null : event.clientX;
		} );
		stage.addEventListener( 'pointerup', ( event ) => {
			if ( startX === null ) {
				return;
			}
			const distance = event.clientX - startX;
			startX = null;
			if ( Math.abs( distance ) >= SWIPE_DISTANCE ) {
				go( index + ( distance < 0 ? 1 : -1 ) );
			}
		} );
		stage.addEventListener( 'pointercancel', () => {
			startX = null;
		} );

		root.style.setProperty( '--gallery-interval', INTERVAL + 'ms' );
		toggle?.addEventListener( 'click', () => {
			holds.user = ! holds.user;
			renderToggle();
			startAuto();
		} );
		reduced.addEventListener( 'change', ( event ) => {
			holds.user = event.matches;
			renderToggle();
			startAuto();
		} );
		root.addEventListener( 'pointerenter', ( event ) => {
			if ( event.pointerType === 'mouse' ) {
				setHold( 'hover', true );
			}
		} );
		root.addEventListener( 'pointerleave', () => setHold( 'hover', false ) );
		root.addEventListener( 'focusin', ( event ) => {
			if ( event.target instanceof Element && event.target.matches( ':focus-visible' ) ) {
				setHold( 'focus', true );
			}
		} );
		root.addEventListener( 'focusout', ( event ) => {
			if ( ! ( event.relatedTarget instanceof Node ) || ! root.contains( event.relatedTarget ) ) {
				setHold( 'focus', false );
			}
		} );
		document.addEventListener( 'visibilitychange', startAuto );
		if ( 'IntersectionObserver' in window ) {
			new IntersectionObserver( ( entries ) => setHold( 'away', ! entries[ entries.length - 1 ].isIntersecting ), { threshold: 0.4 } ).observe( stage );
		} else {
			holds.away = false;
		}

		if ( toggle ) {
			toggle.hidden = false;
			renderToggle();
		}
		startAuto();
	}

	if ( ! lightbox || typeof lightbox.showModal !== 'function' ) {
		return;
	}

	const bigImage = lightbox.querySelector( '[data-lightbox-image]' );
	const bigCaption = lightbox.querySelector( '[data-lightbox-caption]' );

	function fillLightbox() {
		const slide = slides[ index ];
		bigImage.src = slide.dataset.full || slide.querySelector( 'img' ).currentSrc;
		bigImage.alt = slide.querySelector( 'img' ).alt;
		bigCaption.textContent = slide.dataset.label ?? '';
	}

	function openLightbox() {
		fillLightbox();
		lightbox.showModal();
		setHold( 'lightbox', true );
	}
	lightbox.addEventListener( 'close', () => setHold( 'lightbox', false ) );

	if ( openButton ) {
		openButton.hidden = false;
		openButton.addEventListener( 'click', openLightbox );
	}
	slides.forEach( ( slide ) => slide.querySelector( 'img' )?.addEventListener( 'click', openLightbox ) );

	lightbox.querySelector( '[data-lightbox-prev]' )?.addEventListener( 'click', () => {
		show( index - 1 );
		fillLightbox();
	} );
	lightbox.querySelector( '[data-lightbox-next]' )?.addEventListener( 'click', () => {
		show( index + 1 );
		fillLightbox();
	} );
	lightbox.addEventListener( 'keydown', ( event ) => {
		if ( total > 1 && ( event.key === 'ArrowLeft' || event.key === 'ArrowRight' ) ) {
			event.preventDefault();
			show( index + ( event.key === 'ArrowRight' ? 1 : -1 ) );
			fillLightbox();
		}
	} );
	lightbox.addEventListener( 'click', ( event ) => {
		if ( event.target === lightbox ) {
			lightbox.close();
		}
	} );
}
