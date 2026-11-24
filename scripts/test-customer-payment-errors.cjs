const fs = require('node:fs'), vm = require('node:vm'), assert = require('node:assert/strict');
const source = fs.readFileSync('public/pelanggan/assets/customer.js', 'utf8');
const elements = new Map();
const node = selector => {
    if (!elements.has(selector)) elements.set(selector, {
        innerHTML: '', textContent: '', hidden: false, disabled: false,
        showModal() { this.open = true; },
        insertAdjacentHTML(position, html) { this.innerHTML = html + this.innerHTML; },
        querySelector() { return node('#submit-proof'); },
    });
    return elements.get(selector);
};
let detail, mode, postCount = 0, pending;
const context = {
    $: selector => ['#history-detail', '#history-dialog'].includes(selector)
        || node('#history-detail').innerHTML.includes('id="' + selector.slice(1) + '"') ? node(selector) : null,
    api: async (url, options = {}) => {
        if (options.method === 'POST') {
            postCount++;
            if (mode === 'pending') return new Promise(resolve => pending = resolve);
            if (mode === 'network') throw new TypeError('Failed to fetch');
            if ([422, 409, 401, 500].includes(mode)) {
                if (mode === 409) detail.can_upload_proof = false;
                throw Object.assign(Error('Pesan validasi server'), {status: mode});
            }
            if (mode === 'invalid') throw Object.assign(Error('Respons tidak valid'), {status: 200, uncertain: true});
            return {ok: true};
        }
        if (url.includes('payment-methods')) {
            if (mode === 'methods') throw Error('gagal');
            return {items: mode === 'qr' ? [{nama: 'QRIS', gambar: 'uploads/payment-methods/qris.png'}] : []};
        }
        if (mode === 'detail' || mode === 'refresh') throw Error('gagal');
        return detail;
    },
    clearTimeout() {}, setTimeout() {}, paymentExpiryTimer: null,
    imagePath: String, esc: String, money: String, dateLabel: String,
    statusLabels: {}, statusProgress: () => '', toast() {}, loadHistory() {},
    FormData: class {}, Date,
};
vm.createContext(context);
vm.runInContext(source.slice(source.indexOf('async function openHistoryDetail('), source.indexOf('\nconst page =')), context);
const reset = async newMode => {
    elements.clear(); postCount = 0; mode = newMode;
    detail = {id: 1, items: [], status: 'menunggu_pembayaran', can_upload_proof: true};
    await context.openHistoryDetail(1);
};
const submit = () => node('#proof-form').onsubmit({preventDefault() {}});
(async () => {
    await reset('detail');
    assert.match(node('#history-detail').innerHTML, /Detail pesanan belum dapat dimuat/);
    assert.equal(typeof node('#retry-payment').onclick, 'function');
    await reset('methods');
    assert.match(node('#history-detail').innerHTML, /Metode pembayaran belum dapat dimuat/);
    assert.match(node('#history-detail').innerHTML, /id="proof-form"/);
    assert.equal(node('#retry-payment').hidden, false);
    await reset('qr');
    assert.match(node('#history-detail').innerHTML, /Gambar pembayaran belum dapat dimuat/);
    assert.doesNotMatch(node('#history-detail').innerHTML, /NK-01/);
    const qrImage = {hidden: false, nextElementSibling: {hidden: true}};
    const qrError = node('#history-detail').innerHTML.match(/onerror="([^"]+)"/)[1];
    new Function(qrError).call(qrImage);
    assert.equal(qrImage.hidden, true); assert.equal(qrImage.nextElementSibling.hidden, false);
    for (const status of [422, 500]) {
        await reset(status); await submit();
        assert.equal(node('#submit-proof').disabled, false);
        assert.match(node('#proof-error').textContent, status === 422 ? /Pesan validasi/ : /Server belum dapat/);
    }
    for (const failure of ['network', 'invalid']) {
        await reset(failure); await submit();
        assert.match(node('#payment-status').textContent, /belum dapat dipastikan/);
        assert.equal(node('#submit-proof').disabled, true);
        assert.equal(node('#retry-payment').hidden, false);
        assert.equal(postCount, 1);
    }
    await reset(409); await submit();
    assert.doesNotMatch(node('#history-detail').innerHTML, /id="proof-form"/);
    assert.match(node('#payment-status').textContent, /Status pesanan/);
    await reset(401); await submit(); assert.equal(postCount, 1);
    await reset('pending');
    const first = submit(); await submit(); assert.equal(postCount, 1);
    mode = 'refresh'; pending({ok: true}); await first;
    assert.match(node('#history-detail').innerHTML, /Bukti pembayaran berhasil dikirim/);
    assert.doesNotMatch(node('#history-detail').innerHTML, /id="proof-form"/);
    assert.equal(postCount, 1);

    const apiContext = {FormData: class {}, csrfToken: () => '', location: {}, fetch: async () => ({status: 200, ok: true, json: async () => {throw Error('bad JSON');}})};
    vm.createContext(apiContext);
    vm.runInContext(source.slice(source.indexOf('async function api('), source.indexOf('\nfunction toast(')), apiContext);
    await assert.rejects(apiContext.api('test'), err => err.uncertain === true);
    apiContext.fetch = async () => ({status: 401, ok: false});
    await assert.rejects(apiContext.api('test'), err => err.status === 401);
    assert.equal(apiContext.location.href, '../login.php');
    console.log('PASS: Payment detail/method/QR failures, upload validation/conflict/session/network/JSON, duplicate guard and confirmed upload with failed refresh');
})().catch(error => {console.error(error); process.exitCode = 1;});
