<?php
/**
 * Kiểm tra dữ liệu ứng tuyển ở MÁY CHỦ (mục 8.3 bước 8–9) — lớp kiểm tra thứ hai sau trình duyệt, và là lớp duy nhất đáng tin.
 * Hàm thuần (không gọi WordPress): kiểm thử được bằng tệp thật.
 *
 * Mã lỗi trả về khớp với data-msg-* của biểu mẫu (templates/apply.php) và modules/apply.js:
 * required, email, phone, file_required, file_type, file_size, file_invalid, consent.
 *
 * @package vgarco-core
 */

defined( 'ABSPATH' ) || exit;

const VGARCO_CV_MAX_BYTES = 10 * 1024 * 1024;

/**
 * Kiểm tra các trường văn bản và ô đồng ý.
 *
 * @param array $in name, email, phone, consent (chuỗi thô từ yêu cầu).
 * @return array<string,string> tên trường => mã lỗi; rỗng nếu hợp lệ.
 */
function vgarco_apply_validate_fields( array $in ): array {
	$errors = array();

	$name = trim( (string) ( $in['name'] ?? '' ) );
	if ( '' === $name ) {
		$errors['name'] = 'required';
	} elseif ( mb_strlen( $name ) > 100 ) {
		$errors['name'] = 'required';
	}

	$email = trim( (string) ( $in['email'] ?? '' ) );
	if ( '' === $email ) {
		$errors['email'] = 'required';
	} elseif ( mb_strlen( $email ) > 200 || false === filter_var( $email, FILTER_VALIDATE_EMAIL ) ) {
		$errors['email'] = 'email';
	}

	$phone = trim( (string) ( $in['phone'] ?? '' ) );
	if ( '' === $phone ) {
		$errors['phone'] = 'required';
	} else {
		$digits = preg_replace( '/\D/', '', $phone ) ?? '';
		// Chỉ cho số, khoảng trắng và các ký tự thường gặp trong số điện thoại; 8–15 chữ số (chuẩn E.164).
		if ( strlen( $digits ) < 8 || strlen( $digits ) > 15 || preg_match( '/[^\d\s+().-]/', $phone ) ) {
			$errors['phone'] = 'phone';
		}
	}

	if ( '1' !== (string) ( $in['consent'] ?? '' ) ) {
		$errors['consent'] = 'consent';
	}

	return $errors;
}

/**
 * Làm sạch tên tệp gốc để lưu và hiển thị: bỏ đường dẫn, ký tự điều khiển, ký tự nguy hiểm; cắt độ dài.
 * Tên gốc CHỈ dùng để hiển thị — tệp trên đĩa luôn có tên ngẫu nhiên.
 */
function vgarco_apply_clean_filename( string $name ): string {
	$name = basename( str_replace( '\\', '/', $name ) );
	$name = preg_replace( '/[\x00-\x1F\x7F<>:"|?*]+/u', '', $name ) ?? '';
	$name = trim( $name, " .\t\n\r" );
	if ( mb_strlen( $name ) > 120 ) {
		$ext  = pathinfo( $name, PATHINFO_EXTENSION );
		$name = mb_substr( pathinfo( $name, PATHINFO_FILENAME ), 0, 110 ) . ( '' !== $ext ? '.' . $ext : '' );
	}
	return '' !== $name ? $name : 'cv';
}

/**
 * Kiểm tra tệp CV: mã lỗi tải lên, dung lượng, đuôi, LOẠI THẬT của nội dung (không tin đuôi hay MIME do trình duyệt gửi),
 * và nội dung nguy hiểm (macro). Chỉ nhận PDF, DOC, DOCX.
 *
 * @param array|null $file Phần tử của $_FILES: name, tmp_name, error, size.
 * @return array{ok: bool, error: string, ext: string, mime: string, name: string, size: int}
 */
