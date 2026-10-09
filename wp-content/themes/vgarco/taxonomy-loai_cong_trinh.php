<?php

defined( 'ABSPATH' ) || exit;

get_header();
get_template_part( 'template-parts/catalogue', null, array( 'term' => get_queried_object() ) );
get_footer();
