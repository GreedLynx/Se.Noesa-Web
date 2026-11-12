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
<dialog id="holiday-dialog" class="product-dialog">
    <form id="holiday-form">
        <div class="dialog-heading">
            <div>
                <p class="eyebrow">
                    KALENDER OPERASIONAL
                </p>
                <h2>
                    Tambah tanggal libur
                </h2>
            </div>
            <button type="button" class="icon-close" data-close-dialog aria-label="Tutup">
                &times;
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
