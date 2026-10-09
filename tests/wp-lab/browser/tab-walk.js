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
