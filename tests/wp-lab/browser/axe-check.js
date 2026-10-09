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
	await new Promise( ( r ) => setTimeout( r, 500 ) );
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
	await new Promise( ( r ) => setTimeout( r, 900 ) );
	const r = await f.contentWindow.axe.run(
		{ exclude: [ [ '.gallery-watermark' ] ] },
		{ runOnly: { type: 'tag', values: [ 'wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa', 'best-practice' ] }, resultTypes: [ 'violations' ] }
	);
	f.remove();
	return `${ label } @${ w }: ${ r.violations.length ? r.violations.map( vgAxeDescribe ).join( ' ; ' ) : '0 vi phạm' }${ note ? ' [' + note + ']' : '' }`;
};
