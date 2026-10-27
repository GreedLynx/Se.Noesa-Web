# Se.Noesa Web

PHP native and PostgreSQL application with separate owner, admin, and customer areas. The interface uses CSS and JavaScript for responsive layouts, animated metrics, charts, and navigation.

## Project layout

```text
app/                         Shared database, session, and access helpers
config/config.php            PostgreSQL connection settings
database/schema.sql          Initial schema and sample data
database/migrations/         Safe changes for an existing database
scripts/create-admin.php     Create an admin account from the CLI
public/
  api/auth.php                Shared login, registration, and logout API
  assets/                     Shared owner/customer assets
  owner/                      Owner dashboard, orders, products, and APIs
  admin/                      Admin dashboard, payment review, product CRUD, and APIs
  pelanggan/                  Customer menu, cart, checkout, and order history
  dashboard.php               Compatibility redirect to owner/dashboard.php
```

## Setup

1. Configure the `db` values in `config/config.php` and enable PHP's `pdo_pgsql` extension.
2. Create the PostgreSQL database. On a new, empty database only, initialize the schema with `psql senoesa -f database/schema.sql`. The schema file drops and recreates its tables, so do not rerun it on a database containing data. For an existing database, apply `php scripts/apply-product-migration.php` (or `psql senoesa -f database/migrations/001_product_min_order.sql`) instead.
3. Create an admin account. In PowerShell, set a password of at least eight characters and run the CLI script:

   ```powershell
   $env:SENOESA_ADMIN_PASSWORD = 'use-a-private-password'
   php scripts/create-admin.php admin admin@senoesa.id
   Remove-Item Env:SENOESA_ADMIN_PASSWORD
   ```

4. Start the development server from the project root: `php -S localhost:8000 -t public`.
5. Open `http://localhost:8000/`. Login routes each account by its database role. Admins can also open `/admin/` directly. Customer registration creates a `pelanggan` account; the seeded demo customer is `andi@mail.com` / `pelanggan123`.

Admin and customer menus use the same PostgreSQL `produk` table. Products saved in Admin appear automatically in the customer catalogue when their remaining stock is greater than zero. Product pictures are uploaded by the admin as JPG, PNG, or WebP files up to 2 MB. Customer orders are saved with item-price snapshots, checked against active product stock and kitchen capacity, and appear in the admin validation queue. Customers can track status and upload JPG, PNG, or WebP payment proof up to 2 MB. The customer checkout requires an active `jadwal_po` schedule whose date range includes the selected delivery day.

New public registrations receive the `pelanggan` role. Owner accounts remain in the owner area. Admin accounts are created only through the CLI script.
