const assert = require('node:assert/strict');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const { spawnSync } = require('node:child_process');
const { pathToFileURL } = require('node:url');

const chrome = process.argv[2];
if (!chrome) throw Error('Berikan path Chrome sebagai argumen.');
const css = fs.readFileSync('public/assets/css/date-picker.css', 'utf8');
const source = fs.readFileSync('public/assets/js/date-picker.js', 'utf8');
const temp = fs.mkdtempSync(path.join(os.tmpdir(), 'senoesa-calendar-status-'));
try {
    for (const [width, scenario, expected] of [[320, 'same', '2030-01-14'], [390, 'same', '2030-01-14'], [1280, 'same', '2030-01-14'], [390, 'next', '2030-02-01'], [390, 'error', '2030-01-11']]) {
        const fixture = `<!doctype html><html lang="id"><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><style>${css}</style><body style="padding:16px">
            <section class="date-panel date-panel--delivery"><div class="date-field"><label for="delivery">Tanggal pengiriman</label><input id="delivery" type="date" min="2030-01-11" value="2030-01-11" data-date-picker data-availability-url="api/availability.php"></div></section>
            <script>
            window.fetch = async url => {
                if (${JSON.stringify(scenario)} === 'error') return { ok:false, json:async()=>({error:'Gagal memuat'}) };
                const params = new URL(url, 'https://example.test/').searchParams;
                const dates = [];
                for (let date = new Date(params.get('start') + 'T12:00:00'); date <= new Date(params.get('end') + 'T12:00:00'); date.setDate(date.getDate() + 1)) {
                    const value = date.getFullYear() + '-' + String(date.getMonth() + 1).padStart(2, '0') + '-' + String(date.getDate()).padStart(2, '0');
                    const states = { '2030-01-11':'closed', '2030-01-12':'nearly_full', '2030-01-13':'full', '2030-01-14':'available', '2030-01-15':'menu_empty' };
                    const status = ${JSON.stringify(scenario)} === 'next' ? (value === '2030-02-01' ? 'available' : 'full') : (states[value] || 'full');
                    dates.push({tanggal:value,status,sisa:status === 'available' ? 21 : 0,menu_tersedia:status === 'menu_empty' ? 0 : 1});
                }
                return { ok:true, json:async()=>({dates}) };
            };
            </script><script>${source}</script><script>
            (async () => {
                try {
                    const input = document.querySelector('#delivery');
                    let changes = 0;
                    input.addEventListener('change', () => changes++);
                    document.querySelector('.date-trigger').click();
                    await new Promise(resolve => setTimeout(resolve, 0));
                    const dialog = document.querySelector('.date-calendar');
                    const first = dialog.querySelector('.date-first-button');
                    const check = (ok, message) => { if (!ok) throw Error(message); };
                    check(first.textContent === 'Tanggal tersedia', 'Teks tombol tanggal tersedia');
                    if (${JSON.stringify(scenario)} === 'same') {
                        for (const [day, status] of [['11','closed'],['12','nearly-full'],['13','full'],['14','available'],['15','menu-empty']]) {
                            const button = dialog.querySelector('[data-day="2030-01-' + day + '"]');
                            check(button.classList.contains('date-' + status), 'Status ' + day + ': ' + button.className);
                            check(getComputedStyle(button, '::after').content !== 'none', 'Titik warna ' + day);
                            check(getComputedStyle(button, '::after').bottom === '3px', 'Titik di bawah tanggal ' + day);
                        }
                        check(dialog.querySelector('[data-day="2030-01-15"]').disabled, 'Menu Habis tidak dapat dipilih');
                    }
                    first.click();
                    await new Promise(resolve => setTimeout(resolve, 20));
                    check(input.value === ${JSON.stringify(expected)}, 'Tanggal terdekat: ' + input.value);
                    check(changes === (${JSON.stringify(scenario)} === 'error' ? 0 : 1), 'Event perubahan tanggal');
                    if (${JSON.stringify(scenario)} === 'error') check(first.textContent === 'Gagal memuat. Coba lagi', 'Gagal fetch dapat diulang');
                    parent.document.body.dataset.result = 'PASS';
                } catch (error) { parent.document.body.dataset.result = 'FAIL: ' + error.message; }
            })();
            </script></body></html>`;
        const html = `<!doctype html><html><body><iframe title="Uji status kalender" style="border:0;width:${width}px;height:850px" srcdoc="${fixture.replaceAll('&', '&amp;').replaceAll('"', '&quot;')}"></iframe></body></html>`;
        const file = path.join(temp, 'fixture.html');
        fs.writeFileSync(file, html);
        const result = spawnSync(chrome, ['--headless', '--disable-gpu', '--no-first-run', '--no-default-browser-check', `--user-data-dir=${path.join(temp, 'profile')}`, `--window-size=${width},850`, '--virtual-time-budget=1500', '--dump-dom', pathToFileURL(file).href], { encoding:'utf8', timeout:30000 });
        assert.equal(result.status, 0, result.error?.message || result.stderr);
        assert.equal(result.stdout.match(/data-result="([^"]*)"/)?.[1], 'PASS', `${scenario}/${width}px`);
        console.log(`PASS: kalender ${scenario} ${width}px`);
    }
} finally {
    fs.rmSync(temp, { recursive:true, force:true });
}
