import { createServer } from 'node:http';
import { readFile } from 'node:fs/promises';
import { extname, join, normalize, sep } from 'node:path';
import { fileURLToPath } from 'node:url';
import { execFile } from 'node:child_process';

const root = fileURLToPath( new URL( '.', import.meta.url ) );
const theme = join( root, '..', 'wp-content', 'themes', 'vgarco' );
const imgDir = process.env.VG_IMG_DIR ?? 'C:/Users/ThangVq/OneDrive - Mobifone/Documents/ChatGPT/Project1/vgarco-preview/assets';
const roots = {
	'/axe/': join( theme, 'node_modules', 'axe-core' ),
	'/js/': join( theme, 'src', 'js' ),
	'/dist/': join( theme, 'dist' ),
	'/img/': imgDir,
	'/': join( root, 'out' ),
};
const types = {
	'.html': 'text/html; charset=utf-8',
	'.js': 'text/javascript; charset=utf-8',
	'.css': 'text/css; charset=utf-8',
	'.woff2': 'font/woff2',
	'.json': 'application/json; charset=utf-8',
	'.jpg': 'image/jpeg',
	'.jpeg': 'image/jpeg',
	'.png': 'image/png',
	'.svg': 'image/svg+xml',
};

const php = process.env.PHP ?? 'C:/Users/ThangVq/tools/php/php.exe';
const phpExt = process.env.PHP_EXT ?? 'C:/Users/ThangVq/tools/php/ext';
const searchEndpoint = ( query, res ) =>
	execFile( php, [ '-d', 'extension_dir=' + phpExt, '-d', 'extension=mbstring', join( root, 'php', 'search-endpoint.php' ), query ], { encoding: 'utf8' }, ( error, stdout ) => {
		res.writeHead( error ? 500 : 200, { 'Content-Type': 'application/json; charset=utf-8', 'Cache-Control': 'public, max-age=300' } );
		res.end( error ? '{"code":"server"}' : stdout );
	} );

createServer( async ( req, res ) => {
	const url = new URL( req.url, 'http://x' );
	const path = decodeURIComponent( url.pathname );
	if ( path === '/wp-json/vgarco/v1/search' ) {
		const lang = url.searchParams.get( 'lang' );
		if ( ! url.searchParams.has( 'q' ) || ! [ 'vi', 'de' ].includes( lang ) ) {
			res.writeHead( 400, { 'Content-Type': 'application/json' } ).end( '{"code":"rest_invalid_param"}' );
			return;
		}
		searchEndpoint( url.searchParams.get( 'q' ), res );
		return;
	}
	const prefix = Object.keys( roots ).find( ( p ) => path.startsWith( p ) );
	const base = normalize( roots[ prefix ] );
	const file = normalize( join( base, path.slice( prefix.length ) || 'frame.html' ) );

	if ( ! file.startsWith( base + sep ) && file !== base ) {
		res.writeHead( 403 ).end();
		return;
	}
	let body;
	try {
		body = await readFile( file );
	} catch {
		res.writeHead( 404 ).end( 'Not found' );
		return;
	}
	res.writeHead( 200, { 'Content-Type': types[ extname( file ) ] ?? 'application/octet-stream', 'Cache-Control': 'no-store' } );
	res.end( body );
} ).listen( Number( process.argv[ 2 ] ?? 4174 ), '127.0.0.1', () => console.log( 'http://127.0.0.1:' + ( process.argv[ 2 ] ?? 4174 ) ) );
