<?php require __DIR__ . '/partials/layout.php';
admin_head('Daftar Pesanan', 'orders'); ?>
<section class="page-intro">
    <div>
        <p class="eyebrow">PENGELOLAAN TRANSAKSI</p>
        <h2>Antrean pesanan</h2>
        <p class="muted">Periksa bukti pembayaran, proses pesanan, dan pantau jadwal kirim.</p>
    </div>
    <div class="capacity-chip"><span class="stat-icon">&#9749;</span><span><b id="orders-capacity">--</b><small>Kapasitas dapur terpakai</small></span></div>
</section>
<section class="panel orders-panel">
    <div class="toolbar">
        <div class="tabs" id="order-tabs"><button class="active" data-tab="validasi">Menunggu validasi <b id="pending-count">0</b></button><button data-tab="proses">Proses <b id="processing-count">0</b></button><button data-tab="selesai">Selesai <b id="completed-count">0</b></button></div><label class="search"><span>&#9906;</span><input id="order-search" type="search" placeholder="Cari pemesan atau kode pesanan"></label>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Kode / Waktu</th>
                    <th>Pemesan</th>
                    <th>Pesanan</th>
                    <th>Total</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody id="order-rows">
                <tr>
                    <td colspan="6" class="loading">Memuat pesanan...</td>
                </tr>
            </tbody>
        </table>
    </div>
    <div class="mobile-order-list" id="mobile-orders"></div>
</section>
<?php admin_foot(); ?>