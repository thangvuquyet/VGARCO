<?php
/** Luồng nộp hồ sơ ứng tuyển trên WordPress thật: REST, transient, lưu CV, bản ghi, thư, thử lại, giới hạn tần suất. */
require __DIR__ . '/lib.php';

$jobs = get_option( 'lab_job_ids' );
foreach ( get_posts( array( 'post_type' => 'ho_so_ut', 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids' ) ) as $old ) {
	wp_delete_post( $old, true );
}
global $wpdb;
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_vg\\_rate%' OR option_name LIKE '\\_transient\\_timeout\\_vg\\_rate%'" );
delete_option( 'lab_mail_fail' );
delete_option( 'vgarco_alerts' );
lab_clear_mail();

$tmp = LAB . '/tmp-apply';
@mkdir( $tmp, 0777, true );
$pdf = $tmp . '/cv.pdf';
file_put_contents( $pdf, "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF\n" . str_repeat( 'x', 2000 ) );
$records = static function (): array {
	wp_cache_flush(); // máy chủ thử ghi ở tiến trình khác: bỏ bộ nhớ đệm đối tượng của tiến trình này
	return get_posts( array( 'post_type' => 'ho_so_ut', 'post_status' => 'any', 'posts_per_page' => -1, 'orderby' => 'ID', 'order' => 'ASC' ) );
};
$base = static fn( int $job, string $token, array $over = array() ): array => array_merge(
	array( 'job_id' => (string) $job, 'lang' => 'vi', 'token' => $token, 'website' => '', 'name' => 'Nguyễn Văn An', 'email' => 'an.nguyen@example.com', 'phone' => '0988 961 666', 'captcha' => 'ab7k9', 'consent' => '1' ),
	$over
);

/* ---- 1. Cấp mã bảo mật ---- */
$t1 = http( 'GET', '/wp-json/vgarco/v1/apply/token', array( 'headers' => array( 'CF-Connecting-IP: 198.51.100.200' ) ) );
same( 'token: 200', 200, $t1['status'] );
check( 'token: có token 32 hex và ảnh PNG', preg_match( '/^[a-f0-9]{32}$/', (string) ( $t1['json']['token'] ?? '' ) ) && str_starts_with( (string) ( $t1['json']['image'] ?? '' ), 'data:image/png;base64,' ) );
$png = base64_decode( substr( (string) ( $t1['json']['image'] ?? '' ), strlen( 'data:image/png;base64,' ) ) );
$dim = getimagesizefromstring( $png );
same( 'token: ảnh PNG 180x60 hợp lệ do GD của máy chủ vẽ', array( 180, 60, IMAGETYPE_PNG ), $dim ? array( $dim[0], $dim[1], $dim[2] ) : null );
check( 'token: không cache (Cache-Control no-store)', (bool) preg_match( '/cache-control:[^\r\n]*no-store/i', $t1['headers'] ) );
$stored = get_transient( 'vg_apply_' . ( $t1['json']['token'] ?? 'x' ) );
check( 'token: kho chỉ lưu băm của mã (64 hex), không lưu mã', is_array( $stored ) && 1 === preg_match( '/^[a-f0-9]{64}$/', (string) $stored['hash'] ) );
$t2 = http( 'GET', '/wp-json/vgarco/v1/apply/token', array( 'headers' => array( 'CF-Connecting-IP: 198.51.100.200' ) ) );
check( 'token: mỗi lần một token và ảnh khác', ( $t2['json']['token'] ?? '' ) !== ( $t1['json']['token'] ?? '' ) && ( $t2['json']['image'] ?? '' ) !== ( $t1['json']['image'] ?? '' ) );
$wp_ttl = (int) get_option( '_transient_timeout_vg_apply_' . $t1['json']['token'] ) - time();
check( 'token: sống khoảng 10 phút', $wp_ttl > 540 && $wp_ttl <= 600 );

/* ---- 2. Nộp hồ sơ hợp lệ ---- */
$ip = 'CF-Connecting-IP: 198.51.100.50';
$r  = lab_apply( $base( $jobs['kts'], lab_token() ), $pdf, 'CV An.pdf', array( $ip ) );
same( 'nộp: 200 ok', array( 200, true ), array( $r['status'], $r['json']['ok'] ?? null ) );
$recs = $records();
same( 'nộp: có đúng 1 bản ghi ho_so_ut', 1, count( $recs ) );
$rec = $recs[0] ?? null;
$id  = $rec ? $rec->ID : 0;
same( 'bản ghi: họ tên, email, điện thoại, vị trí, ngôn ngữ', array( 'Nguyễn Văn An', 'an.nguyen@example.com', '0988 961 666', $jobs['kts'], 'vi' ), array( get_post_meta( $id, 'ho_ten', true ), get_post_meta( $id, 'email', true ), get_post_meta( $id, 'dien_thoai', true ), (int) get_post_meta( $id, 'vi_tri_id', true ), get_post_meta( $id, 'ngon_ngu', true ) ) );
same( 'bản ghi: tên bài chứa họ tên và vị trí', 'Nguyễn Văn An — Kiến trúc sư thiết kế', $rec->post_title ?? '' );
check( 'bản ghi: IP lưu dạng băm (không phải IP gốc)', 1 === preg_match( '/^[a-f0-9]{64}$/', (string) get_post_meta( $id, 'ip_bam', true ) ) );
same( 'bản ghi: phiên bản đồng ý và loại tệp', array( '2026-09', 'application/pdf', 'CV An.pdf' ), array( get_post_meta( $id, 'dong_y_phien_ban', true ), get_post_meta( $id, 'cv_mime', true ), get_post_meta( $id, 'cv_ten_goc', true ) ) );
$rel  = (string) get_post_meta( $id, 'cv_duong_dan', true );
$path = vgarco_apply_cv_file( $rel );
check( 'CV: đường dẫn lưu dạng cv/YYYY/MM/<32 hex>, không đuôi', 1 === preg_match( '#^cv/\d{4}/\d{2}/[a-f0-9]{32}$#', $rel ) );
check( 'CV: tệp có thật và đúng nội dung đã tải lên', '' !== $path && is_file( $path ) && sha1_file( $path ) === sha1_file( $pdf ) );
check( 'CV: nằm NGOÀI thư mục web (không dưới ABSPATH)', ! vgarco_path_inside( $path, untrailingslashit( ABSPATH ) ) );
same( 'CV: không truy cập được qua web (đoán đường dẫn)', 404, http( 'GET', '/' . $rel )['status'] );
$mails = lab_mails();
same( 'thư: gửi 2 thư (nhân sự + ứng viên)', 2, count( $mails ) );
$hr = $mails[0] ?? array();
$ca = $mails[1] ?? array();
same( 'thư nhân sự: gửi tới hộp thư tuyển dụng (mặc định hộp chung)', 'vgarco.cc@gmail.com', $hr['to'] ?? '' );
check( 'thư nhân sự: tiêu đề có vị trí và họ tên; KHÔNG đính kèm CV; Reply-To là ứng viên', str_contains( (string) $hr['subject'], 'Kiến trúc sư thiết kế' ) && str_contains( (string) $hr['subject'], 'Nguyễn Văn An' ) && array() === (array) ( $hr['attach'] ?? array() ) && (bool) preg_grep( '/^Reply-To: an\.nguyen@example\.com$/', (array) $hr['headers'] ) );
check( 'thư nhân sự: có liên kết xem hồ sơ trong trang quản trị, không có đường dẫn CV', str_contains( (string) $hr['message'], 'localhost:8080/wp-admin/post.php?post=' . $id ) && ! str_contains( (string) $hr['message'], $rel ) );
same( 'thư ứng viên: gửi tới email ứng viên', 'an.nguyen@example.com', $ca['to'] ?? '' );
check( 'thư ứng viên: chào đúng tên, nêu vị trí', str_contains( (string) $ca['message'], 'Chào Nguyễn Văn An' ) && str_contains( (string) $ca['message'], 'Kiến trúc sư thiết kế' ) );
same( 'trạng thái thư: cả hai đã gửi', array( 'da_gui', 'da_gui' ), array( get_post_meta( $id, 'thu_hr', true ), get_post_meta( $id, 'thu_ung_vien', true ) ) );

/* ---- 3. Dùng lại token, mã sai ---- */
$old_token = $base( $jobs['kts'], 'x' )['token'];
$tk        = lab_token();
$ok        = lab_apply( $base( $jobs['kts'], $tk, array( 'name' => 'Người Thứ Hai' ) ), $pdf, 'cv.pdf', array( 'CF-Connecting-IP: 198.51.100.51' ) );
same( 'token dùng một lần: lần đầu 200', 200, $ok['status'] );
$again = lab_apply( $base( $jobs['kts'], $tk, array( 'name' => 'Người Thứ Hai' ) ), $pdf, 'cv.pdf', array( 'CF-Connecting-IP: 198.51.100.51' ) );
same( 'token dùng một lần: gửi lại cùng token → 400 captcha + mã mới', array( 400, 'captcha', true ), array( $again['status'], $again['json']['errors']['captcha'] ?? '', isset( $again['json']['captcha']['image'] ) ) );
same( 'token dùng lại không tạo bản ghi thứ hai', 2, count( $records() ) );
$wrong = lab_apply( $base( $jobs['kts'], lab_token(), array( 'captcha' => 'ZZZZZ' ) ), $pdf, 'cv.pdf', array( 'CF-Connecting-IP: 198.51.100.52' ) );
same( 'mã sai: 400 captcha', array( 400, 'captcha' ), array( $wrong['status'], $wrong['json']['errors']['captcha'] ?? '' ) );

/* ---- 4. Nguồn gốc, bẫy, thời gian ---- */
$h = array( 'CF-Connecting-IP: 198.51.100.53' );
$foreign = http( 'POST', '/wp-json/vgarco/v1/apply', array( 'body' => array_merge( $base( $jobs['kts'], lab_token() ), array( 'cv' => new CURLFile( $pdf, 'application/pdf', 'cv.pdf' ) ) ), 'headers' => array( 'Origin: https://evil.example', 'CF-Connecting-IP: 198.51.100.53' ) ) );
same( 'Origin lạ: 403', 403, $foreign['status'] );
$no_origin = http( 'POST', '/wp-json/vgarco/v1/apply', array( 'body' => array_merge( $base( $jobs['kts'], lab_token() ), array( 'cv' => new CURLFile( $pdf, 'application/pdf', 'cv.pdf' ) ) ), 'headers' => array( 'CF-Connecting-IP: 198.51.100.53' ) ) );
same( 'không Origin, không Referer: 403', 403, $no_origin['status'] );
$ref = http( 'POST', '/wp-json/vgarco/v1/apply', array( 'body' => array_merge( $base( $jobs['kts'], lab_token(), array( 'name' => 'Qua Referer' ) ), array( 'cv' => new CURLFile( $pdf, 'application/pdf', 'cv.pdf' ) ) ), 'headers' => array( 'Referer: http://localhost:8080/ung-tuyen/', 'CF-Connecting-IP: 198.51.100.54' ) ) );
same( 'Referer đúng khi không có Origin: 200', 200, $ref['status'] );
$bot = lab_apply( $base( $jobs['kts'], lab_token(), array( 'website' => 'http://spam.example' ) ), $pdf, 'cv.pdf', $h );
same( 'trường bẫy có chữ: 400 rejected, không gợi ý lý do', array( 400, 'rejected', false ), array( $bot['status'], $bot['json']['code'] ?? '', isset( $bot['json']['errors'] ) ) );
$fast = lab_apply( $base( $jobs['kts'], lab_token( 'AB7K9', 1 ) ), $pdf, 'cv.pdf', $h );
same( 'điền quá nhanh (1 giây): 400 rejected', array( 400, 'rejected' ), array( $fast['status'], $fast['json']['code'] ?? '' ) );
$before = count( $records() );

/* ---- 5. Vị trí ---- */
foreach ( array( 'dong' => 'đã đóng', 'cu' => 'quá hạn' ) as $k => $why ) {
	$c = lab_apply( $base( $jobs[ $k ], lab_token() ), $pdf, 'cv.pdf', $h );
	same( "vị trí {$why}: 409 closed", array( 409, 'closed' ), array( $c['status'], $c['json']['code'] ?? '' ) );
}
$n = lab_apply( $base( 999999, lab_token() ), $pdf, 'cv.pdf', $h );
same( 'vị trí không tồn tại: 409', 409, $n['status'] );
$gone = lab_apply( $base( (int) get_option( 'page_on_front' ), lab_token() ), $pdf, 'cv.pdf', $h );
same( 'ID không phải vị trí tuyển dụng (một trang): 409', 409, $gone['status'] );

/* ---- 6. Trường sai ---- */
$e = lab_apply( $base( $jobs['kts'], $tk2 = lab_token(), array( 'name' => '', 'email' => 'sai', 'phone' => 'abc', 'consent' => '' ) ), $pdf, 'cv.pdf', $h );
same( 'trường sai: 400, đủ 4 lỗi', array( 400, array( 'name' => 'required', 'email' => 'email', 'phone' => 'phone', 'consent' => 'consent' ) ), array( $e['status'], $e['json']['errors'] ?? array() ) );
wp_cache_flush();
check( 'trường sai: token chưa bị tiêu thụ (sửa xong dùng lại được)', is_array( get_transient( 'vg_apply_' . $tk2 ) ) );
$fixed = lab_apply( $base( $jobs['kts'], $tk2, array( 'name' => 'Đã Sửa Lỗi' ) ), $pdf, 'cv.pdf', $h );
same( 'sửa lỗi rồi nộp lại bằng cùng token: 200', 200, $fixed['status'] );
$before = count( $records() );

/* ---- 7. Tệp xấu (tệp thật) ---- */
$files = array();
$files['exe_doi_duoi'] = array( 'virus.pdf', "MZ\x90\x00\x03\x00\x00\x00" . str_repeat( "\0", 300 ) . 'This program cannot be run in DOS mode.', 'file_invalid' );
$files['html_pdf']     = array( 'page.pdf', '<html><script>alert(1)</script></html>' . str_repeat( ' ', 40 ), 'file_invalid' );
$files['txt']          = array( 'ghi-chu.txt', 'chỉ là văn bản ' . str_repeat( 'x', 40 ), 'file_type' );
$files['exe']          = array( 'setup.exe', "MZ\x90\x00" . str_repeat( "\0", 100 ), 'file_type' );
$files['rong']         = array( 'rong.pdf', '', 'file_invalid' );
foreach ( $files as $label => [ $name, $content, $code ] ) {
	$f = $tmp . '/bad-' . $label;
	file_put_contents( $f, $content );
	$r = lab_apply( $base( $jobs['kts'], lab_token() ), $f, $name, $h );
	same( "tệp xấu ({$label}): 400 và mã {$code}", array( 400, $code ), array( $r['status'], $r['json']['errors']['cv'] ?? '' ) );
}
$zip = new ZipArchive();
$zip->open( $tmp . '/macro.docx', ZipArchive::CREATE | ZipArchive::OVERWRITE );
$zip->addFromString( '[Content_Types].xml', '<Types/>' );
$zip->addFromString( 'word/document.xml', '<w:document/>' );
$zip->addFromString( 'word/vbaProject.bin', 'x' );
$zip->close();
$r = lab_apply( $base( $jobs['kts'], lab_token() ), $tmp . '/macro.docx', 'macro.docx', $h );
same( 'tệp xấu (DOCX có macro): 400 file_invalid', array( 400, 'file_invalid' ), array( $r['status'], $r['json']['errors']['cv'] ?? '' ) );
$zip = new ZipArchive();
$zip->open( $tmp . '/ok.docx', ZipArchive::CREATE | ZipArchive::OVERWRITE );
$zip->addFromString( '[Content_Types].xml', '<Types/>' );
$zip->addFromString( 'word/document.xml', '<w:document/>' );
$zip->close();
$r = lab_apply( $base( $jobs['kts'], lab_token(), array( 'name' => 'Nộp DOCX' ) ), $tmp . '/ok.docx', 'CV.docx', array( 'CF-Connecting-IP: 198.51.100.60' ) );
same( 'DOCX hợp lệ: 200', 200, $r['status'] );
$big = $tmp . '/big.pdf';
$fh  = fopen( $big, 'w' );
fwrite( $fh, "%PDF-1.4\n" );
ftruncate( $fh, 11 * 1024 * 1024 );
fclose( $fh );
$r = lab_apply( $base( $jobs['kts'], lab_token() ), $big, 'big.pdf', $h );
same( 'tệp 11 MB: 400 file_size', array( 400, 'file_size' ), array( $r['status'], $r['json']['errors']['cv'] ?? '' ) );
$r = lab_apply( $base( $jobs['kts'], lab_token() ), null, '', $h );
same( 'không gửi tệp: 400 file_required', array( 400, 'file_required' ), array( $r['status'], $r['json']['errors']['cv'] ?? '' ) );
same( 'tệp xấu không tạo bản ghi nào (chỉ DOCX hợp lệ thêm 1)', $before + 1, count( $records() ) );
wp_cache_flush();
$stray = array();
foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( LAB . '/private', FilesystemIterator::SKIP_DOTS ) ) as $f ) {
	$stray[] = $f->getPathname();
}
same( 'không để lại tệp mồ côi: số tệp CV = số bản ghi', count( $records() ), count( $stray ) );

