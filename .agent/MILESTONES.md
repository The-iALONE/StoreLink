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

Local login and ngrok: [LOCAL.md](LOCAL.md)

## Later (after Telegram shop is the source of truth)

Clone the Telegram gateway: Bale extras, Eitaa, Rubika, Instagram. AI channel captions and post edits (hooks already exist).

Do not start those messengers until M12–M14 stay the shopping/admin contract.

## PHP Note

Required extensions: mysqli, openssl, json. Enable `mbstring` for Persian CLI tools if needed.
