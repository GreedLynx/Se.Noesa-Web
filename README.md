# Se.Noesa – Web Owner (PHP native + PostgreSQL)
```
senoesa/
├─ config/config.php        # kredensial DB
├─ app/bootstrap.php        # PDO, session, helper json()/q()/require_owner()
├─ database/schema.sql      # 5 tabel + data contoh
└─ public/                  # <- document root
   ├─ index.php             # splash animasi
   ├─ login.php register.php dashboard.php orders.php order-detail.php products.php
   ├─ partials/layout.php   # head, topbar, nav, foot
   ├─ api/                  # auth, dashboard, orders, products (JSON)
   └─ assets/{css,js,img}   # app.css, app.js, gambar dari desain
```
Jalankan: `createdb senoesa && psql senoesa -f database/schema.sql` lalu `php -S localhost:8000 -t public`
(ekstensi `pdo_pgsql` harus aktif). Login: owner@senoesa.id / owner123
