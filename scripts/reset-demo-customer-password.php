<?php
// Reset only the seeded demo customer account; does not modify other users.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/../app/bootstrap.php';

$email = 'andi@mail.com';
$password = 'pelanggan123';
$updated = q(
    "UPDATE users SET password_hash = ? WHERE email = ? AND role = 'pelanggan'",
    [password_hash($password, PASSWORD_DEFAULT), $email]
)->rowCount();

if (!$updated) {
    fwrite(STDERR, "Demo customer account not found: {$email}\n");
    exit(1);
}

fwrite(STDOUT, "Demo customer password reset successfully.\n");
