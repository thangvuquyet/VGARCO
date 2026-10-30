import { spawnSync } from 'node:child_process';
import { readdirSync, statSync } from 'node:fs';
import { join, dirname } from 'node:path';
import { mkdtempSync, writeFileSync, readFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { fileURLToPath } from 'node:url';

const root = join( dirname( fileURLToPath( import.meta.url ) ), '..' );
const php = process.env.PHP ?? 'php';
const ext = process.env.PHP_EXT ?? '';
if ( spawnSync( php, [ '-v' ] ).error ) {
	console.error( [
		`Không chạy được PHP ("${ php }"). Đặt biến PHP (và PHP_EXT = thư mục ext) trỏ tới php, ví dụ:`,
		'  cmd:        set PHP=C:\\đường\\dẫn\\php.exe  rồi  set PHP_EXT=C:\\đường\\dẫn\\ext',
		'  PowerShell: $env:PHP="C:\\đường\\dẫn\\php.exe"; $env:PHP_EXT="C:\\đường\\dẫn\\ext"',
		'  bash:       export PHP=/đường/dẫn/php PHP_EXT=/đường/dẫn/ext',
	].join( '\n' ) );
	process.exit( 1 );
}
const phpArgs = ext ? [ '-d', `extension_dir=${ ext }` ] : [];
const withMb = [ ...phpArgs, '-d', 'extension=mbstring' ];
const withApply = [ ...withMb, '-d', 'extension=gd', '-d', 'extension=fileinfo', '-d', 'extension=zip' ];

const walk = ( dir, test, out = [] ) => {
	for ( const name of readdirSync( dir ) ) {
		if ( name === 'node_modules' || name === 'dist' || name === 'out' || name === '.lab' ) {
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

const phpFiles = [ ...walk( join( root, 'wp-content' ), ( p ) => p.endsWith( '.php' ) ), ...walk( join( root, 'tests' ), ( p ) => p.endsWith( '.php' ) ) ];
const bad = phpFiles.filter( ( f ) => run( php, [ ...phpArgs, '-l', f ] ).status !== 0 );
report( `php -l (${ phpFiles.length } tệp)`, bad.length === 0, bad.join( ', ' ) );

for ( const script of [ 'smoke.php', 'coexist.php', 'languages.php', 'serverconf.php', 'pages.php', 'apply.php', 'search.php', 'seo.php', 'security.php', 'import.php', 'media.php', 'export-images.php' ] ) {
	const r = run( php, [ ...( [ 'apply.php', 'media.php', 'export-images.php' ].includes( script ) ? withApply : withMb ), join( 'tests', 'php', script ) ] );
	const last = ( r.stdout + r.stderr ).trim().split( '\n' ).pop();
	report( script, r.status === 0, last );
}

{
	const r = run( process.execPath, [ join( root, 'tests', 'i18n.mjs' ) ] );
	report( 'i18n.mjs', r.status === 0, ( r.stdout + r.stderr ).trim().split( '\n' ).pop() );

	const { parsePo } = await import( '../scripts/i18n/po.mjs' );
	const dirs = [
		[ 'vgarco', 'wp-content/themes/vgarco/languages/de_DE' ],
		[ 'vgarco-core', 'wp-content/plugins/vgarco-core/languages/vgarco-core-de_DE' ],
	];
	const tmp = mkdtempSync( join( tmpdir(), 'vg-gettext-' ) );
	for ( const [ domain, base ] of dirs ) {
		const entries = parsePo( readFileSync( join( root, base + '.po' ), 'utf8' ) )
			.filter( ( e ) => e.id !== '' && e.str.some( Boolean ) && ! e.flags.includes( 'fuzzy' ) )
			.map( ( e ) => ( { id: e.id, plural: e.plural, str: e.str } ) );
		const json = join( tmp, domain + '.json' );
		writeFileSync( json, JSON.stringify( entries ) );
		const g = run( php, [ ...withMb, '-d', 'extension=gettext', join( 'tests', 'php', 'gettext.php' ), domain, join( root, base + '.mo' ), json ] );
		report( `gettext.php (${ domain })`, g.status === 0, ( g.stdout + g.stderr ).trim().split( '\n' ).pop() );
	}
}

const jsFiles = [
	...walk( join( root, 'wp-content', 'themes', 'vgarco', 'src' ), ( p ) => p.endsWith( '.js' ) ),
	...walk( join( root, 'scripts' ), ( p ) => p.endsWith( '.mjs' ) ),
	...walk( join( root, 'tests' ), ( p ) => p.endsWith( '.mjs' ) ),
];
const badJs = jsFiles.filter( ( f ) => run( process.execPath, [ '--check', f ] ).status !== 0 );
report( `node --check (${ jsFiles.length } tệp)`, badJs.length === 0, badJs.join( ', ' ) );

const theme = join( root, 'wp-content', 'themes', 'vgarco' );
const build = run( 'npm', [ 'run', 'build' ], theme );
report( 'vite build', build.status === 0, ( build.stdout.match( /built in [\d.]+m?s/ ) ?? [ build.stderr.trim().split( '\n' ).pop() ] )[ 0 ] );

if ( process.argv.includes( '--mutate' ) ) {
	const r = run( process.execPath, [ join( root, 'tests', 'mutate.mjs' ) ] );
	console.log( r.stdout );
	report( 'đột biến: mọi lỗi cố ý đều bị bắt', r.status === 0 );
}

console.log( failed ? `\n${ failed } nhóm kiểm tra HỎNG` : '\nTất cả đạt' );
process.exit( failed ? 1 : 0 );
