<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__.'/../app/bootstrap.php';
if ($argc !== 2) {
  fwrite(STDERR, "Usage: php scripts/reset-admin-password.php <admin-email>\nSet SENOESA_ADMIN_PASSWORD before running.\n");
  exit(2);
}
$password=getenv('SENOESA_ADMIN_PASSWORD') ?: '';
if(strlen($password)<8){fwrite(STDERR,"SENOESA_ADMIN_PASSWORD must be at least 8 characters.\n");exit(2);}
$updated=q("UPDATE users SET password_hash=? WHERE email=? AND role='admin'",[password_hash($password,PASSWORD_DEFAULT),$argv[1]])->rowCount();
if(!$updated){fwrite(STDERR,"No admin account found for that email.\n");exit(1);}
fwrite(STDOUT,"Admin password updated.\n");