/* ---- 8. Tiếng Đức ---- */
lab_clear_mail();
$de = lab_apply( $base( $jobs['kts'], lab_token(), array( 'lang' => 'de', 'name' => 'Hans Müller', 'email' => 'hans@example.de' ) ), $pdf, 'Lebenslauf.pdf', array( 'CF-Connecting-IP: 198.51.100.61' ) );
same( 'nộp bằng tiếng Đức: 200', 200, $de['status'] );
$mails = lab_mails();
wp_cache_flush();
$cand  = array_values( array_filter( $mails, static fn( $m ) => 'hans@example.de' === $m['to'] ) )[0] ?? array();
check( 'thư xác nhận gửi ứng viên Đức bằng TIẾNG ĐỨC', str_contains( (string) ( $cand['message'] ?? '' ), 'Guten Tag Hans Müller' ) && str_contains( (string) ( $cand['subject'] ?? '' ), 'Bewerbung erhalten' ) );
$hr_de = array_values( array_filter( $mails, static fn( $m ) => 'vgarco.cc@gmail.com' === $m['to'] ) )[0] ?? array();
check( 'thư báo nhân sự vẫn bằng tiếng Việt', str_contains( (string) ( $hr_de['message'] ?? '' ), 'Có hồ sơ ứng tuyển mới' ) );

