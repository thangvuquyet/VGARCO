// Chi tiết tin tức: nút sao chép liên kết (UC-06 6a). Nút ẩn khi không có JS — chia sẻ Facebook là liên kết thật (6b).

export default function initArticle() {
	const button = document.querySelector( '[data-copy-link]' );
	if ( ! button ) {
		return;
	}

	const status = document.querySelector( '[data-copy-status]' );
	let timer = null;

	// Trình duyệt không có Clipboard API (hoặc trang không an toàn): không hiện nút thay vì hiện nút không chạy.
	if ( ! navigator.clipboard?.writeText ) {
		return;
	}
	button.hidden = false;

	button.addEventListener( 'click', async () => {
		try {
			await navigator.clipboard.writeText( button.dataset.copyLink || window.location.href );
		} catch {
			return;
		}
		button.classList.add( 'is-done' );
		button.setAttribute( 'aria-label', button.dataset.done );
		if ( status ) {
			status.textContent = button.dataset.done;
		}
		window.clearTimeout( timer );
		timer = window.setTimeout( () => {
			button.classList.remove( 'is-done' );
			button.setAttribute( 'aria-label', button.dataset.label );
			if ( status ) {
				status.textContent = '';
			}
		}, 2200 );
	} );
}
