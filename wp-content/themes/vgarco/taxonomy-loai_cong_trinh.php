<?php
/**
 * Danh mục dự án theo một loại công trình (UC-04). Nội dung ở template-parts/catalogue.php.
 *
 * @package vgarco
 */

defined( 'ABSPATH' ) || exit;

get_header();
get_template_part( 'template-parts/catalogue', null, array( 'term' => get_queried_object() ) );
get_footer();
