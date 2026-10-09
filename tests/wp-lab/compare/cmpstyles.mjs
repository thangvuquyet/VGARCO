import { readFileSync, existsSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const out = join( dirname( fileURLToPath( import.meta.url ) ), 'out' );
const names = process.argv.slice( 2 );
const IGNORE = new Set( [ 'minHeight' ] );
for ( const n of names ) {
	if ( ! existsSync( join( out, `lab-${ n }.json` ) ) || ! existsSync( join( out, `st-${ n }.json` ) ) ) {
		console.log( `== ${ n }: thiếu dữ liệu` );
		continue;
	}
	const lab = JSON.parse( readFileSync( join( out, `lab-${ n }.json` ), 'utf8' ) );
	const st = JSON.parse( readFileSync( join( out, `st-${ n }.json` ), 'utf8' ) );
	const common = Object.keys( st.items ).filter( ( k ) => k in lab.items );
	const onlySt = Object.keys( st.items ).filter( ( k ) => ! ( k in lab.items ) );
	const onlyLab = Object.keys( lab.items ).filter( ( k ) => ! ( k in st.items ) );
	console.log( `== ${ n }: chung ${ common.length }, chỉ ở trang tĩnh ${ onlySt.length }, chỉ ở website thử ${ onlyLab.length }` );
	let diffs = 0;
	for ( const k of common ) {
		const d = [];
		for ( const p of Object.keys( st.items[ k ] ) ) {
			if ( IGNORE.has( p ) || p.startsWith( '__' ) ) {
				continue;
			}
			if ( st.items[ k ][ p ] !== lab.items[ k ][ p ] ) {
				d.push( `${ p }: tĩnh «${ st.items[ k ][ p ] }» | thử «${ lab.items[ k ][ p ] }»` );
			}
		}
		const dw = Math.abs( st.items[ k ].__w - lab.items[ k ].__w ) > 2 ? `rộng ${ st.items[ k ].__w }→${ lab.items[ k ].__w }` : '';
		if ( d.length || dw ) {
			diffs++;
			console.log( `   ${ k }: ${ [ ...d.slice( 0, 4 ), dw ].filter( Boolean ).join( ' ; ' ) }${ d.length > 4 ? ` (+${ d.length - 4 })` : '' }` );
		}
	}
	console.log( `   → ${ diffs }/${ common.length } phần tử chung có khác biệt` );
}
