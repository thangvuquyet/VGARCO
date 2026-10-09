<?php

define( 'ABSPATH', __DIR__ . '/' );
define( 'ROOT', dirname( __DIR__, 2 ) );

$careers = ROOT . '/wp-content/plugins/vgarco-core/inc/careers/';
foreach ( array( 'captcha', 'apply-validate', 'apply-process', 'applications-lib' ) as $file ) {
	require_once $careers . $file . '.php';
}

$pass = 0;
$fail = array();

function check( string $label, bool $condition ): void {
	global $pass, $fail;
	if ( $condition ) {
		++$pass;
	} else {
		$fail[] = $label;
	}
}

function same( string $label, $expected, $actual ): void {
	check( $label . ' — mong đợi ' . var_export( $expected, true ) . ', nhận ' . var_export( $actual, true ), $expected === $actual );
}

$tmp = sys_get_temp_dir() . '/vgarco-apply-test-' . getmypid();
@mkdir( $tmp, 0777, true );
register_shutdown_function(
	static function () use ( $tmp ): void {
		foreach ( (array) glob( $tmp . '/*' ) as $f ) {
			@unlink( $f );
		}
		@rmdir( $tmp );
	}
);

function make_file( string $name, string $content ): string {
	global $tmp;
	$path = $tmp . '/' . $name;
	file_put_contents( $path, $content );
	return $path;
}

function make_zip( string $name, array $entries ): string {
	global $tmp;
	$path = $tmp . '/' . $name;
	$zip  = new ZipArchive();
	$zip->open( $path, ZipArchive::CREATE | ZipArchive::OVERWRITE );
	foreach ( $entries as $entry => $body ) {
		$zip->addFromString( $entry, $body );
	}
	$zip->close();
	return $path;
}

function upload( string $path, string $client_name, int $error = UPLOAD_ERR_OK ): array {
	return array(
		'name'     => $client_name,
		'tmp_name' => $path,
		'error'    => $error,
		'size'     => is_file( $path ) ? filesize( $path ) : 0,
	);
}

$pdf       = make_file( 'ok.pdf', "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF\n" );
$docx      = make_zip( 'ok.docx', array( '[Content_Types].xml' => '<Types/>', 'word/document.xml' => '<w:document/>' ) );
$docx_vba  = make_zip( 'macro.docx', array( '[Content_Types].xml' => '<Types/>', 'word/document.xml' => '<w:document/>', 'word/vbaProject.bin' => 'x' ) );
$docx_none = make_zip( 'nodoc.docx', array( 'readme.txt' => 'không phải tài liệu Word' ) );
$doc       = make_file( 'ok.doc', "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1" . str_repeat( "\0", 600 ) . 'Kỹ năng: VBA, Macros, Excel' );
$doc_vba   = make_file( 'macro.doc', "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1" . str_repeat( "\0", 600 ) . mb_convert_encoding( '_VBA_PROJECT', 'UTF-16LE', 'UTF-8' ) . str_repeat( "\0", 100 ) );
$exe       = make_file( 'virus.pdf', "MZ\x90\x00\x03\x00\x00\x00" . str_repeat( "\0", 300 ) . 'This program cannot be run in DOS mode.' );
$html      = make_file( 'page.pdf', '<html><script>alert(1)</script></html> ' . str_repeat( 'x', 40 ) );
$php_file  = make_file( 'shell.docx', '<?php system($_GET["c"]); ?>' . str_repeat( ' ', 40 ) );
$empty     = make_file( 'empty.pdf', '' );
$ole_only  = make_file( 'ole.pdf', "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1" . str_repeat( "\0", 600 ) );
$zip_only  = make_zip( 'zip.pdf', array( 'word/document.xml' => '<w:document/>' ) );
$text_pdf  = make_file( 'text.pdf', str_repeat( 'chỉ là văn bản ', 5 ) );
$big       = make_file( 'big.pdf', "%PDF-1.4\n" );
$handle    = fopen( $big, 'r+' );
ftruncate( $handle, VGARCO_CV_MAX_BYTES + 1 );
fclose( $handle );
$exact     = make_file( 'exact.pdf', "%PDF-1.4\n" );
$handle    = fopen( $exact, 'r+' );
ftruncate( $handle, VGARCO_CV_MAX_BYTES );
fclose( $handle );

$codes = array();
for ( $i = 0; $i < 200; $i++ ) {
	$codes[] = vgarco_captcha_code();
}
same( 'captcha: mã dài 5 ký tự', array( 5 ), array_values( array_unique( array_map( 'strlen', $codes ) ) ) );
check( 'captcha: chỉ dùng ký tự trong bảng (không có 0 O 1 I L)', 0 === count( array_filter( $codes, fn( $c ) => preg_match( '/[^' . VGARCO_CAPTCHA_ALPHABET . ']/', $c ) ) ) );
check( 'captcha: bảng ký tự không chứa ký tự dễ nhầm', ! preg_match( '/[01OIL]/', VGARCO_CAPTCHA_ALPHABET ) );
check( 'captcha: 200 mã không trùng nhau hết (ngẫu nhiên thật)', count( array_unique( $codes ) ) > 190 );

same( 'captcha: chuẩn hóa bỏ khoảng trắng, đổi hoa', 'AB7K9', vgarco_captcha_normalize( " ab 7\tk9 " ) );
same( 'captcha: băm không phân biệt hoa thường và khoảng trắng', vgarco_captcha_hash( 'AB7K9', 's' ), vgarco_captcha_hash( ' ab7 k9', 's' ) );
check( 'captcha: mã khác → băm khác', vgarco_captcha_hash( 'AB7K9', 's' ) !== vgarco_captcha_hash( 'AB7K8', 's' ) );
check( 'captcha: muối khác → băm khác', vgarco_captcha_hash( 'AB7K9', 's1' ) !== vgarco_captcha_hash( 'AB7K9', 's2' ) );
check( 'captcha: băm không chứa mã', ! str_contains( vgarco_captcha_hash( 'AB7K9', 's' ), 'AB7K9' ) );

