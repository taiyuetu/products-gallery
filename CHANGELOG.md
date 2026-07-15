# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Planned

- Org/team workspaces
- Billing / plan limits
- Login rate limiting

## [2.0.0] — 2026-07-15

### Breaking

- Product columns are no longer global (`config/columns.php` deprecated)
- `products` table replaced with slim schema: `user_id`, `primary_value`, `oem_value`, `gallery`, `attrs` (JSON)
- `categories` now require `user_id`
- Migration `004_saas_per_user_json.sql` drops and recreates products/categories (re-import required)
- Uploads path is now `public/uploads/products/{userId}/`

### Added

- Per-user multi-tenant isolation (each account owns its catalog)
- `user_fields` table and Field management UI (`?c=field`)
- CSV import creates/extends that user’s field schema from headers
- Open user registration (`?c=auth&a=register`)
- `core/Auth.php` tenant helpers

### Changed

- Import upsert keys on per-user `primary_value` (first CSV column by default)
- OEM match scoped to current user and configured OEM field
- Owner can clear their own product data (no cross-tenant wipe)

## [1.1.0] — 2026-07-15

### Security

- Whitelist product list `sort` columns
- Stop echoing raw exception / PDO messages in the browser
- Restrict destructive category delete / clearer admin UX
- Raise first-run admin password minimum length to 8

### Fixed

- Align `setup.sql` database name with config (`productsgallery`)
- Ship `config/database.example.php`

### Added

- Git version control, `VERSION`, `CHANGELOG.md`

## [1.0.0] — 2026-05-14

### Added

- Fixed-schema product CRUD, CSV import/export, gallery, OEM match, categories, session auth

[Unreleased]: https://github.com/taiyuetu/products-gallery/compare/v2.0.0...HEAD
[2.0.0]: https://github.com/taiyuetu/products-gallery/compare/v1.1.0...v2.0.0
[1.1.0]: https://github.com/taiyuetu/products-gallery/compare/v1.0.0...v1.1.0
[1.0.0]: https://github.com/taiyuetu/products-gallery/releases/tag/v1.0.0
