# PricePilot Milestones

## Environment Snapshot

| Component | Version | Status |
|-----------|---------|--------|
| WordPress | 7.1 | OK |
| WooCommerce | 11.1.0 | Installed |
| PHP (XAMPP) | 8.3+ | OK |
| Node.js | 26.3.0 | OK |

## Milestone Progress

- [x] M0 — Dev rules, project init
- [ ] M1 — Plugin bootstrap
- [ ] M2 — Products + filters
- [ ] M3 — Bulk pricing engine
- [ ] M4 — Preview + confirmation UI
- [ ] M5 — Undo + history
- [ ] M6 — Import / export
- [ ] M7 — Dashboard + UI polish
- [ ] M8 — Security hardening
- [ ] M9 — Testing
- [ ] M10 — Production cleanup

## PHP Upgrade Note

Required extensions: mysqli, gd, zip, mbstring, intl, fileinfo, openssl.
Enable `mbstring` in `php.ini` if currency detection or Persian text handling fails in CLI.
