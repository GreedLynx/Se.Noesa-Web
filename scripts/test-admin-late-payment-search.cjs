const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const { execFileSync } = require('node:child_process');

const php = String.raw`
$_GET = json_decode($argv[1], true);
$queries = [];
function late_payment_table_available() { return true; }
function q($sql, $params = []) {
    $GLOBALS['queries'][] = ['sql' => $sql, 'params' => $params];
    return new class { function fetchAll() { return []; } };
}
function json($data, $status = 200) {
    echo json_encode(['status' => $status, 'data' => $data, 'queries' => $GLOBALS['queries']]);
    exit;
}
$source = file_get_contents('app/late-payment.php');
$start = strpos($source, 'function late_payment_list(');
$end = strpos($source, 'function late_payment_logs(', $start);
eval(substr($source, $start, $end - $start));
$_SERVER['REQUEST_METHOD'] = 'GET';
$endpoint = file_get_contents('public/admin/api/late-payments.php');
eval(substr($endpoint, strpos($endpoint, '$search = $_GET')));
`;
function request(query) {
    return JSON.parse(execFileSync('php', ['-r', php, JSON.stringify(query)], { encoding: 'utf8' }));
}

const eligible = request({ eligible: '1', q: '  ORD-42  ' });
assert.equal(eligible.status, 200);
assert.match(eligible.queries[0].sql, /NOT EXISTS[\s\S]*nama_pemesan ILIKE \?[\s\S]*ORDER BY[\s\S]*LIMIT 100/);
assert.deepEqual(eligible.queries[0].params, ['%ORD-42%', '%ORD-42%']);
const recorded = request({ q: '  Siti  ' });
assert.equal(recorded.status, 200);
assert.match(recorded.queries[0].sql, /JOIN pesanan[\s\S]*nama_pemesan ILIKE \? OR p\.kode ILIKE \?/);
assert.deepEqual(recorded.queries[0].params, ['%Siti%', '%Siti%']);
assert.deepEqual(request({}).queries[0].params, ['%%', '%%']);
assert.equal(request({ q: ['invalid'] }).status, 422);

const select = { innerHTML: '' };
const search = { value: 'ORD-42' };
let requested = '';
const js = fs.readFileSync('public/admin/assets/late-payments.js', 'utf8');
const context = {
    eligibleRequest: 0,
    $: selector => selector === '#late-order' ? select : search,
    api: async url => { requested = url; return { items: [] }; },
    toast() {},
};
vm.createContext(context);
vm.runInContext(js.slice(js.indexOf('async function loadEligible()'), js.indexOf('async function loadList()')), context);
context.loadEligible().then(() => {
    assert.equal(requested, 'api/late-payments.php?eligible=1&q=ORD-42');
    assert.match(select.innerHTML, /Tidak ada pesanan yang cocok/);
    const admin = fs.readFileSync('public/admin/assets/admin.js', 'utf8');
    let sent;
    const uploadContext = {
        FormData: class { append() {} },
        $: () => null,
        document: { querySelector: () => ({ content: 'csrf-test' }) },
        fetch: async (_url, options) => { sent = options; return { ok: true, status: 200, json: async () => ({ ok: true }) }; },
    };
    vm.createContext(uploadContext);
    vm.runInContext(admin.slice(admin.indexOf('// Token CSRF'), admin.indexOf('async function uploadDispatchPhoto(')), uploadContext);
    const proof = new uploadContext.FormData();
    return uploadContext.api('api/late-payments.php', { method: 'POST', body: proof }).then(() => {
        assert.equal(sent.body, proof, 'File bukti harus tetap berupa FormData');
        assert.equal(sent.headers['X-CSRF-Token'], 'csrf-test');
        assert.equal('Content-Type' in sent.headers, false, 'Browser menentukan multipart boundary');
        return uploadContext.api('api/late-payments.php', { method: 'POST', body: { action: 'verify' } });
    }).then(() => {
        assert.equal(sent.body, '{"action":"verify"}', 'POST JSON lama tetap bekerja');
        assert.equal(sent.headers['Content-Type'], 'application/json');
        const nodes = new Map([['#order-search', { value: 'Siti' }]]);
        const urls = [];
        const listContext = {
            orderTab: 'pembayaran_terlambat',
            $: selector => { if (!nodes.has(selector)) nodes.set(selector, {}); return nodes.get(selector); },
            api: async url => {
                urls.push(url);
                if (url.startsWith('api/orders.php')) return { counts: { total: 1, payment: 0, pending: 0,
                    processing: 0, completed: 0, rejected: 0, cancelled: 1, cancellation_unreviewed: 0 },
                    capacity: { pakai: 0, kapasitas: 50 } };
                if (url.includes('eligible=1')) return { items: [{ id: 42, kode: 'ORD-42', nama_pemesan: 'Siti' },
                    { id: 43, kode: 'ORD-43', nama_pemesan: 'Siti' }] };
                return { items: [{ pesanan_id: 42, kode: 'ORD-42', nama_pemesan: 'Siti', nominal_dilaporkan: 1000,
                    status_rekonsiliasi: 'menunggu_verifikasi' }] };
            },
            esc: String, money: String, dateLabel: String,
        };
        vm.createContext(listContext);
        vm.runInContext(admin.slice(admin.indexOf('async function loadOrders()'), admin.indexOf('function ordersPage()')), listContext);
        return listContext.loadOrders().then(() => {
            assert.ok(urls.includes('api/late-payments.php?q=Siti'));
            assert.ok(urls.includes('api/late-payments.php?eligible=1&q=Siti'));
            assert.match(nodes.get('#late-payment-view').innerHTML, /ORD-42/);
            assert.match(nodes.get('#late-payment-view').innerHTML, /ORD-43/);
            assert.match(nodes.get('#late-payment-view').innerHTML, /Catat bukti/);
            assert.equal((nodes.get('#late-payment-view').innerHTML.match(/ORD-42/g) || []).length, 1,
                'Catatan yang masuk di sela dua GET tidak ditampilkan dua kali');
            assert.match(admin, /\$\("#order-search"\)\.closest\("\.search"\)\.hidden = false/);
            console.log('PASS: pencarian tab dan calon pesanan; bukti multipart dan POST JSON memakai transport yang tepat');
        });
    });
}).catch(error => { console.error(error); process.exitCode = 1; });
