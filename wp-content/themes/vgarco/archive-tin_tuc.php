<?php

defined( 'ABSPATH' ) || exit;

get_header();
get_template_part( 'template-parts/news-archive', null, array( 'term' => null ) );
get_footer();
