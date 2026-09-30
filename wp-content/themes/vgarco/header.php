<?php
/**
 * Đầu trang: khung <head>, liên kết bỏ qua, thanh đầu trang, mở <main>.
 * Đóng <main> ở footer.php.
 *
 * @package vgarco
 */

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
		<button type="button" class="icon-button search-open" data-vg-search-open aria-label="<?php esc_attr_e( 'Tìm kiếm dự án', 'vgarco' ); ?>">
			<?php echo vgarco_icon( 'search' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG của theme. ?>
		</button>
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
