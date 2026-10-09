export default function initProjects() {
	const story = document.querySelector( '[data-story]' );
	if ( ! story ) {
		return;
	}

	const buttons = [ ...story.querySelectorAll( '[data-story-show]' ) ];
	const images = [ ...story.querySelectorAll( '.story-image' ) ];
	const caption = story.querySelector( '[data-story-caption]' );

	if ( buttons.length < 2 || buttons.length !== images.length ) {
		return;
	}

	buttons.forEach( ( button, index ) => {
		button.addEventListener( 'click', () => {
			buttons.forEach( ( other ) => {
				const on = other === button;
				other.classList.toggle( 'is-active', on );
				other.setAttribute( 'aria-pressed', String( on ) );
			} );
			images.forEach( ( image, i ) => {
				image.classList.toggle( 'is-active', i === index );
				image.setAttribute( 'aria-hidden', String( i !== index ) );
			} );
			if ( caption ) {
				caption.textContent = button.dataset.caption ?? '';
			}
		} );
	} );
}
