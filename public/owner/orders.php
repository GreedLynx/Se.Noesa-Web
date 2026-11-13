<?php
require __DIR__ . '/partials/layout.php';
head('Daftar Pesanan', 'orders');
topbar('Pesanan');
?>
<section class="owner-orders-intro rise">
    <div>
        <h2>Antrean pesanan</h2>
        <p>Periksa pembayaran, proses pesanan, dan pantau jadwal pengiriman.</p>
    </div>
    <button class="btn-main offline-trigger" type="button" id="new-offline-order">+ Pesanan offline</button>
</section>
<section class="card owner-capacity rise">
    <div>
        <h3>Kapasitas dapur</h3>
        <p id="orders-capacity">Memuat kapasitas...</p>
    </div>
    <span class="capacity-icon" aria-hidden="true">&#9749;</span>
</section>
<div class="seg order-tabs rise" id="order-tabs" role="tablist" aria-label="Filter status pesanan">
    <button class="on" type="button" data-tab="semua">Semua <b id="all-count">0</b></button>
    <button type="button" data-tab="pembayaran">Menunggu pembayaran <b id="payment-count">0</b></button>
    <button type="button" data-tab="validasi">Menunggu validasi <b id="pending-count">0</b></button>
    <button type="button" data-tab="proses">Proses <b id="processing-count">0</b></button>
    <button type="button" data-tab="selesai">Selesai <b id="completed-count">0</b></button>
    <button type="button" data-tab="ditolak">Ditolak <b id="rejected-count">0</b></button>
</div>
<label class="search rise">
    <span aria-hidden="true">&#9906;</span>
    <input id="order-search" type="search" placeholder="Cari pemesan atau kode pesanan">
</label>
<div id="order-list" aria-live="polite">
    <p class="empty">Memuat pesanan...</p>
</div>

<dialog id="offline-order-dialog" class="owner-dialog">
    <form id="offline-order-form">
        <div class="owner-dialog-heading">
            <div><small>PENCATATAN PESANAN</small><h2>Tambah pesanan offline</h2></div>
            <button type="button" class="owner-close" data-close-offline-order aria-label="Tutup">&times;</button>
        </div>
        <p class="owner-muted">Pesanan langsung berstatus diproses dan menggunakan kuota pada tanggal pengiriman.</p>
        <label>Nama pemesan<input name="nama_pemesan" maxlength="100" required></label>
        <div class="owner-form-row">
            <label>Nama penerima<input name="nama_penerima" maxlength="100" required></label>
            <label>Nomor HP<input name="telepon" type="tel" maxlength="20" required></label>
        </div>
        <label>Alamat pengiriman<textarea name="alamat" rows="2" maxlength="2000" required></textarea></label>
        <div class="owner-form-row">
            <label>Tanggal kirim<input name="tgl_kirim" id="offline-delivery-date" type="date" required></label>
            <label>Waktu kirim<input name="waktu_kirim" type="time" value="12:00" required></label>
        </div>
        <label>Catatan (opsional)<input name="keterangan" maxlength="120"></label>
        <section class="owner-menu-section">
            <div class="owner-row-between"><b>Pilih menu</b><span id="offline-menu-capacity" class="owner-muted"></span></div>
            <div id="offline-menu-list" class="owner-menu-list">Pilih tanggal pengiriman untuk melihat kuota menu.</div>
        </section>
        <div class="owner-row-between owner-order-total"><span>Total pesanan</span><b id="offline-order-total">Rp 0</b></div>
        <p id="offline-order-error" class="owner-error" role="alert"></p>
        <div class="owner-dialog-actions">
            <button type="button" class="owner-secondary" data-close-offline-order>Batal</button>
            <button type="submit" class="btn-main" id="save-offline-order">Simpan pesanan</button>
        </div>
    </form>
</dialog>
<?php
nav('orders');
foot();
