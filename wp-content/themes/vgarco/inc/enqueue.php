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

		// Preload phông của chữ nghiêng Lora: có mặt ở phần đầu mọi trang (cụm từ nhấn trong tiêu đề).
		// Chỉ preload tập con cần cho ngôn ngữ đang xem: tập "vietnamese" chỉ khi trang tiếng Việt.
		// Phông sans không preload: Windows đã có Segoe UI; máy khác dùng VG Sans, trình duyệt chỉ tải tập con cần.
		$subsets = array( 'lora-latin-400-italic' );
		if ( str_starts_with( determine_locale(), 'vi' ) ) {
			$subsets[] = 'lora-vietnamese-400-italic';
		}
		$fonts = array();
		foreach ( $entry['assets'] ?? array() as $asset ) {
			foreach ( $subsets as $subset ) {
				if ( str_contains( $asset, "/{$subset}-" ) ) {
					$fonts[] = get_theme_file_uri( "dist/{$asset}" );
				}
			}
		}
		if ( $fonts ) {
			add_action(
				'wp_head',
				static function () use ( $fonts ): void {
					foreach ( $fonts as $url ) {
						// crossorigin bắt buộc với phông, kể cả cùng nguồn; thiếu thì trình duyệt tải hai lần.
						printf( '<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin>' . "\n", esc_url( $url ) );
					}
				},
				2 // KHÔNG dùng 1: wp_enqueue_scripts chạy ở wp_head mức 1, WordPress bỏ qua callback thêm vào CÙNG mức khi đang lặp nên thẻ preload không bao giờ được in (chỉ lộ ra trên WordPress thật)
			);
		}
	}
);
