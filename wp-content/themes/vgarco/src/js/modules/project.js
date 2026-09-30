// Chi tiết dự án: thư viện ảnh (chuyển ảnh, bàn phím, vuốt) và xem ảnh lớn.
//
// Máy chủ đã hiển thị sẵn ảnh đầu, các nút và ảnh nhỏ ở dạng liên kết tới tệp ảnh (không cần JS để xem ảnh).
// JS thêm: chuyển ảnh, bàn phím (← → Home End), vuốt trên màn hình cảm ứng, xem ảnh lớn trong <dialog>.
// Không tự chạy: thư viện tự chuyển ảnh vi phạm WCAG 2.2.2 nếu không có cách dừng, và người xem đang đọc sẽ bị mất ảnh.

const SWIPE_DISTANCE = 50;

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

	let index = slides.findIndex( ( slide ) => ! slide.hidden );
	index = index < 0 ? 0 : index;

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

		// Tải trước ảnh kề bên: ảnh ẩn (display:none) không được trình duyệt tải nếu còn lazy.
		[ index + 1, index - 1 ].forEach( ( i ) => {
			const img = slides[ ( ( i % total ) + total ) % total ].querySelector( 'img' );
			if ( img ) {
				img.loading = 'eager';
			}
		} );
	}

	// Chỉ một ảnh: không có gì để chuyển; vẫn cho phóng to.
	if ( total > 1 ) {
		if ( controls ) {
			controls.hidden = false;
		}
		root.querySelector( '[data-gallery-prev]' )?.addEventListener( 'click', () => show( index - 1 ) );
		root.querySelector( '[data-gallery-next]' )?.addEventListener( 'click', () => show( index + 1 ) );
		dots.forEach( ( dot, i ) => dot.addEventListener( 'click', () => show( i ) ) );
		thumbs.forEach( ( thumb, i ) => {
			thumb.addEventListener( 'click', ( event ) => {
				event.preventDefault(); // không mở tệp ảnh trong tab mới
				show( i );
			} );
		} );

		stage.addEventListener( 'keydown', ( event ) => {
			const keys = { ArrowLeft: index - 1, ArrowRight: index + 1, Home: 0, End: total - 1 };
			if ( event.key in keys && ! event.altKey && ! event.ctrlKey && ! event.metaKey ) {
				event.preventDefault();
				show( keys[ event.key ] );
			}
		} );

		// Vuốt ngang trên màn hình cảm ứng. Chuột không vuốt để khỏi cản thao tác bấm mở ảnh lớn.
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
				show( index + ( distance < 0 ? 1 : -1 ) );
			}
		} );
		stage.addEventListener( 'pointercancel', () => {
			startX = null;
		} );
	}

	/* ---- Xem ảnh lớn ---- */

	if ( ! lightbox || typeof lightbox.showModal !== 'function' ) {
		return; // trình duyệt quá cũ: bỏ qua phóng to, thư viện vẫn dùng được
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
	}

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
	// Bấm vào nền tối (chính <dialog>, không phải ảnh hay nút) thì đóng.
	lightbox.addEventListener( 'click', ( event ) => {
		if ( event.target === lightbox ) {
			lightbox.close();
		}
	} );
}
