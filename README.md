# Product Database Web App (PHP + MySQL)

**Version:** see [`VERSION`](VERSION) · **Changes:** see [`CHANGELOG.md`](CHANGELOG.md)

A modular PHP + MySQL product management app with:

- CSV import to database (supports Chinese text)
- **Product image gallery** — multiple images per product; upload on create/edit, or bulk-attach via CSV import by matching the `TQB编码` filename
- Full CRUD pages
- Column-based filtering and global search
- Export all products or filtered results to CSV
- Category management
- OEM matching tool
- Authentication (first-run admin setup; admin-only destructive actions)

---

## 1) Requirements

- PHP 8.1+ (CLI enabled)
- MySQL 5.7+ / 8.0+
- PHP extensions: `mbstring`, `pdo_mysql`, `fileinfo`, `gd` (optional, only for future image processing)

---

## 2) Project Structure

```text
productsdata-gallery/
├── index.php                 # front controller (routing: ?c=...&a=...)
├── VERSION                   # current semver
├── CHANGELOG.md              # release notes
├── setup.sql                 # first-time DB setup (database: productsgallery)
├── migrations/
│   ├── 001_add_image_path.sql
│   ├── 002_add_category_id.sql
│   └── 003_convert_image_to_gallery.sql
├── config/
│   ├── database.example.php  # copy → database.php (gitignored)
│   ├── database.php          # local credentials (do not commit)
│   └── columns.php           # single source of truth for columns
├── core/
│   ├── Controller.php
│   ├── Database.php
│   ├── Model.php
│   └── ImageHelper.php
├── controllers/
├── models/
├── views/
└── public/
    ├── css/app.css
    └── uploads/products/     # uploaded product images
```

---

## 3) Database Setup

### First-time install

1. Start MySQL service.
2. Run `setup.sql` in your MySQL client (creates database `productsgallery`).
3. Copy the example config and edit credentials:

```powershell
copy config\database.example.php config\database.php
```

```php
// config/database.php
return [
    'host'     => 'localhost',
    'port'     => 3306,              // match your MySQL port
    'dbname'   => 'productsgallery',
    'username' => 'root',
    'password' => '',                // update this
    'charset'  => 'utf8mb4',
];
```

4. On first visit the app detects an empty `users` table and redirects to a one-time setup page to create the administrator account (password ≥ 8 characters).

### Upgrading an existing database

Run migrations in order if upgrading from an older schema:

```sql
USE productsgallery;
SOURCE migrations/001_add_image_path.sql;
SOURCE migrations/002_add_category_id.sql;
SOURCE migrations/003_convert_image_to_gallery.sql;
```

Migration `003` converts single `image_path` into multi-image `gallery` (JSON array of relative paths) and drops `image_path`.

Fresh installs from current `setup.sql` already include `gallery` and `category_id` — skip migrations.

---

## 4) Run Locally

In project root:

```powershell
php -S 127.0.0.1:3333 -t .
```

Open <http://127.0.0.1:3333/> — first request redirects to login (or admin setup if no users exist).

---

## 5) Main Features

### CSV Import

- Entry: `?c=import&a=index`
- Upload `.csv` file; encoding: Auto / GBK / UTF-8
- Header names must match Chinese labels in `config/columns.php`
- Rows with a **new TQB编码** → inserted
- Rows with an **existing TQB编码** and a strict-subset OEM号码 (and no image to attach) → skipped
- Rows with an **existing TQB编码** and additional OEM号码 → OEM merged (slash-separated, deduplicated) and other fields updated

### Product image gallery

Each product can have **multiple** images. Files live under `public/uploads/products/`; the DB stores a JSON array of relative paths in `products.gallery`.

**Create / Edit** (`?c=product&a=create` / `edit`)

- Multi-file upload with live preview
- On edit: per-image remove checkboxes, or remove all
- Allowed: `jpg`, `jpeg`, `png`, `gif`, `webp`; max 8 MB; MIME sniffed via `finfo`

**Bulk attach via CSV import**

1. Name each image after the row’s `TQB编码` (e.g. `TQB0-0002.jpg`). Multiple files for the same code (e.g. `TQB0-0002 (2).jpg`) merge into one gallery.
2. On the import page, select the CSV and optionally the image files (Chrome/Edge: “选择整个文件夹”).
3. Matched images are merged into each product’s gallery; unmatched uploads are deleted to avoid orphans.

**Display**

- List: thumbnail + count badge; click opens lightbox gallery
- Detail: gallery grid with lightbox navigation

### CRUD

- List / create / show / edit / delete product
- Clear ALL products: POST `?c=product&a=deleteAll` (**admin only**)
- Category delete: **admin only**

### Filtering, search, export

- Global search + multi-column filters (`f[field]=value`)
- Sort columns are whitelisted (safe ORDER BY)
- Export all or filtered CSV (UTF-8 BOM); images are not embedded

### OEM match

- Upload a CSV with an `oem` column; match against stored OEM tokens (normalized spaces/hyphens); download matches

---

## 6) Chinese Text / Encoding

- MySQL/table charset: `utf8mb4`
- PHP output: UTF-8
- Use the import encoding selector for Excel-exported Chinese CSVs
- Exported CSV includes UTF-8 BOM

---

## 7) Extend Columns (Secondary Development)

1. Add column in DB (`ALTER TABLE` + optional `migrations/` file)
2. Add mapping in `config/columns.php` (`field`, `label`, `type`, `filterable`, `list`, `tab`)
3. Add the field to `models/Product.php` `$fillable`

List / filter / form / import / export pick up new fields from config.

`gallery` is intentionally **not** in `columns.php` (not a CSV column); handled by `ProductController` / `ImportController` via `ImageHelper`.

Bump `VERSION` and add a `CHANGELOG.md` entry when you ship a change.

---

## 8) Upload Limits

For large image batches, raise PHP limits in `php.ini`:

```ini
upload_max_filesize = 64M
post_max_size       = 128M
max_file_uploads    = 500
memory_limit        = 256M
max_execution_time  = 300
```

---

## 9) Troubleshooting

### Server starts but page shows error

- Ensure MySQL is running
- Copy/configure `config/database.php` from `database.example.php`
- Confirm `setup.sql` was executed (`productsgallery`)
- Upgrading? Run migrations `001` → `003` as needed

### Images not appearing after import

- Filename (without extension) must equal `TQB编码` (case-insensitive; copy suffixes like `(2)` / `副本` are stripped)
- Confirm `public/uploads/products/` is writable
- Check import result banner for match / missing counts

---

## 10) Security / Production Notes

Designed for internal / LAN use. Before wider exposure:

- HTTPS; secure session cookie flags
- Keep using admin role for destructive actions; add user-management UI if needed
- CSRF is already required on mutating POSTs
- Do not commit `config/database.php`
- Deny directory listing on `public/uploads/products/`
- Backup uploads together with the database
- Exception details go to the server log only (not the browser)

---

## 11) Version control

```powershell
git status
git log --oneline
```

Release process:

1. Update code
2. Bump `VERSION`
3. Add a section under `CHANGELOG.md`
4. Commit and tag, e.g. `git tag v1.1.0`
