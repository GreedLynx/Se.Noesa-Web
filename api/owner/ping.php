<?php
// ENDPOINT UJI: hanya owner. Hapus sebelum demo final.
require_once __DIR__ . '/../../helpers/auth.php';

$user = requireRole(['owner']);
jsonResponse(['message' => 'Halo Owner, akses diterima', 'user' => $user]);