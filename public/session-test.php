
<?php
require __DIR__ . '/../app/bootstrap.php';

echo "Session ID: " . session_id() . PHP_EOL;
echo "Session status: " . session_status() . PHP_EOL;
echo "Session data:\n";
var_export($_SESSION);
