<?php require __DIR__ . '/partials/layout.php';
head('Detail Pesanan', 'detail');
topbar('Detail Pesanan', true); ?>
<div id="detail" data-id="<?= (int)($_GET['id'] ?? 0) ?>">
    <div class="skel"></div>
    <div class="skel"></div>
</div>
<div class="cta"><button class="btn-main" id="act" hidden></button></div>
<?php foot();
