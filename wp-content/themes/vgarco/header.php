<?php

defined( 'ABSPATH' ) || exit;
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?> data-vg-page="<?php echo esc_attr( vgarco_page_key() ); ?>">
<?php wp_body_open(); ?>

<a class="skip" href="#main"><?php esc_html_e( 'Đến nội dung chính', 'vgarco' ); ?></a>

<header class="header" id="top">
	<a class="brand" href="<?php echo esc_url( home_url( '/' ) ); ?>" aria-label="<?php esc_attr_e( 'VGARCO — Trang chủ', 'vgarco' ); ?>">
		<?php get_template_part( 'template-parts/layout/logo' ); ?>
		<span>VGARCO<small><?php esc_html_e( 'TƯ VẤN XÂY DỰNG', 'vgarco' ); ?></small></span>
	</a>

	<nav class="nav" id="main-nav" aria-label="<?php esc_attr_e( 'Điều hướng chính', 'vgarco' ); ?>">
		<?php vgarco_render_primary_nav( vgarco_menu_tree( 'primary' ) ); ?>
	</nav>

	<div class="header-actions">
		<?php ?>
		<a class="icon-button search-open" href="<?php echo esc_url( get_post_type_archive_link( 'du_an' ) ?: home_url( '/du-an/' ) ); ?>" data-vg-search-open aria-label="<?php esc_attr_e( 'Tìm kiếm dự án', 'vgarco' ); ?>">
			<?php echo vgarco_icon( 'search' ); ?>
		</a>
		<?php vgarco_language_switcher(); ?>
		<button type="button" class="icon-button menu-toggle" aria-expanded="false" aria-controls="main-nav"
			aria-label="<?php esc_attr_e( 'Mở menu', 'vgarco' ); ?>"
			data-label-open="<?php esc_attr_e( 'Mở menu', 'vgarco' ); ?>"
			data-label-close="<?php esc_attr_e( 'Đóng menu', 'vgarco' ); ?>">
			<span></span><span></span>
		</button>
	</div>
</header>

<main id="main">
<?php ?>
<div class="lang-notice wrap" data-lang-notice role="status" hidden>
	<p><?php esc_html_e( 'Nội dung này đang được dịch. Trong lúc chờ, mời bạn xem mục tương ứng.', 'vgarco' ); ?></p>
	<button type="button" class="lang-notice-close" data-lang-notice-close aria-label="<?php esc_attr_e( 'Đóng', 'vgarco' ); ?>">
		<?php echo vgarco_icon( 'close' ); ?>
	</button>
</div>
