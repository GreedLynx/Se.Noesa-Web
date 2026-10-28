<?php
require_once __DIR__.'/../../../app/bootstrap.php';

function customer_head(string $title, string $page): array {
  $customer = require_role('pelanggan', false);
  $GLOBALS['customer_page'] = $page;
  $css = fn($file) => filemtime(__DIR__.'/../assets/'.$file);
  ?>
  <!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title><?=htmlspecialchars($title)?> · Se.Noesa</title>
  <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/customer.css?v=<?=$css('customer.css')?>"><link rel="stylesheet" href="assets/quantity-input.css?v=<?=$css('quantity-input.css')?>"><link rel="stylesheet" href="assets/catalog-mobile.css?v=<?=$css('catalog-mobile.css')?>"></head>
  <body data-page="<?=htmlspecialchars($page)?>"><div class="customer-shell">
  <aside class="customer-rail">
    <button type="button" class="rail-logout" data-logout aria-label="Keluar" title="Keluar"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M10 4H5v16h5M14 8l4 4-4 4M8 12h10"/></svg></button>
    <a class="customer-logo" href="riwayat.php" aria-label="Profil pelanggan"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="3.3"/><path d="M5.5 19c.7-3.1 3.1-4.8 6.5-4.8s5.8 1.7 6.5 4.8"/><circle cx="12" cy="12" r="10"/></svg></a>
    <nav class="customer-page-nav" aria-label="Navigasi pelanggan">
      <a class="<?=$page==='catalog'?'active':''?>" href="index.php"><span>◯</span><small>Menu</small></a>
      <a class="<?=$page==='history'?'active':''?>" href="riwayat.php"><span>☰</span><small>Riwayat</small></a>
    </nav>
    <?php if($page==='catalog'): ?><nav class="customer-category-rail" aria-label="Kategori menu">
      <button type="button" data-category="" aria-label="Semua menu" title="Semua menu"><svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg></button>
      <button type="button" data-category="makanan" aria-label="Makanan" title="Makanan"><svg viewBox="0 0 24 24"><path d="M3 4v6M6 4v6M9 4v6M6 10v10M17 4v16M17 4c-3 2-3 7 0 8"/><circle cx="6" cy="8" r="5"/></svg></button>
      <button type="button" data-category="minuman" aria-label="Minuman" title="Minuman"><svg viewBox="0 0 24 24"><path d="M6 8h12l-1.5 12h-9zM9 8l2-6h8M10 12h5"/></svg></button>
      <button type="button" data-category="snack" aria-label="Snack" title="Snack"><svg viewBox="0 0 24 24"><path d="m4 15 11-11a3 3 0 0 1 4 4L8 19a3 3 0 0 1-4-4zM7 17l10-10M3 20l2-2"/></svg></button>
    </nav><?php endif; ?>
    <?php if(in_array($page,['catalog','history'],true)): ?><div class="customer-bottom-user" aria-label="<?=htmlspecialchars($customer['username']??'Pelanggan')?>"><?=htmlspecialchars(strtoupper(substr($customer['username']??'P',0,1)))?></div><?php endif; ?>
  </aside>
  <main class="customer-main">
    <header class="customer-top"><div><small>Se.Noesa / Pelanggan</small><h1><?=htmlspecialchars($title)?></h1></div><div class="customer-user"><span class="user-icon"><?=htmlspecialchars(strtoupper(substr($customer['username']??'P',0,1)))?></span><span class="user-name"><?=htmlspecialchars($customer['username']??'Pelanggan')?></span><button class="logout-text" type="button" data-logout>Keluar</button></div></header>
    <div class="customer-content">
  <?php return $customer;
}

function customer_foot(): void { ?>
    </div>
  </main>
  <nav class="customer-mobile-nav" aria-label="Navigasi pelanggan"><a class="<?=$GLOBALS['customer_page']==='catalog'?'active':''?>" href="index.php" aria-label="Menu"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="8"/><path d="M4 12h16"/></svg><span>Menu</span></a><a class="<?=$GLOBALS['customer_page']==='history'?'active':''?>" href="riwayat.php" aria-label="Riwayat"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 6h14M5 12h14M5 18h14"/></svg><span>Riwayat</span></a><button type="button" data-logout aria-label="Keluar"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M14 4h5v16h-5M3 12h12m-4-4 4 4-4 4"/></svg><span>Keluar</span></button></nav>
  <dialog id="detail-dialog" class="customer-dialog"><button class="dialog-close" data-close-detail aria-label="Tutup">&times;</button><div id="detail-content"></div></dialog>
  <dialog id="checkout-dialog" class="customer-dialog checkout-dialog"><button class="dialog-close" data-close-checkout aria-label="Tutup">&times;</button><div class="dialog-heading"><p class="eyebrow">KONFIRMASI</p><h2>Data pemesanan</h2><p class="muted">Pilih alamat tersimpan atau tambahkan alamat baru.</p></div><form id="checkout-form"><label>Nama pemesan<input name="nama_pemesan" required></label><label>Alamat pengiriman<select id="address-select" name="alamat_id"><option value="">+ Tambah alamat baru</option></select></label><div id="address-fields"><label>Label alamat<input name="label_alamat" value="Rumah" maxlength="50" required></label><div class="form-row"><label>Nama penerima<input name="nama_penerima" maxlength="100" required></label><label>Nomor HP<input name="telepon" type="tel" maxlength="20" required></label></div><label>Alamat lengkap<textarea name="alamat" rows="3" maxlength="2000" required></textarea></label><label class="save-address-option"><input type="checkbox" name="simpan_alamat" value="1" checked> Simpan alamat ini untuk pesanan berikutnya</label><label class="save-address-option"><input type="checkbox" name="jadikan_default" value="1"> Jadikan alamat default</label></div><label>Tanggal kirim<input name="tgl_kirim" type="date" required></label><label>Keterangan pesanan (opsional)<textarea name="keterangan" rows="2" maxlength="120" placeholder="Contoh: tanpa sambal, kirim ke ruang rapat"></textarea><small class="field-hint">Maksimal 120 karakter.</small></label><p id="schedule-note" class="schedule-note"></p><p id="checkout-error" class="form-error"></p><button id="submit-order" class="customer-button primary" type="submit">Kirim pesanan</button></form></dialog>
  <div id="customer-toast" class="toast" role="status" aria-live="polite"></div>
  </div><script src="assets/customer.js?v=<?=filemtime(__DIR__.'/../assets/customer.js')?>"></script></body></html>
<?php }
