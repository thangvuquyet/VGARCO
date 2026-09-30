// Đồng bộ bản dịch tiếng Đức: trích chuỗi (.pot) → gộp vào de_DE.po (giữ bản dịch đã có, điền chỗ trống từ bản thiết kế HTML) → biên dịch .mo.
//   node scripts/i18n/sync.mjs            cập nhật tệp
//   node scripts/i18n/sync.mjs --check    chỉ kiểm tra: .pot mới, .po đủ mục, .mo khớp .po, ký hiệu %s nhất quán (dùng trong kiểm thử)
//
// Bản dịch mồi lấy từ từ điển của bản thiết kế HTML (de.js, pages-de.js, updates-de.js): đó là BẢN NHÁP do nhà thầu soạn,
// phải để đối tác Đức hiệu đính trước khi công bố (Mốc 2). Mục khớp chính xác đánh dấu đã dịch; mục khớp gần đúng (khác khoảng trắng,
// ngắt dòng, hoa/thường) đánh dấu "fuzzy" — KHÔNG vào .mo cho tới khi người hiệu đính bỏ cờ.
import { existsSync, readFileSync, writeFileSync } from 'node:fs';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';
import vm from 'node:vm';
import { extractAll, TARGETS } from './extract.mjs';
import { parsePo, writePo, compileMo, parseMo, placeholders, newEntry, moKey } from './po.mjs';

const root = join( dirname( fileURLToPath( import.meta.url ) ), '..', '..' );
const PROTOTYPE = process.env.VG_PROTOTYPE ?? 'C:/Users/ThangVq/OneDrive - Mobifone/Documents/ChatGPT/Project1/vgarco-preview';

/** Bản dịch số nhiều và chuỗi đặc biệt không có trong bản thiết kế. */
const OVERRIDES = {
	'%s dự án': [ '%s Projekt', '%s Projekte' ],
};

/** Bản nháp tiếng Đức của nhà thầu cho chuỗi không có trong bản thiết kế HTML (scripts/i18n/de-draft.json). */
const DRAFT = JSON.parse( readFileSync( join( dirname( fileURLToPath( import.meta.url ) ), 'de-draft.json' ), 'utf8' ) );
const draftFor = ( id ) => ( DRAFT[ id ] === undefined ? null : Array.isArray( DRAFT[ id ] ) ? DRAFT[ id ] : { text: DRAFT[ id ], exact: true } );

const collapse = ( s ) => s.replace( /\s+/g, ' ' ).trim();

/** Nạp từ điển VI→DE của bản thiết kế (các tệp gán vào window.VGARCO_DE). */
export function loadPrototypeDictionary( dir = PROTOTYPE ) {
	const window = {};
	for ( const name of [ 'de.js', 'pages-de.js', 'updates-de.js' ] ) {
		const file = join( dir, name );
		if ( existsSync( file ) ) {
			vm.runInNewContext( readFileSync( file, 'utf8' ), { window, Object } );
		}
	}
	return window.VGARCO_DE ?? {};
}

/**
 * Tìm bản dịch của một chuỗi trong từ điển.
 *
 * @returns {{text: string, exact: boolean}|null}
 */
export function lookup( dict, index, id ) {
	if ( dict[ id ] !== undefined ) {
		return { text: dict[ id ], exact: true };
	}
	// Tiêu đề nhiều dòng có cụm nhấn: "Dòng 1\n<em>Dòng 2</em>". Bản thiết kế dịch từng dòng riêng (không có thẻ <em>),
	// nên dịch từng dòng rồi ghép lại đúng cấu trúc; chỗ ngắt dòng và cụm nhấn giữ như chuỗi gốc.
	if ( /\n|<em>/.test( id ) ) {
		const parts = id.split( '\n' ).map( ( line ) => {
			const em = line.match( /^<em>(.*)<\/em>$/s );
			const text = collapse( em ? em[ 1 ] : line );
			const found = dict[ text ];
			if ( found === undefined || /<\/?em>/.test( text ) ) {
				return null;
			}
			return em ? '<em>' + found + '</em>' : found;
		} );
		if ( parts.every( ( p ) => p !== null ) ) {
			return { text: parts.join( '\n' ), exact: true };
		}
	}
	const flat = collapse( id );
	if ( index.has( flat ) ) {
		return { text: index.get( flat ), exact: false }; // khác ngắt dòng/khoảng trắng: người hiệu đính đặt lại chỗ ngắt
	}
	const upper = flat.toLocaleUpperCase( 'vi' );
	if ( flat === upper && index.has( 'U:' + upper ) ) {
		return { text: index.get( 'U:' + upper ).toLocaleUpperCase( 'de' ), exact: false };
	}
	return null;
}

