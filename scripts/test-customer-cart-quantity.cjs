const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

const source = fs.readFileSync('public/pelanggan/assets/customer.js', 'utf8');
const elements = new Map();
const node = key => {
    if (!elements.has(key)) elements.set(key, {
        addEventListener(type, fn) { this[type] = fn; },
        closest() { return null; },
        close() { this.open = false; },
        showModal() { assert.ok(!this.open); this.open = true; },
    });
    return elements.get(key);
};
for (const key of ['dec', 'inc', 'remove']) node(`[data-${key}]`).dataset = { [key]: '1' };
const product = { id: 1, nama: 'Menu uji', min_pesan: 12, kuota: 24, sisa: 24, harga: 10000 };
const otherProduct = { ...product, id: 2, min_pesan: 10, kuota: 50, sisa: 50 };
let sent;
const context = {
    $: node, $$: key => [node(key)], imagePath: String, esc: String, money: String, toast() {},
    document: { body: { classList: { toggle() {} } } },
    cart: {}, productCache: { 1: product, 2: otherProduct }, catalogClosed: false, location: {},
    FormData: class { *[Symbol.iterator]() {} },
    api: async (url, options) => { assert.equal(url, 'api/orders.php'); sent = options.body; return { id: 123 }; },
};
vm.createContext(context);
vm.runInContext(source.split('\n').slice(0, 2).join('\n'), context);
vm.runInContext(source.slice(source.indexOf('function showProduct('), source.indexOf('const statusLabels')), context);
const input = node('#quantity-value');
const minimumProduct = { ...product, id: 3, min_pesan: 4, kuota: 8, sisa: 8 };
context.showProduct(minimumProduct);
assert.match(node('#detail-content').innerHTML, /Min\. Pesan<b>4 porsi/);
assert.match(node('#detail-content').innerHTML, /min="4"[^>]+value="4"/, 'Jumlah awal mengikuti minimum 4 porsi');
node('#detail-dialog').close();
context.showProduct(product);
assert.match(node('#detail-content').innerHTML, /<span>Sisa kuota<b id="detail-sisa">12 porsi/);
assert.doesNotMatch(node('#detail-content').innerHTML, /Jumlah dipesan/);
input.value = '13';
input.input();
assert.equal(node('#detail-sisa').textContent, '11 porsi');
input.value = '12';
input.input();
assert.equal(node('#detail-sisa').textContent, '12 porsi');
input.value = '15';
input.input();
node('#add-to-cart').onclick();
context.openCart();
node('[data-dec]').onclick();
assert.equal(context.cart[1], 14);
node('[data-inc]').onclick();
assert.equal(node('#cart-dialog-total').textContent, '150000');
for (let i = 0; i < 5; i++) node('[data-dec]').onclick();
assert.equal(context.cart[1], 12);
assert.match(node('#cart-lines').innerHTML, /aria-label="Kurangi" disabled/);
context.showProduct(product);
input.value = '13';
input.input();
assert.equal(node('#add-to-cart').disabled, true);
input.value = '12';
input.input();
node('#add-to-cart').onclick();
context.openCart();
node('[data-inc]').onclick();
assert.equal(context.cart[1], 24);
assert.match(node('#cart-lines').innerHTML, /aria-label="Tambah" disabled/);
node('[data-remove]').onclick();
assert.equal(context.cart[1], undefined);
assert.equal(node('#cart-dialog').open, false);

context.cart[2] = 39;
context.showProduct(product);
assert.equal(node('#add-to-cart').disabled, true, 'Sisa 11 porsi tidak cukup untuk minimum 12');
node('#detail-dialog').close();
context.showProduct({ ...product, sisa: 0, available: false, stok_status: 'full' });
assert.match(node('#detail-content').innerHTML, /Sisa kuota<b id="detail-sisa">0 porsi/);
assert.equal(node('#add-to-cart').disabled, true);
node('#detail-dialog').close();
context.cart[2] = 37;
// Render berikutnya membuat tombol baru seperti penggantian innerHTML di browser.
elements.delete('#add-to-cart');
context.showProduct(product);
node('#add-to-cart').onclick();
context.openCart();
node('[data-inc]').onclick();
node('[data-inc]').onclick();
assert.equal(context.cart[1], 13, 'Tambah satu porsi berhenti saat total mencapai 50');
assert.match(node('#cart-lines').innerHTML, /aria-label="Tambah" disabled/);
node('#cart-dialog').close();
context.showProduct(product);
assert.equal(node('#add-to-cart').disabled, true, 'Modal juga mematuhi batas satu pesanan');

(async () => {
    await context.submitOrder({ preventDefault() {}, currentTarget: { elements: { alamat_id: { value: '1' } } } });
    assert.equal(JSON.stringify(sent.items), '[{"id":1,"qty":13},{"id":2,"qty":37}]');
    assert.equal(context.location.href, 'riwayat.php?order=123');
    console.log('PASS: sisa porsi, batas cart, hapus, total, dan payload checkout');
})().catch(error => { console.error(error); process.exitCode = 1; });
