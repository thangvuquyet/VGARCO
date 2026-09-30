// Trang Lĩnh vực hoạt động (UC-03): mở nhóm theo neo trên đường dẫn (#thiet-ke-kien-truc) và đổi khung ảnh theo nhóm đang mở.
//
// Máy chủ đã hiển thị sẵn nhóm đầu tiên ở trạng thái mở. Từ trang chủ bấm vào một lĩnh vực sẽ tới #<neo>: nhóm đó phải mở và
// cuộn tới (UC-03 1a). Trình duyệt tự cuộn tới phần tử nhưng phần tử <details> đóng thì chỉ thấy dòng tiêu đề.

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
		// Nhóm không có dự án tham khảo thì giữ nguyên ảnh đang hiển thị.
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