function buildIndex( dict ) {
	const index = new Map();
	for ( const [ k, v ] of Object.entries( dict ) ) {
		index.set( collapse( k ), v );
		index.set( 'U:' + collapse( k ).toLocaleUpperCase( 'vi' ), v );
	}
	return index;
}

const HEADER_DE = [
	'Project-Id-Version: {project}',
	'MIME-Version: 1.0',
	'Content-Type: text/plain; charset=UTF-8',
	'Content-Transfer-Encoding: 8bit',
	'Language: de',
	'Plural-Forms: nplurals=2; plural=(n != 1);',
	'Last-Translator: BẢN NHÁP từ bản thiết kế HTML — cần đối tác Đức hiệu đính',
	'',
].join( '\n' );

/**
 * Gộp mẫu (.pot) vào bản dịch hiện có.
 *
 * @param {import('./po.mjs').PoEntry[]} pot
 * @param {import('./po.mjs').PoEntry[]} existing
 * @param {(entry) => ({text: string, exact: boolean}|string[]|null)} suggest Gợi ý cho mục còn trống.
 */
export function mergePo( pot, existing, suggest, project ) {
	const old = new Map( existing.filter( ( e ) => e.id !== '' ).map( ( e ) => [ moKey( e ), e ] ) );
	const header = newEntry( '' );
	header.comments = [ `${ project } — tiếng Đức (de_DE).`, 'Bản NHÁP: dịch mồi từ bản thiết kế HTML, chờ đối tác Đức hiệu đính. Mục "fuzzy" chưa được dùng.' ];
	header.str = [ HEADER_DE.replace( '{project}', project ) ];

	const entries = [ header ];
	for ( const p of pot.filter( ( e ) => e.id !== '' ) ) {
		const e = { ...newEntry( p.id ), ctxt: p.ctxt, plural: p.plural, refs: p.refs, extracted: p.extracted, flags: p.flags.filter( ( f ) => f !== 'fuzzy' ) };
		e.str = p.plural === null ? [ '' ] : [ '', '' ];

		const prev = old.get( moKey( p ) );
		if ( prev && prev.str.some( Boolean ) ) {
			e.str = prev.str.slice();
			e.comments = prev.comments;
			if ( prev.flags.includes( 'fuzzy' ) ) {
				e.flags.push( 'fuzzy' );
			}
		} else {
			const s = suggest( p );
			if ( Array.isArray( s ) ) {
				e.str = s;
			} else if ( s ) {
				e.str = p.plural === null ? [ s.text ] : [ s.text, s.text ];
				if ( ! s.exact ) {
					e.flags.push( 'fuzzy' );
				}
			}
		}
		// Ký hiệu %s/%d phải khớp msgid: nếu không, bỏ gợi ý thay vì để sinh lỗi khi in.
		if ( e.str.some( Boolean ) && e.str.some( ( t ) => t && placeholders( t ).join() !== placeholders( p.id ).join() ) ) {
			e.str = p.plural === null ? [ '' ] : [ '', '' ];
			e.flags = e.flags.filter( ( f ) => f !== 'fuzzy' );
		}
		entries.push( e );
	}
	return entries;
}

