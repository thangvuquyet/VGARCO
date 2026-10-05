<?php
// WP-CLI giả: đủ cho VGARCO_CLI_Command (log, warning, error, success, format_items).
namespace {
	define( 'WP_CLI', true );
	class WP_CLI {
		public static array $lines  = array();
		public static array $tables = array(); // các bảng đã in qua format_items (để kiểm nội dung)
		public static function add_command( $name, $class ) {}
		public static function log( $m ) { self::$lines[] = (string) $m; }
		public static function success( $m ) { self::$lines[] = 'OK: ' . $m; }
		public static function warning( $m ) { self::$lines[] = 'WARN: ' . $m; }
		public static function error( $m ) { throw new RuntimeException( (string) $m ); }
		public static function error_multi_line( $lines ) { self::$lines[] = 'ERRORS: ' . implode( ' | ', (array) $lines ); }
	}
}
namespace WP_CLI\Utils {
	function format_items( $format, $items, $fields ) {
		\WP_CLI::$tables[] = $items;
		\WP_CLI::log( 'table: ' . count( $items ) . ' dòng' );
	}
}
