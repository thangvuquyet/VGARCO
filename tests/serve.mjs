// Máy chủ tĩnh tối giản (không phụ thuộc gói ngoài) để thử HTML kết xuất và JS trên trình duyệt.
//   node tests/serve.mjs [cổng]
// Phục vụ: tests/out/ (khung HTML) và src/js/ của theme (dưới /js/).
import { createServer } from 'node:http';
import { readFile } from 'node:fs/promises';
import { extname, join, normalize, sep } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = fileURLToPath( new URL( '.', import.meta.url ) );
// Thứ tự quan trọng: tiền tố dài trước, "/" cuối cùng.
const theme = join( root, '..', 'wp-content', 'themes', 'vgarco' );
const roots = {
	'/js/': join( theme, 'src', 'js' ),
	'/dist/': join( theme, 'dist' ),
	'/': join( root, 'out' ),
};
const types = {
	'.html': 'text/html; charset=utf-8',
	'.js': 'text/javascript; charset=utf-8',
	'.css': 'text/css; charset=utf-8',
	'.woff2': 'font/woff2',
	'.json': 'application/json; charset=utf-8',
};

createServer( async ( req, res ) => {
	const path = decodeURIComponent( new URL( req.url, 'http://x' ).pathname );
	const prefix = Object.keys( roots ).find( ( p ) => path.startsWith( p ) );
	const base = roots[ prefix ];
	const file = normalize( join( base, path.slice( prefix.length ) || 'frame.html' ) );

	if ( ! file.startsWith( base + sep ) && file !== base ) {
		res.writeHead( 403 ).end();
		return;
	}
	let body;
	try {
		body = await readFile( file ); // đọc xong mới gửi header: tệp không có thì trả 404 được, không sập tiến trình
	} catch {
		res.writeHead( 404 ).end( 'Not found' );
		return;
	}
	res.writeHead( 200, { 'Content-Type': types[ extname( file ) ] ?? 'application/octet-stream', 'Cache-Control': 'no-store' } );
	res.end( body );
} ).listen( Number( process.argv[ 2 ] ?? 4174 ), '127.0.0.1', () => console.log( 'http://127.0.0.1:' + ( process.argv[ 2 ] ?? 4174 ) ) );
