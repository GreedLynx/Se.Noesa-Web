<?php require __DIR__ . '/partials/layout.php';
admin_head('Produk & Kuota', 'products'); ?>
<section class="page-intro">
    <div>
        <p class="eyebrow">KATALOG SE.NOESA</p>
        <h2>Produk &amp; Kuota</h2>
        <p class="muted">Atur harga, kategori, stok, dan gambar. Produk dengan stok tersedia otomatis tampil di katalog pelanggan.</p>
    </div><button class="button primary" id="new-product"><span>+</span> Tambah produk</button>
</section>
<section class="panel products-panel">
    <div class="toolbar">
        <div class="filter-chips" id="product-filters"><button class="active" data-category="">Semua <b id="all-count">0</b></button><button data-category="makanan">Makanan</button><button data-category="minuman">Minuman</button><button data-category="snack">Snack</button><button data-category="kritis">Kritis <b id="critical-count">0</b></button></div><label class="search"><span>&#9906;</span><input id="product-search" type="search" placeholder="Cari nama menu atau kode"></label>
    </div>
    <div class="product-grid" id="product-grid">
        <div class="loading">Memuat produk...</div>
    </div>
</section>
<dialog id="product-dialog" class="product-dialog">
    <form id="product-form" enctype="multipart/form-data">
        <div class="dialog-heading">
            <div>
                <p class="eyebrow">KATALOG PRODUK</p>
                <h2 id="product-form-title">Tambah produk</h2>
            </div><button type="button" class="icon-close" data-close-dialog aria-label="Tutup">&times;</button>
        </div><input type="hidden" name="id"><input type="hidden" name="gambar"><label>Kode produk<input name="kode" required maxlength="10" placeholder="NK-01"></label><label>Nama produk<input name="nama" required maxlength="100" placeholder="Nasi Kotak Ayam"></label>
        <div class="form-row"><label>Kategori<select name="kategori" required>
                    <option value="makanan">Makanan</option>
                    <option value="minuman">Minuman</option>
                    <option value="snack">Snack</option>
                </select></label><label>Harga (Rp)<input name="harga" type="number" min="1" required></label></div>
        <div class="form-row"><label>Kuota total<input name="kuota" type="number" min="0" required></label><label>Sudah terpakai<input name="terpakai" type="number" min="0" required></label></div>
        <div class="form-row"><label>Minimal pemesanan<input name="min_pesan" type="number" min="1" value="1" required></label><label class="image-upload-label">Upload gambar makanan<input name="gambar_file" type="file" accept="image/jpeg,image/png,image/webp"><small id="image-help">Wajib untuk produk baru. JPG, PNG, atau WebP · maksimal 2 MB</small></label></div><img id="product-image-preview" class="product-image-preview" alt="Pratinjau gambar produk" hidden>
        <p class="form-error" id="product-error" role="alert"></p>
        <div class="dialog-actions"><button type="button" class="button secondary" data-close-dialog>Batal</button><button class="button primary">Simpan produk</button></div>
    </form>
</dialog>
<?php admin_foot(); ?>