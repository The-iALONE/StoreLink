<?php
/**
 * Generate JED JSON translation files for the admin script.
 *
 * Usage: php tools/generate-i18n-json.php
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

$languages_dir = dirname( __DIR__ ) . '/languages';
$po_file       = $languages_dir . '/pricepilot-fa_IR.po';
$script_hash   = md5( 'admin/assets/index.js' );

if ( ! file_exists( $po_file ) ) {
	fwrite( STDERR, "Missing {$po_file}\n" );
	exit( 1 );
}

$wp_includes = dirname( __DIR__, 4 ) . '/wp-includes/pomo';
require_once $wp_includes . '/translations.php';
require_once $wp_includes . '/streams.php';
require_once $wp_includes . '/plural-forms.php';
require_once $wp_includes . '/po.php';

$po = new PO();
$po->import_from_file( $po_file );

$entries = array(
	'' => array(
		'domain'       => 'pricepilot',
		'lang'         => 'fa',
		'plural-forms' => 'nplurals=2; plural=(n != 1);',
	),
);

foreach ( $po->entries as $entry ) {
	if ( empty( $entry->singular ) ) {
		continue;
	}
	$entries[ $entry->singular ] = array( $entry->translations[0] ?? $entry->singular );
}

$jed = array(
	'translation-revision-date' => gmdate( 'Y-m-d H:i' ) . '+0000',
	'generator'                 => 'PricePilot',
	'source'                    => 'admin/assets/index.js',
	'domain'                    => 'pricepilot',
	'locale_data'               => array(
		'pricepilot' => $entries,
		'messages'   => $entries,
	),
);

$json_file = $languages_dir . '/pricepilot-fa_IR-' . $script_hash . '.json';
file_put_contents(
	$json_file,
	json_encode( $jed, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT )
);
echo "Generated: {$json_file}\n";
