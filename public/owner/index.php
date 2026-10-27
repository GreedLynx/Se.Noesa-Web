<?php
require __DIR__.'/../../app/bootstrap.php';
if (me() && me()['role'] === 'owner') { header('Location: dashboard.php'); exit; }
header('Location: ../login.php'); exit;
