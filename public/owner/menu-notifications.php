<?php
require __DIR__ . '/partials/layout.php';
head('Notifikasi Perubahan Menu', 'notifications');
topbar('Notifikasi Perubahan Menu');
?>
<div id="mn-page" data-mode="owner" data-api="../admin/api/menu-notifications.php" data-today="">
    <section class="owner-orders-intro rise mn-owner-intro">
        <div>
            <p class="eyebrow">KOMUNIKASI PELANGGAN</p>
            <h2>Notifikasi perubahan menu</h2>
            <p class="owner-muted">
                Pantau pemberitahuan menu yang dikirim Admin, daftar pelanggan terdampak, dan respons mereka.
                Penggantian menu baru berlaku setelah Admin menyetujui.
            </p>
        </div>
        <span class="mn-readonly">Hanya baca</span>
    </section>

    <section class="mn-stats" id="mn-stats" aria-live="polite"></section>

    <section class="card rise mn-owner-history">
        <div class="owner-row-between mn-owner-heading">
            <div>
                <p class="eyebrow">RIWAYAT</p>
                <h3>Notifikasi terkirim</h3>
            </div>
            <button type="button" class="owner-secondary mn-owner-refresh" id="mn-refresh">Muat ulang</button>
        </div>
        <div class="mn-list" id="mn-list">
            <p class="mn-list-empty">Memuat notifikasi…</p>
        </div>
        <div class="mn-more">
            <button type="button" class="owner-secondary" id="mn-more" hidden>Muat lagi</button>
        </div>
    </section>
</div>

<dialog id="mn-detail-dialog" class="owner-dialog mn-dialog" aria-labelledby="mn-detail-title">
    <div class="mn-dialog-head">
        <div>
            <small>DETAIL NOTIFIKASI</small>
            <h2 id="mn-detail-title">Respons pelanggan</h2>
        </div>
        <button type="button" class="mn-close" data-mn-close aria-label="Tutup"><span class="ui-icon icon-tutup" aria-hidden="true"></span></button>
    </div>
    <div data-mn-detail-body>
        <p class="mn-list-empty">Memuat detail…</p>
    </div>
</dialog>
<?php
nav('notifications');
foot();
?>
