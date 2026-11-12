const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');

const source = fs.readFileSync('public/admin/assets/admin.js', 'utf8');
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
vm.runInContext(source.slice(source.indexOf('async function dashboard()'), source.indexOf('\nlet orderTab')), context);

(async () => {
    // An older server response must not blank the existing dashboard statistics.
    response = { ...base };
    await context.dashboard();
    assert.equal(element('[data-stat="orders"]').textContent, '2');
    assert.match(element('#production-list').innerHTML, /belum.*dimuat/i);
    assert.doesNotMatch(element('#production-list').innerHTML, /Belum ada pesanan/);

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

    console.log('PASS: dashboard keeps statistics on missing/malformed recap, shows empty and populated recap with detail links');
})().catch(error => { console.error(error); process.exitCode = 1; });
