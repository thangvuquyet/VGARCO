<?php
/**
 * Nạp CSS/JS đã build bằng Vite. PHP đọc manifest để lấy tên tệp có mã băm:
 * tên đổi khi nội dung đổi, nên cache dài hạn ở CDN không bao giờ phục vụ bản cũ.
 *
 * @package vgarco
 */

defined( 'ABSPATH' ) || exit;

/**
 * Manifest do Vite sinh ra ở dist/.vite/manifest.json.
 *
 * @return array Mảng rỗng nếu chưa build.
 */
function vgarco_vite_manifest(): array {
	static $manifest = null;

	if ( null === $manifest ) {
		$manifest = array();
		$file     = get_theme_file_path( 'dist/.vite/manifest.json' );
		if ( is_readable( $file ) ) {
			$decoded = json_decode( (string) file_get_contents( $file ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			if ( is_array( $decoded ) ) {
				$manifest = $decoded;
			}
		}
	}

	return $manifest;
}

add_action(
	'wp_enqueue_scripts',
	static function (): void {
		$entry = vgarco_vite_manifest()['src/js/main.js'] ?? null;

		if ( ! $entry ) {
			// Chưa build: chỉ báo cho người đang đăng nhập quản trị, khách vãng lai không thấy gì.
			if ( current_user_can( 'manage_options' ) ) {
				add_action(
					'wp_head',
					static function (): void {
						echo "<!-- VGARCO: chưa có dist/. Chạy `npm run build` trong thư mục theme. -->\n";
					}
				);
			}
			return;
		}

		foreach ( $entry['css'] ?? array() as $index => $css_file ) {
			wp_enqueue_style( "vgarco-{$index}", get_theme_file_uri( "dist/{$css_file}" ), array(), null );
		}

		// Script module (WordPress 6.5+): in ra type="module", tự defer.
		wp_enqueue_script_module( 'vgarco-main', get_theme_file_uri( 'dist/' . $entry['file'] ), array(), null );
	}
);
