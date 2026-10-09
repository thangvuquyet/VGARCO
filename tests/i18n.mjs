import { readFileSync, existsSync } from 'node:fs';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';
import { escapePo, unescapePo, parsePo, writePo, compileMo, parseMo, placeholders, newEntry, moKey } from '../scripts/i18n/po.mjs';
import { phpLiteral, splitArgs, extractFromSource, buildPot, extractAll } from '../scripts/i18n/extract.mjs';
import { lookup, mergePo, validate } from '../scripts/i18n/sync.mjs';

const root = join( dirname( fileURLToPath( import.meta.url ) ), '..' );
let pass = 0;
const fail = [];
const check = ( label, ok ) => ( ok ? pass++ : fail.push( label ) );
const same = ( label, expected, actual ) => check( `${ label } — mong đợi ${ JSON.stringify( expected ) }, nhận ${ JSON.stringify( actual ) }`, JSON.stringify( expected ) === JSON.stringify( actual ) );

const tricky = 'Dòng 1\n\t"trích dẫn" và \\ gạch ngược\r\nCuối';
same( 'po: thoát rồi giải mã trả về đúng chuỗi gốc', tricky, unescapePo( escapePo( tricky ) ) );
same( 'po: thoát dấu nháy kép, xuống dòng', 'a\\"b\\nc', escapePo( 'a"b\nc' ) );

const sample = `# Bản dịch thử
msgid ""
msgstr ""
"Language: de\\n"
"Plural-Forms: nplurals=2; plural=(n != 1);\\n"

#. translators: %s là tên
#: a.php:10 b.php:20
#, php-format
msgid "Xin chào %s"
msgstr "Hallo %s"

#: c.php:5
msgctxt "menu"
msgid "Dự án"
msgstr "Projekte"

#: d.php:1
msgid "vị trí"
msgid_plural "vị trí"
msgstr[0] "Stelle"
msgstr[1] "Stellen"

msgid ""
"Dòng một\\n"
"dòng hai"
msgstr ""
"Zeile eins\\n"
"Zeile zwei"

#, fuzzy
msgid "Chưa chắc"
msgstr "Unsicher"

msgid "Chưa dịch"
msgstr ""

#~ msgid "Đã lỗi thời"
#~ msgstr "Veraltet"
`;
const parsed = parsePo( sample );
same( 'po: số mục (header + 6, bỏ mục lỗi thời)', 7, parsed.length );
same( 'po: header', [ '', true ], [ parsed[ 0 ].id, parsed[ 0 ].str[ 0 ].includes( 'Language: de' ) ] );
same( 'po: tham chiếu, cờ, chú thích trích', [ [ 'a.php:10', 'b.php:20' ], [ 'php-format' ], [ 'translators: %s là tên' ] ], [ parsed[ 1 ].refs, parsed[ 1 ].flags, parsed[ 1 ].extracted ] );
same( 'po: ngữ cảnh', [ 'menu', 'Dự án', 'Projekte' ], [ parsed[ 2 ].ctxt, parsed[ 2 ].id, parsed[ 2 ].str[ 0 ] ] );
same( 'po: số nhiều', [ 'vị trí', [ 'Stelle', 'Stellen' ] ], [ parsed[ 3 ].plural, parsed[ 3 ].str ] );
same( 'po: chuỗi nhiều dòng được nối lại', [ 'Dòng một\ndòng hai', 'Zeile eins\nZeile zwei' ], [ parsed[ 4 ].id, parsed[ 4 ].str[ 0 ] ] );
same( 'po: cờ fuzzy', [ 'fuzzy' ], parsed[ 5 ].flags );
same( 'po: không có mục lỗi thời', false, parsed.some( ( e ) => e.id === 'Đã lỗi thời' ) );
same( 'po: ghi rồi đọc lại cho đúng dữ liệu cũ', JSON.stringify( parsed ), JSON.stringify( parsePo( writePo( parsed ) ) ) );
check( 'po: dòng không hiểu thì báo lỗi, không bỏ qua im lặng', ( () => { try { parsePo( 'rác rưởi' ); return false; } catch { return true; } } )() );

