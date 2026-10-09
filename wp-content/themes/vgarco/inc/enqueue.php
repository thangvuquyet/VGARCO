<?php

defined( 'ABSPATH' ) || exit;

function vgarco_vite_manifest(): array {
	static $manifest = null;

	if ( null === $manifest ) {
		$manifest = array();
		$file     = get_theme_file_path( 'dist/.vite/manifest.json' );
		if ( is_readable( $file ) ) {
			$decoded = json_decode( (string) file_get_contents( $file ), true );
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

		wp_enqueue_script_module( 'vgarco-main', get_theme_file_uri( 'dist/' . $entry['file'] ), array(), null );

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
						printf( '<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin>' . "\n", esc_url( $url ) );
					}
				},
				2
			);
		}
	}
);
