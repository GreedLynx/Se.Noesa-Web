<?php
require __DIR__ . '/partials/layout.php';
admin_head('Tanggal Libur', 'schedules');
?>
<section class="page-intro">
    <div>
        <p class="eyebrow">
            KALENDER OPERASIONAL
        </p>
        <h2>
            Tanggal Libur
        </h2>
        <p class="muted">
            Operasional selalu buka. Tambahkan tanggal atau rentang libur untuk menutup semua kuota produk.
        </p>
    </div>
    <button class="button primary" id="new-holiday">
        <span>
            +
        </span>
        Tambah tanggal libur
    </button>
</section>
<section class="panel products-panel">
    <table>
        <thead>
            <tr>
                <th>
                    Rentang libur
                </th>
                <th>
                    Aksi
                </th>
            </tr>
        </thead>
        <tbody id="holiday-rows">
        </tbody>
    </table>
</section>
<section class="panel products-panel" aria-labelledby="affected-orders-title">
    <div class="panel-heading"><div><h2 id="affected-orders-title">Pesanan terdampak libur</h2>
        <p class="muted">Periksa setiap pesanan. Pesanan online dapat ditawari tanggal pengganti pada halaman detail.</p></div>
        <span class="tag soft" id="affected-orders-count">0 pesanan</span></div>
    <div class="affected-orders-scroll"><table><thead><tr><th>Pesanan</th><th>Tanggal kirim</th><th>Status</th><th>Tindak lanjut</th></tr></thead>
        <tbody id="affected-order-rows"><tr><td colspan="4">Memuat pesanan...</td></tr></tbody></table></div>
</section>
<dialog id="holiday-dialog" class="product-dialog" aria-labelledby="holiday-form-title">
    <form id="holiday-form">
        <input type="hidden" name="id">
        <div class="dialog-heading">
            <div>
                <p class="eyebrow">
                    KALENDER OPERASIONAL
                </p>
                <h2 id="holiday-form-title">
                    Tambah tanggal libur
                </h2>
            </div>
            <button type="button" class="icon-close" data-close-dialog aria-label="Tutup">
                <span class="ui-icon icon-tutup" aria-hidden="true"></span>
            </button>
        </div>
        <div class="form-row">
            <label>
                Tanggal mulai libur
                <input name="tanggal_mulai" type="date" required>
            </label>
            <label>
                Tanggal akhir libur
                <input name="tanggal_selesai" type="date" required>
            </label>
        </div>
        <p class="muted">Tanggal mulai dan akhir termasuk dalam rentang libur. Pesanan yang sudah ada tidak berubah otomatis.</p>
        <p class="form-error" id="holiday-error" role="alert">
        </p>
        <div class="dialog-actions">
            <button type="button" class="button secondary"
                data-close-dialog>
                Batal
            </button>
            <button class="button primary">
                Simpan tanggal libur
            </button>
        </div>
    </form>
</dialog>
<?php admin_foot(); ?>