const mo = compileMo( parsed );
const map = parseMo( mo );
same( 'mo: chuỗi thường', 'Hallo %s', map.get( 'Xin chào %s' ) );
same( 'mo: ngữ cảnh ghép bằng \\x04', 'Projekte', map.get( 'menu\x04Dự án' ) );
same( 'mo: số nhiều ghép bằng \\0', 'Stelle\0Stellen', map.get( 'vị trí\0vị trí' ) );
same( 'mo: chuỗi nhiều dòng', 'Zeile eins\nZeile zwei', map.get( 'Dòng một\ndòng hai' ) );
same( 'mo: bỏ mục chưa dịch và mục fuzzy', [ false, false ], [ map.has( 'Chưa dịch' ), map.has( 'Chưa chắc' ) ] );
check( 'mo: có phần đầu (khóa rỗng) mang Plural-Forms', ( map.get( '' ) ?? '' ).includes( 'Plural-Forms' ) );
const n = mo.readUInt32LE( 8 );
const origAt = mo.readUInt32LE( 12 );
const keys = [];
let nulOk = true;
for ( let i = 0; i < n; i++ ) {
	const len = mo.readUInt32LE( origAt + 8 * i );
	const off = mo.readUInt32LE( origAt + 8 * i + 4 );
	keys.push( mo.subarray( off, off + len ) );
	nulOk &&= mo[ off + len ] === 0;
}
check( 'mo: bảng chuỗi gốc xếp tăng dần theo byte', keys.every( ( k, i ) => i === 0 || Buffer.compare( keys[ i - 1 ], k ) < 0 ) );
check( 'mo: mọi chuỗi gốc kết thúc bằng NUL', nulOk );
same( 'mo: kích thước đầu tệp', [ 0x950412de, 0 ], [ mo.readUInt32LE( 0 ), mo.readUInt32LE( 4 ) ] );
const vn = [ 'é', 'Z', 'a', 'Đ', 'ä', 'ạ', '~' ].map( ( s ) => Object.assign( newEntry( s ), { str: [ s + '!' ] } ) );
const vnKeys = [ ...parseMo( compileMo( vn ) ).keys() ].filter( Boolean );
same( 'mo: Unicode xếp theo byte UTF-8', [ 'Z', '~', 'a', 'ä', 'é', 'Đ', 'ạ' ].map( ( s ) => Buffer.from( s ) ).sort( Buffer.compare ).map( ( b ) => b.toString() ), vnKeys );

same( 'placeholders: %s %d %1$s, bỏ %%', [ '%1$s', '%d', '%s' ], placeholders( 'a %s b %d c %1$s d 100%%' ) );
same( 'placeholders: không có', [], placeholders( 'Xin chào' ) );

same( 'phpLiteral: nháy đơn với \\\' và \\\\', "it's \\ ok", phpLiteral( "'it\\'s \\\\ ok'" ) );
same( 'phpLiteral: nháy kép với \\n và \\"', 'a\nb"c', phpLiteral( '"a\\nb\\"c"' ) );
same( 'phpLiteral: nháy kép có biến → không phải chuỗi thuần', null, phpLiteral( '"Xin chào $name"' ) );
same( 'phpLiteral: nháy kép có \\$ → chuỗi thuần', 'giá $5', phpLiteral( '"giá \\$5"' ) );
same( 'phpLiteral: nối chuỗi → không phải chuỗi thuần', null, phpLiteral( "'a' . 'b'" ) );
same( 'phpLiteral: biến → null', null, phpLiteral( '$text' ) );

