const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');

const source = fs.readFileSync('public/admin/assets/admin.js', 'utf8');
const dashboardMarkup = fs.readFileSync('public/admin/dashboard.php', 'utf8');
const dashboardApi = fs.readFileSync('public/admin/api/dashboard.php', 'utf8');
assert.ok(dashboardMarkup.indexOf('id="production-title"') < dashboardMarkup.indexOf('id="cancellation-title"'), 'cancellation panel follows production recap');
assert.match(dashboardApi.slice(dashboardApi.indexOf('$cancellations = q(')), /LIMIT 2/);
const nodes = new Map();
const element = selector => {
    if (!nodes.has(selector)) nodes.set(selector, { innerHTML: '', textContent: '', style: {}, value: '2030-11-20' });
    return nodes.get(selector);
};
const base = { orders: 2, pending: 1, critical: 0, capacity: 80, used: 8, chart: [], priority: [], recent: [] };
let response;
const context = {
    $: element,
    api: async () => response,
    dateLabel: value => value || '-',
    esc: value => String(value).replace(/[&<>"']/g, char => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[char])),
    badge: status => status,
    money: String,
    countUp: (node, value) => { node.textContent = String(value); },
};
vm.createContext(context);
vm.runInContext(source.slice(source.indexOf('function cancellationSourceLabel('), source.indexOf('function countUp(')), context);
vm.runInContext(source.slice(source.indexOf('async function dashboard()'), source.indexOf('\nlet orderTab')), context);

(async () => {
    // An older server response must not blank the existing dashboard statistics.
    response = { ...base };
    await context.dashboard();
    assert.equal(element('[data-stat="orders"]').textContent, '2');
    assert.match(element('#production-list').innerHTML, /belum.*dimuat/i);
    assert.doesNotMatch(element('#production-list').innerHTML, /Belum ada pesanan/);
    assert.match(element('#cancellation-list').innerHTML, /belum.*dimuat/i);

    response = { ...base, production_date: '2030-11-20', production: [] };
    await context.dashboard();
    assert.match(element('#production-list').innerHTML, /Belum ada pesanan/);

    response = { ...base, production_date: '2030-11-20', production: [
        { menu: '<Menu>', total_porsi: 8, pesanan: [{ id: 42, kode: 'SN-42', status: 'diproses', porsi: 8 }] },
    ] };
    await context.dashboard();
    assert.match(element('#production-list').innerHTML, /&lt;Menu&gt;/);
    assert.match(element('#production-list').innerHTML, /8 porsi/);
    assert.match(element('#production-list').innerHTML, /order-detail.php\?id=42/);

    response = { ...base, production: [{ menu: 'Menu', total_porsi: 8 }] };
    await context.dashboard();
    assert.equal(element('[data-stat="orders"]').textContent, '2');
    assert.match(element('#production-list').innerHTML, /belum.*dimuat/i);

    for (const cancellations of [null, { items: [null] }, { items: {} }]) {
        response = { ...base, production: [], cancellations };
        await context.dashboard();
        assert.equal(element('[data-stat="orders"]').textContent, '2');
        assert.match(element('#cancellation-list').innerHTML, /belum.*dimuat/i);
        assert.doesNotMatch(element('#cancellation-list').innerHTML, /Tidak ada pembatalan/);
    }
    response = { ...base, production: [], cancellations: { unreviewed_count: 0, urgent_count: 0, items: [] } };
    await context.dashboard();
    assert.match(element('#cancellation-list').innerHTML, /Tidak ada pembatalan yang belum ditinjau/);
    response.cancellations = { unreviewed_count: 8, urgent_count: 2, items: [
        { id: 42, kode: '<BATAL>', nama_pemesan: 'Pemesan', cancellation_source: 'pelanggan', status_before_cancel: 'diproses', cancelled_at: '2000-01-01T08:00:00+07:00', production_stop_required: true },
    ] };
    await context.dashboard();
    assert.equal(element('#cancellation-count').textContent, '8');
    assert.match(element('#cancellation-list').innerHTML, /order-detail.php\?id=42/);
    assert.match(element('#cancellation-list').innerHTML, /&lt;BATAL&gt;/);
    assert.match(element('#cancellation-list').innerHTML, /Dibatalkan oleh pelanggan/);
    assert.match(element('#cancellation-list').innerHTML, /hentikan persiapan/i);
    assert.match(element('#cancellation-list').innerHTML, /Buka detail pesanan/);
    assert.equal(context.cancellationSourceLabel('otomatis'), 'Otomatis · 1 jam tanpa bukti bayar');
    assert.equal(context.cancellationSourceLabel(null), 'Sumber belum tercatat (data lama)');

    let apiResponse;
    const apiContext = {
        $: () => null,
        location: {},
        fetch: async () => ({ status: 200, ok: true, json: async () => {
            if (apiResponse instanceof Error) throw apiResponse;
            return apiResponse;
        } }),
    };
    vm.createContext(apiContext);
    vm.runInContext(source.slice(source.indexOf('async function api('), source.indexOf('\nasync function uploadDispatchPhoto(')), apiContext);
    apiResponse = { error: 'Rekap gagal dimuat' };
    await assert.rejects(() => apiContext.api('api/dashboard.php'), /Rekap gagal dimuat/);
    apiResponse = new SyntaxError('Invalid JSON');
    await assert.rejects(() => apiContext.api('api/dashboard.php'), /Respons server tidak valid/);

    const failedContext = {
        page: 'dashboard',
        run: async () => { throw new Error('<Respons gagal>'); },
        $: element, toast() {}, esc: context.esc,
    };
    vm.createContext(failedContext);
    vm.runInContext(source.slice(source.lastIndexOf('if (run)')), failedContext);
    await new Promise(resolve => setImmediate(resolve));
    assert.match(element('#production-list').innerHTML, /role="alert"/);
    assert.match(element('#cancellation-list').innerHTML, /role="alert"/);
    assert.match(element('#cancellation-list').innerHTML, /&lt;Respons gagal&gt;/);
    assert.doesNotMatch(element('#cancellation-list').innerHTML, /<Respons gagal>/);
    assert.equal(element('#cancellation-count').textContent, '--');

    console.log('PASS: dashboard survives missing/malformed production and cancellation data; valid pending cancellations expose priority and detail links');
})().catch(error => { console.error(error); process.exitCode = 1; });
