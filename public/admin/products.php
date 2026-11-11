<?php
require __DIR__ . '/partials/layout.php';
admin_head('Produk & Kuota', 'products');
?>
<section class="page-intro">
    <div>
        <p class="eyebrow">
            KATALOG SE.NOESA
        </p>
        <h2>
            Produk &amp; Kuota
        </h2>
        <p class="muted">
            Atur harga, kategori, kuota, dan gambar. Produk dengan kuota tersedia otomatis tampil di katalog
            pelanggan.
        </p>
    </div>
    <button class="button primary" id="new-product">
        <span>
            +
        </span>
        Tambah produk
    </button>
</section>
<section class="panel products-panel">
    <div class="toolbar">
        <div class="filter-chips" id="product-filters">
            <button class="active" data-category="">
                Semua
                <b
                    id="all-count">
                    0
                </b>
            </button>
            <button data-category="makanan">
                Makanan
            </button>
            <button
                data-category="minuman">
                Minuman
            </button>
            <button data-category="snack">
                Snack
            </button>
            <button
                data-category="kritis">
                Kritis
                <b id="critical-count">
                    0
                </b>
            </button>
        </div>
        <label
            class="search">
            <span>
                &#9906;
            </span>
            <input id="product-search" type="search"
                placeholder="Cari nama menu atau kode">
        </label>
    </div>
    <div class="product-grid" id="product-grid">
        <div class="loading">
            Memuat produk...
        </div>
    </div>
</section>
<dialog id="product-dialog" class="product-dialog">
    <form id="product-form" enctype="multipart/form-data">
        <div class="dialog-heading">
            <div>
                <p class="eyebrow">
                    KATALOG PRODUK
                </p>
                <h2 id="product-form-title">
                    Tambah produk
                </h2>
            </div>
            <button type="button" class="icon-close" data-close-dialog aria-label="Tutup">
                &times;
            </button>
        </div>
        <input type="hidden" name="id">
        <input type="hidden" name="gambar">
        <label>
            Kode produk
            <input name="kode"
                required maxlength="10" placeholder="NK-01">
        </label>
        <label>
            Nama produk
            <input name="nama" required
                maxlength="100" placeholder="Nasi Kotak Ayam">
        </label>
        <div class="form-row">
            <label>
                Kategori
                <select name="kategori" required>
                    <option value="makanan">
                        Makanan
                    </option>
                    <option value="minuman">
                        Minuman
                    </option>
                    <option value="snack">
                        Snack
                    </option>
                </select>
            </label>
            <label>
                Harga (Rp)
                <input name="harga" type="number" min="1" required>
            </label>
        </div>
        <div class="form-row">
            <label>
                Minimal pemesanan (porsi)
                <input name="min_pesan" type="number" min="4" value="4"
                    required>
            </label>
            <label>
                Kuota default per hari (porsi)
                <input name="kuota_default" type="number" min="0" max="2147483647" value="0" required>
                <small>Dipakai pada tanggal tanpa kuota khusus. Nilai 0 berarti tidak tersedia.</small>
            </label>
        </div>
        <label class="image-upload-label" style="text-align:center;display:block;max-width:480px;margin:0 auto;">
            Upload
            gambar makanan
            <input name="gambar_file" type="file" accept="image/jpeg,image/png,image/webp">
            <small
                id="image-help">
                Wajib untuk produk baru. JPG, PNG, atau WebP · maksimal 2 MB
            </small>
        </label>
        <div id="kuota-harian-section" class="form-section" hidden>
            <h3>
                Kuota khusus tanggal pengiriman
            </h3>
            <div class="form-row">
                <label>
                    Tanggal mulai
                    <input type="date" id="kuota-tanggal">
                </label>
                <label>
                    Tanggal selesai
                    <input type="date" id="kuota-tanggal-selesai">
                </label>
                <label>
                    Kuota (porsi)
                    <input type="number" id="kuota-nilai" min="0" max="2147483647">
                </label>
                <button type="button" id="kuota-add" class="button small secondary">
                    Simpan rentang
                </button>
                <button type="button" id="kuota-reset" class="button small secondary">Kembali ke default</button>
            </div>
            <p class="muted">Tanggal awal dan akhir termasuk. Maksimal 366 hari; nilai baru menimpa tanggal yang bertumpuk. Kuota 0 menutup produk pada rentang ini. Kembali ke default menghapus kuota khusus rentang pilihan.</p>
            <table class="kuota-table">
                <thead>
                    <tr>
                        <th>
                            Tanggal
                        </th>
                        <th>
                            Kuota
                        </th>
                        <th>
                            Aksi
                        </th>
                    </tr>
                </thead>
                <tbody id="kuota-list">
                </tbody>
            </table>
        </div>
        <img id="product-image-preview" class="product-image-preview" alt="Pratinjau gambar produk" hidden>
        <p class="form-error" id="product-error" role="alert">
        </p>
        <div class="dialog-actions">
            <button type="button" class="button secondary"
                data-close-dialog>
                Batal
            </button>
            <button class="button primary">
                Simpan produk
            </button>
        </div>
    </form>
</dialog>
<?php admin_foot(); ?>
