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
        <button type="button" class="button primary" id="new-offline-order">+ Pesanan offline</button>
        <div class="capacity-chip">
            <span class="stat-icon">
                &#9749;
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
                <b
                    id="all-count">
                    0
                </b>
            </button>
            <button data-tab="pembayaran">
                Menunggu pembayaran
                <b
                    id="payment-count">
                    0
                </b>
            </button>
            <button data-tab="validasi">
                Menunggu validasi
                <b
                    id="pending-count">
                    0
                </b>
            </button>
            <button data-tab="proses">
                Proses
                <b
                    id="processing-count">
                    0
                </b>
            </button>
            <button data-tab="selesai">
                Selesai
                <b
                    id="completed-count">
                    0
                </b>
            </button>
            <button data-tab="ditolak">
                Tolak
                <b
                    id="rejected-count">
                    0
                </b>
            </button>
            <button data-tab="dibatalkan">
                Dibatalkan <b id="cancelled-count">0</b>
            </button>
        </div>
        <label class="search">
            <span>
                &#9906;
            </span>
            <input
                id="order-search" type="search" placeholder="Cari pemesan atau kode pesanan">
        </label>
    </div>
    <div class="cancellation-filter" id="cancellation-review-filter" hidden>
        <label for="cancellation-review">Filter riwayat</label>
        <select id="cancellation-review">
            <option value="">Semua pembatalan</option>
            <option value="unreviewed">Belum ditinjau</option>
            <option value="reviewed">Sudah ditinjau</option>
        </select>
        <span class="muted"><b id="cancellation-unreviewed-count">0</b> belum ditinjau</span>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>
                        Kode / Waktu
                    </th>
                    <th>
                        Pemesan
                    </th>
                    <th>
                        Pesanan
                    </th>
                    <th>
                        Total
                    </th>
                    <th>
                        Status
                    </th>
                    <th>
                    </th>
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
                <h2>Tambah pesanan offline</h2>
            </div>
            <button type="button" class="icon-close" data-close-offline-order aria-label="Tutup">&times;</button>
        </div>
        <p class="muted">Pesanan langsung berstatus diproses dan mengurangi kuota pada tanggal pengiriman.</p>
        <label>Nama pemesan<input name="nama_pemesan" maxlength="100" required></label>
        <div class="form-row">
            <label>Nama penerima<input name="nama_penerima" maxlength="100" required></label>
            <label>Nomor HP<input name="telepon" type="tel" maxlength="20" required></label>
        </div>
        <label>Alamat pengiriman<textarea name="alamat" rows="2" maxlength="2000" required></textarea></label>
        <div class="form-row">
            <label>Tanggal kirim<input name="tgl_kirim" id="offline-delivery-date" type="date" required></label>
            <label>Waktu kirim<input name="waktu_kirim" type="time" value="12:00" required></label>
        </div>
        <label>Catatan (opsional)<input name="keterangan" maxlength="120"></label>
        <section class="offline-menu-section">
            <div class="row-between"><b>Pilih menu</b><span id="offline-menu-capacity" class="muted"></span></div>
            <div id="offline-menu-list" class="offline-menu-list">Pilih tanggal pengiriman untuk melihat kuota menu.</div>
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
