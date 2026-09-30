// Trang chủ: trình chiếu ảnh ở hero và nút dừng chung cho mọi chuyển động tự chạy.
//
// Yêu cầu: tối đa 5 dự án nổi bật, chuyển cảnh mềm, có nút tạm dừng, tự dừng khi thiết bị bật "giảm chuyển động"
// (yêu cầu 1, UC-01 3a/3b, WCAG 2.2.2). Ảnh đầu tiên do máy chủ đã hiển thị sẵn (LCP), JS chỉ thêm chuyển động.

const INTERVAL = 5600;

export default function initHome() {
	const reduced = window.matchMedia( '(prefers-reduced-motion: reduce)' );
	const hero = document.querySelector( '[data-hero]' );
	const images = hero ? [ ...hero.querySelectorAll( '.showcase-image' ) ] : [];
	const captions = hero ? [ ...hero.querySelectorAll( '.showcase-caption' ) ] : [];
	const toggle = document.querySelector( '.motion-toggle' );

	let index = 0;
	let timer = null;

	function show( next ) {
		index = ( next + images.length ) % images.length;
		images.forEach( ( image, i ) => image.classList.toggle( 'is-active', i === index ) );
		// Chú thích chỉ có ở ảnh thuộc dự án; ảnh mặc định không có chú thích.
		captions.forEach( ( caption, i ) => caption.classList.toggle( 'is-active', i === index ) );
	}

	function stop() {
		window.clearInterval( timer );
		timer = null;
	}

	function start() {
		if ( images.length > 1 && ! timer ) {
			timer = window.setInterval( () => show( index + 1 ), INTERVAL );
		}
	}

	function setPaused( paused ) {
		document.body.classList.toggle( 'is-paused', paused );
		if ( toggle ) {
			toggle.setAttribute( 'aria-pressed', String( paused ) );
			// Nhãn nút lấy từ thuộc tính data do PHP sinh (đã dịch), không viết cứng trong JS.
			toggle.setAttribute( 'aria-label', paused ? toggle.dataset.labelPlay : toggle.dataset.labelPause );
		}
		if ( paused ) {
			stop();
		} else {
			start();
		}
	}

	toggle?.addEventListener( 'click', () => setPaused( ! document.body.classList.contains( 'is-paused' ) ) );
	reduced.addEventListener( 'change', ( event ) => setPaused( event.matches ) );

	// Không chạy ngầm khi thẻ trình duyệt bị ẩn.
	document.addEventListener( 'visibilitychange', () => {
		if ( document.hidden ) {
			stop();
		} else if ( ! document.body.classList.contains( 'is-paused' ) ) {
			start();
		}
	} );

	setPaused( reduced.matches );
}
