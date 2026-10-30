<?php

define( 'LAB', __DIR__ . '/.lab' );
$_SERVER['HTTP_HOST']   = 'localhost:8080';
$_SERVER['REQUEST_URI'] = '/';
require __DIR__ . '/wpcli-shim.php';
require LAB . '/wordpress/wp-load.php';

$pages = array(
	'home'        => array( 'Trang chủ', 'trang-chu', '', 'Startseite', 'startseite' ),
	'about'       => array( 'Về công ty', 'gioi-thieu', 'templates/about.php', 'Über uns', 'ueber-uns' ),
	'leadership'  => array( 'Ban lãnh đạo', 'gioi-thieu/ban-lanh-dao', 'templates/leadership.php', 'Geschäftsführung', 'geschaeftsfuehrung' ),
	'departments' => array( 'Phòng ban', 'gioi-thieu/phong-ban', 'templates/departments.php', 'Abteilungen', 'abteilungen' ),
	'services'    => array( 'Lĩnh vực hoạt động', 'linh-vuc-hoat-dong', 'templates/services.php', 'Leistungen', 'leistungen' ),
	'careers'     => array( 'Tuyển dụng', 'tuyen-dung', 'templates/careers.php', 'Karriere', 'karriere' ),
	'apply'       => array( 'Ứng tuyển', 'ung-tuyen', 'templates/apply.php', 'Bewerbung', 'bewerbung' ),
	'contact'     => array( 'Liên hệ', 'lien-he', 'templates/contact.php', 'Kontakt', 'kontakt' ),
	'privacy'     => array( 'Chính sách bảo vệ dữ liệu', 'chinh-sach-bao-ve-du-lieu', '', 'Datenschutz', 'datenschutz' ),
);

$vi = array();
$de = array();
foreach ( $pages as $key => [ $title, $path, $template, $title_de, $slug_de ] ) {
	$parts    = explode( '/', $path );
	$slug     = end( $parts );
	$parent   = count( $parts ) > 1 ? (int) ( get_page_by_path( $parts[0] )->ID ?? 0 ) : 0;
	$existing = get_page_by_path( $path );
	$content  = 'privacy' === $key ? '<p>Nội dung chính sách bảo vệ dữ liệu cá nhân (bản thử).</p>' : '';
	$id       = $existing ? $existing->ID : wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => $title, 'post_name' => $slug, 'post_parent' => $parent, 'post_content' => $content ) );
	if ( $template ) {
		update_post_meta( $id, '_wp_page_template', $template );
	}
	vgarco_set_post_language( $id, 'vi' );
	$vi[ $key ] = (int) $id;

	$de_parent = $parent ? (int) pll_get_post( $parent, 'de' ) : 0;
	$de_id     = (int) pll_get_post( $id, 'de' );
	if ( ! $de_id ) {
		$de_content = 'privacy' === $key ? '<p>Inhalt der Datenschutzerklärung (Testfassung).</p>' : '';
		$de_id      = (int) wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => $title_de, 'post_name' => $slug_de, 'post_parent' => $de_parent, 'post_content' => $de_content ) );
		if ( $template ) {
			update_post_meta( $de_id, '_wp_page_template', $template );
		}
		vgarco_set_post_language( $de_id, 'de' );
		vgarco_link_translation( (int) $id, $de_id, 'de' );
	}
	$de[ $key ] = $de_id;
}
update_option( 'show_on_front', 'page' );
update_option( 'page_on_front', $vi['home'] );
flush_rewrite_rules();
echo 'trang: ' . json_encode( $vi ) . "\n" . 'trang DE: ' . json_encode( $de ) . "\n";
