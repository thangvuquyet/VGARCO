// Chép phông chữ tự host từ gói Fontsource vào src/fonts/ và sinh src/scss/_fonts.scss.
//   npm run fonts
//
// Chỉ lấy ba tập con cần cho tiếng Việt và tiếng Đức. Mỗi tập là một tệp WOFF2 riêng kèm unicode-range,
// nên trình duyệt chỉ tải tập mà trang thực sự dùng (trang tiếng Việt: latin + vietnamese).
//   latin       — chữ Latin cơ bản, ä ö ü Ä Ö Ü ß, dấu câu
//   vietnamese  — chữ có dấu tiếng Việt (ă â ê ô ơ ư đ và thanh điệu), dấu kết hợp
//   latin-ext   — ký tự Latin mở rộng (tên riêng nước ngoài)
//
// Phông đặt trên máy chủ website, không tải từ dịch vụ bên thứ ba (yêu cầu 5.3.5).
import { copyFileSync, existsSync, mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = join( dirname( fileURLToPath( import.meta.url ) ), '..' );
const modules = join( root, 'node_modules', '@fontsource' );
const outFonts = join( root, 'src', 'fonts' );

const SUBSETS = [ 'vietnamese', 'latin-ext', 'latin' ]; // thứ tự nguồn của Fontsource: tập đứng sau thắng khi trùng dải mã
const FACES = [
	// Chữ nghiêng Lora: chỉ dùng cho cụm từ nhấn trong tiêu đề (đặc tả bảng 6.3).
	{ pkg: 'lora', css: '400-italic.css', weight: '400', style: 'italic', family: 'Lora' },
	// Open Sans làm phông dự phòng cho macOS/iOS/Android (Segoe UI chỉ có sẵn trên Windows) — đặc tả mục 6.2.
	{ pkg: 'open-sans', css: '400.css', weight: '400', style: 'normal', family: 'VG Sans' },
	{ pkg: 'open-sans', css: '600.css', weight: '600', style: 'normal', family: 'VG Sans' },
];

mkdirSync( outFonts, { recursive: true } );

const blocks = [];
const copied = new Set();

for ( const face of FACES ) {
	const css = readFileSync( join( modules, face.pkg, face.css ), 'utf8' );
	const wanted = SUBSETS.map( ( subset ) => `${ face.pkg }-${ subset }-${ face.weight }-${ face.style }` );

	const found = [];
	for ( const match of css.matchAll( /\/\*\s*([\w-]+)\s*\*\/\s*@font-face\s*\{([^}]*)\}/g ) ) {
		const [ , label, body ] = match;
		if ( ! wanted.includes( label ) ) {
			continue;
		}
		const range = body.match( /unicode-range:\s*([^;]+);/ )?.[ 1 ].trim();
		if ( ! range ) {
			throw new Error( `Thiếu unicode-range: ${ label }` );
		}
		const file = `${ label }.woff2`;
		const from = join( modules, face.pkg, 'files', file );
		if ( ! existsSync( from ) ) {
			throw new Error( `Thiếu tệp phông: ${ from }` );
		}
		if ( ! copied.has( file ) ) {
			copyFileSync( from, join( outFonts, file ) );
			copied.add( file );
		}
		found.push( { label, file, range } );
	}

	if ( found.length !== wanted.length ) {
		throw new Error( `${ face.pkg } ${ face.css }: cần ${ wanted.length } tập con, chỉ tìm thấy ${ found.length }` );
	}

	for ( const { label, file, range } of found ) {
		blocks.push(
			`// ${ label }\n@font-face {\n  font-family: '${ face.family }';\n  font-style: ${ face.style };\n  font-weight: ${ face.weight };\n  font-display: swap;\n  src: url('../fonts/${ file }') format('woff2');\n  unicode-range: ${ range };\n}\n`
		);
	}
}

// Giấy phép phông (SIL OFL / Apache) đi kèm tệp phông.
for ( const pkg of [ ...new Set( FACES.map( ( f ) => f.pkg ) ) ] ) {
	const license = join( modules, pkg, 'LICENSE' );
	if ( existsSync( license ) ) {
		copyFileSync( license, join( outFonts, `LICENSE-${ pkg }.txt` ) );
	}
}

const header = `// TỆP SINH TỰ ĐỘNG bởi \`npm run fonts\` (scripts/copy-fonts.mjs). Không sửa tay.
// Phông: Lora nghiêng 400 cho cụm từ nhấn; VG Sans (= Open Sans) 400/600 làm phông dự phòng cho macOS, iOS, Android.
// Giấy phép: xem src/fonts/LICENSE-*.txt.

`;
writeFileSync( join( root, 'src', 'scss', '_fonts.scss' ), header + blocks.join( '\n' ) );

console.log( `Đã chép ${ copied.size } tệp phông vào src/fonts/ và sinh ${ blocks.length } khối @font-face.` );
