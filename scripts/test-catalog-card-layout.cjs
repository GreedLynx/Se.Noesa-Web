const assert = require('node:assert/strict');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const { spawnSync } = require('node:child_process');
const { pathToFileURL } = require('node:url');

const chrome = process.argv[2];
if (!chrome) throw Error('Berikan path Chrome sebagai argumen.');
const css = ['public/pelanggan/assets/customer.css', 'public/pelanggan/assets/catalog-mobile.css']
    .map(file => fs.readFileSync(file, 'utf8')).join('\n');
const markup = `<div class="customer-shell"><main class="customer-main"><div class="customer-content">
    <div class="menu-grid"><article class="menu-card stock-full menu-unavailable">
    <span class="menu-warning">Stok habis</span><div class="menu-photo"><img class="menu-image" alt="Menu uji"></div><div class="menu-card-copy"><h3>Menu uji</h3>
    <p>Min. 10 porsi · Sisa kuota: 0 porsi</p><strong class="menu-price">Rp 10.000</strong></div>
    <div class="menu-action"><button class="menu-add" disabled>+</button></div>
    </article></div></div></main></div>`;
const check = `<script>
try {
    const card = document.querySelector('.menu-card').getBoundingClientRect();
    const grid = document.querySelector('.menu-grid').getBoundingClientRect();
    const copy = document.querySelector('.menu-card-copy').getBoundingClientRect();
    const action = document.querySelector('.menu-action').getBoundingClientRect();
    const warning = document.querySelector('.menu-warning').getBoundingClientRect();
    const add = document.querySelector('.menu-add').getBoundingClientRect();
    const fail = reason => { throw Error(reason); };
    if (innerWidth <= 700) {
        if (!(card.left > grid.left && card.right < grid.right)) fail('Kartu menyentuh tepi grid');
        if (!(copy.left >= card.left + 12 && action.right <= card.right - 12)) fail('Isi menyentuh tepi kartu');
        if (getComputedStyle(document.querySelector('.menu-card')).borderStyle !== 'solid') fail('Batas kartu hilang');
    }
    if (!(warning.width > warning.height && Math.abs(warning.right - (card.right - 12)) <= 3 && Math.abs(warning.top - (card.top + 12)) <= 3)) fail('Label tidak di pojok kanan atas kartu');
    const labelStyle = getComputedStyle(document.querySelector('.menu-warning'));
    if (labelStyle.backgroundColor === 'rgba(0, 0, 0, 0)' || labelStyle.color !== 'rgb(255, 255, 255)' || parseFloat(labelStyle.borderRadius) > 6) fail('Gaya pita abu hilang');
    if (!(getComputedStyle(document.querySelector('.menu-add')).opacity < 1)) fail('Tombol habis tidak pudar');
    if (document.documentElement.scrollWidth > innerWidth) fail('Overflow horizontal');
    parent.document.body.dataset.result = 'PASS';
} catch (error) { parent.document.body.dataset.result = 'FAIL: ' + error.message; }
</script>`;
const temp = fs.mkdtempSync(path.join(os.tmpdir(), 'senoesa-catalog-card-'));
try {
    for (const width of [320, 390, 1280]) {
        const frame = `<!doctype html><html lang="id"><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><style>${css}</style><body data-page="catalog">${markup}${check}</body></html>`;
        const html = `<!doctype html><html><body><iframe title="Uji kartu katalog" style="border:0;width:${width}px;height:800px" srcdoc="${frame.replaceAll('&', '&amp;').replaceAll('"', '&quot;')}"></iframe></body></html>`;
        const file = path.join(temp, 'fixture.html');
        fs.writeFileSync(file, html);
        const result = spawnSync(chrome, ['--headless', '--disable-gpu', '--no-first-run', '--no-default-browser-check', `--user-data-dir=${path.join(temp, 'profile')}`, `--window-size=${width},800`, '--dump-dom', pathToFileURL(file).href], { encoding: 'utf8', timeout: 30000 });
        assert.equal(result.status, 0, result.error?.message || result.stderr);
        assert.equal(result.stdout.match(/data-result="([^"]*)"/)?.[1], 'PASS', `${width}px`);
        console.log(`PASS: kartu katalog ${width}px, ruang tepi, label, tombol, overflow`);
    }
} finally {
    fs.rmSync(temp, { recursive: true, force: true });
}
