// Kiểm tra bố cục ở nhiều độ rộng, kể cả khi giao diện bằng tiếng Đức (yêu cầu 5.2 #2 và 5.3 #3 của đặc tả).
// Chạy trong trình duyệt (ví dụ cửa sổ trình duyệt nhúng hoặc F12 → Console) khi máy chủ thử đang chạy ở http://localhost:8080
// (node tests/wp-lab/run.mjs đã bật máy chủ này trong lúc chạy; hoặc xem tests/wp-lab/README.md để bật tay). Dán toàn bộ tệp này rồi gọi:
//
//   const urls = ['/', '/gioi-thieu/', '/du-an/', '/tuyen-dung/vt-kts/'];
//   await vgLayout(390, urls, 'de');    // tiếng Đức (?lab_lang=de do plugin lab-lang)
//   await vgLayout(390, urls, '');      // tiếng Việt, để so sánh
//   vgLayoutDiff(await vgLayout(390, urls, 'de'), await vgLayout(390, urls, ''));   // chỉ lỗi xuất hiện riêng ở tiếng Đức
//
// Mỗi trang được nạp trong một iframe rộng đúng W px (media query theo độ rộng của iframe). Báo hai loại lỗi:
//   - "TRÀN NGANG": cả trang cuộn ngang được;
//   - phần tử có chữ rộng hơn hộp chứa nó (chữ tràn ra ngoài hộp hoặc đè lên chữ bên cạnh).
// Bỏ qua chữ chỉ dành cho trình đọc màn hình (.sr-only), phần ẩn và dải đối tác chạy ngang (tràn theo thiết kế).

window.vgLayout = async function ( W, urls, lang ) {
	const out = {};
	for ( const u of urls ) {
		const f = document.createElement( 'iframe' );
		f.style.cssText = `position:absolute;left:-20000px;top:0;border:0;width:${ W }px;height:900px`;
		document.body.appendChild( f );
		const url = lang ? u + ( u.includes( '?' ) ? '&' : '?' ) + 'lab_lang=' + lang : u;
		await new Promise( ( r ) => {
			f.onload = r;
			f.src = url;
			setTimeout( r, 8000 );
		} );
		await new Promise( ( r ) => setTimeout( r, 350 ) ); // phông chữ nạp xong, bố cục ổn định
		const d = f.contentDocument;
		const w = f.contentWindow;
		const issues = [];
		if ( d.documentElement.scrollWidth > W ) {
			issues.push( 'TRÀN NGANG ' + d.documentElement.scrollWidth );
		}
		for ( const e of d.querySelectorAll( 'a, button, label, h1, h2, h3, h4, p, li, span, dt, dd, summary, strong, small' ) ) {
			if ( e.closest( '.sr-only, [hidden], [aria-hidden="true"], .partner-marquee' ) || ! e.textContent.trim() ) {
				continue;
			}
			const cs = w.getComputedStyle( e );
			if ( cs.display === 'none' || cs.visibility === 'hidden' || cs.display === 'inline' ) {
				continue;
			}
			if ( e.clientWidth > 4 && e.scrollWidth > e.clientWidth + 1 ) {
				issues.push( e.tagName.toLowerCase() + '.' + ( ( e.className || '' ).toString().split( ' ' )[ 0 ] || '-' ) + ' "' + e.textContent.trim().slice( 0, 28 ) + '" ' + e.scrollWidth + '>' + e.clientWidth );
			}
		}
		out[ u ] = issues;
		f.remove();
	}
	return out;
};

window.vgLayoutDiff = function ( de, vi ) {
	const diff = {};
	for ( const u of Object.keys( de ) ) {
		const only = de[ u ].filter( ( x ) => ! ( vi[ u ] || [] ).includes( x ) );
		if ( only.length ) {
			diff[ u ] = only;
		}
	}
	return diff;
};
