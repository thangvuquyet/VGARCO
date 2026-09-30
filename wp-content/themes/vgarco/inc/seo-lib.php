<?php
/**
 * SEO cơ bản (mục 8.9 của phương án): các hàm THUẦN dựng thẻ meta, Open Graph và dữ liệu có cấu trúc (JSON-LD).
 * Không gọi WordPress nên kiểm thử được bằng dữ liệu giả (tests/php/seo.php). Phần lấy dữ liệu từ WordPress nằm ở seo.php.
 *
 * Mọi chuỗi đi vào HTML đều được thoát ở đây; JSON-LD dùng JSON_HEX_TAG để không thể "thoát" khỏi thẻ <script>.
 *
 * @package vgarco
 */

defined( 'ABSPATH' ) || exit;

/**
 * Mô tả cho công cụ tìm kiếm: bỏ thẻ HTML, gộp khoảng trắng, cắt ở ranh giới từ (tối đa $max ký tự, kể cả dấu …).
 */
function vgarco_seo_description( string $text, int $max = 160 ): string {
	$text = html_entity_decode( strip_tags( $text ), ENT_QUOTES, 'UTF-8' );
	$text = trim( (string) preg_replace( '/\s+/u', ' ', $text ) );

	if ( mb_strlen( $text ) <= $max ) {
		return $text;
	}

	$cut   = mb_substr( $text, 0, $max - 1 );
	$space = mb_strrpos( $cut, ' ' );
	if ( false !== $space && $space > $max * 0.6 ) {
		$cut = mb_substr( $cut, 0, $space ); // không cắt giữa từ
	}
	return rtrim( $cut, " \t,;:.-–—" ) . '…';
}

/**
 * Chỉ nhận địa chỉ tuyệt đối http(s). Mọi thứ khác (javascript:, data:, đường dẫn tương đối) trả về rỗng:
 * thẻ OG và JSON-LD bắt buộc dùng địa chỉ tuyệt đối.
 */
function vgarco_seo_url( string $url ): string {
	$url = trim( $url );
	if ( ! preg_match( '#^https?://[^\s<>"\']+$#i', $url ) ) {
		return '';
	}
	return false !== filter_var( $url, FILTER_VALIDATE_URL ) ? $url : '';
}

/** Thoát giá trị thuộc tính HTML. */
function vgarco_seo_attr( string $value ): string {
	return htmlspecialchars( $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
}

/**
 * Các thẻ <meta>/<link> của một trang.
 *
 * @param array $m description, canonical, title, type (website|article), image, image_width, image_height, image_alt,
 *                 locale, alternates (mảng locale), site_name, published (ISO 8601, chỉ với type=article).
 * @return string HTML, mỗi thẻ một dòng. Thẻ không có dữ liệu thì bỏ.
 */
function vgarco_seo_tags( array $m ): string {
	$out = array();
	$add = static function ( string $tag ) use ( &$out ): void {
		$out[] = $tag;
	};
	$meta = static fn( string $attr, string $name, string $content ): string => sprintf( '<meta %s="%s" content="%s">', $attr, vgarco_seo_attr( $name ), vgarco_seo_attr( $content ) );

	$description = vgarco_seo_description( (string) ( $m['description'] ?? '' ) );
	$canonical   = vgarco_seo_url( (string) ( $m['canonical'] ?? '' ) );
	$image       = vgarco_seo_url( (string) ( $m['image'] ?? '' ) );
	$title       = trim( (string) ( $m['title'] ?? '' ) );

	if ( '' !== $description ) {
		$add( $meta( 'name', 'description', $description ) );
	}
	if ( '' !== $canonical ) {
		$add( sprintf( '<link rel="canonical" href="%s">', vgarco_seo_attr( $canonical ) ) );
	}

	$add( $meta( 'property', 'og:type', 'article' === ( $m['type'] ?? '' ) ? 'article' : 'website' ) );
	if ( '' !== $title ) {
		$add( $meta( 'property', 'og:title', $title ) );
	}
	if ( '' !== $description ) {
		$add( $meta( 'property', 'og:description', $description ) );
	}
	if ( '' !== $canonical ) {
		$add( $meta( 'property', 'og:url', $canonical ) );
	}
	if ( '' !== (string) ( $m['site_name'] ?? '' ) ) {
		$add( $meta( 'property', 'og:site_name', (string) $m['site_name'] ) );
	}
	if ( '' !== (string) ( $m['locale'] ?? '' ) ) {
		$add( $meta( 'property', 'og:locale', (string) $m['locale'] ) );
	}
	foreach ( (array) ( $m['alternates'] ?? array() ) as $alternate ) {
		if ( '' !== (string) $alternate && $alternate !== ( $m['locale'] ?? '' ) ) {
			$add( $meta( 'property', 'og:locale:alternate', (string) $alternate ) );
		}
	}
	if ( '' !== $image ) {
		$add( $meta( 'property', 'og:image', $image ) );
		if ( (int) ( $m['image_width'] ?? 0 ) > 0 && (int) ( $m['image_height'] ?? 0 ) > 0 ) {
			$add( $meta( 'property', 'og:image:width', (string) (int) $m['image_width'] ) );
			$add( $meta( 'property', 'og:image:height', (string) (int) $m['image_height'] ) );
		}
		if ( '' !== (string) ( $m['image_alt'] ?? '' ) ) {
			$add( $meta( 'property', 'og:image:alt', (string) $m['image_alt'] ) );
		}
	}
	if ( 'article' === ( $m['type'] ?? '' ) && '' !== (string) ( $m['published'] ?? '' ) ) {
		$add( $meta( 'property', 'article:published_time', (string) $m['published'] ) );
	}
	$add( $meta( 'name', 'twitter:card', '' !== $image ? 'summary_large_image' : 'summary' ) );

	return implode( "\n", $out ) . "\n";
}

/**
 * Thẻ <script type="application/ld+json">. JSON_HEX_TAG/AMP/APOS/QUOT mã hóa < > & ' " nên một tiêu đề chứa "</script>" không phá được trang.
 */
function vgarco_seo_jsonld( array $data ): string {
	if ( ! $data ) {
		return '';
	}
	$json = json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT );
	return false === $json ? '' : '<script type="application/ld+json">' . $json . "</script>\n";
}

