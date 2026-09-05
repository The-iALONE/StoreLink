<?php
/**
 * Compile .po files to .mo without bootstrapping WordPress.
 *
 * Usage: php tools/compile-i18n.php
 *
 * @package PricePilot
 */

if ( ! function_exists( 'array_last' ) ) {
	/**
	 * Polyfill for WordPress POMO on standalone runs.
	 *
	 * @param array<int, mixed> $array Input array.
	 * @return mixed
	 */
	function array_last( array $array ) {
		return end( $array );
	}
}

$wp_includes = dirname( __DIR__, 4 ) . '/wp-includes/pomo';
require_once $wp_includes . '/translations.php';
require_once $wp_includes . '/streams.php';
require_once $wp_includes . '/plural-forms.php';
require_once $wp_includes . '/po.php';
require_once $wp_includes . '/mo.php';

$languages_dir = dirname( __DIR__ ) . '/languages';
$files         = glob( $languages_dir . '/*.po' );

foreach ( $files as $po_file ) {
	$po = new PO();
	if ( ! $po->import_from_file( $po_file ) ) {
		fwrite( STDERR, "Failed: {$po_file}\n" );
		continue;
	}

	$mo_file = preg_replace( '/\.po$/', '.mo', $po_file );
	$mo      = new MO();
	$mo->entries = $po->entries;
	$mo->headers = $po->headers;
	$mo->set_header( 'Plural-Forms', $po->headers['Plural-Forms'] ?? 'nplurals=2; plural=(n != 1);' );

	if ( $mo->export_to_file( $mo_file ) ) {
		echo "Compiled: {$mo_file}\n";
	}
}
