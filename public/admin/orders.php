<?php
require __DIR__ . '/partials/layout.php';
admin_head('Daftar Pesanan', 'orders');
?>
<section class="page-intro">
    <div>
        <p class="eyebrow">
            PENGELOLAAN TRANSAKSI
        </p>
        <h2>
            Antrean pesanan
        </h2>
        <p class="muted">
            Periksa bukti pembayaran, proses pesanan, dan pantau jadwal kirim.
        </p>
    </div>
    <div class="capacity-actions">
        <button type="button" class="button primary" id="new-offline-order">+ Pesanan Dine-in</button>
        <div class="capacity-chip">
            <span class="stat-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20" aria-hidden="true"><path d="M18 8h1a4 4 0 0 1 0 8h-1M2 8h16v9a4 4 0 0 1-4 4H6a4 4 0 0 1-4-4V8z"/><path d="M6 1v3M10 1v3M14 1v3"/></svg>
            </span>
            <span>
                <b id="orders-capacity">--</b>
                <small>Kapasitas dapur terpakai</small>
            </span>
        </div>
    </div>
</section>
<section class="panel orders-panel">
    <div class="toolbar">
        <div class="tabs" id="order-tabs">
            <button class="active" data-tab="semua">
                Semua
                <b id="all-count">0</b>
            </button>
            <button data-tab="pembayaran">
                Menunggu pembayaran
                <b id="payment-count">0</b>
            </button>
                <button data-tab="validasi">
                    Menunggu verifikasi
                <b
                    id="pending-count">
                    0
                </b>
            </button>
            <button data-tab="proses">
                Proses
                <b id="processing-count">0</b>
            </button>
            <button data-tab="selesai">
                Selesai
                <b id="completed-count">0</b>
            </button>
            <button data-tab="ditolak">
                Tolak
                <b id="rejected-count">0</b>
            </button>
            <button data-tab="dibatalkan">
                Dibatalkan <b id="cancelled-count">0</b>
            </button>
        </div>
        <label class="search">
            <span>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18" aria-hidden="true">
                    <circle cx="11" cy="11" r="7"/>
                    <path d="M16 16l5 5"/>
                </svg>
            </span>
            <input id="order-search" type="search" placeholder="Cari pemesan atau kode pesanan">
        </label>
    </div>
    <div class="cancellation-filter" id="cancellation-review-filter" hidden>
        <label for="cancellation-review">Status tinjauan</label>
        <select id="cancellation-review">
            <option value="">Semua status</option>
            <option value="unreviewed">Belum ditinjau</option>
            <option value="reviewed">Sudah ditinjau</option>
        </select>
        
        <label for="cancellation-source">Sumber pembatalan</label>
        <select id="cancellation-source">
            <option value="">Semua sumber</option>
            <option value="pelanggan">Oleh pelanggan</option>
            <option value="otomatis">Otomatis (sistem)</option>
            <option value="lainnya">Lainnya / Admin</option>
        </select>

        <span class="muted"><b id="cancellation-unreviewed-count">0</b> belum ditinjau</span>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Pemesan</th>
                    <th>Kode / Waktu</th>
                    <th>Pesanan</th>
                    <th>Total</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody id="order-rows">
                <tr>
                    <td colspan="6" class="loading">
                        Memuat pesanan...
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
    <div class="mobile-order-list" id="mobile-orders">
    </div>
</section>
<dialog id="offline-order-dialog" class="product-dialog offline-order-dialog">
    <form id="offline-order-form">
        <div class="dialog-heading">
            <div>
                <p class="eyebrow">PENCATATAN ADMIN</p>
                <h2>Tambah pesanan Dine-in</h2>
            </div>
            <button type="button" class="icon-close" data-close-offline-order aria-label="Tutup">&times;</button>
        </div>
        <p class="muted">Pesanan langsung berstatus diproses dan mengurangi kuota pada tanggal kedatangan.</p>
        
        <!-- Input tersembunyi untuk mengunci jenis pesanan sebagai Dine-in -->
        <input type="hidden" name="jenis_pesanan" id="offline-order-type" value="dine_in">

        <label>Nama pemesan<input name="nama_pemesan" maxlength="100" required></label>
        <div class="form-row">
            <label>Nama penerima<input name="nama_penerima" maxlength="100" required></label>
            <label>Nomor HP<input name="telepon" type="tel" maxlength="20" required></label>
        </div>
        
        <!-- Alamat disembunyikan karena Dine-in -->
        <label id="offline-address-field" hidden>Alamat pengiriman<textarea name="alamat" rows="2" maxlength="2000"></textarea></label>
        
        <div class="form-row">
            <label>
                <span id="offline-date-label">Tanggal Dine-in / Acara</span>
                <input name="tgl_kirim" id="offline-delivery-date" type="date" required>
            </label>
            <label>
                <span id="offline-time-label">Jam Sajian / Kedatangan</span>
                <input name="waktu_kirim" type="time" value="12:00" required>
            </label>
        </div>
        
        <label>Catatan (opsional)<input name="keterangan" maxlength="120"></label>
        
        <section class="offline-menu-section">
            <div class="row-between"><b>Pilih menu</b><span id="offline-menu-capacity" class="muted"></span></div>
            <div id="offline-menu-list" class="offline-menu-list">Pilih tanggal untuk melihat kuota menu.</div>
        </section>
        
        <div class="row-between offline-total"><span>Total pesanan</span><b id="offline-order-total">Rp 0</b></div>
        <p id="offline-order-error" class="form-error" role="alert"></p>
        <div class="dialog-actions">
            <button type="button" class="button secondary" data-close-offline-order>Batal</button>
            <button type="submit" class="button primary" id="save-offline-order">Simpan pesanan</button>
        </div>
    </form>
</dialog>
<?php admin_foot(); ?>