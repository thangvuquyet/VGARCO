<?php

defined( 'ABSPATH' ) || exit;

const VGARCO_APPLY_RETENTION_MONTHS = 12;

function vgarco_recruit_caps(): array {
	return array(
		'vg_view_applications'   => true,
		'vg_download_cv'         => true,
		'vg_export_applications' => true,
		'vg_delete_applications' => true,
	);
}

function vgarco_apply_retention_cutoff( int $now, int $months = VGARCO_APPLY_RETENTION_MONTHS ): int {
	$date = ( new DateTimeImmutable( '@' . $now ) )->setTimezone( new DateTimeZone( 'UTC' ) );
	return $date->modify( '-' . $months . ' months' )->getTimestamp();
}

function vgarco_apply_is_expired( int $submitted_at, int $now, int $months = VGARCO_APPLY_RETENTION_MONTHS ): bool {
	return $submitted_at < vgarco_apply_retention_cutoff( $now, $months );
}

function vgarco_path_normalize( string $path ): string {
	$path = rtrim( str_replace( '\\', '/', $path ), '/' );
	return '\\' === DIRECTORY_SEPARATOR ? strtolower( $path ) : $path;
}

function vgarco_path_inside( string $path, string $root ): bool {
	$path = vgarco_path_normalize( $path );
	$root = vgarco_path_normalize( $root );
	return '' !== $root && ( $path === $root || str_starts_with( $path, $root . '/' ) );
}

function vgarco_apply_cv_path_valid( string $relative ): bool {
	return 1 === preg_match( '#^cv/\d{4}/\d{2}/[a-f0-9]{32}$#', $relative );
}

function vgarco_cv_ext_for_mime( string $mime ): string {
	$map = array(
		'application/pdf'    => 'pdf',
		'application/msword' => 'doc',
		'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
	);
	return $map[ $mime ] ?? 'bin';
}

function vgarco_mail_line( string $text ): string {
	return trim( (string) preg_replace( '/[\r\n\t]+/', ' ', strip_tags( $text ) ) );
}

function vgarco_apply_fill( string $template, array $values ): string {
	$pairs = array();
	foreach ( $values as $key => $value ) {
		$pairs[ '{' . $key . '}' ] = (string) $value;
	}
	return strtr( $template, $pairs );
}

function vgarco_xlsx_col( int $index ): string {
	$name = '';
	for ( $n = $index + 1; $n > 0; $n = intdiv( $n - 1, 26 ) ) {
		$name = chr( 65 + ( $n - 1 ) % 26 ) . $name;
	}
	return $name;
}

function vgarco_xlsx_clean( string $text ): string {
	$text = preg_replace( '/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', $text ) ?? '';
	return mb_substr( $text, 0, 32000 );
}

function vgarco_xlsx_build( array $headers, array $rows, array $widths = array(), string $sheet = 'Ho so ung tuyen' ): string {
	if ( ! class_exists( 'ZipArchive' ) ) {
		throw new RuntimeException( 'Thiếu phần mở rộng zip của PHP.' );
	}

	$cell = static function ( int $col, int $row, $value, int $style ): string {
		$ref = vgarco_xlsx_col( $col ) . $row;
		if ( is_int( $value ) || is_float( $value ) ) {
			return '<c r="' . $ref . '" s="' . $style . '"><v>' . $value . '</v></c>';
		}
		return '<c r="' . $ref . '" s="' . $style . '" t="inlineStr"><is><t xml:space="preserve">'
			. htmlspecialchars( vgarco_xlsx_clean( (string) $value ), ENT_XML1 | ENT_QUOTES, 'UTF-8' )
			. '</t></is></c>';
	};

	$data = '<row r="1">';
	foreach ( array_values( $headers ) as $c => $title ) {
		$data .= $cell( $c, 1, $title, 1 );
	}
	$data .= '</row>';
	foreach ( array_values( $rows ) as $r => $row ) {
		$data .= '<row r="' . ( $r + 2 ) . '">';
		foreach ( array_values( $row ) as $c => $value ) {
			$data .= $cell( $c, $r + 2, $value, 0 );
		}
		$data .= '</row>';
	}

	$cols = '';
	foreach ( array_values( $widths ) as $c => $w ) {
		$cols .= '<col min="' . ( $c + 1 ) . '" max="' . ( $c + 1 ) . '" width="' . (int) $w . '" customWidth="1"/>';
	}
	$last = vgarco_xlsx_col( max( 0, count( $headers ) - 1 ) ) . ( count( $rows ) + 1 );

	$sheet_xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
		. '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
		. '<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
		. ( '' !== $cols ? '<cols>' . $cols . '</cols>' : '' )
		. '<sheetData>' . $data . '</sheetData>'
		. '<autoFilter ref="A1:' . $last . '"/>'
		. '</worksheet>';

	$ns_rel = 'http://schemas.openxmlformats.org/package/2006/relationships';
	$files  = array(
		'[Content_Types].xml'        => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
			. '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
			. '<Default Extension="xml" ContentType="application/xml"/>'
			. '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
			. '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
			. '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
			. '</Types>',
		'_rels/.rels'                => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<Relationships xmlns="' . $ns_rel . '"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>',
		'xl/workbook.xml'            => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
			. '<sheets><sheet name="' . htmlspecialchars( mb_substr( preg_replace( '/[\[\]:*?\/\\\\]/', ' ', $sheet ) ?? 'Sheet1', 0, 31 ), ENT_XML1 | ENT_QUOTES, 'UTF-8' ) . '" sheetId="1" r:id="rId1"/></sheets></workbook>',
		'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<Relationships xmlns="' . $ns_rel . '">'
			. '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
			. '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
			. '</Relationships>',
		'xl/styles.xml'              => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
			. '<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts>'
			. '<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill>'
			. '<fill><patternFill patternType="solid"><fgColor rgb="FFEFEFEA"/></patternFill></fill></fills>'
			. '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
			. '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
			. '<cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"><alignment vertical="top" wrapText="1"/></xf>'
			. '<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/></cellXfs>'
			. '</styleSheet>',
		'xl/worksheets/sheet1.xml'   => $sheet_xml,
	);

	$tmp = tempnam( sys_get_temp_dir(), 'vgx' );
	if ( false === $tmp ) {
		throw new RuntimeException( 'Không tạo được tệp tạm.' );
	}
	$zip = new ZipArchive();
	if ( true !== $zip->open( $tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
		@unlink( $tmp );
		throw new RuntimeException( 'Không mở được tệp nén.' );
	}
	foreach ( $files as $name => $xml ) {
		$zip->addFromString( $name, $xml );
	}
	$zip->close();

	$binary = (string) file_get_contents( $tmp );
	@unlink( $tmp );
	return $binary;
}
