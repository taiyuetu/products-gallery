# Product Gallery SaaS (PHP + MySQL) — v2.0

**Version:** see [`VERSION`](VERSION) · **Changes:** see [`CHANGELOG.md`](CHANGELOG.md)

Multi-tenant product catalog: **each user has a private product library**. Column fields are **not global** — they are created from each user’s uploaded CSV headers.

---

## What’s new in 2.0 (breaking)

- Per-user data isolation (`user_id` on products, categories, uploads)
- Dynamic schema via `user_fields` + product `attrs` JSON (no fixed wide columns)
- Open **registration** alongside login
- **字段管理** UI to toggle list/filter, set primary & OEM fields
- Images stored under `public/uploads/products/{userId}/`

Existing v1 fixed-column databases must run migration `004` (drops/rebuilds products & categories) and re-import CSVs.

---

## Requirements

- PHP 8.1+
- MySQL 5.7+ / 8.0+ (JSON column support)
- Extensions: `mbstring`, `pdo_mysql`, `fileinfo`

---

## Setup

1. Run [`setup.sql`](setup.sql) (creates DB `productsgallery` and v2 tables).
2. Copy config:

```powershell
copy config\database.example.php config\database.php
```

3. Start:

```powershell
php -S 127.0.0.1:3333 -t .
```

4. First visit → create admin (setup). Later users can **register** their own accounts.

### Upgrading from v1.x

```sql
USE productsgallery;
SOURCE migrations/004_saas_per_user_json.sql;
```

This **deletes** old product/category rows. Re-import CSVs per user.

---

## How fields work

1. User uploads a CSV on **导入 CSV**.
2. Headers become that user’s `user_fields` (new headers are added on later imports; old fields are not auto-deleted).
3. First import: **first column = primary** (upsert + image filename match); a header matching OEM / `OEM号码` becomes the OEM field.
4. Adjust in **字段管理**: list, filter, primary, OEM, active, order, tab group.
5. Product values live in `products.attrs` JSON; `primary_value` / `oem_value` are denormalized for fast match.

Image files should be named after the **primary** field value (e.g. `SKU-001.jpg`).

---

## Features

| Feature | Notes |
|---------|--------|
| Register / Login | Each account is an isolated tenant |
| CSV import + images | Schema sync + upsert by primary |
| CRUD | Forms/list/export driven by user’s fields |
| OEM match | Uses that user’s OEM field / `oem_value` |
| Categories | Per-user |
| Clear my data | Deletes only the logged-in user’s products |

---

## Project structure

```text
index.php
setup.sql
migrations/004_saas_per_user_json.sql
config/database.example.php
core/          Auth, Controller, Database, Model, ImageHelper
models/        User, UserField, Product, Category
controllers/   Auth, Product, Import, Field, Match, Category
views/
public/uploads/products/{userId}/
```

`config/columns.php` is deprecated (empty stub); do not use it for new fields.

---

## Security notes

- Session auth + CSRF on POSTs
- All catalog queries scoped by `user_id`
- Designed for internal / LAN SaaS without billing; add HTTPS before public exposure
