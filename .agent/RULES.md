# PricePilot Development Rules

Before starting any task, read the relevant official documentation.

## Mandatory Checks

1. Read WordPress Plugin Handbook sections related to the task.
2. Read WooCommerce Code Reference for any product/pricing API usage.
3. Read REST API Handbook for endpoint changes.
4. Log which docs were consulted in task notes or commit messages.

## WordPress Core (Strict)

- **Under no circumstances** modify, edit, delete, or patch WordPress core files.
- This includes everything under `wp-admin/`, `wp-includes/`, and root core files (e.g. `wp-config.php`, `index.php`, `wp-load.php`).
- All changes must stay inside this plugin (`PricePilot`) or other approved extensions — never in core.

## Code Rules

- Follow WordPress Coding Standards.
- Never write directly to `_price` or `_regular_price` postmeta — use `WC_Product` APIs.
- Database schema changes only through `Migrator` + `dbDelta()`.
- All user-facing strings use text domain `pricepilot`.
- Capability default: `manage_woocommerce` (filterable via `pricepilot_capability`).
- Every REST endpoint requires permission callback + nonce.
- Bulk operations must preview before apply — no direct writes from filter actions.
- Paginate product queries — never load entire catalog into memory.

## Internationalization

- Supported locales: `fa_IR` (default), `en_US`
- User preference stored in user meta (`pricepilot_admin_locale`)
- Translation files in `languages/` (.po, .mo, .json)
- Rebuild translations: `npm run i18n`
- PHP for i18n scripts: auto-detects `C:\xampp\php\php.exe` or set `PHP_BIN` env var

## Security

- Sanitize inputs, escape outputs.
- Prepared SQL only in repositories.
- Validate import files (extension, MIME, size) before processing.

## Performance

- Max 100 products per page in list queries.
- Bulk apply in batches of 50.
- Dashboard stats cached 5 minutes via transient.
