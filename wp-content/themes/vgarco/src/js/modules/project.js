// Chi tiết dự án: thư viện ảnh (chuyển ảnh, bàn phím, vuốt, tự chạy) và xem ảnh lớn.
//
// Máy chủ đã hiển thị sẵn ảnh đầu, các nút và ảnh nhỏ ở dạng liên kết tới tệp ảnh (không cần JS để xem ảnh).
// JS thêm: chuyển ảnh, bàn phím (← → Home End), vuốt trên màn hình cảm ứng, xem ảnh lớn trong <dialog>, và TỰ CHẠY.
//
// Tự chạy (6,5 s/ảnh, như bản thiết kế) chỉ khi KHÔNG có lý do nào dưới đây — mỗi lý do đứng riêng, hết hết mới chạy:
//   user     người dùng bấm nút tạm dừng (WCAG 2.2.2), hoặc hệ điều hành bật "giảm chuyển động" (khởi đầu ở trạng thái dừng; bấm nút để chạy);
//   hover    rê CHUỘT vào thư viện (chạm trên cảm ứng không tính, để khỏi đứng mãi);
//   focus    tiêu điểm BÀN PHÍM nằm trong thư viện (đang đọc chú thích, đang chọn ảnh);
//   away     thư viện chưa/không còn nằm trong khung nhìn (không chuyển ảnh khi chưa ai thấy; thanh tiến độ bắt đầu khi thấy);
//   lightbox đang xem ảnh lớn;
//   và thẻ trình duyệt đang ẩn.
// Người dùng tự chuyển ảnh thì thời gian đếm lại từ đầu. Ảnh tự chuyển KHÔNG đọc to cho trình đọc màn hình (mỗi 6,5 s sẽ là quấy rối); ảnh do người dùng chọn thì có.

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

		// Tải trước ảnh kề bên: ảnh ẩn (display:none) không được trình duyệt tải nếu còn lazy.
		[ index + 1, index - 1 ].forEach( ( i ) => {
			const img = slides[ ( ( i % total ) + total ) % total ].querySelector( 'img' );
			if ( img ) {
				img.loading = 'eager';
			}
		} );
	}

	/* ---- Tự chạy ---- */

	const running = () => total > 1 && ! document.hidden && ! Object.values( holds ).some( Boolean );

	function stopAuto() {
		window.clearTimeout( timer );
		timer = null;
		root.classList.remove( 'is-counting' );
	}

	// (Re)khởi động đếm giờ: thanh tiến độ của ảnh đang chọn chạy lại từ đầu (gỡ lớp, ép tính lại kiểu, gắn lại) cùng lúc với đồng hồ.
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

	// Chỉ đếm lại khi một lý do thật sự đổi, tránh đặt lại đồng hồ vô cớ.
	function setHold( name, value ) {
		if ( holds[ name ] !== value ) {
			holds[ name ] = value;
			startAuto();
		}
	}

	// Người dùng tự chuyển ảnh: hiện ảnh (có đọc to) rồi đếm lại; nếu đang bị giữ thì startAuto tự dừng.
	function go( next ) {
		show( next );
		startAuto();
	}

	// Chỉ một ảnh: không có gì để chuyển; vẫn cho phóng to.
	if ( total > 1 ) {
		if ( controls ) {
			controls.hidden = false;
		}
		root.querySelector( '[data-gallery-prev]' )?.addEventListener( 'click', () => go( index - 1 ) );
		root.querySelector( '[data-gallery-next]' )?.addEventListener( 'click', () => go( index + 1 ) );
		dots.forEach( ( dot, i ) => dot.addEventListener( 'click', () => go( i ) ) );
		thumbs.forEach( ( thumb, i ) => {
			thumb.addEventListener( 'click', ( event ) => {
				event.preventDefault(); // không mở tệp ảnh trong tab mới
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
				go( index + ( distance < 0 ? 1 : -1 ) );
			}
		} );
		stage.addEventListener( 'pointercancel', () => {
			startX = null;
		} );

		// Các lý do khiến tự chạy đứng lại (xem đầu tệp).
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
		// Chỉ tiêu điểm do bàn phím (:focus-visible): bấm chuột vào nút cũng làm nút nhận tiêu điểm nhưng khi đó con trỏ đã giữ thư viện đứng.
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
	// Bấm vào nền tối (chính <dialog>, không phải ảnh hay nút) thì đóng.
	lightbox.addEventListener( 'click', ( event ) => {
		if ( event.target === lightbox ) {
			lightbox.close();
		}
	} );
}
