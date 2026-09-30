// Danh mục dự án: khối "Câu chuyện một công trình" chuyển giữa phối cảnh thiết kế và công trình thực tế (UC-04 6a).
// Máy chủ đã hiển thị sẵn ảnh phối cảnh; JS chỉ thêm việc chuyển. Thiếu ảnh công trình thì không có nút (UC-04 6b).

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
				// Ảnh đang ẩn không được trình đọc màn hình đọc: hai ảnh cùng một công trình, chỉ một ảnh đang hiển thị.
				image.setAttribute( 'aria-hidden', String( i !== index ) );
			} );
			if ( caption ) {
				caption.textContent = button.dataset.caption ?? '';
			}
		} );
	} );
}
