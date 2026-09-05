<?php
/**
 * Admin dashboard template.
 *
 * @package PricePilot
 */

use PricePilot\Core\Locale;

defined( 'ABSPATH' ) || exit;

$direction = Locale::is_rtl() ? 'rtl' : 'ltr';
?>
<div class="wrap pricepilot-wrap" dir="<?php echo esc_attr( $direction ); ?>">
	<div id="pricepilot-root"></div>
</div>
