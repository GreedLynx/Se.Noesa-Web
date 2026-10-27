<?php require __DIR__.'/partials/layout.php'; customer_head('Riwayat Pesanan','history'); ?>
<section class="catalog-intro"><div><p class="eyebrow">PESANAN SAYA</p><h2>Riwayat Pesanan</h2><p>Lihat perkembangan pesanan dan unggah bukti pembayaran.</p></div><a class="history-link" href="index.php"><span>+</span> Pesan menu</a></section>
<div class="history-tabs" id="history-tabs"><button class="active" data-status="">Semua</button><button data-status="menunggu_validasi">Menunggu verifikasi</button><button data-status="diproses">Diproses</button><button data-status="selesai">Selesai</button><button data-status="ditolak">Ditolak</button></div>
<section class="history-list" id="history-list"><div class="loading">Memuat riwayat pesanan...</div></section>
<dialog id="history-dialog" class="customer-dialog history-dialog"><button class="dialog-close" data-close-history aria-label="Tutup">&times;</button><div id="history-detail"></div></dialog>
<?php customer_foot(); ?>