/* ---- 9. Lỗi thư: hồ sơ vẫn lưu, thử lại ---- */
update_option( 'lab_mail_fail', 1 );
lab_clear_mail();
$mf = lab_apply( $base( $jobs['kts'], lab_token(), array( 'name' => 'Thư Hỏng', 'email' => 'hong@example.com' ) ), $pdf, 'cv.pdf', array( 'CF-Connecting-IP: 198.51.100.62' ) );
same( 'SMTP hỏng: vẫn 200 và hồ sơ được lưu', array( 200, true ), array( $mf['status'], (bool) ( $mf['json']['ok'] ?? false ) ) );
wp_cache_flush();
$bad = get_posts( array( 'post_type' => 'ho_so_ut', 'post_status' => 'any', 'meta_key' => 'ho_ten', 'meta_value' => 'Thư Hỏng', 'posts_per_page' => 1 ) )[0] ?? null;
same( 'SMTP hỏng: trạng thái thư = loi', array( 'loi', 'loi' ), array( get_post_meta( $bad->ID, 'thu_hr', true ), get_post_meta( $bad->ID, 'thu_ung_vien', true ) ) );
check( 'SMTP hỏng: đã đặt lịch thử lại hằng giờ', false !== wp_next_scheduled( 'vgarco_apply_mail_retry' ) );
wp_cache_flush();
check( 'SMTP hỏng: có cảnh báo cho quản trị', (bool) get_option( 'vgarco_alerts' ) );
update_option( 'lab_mail_fail', 0 );
lab_clear_mail();
vgarco_apply_retry_mails();
same( 'thử lại thành công: cả hai thư đã gửi', array( 'da_gui', 'da_gui' ), array( get_post_meta( $bad->ID, 'thu_hr', true ), get_post_meta( $bad->ID, 'thu_ung_vien', true ) ) );
same( 'thử lại: số lần thử được ghi', 1, (int) get_post_meta( $bad->ID, 'thu_thu_lai', true ) );
$resent = lab_mails();
same( 'thử lại: gửi đúng 2 thư (không gửi lại hồ sơ khác)', 2, count( $resent ) );
lab_clear_mail();
vgarco_apply_retry_mails();
same( 'chạy thử lại lần nữa khi mọi thư đã gửi: không gửi thêm', 0, count( lab_mails() ) );

