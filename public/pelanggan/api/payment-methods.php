<?php
require __DIR__ . '/../../../app/bootstrap.php';
require_role('pelanggan');
json(['items' => q('SELECT id,nama,gambar FROM metode_pembayaran WHERE aktif ORDER BY id')->fetchAll()]);
