// Đọc/ghi tệp .po và biên dịch .mo (định dạng nhị phân GNU gettext) — không cần cài gettext.
// Đủ dùng cho dự án: ngữ cảnh (msgctxt), số nhiều (msgid_plural, msgstr[n]), chú thích, cờ (fuzzy, php-format).

/** Giải mã chuỗi trong dấu nháy kép của .po. */
export function unescapePo( s ) {
	return s.replace( /\\(["\\nrt])/g, ( _, c ) => ( { n: '\n', r: '\r', t: '\t', '"': '"', '\\': '\\' } )[ c ] );
}

/** Mã hóa chuỗi để ghi vào .po. */
export function escapePo( s ) {
	return s.replace( /\\/g, '\\\\' ).replace( /"/g, '\\"' ).replace( /\n/g, '\\n' ).replace( /\r/g, '\\r' ).replace( /\t/g, '\\t' );
}

/**
 * @typedef {{ctxt: string|null, id: string, plural: string|null, str: string[], refs: string[], extracted: string[], comments: string[], flags: string[]}} PoEntry
 */

/** @returns {PoEntry} */
export function newEntry( id = '' ) {
	return { ctxt: null, id, plural: null, str: [ '' ], refs: [], extracted: [], comments: [], flags: [] };
}

/**
 * Phân tích nội dung .po.
 *
 * @param {string} text
 * @returns {PoEntry[]} Mục đầu tiên có id rỗng là phần đầu (header) nếu có.
 */
export function parsePo( text ) {
	const entries = [];
	let cur = null;
	let field = null; // trường đang nối dòng: 'ctxt' | 'id' | 'plural' | 'str:N'
	const flush = () => {
		if ( cur && ( cur.id !== '' || cur.str.some( Boolean ) || cur.refs.length || cur.ctxt !== null ) ) {
			entries.push( cur );
		}
		cur = null;
		field = null;
	};
	const ensure = () => ( cur ??= newEntry() );

	for ( const raw of text.replace( /\r\n/g, '\n' ).split( '\n' ) ) {
		const line = raw.trim();
		if ( line === '' ) {
			flush();
			continue;
		}
		if ( line.startsWith( '#~' ) ) {
			continue; // mục đã lỗi thời
		}
		if ( line.startsWith( '#' ) ) {
			// Chú thích của mục kế tiếp. Nếu mục hiện tại đã có msgid thì đây là mục mới.
			if ( cur && field !== null ) {
				flush();
			}
			const e = ensure();
			if ( line.startsWith( '#:' ) ) {
				e.refs.push( ...line.slice( 2 ).trim().split( /\s+/ ).filter( Boolean ) );
			} else if ( line.startsWith( '#,' ) ) {
				e.flags.push( ...line.slice( 2 ).split( ',' ).map( ( f ) => f.trim() ).filter( Boolean ) );
			} else if ( line.startsWith( '#.' ) ) {
				e.extracted.push( line.slice( 2 ).trim() );
			} else if ( ! line.startsWith( '#|' ) ) {
				e.comments.push( line.slice( 1 ).trim() );
			}
			continue;
		}

		let m;
		if ( ( m = line.match( /^msgctxt\s+"(.*)"$/ ) ) ) {
			if ( cur && field !== null ) {
				flush();
			}
			ensure().ctxt = unescapePo( m[ 1 ] );
			field = 'ctxt';
		} else if ( ( m = line.match( /^msgid\s+"(.*)"$/ ) ) ) {
			if ( cur && field !== null && field !== 'ctxt' ) {
				flush();
			}
			ensure().id = unescapePo( m[ 1 ] );
			field = 'id';
		} else if ( ( m = line.match( /^msgid_plural\s+"(.*)"$/ ) ) ) {
			ensure().plural = unescapePo( m[ 1 ] );
			field = 'plural';
		} else if ( ( m = line.match( /^msgstr(?:\[(\d+)\])?\s+"(.*)"$/ ) ) ) {
			const n = m[ 1 ] === undefined ? 0 : Number( m[ 1 ] );
			ensure().str[ n ] = unescapePo( m[ 2 ] );
			field = 'str:' + n;
		} else if ( ( m = line.match( /^"(.*)"$/ ) ) && field ) {
			const piece = unescapePo( m[ 1 ] );
			if ( field === 'ctxt' ) {
				cur.ctxt += piece;
			} else if ( field === 'id' ) {
				cur.id += piece;
			} else if ( field === 'plural' ) {
				cur.plural += piece;
			} else {
				cur.str[ Number( field.slice( 4 ) ) ] += piece;
			}
		} else {
			throw new Error( 'Dòng .po không hiểu: ' + raw );
		}
	}
	flush();
	return entries;
}

function quoted( label, value ) {
	if ( ! value.includes( '\n' ) || value === '\n' ) {
		return `${ label } "${ escapePo( value ) }"`;
	}
	// Nhiều dòng: dòng đầu rỗng, mỗi dòng của chuỗi một dòng .po (theo quy ước của gettext).
	const parts = value.split( /(?<=\n)/ );
	return [ `${ label } ""`, ...parts.map( ( p ) => `"${ escapePo( p ) }"` ) ].join( '\n' );
}

/** Ghi danh sách mục ra văn bản .po. */
export function writePo( entries ) {
	const blocks = entries.map( ( e ) => {
		const lines = [];
		e.comments.forEach( ( c ) => lines.push( '# ' + c ) );
		e.extracted.forEach( ( c ) => lines.push( '#. ' + c ) );
		if ( e.refs.length ) {
			lines.push( '#: ' + e.refs.join( ' ' ) );
		}
		if ( e.flags.length ) {
			lines.push( '#, ' + e.flags.join( ', ' ) );
		}
		if ( e.ctxt !== null ) {
			lines.push( quoted( 'msgctxt', e.ctxt ) );
		}
		lines.push( quoted( 'msgid', e.id ) );
		if ( e.plural !== null ) {
			lines.push( quoted( 'msgid_plural', e.plural ) );
			const count = Math.max( e.str.length, 2 );
			for ( let i = 0; i < count; i++ ) {
				lines.push( quoted( `msgstr[${ i }]`, e.str[ i ] ?? '' ) );
			}
		} else {
			lines.push( quoted( 'msgstr', e.str[ 0 ] ?? '' ) );
		}
		return lines.join( '\n' );
	} );
	return blocks.join( '\n\n' ) + '\n';
}

/** Khóa tra cứu của mục trong MO: ngữ cảnh + \x04 + msgid (+ \0 + số nhiều). */
export function moKey( e ) {
	return ( e.ctxt !== null ? e.ctxt + '\x04' : '' ) + e.id + ( e.plural !== null ? '\0' + e.plural : '' );
}

/**
 * Biên dịch sang .mo. Bỏ mục chưa dịch và mục fuzzy (như msgfmt mặc định); giữ phần đầu.
 *
 * @param {PoEntry[]} entries
 * @returns {Buffer}
 */
export function compileMo( entries ) {
	const used = entries.filter( ( e ) => {
		if ( e.id === '' && e.ctxt === null ) {
			return true; // header
		}
		return ! e.flags.includes( 'fuzzy' ) && e.str.some( ( s ) => s !== '' );
	} );
	const pairs = used
		.map( ( e ) => ( { key: Buffer.from( moKey( e ), 'utf8' ), value: Buffer.from( ( e.plural !== null ? e.str : [ e.str[ 0 ] ?? '' ] ).join( '\0' ), 'utf8' ) } ) )
		.sort( ( a, b ) => Buffer.compare( a.key, b.key ) );

	const n = pairs.length;
	const headerSize = 28;
	const tablesSize = 16 * n;
	let offset = headerSize + tablesSize;
	const origTable = [];
	const transTable = [];
	const chunks = [];
	for ( const { key } of pairs ) {
		origTable.push( [ key.length, offset ] );
		chunks.push( key, Buffer.from( [ 0 ] ) );
		offset += key.length + 1;
	}
	for ( const { value } of pairs ) {
		transTable.push( [ value.length, offset ] );
		chunks.push( value, Buffer.from( [ 0 ] ) );
		offset += value.length + 1;
	}

	const head = Buffer.alloc( headerSize + tablesSize );
	head.writeUInt32LE( 0x950412de, 0 );
	head.writeUInt32LE( 0, 4 ); // phiên bản
	head.writeUInt32LE( n, 8 );
	head.writeUInt32LE( headerSize, 12 ); // bảng chuỗi gốc
	head.writeUInt32LE( headerSize + 8 * n, 16 ); // bảng chuỗi dịch
	head.writeUInt32LE( 0, 20 ); // không dùng bảng băm
	head.writeUInt32LE( headerSize + 16 * n, 24 );
	origTable.forEach( ( [ len, off ], i ) => {
		head.writeUInt32LE( len, headerSize + 8 * i );
		head.writeUInt32LE( off, headerSize + 8 * i + 4 );
	} );
	transTable.forEach( ( [ len, off ], i ) => {
		head.writeUInt32LE( len, headerSize + 8 * n + 8 * i );
		head.writeUInt32LE( off, headerSize + 8 * n + 8 * i + 4 );
	} );
	return Buffer.concat( [ head, ...chunks ] );
}

/**
 * Đọc .mo (để kiểm tra vòng khứ hồi).
 *
 * @param {Buffer} buf
 * @returns {Map<string,string>} khóa (ngữ cảnh\x04msgid[\0số nhiều]) → bản dịch (các dạng nối bằng \0)
 */
export function parseMo( buf ) {
	const magic = buf.readUInt32LE( 0 );
	if ( magic !== 0x950412de ) {
		throw new Error( 'Sai magic của tệp .mo' );
	}
	const n = buf.readUInt32LE( 8 );
	const origAt = buf.readUInt32LE( 12 );
	const transAt = buf.readUInt32LE( 16 );
	const map = new Map();
	for ( let i = 0; i < n; i++ ) {
		const [ ol, oo ] = [ buf.readUInt32LE( origAt + 8 * i ), buf.readUInt32LE( origAt + 8 * i + 4 ) ];
		const [ tl, to ] = [ buf.readUInt32LE( transAt + 8 * i ), buf.readUInt32LE( transAt + 8 * i + 4 ) ];
		map.set( buf.subarray( oo, oo + ol ).toString( 'utf8' ), buf.subarray( to, to + tl ).toString( 'utf8' ) );
	}
	return map;
}

/** Các ký hiệu printf trong chuỗi, để so sánh msgid với msgstr. */
export function placeholders( s ) {
	return ( s.match( /%(?:\d+\$)?[sdfu%]/g ) ?? [] ).filter( ( p ) => p !== '%%' ).sort();
}
