const fs = require('node:fs'), vm = require('node:vm'), assert = require('node:assert/strict');
const source = fs.readFileSync('public/pelanggan/assets/customer.js', 'utf8');
let detail, confirmation;
const elements = {
    '#history-detail': {}, '#history-dialog': { showModal() {} },
    '#cancel-order-btn': {}, '#proof-form': {}, '#replace-proof': {}, '#retry-payment': {}, '#payment-status': {}
};
const context = {
    clearTimeout() {}, setTimeout() {}, paymentExpiryTimer: null, imagePath: String,
    $: selector => {
        if (selector === '#history-detail' || selector === '#history-dialog') return elements[selector];
        return elements['#history-detail'].innerHTML.includes('id="' + selector.slice(1) + '"') ? elements[selector] : null;
    },
    api: async url => url.includes('payment-methods') ? { items: [] } : detail,
    esc: String, money: String, dateLabel: String, statusLabels: {}, statusProgress: () => '',
    confirm: text => { confirmation = text; return false; },
    setTimeout: () => 0,
    clearTimeout() {},
    paymentExpiryTimer: null,
    Date,
};
vm.createContext(context);
vm.runInContext(source.slice(source.indexOf('async function openHistoryDetail('), source.indexOf('\nconst page =')), context);
(async () => {
    for (const [status, canCancel, canUpload, proof] of [
        ['menunggu_pembayaran', true, true, null],
        ['menunggu_pembayaran', true, false, null],
        ['menunggu_validasi', true, true, 'uploads/payments/old.png'],
        ['diproses', true, false, 'uploads/payments/old.png'],
        ['diproses', false, false, 'uploads/payments/old.png'],
        ['dibatalkan', false, false, 'uploads/payments/old.png']
    ]) {
        detail = { id: 1, kode: 'UJI', items: [], status, can_cancel: canCancel, can_upload_proof: canUpload,
            bukti_bayar: proof, cancel_before: '2030-11-19T00:00:00+07:00', payment_expires_at: '2030-11-18T13:00:00+07:00' };
        await context.openHistoryDetail(1);
        const html = elements['#history-detail'].innerHTML;
        assert.equal(html.includes('id="cancel-order-btn"'), canCancel);
        assert.equal(html.includes('id="proof-form"'), canUpload);
        if (canCancel) {
            assert.match(html, /Syarat Pembatalan:/);
            assert.match(html, /width:100%; background:var\(--red\)/);
            assert.match(html, /18 Nov 2030, akhir hari WIB/);
            await elements['#cancel-order-btn'].onclick();
            assert.match(confirmation, /refund penuh ditangani Admin di luar aplikasi/);
        }
        if (status === 'diproses' && !canCancel) assert.match(html, /Batas Waktu Pembatalan Terlewati/);
        if (status === 'dibatalkan') assert.match(html, /<h3>Refund<\/h3>/);
        if (status === 'menunggu_pembayaran' && !canUpload) assert.match(html, /Tenggat waktu upload bukti pembayaran telah berakhir/);
    }
    detail = { id: 2, kode: 'RESCHEDULE', items: [], status: 'diproses', can_cancel: true,
        can_upload_proof: false, reschedule_offer: { status_tawaran: 'menunggu', alasan: 'Tanggal lama ditutup',
            tanggal_asal: '2030-11-20', tanggal_usulan: '2030-11-22', berlaku_sampai: '2030-11-19T12:00:00+07:00', dibuat_at: '2030-11-18T12:00:00+07:00' },
        can_respond_reschedule: true };
    await context.openHistoryDetail(2);
    const offerHtml = elements['#history-detail'].innerHTML;
    assert.match(offerHtml, /Tawaran jadwal pengganti/);
    assert.match(offerHtml, /Tanggal lama ditutup/);
    assert.match(offerHtml, /id="accept-reschedule"/);
    assert.match(offerHtml, /id="reject-reschedule"/);
    assert.doesNotMatch(offerHtml, /id="cancel-order-btn"/);
    assert.doesNotMatch(offerHtml, /id="proof-form"/);
    detail = { id: 1, items: [], status: 'selesai', can_cancel: false, can_upload_proof: false,
        foto_kiriman_list: ['uploads/dispatch/one.jpg', 'uploads/dispatch/two.jpg', 'uploads/dispatch/three.jpg'] };
    await context.openHistoryDetail(1);
    assert.equal((elements['#history-detail'].innerHTML.match(/class="uploaded-proof"/g) || []).length, 3);
    console.log('PASS: Detail mengikuti izin server, tenggat upload, konfirmasi refund, dan respons tawaran jadwal');
})().catch(error => { console.error(error); process.exitCode = 1; });