/**
 * Tổ chức (Organization) cho trang chủ: tên, địa chỉ, điện thoại, logo.
 *
 * @param array $o name, url, logo, phone, email, street, country (mã ISO, mặc định VN), same_as (mảng địa chỉ mạng xã hội).
 */
function vgarco_seo_org( array $o ): array {
	$data = array(
		'@context' => 'https://schema.org',
		'@type'    => 'Organization',
		'name'     => (string) ( $o['name'] ?? '' ),
		'url'      => vgarco_seo_url( (string) ( $o['url'] ?? '' ) ),
	);
	if ( '' === $data['name'] || '' === $data['url'] ) {
		return array();
	}
	if ( '' !== vgarco_seo_url( (string) ( $o['logo'] ?? '' ) ) ) {
		$data['logo'] = vgarco_seo_url( (string) $o['logo'] );
	}
	if ( '' !== trim( (string) ( $o['phone'] ?? '' ) ) ) {
		$data['telephone'] = trim( (string) $o['phone'] );
	}
	if ( '' !== trim( (string) ( $o['email'] ?? '' ) ) ) {
		$data['email'] = trim( (string) $o['email'] );
	}
	if ( '' !== trim( (string) ( $o['street'] ?? '' ) ) ) {
		$data['address'] = array(
			'@type'         => 'PostalAddress',
			'streetAddress' => trim( (string) preg_replace( '/\s+/u', ' ', (string) $o['street'] ) ),
			'addressCountry' => (string) ( $o['country'] ?? 'VN' ),
		);
	}
	$same = array_values( array_filter( array_map( static fn( $u ) => vgarco_seo_url( (string) $u ), (array) ( $o['same_as'] ?? array() ) ) ) );
	if ( $same ) {
		$data['sameAs'] = $same;
	}
	return $data;
}

/**
 * BreadcrumbList từ đường dẫn vị trí của trang. Mục cuối (trang hiện tại) không có liên kết trong $trail: dùng $current_url.
 * Có ít hơn 2 mục thì không đáng in.
 *
 * @param array[] $trail        Mỗi phần tử: [nhãn, địa chỉ tuyệt đối] hoặc [nhãn] cho mục cuối.
 * @param string  $current_url  Địa chỉ trang hiện tại.
 */