same( 'splitArgs: dấu phẩy trong chuỗi và ngoặc lồng', [ "'a, b'", ' fn( 1, 2 )', " 'c' " ], splitArgs( "'a, b', fn( 1, 2 ), 'c' ) rest", 0 ) );
same( 'splitArgs: nháy có \\\' bên trong', [ "'it\\'s, ok'" ], splitArgs( "'it\\'s, ok')", 0 ) );

const php = `<?php
// Khai báo hàm không phải lời gọi:
function __( $t ) { return $t; }
echo __( 'Xin chào', 'vgarco' );
esc_html_e( "Dòng 1\\nDòng 2", 'vgarco' );
printf( /* translators: %s: tên */ __( 'Gọi %s', 'vgarco' ), $x );
echo _n( '%s ảnh', '%s ảnh', $n, 'vgarco' );
echo _x( 'Sao chép', 'nút', 'vgarco' );
echo __( 'Của plugin khác', 'other-plugin' );
echo __( $dynamic, 'vgarco' );
echo esc_attr__( 'Xin chào', 'vgarco' );
echo __( 'Lõi WordPress' );
$obj->__( 'không phải hàm gettext', 'vgarco' );
`;
const ex = extractFromSource( php, 'x.php', 'vgarco' );
same( 'extract: các chuỗi tìm thấy', [ 'Xin chào', 'Dòng 1\nDòng 2', 'Gọi %s', '%s ảnh', 'Sao chép', 'Xin chào' ], ex.found.map( ( f ) => f.id ) );
same( 'extract: số dòng (để báo lỗi) và tham chiếu chỉ gồm tên tệp', [ [ 4, 5, 6, 7, 8, 11 ], [ 'x.php' ] ], [ ex.found.map( ( f ) => f.line ), [ ...new Set( ex.found.map( ( f ) => f.ref ) ) ] ] );
same( 'extract: số nhiều và ngữ cảnh', [ '%s ảnh', null, 'nút' ], [ ex.found[ 3 ].plural, ex.found[ 3 ].ctxt, ex.found[ 4 ].ctxt ] );
same( 'extract: chú thích cho người dịch', 'translators: %s: tên', ex.found[ 2 ].comment );
same( 'extract: chuỗi động được báo riêng, không bị bỏ sót im lặng', 1, ex.dynamic.length );
check( 'extract: bỏ qua domain khác, khai báo hàm, ->__()', ! ex.found.some( ( f ) => [ 'Của plugin khác', 'Lõi WordPress', 'không phải hàm gettext' ].includes( f.id ) ) );
const pot = buildPot( ex.found, 'Thử', 'vgarco' );
same( 'pot: gộp trùng lặp (Xin chào dùng 2 lần trong 1 tệp → 1 mục, 1 tham chiếu)', [ 1, 1 ], [ pot.filter( ( e ) => e.id === 'Xin chào' ).length, pot.find( ( e ) => e.id === 'Xin chào' ).refs.length ] );
const twoFiles = buildPot( [ { id: 'Chung', plural: null, ctxt: null, ref: 'a.php', comment: '' }, { id: 'Chung', plural: null, ctxt: null, ref: 'b.php', comment: '' } ], 'Thử', 'vgarco' );
same( 'pot: chuỗi dùng ở hai tệp → một mục, hai tham chiếu', [ 'a.php', 'b.php' ], twoFiles.find( ( e ) => e.id === 'Chung' ).refs );
same( 'pot: cờ php-format cho chuỗi có %s', true, pot.find( ( e ) => e.id === 'Gọi %s' ).flags.includes( 'php-format' ) );
same( 'pot: mục đầu là header', '', pot[ 0 ].id );

