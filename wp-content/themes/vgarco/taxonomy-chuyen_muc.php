<?php
/**
 * Danh sách tin tức theo một chuyên mục (UC-06). Nội dung ở template-parts/news-archive.php.
 *
 * @package vgarco
 */

defined( 'ABSPATH' ) || exit;

get_header();
get_template_part( 'template-parts/news-archive', null, array( 'term' => get_queried_object() ) );
get_footer();
