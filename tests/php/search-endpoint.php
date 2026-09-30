<?php
/**
 * Đường REST giả cho trình duyệt thử: php search-endpoint.php <q> <lang> → JSON như GET /wp-json/vgarco/v1/search.
 * Dùng đúng vgarco_search_rank() của plugin trên 19 dự án thật.
 */
require_once __DIR__ . '/search-index.php';

$query = mb_substr( (string) ( $argv[1] ?? '' ), 0, VGARCO_SEARCH_MAX_CHARS );
echo json_encode( array( 'q' => $query, 'results' => vgarco_search_rank( fx_search_index( true ), $query ) ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
