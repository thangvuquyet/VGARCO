window.vgSweep = async ( pages, widths, wait = 500 ) => {
	const summary = ( win ) => {
		const main = win.document.querySelector( 'main' ) || win.document.body;
		const name = ( e ) => e.tagName.toLowerCase() + ( e.className && typeof e.className === 'string' ? '.' + e.className.trim().split( /\s+/ )[ 0 ] : '' );
		const seen = new Map();
		main.querySelectorAll( '*' ).forEach( ( e ) => {
			const cs = win.getComputedStyle( e );
			if ( ! /grid|flex/.test( cs.display ) ) {
				return;
			}
			const kids = [ ...e.children ].filter( ( x ) => win.getComputedStyle( x ).display !== 'none' && x.getBoundingClientRect().width > 0 );
			if ( kids.length < 2 ) {
				return;
			}
			const d = cs.display.includes( 'grid' )
				? 'g' + cs.gridTemplateColumns.split( ' ' ).map( ( v ) => Math.round( parseFloat( v ) ) || v ).join( '/' )
				: 'f' + ( cs.flexDirection === 'column' ? 'C' : 'R' );
			const key = name( e ) + ' ' + d;
			seen.set( key, ( seen.get( key ) || 0 ) + 1 );
		} );
		return [ ...seen ].map( ( [ k, n ] ) => k + ( n > 1 ? 'x' + n : '' ) ).join( ' ; ' );
	};
	const out = {};
	for ( const [ label, url ] of pages ) {
		const lines = [];
		for ( const w of widths ) {
			const f = document.createElement( 'iframe' );
			f.style.cssText = `position:absolute;left:-20000px;top:0;width:${ w }px;height:900px;border:0`;
			f.src = url;
			document.body.appendChild( f );
			await new Promise( ( r ) => { f.onload = r; } );
			await new Promise( ( r ) => setTimeout( r, wait ) );
			let s = summary( f.contentWindow );
			if ( f.contentDocument.documentElement.scrollWidth > w ) {
				s += ' !!SCROLL' + f.contentDocument.documentElement.scrollWidth;
			}
			f.remove();
			lines.push( w + ': ' + s );
		}
		out[ label ] = lines;
	}
	return out;
};

window.vgCramped = async ( pages, widths, min = 24 ) => {
	const out = {};
	for ( const [ label, url ] of pages ) {
		const lines = [];
		for ( const w of widths ) {
			const f = document.createElement( 'iframe' );
			f.style.cssText = `position:absolute;left:-20000px;top:0;width:${ w }px;height:900px;border:0`;
			f.src = url;
			document.body.appendChild( f );
			await new Promise( ( r ) => { f.onload = r; } );
			await new Promise( ( r ) => setTimeout( r, 400 ) );
			const win = f.contentWindow;
			const bad = [];
			f.contentDocument.querySelectorAll( 'main h1, main h2, main h3, main p, main li, main dd, main dt, main label, main button, main a' ).forEach( ( e ) => {
				if ( [ ...e.children ].some( ( c ) => /^(H1|H2|H3|P|LI|DD|DT|UL|OL|DIV|ARTICLE|SECTION)$/.test( c.tagName ) ) ) {
					return;
				}
				const text = ( e.textContent || '' ).trim().replace( /\s+/g, ' ' );
				const cs = win.getComputedStyle( e );
				if ( text.length < 40 || cs.display === 'none' ) {
					return;
				}
				const lineHeight = parseFloat( cs.lineHeight ) || parseFloat( cs.fontSize ) * 1.3;
				const height = e.getBoundingClientRect().height - parseFloat( cs.paddingTop ) - parseFloat( cs.paddingBottom );
				const n = Math.max( 1, Math.round( height / lineHeight ) );
				if ( n >= 3 && text.length / n < min ) {
					bad.push( `${ e.tagName.toLowerCase() }${ e.className ? '.' + String( e.className ).split( ' ' )[ 0 ] : '' } "${ text.slice( 0, 28 ) }…" ${ text.length }ch/${ n }dòng=${ ( text.length / n ).toFixed( 0 ) } w${ Math.round( e.getBoundingClientRect().width ) }` );
				}
			} );
			f.remove();
			if ( bad.length ) {
				lines.push( w + ': ' + bad.slice( 0, 5 ).join( ' | ' ) );
			}
		}
		out[ label ] = lines;
	}
	return out;
};
