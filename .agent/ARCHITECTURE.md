# PricePilot V1 Architecture Snapshot

## Stack

- PHP 8.3+ (plugin requirement)
- WordPress 7.1+
- WooCommerce 8.0+
- REST API namespace: `pricepilot/v1`
- React admin via `@wordpress/scripts`
- Composer PSR-4 autoload: `PricePilot\` → `includes/`
- Custom tables: operations + operation_items

## Layers

| Layer | Responsibility |
|-------|----------------|
| REST Controllers | HTTP, validation, response shape |
| Services (Pricing, ImportExport) | Business logic |
| Repositories | Database access |
| ProductReader/Updater | WooCommerce product I/O |
| FilterRegistry | Extensible product filters |
| Admin React | UI only — no business logic |

## Key Flows

1. **Bulk pricing**: Preview (read-only) → Apply (batched write) → Log → Undo (restore snapshot)
2. **Import**: Upload → Validate rows → Partial apply → Error report
3. **Dashboard**: Transient-cached WC aggregate queries
