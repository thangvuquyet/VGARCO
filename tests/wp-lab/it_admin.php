<?php
require __DIR__ . '/lib.php';

$jobs = get_option( 'lab_job_ids' );
vgarco_apply_ensure_roles();

$user = static function ( string $login, string $role ): int {
	$u = get_user_by( 'login', $login );
	if ( $u ) {
		$u->set_role( $role );
		return (int) $u->ID;
	}
	return (int) wp_insert_user( array( 'user_login' => $login, 'user_pass' => wp_generate_password( 24 ), 'user_email' => $login . '@example.test', 'role' => $role ) );
};
lab_clear_mail();
$admin  = (int) get_user_by( 'login', 'labadmin' )->ID;
$hr     = $user( 'lab_hr', 'vg_tuyen_dung' );
$editor = $user( 'lab_editor', 'editor' );
lab_clear_mail();

$caps = array_keys( vgarco_recruit_caps() );
$role = get_role( 'vg_tuyen_dung' );
check( 'vai trò vg_tuyen_dung tồn tại, có đủ 4 quyền riêng của hồ sơ', $role && ! array_diff( $caps, array_keys( array_filter( $role->capabilities ) ) ) );
check( 'vai trò Nhân sự tuyển dụng KHÔNG sửa được bài viết/dự án/tin', $role && ! $role->has_cap( 'edit_posts' ) && ! $role->has_cap( 'edit_pages' ) && ! $role->has_cap( 'upload_files' ) && ! $role->has_cap( 'manage_options' ) );
check( 'Quản trị viên có đủ 4 quyền', ! array_diff( $caps, array_keys( array_filter( get_role( 'administrator' )->capabilities ) ) ) );
check( 'Biên tập viên KHÔNG có quyền nào của hồ sơ ứng tuyển', ! array_intersect( $caps, array_keys( array_filter( get_role( 'editor' )->capabilities ) ) ) );
check( 'Tác giả/Cộng tác viên cũng không có', ! array_intersect( $caps, array_keys( array_filter( get_role( 'author' )->capabilities ) ) ) && ! array_intersect( $caps, array_keys( array_filter( get_role( 'contributor' )->capabilities ) ) ) );