$image = vgarco_captcha_image( 'AB7K9' );
check( 'captcha: ảnh là PNG data URI', str_starts_with( $image, 'data:image/png;base64,' ) );
$png  = base64_decode( substr( $image, strlen( 'data:image/png;base64,' ) ), true );
$size = false !== $png ? getimagesizefromstring( $png ) : false;
same( 'captcha: PNG hợp lệ 180x60', array( 180, 60, IMAGETYPE_PNG ), $size ? array( $size[0], $size[1], $size[2] ) : null );
check( 'captcha: mã không nằm dạng chữ trong ảnh PNG', false !== $png && ! str_contains( $png, 'AB7K9' ) );
check( 'captcha: mỗi lần vẽ một ảnh khác', vgarco_captcha_image( 'AB7K9' ) !== $image );
check( 'captcha: SVG dự phòng là SVG hợp lệ', str_starts_with( vgarco_captcha_svg( 'AB7K9' ), 'data:image/svg+xml;base64,' ) );

$valid = array( 'name' => 'Nguyễn Văn An', 'email' => 'an.nguyen@example.com', 'phone' => '0988 961 666', 'consent' => '1' );
same( 'trường: hợp lệ → không lỗi', array(), vgarco_apply_validate_fields( $valid ) );
same( 'trường: rỗng hết → 3 lỗi bắt buộc + đồng ý', array( 'name' => 'required', 'email' => 'required', 'phone' => 'required', 'consent' => 'consent' ), vgarco_apply_validate_fields( array() ) );
same( 'trường: chỉ khoảng trắng coi như rỗng', array( 'name' => 'required' ), vgarco_apply_validate_fields( array_merge( $valid, array( 'name' => "  \t " ) ) ) );
same( 'trường: họ tên quá 100 ký tự', array( 'name' => 'required' ), vgarco_apply_validate_fields( array_merge( $valid, array( 'name' => str_repeat( 'a', 101 ) ) ) ) );
same( 'trường: họ tên đúng 100 ký tự', array(), vgarco_apply_validate_fields( array_merge( $valid, array( 'name' => str_repeat( 'ạ', 100 ) ) ) ) );
foreach ( array( 'khong-phai-email', 'an@', '@example.com', 'an@example', "an@example.com\r\nBcc: victim@example.org", 'an@example.com, b@example.com' ) as $bad ) {
	same( 'email không hợp lệ: ' . json_encode( $bad ), array( 'email' => 'email' ), vgarco_apply_validate_fields( array_merge( $valid, array( 'email' => $bad ) ) ) );
}
foreach ( array( '0988961666', '+84 988 961 666', '(024) 3555-1234', '+49 30 1234567' ) as $good ) {
	same( 'điện thoại hợp lệ: ' . $good, array(), vgarco_apply_validate_fields( array_merge( $valid, array( 'phone' => $good ) ) ) );
}
foreach ( array( 'abc', '12345', '0988abc961', '0988 961 666 ext', str_repeat( '9', 16 ), '<script>' ) as $bad ) {
	same( 'điện thoại không hợp lệ: ' . $bad, array( 'phone' => 'phone' ), vgarco_apply_validate_fields( array_merge( $valid, array( 'phone' => $bad ) ) ) );
}
foreach ( array( '', '0', 'on', 'true' ) as $bad ) {
	same( 'đồng ý không đúng "1": ' . json_encode( $bad ), array( 'consent' => 'consent' ), vgarco_apply_validate_fields( array_merge( $valid, array( 'consent' => $bad ) ) ) );
}

same( 'tên tệp: bỏ đường dẫn kiểu Unix', 'passwd.pdf', vgarco_apply_clean_filename( '../../etc/passwd.pdf' ) );
same( 'tên tệp: bỏ đường dẫn kiểu Windows', 'CV.pdf', vgarco_apply_clean_filename( 'C:\\Users\\x\\CV.pdf' ) );
same( 'tên tệp: bỏ ký tự điều khiển và ký tự nguy hiểm', 'ab.pdf', vgarco_apply_clean_filename( "a\x00<b>\x1F.pdf" ) );
same( 'tên tệp: giữ tiếng Việt', 'CV Nguyễn Văn An.pdf', vgarco_apply_clean_filename( 'CV Nguyễn Văn An.pdf' ) );
same( 'tên tệp: chỉ toàn dấu chấm → cv', 'cv', vgarco_apply_clean_filename( '...' ) );
same( 'tên tệp: rỗng → cv', 'cv', vgarco_apply_clean_filename( '' ) );
$long = vgarco_apply_clean_filename( str_repeat( 'a', 300 ) . '.docx' );
check( 'tên tệp: tên dài bị cắt nhưng giữ đuôi', mb_strlen( $long ) <= 120 && str_ends_with( $long, '.docx' ) );

