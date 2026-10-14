<?php
// fitur_admin/edit_menu.php
$kategori = ['Makanan', 'Minuman', 'Snacks'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Menu - Admin</title>
    <link rel="stylesheet" href="../asset/style.css">
</head>
<body class="admin-pesanan-bg">
    <div class="admin-pesanan-page">

        <h1 class="admin-pesanan-title">Admin</h1>

        <div class="admin-pesanan-actions">
            <a href="../admin/dashboard_admin.php" class="admin-icon-btn" aria-label="Kembali">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M15 6l-6 6 6 6" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </a>
            <button type="button" class="admin-icon-btn" aria-label="Refresh" onclick="location.reload()">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M4 12a8 8 0 0 1 13.7-5.6L20 8M20 4v4h-4M20 12a8 8 0 0 1-13.7 5.6L4 16M4 20v-4h4"
                          fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </button>
        </div>

        <div class="admin-pesanan-panel">
            <?php foreach ($kategori as $k): ?>
                <a href="edit_menu_kategori.php?kategori=<?= urlencode(strtolower($k)) ?>" class="admin-order-row admin-cat-row">
                    <span class="admin-order-user"><?= htmlspecialchars($k) ?></span>
                </a>
            <?php endforeach; ?>
        </div>

    </div>
</body>
</html>