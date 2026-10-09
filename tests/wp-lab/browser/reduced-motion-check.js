window.vgReduced = async function ( url, W = 1440, reduce = true ) {
	const base = location.origin;
	const html = await fetch( url ).then( ( r ) => r.text() );
	const doc = new DOMParser().parseFromString( html, 'text/html' );
	const head = doc.querySelector( 'head' );
	head.insertAdjacentHTML( 'afterbegin', `<base href="${ base }/">` );
	for ( const link of [ ...doc.querySelectorAll( 'link[rel="stylesheet"]' ) ] ) {
		const cssUrl = new URL( link.getAttribute( 'href' ), base + url ).href;
		let css = await fetch( cssUrl ).then( ( r ) => r.text() );
		css = css
			.replace( /url\((['"]?)(?!data:|https?:|\/)([^)'"]+)\1\)/g, ( m, q, p ) => `url(${ new URL( p, cssUrl ).href })` )
			.replace( /\(prefers-reduced-motion:\s*reduce\)/g, reduce ? 'all' : 'not all' )
			.replace( /\(prefers-reduced-motion:\s*no-preference\)/g, reduce ? 'not all' : 'all' );
		const style = doc.createElement( 'style' );
		style.textContent = css;
		link.replaceWith( style );
	}
	const f = document.createElement( 'iframe' );
	f.style.cssText = `position:absolute;left:-20000px;top:0;border:0;width:${ W }px;height:900px`;
	document.body.appendChild( f );
	const w = f.contentWindow;
	const real = w.matchMedia.bind( w );
	w.matchMedia = ( q ) => {
		const m = /prefers-reduced-motion:\s*(reduce|no-preference)/.exec( q );
		if ( ! m ) {
			return real( q );
		}
		return { matches: ( m[ 1 ] === 'reduce' ) === reduce, media: q, onchange: null, addEventListener() {}, removeEventListener() {}, addListener() {}, removeListener() {}, dispatchEvent: () => false };
	};
	const d = f.contentDocument;
	d.open();
	d.write( '<!doctype html>' + doc.documentElement.outerHTML );
	d.close();
	await new Promise( ( r ) => setTimeout( r, 1800 ) );

	const loi = [];
	const dur = ( s ) => Math.max( 0, ...String( s ).split( ',' ).map( ( x ) => parseFloat( x ) * ( /ms\s*$/.test( x ) ? 1 : 1000 ) ) );
	let chuyenCanh = 0;
	let hoatAnh = 0;
	for ( const e of d.querySelectorAll( '*' ) ) {
		const cs = w.getComputedStyle( e );
		if ( dur( cs.transitionDuration ) > 1 ) {
			chuyenCanh++;
			reduce && loi.push( 'chuyển cảnh còn chạy: ' + e.tagName.toLowerCase() + '.' + ( e.className || '' ).toString().split( ' ' )[ 0 ] + ' ' + cs.transitionDuration );
		}
		if ( cs.animationName !== 'none' && dur( cs.animationDuration ) > 1 ) {
			hoatAnh++;
			reduce && loi.push( 'hoạt ảnh còn chạy: ' + e.tagName.toLowerCase() + '.' + ( e.className || '' ).toString().split( ' ' )[ 0 ] + ' ' + cs.animationName );
		}
	}
	const pick = ( sel, pseudo ) => { const e = d.querySelector( sel ); return e ? w.getComputedStyle( e, pseudo ) : null; };
	const cta = pick( '.cta-circle', '::after' );
	const marquee = pick( '.marquee-track' );
	const hero = pick( '.hero-content h1' );
	const toggle = d.querySelector( '[data-motion-toggle], .motion-toggle, [data-gallery-toggle]' );
	const kq = {
		url,
		giamChuyenDong: reduce,
		cuonMuot: w.getComputedStyle( d.documentElement ).scrollBehavior,
		chuyenCanh,
		hoatAnh,
		vongCTA: cta ? cta.display : '(không có)',
		daiDoiTac: marquee ? marquee.animationName : '(không có)',
		chuH1: hero ? hero.animationName : '(không có)',
		nutDung: toggle ? { lop: toggle.className, pressed: toggle.getAttribute( 'aria-pressed' ), nhan: toggle.textContent.trim().slice( 0, 30 ) } : '(không có)',
		thanTrang: d.body.className,
	};
	if ( reduce ) {
		kq.cuonMuot !== 'auto' && loi.push( 'scroll-behavior không phải auto: ' + kq.cuonMuot );
		cta && kq.vongCTA !== 'none' && loi.push( 'vòng CTA vẫn hiện' );
		marquee && kq.daiDoiTac !== 'none' && loi.push( 'dải đối tác vẫn chạy' );
		hero && kq.chuH1 !== 'none' && loi.push( 'chữ H1 vẫn có hoạt ảnh vào' );
		toggle && toggle.hasAttribute( 'aria-pressed' ) && toggle.getAttribute( 'aria-pressed' ) !== 'true' && loi.push( 'nút dừng chưa ở trạng thái "đã dừng" khi bật giảm chuyển động' );
	}
	kq.loi = loi;
	f.remove();
	return kq;
};
