<?php
require __DIR__ . '/../../../app/bootstrap.php';
require_owner();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
  json(['items' => q('SELECT id,nama,gambar,aktif FROM metode_pembayaran ORDER BY id DESC')->fetchAll()]);
}

json(['error' => 'Metode request tidak didukung'], 405);