const dict = {
	'Tuyển dụng': 'Karriere',
	'Từ những ý tưởng.': 'Von ersten Ideen.',
	'Đến giá trị dài lâu.': 'Zu bleibendem Wert.',
	'TƯ VẤN XÂY DỰNG': 'BAUBERATUNG',
	'Giới thiệu': 'Über uns',
	'Một dòng': 'Eine Zeile',
};
const index = new Map();
for ( const [ k, v ] of Object.entries( dict ) ) {
	index.set( k.replace( /\s+/g, ' ' ).trim(), v );
	index.set( 'U:' + k.replace( /\s+/g, ' ' ).trim().toLocaleUpperCase( 'vi' ), v );
}
same( 'lookup: khớp đúng', { text: 'Karriere', exact: true }, lookup( dict, index, 'Tuyển dụng' ) );
same( 'lookup: tiêu đề hai dòng có <em> được ghép từ hai dòng của từ điển', { text: 'Von ersten Ideen.\n<em>Zu bleibendem Wert.</em>', exact: true }, lookup( dict, index, 'Từ những ý tưởng.\n<em>Đến giá trị dài lâu.</em>' ) );
same( 'lookup: khác ngắt dòng → khớp gần đúng (fuzzy)', { text: 'Eine Zeile', exact: false }, lookup( dict, index, 'Một\ndòng' ) );
same( 'lookup: chữ hoa suy ra từ bản thường', { text: 'KARRIERE', exact: false }, lookup( dict, index, 'TUYỂN DỤNG' ) );
same( 'lookup: không có', null, lookup( dict, index, 'Chuỗi lạ' ) );
same( 'lookup: một dòng có trong từ điển, dòng kia không → không ghép', null, lookup( dict, index, 'Từ những ý tưởng.\n<em>Không có</em>' ) );

const potE = [ newEntry( '' ), Object.assign( newEntry( 'Tuyển dụng' ), { refs: [ 'new.php:1' ] } ), Object.assign( newEntry( 'Gọi %s' ), { refs: [ 'a.php:2' ] } ), Object.assign( newEntry( 'Mới' ), { refs: [ 'n.php:3' ] } ) ];
const edited = parsePo( 'msgid ""\nmsgstr ""\n\n#: old.php:9\nmsgid "Tuyển dụng"\nmsgstr "Bewerbung (sửa tay)"\n\nmsgid "Mục đã bị xóa khỏi mã"\nmsgstr "Gelöscht"\n' );
const merged = mergePo( potE, edited, ( e ) => ( e.id === 'Gọi %s' ? { text: 'Anrufen %d', exact: true } : e.id === 'Mới' ? { text: 'Neu', exact: false } : null ), 'Thử' );
same( 'merge: giữ bản dịch SỬA TAY, không bị từ điển ghi đè', 'Bewerbung (sửa tay)', merged.find( ( e ) => e.id === 'Tuyển dụng' ).str[ 0 ] );
same( 'merge: tham chiếu lấy từ mã mới', [ 'new.php:1' ], merged.find( ( e ) => e.id === 'Tuyển dụng' ).refs );
same( 'merge: bản dịch sai ký hiệu %s/%d bị bỏ thay vì để sinh lỗi khi in', [ '' ], merged.find( ( e ) => e.id === 'Gọi %s' ).str );
same( 'merge: gợi ý gần đúng mang cờ fuzzy', [ 'Neu', true ], [ merged.find( ( e ) => e.id === 'Mới' ).str[ 0 ], merged.find( ( e ) => e.id === 'Mới' ).flags.includes( 'fuzzy' ) ] );
same( 'merge: mục không còn trong mã bị bỏ', false, merged.some( ( e ) => e.id === 'Mục đã bị xóa khỏi mã' ) );
same( 'merge: header mới ghi ngôn ngữ và dạng số nhiều của tiếng Đức', true, merged[ 0 ].str[ 0 ].includes( 'Language: de' ) && merged[ 0 ].str[ 0 ].includes( 'nplurals=2' ) );

