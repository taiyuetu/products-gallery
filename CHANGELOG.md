# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Planned

- Optional role management UI for creating non-admin users
- Login rate limiting

## [1.1.0] — 2026-07-15

### Security

- Whitelist product list `sort` columns (controller + `Model::buildOrder`) to prevent ORDER BY SQL injection
- Stop echoing raw exception / PDO messages in the browser; log details with `error_log` instead
- Restrict **清空数据** (`deleteAll`) and category delete to `admin` role
- Raise first-run admin password minimum length from 6 to 8 characters

### Fixed

- Align `setup.sql` database name with config (`productsgallery`)
- Ship `config/database.example.php`; keep real credentials in gitignored `database.php`

### Changed

- README updated for multi-image `gallery` (JSON) instead of single `image_path`
- Document versioning via `VERSION` + this changelog

### Added

- Git version control (`.gitignore`, initial repository)
- `VERSION` file and `CHANGELOG.md`

## [1.0.0] — 2026-05-14

### Added

- Product CRUD with column-driven config (`config/columns.php`)
- CSV import with Chinese encoding options and OEM merge/skip rules
- CSV export (UTF-8 BOM) for full or filtered result sets
- Multi-image product gallery (`gallery` JSON column) with lightbox UI
- Bulk image attach on import by matching `TQB编码` filenames
- OEM matching tool (token-normalized match + download)
- Category management
- Session authentication with first-run admin setup, CSRF on POSTs, bcrypt passwords

[Unreleased]: https://github.com/local/productsdata-gallery/compare/v1.1.0...HEAD
[1.1.0]: https://github.com/local/productsdata-gallery/compare/v1.0.0...v1.1.0
[1.0.0]: https://github.com/local/productsdata-gallery/releases/tag/v1.0.0
