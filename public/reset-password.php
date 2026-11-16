<?php
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Referrer-Policy: no-referrer');
header('Location: forgot-password.php', true, 302);
exit;