const errs = validate( [ newEntry( '' ), newEntry( 'A %s' ), newEntry( 'B' ) ], [ Object.assign( newEntry( '' ), { str: [ 'Language: de\n' ] } ), Object.assign( newEntry( 'A %s' ), { str: [ 'A' ] } ), Object.assign( newEntry( 'C' ), { str: [ 'C' ] } ) ], null );
same( 'validate: phát hiện mục thiếu, mục thừa và ký hiệu lệch', 3, errs.length );
check( 'validate: .mo lệch so với .po bị phát hiện', validate( [ newEntry( '' ) ], [ newEntry( '' ) ], Buffer.from( 'sai' ) ).some( ( e ) => e.includes( '.mo không khớp' ) ) );

const VIETNAMESE_ONLY = [
	'[VGARCO] Hồ sơ ứng tuyển: {vi_tri} — {ten}',
	'Có hồ sơ ứng tuyển mới.',
	'Hồ sơ #%1$d (%2$s) đã lưu nhưng thư chưa gửi được.',
	'Hồ sơ #%d: thư vẫn chưa gửi được sau 3 lần thử.',
];
for ( const t of extractAll() ) {
	const potPath = join( root, t.out );
	const base = t.domain === 'vgarco' ? 'de_DE' : `${ t.domain }-de_DE`;
	const dir = dirname( potPath );
	const poPath = join( dir, `${ base }.po` );
	const moPath = join( dir, `${ base }.mo` );
	check( `${ t.domain }: có đủ .pot, .po, .mo`, existsSync( potPath ) && existsSync( poPath ) && existsSync( moPath ) );
	if ( ! existsSync( poPath ) || ! existsSync( moPath ) ) {
		continue;
	}
	const po = parsePo( readFileSync( poPath, 'utf8' ) );
	const errors = validate( t.entries, po, readFileSync( moPath ) );
	if ( readFileSync( potPath, 'utf8' ) !== writePo( t.entries ) ) {
		errors.unshift( '.pot cũ so với mã (chạy: node scripts/i18n/extract.mjs)' );
	}
	same( `${ t.domain }: .pot mới, .po đủ mục, .mo khớp .po, ký hiệu %s nhất quán`, [], errors );
	same( `${ t.domain }: không có chuỗi động nào bị bỏ sót`, [], t.dynamic );

	const untranslated = po.filter( ( e ) => e.id !== '' && ! e.str.some( Boolean ) ).map( ( e ) => e.id );
	if ( t.domain === 'vgarco' ) {
		same( 'theme: mọi chuỗi giao diện đều có bản dịch tiếng Đức (chạy scripts/i18n/sync.mjs, thêm bản nháp vào de-draft.json)', [], untranslated );
		same( 'theme: không còn mục fuzzy (fuzzy không vào .mo nên trang vẫn hiện tiếng Việt)', [], po.filter( ( e ) => e.flags.includes( 'fuzzy' ) ).map( ( e ) => e.id ) );
	} else {
		same( 'plugin: chỉ các chuỗi dành cho nhân sự/quản trị được phép để tiếng Việt', [], untranslated.filter( ( id ) => ! VIETNAMESE_ONLY.some( ( p ) => id.startsWith( p ) ) ) );
	}
	const mo = parseMo( readFileSync( moPath ) );
	check( `${ t.domain }: .mo có header tiếng Đức`, ( mo.get( '' ) ?? '' ).includes( 'Language: de' ) && ( mo.get( '' ) ?? '' ).includes( 'nplurals=2' ) );
	check( `${ t.domain }: mọi mục đã dịch đều có trong .mo`, po.filter( ( e ) => e.id !== '' && e.str.some( Boolean ) && ! e.flags.includes( 'fuzzy' ) ).every( ( e ) => mo.has( moKey( e ) ) ) );
}

if ( fail.length ) {
	fail.forEach( ( f ) => console.log( `  ✗ ${ f }` ) );
	console.log( `THẤT BẠI ${ fail.length } / ${ pass + fail.length }` );
	process.exit( 1 );
}
console.log( `ĐẠT ${ pass }/${ pass }` );
