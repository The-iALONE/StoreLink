# StoreLink Architecture

## Stack

- PHP 8.3+
- WordPress 6.6+
- WooCommerce 8.0+
- REST namespace: `storelink/v1`
- Composer PSR-4: `StoreLink\` → `includes/`
- Admin: PHP settings page (no React in this version)
- Custom tables: `storelink_customers`, `storelink_sessions`

## Telegram first

Shopping and store-ops live in `BotEngine`. Telegram and Bale share that contract (`BaleGateway` is a Telegram-compatible adapter). Eitaa, Rubika, and Instagram wait until this contract is stable.

## Layers

| Layer | Responsibility |
|-------|----------------|
| GatewayInterface | Translate messenger payloads to IncomingUpdate / OutgoingMessage |
| GatewayRegistry | Register adapters (`storelink_register_gateways`) |
| BotEngine | Customer shop + admin order ops |
| CatalogService / OrderService / AdminOrderService / CheckoutService | WooCommerce product, coupon, shipping, and order APIs |
| OrderNotifier | New-order and optional status alerts to admin chats; buyer status, refunded, shipped, tracking gated by settings |
| Session / Customer repositories | Chat state and identities |
| TokenVault | Encrypt bot tokens at rest |
| Admin settings | Tabs: General (shared), Telegram, Bale (connection). Tokens, webhook, public HTTPS base, SOCKS proxy, HTTPS API relay, admin user IDs |
| ChannelPublisherInterface | Plain channel posts; `MessengerChannelPublisher` for Telegram/Bale; `ChannelPublishQueue` for bulk |

## Telegram flow

```
Webhook POST /storelink/v1/webhooks/telegram
  → secret header check
  → TelegramGateway::parse_update
  → BotEngine (session + WooCommerce)
  → TelegramGateway::send
```

Customer: catalog, search, variable attributes, cart (+/−), checkout (contact, province/city, shipping rates, coupon), my orders, download links when WC grants access.

Admin (numeric user IDs per messenger, after `/start`): order list with WC status filters, detail, on-hold/processing/completed/cancelled via `$order->update_status()`. Open order in WooCommerce admin. New-order alerts go to each enabled messenger.

```
woocommerce_new_order / checkout processed / bot OrderService::create
  → OrderNotifier → admin chats (Telegram and/or Bale)

woocommerce_order_status_changed
  → OrderNotifier → buyer chat (`_storelink_chat_id`) + download URLs when permitted

storelink_product_changed
  → ChannelPublishQueue::sync → MessengerChannelPublisher

admin Queue publish
  → as_enqueue_async_action storelink_channel_publish_product
  → ChannelPublishQueue::run
```

Outbound PHP → `api.telegram.org` may use SOCKS/HTTP proxy (V2Ray) or an HTTPS relay (`telegram_relay`, e.g. Cloudflare Worker). Inbound webhooks still need public HTTPS. Test with settings `getMe`.

## Extension hooks

- `storelink_register_gateways`
- `storelink_telegram_api_base` (default Telegram)
- `storelink_bale_api_base` (Bale: `https://tapi.bale.ai`)
- `storelink_product_changed` (`WC_Product`, reason)
- `ChannelPublisherInterface::publish_product` / `update_post`
- `storelink_register_tracking_providers`

Shipment tracking (M22) lives on WooCommerce order meta (`_storelink_tracking_*`). Carriers implement `ProviderInterface` (manual courier, Iran Post, Tipax). Iran Post and Tipax only return an official HTTPS tracking page; they do not scrape status. WP-Cron `storelink_refresh_tracking` runs hourly but skips providers with `can_refresh() === false`. Saving a number notifies the Telegram buyer chat when `_storelink_platform` is `telegram`.

## Not in M0–M21

Messenger customers live in `storelink_customers` plus order meta (`_storelink_platform`, `_storelink_chat_id`). The bot does not create a WordPress user.

Payment is the WooCommerce checkout URL. The bot does not capture cards.

Channel posts are plain text from StoreLink caption checkboxes (name, price, stock, link on by default; short description off). Caption is capped at 1024 characters. Manual publish of product IDs or one category is queued with WooCommerce Action Scheduler (`storelink_channel_publish_product`). No AI.

Order notifications are gated on WooCommerce → StoreLink. Defaults: admin new-order on, buyer generic status on. Refunded/shipped templates, tracking messages, and admin status alerts start off. Buyer chat requires `_storelink_chat_id`. WooCommerce email is unchanged.

There is no shipment live-status API. Tracking numbers and carriers are order meta (M22). Extra carriers register on `storelink_register_tracking_providers`.

API, webhook, and channel failures are written with `wc_get_logger()` under source `storelink`. Tokens are stripped before write. WooCommerce → Status → Logs is the viewer. There is no separate log page.
