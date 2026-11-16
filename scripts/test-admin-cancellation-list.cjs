const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');

const source = fs.readFileSync('public/admin/assets/admin.js', 'utf8');
const markup = fs.readFileSync('public/admin/orders.php', 'utf8');
assert.match(markup, /data-tab="dibatalkan"/);
assert.match(markup, /id="cancelled-count"/);
assert.match(markup, /id="cancellation-review"/);
const nodes = new Map();
const node = selector => {
    if (!nodes.has(selector)) nodes.set(selector, { value: '', innerHTML: '', textContent: '', hidden: false, classList: { toggle() {} } });
    return nodes.get(selector);
};
const tabs = ['semua', 'validasi', 'dibatalkan', 'selesai'].map(tab => ({
    dataset: { tab }, classList: { toggle() {} },
}));
let requested;
const fixture = {
    counts: { total: 3, payment: 0, pending: 0, processing: 0, completed: 0, rejected: 0, cancelled: 3, cancellation_unreviewed: 2 },
    capacity: { pakai: 0, kapasitas: 80 },
    items: [{ id: 9, kode: '<BATAL>', nama_pemesan: '<Nama>', status: 'dibatalkan', pax: 4, total: 40000,
        status_updated_at: '2000-01-01T08:00:00+07:00', cancellation_source: null, cancelled_at: null, reviewed_at: null }],
};
const context = {
    URL, URLSearchParams,
    location: { search: '?tab=dibatalkan&review=unreviewed' },
    document: {
        querySelector: node,
        querySelectorAll: selector => selector === '#order-tabs button' ? tabs : [],
    },
    setTimeout() {}, clearTimeout() {},
    testApi: async url => { requested = url; return fixture; },
};
vm.createContext(context);
vm.runInContext(source.slice(0, source.lastIndexOf('\nshell();')), context);
vm.runInContext('api = globalThis.testApi;', context);

(async () => {
    context.ordersPage();
    await context.loadOrders();
    assert.match(requested, /tab=dibatalkan/);
    assert.match(requested, /review=unreviewed/);
    assert.equal(node('#cancellation-review-filter').hidden, false);
    assert.equal(node('#cancelled-count').textContent, 3);
    assert.equal(node('#cancellation-unreviewed-count').textContent, 2);
    for (const selector of ['#order-rows', '#mobile-orders']) {
        assert.match(node(selector).innerHTML, /Sumber belum tercatat/);
        assert.match(node(selector).innerHTML, /Belum ditinjau/);
        assert.match(node(selector).innerHTML, /order-detail.php\?id=9/);
        assert.match(node(selector).innerHTML, /&lt;BATAL&gt;/);
        assert.doesNotMatch(node(selector).innerHTML, /<BATAL>/);
    }
    node('#order-search').value = 'Kode & Nama';
    node('#cancellation-review').value = 'reviewed';
    node('#cancellation-review').onchange();
    await context.loadOrders();
    assert.match(requested, /review=reviewed/);
    assert.match(requested, /q=Kode%20%26%20Nama/);
    tabs.find(tab => tab.dataset.tab === 'selesai').onclick();
    await context.loadOrders();
    assert.match(requested, /tab=selesai/);
    assert.doesNotMatch(requested, /review=/);
    assert.equal(node('#cancellation-review-filter').hidden, true);

    context.location.search = '?tab=invalid&review=invalid';
    context.ordersPage();
    await context.loadOrders();
    assert.match(requested, /tab=semua/);
    assert.doesNotMatch(requested, /review=/);
    assert.equal(node('#cancellation-review-filter').hidden, true);
    console.log('PASS: canceled tab/count/search and review filter work on desktop/mobile; URL initialization accepts only documented filters');
})().catch(error => { console.error(error); process.exitCode = 1; });
