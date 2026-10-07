<?php
//
require_once __DIR__ . '/../../helpers/auth.php';

$user = requireRole(['admin']);
jsonResponse(['message' => 'Halo Admin, akses diterima', 'user' => $user]);