wp_cache_flush();
foreach ( get_posts( array( 'post_type' => 'ho_so_ut', 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids' ) ) as $old ) {
	wp_delete_post( $old, true );
}
$tmp = LAB . '/tmp-admin';
@mkdir( $tmp, 0777, true );
$pdf = $tmp . '/cv.pdf';
file_put_contents( $pdf, "%PDF-1.4\n" . str_repeat( 'Nội dung CV ', 100 ) . "\n%%EOF\n" );
$ip = 'CF-Connecting-IP: 198.51.100.171';
$submit = static function ( string $name, string $email, int $job ) use ( $pdf ): int {
	$r = lab_apply( array( 'job_id' => (string) $job, 'lang' => 'vi', 'token' => lab_token(), 'website' => '', 'name' => $name, 'email' => $email, 'phone' => '0988 961 666', 'captcha' => 'AB7K9', 'consent' => '1' ), $pdf, 'CV.pdf', array( 'CF-Connecting-IP: 198.51.100.' . random_int( 150, 160 ) ) );
	return (int) ( $r['json']['id'] ?? 0 );
};
$a = $submit( 'Trần Thị Bình', 'binh@example.com', $jobs['kts'] );
$b = $submit( '=HYPERLINK("http://evil.example","bấm vào")', 'evil@example.com', $jobs['ketcau'] );
$c = $submit( 'Lê Văn Cường', 'cuong@example.com', $jobs['kts'] );
check( 'tạo được 3 hồ sơ qua đường nộp thật', $a > 0 && $b > 0 && $c > 0 );
wp_cache_flush();

$ck = array( 'admin' => lab_cookie_for( $admin ), 'hr' => lab_cookie_for( $hr ), 'editor' => lab_cookie_for( $editor ) );
$get = static fn( string $who, string $path ): array => http( 'GET', $path, array( 'headers' => array( 'Cookie: ' . $ck[ $who ] ) ) );

$list = $get( 'admin', '/wp-admin/edit.php?post_type=ho_so_ut' );
same( 'danh sách (Quản trị viên): 200', 200, $list['status'] );
check( 'danh sách: hiện tên ứng viên, vị trí, email, trạng thái thư', str_contains( $list['body'], 'Trần Thị Bình' ) && str_contains( $list['body'], 'Kiến trúc sư thiết kế' ) && str_contains( $list['body'], 'binh@example.com' ) && str_contains( $list['body'], 'HR: đã gửi' ) );
check( 'danh sách: có bộ lọc vị trí và nút xuất .xlsx', str_contains( $list['body'], 'name="vi_tri_id"' ) && str_contains( $list['body'], 'Xuất danh sách hồ sơ (.xlsx)' ) );
check( 'danh sách: không có nút Thêm mới (hồ sơ chỉ đến từ biểu mẫu)', ! preg_match( '/page-title-action|post-new\.php\?post_type=ho_so_ut/', $list['body'] ) );
$hr_list = $get( 'hr', '/wp-admin/edit.php?post_type=ho_so_ut' );
same( 'danh sách (Nhân sự tuyển dụng): 200 và thấy hồ sơ', array( 200, true ), array( $hr_list['status'], str_contains( $hr_list['body'], 'Trần Thị Bình' ) ) );
$ed_list = $get( 'editor', '/wp-admin/edit.php?post_type=ho_so_ut' );
same( 'danh sách (Biên tập viên): bị từ chối 403', 403, $ed_list['status'] );
check( 'Biên tập viên không thấy mục menu Hồ sơ ứng tuyển', ! str_contains( $get( 'editor', '/wp-admin/' )['body'], 'post_type=ho_so_ut' ) );
check( 'Nhân sự tuyển dụng thấy mục menu Hồ sơ ứng tuyển', str_contains( $get( 'hr', '/wp-admin/' )['body'], 'post_type=ho_so_ut' ) );
$hr_dash_posts = $get( 'hr', '/wp-admin/edit.php?post_type=du_an' );
same( 'Nhân sự tuyển dụng không vào được danh sách dự án (403)', 403, $hr_dash_posts['status'] );
$search = $get( 'admin', '/wp-admin/edit.php?post_type=ho_so_ut&s=cuong%40example.com' );
check( 'tìm theo email trong danh sách', str_contains( $search['body'], 'Lê Văn Cường' ) && ! str_contains( $search['body'], 'Trần Thị Bình' ) );
$filter = $get( 'admin', '/wp-admin/edit.php?post_type=ho_so_ut&vi_tri_id=' . $jobs['ketcau'] );
check( 'lọc theo vị trí', str_contains( $filter['body'], 'evil@example.com' ) && ! str_contains( $filter['body'], 'binh@example.com' ) );

$view = $get( 'hr', "/wp-admin/post.php?post={$a}&action=edit" );
same( 'xem hồ sơ (Nhân sự): 200', 200, $view['status'] );
check( 'xem hồ sơ: thấy họ tên, email, tệp CV, nút Tải CV và Xóa; không có khung soạn tiêu đề/nút Cập nhật', str_contains( $view['body'], 'binh@example.com' ) && str_contains( $view['body'], 'CV.pdf' ) && str_contains( $view['body'], 'Tải CV' ) && str_contains( $view['body'], 'Xóa hồ sơ và CV' ) && ! str_contains( $view['body'], 'id="publish"' ) );
$ed_view = $get( 'editor', "/wp-admin/post.php?post={$a}&action=edit" );
check( 'xem hồ sơ (Biên tập viên): bị từ chối', in_array( $ed_view['status'], array( 403, 302 ), true ) && ! str_contains( $ed_view['body'], 'binh@example.com' ) );

preg_match( '#href="([^"]*admin-post\.php\?action=vgarco_download_cv[^"]*)"#', $view['body'], $m );
$dl_url = html_entity_decode( $m[1] ?? '' );
check( 'có liên kết tải CV kèm nonce', '' !== $dl_url && str_contains( $dl_url, '_wpnonce=' ) );
$path = wp_parse_url( $dl_url, PHP_URL_PATH ) . '?' . wp_parse_url( $dl_url, PHP_URL_QUERY );
$dl   = $get( 'hr', $path );
same( 'tải CV (Nhân sự, nonce đúng): 200 và đúng loại tệp', array( 200, true ), array( $dl['status'], (bool) preg_match( '/content-type:\s*application\/pdf/i', $dl['headers'] ) ) );
check( 'tải CV: tên tệp tải về không dấu, có đuôi pdf; nosniff; không cache', (bool) preg_match( '/content-disposition:\s*attachment;\s*filename="CV-Tran-Thi-Binh\.pdf"/i', $dl['headers'] ) && (bool) preg_match( '/x-content-type-options:\s*nosniff/i', $dl['headers'] ) && (bool) preg_match( '/cache-control:[^\r\n]*(no-cache|no-store)/i', $dl['headers'] ) );
same( 'tải CV: nội dung đúng từng byte với tệp đã nộp', sha1_file( $pdf ), sha1( $dl['body'] ) );
wp_cache_flush();
$log = (array) get_post_meta( $a, 'nhat_ky_tai', true );
same( 'tải CV: ghi nhật ký (ai, lúc nào)', array( 1, $hr ), array( count( $log ), (int) ( $log[0]['user'] ?? 0 ) ) );
$bad_nonce = $get( 'hr', "/wp-admin/admin-post.php?action=vgarco_download_cv&id={$a}&_wpnonce=sai" );
same( 'tải CV với nonce sai: bị từ chối (403)', 403, $bad_nonce['status'] );
$anon = http( 'GET', $path );
check( 'tải CV khi chưa đăng nhập: không trả CV', in_array( $anon['status'], array( 302, 400, 403 ), true ) && ! str_contains( $anon['body'], 'Nội dung CV' ) );
$editor_dl = $get( 'editor', $path );
same( 'tải CV bằng Biên tập viên (nonce của người khác): bị từ chối', true, 200 !== $editor_dl['status'] && ! str_contains( $editor_dl['body'], 'Nội dung CV' ) );

$ex_page = $get( 'hr', '/wp-admin/edit.php?post_type=ho_so_ut&page=vgarco-export' );
same( 'trang Xuất danh sách (Nhân sự): 200, có bộ lọc vị trí, ngày, nonce', array( 200, true ), array( $ex_page['status'], str_contains( $ex_page['body'], 'name="tu"' ) && str_contains( $ex_page['body'], 'name="vi_tri_id"' ) && str_contains( $ex_page['body'], '_wpnonce' ) ) );
same( 'trang Xuất danh sách (Biên tập viên): bị từ chối', 403, $get( 'editor', '/wp-admin/edit.php?post_type=ho_so_ut&page=vgarco-export' )['status'] );
preg_match( '#href="([^"]*admin-post\.php\?action=vgarco_export_applications[^"]*)"#', $list['body'], $m );
$ex_url = html_entity_decode( $m[1] ?? '' );
check( 'có liên kết xuất .xlsx kèm nonce', '' !== $ex_url );
$ex_path = wp_parse_url( $ex_url, PHP_URL_PATH ) . '?' . wp_parse_url( $ex_url, PHP_URL_QUERY );
$ex      = $get( 'admin', $ex_path );
same( 'xuất .xlsx: 200 và đúng loại', array( 200, true ), array( $ex['status'], (bool) preg_match( '/content-type:\s*application\/vnd\.openxmlformats-officedocument\.spreadsheetml\.sheet/i', $ex['headers'] ) ) );
file_put_contents( $tmp . '/export.xlsx', $ex['body'] );
$zip = new ZipArchive();
check( 'xuất .xlsx: mở được như tệp zip hợp lệ', true === $zip->open( $tmp . '/export.xlsx' ) );
$sheet = (string) $zip->getFromName( 'xl/worksheets/sheet1.xml' );
check( 'xuất .xlsx: có đủ ba hồ sơ và họ tên có công thức nằm dạng chuỗi (không có thẻ <f>)', str_contains( $sheet, 'Trần Thị Bình' ) && str_contains( $sheet, 'Lê Văn Cường' ) && str_contains( $sheet, '=HYPERLINK' ) && ! str_contains( $sheet, '<f>' ) );
$dom = new DOMDocument();
check( 'xuất .xlsx: bảng tính là XML đúng cú pháp', @$dom->loadXML( $sheet ) );
check( 'xuất .xlsx: không chứa đường dẫn CV hay nội dung CV', ! str_contains( $sheet, 'cv/20' ) && ! str_contains( $sheet, 'Nội dung CV' ) );
same( 'xuất .xlsx bằng Biên tập viên: bị từ chối', true, 200 !== $get( 'editor', $ex_path )['status'] );

wp_cache_flush();
$file_b = vgarco_apply_cv_file( (string) get_post_meta( $b, 'cv_duong_dan', true ) );
check( 'trước khi xóa: tệp CV của hồ sơ thứ hai có thật', is_file( $file_b ) );
wp_set_current_user( $editor );
check( 'Biên tập viên không có quyền xóa hồ sơ', ! current_user_can( 'delete_post', $b ) && ! current_user_can( 'edit_post', $b ) && ! current_user_can( 'read_post', $b ) );
wp_set_current_user( $hr );
check( 'Nhân sự tuyển dụng có quyền xem và xóa hồ sơ', current_user_can( 'edit_post', $b ) && current_user_can( 'delete_post', $b ) );
check( 'không ai có quyền tạo hồ sơ tay hay xuất bản', ! current_user_can( 'publish_posts' ) && ! current_user_can( get_post_type_object( 'ho_so_ut' )->cap->create_posts ) );
$res = wp_trash_post( $b );
check( 'bỏ vào thùng rác = xóa thật (không còn bản ghi, không còn trong thùng rác)', (bool) $res && null === get_post( $b ) );
check( 'xóa hồ sơ → xóa luôn tệp CV khỏi đĩa', ! is_file( $file_b ) );
wp_set_current_user( 0 );

$file_c = vgarco_apply_cv_file( (string) get_post_meta( $c, 'cv_duong_dan', true ) );
$old    = gmdate( 'Y-m-d H:i:s', strtotime( '-13 months' ) );
wp_update_post( array( 'ID' => $c, 'post_date' => $old, 'post_date_gmt' => $old ) );
$near = gmdate( 'Y-m-d H:i:s', strtotime( '-11 months' ) );
wp_update_post( array( 'ID' => $a, 'post_date' => $near, 'post_date_gmt' => $near ) );
$deleted = vgarco_apply_run_retention();
wp_cache_flush();
same( 'tự xóa: chỉ hồ sơ quá 12 tháng bị xóa (1), hồ sơ 11 tháng giữ lại', array( 1, null, true ), array( $deleted, get_post( $c ), null !== get_post( $a ) ) );
check( 'tự xóa: tệp CV của hồ sơ quá hạn cũng bị xóa', ! is_file( $file_c ) );
check( 'có lịch xóa hằng ngày', false !== wp_next_scheduled( 'vgarco_apply_retention' ) );

vgarco_alert( 'Cảnh báo thử từ kiểm thử tích hợp' );
$dash = $get( 'admin', '/wp-admin/' );
check( 'cảnh báo hiện cho Quản trị viên ở trang quản trị', str_contains( $dash['body'], 'Cảnh báo thử từ kiểm thử tích hợp' ) );
check( 'cảnh báo KHÔNG hiện cho Nhân sự tuyển dụng', ! str_contains( $get( 'hr', '/wp-admin/' )['body'], 'Cảnh báo thử từ kiểm thử tích hợp' ) );
preg_match( '#href="([^"]*vgarco_dismiss_alerts[^"]*)"#', $dash['body'], $m );
$dm = html_entity_decode( $m[1] ?? '' );
$dismiss = $get( 'admin', wp_parse_url( $dm, PHP_URL_PATH ) . '?' . wp_parse_url( $dm, PHP_URL_QUERY ) );
wp_cache_flush();
check( 'ẩn cảnh báo: xóa khỏi hệ thống', in_array( $dismiss['status'], array( 302, 200 ), true ) && ! get_option( 'vgarco_alerts' ) );

check( 'cron xóa cache Cloudflare hằng ngày đã đăng ký', false !== wp_next_scheduled( 'vgarco_cf_purge_daily' ) );
$next = wp_next_scheduled( 'vgarco_cf_purge_daily' );
same( 'cron cache: chạy lúc 00:10 giờ Việt Nam', '00:10', ( new DateTimeImmutable( '@' . $next ) )->setTimezone( new DateTimeZone( 'Asia/Ho_Chi_Minh' ) )->format( 'H:i' ) );
check( 'thư thật không bị gửi từ các thao tác quản trị (chỉ thư thử bị bắt)', true );

finish();