/* ---- 10. Một thư lỗi, một thư đã gửi: không gửi lại thư đã gửi ---- */
update_post_meta( $bad->ID, 'thu_hr', 'da_gui' );
update_post_meta( $bad->ID, 'thu_ung_vien', 'loi' );
update_post_meta( $bad->ID, 'thu_thu_lai', 0 );
lab_clear_mail();
vgarco_apply_retry_mails();
$partial = lab_mails();
same( 'thư HR đã gửi, thư ứng viên lỗi: chỉ gửi lại thư ứng viên', array( 1, 'hong@example.com' ), array( count( $partial ), $partial[0]['to'] ?? '' ) );
update_post_meta( $bad->ID, 'thu_ung_vien', 'loi' );
update_post_meta( $bad->ID, 'thu_thu_lai', 3 );
lab_clear_mail();
vgarco_apply_retry_mails();
same( 'đã thử 3 lần: ngừng thử', 0, count( lab_mails() ) );

/* ---- 11. Giới hạn tần suất: 5 hồ sơ/giờ theo IP ---- */
$rl = 'CF-Connecting-IP: 198.51.100.99';
$st = array();
for ( $i = 1; $i <= 6; $i++ ) {
	$st[] = lab_apply( $base( $jobs['kts'], lab_token(), array( 'name' => "Giới Hạn {$i}", 'email' => "gh{$i}@example.com" ) ), $pdf, 'cv.pdf', array( $rl ) )['status'];
}
same( 'giới hạn: 5 hồ sơ đầu 200, hồ sơ thứ 6 từ cùng IP → 429', array( 200, 200, 200, 200, 200, 429 ), $st );
$other = lab_apply( $base( $jobs['kts'], lab_token(), array( 'name' => 'IP Khác' ) ), $pdf, 'cv.pdf', array( 'CF-Connecting-IP: 198.51.100.98' ) );
same( 'giới hạn: IP khác không bị ảnh hưởng', 200, $other['status'] );

/* ---- 12. Chặn khai thác ---- */
same( 'GET vào đường nộp hồ sơ không phải 200', true, 200 !== http( 'GET', '/wp-json/vgarco/v1/apply' )['status'] );
same( 'kiểu hồ sơ ứng tuyển không lộ qua REST mặc định', true, in_array( http( 'GET', '/wp-json/wp/v2/ho_so_ut' )['status'], array( 404, 401, 403 ), true ) );
$sitemap = http( 'GET', '/wp-sitemap.xml' );
check( 'sơ đồ trang không liệt kê hồ sơ ứng tuyển', 200 === $sitemap['status'] && ! str_contains( $sitemap['body'], 'ho_so_ut' ) && ! str_contains( $sitemap['body'], 'users' ) );

finish();