function vgarco_apply_validate_cv( ?array $file ): array {
	$fail = static fn( string $code ): array => array( 'ok' => false, 'error' => $code, 'ext' => '', 'mime' => '', 'name' => '', 'size' => 0 );

	if ( ! $file || ( $file['error'] ?? UPLOAD_ERR_NO_FILE ) === UPLOAD_ERR_NO_FILE ) {
		return $fail( 'file_required' );
	}

	$upload_error = (int) ( $file['error'] ?? 0 );
	if ( UPLOAD_ERR_INI_SIZE === $upload_error || UPLOAD_ERR_FORM_SIZE === $upload_error ) {
		return $fail( 'file_size' );
	}
	if ( UPLOAD_ERR_OK !== $upload_error ) {
		return $fail( 'file_invalid' );
	}

	$path = (string) ( $file['tmp_name'] ?? '' );
	$size = is_file( $path ) ? (int) filesize( $path ) : 0;
	if ( $size > VGARCO_CV_MAX_BYTES ) {
		return $fail( 'file_size' );
	}
	if ( $size < 16 ) {
		return $fail( 'file_invalid' ); // rỗng hoặc quá ngắn để là một tài liệu
	}

	$name = vgarco_apply_clean_filename( (string) ( $file['name'] ?? '' ) );
	$ext  = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );
	if ( ! in_array( $ext, array( 'pdf', 'doc', 'docx' ), true ) ) {
		return $fail( 'file_type' );
	}

	$head = (string) file_get_contents( $path, false, null, 0, 8 );

	// Loại thật theo nội dung. PDF: bắt đầu bằng %PDF-. DOC: tệp OLE (D0 CF 11 E0). DOCX: ZIP (PK) có word/document.xml.
	if ( 'pdf' === $ext ) {
		if ( ! str_starts_with( $head, '%PDF-' ) ) {
			return $fail( 'file_invalid' );
		}
		$mime = 'application/pdf';
	} elseif ( 'doc' === $ext ) {
		if ( "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1" !== $head ) {
			return $fail( 'file_invalid' );
		}
		// .doc chứa macro có luồng "_VBA_PROJECT" trong tệp OLE (tên luồng lưu dạng UTF-16LE). Chỉ tìm đúng tên này: các chữ như
		// "VBA" hay "Macros" có thể là kỹ năng ghi trong chính CV, không phải dấu hiệu macro.
		if ( str_contains( (string) file_get_contents( $path ), mb_convert_encoding( '_VBA_PROJECT', 'UTF-16LE', 'UTF-8' ) ) ) {
			return $fail( 'file_invalid' );
		}
		$mime = 'application/msword';
	} else {
		if ( ! str_starts_with( $head, 'PK' ) || ! class_exists( 'ZipArchive' ) ) {
			return $fail( 'file_invalid' );
		}
		$zip = new ZipArchive();
		if ( true !== $zip->open( $path ) ) {
			return $fail( 'file_invalid' );
		}
		$has_document = false !== $zip->locateName( 'word/document.xml' );
		$has_macro    = false;
		for ( $i = 0; $i < $zip->numFiles; $i++ ) {
			$entry = (string) $zip->getNameIndex( $i );
			// Tệp có macro đổi đuôi thành .docx vẫn mang vbaProject.bin (kể cả đặt ở thư mục khác).
			if ( preg_match( '/vbaProject\.bin$/i', $entry ) || str_contains( strtolower( $entry ), 'vbadata' ) ) {
				$has_macro = true;
			}
		}
		$zip->close();
		if ( ! $has_document || $has_macro ) {
			return $fail( 'file_invalid' );
		}
		$mime = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
	}

	// Đối chiếu thêm bằng finfo (nếu có): loại đoán được không được là chương trình chạy được hay trang web.
	if ( function_exists( 'finfo_open' ) ) {
		$finfo    = finfo_open( FILEINFO_MIME_TYPE );
		$detected = $finfo ? (string) finfo_file( $finfo, $path ) : '';
		if ( preg_match( '#^(application/x-(dosexec|executable|msdownload|sharedlib|elf)|text/html|application/x-httpd-php|text/x-php)#', $detected ) ) {
			return $fail( 'file_invalid' );
		}
	}

	return array(
		'ok'    => true,
		'error' => '',
		'ext'   => $ext,
		'mime'  => $mime,
		'name'  => $name,
		'size'  => $size,
	);
}
