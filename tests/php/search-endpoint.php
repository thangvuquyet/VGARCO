<?php
require_once __DIR__ . '/search-index.php';

$query = mb_substr( (string) ( $argv[1] ?? '' ), 0, VGARCO_SEARCH_MAX_CHARS );
echo json_encode( array( 'q' => $query, 'results' => vgarco_search_rank( fx_search_index( true ), $query ) ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
