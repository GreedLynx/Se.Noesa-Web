const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

const source = fs.readFileSync('public/admin/assets/admin.js', 'utf8');
const start = source.indexOf('async function orderDetail() {');
const end = source.indexOf('\nshell();', start);
const uploadStart = source.indexOf('async function uploadDispatchPhoto(');
const uploadEnd = source.indexOf('\nfunction toast(', uploadStart);
assert.notEqual(start, -1, 'orderDetail function exists');
assert.notEqual(end, -1, 'orderDetail function boundary exists');
assert.notEqual(uploadStart, -1, 'uploadDispatchPhoto function exists');
assert.notEqual(uploadEnd, -1, 'uploadDispatchPhoto function boundary exists');

const elements = new Map();
const element = () => ({ innerHTML: '', outerHTML: '', hidden: false });
let uploadForm = null;
let fileInput = { files: [] };
let uploadHandler;
const uploadedPhotos = [];
const orderResponse = {
    id: 42,
    kode: 'ORD-42',
    status: 'selesai',
    foto_kiriman_list: ['uploads/dispatch/dispatch-42.jpg'],
};
const context = {
    URLSearchParams,
    location: { search: '?id=42' },
    $: (selector) => {
        if (selector === '#dispatch-photo-form') return uploadForm;
        if (selector === 'button') return { disabled: false };
        if (selector === 'input[type="file"]') return fileInput;
        if (!elements.has(selector)) elements.set(selector, element());
        return elements.get(selector);
    },
    $$: () => [],
    api: async () => orderResponse,
    badge: () => '<span>Selesai</span>',
    dateLabel: () => '-',
    esc: (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#39;',
    })[char]),
    money: () => 'Rp 0',
    uploadDispatchPhoto: async (id, photo) => uploadedPhotos.push({ id, photo }),
    toast: () => {},
    setTimeout: () => {},
};

vm.createContext(context);
vm.runInContext(source.slice(start, end), context);

(async () => {
    await context.orderDetail();
    const detail = elements.get('#order-detail').innerHTML;
    assert.match(detail, /Data daftar menu tidak tersedia/);
    assert.match(detail, /dispatch-42\.jpg/);
    assert.equal(elements.get('#detail-actions').hidden, false);

    for (const [status, photo] of [['diproses', 'uploads/dispatch/old.jpg'], ['selesai', 'uploads/dispatch/old.jpg'], ['selesai', ''], ['diproses', ''], ['menunggu_validasi', '']]) {
        orderResponse.status = status;
        orderResponse.foto_kiriman = photo;
        delete orderResponse.foto_kiriman_list;
        await context.orderDetail();
        const html = elements.get('#order-detail').innerHTML;
        assert.equal(html.includes('class="dispatch-photo"'), Boolean(photo));
        assert.equal(html.includes('id="dispatch-photo-form"'), status === 'diproses');
    }
    orderResponse.status = 'selesai';
    orderResponse.foto_kiriman_list = ['uploads/dispatch/one.jpg', 'uploads/dispatch/two.jpg', 'uploads/dispatch/three.jpg'];
    await context.orderDetail();
    assert.equal((elements.get('#order-detail').innerHTML.match(/class="dispatch-photo"/g) || []).length, 3);

    orderResponse.menu = [{ nama: 'Menu uji', qty: 2, harga: 0, subtotal: 0, item_id: 1 }];
    await context.orderDetail();
    const completeDetail = elements.get('#order-detail').innerHTML;
    assert.match(completeDetail, /Menu uji/);
    assert.match(completeDetail, /Centang setiap menu/);
    assert.doesNotMatch(completeDetail, /Data daftar menu tidak tersedia/);

    orderResponse.status = 'diproses';
    uploadForm = { addEventListener: (event, handler) => { uploadHandler = handler; } };
    fileInput.files = [{ name: 'menu-1.jpg', size: 1024 }, { name: 'menu-2.jpg', size: 1024 }];
    await context.orderDetail();
    await uploadHandler({ preventDefault() {} });
    assert.deepEqual(uploadedPhotos.map(({ id, photo }) => [id, photo.name]), [
        [42, 'menu-1.jpg'],
        [42, 'menu-2.jpg'],
    ]);

    uploadedPhotos.length = 0;
    fileInput.files = [{ name: 'too-large.jpg', size: 2 * 1024 * 1024 + 1 }];
    await context.orderDetail();
    await uploadHandler({ preventDefault() {} });
    assert.equal(uploadedPhotos.length, 0);

    orderResponse.status = 'ditolak';
    orderResponse.alasan_penolakan = '<script>alert(1)</script>Nominal transfer tidak sesuai.';
    await context.orderDetail();
    const rejectedDetail = elements.get('#order-detail').innerHTML;
    assert.match(rejectedDetail, /Alasan penolakan/);
    assert.match(rejectedDetail, /&lt;script&gt;/);
    assert.doesNotMatch(rejectedDetail, /<script>/);

    const formEntries = [];
    let responseText = '<br /><b>Warning</b>: POST Content-Length exceeded {"ok":true}';
    const uploadContext = {
        FormData: class {
            append(...entry) { formEntries.push(entry); }
        },
        fetch: async () => ({ text: async () => responseText, ok: true }),
    };
    vm.createContext(uploadContext);
    vm.runInContext(source.slice(uploadStart, uploadEnd), uploadContext);
    await uploadContext.uploadDispatchPhoto(42, { name: 'menu.jpg' });
    assert.deepEqual(formEntries.map(([name]) => name), ['action', 'id', 'foto_kiriman[]']);
    responseText = '<br /><b>Warning</b>: POST Content-Length exceeds post_max_size';
    await assert.rejects(
        uploadContext.uploadDispatchPhoto(42, { name: 'menu.jpg' }),
        /Ukuran total upload melewati batas server/,
    );
    console.log('PASS: Detail bertahan saat menu hilang, menu normal tetap tampil, banyak foto diunggah satu per satu, dan foto besar ditolak.');
})().catch((error) => {
    console.error(error);
    process.exitCode = 1;
});
