import { createServer } from 'node:http';
import { mkdirSync, writeFileSync, readFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const here = dirname( fileURLToPath( import.meta.url ) );
const out = join( here, 'out' );
mkdirSync( out, { recursive: true } );
createServer( ( req, res ) => {
	res.setHeader( 'Access-Control-Allow-Origin', '*' );
	res.setHeader( 'Access-Control-Allow-Headers', '*' );
	if ( req.method === 'OPTIONS' ) {
		res.end();
		return;
	}
	if ( req.method === 'GET' && req.url.startsWith( '/fn.js' ) ) {
		res.setHeader( 'Content-Type', 'text/javascript; charset=utf-8' );
		res.end( readFileSync( join( here, 'fn.js' ), 'utf8' ) );
		return;
	}
	const browserFile = req.method === 'GET' && /^\/b\/([a-z0-9-]+\.js)$/i.exec( req.url );
	if ( browserFile ) {
		res.setHeader( 'Content-Type', 'text/javascript; charset=utf-8' );
		res.end( readFileSync( join( here, '..', 'browser', browserFile[ 1 ] ), 'utf8' ) );
		return;
	}
	let body = '';
	req.on( 'data', ( c ) => ( body += c ) );
	req.on( 'end', () => {
		const name = req.url.replace( /[^a-z0-9_-]/gi, '' ) || 'x';
		writeFileSync( join( out, name + '.json' ), body );
		res.end( 'ok' );
	} );
} ).listen( 9100, '127.0.0.1', () => console.log( 'collector :9100' ) );