$cases = array(
	array( 'PDF thật', $pdf, 'ok.pdf', true, 'application/pdf' ),
	array( 'DOCX thật', $docx, 'ok.docx', true, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' ),
	array( 'DOC thật (chữ VBA/Macros trong nội dung CV không phải macro)', $doc, 'ok.doc', true, 'application/msword' ),
	array( 'PDF đúng 10 MB', $exact, 'exact.pdf', true, 'application/pdf' ),
	array( 'đuôi viết hoa', $pdf, 'CV.PDF', true, 'application/pdf' ),
	array( 'exe đổi đuôi .pdf', $exe, 'virus.pdf', false, 'file_invalid' ),
	array( 'HTML đổi đuôi .pdf', $html, 'page.pdf', false, 'file_invalid' ),
	array( 'PHP đổi đuôi .docx', $php_file, 'shell.docx', false, 'file_invalid' ),
	array( 'văn bản thường đổi đuôi .pdf', $text_pdf, 'text.pdf', false, 'file_invalid' ),
	array( 'DOCX có macro (vbaProject.bin)', $docx_vba, 'macro.docx', false, 'file_invalid' ),
	array( 'DOCX không có word/document.xml', $docx_none, 'nodoc.docx', false, 'file_invalid' ),
	array( 'DOC có macro (_VBA_PROJECT)', $doc_vba, 'macro.doc', false, 'file_invalid' ),
	array( 'tệp rỗng', $empty, 'empty.pdf', false, 'file_invalid' ),
	array( 'văn bản thường đổi đuôi .doc', $text_pdf, 'text.doc', false, 'file_invalid' ),
	array( 'PDF đổi đuôi .doc', $pdf, 'cv.doc', false, 'file_invalid' ),
	array( 'DOCX đổi đuôi .doc', $docx, 'cv.doc', false, 'file_invalid' ),
	array( 'DOC (OLE) đổi đuôi .pdf', $ole_only, 'ole.pdf', false, 'file_invalid' ),
	array( 'DOCX (zip) đổi đuôi .pdf', $zip_only, 'zip.pdf', false, 'file_invalid' ),
	array( 'DOC (OLE) đổi đuôi .docx', $doc, 'cv.docx', false, 'file_invalid' ),
	array( 'PDF đổi đuôi .docx', $pdf, 'cv.docx', false, 'file_invalid' ),
	array( 'quá 10 MB', $big, 'big.pdf', false, 'file_size' ),
	array( 'đuôi .exe', $pdf, 'setup.exe', false, 'file_type' ),
	array( 'đuôi kép .pdf.exe', $pdf, 'cv.pdf.exe', false, 'file_type' ),
	array( 'đuôi .php', $pdf, 'cv.php', false, 'file_type' ),
	array( 'không có đuôi', $pdf, 'cv', false, 'file_type' ),
	array( 'đuôi .rtf', $pdf, 'cv.rtf', false, 'file_type' ),
);
foreach ( $cases as [ $label, $path, $client_name, $ok, $expect ] ) {
	$r = vgarco_apply_validate_cv( upload( $path, $client_name ) );
	same( "tệp CV: {$label} → ok", $ok, $r['ok'] );
	same( "tệp CV: {$label} → " . ( $ok ? 'loại' : 'mã lỗi' ), $expect, $ok ? $r['mime'] : $r['error'] );
}
same( 'tệp CV: không gửi tệp', 'file_required', vgarco_apply_validate_cv( null )['error'] );
same( 'tệp CV: mã UPLOAD_ERR_NO_FILE', 'file_required', vgarco_apply_validate_cv( upload( '', '', UPLOAD_ERR_NO_FILE ) )['error'] );
same( 'tệp CV: vượt upload_max_filesize của PHP', 'file_size', vgarco_apply_validate_cv( upload( '', 'cv.pdf', UPLOAD_ERR_INI_SIZE ) )['error'] );
same( 'tệp CV: vượt MAX_FILE_SIZE của biểu mẫu', 'file_size', vgarco_apply_validate_cv( upload( '', 'cv.pdf', UPLOAD_ERR_FORM_SIZE ) )['error'] );
same( 'tệp CV: tải lên dở dang', 'file_invalid', vgarco_apply_validate_cv( upload( $pdf, 'cv.pdf', UPLOAD_ERR_PARTIAL ) )['error'] );
same( 'tệp CV: không có thư mục tạm', 'file_invalid', vgarco_apply_validate_cv( upload( $pdf, 'cv.pdf', UPLOAD_ERR_NO_TMP_DIR ) )['error'] );
$clean = vgarco_apply_validate_cv( upload( $pdf, '../../x/CV Nguyễn.pdf' ) );
same( 'tệp CV: tên gốc được làm sạch', 'CV Nguyễn.pdf', $clean['name'] );
same( 'tệp CV: trả dung lượng thật', filesize( $pdf ), $clean['size'] );

function fake( array $over = array() ): array {
	global $tmp;
	static $n = 0;

	$state = (object) array(
		'tokens'   => array(),
		'records'  => array(),
		'saved'    => array(),
		'deleted'  => array(),
		'mails'    => array(),
		'rates'    => array(),
		'issued'   => 0,
		'consumed' => array(),
	);
	$state->tokens['tok1'] = array( 'issued' => 1000, 'hash' => vgarco_captcha_hash( 'AB7K9', 'salt' ) );

	$deps = array(
		'rate_attempts'    => fn( $ip ) => $over['rate_attempts'] ?? true,
		'rate_submissions' => fn( $ip ) => $over['rate_submissions'] ?? true,
		'rate_record'      => function ( $ip, $accepted ) use ( $state ) {
			$state->rates[] = $accepted ? 'accepted' : 'attempt';
		},
		'token_peek'       => fn( $t ) => $state->tokens[ $t ] ?? null,
		'token_consume'    => function ( $t ) use ( $state, $over ) {
			if ( ! empty( $over['consume_lost'] ) || ! isset( $state->tokens[ $t ] ) ) {
				return false;
			}
			unset( $state->tokens[ $t ] );
			$state->consumed[] = $t;
			return true;
		},
		'token_issue'      => function () use ( $state ) {
			++$state->issued;
			$state->tokens[ 'new' . $state->issued ] = array( 'issued' => 5000, 'hash' => 'x' );
			return array( 'token' => 'new' . $state->issued, 'image' => 'data:image/png;base64,AAAA' );
		},
		'hash'             => fn( $code ) => vgarco_captcha_hash( $code, 'salt' ),
		'job_open'         => fn( $id ) => $over['job_open'] ?? ( 7 === $id ),
		'save_cv'          => function ( $tmp_name, $ext ) use ( $state, $over, $tmp, &$n ) {
			if ( ! empty( $over['save_fail'] ) ) {
				throw new RuntimeException( 'đĩa đầy' );
			}
			++$n;
			$rel = 'cv/2026/09/' . str_pad( (string) $n, 32, 'a', STR_PAD_LEFT );
			copy( $tmp_name, $tmp . '/stored-' . $n );
			$state->saved[] = array( 'path' => $rel, 'ext' => $ext, 'bytes' => filesize( $tmp_name ) );
			return array( 'path' => $rel );
		},
		'insert'           => function ( $data ) use ( $state, $over ) {
			if ( ! empty( $over['insert_fail'] ) ) {
				throw new RuntimeException( 'lỗi CSDL' );
			}
			$state->records[] = $data;
			return 100 + count( $state->records );
		},
		'delete_cv'        => function ( $path ) use ( $state ) {
			$state->deleted[] = $path;
			return true;
		},
		'mail'             => function ( $id ) use ( $state, $over ) {
			$state->mails[] = $id;
			if ( isset( $over['mail'] ) ) {
				return ( $over['mail'] )( $id );
			}
			return array( 'hr' => true, 'candidate' => true );
		},
	);
	return array( $state, $deps );
}

function request( array $in = array() ): array {
	return array_merge(
		array(
			'job_id'  => '7',
			'lang'    => 'de',
			'token'   => 'tok1',
			'website' => '',
			'name'    => '  Nguyễn Văn An ',
			'email'   => ' an@example.com ',
			'phone'   => ' 0988 961 666 ',
			'captcha' => 'ab7k9',
			'consent' => '1',
		),
		$in
	);
}

function context( array $over = array() ): array {
	return array_merge(
		array(
			'method'  => 'POST',
			'origin'  => 'https://vgarco.vn',
			'referer' => '',
			'host'    => 'vgarco.vn',
			'ip_hash' => 'iphash',
			'now'     => 1000 + 60,
		),
		$over
	);
}

$files_ok = array( 'cv' => upload( $pdf, 'CV An.pdf' ) );

[ $st, $deps ] = fake();
$r = vgarco_apply_process( request(), $files_ok, context(), $deps );
same( 'nhận hồ sơ: 200', 200, $r['status'] );
same( 'nhận hồ sơ: ok=true và có id', array( true, 101 ), array( $r['body']['ok'], $r['body']['id'] ) );
same( 'nhận hồ sơ: ghi đúng một bản ghi', 1, count( $st->records ) );
$rec = $st->records[0] ?? array();
same( 'nhận hồ sơ: dữ liệu được cắt khoảng trắng', array( 'Nguyễn Văn An', 'an@example.com', '0988 961 666' ), array( $rec['name'] ?? '', $rec['email'] ?? '', $rec['phone'] ?? '' ) );
same( 'nhận hồ sơ: ngôn ngữ, vị trí, phiên bản đồng ý', array( 'de', 7, VGARCO_APPLY_CONSENT_VERSION ), array( $rec['lang'] ?? '', $rec['job_id'] ?? 0, $rec['consent_ver'] ?? '' ) );
same( 'nhận hồ sơ: thông tin CV', array( 'CV An.pdf', 'application/pdf', filesize( $pdf ) ), array( $rec['cv_name'] ?? '', $rec['cv_mime'] ?? '', $rec['cv_size'] ?? 0 ) );
same( 'nhận hồ sơ: dấu thời gian đồng ý = thời điểm gửi', 1060, $rec['consent_at'] ?? 0 );
same( 'nhận hồ sơ: chỉ lưu IP đã băm', 'iphash', $rec['ip_hash'] ?? '' );
same( 'nhận hồ sơ: lưu đúng một tệp, đúng đuôi', array( 1, 'pdf' ), array( count( $st->saved ), $st->saved[0]['ext'] ?? '' ) );
same( 'nhận hồ sơ: token đã bị tiêu thụ', array( 'tok1' ), $st->consumed );
same( 'nhận hồ sơ: gửi thư một lần cho hồ sơ vừa tạo', array( 101 ), $st->mails );
same( 'nhận hồ sơ: tính một lần thử và một hồ sơ nhận được', array( 'attempt', 'accepted' ), $st->rates );
same( 'nhận hồ sơ: không xóa tệp', array(), $st->deleted );

$r2 = vgarco_apply_process( request(), $files_ok, context(), $deps );
same( 'phát lại: 400 captcha', array( 400, 'validation', 'captcha' ), array( $r2['status'], $r2['body']['code'], $r2['body']['errors']['captcha'] ?? '' ) );
same( 'phát lại: không ghi thêm bản ghi', 1, count( $st->records ) );
check( 'phát lại: có mã bảo mật mới', isset( $r2['body']['captcha']['token'], $r2['body']['captcha']['image'] ) );

[ $st, $deps ] = fake();
$r = vgarco_apply_process( request( array( 'captcha' => ' a B 7 k9 ' ) ), $files_ok, context(), $deps );
same( 'mã bảo mật: hoa thường và khoảng trắng được bỏ qua', 200, $r['status'] );

[ $st, $deps ] = fake();
vgarco_apply_process( request( array( 'lang' => 'fr' ) ), $files_ok, context(), $deps );
same( 'ngôn ngữ ngoài vi/de → vi', 'vi', $st->records[0]['lang'] ?? '' );

$origins = array(
	'GET không được'                     => array( array( 'method' => 'GET' ), 403 ),
	'Origin lạ'                          => array( array( 'origin' => 'https://evil.example' ), 403 ),
	'Origin có tên miền là hậu tố'       => array( array( 'origin' => 'https://vgarco.vn.evil.example' ), 403 ),
	'Origin giấu tên miền trong đường dẫn' => array( array( 'origin' => 'https://evil.example/vgarco.vn' ), 403 ),
	'không có Origin và Referer'         => array( array( 'origin' => '', 'referer' => '' ), 403 ),
	'Referer lạ khi không có Origin'     => array( array( 'origin' => '', 'referer' => 'https://evil.example/apply' ), 403 ),
	'Origin đúng nhưng Referer lạ (Origin quyết định)' => array( array( 'origin' => 'https://vgarco.vn', 'referer' => 'https://evil.example/' ), 200 ),
	'Referer đúng khi không có Origin'   => array( array( 'origin' => '', 'referer' => 'https://vgarco.vn/tuyen-dung/x/ung-tuyen/' ), 200 ),
	'tên miền viết hoa'                  => array( array( 'origin' => 'https://VGARCO.vn' ), 200 ),
	'website không cấu hình tên miền'    => array( array( 'host' => '' ), 403 ),
);
foreach ( $origins as $label => [ $ctx, $status ] ) {
	[ $st, $deps ] = fake();
	$r = vgarco_apply_process( request(), $files_ok, context( $ctx ), $deps );
	same( "nguồn gốc: {$label}", $status, $r['status'] );
	if ( 403 === $status ) {
		same( "nguồn gốc: {$label} → không lưu, không tính tần suất", array( 0, 0, array() ), array( count( $st->records ), count( $st->saved ), $st->rates ) );
	}
}

[ $st, $deps ] = fake( array( 'rate_attempts' => false ) );
$r = vgarco_apply_process( request(), $files_ok, context(), $deps );
same( 'tần suất: quá số lần thử → 429', array( 429, 'rate_limited', 0 ), array( $r['status'], $r['body']['code'], count( $st->records ) ) );
[ $st, $deps ] = fake( array( 'rate_submissions' => false ) );
$r = vgarco_apply_process( request(), $files_ok, context(), $deps );
same( 'tần suất: quá số hồ sơ → 429', array( 429, 0 ), array( $r['status'], count( $st->records ) ) );
same( 'tần suất: yêu cầu bị chặn không tiêu thụ token', array(), $st->consumed );

[ $st, $deps ] = fake();
$r = vgarco_apply_process( request( array( 'website' => 'http://spam.example' ) ), $files_ok, context(), $deps );
same( 'bẫy: trường website có chữ → 400 rejected', array( 400, 'rejected' ), array( $r['status'], $r['body']['code'] ) );
same( 'bẫy: không lưu, không tiêu thụ token, không gợi ý lý do', array( 0, array(), false ), array( count( $st->records ), $st->consumed, isset( $r['body']['errors'] ) || isset( $r['body']['captcha'] ) ) );
same( 'bẫy: vẫn tính một lần thử', array( 'attempt' ), $st->rates );
[ $st, $deps ] = fake();
$r = vgarco_apply_process( request( array( 'website' => '   ' ) ), $files_ok, context(), $deps );
same( 'bẫy: chỉ khoảng trắng không phải bot', 200, $r['status'] );

foreach ( array( 3 => 'rejected', 4 => 'ok', 7200 => 'ok', 7201 => 'rejected' ) as $elapsed => $expect ) {
	[ $st, $deps ] = fake();
	$r = vgarco_apply_process( request(), $files_ok, context( array( 'now' => 1000 + $elapsed ) ), $deps );
	same( "thời gian: điền sau {$elapsed} giây → {$expect}", 'ok' === $expect, 200 === $r['status'] );
	if ( 'rejected' === $expect ) {
		same( "thời gian: {$elapsed} giây → mã rejected, không lưu", array( 400, 'rejected', 0 ), array( $r['status'], $r['body']['code'], count( $st->records ) ) );
	}
}

[ $st, $deps ] = fake();
$r = vgarco_apply_process( request( array( 'captcha' => 'ZZZZZ' ) ), $files_ok, context(), $deps );
same( 'mã bảo mật sai: 400 validation.captcha', array( 400, 'validation', 'captcha' ), array( $r['status'], $r['body']['code'], $r['body']['errors']['captcha'] ?? '' ) );
same( 'mã bảo mật sai: token cũ bị hủy, cấp mã mới', array( array( 'tok1' ), 'new1' ), array( $st->consumed, $r['body']['captcha']['token'] ?? '' ) );
same( 'mã bảo mật sai: không lưu gì', array( 0, 0 ), array( count( $st->records ), count( $st->saved ) ) );
[ $st, $deps ] = fake();
$r = vgarco_apply_process( request( array( 'token' => 'khong-co' ) ), $files_ok, context(), $deps );
same( 'token lạ: 400 captcha và cấp mã mới', array( 400, 'captcha', true ), array( $r['status'], $r['body']['errors']['captcha'] ?? '', isset( $r['body']['captcha']['token'] ) ) );
[ $st, $deps ] = fake();
$r = vgarco_apply_process( request( array( 'token' => '' ) ), $files_ok, context(), $deps );
same( 'thiếu token: 400 captcha', array( 400, 'captcha' ), array( $r['status'], $r['body']['errors']['captcha'] ?? '' ) );
[ $st, $deps ] = fake();
$r = vgarco_apply_process( request( array( 'captcha' => '' ) ), $files_ok, context(), $deps );
same( 'mã bảo mật rỗng: 400 captcha', array( 400, 'captcha' ), array( $r['status'], $r['body']['errors']['captcha'] ?? '' ) );

[ $st, $deps ] = fake( array( 'job_open' => false ) );
$r = vgarco_apply_process( request(), $files_ok, context(), $deps );
same( 'vị trí đã đóng: 409 closed, không lưu', array( 409, 'closed', 0 ), array( $r['status'], $r['body']['code'], count( $st->records ) ) );
[ $st, $deps ] = fake();
$r = vgarco_apply_process( request( array( 'job_id' => '0' ) ), $files_ok, context(), $deps );
same( 'vị trí không xác định: 409', 409, $r['status'] );
[ $st, $deps ] = fake();
$r = vgarco_apply_process( request( array( 'job_id' => '999' ) ), $files_ok, context(), $deps );
same( 'vị trí không tồn tại/không mở: 409', 409, $r['status'] );

[ $st, $deps ] = fake();
$r = vgarco_apply_process( request( array( 'name' => '', 'email' => 'sai', 'consent' => '' ) ), array( 'cv' => upload( $exe, 'virus.pdf' ) ), context(), $deps );
same( 'lỗi trường và tệp: 400 validation', array( 400, 'validation' ), array( $r['status'], $r['body']['code'] ) );
same( 'lỗi trường và tệp: gom mọi lỗi một lượt', array( 'name' => 'required', 'email' => 'email', 'consent' => 'consent', 'cv' => 'file_invalid' ), $r['body']['errors'] ?? array() );
same( 'lỗi trường và tệp: không lưu', array( 0, 0 ), array( count( $st->records ), count( $st->saved ) ) );
same( 'lỗi trường và tệp: KHÔNG tiêu thụ token (không bắt gõ lại mã)', array( array(), true ), array( $st->consumed, isset( $st->tokens['tok1'] ) ) );
check( 'lỗi trường và tệp: không kèm mã bảo mật mới (vẫn dùng mã cũ)', ! isset( $r['body']['captcha'] ) );
[ $st, $deps ] = fake();
$r = vgarco_apply_process( request(), array(), context(), $deps );
same( 'thiếu tệp: cv=file_required', array( 400, 'file_required' ), array( $r['status'], $r['body']['errors']['cv'] ?? '' ) );

[ $st, $deps ] = fake();
vgarco_apply_process( request( array( 'email' => 'sai' ) ), $files_ok, context(), $deps );
$r = vgarco_apply_process( request(), $files_ok, context(), $deps );
same( 'sửa lỗi rồi nộp lại bằng cùng token: 200', 200, $r['status'] );

[ $st, $deps ] = fake( array( 'consume_lost' => true ) );
$r = vgarco_apply_process( request(), $files_ok, context(), $deps );
same( 'hai yêu cầu song song cùng token: bên thua bị từ chối, không lưu', array( 400, 'rejected', 0, 0 ), array( $r['status'], $r['body']['code'], count( $st->records ), count( $st->saved ) ) );

[ $st, $deps ] = fake( array( 'save_fail' => true ) );
$r = vgarco_apply_process( request(), $files_ok, context(), $deps );
same( 'không lưu được tệp: 500 server, không ghi bản ghi, không gửi thư', array( 500, 'server', 0, array() ), array( $r['status'], $r['body']['code'], count( $st->records ), $st->mails ) );
same( 'không lưu được tệp: không tính là hồ sơ nhận được', array( 'attempt' ), $st->rates );

[ $st, $deps ] = fake( array( 'insert_fail' => true ) );
$r = vgarco_apply_process( request(), $files_ok, context(), $deps );
same( 'không ghi được bản ghi: 500 và xóa tệp vừa lưu (không để tệp mồ côi)', array( 500, 1, $st->saved[0]['path'] ?? '' ), array( $r['status'], count( $st->deleted ), $st->deleted[0] ?? '' ) );
same( 'không ghi được bản ghi: không gửi thư', array(), $st->mails );

[ $st, $deps ] = fake( array( 'mail' => fn() => throw new RuntimeException( 'SMTP hỏng' ) ) );
$r = vgarco_apply_process( request(), $files_ok, context(), $deps );
same( 'thư ném lỗi: vẫn 200 và giữ hồ sơ', array( 200, 1, array() ), array( $r['status'], count( $st->records ), $st->deleted ) );
same( 'thư ném lỗi: báo cả hai thư chưa gửi', array( 'hr' => false, 'candidate' => false ), $r['body']['mail'] );
[ $st, $deps ] = fake( array( 'mail' => fn() => array( 'hr' => true, 'candidate' => false ) ) );
$r = vgarco_apply_process( request(), $files_ok, context(), $deps );
same( 'thư ứng viên lỗi: vẫn 200, ghi nhận kết quả từng thư', array( 200, array( 'hr' => true, 'candidate' => false ) ), array( $r['status'], $r['body']['mail'] ) );

$caps = vgarco_recruit_caps();
$cap_names = array_keys( $caps );
sort( $cap_names );
same( 'quyền: đủ 4 quyền riêng của hồ sơ', array( 'vg_delete_applications', 'vg_download_cv', 'vg_export_applications', 'vg_view_applications' ), $cap_names );
check( 'quyền: đều được cấp', ! in_array( false, $caps, true ) );
check( 'quyền: không có quyền chung của Biên tập viên (edit_posts, upload_files)', ! isset( $caps['edit_posts'] ) && ! isset( $caps['upload_files'] ) );

$now = gmmktime( 0, 0, 0, 9, 30, 2026 );
same( 'thời hạn: mốc cắt = trừ 12 tháng theo lịch', gmmktime( 0, 0, 0, 9, 30, 2025 ), vgarco_apply_retention_cutoff( $now ) );
check( 'thời hạn: nộp trước mốc → quá hạn', vgarco_apply_is_expired( gmmktime( 0, 0, 0, 9, 29, 2025 ), $now ) );
check( 'thời hạn: nộp đúng mốc → chưa quá hạn', ! vgarco_apply_is_expired( gmmktime( 0, 0, 0, 9, 30, 2025 ), $now ) );
check( 'thời hạn: nộp hôm qua → chưa quá hạn', ! vgarco_apply_is_expired( $now - 86400, $now ) );
check( 'thời hạn: 11 tháng trước → chưa quá hạn', ! vgarco_apply_is_expired( gmmktime( 0, 0, 0, 10, 15, 2025 ), $now ) );
check( 'thời hạn: 13 tháng trước → quá hạn', vgarco_apply_is_expired( gmmktime( 0, 0, 0, 8, 15, 2025 ), $now ) );
same( 'thời hạn: tham số số tháng', gmmktime( 0, 0, 0, 3, 30, 2026 ), vgarco_apply_retention_cutoff( $now, 6 ) );

check( 'đường dẫn: thư mục con nằm trong', vgarco_path_inside( '/var/www/html/wp-content/uploads', '/var/www/html' ) );
check( 'đường dẫn: chính thư mục gốc', vgarco_path_inside( '/var/www/html/', '/var/www/html' ) );
check( 'đường dẫn: thư mục anh em cùng tiền tố KHÔNG nằm trong', ! vgarco_path_inside( '/var/www/html-private', '/var/www/html' ) );
check( 'đường dẫn: thư mục cha KHÔNG nằm trong', ! vgarco_path_inside( '/var/www', '/var/www/html' ) );
check( 'đường dẫn: nơi lưu riêng tư ngoài web root', ! vgarco_path_inside( '/srv/vgarco/private', '/var/www/html' ) );
check( 'đường dẫn: dấu gạch ngược được chuẩn hóa', vgarco_path_inside( 'C:\\www\\site\\private', 'C:/www/site' ) );
check( 'đường dẫn: gốc rỗng không khớp gì', ! vgarco_path_inside( '/x', '' ) );

$hex = str_repeat( 'a', 32 );
check( 'CV: đường dẫn đúng dạng', vgarco_apply_cv_path_valid( "cv/2026/09/{$hex}" ) );
foreach ( array( "../cv/2026/09/{$hex}", "cv/2026/09/../../etc/{$hex}", "cv/2026/09/{$hex}/x", "cv/2026/09/{$hex}.php", 'cv/2026/09/' . strtoupper( $hex ), 'cv/2026/09/' . substr( $hex, 1 ), "/etc/passwd", '', "cv/26/09/{$hex}", "uploads/2026/09/{$hex}" ) as $bad ) {
	check( 'CV: đường dẫn sai bị từ chối: ' . json_encode( $bad ), ! vgarco_apply_cv_path_valid( $bad ) );
}
same( 'CV: đuôi theo loại', array( 'pdf', 'doc', 'docx', 'bin' ), array(
	vgarco_cv_ext_for_mime( 'application/pdf' ),
	vgarco_cv_ext_for_mime( 'application/msword' ),
	vgarco_cv_ext_for_mime( 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' ),
	vgarco_cv_ext_for_mime( 'text/html' ),
) );

same( 'thư: bỏ xuống dòng và thẻ trong một dòng', 'Nguyen Van An Bcc: x@y.com alert(1)', vgarco_mail_line( "Nguyen\r\nVan\tAn\nBcc: x@y.com <script>alert(1)</script>" ) );
check( 'thư: chống chèn header qua họ tên', ! preg_match( '/[\r\n]/', vgarco_mail_line( "An\r\nBcc: victim@example.org" ) ) );
same( 'thư: thay chỗ điền', 'Chào An, vị trí Kỹ sư', vgarco_apply_fill( 'Chào {ten}, vị trí {vi_tri}', array( 'ten' => 'An', 'vi_tri' => 'Kỹ sư' ) ) );
same( 'thư: chỗ điền lạ được giữ nguyên', 'Chào {khong_co}', vgarco_apply_fill( 'Chào {khong_co}', array( 'ten' => 'An' ) ) );
same( 'thư: giá trị chứa chỗ điền khác không bị thay lần hai', 'Chào {vi_tri} / Kỹ sư', vgarco_apply_fill( 'Chào {ten} / {vi_tri}', array( 'ten' => '{vi_tri}', 'vi_tri' => 'Kỹ sư' ) ) );

same( 'xlsx: tên cột', array( 'A', 'B', 'Z', 'AA', 'AB', 'AZ', 'BA', 'ZZ', 'AAA' ), array_map( 'vgarco_xlsx_col', array( 0, 1, 25, 26, 27, 51, 52, 701, 702 ) ) );

$xlsx_rows = array(
	array( 1, '05/09/2026 09:30', 'Kỹ sư "giám sát" <A&B>', '=HYPERLINK("http://evil.example","bấm")', '0988961666', "Có\x07ký tự\x00điều khiển", 'Dòng 1' . "\n" . 'Dòng 2' ),
	array( 2, '06/09/2026 10:00', '+cmd|calc', '@SUM(A1)', '-1+1', '😀 emoji', str_repeat( 'x', 40000 ) ),
);
$binary = vgarco_xlsx_build( array( 'Mã', 'Nộp lúc', 'Vị trí', 'Họ tên', 'Điện thoại', 'Ghi chú', 'Nhiều dòng' ), $xlsx_rows, array( 8, 18, 30, 30, 16, 20, 20 ) );
check( 'xlsx: bắt đầu bằng chữ ký ZIP', str_starts_with( $binary, 'PK' ) );
$file = make_file( 'export.xlsx', $binary );
$zip  = new ZipArchive();
check( 'xlsx: mở lại được như tệp zip', true === $zip->open( $file ) );
$names = array();
for ( $i = 0; $i < $zip->numFiles; $i++ ) {
	$names[] = $zip->getNameIndex( $i );
}
sort( $names );
same( 'xlsx: đủ các phần bắt buộc của OOXML', array( '[Content_Types].xml', '_rels/.rels', 'xl/_rels/workbook.xml.rels', 'xl/styles.xml', 'xl/workbook.xml', 'xl/worksheets/sheet1.xml' ), $names );
foreach ( $names as $name ) {
	$dom = new DOMDocument();
	check( "xlsx: {$name} là XML đúng cú pháp", @$dom->loadXML( (string) $zip->getFromName( $name ) ) );
}
$sheet = (string) $zip->getFromName( 'xl/worksheets/sheet1.xml' );
$xml   = simplexml_load_string( $sheet );
$xml->registerXPathNamespace( 'm', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main' );
$cell = static function ( string $ref ) use ( $xml ): array {
	$found = $xml->xpath( "//m:c[@r='{$ref}']" );
	if ( ! $found ) {
		return array( null, null );
	}
	$c = $found[0];
	return array( (string) $c['t'], isset( $c->is ) ? (string) $c->is->t : (string) $c->v );
};
same( 'xlsx: tiêu đề cột 1', array( 'inlineStr', 'Mã' ), $cell( 'A1' ) );
same( 'xlsx: tiêu đề tiếng Việt giữ nguyên', array( 'inlineStr', 'Nộp lúc' ), $cell( 'B1' ) );
same( 'xlsx: số nguyên ghi dạng số', array( '', '1' ), $cell( 'A2' ) );
same( 'xlsx: ký tự đặc biệt XML được thoát và đọc lại đúng', array( 'inlineStr', 'Kỹ sư "giám sát" <A&B>' ), $cell( 'C2' ) );
same( 'xlsx: chuỗi bắt đầu bằng = là chữ, không phải công thức', array( 'inlineStr', '=HYPERLINK("http://evil.example","bấm")' ), $cell( 'D2' ) );
same( 'xlsx: chuỗi bắt đầu bằng +/@/- là chữ', array( array( 'inlineStr', '+cmd|calc' ), array( 'inlineStr', '@SUM(A1)' ), array( 'inlineStr', '-1+1' ) ), array( $cell( 'C3' ), $cell( 'D3' ), $cell( 'E3' ) ) );
check( 'xlsx: không có thẻ công thức <f> nào trong bảng', ! str_contains( $sheet, '<f>' ) && ! str_contains( $sheet, '<f ' ) );
same( 'xlsx: số điện thoại giữ số 0 đầu (dạng chuỗi)', array( 'inlineStr', '0988961666' ), $cell( 'E2' ) );
same( 'xlsx: ký tự điều khiển bị bỏ', array( 'inlineStr', 'Cóký tựđiều khiển' ), $cell( 'F2' ) );
same( 'xlsx: emoji giữ nguyên', array( 'inlineStr', '😀 emoji' ), $cell( 'F3' ) );
same( 'xlsx: xuống dòng trong ô được giữ', array( 'inlineStr', "Dòng 1\nDòng 2" ), $cell( 'G2' ) );
same( 'xlsx: ô dài bị cắt ở 32 000 ký tự (giới hạn 32 767 của Excel)', 32000, mb_strlen( $cell( 'G3' )[1] ) );
same( 'xlsx: khung lọc phủ đúng vùng dữ liệu', 'A1:G3', (string) ( $xml->xpath( '//m:autoFilter' )[0]['ref'] ?? '' ) );
same( 'xlsx: cố định dòng tiêu đề', 'frozen', (string) ( $xml->xpath( '//m:pane' )[0]['state'] ?? '' ) );
same( 'xlsx: có độ rộng cột', 7, count( $xml->xpath( '//m:col' ) ) );
same( 'xlsx: số dòng dữ liệu (tiêu đề + 2)', 3, count( $xml->xpath( '//m:row' ) ) );

$empty_xlsx = vgarco_xlsx_build( array( 'A', 'B', 'C' ), array() );
$zip2       = new ZipArchive();
$zip2->open( make_file( 'empty.xlsx', $empty_xlsx ) );
$sheet2 = (string) $zip2->getFromName( 'xl/worksheets/sheet1.xml' );
check( 'xlsx: không có dòng dữ liệu vẫn là tệp hợp lệ, khung lọc chỉ phủ tiêu đề', str_contains( $sheet2, 'ref="A1:C1"' ) && ! str_contains( $sheet2, '<row r="2"' ) );
$sheet_name = static function ( string $name ) use ( $tmp ): string {
	$z = new ZipArchive();
	$z->open( make_file( 'name.xlsx', vgarco_xlsx_build( array( 'A' ), array(), array(), $name ) ) );
	preg_match( '/<sheet name="([^"]*)"/', (string) $z->getFromName( 'xl/workbook.xml' ), $m );
	$z->close();
	return $m[1] ?? '';
};
same( 'xlsx: tên trang tính bị làm sạch ký tự cấm', 'Sheet  1   a b', rtrim( $sheet_name( 'Sheet [1]: a/b*?\\' ) ) );
same( 'xlsx: tên trang tính tối đa 31 ký tự', 31, strlen( $sheet_name( str_repeat( 'a', 50 ) ) ) );

if ( $fail ) {
	foreach ( $fail as $line ) {
		echo "  ✗ {$line}\n";
	}
	echo 'THẤT BẠI ' . count( $fail ) . ' / ' . ( $pass + count( $fail ) ) . "\n";
	exit( 1 );
}
echo "ĐẠT {$pass}/{$pass}\n";
