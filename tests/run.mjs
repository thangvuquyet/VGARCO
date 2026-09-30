// Chạy toàn bộ kiểm tra tự động không cần WordPress:
//   node tests/run.mjs            cú pháp PHP, smoke, pages, cú pháp JS, build
//   node tests/run.mjs --mutate   thêm kiểm thử đột biến (cố ý phá mã, xác nhận kiểm thử chuyển đỏ)
//
// Cần PHP 8.3 CLI. Chỉ đường dẫn bằng biến môi trường nếu không nằm trong PATH:
//   PHP=C:\tools\php\php.exe  PHP_EXT=C:\tools\php\ext
import { spawnSync } from 'node:child_process';
import { readdirSync, statSync } from 'node:fs';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = join( dirname( fileURLToPath( import.meta.url ) ), '..' );
const php = process.env.PHP ?? 'php';
const ext = process.env.PHP_EXT ?? '';
const phpArgs = ext ? [ '-d', `extension_dir=${ ext }` ] : [];
const withMb = [ ...phpArgs, '-d', 'extension=mbstring' ];
const withApply = [ ...withMb, '-d', 'extension=gd', '-d', 'extension=fileinfo', '-d', 'extension=zip' ];

const walk = ( dir, test, out = [] ) => {
	for ( const name of readdirSync( dir ) ) {
		if ( name === 'node_modules' || name === 'dist' || name === 'out' ) {
			continue;
		}
		const path = join( dir, name );
		statSync( path ).isDirectory() ? walk( path, test, out ) : test( path ) && out.push( path );
	}
	return out;
};

const run = ( cmd, args, cwd = root ) => spawnSync( cmd, args, { cwd, encoding: 'utf8', shell: process.platform === 'win32' && cmd === 'npm' } );
let failed = 0;
const report = ( name, ok, detail = '' ) => {
	console.log( `${ ok ? 'ĐẠT ' : 'HỎNG' }  ${ name }${ detail ? ' — ' + detail : '' }` );
	failed += ok ? 0 : 1;
};

// 1. Cú pháp PHP
const phpFiles = [ ...walk( join( root, 'wp-content' ), ( p ) => p.endsWith( '.php' ) ), ...walk( join( root, 'tests' ), ( p ) => p.endsWith( '.php' ) ) ];
const bad = phpFiles.filter( ( f ) => run( php, [ ...phpArgs, '-l', f ] ).status !== 0 );
report( `php -l (${ phpFiles.length } tệp)`, bad.length === 0, bad.join( ', ' ) );

// 2. Kiểm thử PHP
for ( const script of [ 'smoke.php', 'pages.php', 'apply.php' ] ) {
	const r = run( php, [ ...( script === 'apply.php' ? withApply : withMb ), join( 'tests', 'php', script ) ] );
	const last = ( r.stdout + r.stderr ).trim().split( '\n' ).pop();
	report( script, r.status === 0, last );
}

// 3. Cú pháp JS
const jsFiles = walk( join( root, 'wp-content', 'themes', 'vgarco', 'src' ), ( p ) => p.endsWith( '.js' ) );
const badJs = jsFiles.filter( ( f ) => run( process.execPath, [ '--check', f ] ).status !== 0 );
report( `node --check (${ jsFiles.length } tệp)`, badJs.length === 0, badJs.join( ', ' ) );

// 4. Build
const theme = join( root, 'wp-content', 'themes', 'vgarco' );
const build = run( 'npm', [ 'run', 'build' ], theme );
report( 'vite build', build.status === 0, ( build.stdout.match( /built in [\d.]+m?s/ ) ?? [ build.stderr.trim().split( '\n' ).pop() ] )[ 0 ] );

// 5. Đột biến (tùy chọn)
if ( process.argv.includes( '--mutate' ) ) {
	const r = run( process.execPath, [ join( root, 'tests', 'mutate.mjs' ) ] );
	console.log( r.stdout );
	report( 'đột biến: mọi lỗi cố ý đều bị bắt', r.status === 0 );
}

console.log( failed ? `\n${ failed } nhóm kiểm tra HỎNG` : '\nTất cả đạt' );
process.exit( failed ? 1 : 0 );
