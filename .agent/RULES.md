# StoreLink Development Rules

Before starting any task, read the relevant official documentation.

## Mandatory Checks

1. Read WordPress Plugin Handbook sections related to the task.
2. Read WooCommerce Code Reference for product and order APIs.
3. Read REST API Handbook for endpoint changes.
4. Read Telegram Bot API for webhook, secret_token, and inline keyboards.
5. Read the current [Bale Bot API](https://docs.bale.ai/) before changing Bale (`https://tapi.bale.ai`).
6. Read the current [Rubika Bot API](https://rubika.ir/botapi) and [methods](https://rubika.ir/botapi/methods) before changing Rubika (`https://botapi.rubika.ir`). Use official method names and enums (`updateBotEndpoints` with `ReceiveUpdate` / `ReceiveInlineMessage`). Do not copy Telegram `setWebhook` onto Rubika.
7. If Bale or Rubika docs cannot be opened or a field is missing from the public pages, stop and ask the user to send the official page or PDF. Do not guess the API.
8. Log which docs were consulted in task notes or commit messages.

## WordPress Core (Strict)

- **Under no circumstances** modify, edit, delete, or patch WordPress core files.
- This includes everything under `wp-admin/`, `wp-includes/`, and root core files (e.g. `wp-config.php`, `index.php`, `wp-load.php`).
- All changes must stay inside this plugin — never in core.

## Code Rules

- Follow WordPress Coding Standards.
- Products and orders only through WooCommerce APIs (`WC_Product`, `wc_create_order`, `add_product`). Never write `_price` or order tables directly.
- Database schema changes only through `Migrator` + `dbDelta()`.
- All user-facing strings use text domain `storelink`.
- Capability default: `manage_woocommerce` (filterable via `storelink_capability`).
- Authenticated REST endpoints require permission callback + nonce.
- Public webhook endpoints must verify the messenger secret; reject otherwise.
- Bot tokens are encrypted at rest and never logged or sent to JavaScript.
- Add messengers only by implementing `GatewayInterface` and registering on `storelink_register_gateways`.
- Complete Telegram shop/admin in `BotEngine`; extra messengers only as gateways that translate the same contract. Consult that messenger's official docs first.
- Paginate catalog queries — never load the entire catalog into memory.
- Always re-read price and stock from WooCommerce at order time.
- Do not collect card data or take payment inside the bot. Use WooCommerce checkout payment URL.

## Internationalization

- Supported locales: `fa_IR` (default), `en_US`
- Translation files in `languages/` (.po, .mo)
- PHP for i18n scripts: auto-detects `C:\xampp\php\php.exe` or set `PHP_BIN`

## Security

- Sanitize inputs, escape outputs.
- Prepared SQL only in repositories.
- Rate-limit webhook handling per chat.
- Deduplicate Telegram `update_id`.

## Performance

- Max 5 products per bot catalog page.
- Bulk/channel jobs (future) must use Action Scheduler, not request-time loops.
