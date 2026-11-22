const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const { spawnSync } = require('node:child_process');

const source = fs.readFileSync('public/admin/assets/admin.js', 'utf8');
let listener, replaced, timer;
const search = { value: '' };
const detailLink = { href: 'https://example.test/demo/admin/order-detail.php?id=42' };
const context = {
    URL, URLSearchParams,
    location: { href: 'https://example.test/demo/admin/orders.php?tgl_kirim=2030-11-20' },
    history: { state: { existing: 1 }, replaceState(state, unused, url) {
        assert.equal(state.existing, 1);
        context.location.href = String(url);
    } },
    document: { addEventListener(type, callback) { assert.equal(type, 'click'); listener = callback; } },
    $: () => search,
    $$: () => [detailLink],
    orderTab: 'dibatalkan', cancellationReview: 'unreviewed', filterCancellationSource: 'pelanggan',
};
context.location.replace = url => { replaced = url; };
vm.createContext(context);
vm.runInContext(source.slice(source.indexOf('function orderDetailUrl('), source.indexOf('async function api(')), context);
search.value = 'Nama & "kode"';
context.syncOrderUrl();
const current = new URL(context.location.href);
assert.equal(current.searchParams.get('q'), search.value);
assert.equal(current.searchParams.get('tgl_kirim'), '2030-11-20');
assert.equal(current.searchParams.get('review'), 'unreviewed');
assert.equal(current.searchParams.get('source'), 'pelanggan');
const detail = new URL(detailLink.href, current);
assert.equal(detail.searchParams.get('id'), '42');
assert.equal(detail.searchParams.get('return_to'), 'orders.php' + current.search);
context.orderTab = 'selesai';
context.syncOrderUrl();
assert.equal(new URL(context.location.href).searchParams.has('review'), false);
assert.equal(new URL(context.location.href).searchParams.has('source'), false);
context.location.href = 'https://example.test/demo/admin/dashboard.php?tgl_kirim=2030-11-20';
assert.equal(new URL(context.orderDetailUrl(7), current).searchParams.get('return_to'), 'dashboard.php?tgl_kirim=2030-11-20');

// Pencarian menyimpan URL dan memperbarui tautan sebelum debounce permintaan API.
context.clearTimeout = () => {};
context.setTimeout = callback => { timer = callback; };
context.loadOrders = () => { throw Error('API must still be pending'); };
const inputHandler = source.match(/\$\("#order-search"\)\.oninput = \(\) => \{[\s\S]*?\};/)[0];
context.orderTimer = null;
vm.runInContext(inputHandler, context);
context.location.href = 'https://example.test/demo/admin/orders.php';
search.value = 'Baru';
search.oninput();
assert.equal(new URL(context.location.href).searchParams.get('q'), 'Baru');
assert.equal(typeof timer, 'function');

context.bindOrderBack();
const back = { href: 'https://example.test/demo/admin/dashboard.php?tgl_kirim=2030-11-20', target: '', hasAttribute: () => false };
const event = overrides => ({ button: 0, target: { closest: () => back }, preventDefault() { this.prevented = true; }, ...overrides });
const click = event();
listener(click);
assert.equal(click.prevented, true);
assert.equal(replaced, back.href);
for (const overrides of [{ ctrlKey: true }, { metaKey: true }, { shiftKey: true }, { altKey: true }, { button: 1 }, { defaultPrevented: true }]) {
    replaced = null;
    listener(event(overrides));
    assert.equal(replaced, null);
}
back.target = '_blank';
replaced = null;
listener(event());
assert.equal(replaced, null);

// Jalankan validasi halaman saja, tanpa bootstrap, sesi, atau database.
const markup = fs.readFileSync('public/admin/order-detail.php', 'utf8');
const validation = markup.slice(markup.indexOf('$backUrl ='), markup.indexOf('?>'));
const cases = [
    ['', 'orders.php'],
    ['orders.php?tab=dibatalkan&source=pelanggan', 'orders.php?tab=dibatalkan&source=pelanggan'],
    ['orders.php?tab=dibatalkan&source=invalid', 'orders.php'],
    [null, 'orders.php'],
    ['orders.php?tgl_kirim=2030-11-20%00', 'orders.php'],
    [['orders.php'], 'orders.php'],
    ['dashboard.php?tgl_kirim=2030-11-20', 'dashboard.php?tgl_kirim=2030-11-20'],
    ['orders.php?tab=dibatalkan&review=reviewed&q=A%26%22B', 'orders.php?tab=dibatalkan&review=reviewed&q=A%26%22B'],
    ['orders.php?tab=selesai&review=reviewed', 'orders.php?tab=selesai'],
    ...['https://evil.test/orders.php', '//evil.test/orders.php', '../owner/orders.php', '/demo/admin/orders.php', 'api/orders.php', 'order-detail.php?id=1', 'orders.php?tab[]=semua', 'orders.php?tab=semua&tab=selesai', 'orders.php?return_to=dashboard.php', 'dashboard.php?tab=semua', 'orders.php?tgl_kirim=2030-02-30', 'orders.php?tab=invalid', 'orders.php?review=invalid', 'orders.php#fragment', 'orders.php\\evil', 'javascript:alert(1)'].map(value => [value, 'orders.php']),
];
const payload = Buffer.from(JSON.stringify(cases)).toString('base64');
const php = '<?php $cases = json_decode(base64_decode("' + payload + '"), true); $results = []; foreach ($cases as $case) { $_GET = ["return_to" => $case[0]]; ' + validation + ' $results[] = $backUrl; } echo json_encode($results);';
const result = spawnSync('php', [], { input: php, encoding: 'utf8' });
assert.equal(result.status, 0, result.stderr);
assert.deepEqual(JSON.parse(result.stdout), cases.map(([, expected]) => expected));
assert.match(markup, /data-order-back href="<\?= htmlspecialchars\(\$backUrl/);
assert.equal((source.match(/esc\(orderDetailUrl\((?:order|o)\.id\)\)/g) || []).length, 6);
assert.match(source, /location\.href = orderDetailUrl\(result\.id\)/);
assert.match(source, /esc\(\$\('#order-back'\)\.getAttribute\('href'\)\)/);
console.log('PASS: origin/filter round-trip, debounce, six entry links/offline, click modifiers, and PHP fallback/URL validation without DB');
