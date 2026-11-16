const assert = require("node:assert/strict");
const fs = require("node:fs");

const schema = fs.readFileSync("database/schema.sql", "utf8");
const migration = fs.readFileSync(
  "database/migrations/017_default_quota_50.sql",
  "utf8",
);
const form = fs.readFileSync("public/admin/products.php", "utf8");
const adminJs = fs.readFileSync("public/admin/assets/admin.js", "utf8");
const productApi = fs.readFileSync("public/admin/api/products.php", "utf8");

assert.match(schema, /kuota_harian INT NOT NULL DEFAULT 50/);
assert.match(
  migration,
  /ALTER TABLE produk\s+ALTER COLUMN kuota_harian SET DEFAULT 50/i,
);
assert.match(migration, /UPDATE produk\s+SET kuota_harian = 50/i);
assert.doesNotMatch(migration, /DELETE\s+FROM\s+kuota_harian/i);
assert.match(form, /name="kuota_default"[^>]*value="50"/);
assert.match(adminJs, /p\?\.kuota_default \?\? 50/);
assert.match(
  productApi,
  /\$request\['kuota_default'\] \?\? \$request\['kuota_harian'\] \?\? 50/,
);

console.log(
  "PASS: Produk lama/baru memakai default kuota harian 50 dan override tidak dihapus.",
);
