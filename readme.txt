=== StoreLink ===
Contributors: storelink
Tags: woocommerce, telegram, bot, orders, messengers
Requires at least: 6.6
Tested up to: 7.1
Requires PHP: 8.3
Stable tag: 2.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Connect WooCommerce to Telegram first. Other messengers plug in through the same gateway interface later.

== Description ==

StoreLink connects a WooCommerce store to messenger bots. Telegram is the complete shop client in this version: browse and search products, cart quantities, checkout (WooCommerce order + payment URL), my orders, and store-ops for configured Telegram admin IDs (order list and status).

Bale uses a thin Telegram-compatible adapter. Eitaa, Rubika, and Instagram wait until the Telegram bot is the source of truth.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/StoreLink`
2. Activate through the Plugins menu (requires WooCommerce 8.0+ and PHP 8.3+)
3. Open WooCommerce → StoreLink, paste the Telegram bot token, save, then Connect webhook

== Frequently Asked Questions ==

= Does payment happen inside Telegram? =

No. The bot creates a pending WooCommerce order and sends the payment URL.

= Can I add another messenger? =

Implement `StoreLink\Messengers\GatewayInterface` and register it on `storelink_register_gateways`.

== Changelog ==

= 2.0.0 =
* Replace PricePilot pricing with StoreLink messenger architecture
* Telegram catalog, cart, and WooCommerce checkout