/** Kiểm tra một bộ .po/.mo; trả danh sách lỗi. */
export function validate( pot, po, moBuffer ) {
	const errors = [];
	const potKeys = new Set( pot.filter( ( e ) => e.id !== '' ).map( moKey ) );
	const poKeys = new Set( po.filter( ( e ) => e.id !== '' ).map( moKey ) );
	for ( const k of potKeys ) {
		if ( ! poKeys.has( k ) ) {
			errors.push( 'Thiếu trong .po: ' + JSON.stringify( k ) );
		}
	}
	for ( const k of poKeys ) {
		if ( ! potKeys.has( k ) ) {
			errors.push( 'Mục thừa trong .po (không còn trong mã): ' + JSON.stringify( k ) );
		}
	}
	for ( const e of po.filter( ( x ) => x.id !== '' ) ) {
		for ( const t of e.str.filter( Boolean ) ) {
			if ( placeholders( t ).join() !== placeholders( e.id ).join() ) {
				errors.push( `Ký hiệu %s/%d lệch: ${ JSON.stringify( e.id ) } → ${ JSON.stringify( t ) }` );
			}
		}
	}
	if ( moBuffer ) {
		const compiled = compileMo( po );
		if ( ! compiled.equals( moBuffer ) ) {
			errors.push( '.mo không khớp .po (chạy: node scripts/i18n/sync.mjs)' );
		}
	}
	return errors;
}

function run() {
	const check = process.argv.includes( '--check' );
	const dict = loadPrototypeDictionary();
	const index = buildIndex( dict );
	if ( ! Object.keys( dict ).length ) {
		console.log( `(không thấy từ điển bản thiết kế ở ${ PROTOTYPE } — chỉ gộp, không điền gợi ý)` );
	}
	let failed = 0;

	for ( const t of extractAll() ) {
		const potPath = join( root, t.out );
		const poPath = potPath.replace( /\.pot$/, '' ).replace( /[^/\\]+$/, ( n ) => ( n === 'vgarco' ? 'de_DE' : n + '-de_DE' ) ) + '.po';
		const moPath = poPath.replace( /\.po$/, '.mo' );
		const existing = existsSync( poPath ) ? parsePo( readFileSync( poPath, 'utf8' ) ) : [];
		const suggest = ( e ) => OVERRIDES[ e.id ] ?? lookup( dict, index, e.id ) ?? draftFor( e.id );

		if ( check ) {
			const potOk = existsSync( potPath ) && readFileSync( potPath, 'utf8' ) === writePo( t.entries );
			const errors = existsSync( poPath ) ? validate( t.entries, existing, existsSync( moPath ) ? readFileSync( moPath ) : Buffer.alloc( 0 ) ) : [ 'Chưa có ' + poPath ];
			if ( ! potOk ) {
				errors.unshift( 'Tệp .pot cũ so với mã (chạy: node scripts/i18n/extract.mjs)' );
			}
			errors.forEach( ( e ) => console.log( `  ${ t.domain }: ${ e }` ) );
			failed += errors.length;
			continue;
		}

		const merged = mergePo( t.entries, existing, suggest, t.project );
		writeFileSync( potPath, writePo( t.entries ) );
		writeFileSync( poPath, writePo( merged ) );
		writeFileSync( moPath, compileMo( merged ) );

		const body = merged.filter( ( e ) => e.id !== '' );
		const done = body.filter( ( e ) => e.str.some( Boolean ) && ! e.flags.includes( 'fuzzy' ) ).length;
		const fuzzy = body.filter( ( e ) => e.flags.includes( 'fuzzy' ) ).length;
		console.log( `${ t.domain }: ${ body.length } chuỗi — đã dịch ${ done }, cần hiệu đính (fuzzy) ${ fuzzy }, chưa dịch ${ body.length - done - fuzzy }` );
	}
	process.exit( failed ? 1 : 0 );
}

if ( process.argv[ 1 ] === fileURLToPath( import.meta.url ) ) {
	run();
}

export { TARGETS };
