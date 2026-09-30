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
	project: () => import( './modules/project.js' ),
	// careers: () => import( './modules/careers.js' ),
};

function start() {
	initNav();

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
