<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__.'/../app/bootstrap.php';
if ($argc !== 3) {
  fwrite(STDERR, "Usage: php scripts/create-admin.php <username> <email>\nSet SENOESA_ADMIN_PASSWORD before running.\n");
  exit(2);
}
$password=getenv('SENOESA_ADMIN_PASSWORD') ?: '';
if(strlen($password)<8){fwrite(STDERR,"SENOESA_ADMIN_PASSWORD must be at least 8 characters.\n");exit(2);}
if(!filter_var($argv[2],FILTER_VALIDATE_EMAIL)){fwrite(STDERR,"Invalid email address.\n");exit(2);}
try{
  q("INSERT INTO users(username,email,password_hash,phone,role) VALUES(?,?,?,NULL,'admin')",[$argv[1],$argv[2],password_hash($password,PASSWORD_DEFAULT)]);
  fwrite(STDOUT,"Admin account created.\n");
}catch(PDOException $e){fwrite(STDERR,"Could not create account: username or email may already exist.\n");exit(1);}
