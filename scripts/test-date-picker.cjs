// Uji kalender tanpa dependensi; tambahkan path Chrome/Edge untuk uji DOM nyata.
const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const path = require('node:path');
const os = require('node:os');
const { spawnSync } = require('node:child_process');
const { pathToFileURL } = require('node:url');

const source = fs.readFileSync('public/assets/js/date-picker.js', 'utf8');
const context = vm.createContext({ Intl, Date });
vm.runInContext(source.slice(source.indexOf('    const dayFormat'), source.indexOf('    document.querySelectorAll')), context);
assert.equal(vm.runInContext("monthDays(2028, 1).days.length", context), 29);
assert.equal(vm.runInContext("monthDays(2027, 1).days.length", context), 28);
assert.equal(vm.runInContext("monthDays(2026, 10).offset", context), 6);
assert.equal(vm.runInContext("monthDays(2026, 11).days.at(-1)", context), '2026-12-31');
assert.equal(vm.runInContext("dateFromValue('2026-02-30')", context), null);
assert.equal(vm.runInContext("dateFromValue('invalid')", context), null);
assert.equal(vm.runInContext("isAvailable('2030-01-10', '2030-01-11', '')", context), false);
assert.equal(vm.runInContext("isAvailable('2030-01-11', '2030-01-11', '')", context), true);
assert.equal(vm.runInContext("isAvailable('2030-02-01', '', '2030-01-31')", context), false);
assert.equal(vm.runInContext("isAvailable('2035-01-01', '', '')", context), true);
console.log('PASS: Kalender kabisat, pergantian bulan/tahun, tanggal tidak valid, dan batas min/max');

