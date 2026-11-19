const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

const source = fs.readFileSync('public/pelanggan/assets/customer.js', 'utf8');
const elements = new Map();
const stockLabel = {};
const node = key => {
    if (!elements.has(key)) elements.set(key, {
        addEventListener(type, fn) { this[type] = fn; },
        closest() { return stockLabel; },
        close() { this.open = false; },
        showModal() { assert.ok(!this.open); this.open = true; },
    });
    return elements.get(key);
};
for (const key of ['dec', 'inc', 'remove']) node(`[data-${key}]`).dataset = { [key]: '1' };
const product = { id: 1, nama: 'Menu uji', min_pesan: 4, kuota: 20, sisa: 14, harga: 10000 };
let sent;
const context = {
    $: node, $$: key => [node(key)], imagePath: String, esc: String, money: String, toast() {},
    document: { body: { classList: { toggle() {} } } },
    cart: {}, productCache: { 1: product }, catalogClosed: false, location: {},
    FormData: class { *[Symbol.iterator]() {} },
    api: async (url, options) => { assert.equal(url, 'api/orders.php'); sent = options.body; return { id: 123 }; },
};
vm.createContext(context);
vm.runInContext(source.slice(source.indexOf('function showProduct('), source.indexOf('const statusLabels')), context);
const input = node('#quantity-value');
context.showProduct(product);
assert.match(node('#detail-content').innerHTML, /<span hidden>Sisa Porsi/);
assert.doesNotMatch(node('#detail-content').innerHTML, /Jumlah dipesan/);
input.value = '5';
input.input();
assert.equal(stockLabel.hidden, false);
assert.equal(node('#detail-sisa').textContent, '9 porsi');
input.value = '4';
input.input();
assert.equal(stockLabel.hidden, true);
input.value = '7';
input.input();
node('#add-to-cart').onclick();
context.openCart();
node('[data-dec]').onclick();
assert.equal(context.cart[1], 6);
node('[data-inc]').onclick();
assert.equal(node('#cart-dialog-total').textContent, '70000');
for (let i = 0; i < 5; i++) node('[data-dec]').onclick();
assert.equal(context.cart[1], 4);
assert.match(node('#cart-lines').innerHTML, /aria-label="Kurangi" disabled/);
context.showProduct(product);
input.value = '11';
input.input();
assert.equal(node('#add-to-cart').disabled, true);
input.value = '10';
input.input();
node('#add-to-cart').onclick();
context.openCart();
node('[data-inc]').onclick();
assert.equal(context.cart[1], 14);
assert.match(node('#cart-lines').innerHTML, /aria-label="Tambah" disabled/);
node('[data-remove]').onclick();
assert.equal(context.cart[1], undefined);
assert.equal(node('#cart-dialog').open, false);

(async () => {
    context.cart[1] = 7;
    await context.submitOrder({ preventDefault() {}, currentTarget: { elements: { alamat_id: { value: '1' } } } });
    assert.equal(JSON.stringify(sent.items), '[{"id":1,"qty":7}]');
    assert.equal(context.location.href, 'riwayat.php?order=123');
    console.log('PASS: sisa porsi, batas cart, hapus, total, dan payload checkout');
})().catch(error => { console.error(error); process.exitCode = 1; });
