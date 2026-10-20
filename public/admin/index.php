<?php
require __DIR__ . '/../../app/bootstrap.php';
if (me() && me()['role'] === 'admin') { header('Location: dashboard.php'); exit; }
header('Location: ../login.php'); exit;