if (process.argv[2]) {
    const directory = fs.mkdtempSync(path.join(os.tmpdir(), 'senoesa-date-picker-'));
    const htmlPath = path.join(directory, 'test.html');
    const css = fs.readFileSync('public/assets/css/date-picker.css', 'utf8');
    const fixture = `<section class="date-panel date-panel--delivery"><div><span class="date-eyebrow">RENCANAKAN PESANANMU</span><h2 class="date-panel-title">Mau dikirim kapan?</h2><p class="date-panel-hint" id="hint">Pilih tanggal untuk melihat menu yang tersedia. Pengiriman paling awal 7 hari dari hari ini.</p></div><div class="date-field"><label for="date">Tanggal pengiriman</label><input id="date" type="date" min="2030-01-11" value="2030-01-11" aria-describedby="hint" data-date-picker></div></section>`;
    const checks = `
        const check = (condition, message) => { if (!condition) throw Error(message); };
        try {
            const input = document.querySelector('input');
            const trigger = document.querySelector('.date-trigger');
            const dialog = document.querySelector('dialog');
            const app = document.querySelector('.app');
            if (app) {
                const style = getComputedStyle(app);
                const contentWidth = app.clientWidth - parseFloat(style.paddingLeft) - parseFloat(style.paddingRight);
                check(document.querySelector('.date-panel').getBoundingClientRect().width >= contentWidth - 2, 'Filter tanggal memenuhi lebar grid Owner');
                const summary = document.querySelector('#quota-summary');
                if (summary) check(summary.getBoundingClientRect().width >= contentWidth - 2, 'Ringkasan kuota memenuhi lebar grid Owner');
            }
            check(input.hidden, 'Input asli disimpan');
            check(trigger.textContent.includes('11 Januari 2030'), 'Tanggal Indonesia');
            let changes = 0;
            input.onchange = () => changes++;
            input.min = '2030-01-11';
            trigger.click();
            check(dialog.open && trigger.getAttribute('aria-expanded') === 'true', 'Kalender terbuka');
            check(dialog.querySelector('[data-day="2030-01-10"]').disabled, 'Tanggal sebelum minimum nonaktif');
            check(dialog.querySelector('[data-month="-1"]').disabled, 'Navigasi tidak melewati minimum');
            dialog.querySelector('[data-day="2030-01-12"]').click();
            check(input.value === '2030-01-12' && changes === 1 && !dialog.open, 'Pemilihan mengirim satu event change');
            trigger.click();
            dialog.querySelector('[data-day="2030-01-12"]').click();
            check(changes === 1, 'Tanggal sama tidak memuat ulang');
            trigger.click();
            dialog.querySelector('[data-day="2030-01-31"]').dispatchEvent(new KeyboardEvent('keydown', {key: 'ArrowRight', bubbles: true}));
            check(document.activeElement.dataset.day === '2030-02-01', 'Keyboard lintas bulan');
            dialog.querySelector('[data-dismiss]').click();
            check(!dialog.open && input.value === '2030-01-12', 'Batal mempertahankan tanggal');
            input.value = '2030-02-15';
            input.dispatchEvent(new Event('input', {bubbles: true}));
            check(trigger.textContent.includes('15 Februari 2030'), 'Sinkron tanggal dari checkout');
            check(document.documentElement.scrollWidth <= innerWidth, 'Tidak ada overflow horizontal');
            trigger.click();
            const bounds = dialog.getBoundingClientRect();
            check(bounds.left >= 0 && bounds.right <= innerWidth, 'Kalender muat di viewport');
            document.body.dataset.result = 'PASS';
            parent.document.body.dataset.result = 'PASS';
        } catch (error) { parent.document.body.dataset.result = 'FAIL: ' + error.message; }
    `;
    for (const [role, width, page = 'products'] of [['owner', 1280], ['owner', 1280, 'orders'], ['pelanggan', 390], ['admin', 1280], ['owner', 390], ['owner', 320], ['pelanggan', 320]]) {
        const roleCss = fs.readFileSync({ pelanggan: 'public/pelanggan/assets/customer.css', admin: 'public/admin/assets/admin.css', owner: 'public/assets/css/app.css' }[role], 'utf8');
        const ownerPanel = fs.readFileSync('public/owner/partials/layout.php', 'utf8').match(/<section class="date-panel"[\s\S]*?<\/section>/)[0].replace(/<\?=[\s\S]*?\?>/g, '2030-01-11');
        const content = role === 'owner' ? `<main class="app"><header class="top"><h1>${page === 'products' ? 'Produk &amp; Kuota' : 'Pesanan'}</h1></header>${ownerPanel}${page === 'products' ? '<p id="quota-summary">Ringkasan kapasitas tanggal pilihan</p><section id="plist" class="card"></section>' : '<div id="tabs"></div><section class="card center">Kapasitas Dapur</section><label class="search">Cari pesanan</label><div id="list"></div>'}</main>` : fixture;
        const frame = `<!doctype html><html lang="id"><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><style>${roleCss}\n${css}\n${role === 'owner' ? '' : 'body{display:block;padding:16px} .date-panel{max-width:1100px}'}</style><body data-page="${page}">${content}<script>${source}</script><script>${checks.replace("const input =", `check(innerWidth === ${width}, 'Viewport sesuai ukuran uji'); const input =`)}</script></body></html>`;
        const escapedFrame = frame.replaceAll('&', '&amp;').replaceAll('"', '&quot;');
        fs.writeFileSync(htmlPath, `<!doctype html><html><body style="margin:0"><iframe title="Uji kalender" style="border:0;width:${width}px;height:850px" srcdoc="${escapedFrame}"></iframe></body></html>`);
        const screenshot = process.argv[3] && role === 'owner' && width === 1280 && page === 'products' ? [`--screenshot=${path.resolve(process.argv[3])}`] : [];
        const result = spawnSync(process.argv[2], ['--headless', '--disable-gpu', '--no-first-run', '--no-default-browser-check', `--user-data-dir=${path.join(directory, 'profile')}`, `--window-size=${width},850`, ...screenshot, '--dump-dom', pathToFileURL(htmlPath).href], { encoding: 'utf8', timeout: 30000 });
        assert.equal(result.status, 0, result.error?.message || result.stderr);
        assert.equal(result.stdout.match(/data-result="([^"]*)"/)?.[1], 'PASS', `${role}/${page} ${width}px`);
        console.log(`PASS: DOM ${role} ${width}px — memilih, batal, min, keyboard, event, responsive`);
    }
    fs.rmSync(directory, { recursive: true, force: true });
}
