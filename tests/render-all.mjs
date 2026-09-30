// Kết xuất bộ trang chuẩn (dữ liệu mặc định của tests/php/fixtures.php) ra tests/out/ để kiểm tra trên trình duyệt:
// trợ năng bằng axe-core (xem tests/a11y.md), đo hình học, thử JS.
//   node tests/render-all.mjs            in danh sách đường dẫn (mở qua tests/serve.mjs)
import { spawnSync } from 'node:child_process';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = join( dirname( fileURLToPath( import.meta.url ) ), '..' );
const php = process.env.PHP ?? 'php';
const ext = process.env.PHP_EXT ?? '';
const args = ext ? [ '-d', `extension_dir=${ ext }` ] : [];

export const PAGES = [
	[ 'front-page', 'vi', {}, 'home.html' ],
	[ 'front-page', 'de', {}, 'home.html', 'home-de.html' ],
	[ 'archive-du_an', 'vi', {}, 'archive-du_an.html' ],
	[ 'taxonomy-loai_cong_trinh', 'vi', { VG_FX_TERM: 'giao-duc' }, 'taxonomy-loai_cong_trinh-giao-duc.html' ],
	[ 'single-du_an', 'vi', { VG_FX_ID: '104' }, 'single-du_an-104.html' ],
	[ 'single-du_an', 'vi', { VG_FX_ID: '106' }, 'single-du_an-106.html' ],
	[ 'templates/about', 'vi', { VG_FX_ID: '501' }, 'templates-about-501.html' ],
	[ 'templates/leadership', 'vi', { VG_FX_ID: '502' }, 'templates-leadership-502.html' ],
	[ 'templates/departments', 'vi', { VG_FX_ID: '503' }, 'templates-departments-503.html' ],
	[ 'templates/services', 'vi', { VG_FX_ID: '504' }, 'templates-services-504.html' ],
	[ 'templates/contact', 'vi', { VG_FX_ID: '505' }, 'templates-contact-505.html' ],
	[ 'templates/careers', 'vi', { VG_FX_ID: '506' }, 'templates-careers-506.html' ],
	[ 'single-vi_tri', 'vi', { VG_FX_ID: '401' }, 'single-vi_tri-401.html' ],
	[ 'templates/apply', 'vi', { VG_FX_ID: '507', VG_FX_GET: 'id=401' }, 'templates-apply-507.html' ],
	[ 'archive-tin_tuc', 'vi', {}, 'archive-tin_tuc.html' ],
	[ 'taxonomy-chuyen_muc', 'vi', { VG_FX_TERM: 'su-kien' }, 'taxonomy-chuyen_muc-su-kien.html' ],
	[ 'single-tin_tuc', 'vi', { VG_FX_ID: '301' }, 'single-tin_tuc-301.html' ],
	[ '404', 'vi', { VG_FX_URI: '/du-an/khong-co/' }, '404.html' ],
	[ 'page', 'vi', {}, 'page.html' ],
];

if ( process.argv[ 1 ] === fileURLToPath( import.meta.url ) ) {
	const out = [];
	for ( const [ template, lang, env, file, alias ] of PAGES ) {
		const r = spawnSync( php, [ ...args, '-d', 'extension=mbstring', join( root, 'tests', 'php', 'render.php' ), lang, template ], { env: { ...process.env, VG_FX_ABS: '1', ...env }, encoding: 'utf8' } );
		if ( r.status !== 0 || /Fatal|Warning/.test( r.stdout + r.stderr ) ) {
			console.error( `LỖI ${ template }: ${ ( r.stdout + r.stderr ).trim().split( '\n' ).pop() }` );
			process.exit( 1 );
		}
		out.push( alias ?? file );
		if ( alias ) {
			// render.php ghi ra tên theo mẫu; bản tiếng Đức ghi đè bản tiếng Việt nên đổi tên ngay.
			const fs = await import( 'node:fs' );
			fs.copyFileSync( join( root, 'tests', 'out', file ), join( root, 'tests', 'out', alias ) );
			// kết xuất lại bản tiếng Việt để tệp gốc đúng ngôn ngữ
			spawnSync( php, [ ...args, '-d', 'extension=mbstring', join( root, 'tests', 'php', 'render.php' ), 'vi', template ], { env: { ...process.env, VG_FX_ABS: '1', ...env }, encoding: 'utf8' } );
		}
	}
	console.log( out.map( ( f ) => '/' + f ).join( '\n' ) );
}
