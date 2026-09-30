// Điểm vào JS. Ngân sách: ≤ 20 KB gzip toàn site, ≤ 8 KB ở trang chủ (phương án mục 7.6).
import '../scss/main.scss';
import { initNav } from './modules/nav.js';

// Module theo trang: chỉ tải khi trang cần. Khóa lấy từ data-vg-page của thẻ <body> (vgarco_page_key() trong PHP).
// Vite tự tách mỗi import() thành một tệp riêng có mã băm.
const pageModules = {
	home: () => import( './modules/home.js' ),
	projects: () => import( './modules/projects.js' ),
	services: () => import( './modules/services.js' ),
	contact: () => import( './modules/contact.js' ),
	article: () => import( './modules/article.js' ),
	careers: () => import( './modules/careers.js' ),
	apply: () => import( './modules/apply.js' ),
	project: () => import( './modules/project.js' ),
	// careers: () => import( './modules/careers.js' ),
};

// Tìm kiếm: mã của hộp thoại chỉ tải khi người dùng chạm vào nút (hoặc rê chuột/focus vào nút — tải trước cho kịp lúc bấm).
function initSearch() {
	const openers = [ ...document.querySelectorAll( '[data-vg-search-open]' ) ];
	if ( ! openers.length || ! document.querySelector( '[data-search-dialog]' ) ) {
		return;
	}

	let loaded = null;
	const load = () => ( loaded ??= import( './modules/search.js' ) );

	for ( const opener of openers ) {
		// Không có JS nó là liên kết tới danh mục dự án; có JS nó là nút mở hộp thoại.
		opener.setAttribute( 'role', 'button' );
		opener.setAttribute( 'aria-haspopup', 'dialog' );
		opener.addEventListener( 'pointerenter', load, { once: true } );
		opener.addEventListener( 'focus', load, { once: true } );
		opener.addEventListener( 'click', ( event ) => {
			// Mở thẻ mới bằng Ctrl/Cmd/Shift/chuột giữa thì để trình duyệt theo liên kết.
			if ( event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey ) {
				return;
			}
			event.preventDefault();
			load().then( ( module ) => module.openSearch( opener ) );
		} );
		// role=button trên <a>: phím Cách cũng phải kích hoạt như nút.
		opener.addEventListener( 'keydown', ( event ) => {
			if ( event.key === ' ' ) {
				event.preventDefault();
				opener.click();
			}
		} );
	}
}

// UC-10 2a: bấm ngôn ngữ chưa có bản dịch thì nhớ trong sessionStorage (không dùng tham số URL để không phân mảnh cache);
// trang đích hiện thông báo "nội dung đang được dịch". Cờ chỉ có hiệu lực 30 giây và dùng một lần.
function initLanguageNotice() {
	const KEY = 'vg-lang-missing';
	const notice = document.querySelector( '[data-lang-notice]' );

	document.addEventListener( 'click', ( event ) => {
		if ( event.target instanceof Element && event.target.closest( '[data-lang-missing]' ) ) {
			try {
				sessionStorage.setItem( KEY, String( Date.now() ) );
			} catch {
				/* trình duyệt chặn sessionStorage: bỏ qua, chỉ mất thông báo */
			}
		}
	} );

	if ( ! notice ) {
		return;
	}
	try {
		const flagged = Number( sessionStorage.getItem( KEY ) );
		sessionStorage.removeItem( KEY );
		if ( flagged && Date.now() - flagged < 30000 ) {
			notice.hidden = false;
		}
	} catch {
		/* như trên */
	}
	notice.querySelector( '[data-lang-notice-close]' )?.addEventListener( 'click', () => {
		notice.hidden = true;
	} );
}

function start() {
	initNav();
	initSearch();
	initLanguageNotice();

	const load = pageModules[ document.body.dataset.vgPage ];
	if ( load ) {
		load().then( ( module ) => module.default?.() );
	}
}

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', start, { once: true } );
} else {
	start();
}
