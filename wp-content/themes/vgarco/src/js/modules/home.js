// Trang chủ: trình chiếu ảnh ở hero và nút dừng chung cho mọi chuyển động tự chạy.
//
// Yêu cầu: tối đa 5 dự án nổi bật, chuyển cảnh mềm, có nút tạm dừng, tự dừng khi thiết bị bật "giảm chuyển động"
// (yêu cầu 1, UC-01 3a/3b, WCAG 2.2.2). Ảnh đầu tiên do máy chủ đã hiển thị sẵn (LCP), JS chỉ thêm chuyển động.
//
// Ba lý do khiến trình chiếu đứng lại, độc lập nhau — chỉ chạy khi cả ba đều không xảy ra:
//   1. người dùng bấm nút dừng (hoặc hệ điều hành bật giảm chuyển động): class is-paused trên <body>, cũng dừng các dải chạy ngang;
//   2. rê CHUỘT vào khung ảnh, hoặc tiêu điểm BÀN PHÍM nằm trong khung (đang đọc chú thích, đang chọn chấm) — như bản đầu của theme;
//   3. thẻ trình duyệt bị ẩn.
// Rê chuột chỉ tính với con trỏ chuột thật: trên màn hình cảm ứng, chạm vào ảnh không được làm trình chiếu đứng mãi tới khi chạm chỗ khác.
// Nút dừng vẫn là cách duy nhất đáp ứng WCAG 2.2.2; hai lý do còn lại chỉ là tiện lợi.

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
		// Chú thích chỉ có ở ảnh thuộc dự án; ảnh mặc định không có chú thích.
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
			// Nhãn nút lấy từ thuộc tính data do PHP sinh (đã dịch), không viết cứng trong JS.
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

	// Không chạy ngầm khi thẻ trình duyệt bị ẩn.
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
		// Chỉ tiêu điểm do bàn phím (:focus-visible); bấm chuột vào chấm cũng làm nút nhận tiêu điểm nhưng khi đó con trỏ đã giữ trình chiếu đứng.
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
				// Đếm lại từ đầu: ảnh vừa chọn được xem đủ thời gian, không bị chuyển ngay.
				stop();
				start();
			} )
		);
		dotsBox.hidden = false;
	}

	setPaused( reduced.matches );
}
