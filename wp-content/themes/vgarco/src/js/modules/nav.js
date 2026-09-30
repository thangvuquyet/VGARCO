// Menu chính: bảng menu con (disclosure) và menu di động.
// Markup do inc/menus.php sinh ra: mỗi mục có con là một nút .nav-trigger điều khiển một .nav-panel (aria-controls).

const DESKTOP = window.matchMedia( '(min-width: 1181px)' );

export function initNav() {
	const header = document.querySelector( '.header' );
	const nav = header?.querySelector( '#main-nav' );
	const toggle = header?.querySelector( '.menu-toggle' );
	if ( ! header || ! nav || ! toggle ) {
		return;
	}

	const triggers = [ ...nav.querySelectorAll( '.nav-trigger' ) ];
	const panelOf = ( trigger ) => document.getElementById( trigger.getAttribute( 'aria-controls' ) );
	const isExpanded = ( trigger ) => trigger.getAttribute( 'aria-expanded' ) === 'true';

	function closePanels( except = null ) {
		for ( const trigger of triggers ) {
			if ( trigger === except ) {
				continue;
			}
			trigger.setAttribute( 'aria-expanded', 'false' );
			const panel = panelOf( trigger );
			if ( panel ) {
				panel.hidden = true;
			}
		}
	}

	function openPanel( trigger ) {
		closePanels( trigger );
		trigger.setAttribute( 'aria-expanded', 'true' );
		const panel = panelOf( trigger );
		if ( panel ) {
			panel.hidden = false;
		}
	}

	function setMenu( open ) {
		nav.classList.toggle( 'open', open );
		toggle.setAttribute( 'aria-expanded', String( open ) );
		// Nhãn nút lấy từ thuộc tính data do PHP sinh (đã dịch), không viết cứng trong JS.
		toggle.setAttribute( 'aria-label', open ? toggle.dataset.labelClose : toggle.dataset.labelOpen );
		if ( ! open ) {
			closePanels();
		}
	}

	const menuIsOpen = () => toggle.getAttribute( 'aria-expanded' ) === 'true';

	toggle.addEventListener( 'click', () => setMenu( ! menuIsOpen() ) );

	for ( const trigger of triggers ) {
		trigger.addEventListener( 'click', () => ( isExpanded( trigger ) ? closePanels() : openPanel( trigger ) ) );

		// Mũi tên xuống: mở bảng và đưa tiêu điểm vào liên kết đầu tiên.
		trigger.addEventListener( 'keydown', ( event ) => {
			if ( event.key !== 'ArrowDown' ) {
				return;
			}
			event.preventDefault();
			openPanel( trigger );
			panelOf( trigger )?.querySelector( 'a' )?.focus();
		} );
	}

	document.addEventListener( 'click', ( event ) => {
		const target = event.target;
		if ( ! ( target instanceof Element ) ) {
			return;
		}
		if ( ! target.closest( '.nav-group' ) ) {
			closePanels();
		}
		if ( ! target.closest( '.header' ) && menuIsOpen() ) {
			setMenu( false );
		}
		if ( target.closest( '.nav-panel a' ) ) {
			closePanels();
		}
	} );

	document.addEventListener( 'keydown', ( event ) => {
		if ( event.key !== 'Escape' ) {
			return;
		}
		const open = triggers.find( isExpanded );
		if ( open ) {
			closePanels();
			if ( DESKTOP.matches ) {
				open.focus();
			}
			return;
		}
		if ( menuIsOpen() ) {
			setMenu( false );
			toggle.focus();
		}
	} );

	// Tiêu điểm rời khỏi thanh đầu trang (Tab đi tiếp): đóng bảng.
	header.addEventListener( 'focusout', ( event ) => {
		if ( event.relatedTarget && ! header.contains( event.relatedTarget ) ) {
			closePanels();
		}
	} );

	// Đổi độ rộng qua điểm gãy: về trạng thái đóng, tránh menu di động "kẹt" mở khi xoay màn hình.
	DESKTOP.addEventListener( 'change', () => {
		setMenu( false );
		closePanels();
	} );
}
