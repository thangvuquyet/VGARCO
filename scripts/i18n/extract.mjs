import { readdirSync, readFileSync, writeFileSync, existsSync, statSync } from 'node:fs';
import { join, relative, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';
import { newEntry, writePo } from './po.mjs';

const root = join( dirname( fileURLToPath( import.meta.url ) ), '..', '..' );

const FUNCTIONS = {
	__: { id: 0, domain: 1 },
	esc_html__: { id: 0, domain: 1 },
	esc_attr__: { id: 0, domain: 1 },
	_e: { id: 0, domain: 1 },
	esc_html_e: { id: 0, domain: 1 },
	esc_attr_e: { id: 0, domain: 1 },
	_x: { id: 0, ctxt: 1, domain: 2 },
	esc_html_x: { id: 0, ctxt: 1, domain: 2 },
	esc_attr_x: { id: 0, ctxt: 1, domain: 2 },
	_n: { id: 0, plural: 1, domain: 3 },
	_nx: { id: 0, plural: 1, ctxt: 3, domain: 4 },
};

export const TARGETS = [
	{ domain: 'vgarco', dir: 'wp-content/themes/vgarco', out: 'wp-content/themes/vgarco/languages/vgarco.pot', project: 'VGARCO Theme' },
	{ domain: 'vgarco-core', dir: 'wp-content/plugins/vgarco-core', out: 'wp-content/plugins/vgarco-core/languages/vgarco-core.pot', project: 'VGARCO Core' },
];

function walk( dir, out = [] ) {
	for ( const name of readdirSync( dir ) ) {
		if ( name === 'node_modules' || name === 'dist' || name === 'languages' ) {
			continue;
		}
		const path = join( dir, name );
		statSync( path ).isDirectory() ? walk( path, out ) : name.endsWith( '.php' ) && out.push( path );
	}
	return out;
}

export function phpLiteral( src ) {
	const s = src.trim();
	let m;
	if ( ( m = s.match( /^'((?:[^'\\]|\\.)*)'$/s ) ) ) {
		return m[ 1 ].replace( /\\(['\\])/g, '$1' );
	}
	if ( ( m = s.match( /^"((?:[^"\\$]|\\.)*)"$/s ) ) ) {
		return m[ 1 ].replace( /\\(n|t|r|"|\\|\$)/g, ( _, c ) => ( { n: '\n', t: '\t', r: '\r', '"': '"', '\\': '\\', $: '$' } )[ c ] );
	}
	return null;
}

export function splitArgs( src, start ) {
	const args = [];
	let depth = 0;
	let quote = null;
	let cur = '';
	for ( let i = start; i < src.length; i++ ) {
		const c = src[ i ];
		if ( quote ) {
			cur += c;
			if ( c === '\\' ) {
				cur += src[ ++i ] ?? '';
			} else if ( c === quote ) {
				quote = null;
			}
			continue;
		}
		if ( c === "'" || c === '"' ) {
			quote = c;
			cur += c;
		} else if ( c === '(' || c === '[' ) {
			depth++;
			cur += c;
		} else if ( c === ')' || c === ']' ) {
			if ( depth === 0 ) {
				args.push( cur );
				return args;
			}
			depth--;
			cur += c;
		} else if ( c === ',' && depth === 0 ) {
			args.push( cur );
			cur = '';
		} else {
			cur += c;
		}
	}
	return args;
}

export function extractFromSource( src, file, domain ) {
	const found = [];
	const dynamic = [];
	const re = new RegExp( '\\b(' + Object.keys( FUNCTIONS ).join( '|' ) + ')\\s*\\(', 'g' );
	let m;
	while ( ( m = re.exec( src ) ) ) {
		const before = src.slice( Math.max( 0, m.index - 12 ), m.index );
		if ( /(?:function\s+|->|::)$/.test( before ) ) {
			continue;
		}
		const spec = FUNCTIONS[ m[ 1 ] ];
		const args = splitArgs( src, m.index + m[ 0 ].length );
		const line = src.slice( 0, m.index ).split( '\n' ).length;

		if ( args[ spec.domain ] === undefined || phpLiteral( args[ spec.domain ] ) !== domain ) {
			continue;
		}
		const id = phpLiteral( args[ spec.id ] ?? '' );
		if ( id === null ) {
			dynamic.push( `${ file }:${ line } ${ m[ 1 ] }( ${ ( args[ spec.id ] ?? '' ).trim().slice( 0, 50 ) } )` );
			continue;
		}
		const plural = spec.plural === undefined ? null : phpLiteral( args[ spec.plural ] ?? '' );
		const ctxt = spec.ctxt === undefined ? null : phpLiteral( args[ spec.ctxt ] ?? '' );

		const prefix = src.slice( Math.max( 0, m.index - 400 ), m.index );
		const comment = prefix.match( /\/\*\s*translators:\s*([\s\S]*?)\*\/\s*$/ ) ?? prefix.match( /\/\/\s*translators:\s*(.*)\s*$/ );

		found.push( { id, plural, ctxt, ref: file, line, comment: comment ? 'translators: ' + comment[ 1 ].replace( /\s+/g, ' ' ).trim() : '' } );
	}
	return { found, dynamic };
}

export function buildPot( found, project, domain ) {
	const map = new Map();
	for ( const f of found ) {
		const key = ( f.ctxt ?? '' ) + '\x04' + f.id;
		let e = map.get( key );
		if ( ! e ) {
			e = newEntry( f.id );
			e.ctxt = f.ctxt;
			e.plural = f.plural;
			e.str = f.plural === null ? [ '' ] : [ '', '' ];
			map.set( key, e );
		}
		if ( ! e.refs.includes( f.ref ) ) {
			e.refs.push( f.ref );
		}
		if ( f.comment && ! e.extracted.includes( f.comment ) ) {
			e.extracted.push( f.comment );
		}
		if ( /%(?:\d+\$)?[sdf]/.test( f.id ) && ! e.flags.includes( 'php-format' ) ) {
			e.flags.push( 'php-format' );
		}
	}
	const header = newEntry( '' );
	header.comments = [ `${ project } — mẫu dịch (POT).`, 'Sinh tự động bởi scripts/i18n/extract.mjs; không sửa tay.' ];
	header.str = [ [ 'Project-Id-Version: ' + project, 'MIME-Version: 1.0', 'Content-Type: text/plain; charset=UTF-8', 'Content-Transfer-Encoding: 8bit', 'Language: vi', 'Plural-Forms: nplurals=1; plural=0;', 'X-Domain: ' + domain, '' ].join( '\n' ) ];
	const entries = [ ...map.values() ].sort( ( a, b ) => ( a.ctxt ?? '' ).localeCompare( b.ctxt ?? '' ) || a.id.localeCompare( b.id, 'vi' ) );
	return [ header, ...entries ];
}

export function extractAll( rootDir = root ) {
	const result = [];
	for ( const t of TARGETS ) {
		const files = walk( join( rootDir, t.dir ) );
		const found = [];
		const dynamic = [];
		for ( const file of files ) {
			const r = extractFromSource( readFileSync( file, 'utf8' ), relative( rootDir, file ).replace( /\\/g, '/' ), t.domain );
			found.push( ...r.found );
			dynamic.push( ...r.dynamic );
		}
		result.push( { ...t, entries: buildPot( found, t.project, t.domain ), dynamic, count: found.length } );
	}
	return result;
}

if ( process.argv[ 1 ] === fileURLToPath( import.meta.url ) ) {
	const check = process.argv.includes( '--check' );
	let stale = 0;
	for ( const t of extractAll() ) {
		const text = writePo( t.entries );
		const path = join( root, t.out );
		if ( check ) {
			const current = existsSync( path ) ? readFileSync( path, 'utf8' ) : '';
			if ( current !== text ) {
				stale++;
				console.log( `CŨ  ${ t.out }` );
			}
		} else {
			writeFileSync( path, text );
			console.log( `${ t.out }: ${ t.entries.length - 1 } chuỗi (${ t.count } lượt dùng)` );
		}
		t.dynamic.forEach( ( d ) => console.log( '  cảnh báo: chuỗi động, không trích được — ' + d ) );
	}
	process.exit( stale ? 1 : 0 );
}
