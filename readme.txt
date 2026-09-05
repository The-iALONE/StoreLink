=== PricePilot ===
Contributors: pricepilot
Tags: woocommerce, pricing, bulk edit, products, import, export
Requires at least: 6.6
Tested up to: 7.1
Requires PHP: 8.3
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Smart WooCommerce pricing and product operations dashboard.

== Description ==

PricePilot helps store managers update WooCommerce product prices faster with bulk operations, advanced filters, preview/confirm workflow, undo, history logs, and Excel import/export.

**Languages:** Persian (default) and English — switch from the admin header.

**V1 Features:**

* Bulk price editor (+/- percent, fixed amount, set price, remove sale)
* Advanced product filters
* Preview before apply
* Undo last bulk operation
* Change history / audit log
* Excel import / export
* Dashboard with store KPIs

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/PricePilot`
2. Run `composer install` in the plugin directory
3. Run `npm install && npm run build` for admin UI assets
4. Activate through the 'Plugins' menu in WordPress
5. Requires WooCommerce 8.0+ and PHP 8.3+

== Frequently Asked Questions ==

= Does it support variable products? =

Bulk pricing applies to simple products and variations. Variable parent products are skipped with a clear message.

= Can I undo bulk changes? =

Yes. The last bulk operation can be undone from the Dashboard or History tab.

== Changelog ==

= 1.0.0 =
* Initial V1 release
