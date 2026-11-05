<?php require __DIR__ . '/partials/layout.php';
admin_head('Jadwal PO', 'schedules'); ?>
<section class="page-intro">
    <div>
        <p class="eyebrow">PENGATURAN JADWAL</p>
        <h2>Jadwal Pre-Order</h2>
        <p class="muted">Atur periode dan kapasitas jadwal pre-order.</p>
    </div><button class="button primary" id="new-schedule"><span>+</span> Tambah jadwal</button>
</section>
<section class="panel products-panel">
    <table>
        <thead>
            <tr>
                <th>Nama Jadwal</th>
                <th>Periode</th>
                <th>Kapasitas</th>
                <th>Status</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody id="schedule-rows"></tbody>
    </table>
</section>
<dialog id="schedule-dialog" class="product-dialog">
    <form id="schedule-form">
        <div class="dialog-heading">
            <div>
                <p class="eyebrow">JADWAL PRE-ORDER</p>
                <h2 id="schedule-form-title">Tambah jadwal</h2>
            </div><button type="button" class="icon-close" data-close-dialog aria-label="Tutup">&times;</button>
        </div><input type="hidden" name="id"><label>Nama jadwal<input name="nama" required maxlength="100"
                placeholder="PO Minggu 1"></label>
        <div class="form-row"><label>Tanggal buka<input name="tgl_buka" type="date" required></label><label>Tanggal
                tutup<input name="tgl_tutup" type="date" required></label></div>
        <div class="form-row"><label>Kapasitas (porsi)<input name="kapasitas" type="number" min="1" value="50"
                    required></label><label class="checkbox-label"><input name="aktif" type="checkbox" checked>
                Aktif</label></div>
        <p class="form-error" id="schedule-error" role="alert"></p>
        <div class="dialog-actions"><button type="button" class="button secondary"
                data-close-dialog>Batal</button><button class="button primary">Simpan jadwal</button></div>
    </form>
</dialog>
<?php admin_foot(); ?>