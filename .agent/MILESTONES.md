# StoreLink Milestones

## Environment Snapshot

| Component | Version | Status |
|-----------|---------|--------|
| WordPress | 7.1 | OK |
| WooCommerce | 11.1.0 | Installed |
| PHP (XAMPP) | 8.3+ | OK |

## Milestone Progress

- [x] M0 — Dev rules, project init
- [x] M1 — StoreLink bootstrap, Migrator, PricePilot removal
- [x] M2 — Messenger contracts, registry, TokenVault
- [x] M3 — WooCommerce catalog and orders
- [x] M4 — Bot conversation, session cart, checkout
- [x] M5 — Telegram webhook, secret, inline buttons
- [x] M6 — Admin settings page
- [x] M7 — Channel/AI hooks without implementation
- [x] M8 — Webhook secret, rate limit, encrypted tokens
- [x] M9 — Fake gateway unit tests
- [x] M10 — i18n, readme, production cleanup
- [x] M11 — Bale gateway (Telegram-compatible Bot API, frozen until Telegram is complete)
- [x] M12 — Telegram shop: cart qty, search, my orders, contact keyboard, outbound proxy
- [x] M13 — Telegram admin: user IDs, order list/detail, WooCommerce status
- [x] M14 — Telegram admin alerts on new WooCommerce orders
- [x] M15 — Docs: Telegram-first; no extra Bale/Eitaa/Rubika/Instagram work yet
- [x] M16 — Telegram on Iranian shared hosting: settings `getMe` test + HTTPS API relay (Cloudflare Worker sample) plus SOCKS; inbound webhook still public HTTPS
- [x] M17 — Variable products (step attributes, variation on the WC order), WooCommerce coupons, shipping cost + province/city from WC shipping methods
- [x] M18 — Standard WooCommerce statuses in the bot: filters pending / on-hold / processing / completed / cancelled / failed; notify the customer chat on status change; pay-again for pending/failed. Payment is only the WooCommerce gateway URL. After a successful gateway callback WooCommerce sets the status (usually processing for physical goods). The bot does not create a payment gateway.
- [x] M19 — Virtual/downloadable products: WC download links in the bot after the order is completed (or when WooCommerce grants download access)
- [x] M20 — Unfreeze Bale: same Telegram shop contract (cart, search, my orders, admin) on `BaleGateway`, no extra Bale-only features
- [x] M21 — Channel publish + edit posts on price/stock change (`storelink_product_changed` from M7). Plain captions, no AI. An unchanged caption or a temporary API error must not create a second post. A new post is only sent when no message id is stored, or Telegram/Bale says that message is gone.
- [x] M22 — Shipment tracking on the WooCommerce order (tracking number, carrier, history). Show it in the buyer chat and in the status message. Extensible providers: manual/local courier first, then Iran Post and Tipax if their APIs are usable. WP-Cron only when a provider can refresh status. Do not hard-code one carrier. Tracking API and cron failures go to the existing WooCommerce log source `storelink`.
- [x] M23 — Bot catalog browse by WooCommerce product category, in addition to the flat list and search.
- [x] M24 — Notification toggles, split by customer vs store admin. Keep today’s M14 new-order alert and M18 status message as the defaults. Extra events (refunded as its own template, shipped, tracking added) stay off until enabled.
- [x] M25 — Channel controls beyond the M21 plain post: optional short description, which fields are included, and manual publish of selected products or one category. Bulk jobs use Action Scheduler. No AI captions.
- [x] M26 — StoreLink settings tabs: General, Telegram, Bale. Shared options live only on General. Each messenger tab is connection only. No inheritance.

Local login and ngrok: [LOCAL.md](LOCAL.md)

Feature how-tos (Persian): [آموزش فیچر ها/README.md](../آموزش فیچر ها/README.md)

## Market (RTL-Theme / Zhaket, ~31 Shahrivar 1405)

Do not copy full competitor catalogs. Close comment-thread pain that stops a purchase. Skip for now: loyalty points, referrals, WebApp, Flow Builder, n8n, AI chatbot, live operator, wallets, Gravity Forms, multi-bot, Snapp Pay / Azki.

| Product | Sales / rating | Positioning |
|---------|----------------|-------------|
| [Boti](https://www.rtl-theme.com/boti-wordpress-plugin/) | 66 sales, 76% product (6 votes), 83% support (229 votes), v1.1.0 | Telegram + Bale in-chat shop. Volume leader. Requires foreign host for Telegram. Bale Pay without e-namad. |
| [Woojox](https://www.zhaket.com/web/wojoox-plugin) | 9 sales, 4.8/5 (4 votes), 1.5M toman, v0.0.5 | Telegram-only kitchen sink (AI, n8n, tickets, wallet). Optional proxy. One bot per install. |
| [Balegram](https://www.rtl-theme.com/balegram-plugin-wordpress/) | 14 sales, 100% (tiny sample), last update ~2 months | Telegram + Bale channel blast + in-chat cart. Sells Cloudflare/Deno anti-filter. Quiet after launch. |

**Boti (shop):** dual messengers, inline search, Web App, Bale Pay, force-join, simple + variable products, add-ons, wishlist, coupons, guest checkout, custom checkout fields, min order, WC gateways, abandoned cart, broadcasts, loyalty, invites, sales dashboard, user block. v1.1.0 added Bale Pay as a WC gateway, digital download links, country/province/city, shipping cost, force-join bugfixes.

**Woojox (ops):** catalog/search/cart, link Telegram to WC customer, orders/downloads/addresses/wallet, custom menus, force-join, live operator, FAQ/AI, flows, restock/price-drop alerts, broadcasts, digital delivery (license, file, login), n8n webhooks, FA/EN/AR, blacklist, backup. Reviews praise support; Q&A wants multi-bot + Bale/Rubika for national-net. SMS module had bugs.

**Balegram (channel):** bulk send with SEO caption/hashtags/buy buttons, live stock/price sync and “out of stock” edits, Cloudflare/Deno proxy, mini CRM, force-join, installment badges, cart +/−, coupons, shipping on invoice, sales split by messenger. Comments: connect without proxy (only works if host can reach Telegram); enable Bale wallet (not shipped); private per-user channel after purchase (Bale only).

**Why Boti sells and still scores 76%:** in-chat checkout + Bale Pay. Gaps in comments: Telegram blocked on IR hosts, no seller ping in Bale after paid order (until they added more notifies), digital goods were “coming later”, order status not pushed to the buyer, shipping/city/variations/coupons missing until 1.1.0. StoreLink now covers Telegram shop + admin alerts, IR-host relay, WC statuses to the buyer, digital download links, Bale on the same shop contract, channel posts with caption fields, and Action Scheduler bulk publish. Still later: Bale Pay, force-join, broadcasts.

## Later (after M22–M25)

Rubika, Eitaa, Soroush Plus, Bale Pay, force-join, abandoned cart, broadcasts. AI captions stay after the plain channel post. Do not start those, and do not start M22–M25, until that milestone is the active task. M0–M21 are closed. API, webhook, and channel failures already go to WooCommerce → Status → Logs, source `storelink`, with tokens redacted. There is no separate log page.

## PHP Note

Required extensions: mysqli, openssl, json. Enable `mbstring` for Persian CLI tools if needed.
