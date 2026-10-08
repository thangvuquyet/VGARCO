// Duyệt trang bằng phím Tab THẬT và ghi lại từng điểm dừng: thứ tự, tên, có vòng tiêu điểm nhìn thấy không, có nằm ngoài khung nhìn/ẩn không.
// Phím Tab phải là phím thật (computer { action: 'key', text: 'Tab', repeat: N }), không phải dispatchEvent: `:focus-visible` chỉ đúng với tiêu điểm do bàn phím.
//
//   await fetch('http://127.0.0.1:9100/b/tab-walk.js').then(r => r.text()).then(t => (0, eval)(t));
//   vgTabStart();                    // bắt đầu ghi; sau đó bấm Tab N lần bằng phím thật
//   ...                              // (bấm Tab)
//   vgTabReport();                   // { dung: [...], loi: [...] }; `loi` rỗng là đạt
//
// Cửa sổ trình duyệt nhúng khi ẩn không chạy cuộn mượt, nên tiêu điểm "nằm ngoài khung nhìn" là giả: trước khi bấm Tab chạy
//   document.documentElement.style.setProperty( 'scroll-behavior', 'auto', 'important' );
// (tiêu điểm vẫn tự cuộn trang tới phần tử — kiểm bằng `scrollY` sau lượt duyệt).
//
// Báo lỗi khi: điểm dừng không có tên truy cập; không có vòng tiêu điểm (outline hoặc box-shadow) khi :focus-visible; phần tử ẩn/0 kích thước nhận tiêu điểm;
// tiêu điểm đứng lại ở một phần tử (bẫy bàn phím); hoặc điểm dừng nằm sau phần tử bị cuộn khỏi khung nhìn mà trang không cuộn theo.

window.vgTabStart = function () {
	window.__vgTab = [];
	document.addEventListener( 'focusin', window.__vgTabHandler = ( e ) => {
		const el = e.target;
		const cs = getComputedStyle( el );
		const r = el.getBoundingClientRect();
		const name = ( el.getAttribute( 'aria-label' ) || el.textContent || el.getAttribute( 'alt' ) || el.getAttribute( 'title' ) || el.value || '' ).replace( /\s+/g, ' ' ).trim().slice( 0, 40 );
		window.__vgTab.push( {
			tag: el.tagName.toLowerCase() + ( el.className && typeof el.className === 'string' ? '.' + el.className.split( ' ' )[ 0 ] : '' ),
			ten: name,
			focusVisible: el.matches( ':focus-visible' ),
			vong: cs.outlineStyle !== 'none' && parseFloat( cs.outlineWidth ) > 0 ? `${ cs.outlineWidth } ${ cs.outlineStyle } ${ cs.outlineColor } +${ cs.outlineOffset }` : ( cs.boxShadow !== 'none' ? 'bóng ' + cs.boxShadow.slice( 0, 40 ) : '' ),
			kichThuoc: `${ Math.round( r.width ) }x${ Math.round( r.height ) }`,
			trongKhung: r.bottom > 0 && r.top < innerHeight && r.right > 0 && r.left < innerWidth,
			hien: cs.visibility !== 'hidden' && cs.display !== 'none' && r.width > 0 && r.height > 0,
		} );
	} );
};

window.vgTabReport = function () {
	document.removeEventListener( 'focusin', window.__vgTabHandler );
	const dung = window.__vgTab;
	const loi = [];
	dung.forEach( ( s, i ) => {
		const at = `#${ i + 1 } ${ s.tag } «${ s.ten }»`;
		if ( ! s.ten ) loi.push( at + ': không có tên truy cập' );
		if ( s.focusVisible && ! s.vong ) loi.push( at + ': :focus-visible nhưng không có vòng tiêu điểm' );
		if ( ! s.hien ) loi.push( at + ': ẩn hoặc 0 kích thước mà vẫn nhận tiêu điểm' );
		else if ( ! s.trongKhung ) loi.push( at + ': nhận tiêu điểm nhưng nằm ngoài khung nhìn (trang không cuộn theo)' );
		if ( i > 0 && dung[ i - 1 ].tag === s.tag && dung[ i - 1 ].ten === s.ten ) loi.push( at + ': lặp lại điểm dừng liền trước (có thể là bẫy bàn phím)' );
	} );
	return { soDiemDung: dung.length, dung: dung.map( ( s, i ) => `${ i + 1 }. ${ s.tag } «${ s.ten }» ${ s.vong ? '' : '(KHÔNG VÒNG) ' }${ s.trongKhung ? '' : '(ngoài khung) ' }` ), loi };
};
