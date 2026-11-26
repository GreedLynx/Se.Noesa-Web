const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

const source = fs.readFileSync('public/pelanggan/assets/customer.js', 'utf8');
const start = source.indexOf('const statusLabels = {');
const end = source.indexOf('let historyStatus =', start);
assert(start >= 0 && end > start, 'Blok status riwayat tersedia');
const render = vm.runInNewContext(source.slice(start, end) + '\nstatusProgress');
const done = status => [...render(status).matchAll(/<span class="detail-step done">([^<]+)<\/span>/g)].map(match => match[1]);

assert.deepEqual(done('menunggu_pembayaran'), ['Pesanan']);
assert.deepEqual(done('menunggu_validasi'), ['Pesanan', 'Pembayaran']);
assert.deepEqual(done('diproses'), ['Pesanan', 'Pembayaran', 'Verifikasi', 'Diproses']);
assert.deepEqual(done('selesai'), ['Pesanan', 'Pembayaran', 'Verifikasi', 'Diproses', 'Selesai']);
for (const status of ['ditolak', 'dibatalkan']) {
    assert.deepEqual(done(status), []);
    assert.match(render(status), /Pesanan (Ditolak|Dibatalkan)/);
}
console.log('PASS: bar status riwayat pelanggan mengikuti verifikasi Admin');
