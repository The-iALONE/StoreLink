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

Shopping and store-ops live in `BotEngine`. Telegram is the complete client. Bale is a thin Telegram-compatible adapter only; Eitaa, Rubika, and Instagram wait until this contract is stable.

## Layers

| Layer | Responsibility |
|-------|----------------|
| GatewayInterface | Translate messenger payloads to IncomingUpdate / OutgoingMessage |
| GatewayRegistry | Register adapters (`storelink_register_gateways`) |
| BotEngine | Customer shop + admin order ops |
| CatalogService / OrderService / AdminOrderService | WooCommerce product and order APIs |
| OrderNotifier | New-order alerts to Telegram admin chats |
| Session / Customer repositories | Chat state and identities |
| TokenVault | Encrypt bot tokens at rest |
| Admin settings | Tokens, webhook, public HTTPS base, Telegram proxy, admin user IDs |
| ChannelPublisherInterface | Future channel/AI posts |

## Telegram flow

```
Webhook POST /storelink/v1/webhooks/telegram
  → secret header check
  → TelegramGateway::parse_update
  → BotEngine (session + WooCommerce)
  → TelegramGateway::send
```

Customer: catalog, search, cart (+/−), checkout (request_contact), my orders.

Admin (Telegram numeric user IDs in settings, after `/start`): order list, detail, processing/completed/cancelled via `$order->update_status()`.

```
woocommerce_new_order / checkout processed / bot OrderService::create
  → OrderNotifier → Telegram admins
```

Outbound PHP → `api.telegram.org` may use SOCKS/HTTP proxy (V2Ray). Inbound webhooks still need public HTTPS.

## Extension hooks

- `storelink_register_gateways`
- `storelink_telegram_api_base` (default Telegram)
- `storelink_bale_api_base` (Bale: `https://tapi.bale.ai`)
- `storelink_product_changed` (`WC_Product`, reason)
- `ChannelPublisherInterface::publish_product` / `update_post`
