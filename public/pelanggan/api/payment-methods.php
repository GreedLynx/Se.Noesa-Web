<?php
require __DIR__ . '/../../../app/bootstrap.php';
require_role('pelanggan');

// SELECT hanya menampilkan metode pembayaran yang sedang aktif untuk pelanggan.
$items = q('SELECT id, nama, gambar FROM metode_pembayaran WHERE aktif ORDER BY id')->fetchAll();
json(['items' => $items]);
