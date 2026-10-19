<?php
session_start();
function cfg(){ static $c; return $c ??= require __DIR__.'/../config/config.php'; }
function db(): PDO {
  static $p; if($p) return $p; $c=cfg()['db'];
  return $p = new PDO("pgsql:host={$c['host']};port={$c['port']};dbname={$c['name']}",$c['user'],$c['pass'],
    [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
}
function q(string $sql,array $a=[]): PDOStatement { $s=db()->prepare($sql); $s->execute($a); return $s; }
function json($d,int $code=200){ http_response_code($code); header('Content-Type: application/json'); echo json_encode($d,JSON_NUMERIC_CHECK); exit; }
function body(): array { return json_decode(file_get_contents('php://input'),true) ?: $_POST; }
function me(){ return $_SESSION['user'] ?? null; }
function require_owner(bool $api=true){
  $u=me();
  if(!$u || $u['role']!=='owner'){ if($api) json(['error'=>'Unauthorized'],401); header('Location: login.php'); exit; }
  return $u;
}
