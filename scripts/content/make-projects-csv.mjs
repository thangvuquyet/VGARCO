import { readFileSync, writeFileSync, mkdirSync } from 'node:fs';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = join( dirname( fileURLToPath( import.meta.url ) ), '..', '..' );
const projects = JSON.parse( readFileSync( join( root, 'tests', 'php', 'data', 'projects.json' ), 'utf8' ) );

const TERM = {
	diplomatic: 'ngoai-giao-cong-quyen',
	security: 'quan-su-an-ninh',
	education: 'giao-duc-dao-tao',
	culture: 'van-hoa-di-tich',
	healthcare: 'y-te',
	infrastructure: 'cong-nghiep-ha-tang',
	residential: 'nha-o-dan-dung',
};
const FEATURED = [ 'ambassador', 'savannakhet', 'specialforces' ];
const SHOWCASE = [ 'tower', 'academy', 'museum' ];

const slug = ( text ) =>
	text
		.normalize( 'NFD' )
		.replace( /[̀-ͯ]/g, '' )
		.replace( /đ/g, 'd' )
		.replace( /Đ/g, 'D' )
		.toLowerCase()
		.replace( /[^a-z0-9]+/g, '-' )
		.replace( /^-+|-+$/g, '' );

const country = ( location ) => ( /Lào/.test( location ) ? 'la' : /Đức/.test( location ) ? 'de' : 'vn' );

const cell = ( value ) => {
	const s = String( value ?? '' );
	return /[;"\r\n]/.test( s ) ? '"' + s.replace( /"/g, '""' ) + '"' : s;
};

const header = [ 'ma', 'thu_tu', 'ten_vi', 'ten_de', 'loai', 'quoc_gia', 'dia_diem_vi', 'dia_diem_de', 'thoi_gian', 'vai_tro_vi', 'vai_tro_de', 'chu_dau_tu_vi', 'chu_dau_tu_de', 'doi_tac', 'cong_bo_chu_dau_tu', 'mo_ta_vi', 'mo_ta_de', 'loai_anh_dai_dien', 'noi_bat', 'tieu_bieu', 'mo_ta_seo_vi', 'mo_ta_seo_de' ];
const seen = new Set();
const rows = projects.map( ( p ) => {
	let ma = slug( p.title );
	if ( seen.has( ma ) ) {
		ma += '-' + p.key;
	}
	seen.add( ma );
	return [
		ma, p.order, p.title, '', TERM[ p.category ] ?? '', country( p.location ), p.location, '', p.year, p.role, '', p.owner, '', p.partner,
		0, p.description, '', p.kind, FEATURED.includes( p.key ) ? 1 : 0, SHOWCASE.includes( p.key ) ? 1 : 0, '', '',
	];
} );

const csv = '﻿' + [ header, ...rows ].map( ( r ) => r.map( cell ).join( ';' ) ).join( '\r\n' ) + '\r\n';
mkdirSync( join( root, 'content' ), { recursive: true } );
writeFileSync( join( root, 'content', 'projects.csv' ), csv );
console.log( `content/projects.csv: ${ rows.length } dự án` );
