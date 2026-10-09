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
		await new Promise( ( r ) => setTimeout( r, 350 ) );
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
