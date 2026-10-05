// Kiểm tra trợ năng bằng axe-core trên TRANG THẬT của website thử (WordPress, http://localhost:8080) ở nhiều độ rộng và nhiều trạng thái động.
// (tests/a11y.md làm cùng việc trên bộ trang tĩnh kết xuất từ tests/php/fixtures.php; hai cách bổ sung nhau: trang tĩnh có đủ dữ liệu
// như nhóm lĩnh vực, còn trang thật có ảnh, nội dung và JS thật.)
//
// Chuẩn bị: máy chủ thử đang chạy (xem README trong thư mục này) và `node tests/serve.mjs 4174` để phục vụ /axe/axe.min.js.
// Dán tệp này vào Console của một trang ở http://localhost:8080 rồi gọi (mỗi lệnh nên dưới ~45 giây, mỗi trang × độ rộng ≈ 2 giây):
//
//   await vgAxe([ '/', '/gioi-thieu/', '/du-an/', '/tin-tuc/', '/tuyen-dung/', '/ung-tuyen/?id=439' ], [ 1440, 768, 390 ]);
//   await vgAxe([ '/?lab_lang=de', '/tuyen-dung/?lab_lang=de' ], [ 1440, 390 ]);          // giao diện tiếng Đức
//   await vgAxeState( 'hộp thoại tìm kiếm', '/', 1440, async ( d, w ) => { /* thao tác trên d (document của iframe), w (window) */ } );
//
// Kết quả: mảng { url, w, n (số vi phạm), v (mô tả) }; `n === 0` ở mọi dòng là đạt. Bộ quy tắc: WCAG 2.0/2.1/2.2 A và AA + best-practice (như tests/a11y.md);
// `.gallery-watermark` (chữ trang trí aria-hidden) được loại ra như ở tests/a11y.md.
//
// ĐỐI CHỨNG bắt buộc khi đổi cách chạy: chèn lỗi cố ý rồi chắc chắn bộ chạy báo được, ví dụ
//   await vgAxeState( 'đối chứng', '/', 1440, ( d ) => d.querySelector( 'main' ).insertAdjacentHTML( 'beforeend', '<img src="data:image/gif;base64,R0lGODlhAQABAAAAACw=" width="40" height="40">' ) );
// phải ra `image-alt(critical)`. Một lần chạy "0 vi phạm" mà chưa từng thấy nó báo lỗi thì chưa chứng minh được gì.

window.vgAxeRun = async ( f, axeSrc ) => {
	await new Promise( ( ok, fail ) => {
		const s = f.contentDocument.createElement( 'script' );
		s.src = axeSrc;
		s.onload = ok;
		s.onerror = () => fail( new Error( 'axe không nạp được từ ' + axeSrc ) );
		f.contentDocument.head.appendChild( s );
	} );
	return f.contentWindow.axe.run(
		{ exclude: [ [ '.gallery-watermark' ] ] },
		{ runOnly: { type: 'tag', values: [ 'wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa', 'best-practice' ] }, resultTypes: [ 'violations' ] }
	);
};

window.vgAxeFrame = async ( url, w ) => {
	const f = document.createElement( 'iframe' );
	f.style.cssText = `position:absolute;left:-20000px;top:0;width:${ w }px;height:900px;border:0`;
	f.src = url;
	document.body.appendChild( f );
	await new Promise( ( r ) => {
		f.onload = r;
		setTimeout( r, 9000 );
	} );
	await new Promise( ( r ) => setTimeout( r, 500 ) ); // phông chữ và JS nạp xong
	return f;
};

window.vgAxeDescribe = ( v ) => `${ v.id }(${ v.impact }) x${ v.nodes.length }: ${ v.nodes[ 0 ].target.join( ' ' ).slice( 0, 70 ) }`;

window.vgAxe = async ( urls, widths, axeSrc = 'http://127.0.0.1:4174/axe/axe.min.js' ) => {
	const out = [];
	for ( const url of urls ) {
		for ( const w of widths ) {
			const f = await vgAxeFrame( url, w );
			const r = await vgAxeRun( f, axeSrc );
			out.push( { url, w, n: r.violations.length, v: r.violations.map( vgAxeDescribe ) } );
			f.remove();
		}
	}
	return out;
};

window.vgAxeState = async ( label, url, w, setup, axeSrc = 'http://127.0.0.1:4174/axe/axe.min.js' ) => {
	const f = await vgAxeFrame( url, w );
	let note = '';
	try {
		await new Promise( ( ok, fail ) => {
			const s = f.contentDocument.createElement( 'script' );
			s.src = axeSrc;
			s.onload = ok;
			s.onerror = fail;
			f.contentDocument.head.appendChild( s );
		} );
		note = ( await setup( f.contentDocument, f.contentWindow ) ) || '';
	} catch ( e ) {
		note = 'LỖI THAO TÁC: ' + e.message;
	}
	await new Promise( ( r ) => setTimeout( r, 900 ) ); // hoạt ảnh, kết quả tìm kiếm
	const r = await f.contentWindow.axe.run(
		{ exclude: [ [ '.gallery-watermark' ] ] },
		{ runOnly: { type: 'tag', values: [ 'wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa', 'best-practice' ] }, resultTypes: [ 'violations' ] }
	);
	f.remove();
	return `${ label } @${ w }: ${ r.violations.length ? r.violations.map( vgAxeDescribe ).join( ' ; ' ) : '0 vi phạm' }${ note ? ' [' + note + ']' : '' }`;
};
