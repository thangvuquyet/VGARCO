<?php

defined( 'ABSPATH' ) || exit;

function vgarco_image_widths(): array {
	return array( 480, 800, 1200, 1600, 2560 );
}

add_action(
	'after_setup_theme',
	static function (): void {
		foreach ( vgarco_image_widths() as $width ) {
			add_image_size( "vg-{$width}", $width, 0, false );
		}
	}
);

add_filter(
	'intermediate_image_sizes_advanced',
	static function ( array $sizes ): array {
		unset( $sizes['medium_large'], $sizes['1536x1536'], $sizes['2048x2048'] );
		return $sizes;
	}
);

add_filter(
	'big_image_size_threshold',
	static function (): int {
		return 2560;
	}
);

add_filter( 'wp_editor_set_quality', 'vgarco_image_quality', 10, 3 );

add_filter(
	'intermediate_image_sizes_advanced',
	static function ( array $sizes, $image_meta = array(), $attachment_id = 0 ): array {
		$state        = &vgarco_image_quality_state();
		$state['ctx'] = null;
		if ( ( defined( 'VGARCO_AVIF_ADAPTIVE' ) && ! VGARCO_AVIF_ADAPTIVE ) || ! $attachment_id || ! is_array( $image_meta ) ) {
			return $sizes;
		}
		$id    = (int) $attachment_id;
		$width = (int) ( $image_meta['width'] ?? 0 );
		$high  = (int) ( $image_meta['height'] ?? 0 );
		$state['ctx'] = array(
			'width'   => $width,
			'quality' => null,
			'resolve' => static fn(): int => vgarco_resolve_avif_quality( $id, $width, $high ),
		);
		return $sizes;
	},
	20,
	3
);
add_filter(
	'wp_generate_attachment_metadata',
	static function ( $metadata ) {
		$state        = &vgarco_image_quality_state();
		$state['ctx'] = null;
		return $metadata;
	},
	99
);

function vgarco_measure_avif_bytes( string $file, int $quality ): ?int {
	$state          = &vgarco_image_quality_state();
	$state['force'] = $quality;
	$dest           = trailingslashit( get_temp_dir() ) . wp_unique_filename( get_temp_dir(), 'vgarco-do-avif.avif' );
	try {
		$editor = wp_get_image_editor( $file );
		if ( is_wp_error( $editor ) ) {
			return null;
		}
		$size = $editor->get_size();
		if ( (int) ( $size['width'] ?? 0 ) > 2560 ) {
			$editor->resize( 2560, null, false );
		}
		$saved = $editor->save( $dest, 'image/avif' );
		if ( is_wp_error( $saved ) || empty( $saved['path'] ) || 'image/avif' !== ( $saved['mime-type'] ?? '' ) ) {
			return null;
		}
		$bytes = (int) filesize( $saved['path'] );
		wp_delete_file( $saved['path'] );
		return $bytes > 0 ? $bytes : null;
	} finally {
		$state['force'] = null;
		wp_delete_file( $dest );
	}
}

function vgarco_resolve_avif_quality( int $attachment_id, int $width, int $height ): int {
	$file = get_attached_file( $attachment_id );
	if ( ! $file || ! is_readable( $file ) || ! wp_image_editor_supports( array( 'mime_type' => 'image/avif' ) ) ) {
		return VGARCO_AVIF_QUALITY;
	}
	$ceiling = (int) apply_filters( 'vgarco_image_ceiling_bytes', vgarco_image_ceiling_bytes( $width, $height ), $width, $height );
	$probe   = wp_get_image_editor( $file );
	$editor  = is_wp_error( $probe ) ? '' : get_class( $probe );
	unset( $probe );
	$sig = md5( implode( '|', array( $editor, $ceiling, $width, $height, (int) filesize( $file ), VGARCO_AVIF_QUALITY_STEPS[0] ) ) );

	$saved = get_post_meta( $attachment_id, '_vgarco_avif_quality', true );
	if ( is_array( $saved ) && ( $saved['sig'] ?? '' ) === $sig && isset( $saved['quality'] ) ) {
		return (int) $saved['quality'];
	}

	$pick = vgarco_pick_avif_quality( static fn( int $q ): ?int => vgarco_measure_avif_bytes( $file, $q ), $ceiling );
	update_post_meta( $attachment_id, '_vgarco_avif_quality', $pick + array( 'ceiling' => $ceiling, 'sig' => $sig, 'editor' => $editor ) );
	return $pick['quality'];
}

add_filter(
	'upload_mimes',
	static function ( array $mimes ): array {
		foreach ( $mimes as $extension => $mime ) {
			if ( str_starts_with( (string) $mime, 'image/' ) && ! in_array( $mime, array( 'image/jpeg', 'image/png' ), true ) ) {
				unset( $mimes[ $extension ] );
			}
		}
		return $mimes;
	}
);

function vgarco_sanitize_uploaded_image( array $upload ): array {
	$type = (string) ( $upload['type'] ?? '' );
	if ( ! empty( $upload['error'] ) || empty( $upload['file'] ) || ! in_array( $type, array( 'image/jpeg', 'image/png' ), true ) ) {
		return $upload;
	}

	$file   = (string) $upload['file'];
	$reject = static function ( string $reason ) use ( $file ): array {
		wp_delete_file( $file );
		return array( 'error' => 'Không xử lý được ảnh này (' . $reason . '). Hãy xuất lại ảnh thành JPEG hoặc PNG rồi tải lên.' );
	};

	$bytes = file_get_contents( $file );
	if ( false === $bytes ) {
		return $reject( 'không đọc được tệp' );
	}

	if ( 'image/png' === $type ) {
		$clean = vgarco_png_strip_metadata( $bytes );
	} else {
		$orientation = vgarco_jpeg_orientation( $bytes );
		if ( $orientation > 1 ) {
			$force  = static fn(): int => $orientation;
			$editor = wp_get_image_editor( $file );
			if ( is_wp_error( $editor ) ) {
				return $reject( 'không xoay được ảnh dọc' );
			}
			add_filter( 'wp_image_maybe_exif_rotate', $force );
			$editor->maybe_exif_rotate();
			remove_filter( 'wp_image_maybe_exif_rotate', $force );
			$editor->set_quality( 92 );
			$saved = $editor->save( $file, 'image/jpeg' );
			if ( is_wp_error( $saved ) ) {
				return $reject( 'không lưu được ảnh sau khi xoay' );
			}
			$bytes = file_get_contents( $file );
			if ( false === $bytes ) {
				return $reject( 'không đọc lại được ảnh sau khi xoay' );
			}
		}
		$clean = vgarco_jpeg_strip_metadata( $bytes );
	}

	if ( null === $clean ) {
		return $reject( 'tệp ảnh bị hỏng' );
	}
	if ( $clean !== $bytes && false === file_put_contents( $file, $clean ) ) {
		return $reject( 'không ghi được tệp' );
	}
	return $upload;
}
add_filter( 'wp_handle_upload', 'vgarco_sanitize_uploaded_image' );
