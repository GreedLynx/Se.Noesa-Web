<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

$user = me();

if (!$user) {
    header('Location: /login.php');
    exit;
}

$target = match ($user['role'] ?? '') {
    'admin'     => '/admin/dashboard_admin.php',
    'owner'     => '/owner/dashboard_owner.php',
    'pelanggan' => '/dashboard.php',
    default     => '/dashboard.php',
};

header('Location: ' . $target);
exit;