function vgarco_seo_breadcrumbs( array $trail, string $current_url = '' ): array {
	$items = array();
	foreach ( array_values( $trail ) as $i => $crumb ) {
		$url  = vgarco_seo_url( (string) ( $crumb[1] ?? '' ) );
		$url  = '' !== $url ? $url : ( $i === count( $trail ) - 1 ? vgarco_seo_url( $current_url ) : '' );
		$item = array(
			'@type'    => 'ListItem',
			'position' => $i + 1,
			'name'     => (string) $crumb[0],
		);
		if ( '' !== $url ) {
			$item['item'] = $url;
		}
		$items[] = $item;
	}
	if ( count( $items ) < 2 ) {
		return array();
	}
	return array(
		'@context'        => 'https://schema.org',
		'@type'           => 'BreadcrumbList',
		'itemListElement' => $items,
	);
}

/** Loại hình làm việc của schema.org. */
function vgarco_seo_employment_type( string $type ): string {
	return array(
		'toan_thoi_gian' => 'FULL_TIME',
		'ban_thoi_gian'  => 'PART_TIME',
		'thuc_tap'       => 'INTERN',
	)[ $type ] ?? 'FULL_TIME';
}

/**
 * Mô tả vị trí tuyển dụng dạng HTML đơn giản cho JobPosting (schema.org cho phép HTML trong description).
 *
 * @param string   $summary  Tóm tắt (có thể rỗng).
 * @param array[]  $sections Mỗi phần: [tiêu đề, mảng dòng].
 */
function vgarco_seo_job_html( string $summary, array $sections ): string {
	$html = '';
	if ( '' !== trim( $summary ) ) {
		$html .= '<p>' . htmlspecialchars( trim( $summary ), ENT_QUOTES, 'UTF-8' ) . '</p>';
	}
	foreach ( $sections as [ $heading, $lines ] ) {
		$lines = array_values( array_filter( array_map( 'trim', (array) $lines ), static fn( $l ) => '' !== $l ) );
		if ( ! $lines ) {
			continue;
		}
		$html .= '<h3>' . htmlspecialchars( (string) $heading, ENT_QUOTES, 'UTF-8' ) . '</h3><ul>';
		foreach ( $lines as $line ) {
			$html .= '<li>' . htmlspecialchars( $line, ENT_QUOTES, 'UTF-8' ) . '</li>';
		}
		$html .= '</ul>';
	}
	return $html;
}

/**
 * JobPosting (Google Jobs). Chỉ dựng cho vị trí ĐANG nhận hồ sơ: tin quá hạn không được đưa cho công cụ tìm kiếm.
 *
 * @param array $j open (bool), title, url, description_html, posted (Y-m-d), deadline (Ymd hoặc rỗng), type, location (tên), org (xem vgarco_seo_org).
 */
function vgarco_seo_job( array $j ): array {
	if ( empty( $j['open'] ) || '' === trim( (string) ( $j['title'] ?? '' ) ) || '' === trim( (string) ( $j['description_html'] ?? '' ) ) ) {
		return array();
	}
	$org = vgarco_seo_org( (array) ( $j['org'] ?? array() ) );
	if ( ! $org ) {
		return array();
	}

	$data = array(
		'@context'           => 'https://schema.org',
		'@type'              => 'JobPosting',
		'title'              => (string) $j['title'],
		'description'        => (string) $j['description_html'],
		'datePosted'         => (string) ( $j['posted'] ?? '' ),
		'employmentType'     => vgarco_seo_employment_type( (string) ( $j['type'] ?? '' ) ),
		'hiringOrganization' => array_filter(
			array(
				'@type'  => 'Organization',
				'name'   => $org['name'],
				'sameAs' => $org['url'],
				'logo'   => $org['logo'] ?? '',
			)
		),
		'url'                => vgarco_seo_url( (string) ( $j['url'] ?? '' ) ),
	);

	$deadline = (string) ( $j['deadline'] ?? '' );
	if ( preg_match( '/^(\d{4})(\d{2})(\d{2})$/', $deadline, $d ) && checkdate( (int) $d[2], (int) $d[3], (int) $d[1] ) ) {
		$data['validThrough'] = "{$d[1]}-{$d[2]}-{$d[3]}T23:59:59+07:00"; // hết ngày hạn nộp theo giờ Việt Nam
	}
	if ( '' !== trim( (string) ( $j['location'] ?? '' ) ) ) {
		$data['jobLocation'] = array(
			'@type'   => 'Place',
			'address' => array(
				'@type'           => 'PostalAddress',
				'addressLocality' => trim( (string) $j['location'] ),
				'addressCountry'  => 'VN',
			),
		);
	}
	return array_filter( $data, static fn( $v ) => '' !== $v && array() !== $v );
}
