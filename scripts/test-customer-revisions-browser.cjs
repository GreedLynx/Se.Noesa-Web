// Render markup/CSS aplikasi tanpa bootstrap atau database; API dimock di browser.
const fs = require('node:fs');
const path = require('node:path');
const os = require('node:os');
const assert = require('node:assert/strict');
const { spawnSync } = require('node:child_process');
const { pathToFileURL } = require('node:url');

const chrome = process.argv[2];
const paymentOnly = process.argv.includes('--payment');
const customerOnly = process.argv.includes('--customer');
if (!chrome) throw Error('Berikan path Chrome sebagai argumen.');
const root = process.cwd();
const output = fs.mkdtempSync(path.join(os.tmpdir(), 'senoesa-revisions-'));
const read = file => fs.readFileSync(file, 'utf8');
const url = file => pathToFileURL(path.resolve(file)).href;
const stripPhp = html => html.replace(/<\?(?:php|=)[\s\S]*?\?>/g, '');
const css = files => files.map(file => read(file).replace(/url\((['"]?)(?!data:|https?:)([^)'"\s]+)\1\)/g,
    (_, quote, value) => `url("${url(path.resolve(path.dirname(file), value))}")`)).join('\n');
const customerCss = css(['public/pelanggan/assets/customer.css', 'public/pelanggan/assets/catalog-mobile.css',
    'public/pelanggan/assets/quantity-input.css', 'public/pelanggan/assets/menu-notifications.css',
    'public/assets/component/icons.css']);
const authCss = css(['public/assets/css/app.css', 'public/assets/component/icons.css']);
const assetBase = url('public/') + '/';
const customerBase = url('public/pelanggan/') + '/';
const layout = read('public/pelanggan/partials/layout.php');
const shellStart = layout.indexOf('<div class="customer-shell">');
const start = layout.slice(shellStart, layout.indexOf('<?php', layout.indexOf('<div class="customer-content">', shellStart)));
const footerStart = layout.indexOf('</div>', layout.indexOf('function customer_foot'));
const end = layout.slice(footerStart, layout.indexOf('    <script src="assets/customer.js'));
const content = file => stripPhp(read(file).slice(read(file).indexOf('?>') + 2)).replace(/<script>[\s\S]*?<\/script>/g, '');
const fail = `const check=(v,m)=>{if(!v)throw Error(m)}; const box=e=>e.getBoundingClientRect();`;

function run(name, page, markup, styles, code, width, height = 900, screenshot = false) {
    const frame = `<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><base href="${page === 'login' || page === 'register' ? assetBase : customerBase}"><style>${styles}\n.rise{animation:none;opacity:1}</style></head><body data-page="${page}">${markup}<script>window.fixtureErrors=[];window.addEventListener('unhandledrejection',e=>fixtureErrors.push(String(e.reason)));window.addEventListener('error',e=>fixtureErrors.push(e.message));</script><script>${code}</script></body></html>`;
    const file = path.join(output, `${name}-${width}.html`);
    fs.writeFileSync(file, `<!doctype html><html><body style="margin:0"><iframe title="Fixture ${page}" style="border:0;width:${width}px;height:${height}px" srcdoc="${frame.replaceAll('&', '&amp;').replaceAll('"', '&quot;')}"></iframe></body></html>`);
    const args = ['--headless', '--disable-gpu', '--no-first-run', '--no-default-browser-check',
        '--allow-file-access-from-files', `--user-data-dir=${path.join(output, 'profile')}`,
        `--window-size=${Math.max(width, 800)},${height}`, '--virtual-time-budget=1500', '--dump-dom'];
    if (screenshot) args.push(`--screenshot=${path.join(output, `${name}-${width}.png`)}`);
    const result = spawnSync(chrome, [...args, pathToFileURL(file).href], {encoding: 'utf8', timeout: 30000});
    assert.equal(result.status, 0, result.error?.message || result.stderr);
    assert.equal(result.stdout.match(/data-result="([^"]*)"/)?.[1], 'PASS', `${name} ${width}px: ${result.stdout.slice(-1600)}`);
    console.log(`PASS: ${name} ${width}x${height}`);
}

for (const page of paymentOnly || customerOnly ? [] : ['login', 'register']) {
    const source = read(`public/${page}.php`);
    const markup = `<main class="app auth">${stripPhp(source.slice(source.indexOf('<section'), source.lastIndexOf('<?php foot')))}</main>`;
    const checks = `${fail}
    window.addEventListener('load',()=>{try {
        const logos=document.querySelectorAll('.auth-logo');check(logos.length===1,'Tepat satu logo');
        const logo=logos[0], hero=document.querySelector('.hero'), drift=hero.querySelector('.drift'), form=document.querySelector('form');
        check(logo.complete&&logo.naturalWidth>0,'Aset logo termuat');
        const l=box(logo),h=box(hero);check(l.width>0&&l.height>0,'Logo terlihat');
        check(l.left>=h.left&&l.right<=h.right+1&&l.top>=h.top&&l.bottom<=h.bottom+1,'Logo dalam hero');
        check(getComputedStyle(logo).objectFit==='contain','Proporsi logo dipertahankan');
        const d=box(drift);check(d.width>=h.width+401&&d.height>=h.height+259,'Lapisan pattern menutup hero sepanjang animasi');
        if(innerWidth>=760){check(Math.abs((l.left+l.right-h.left-h.right)/2)<2&&Math.abs((l.top+l.bottom-h.top-h.bottom)/2)<2,'Logo auth desktop di tengah hero');check(l.height>132,'Logo auth desktop diperbesar');}
        const text=document.querySelector('.hello');if(text){const t=box(text);check(l.bottom<=t.top||l.left>=t.right||l.right<=t.left,'Logo tidak menimpa welcome');}
        check(form.querySelector('button').getBoundingClientRect().width>0,'Submit dapat diakses');
        check(document.documentElement.scrollWidth<=innerWidth,'Tidak ada overflow horizontal');
        if(innerWidth>=760)check(box(document.querySelector('.sheet')).left>=h.right-1,'Layout desktop dua kolom');
        parent.document.body.dataset.result='PASS';
    }catch(e){parent.document.body.dataset.result='FAIL: '+e.message;}});`;
    for (const width of [320,390,759,760,1280]) run(page,page,markup,authCss,checks,width,900,[390,1280].includes(width));
    run(`${page}-landscape`,page,markup,authCss,checks,844,390,true);
}

const fixtures = [
    {id:1,nama:'Menu tersedia',kategori:'makanan',gambar:'NK-01.png',harga:10000,min_pesan:4,minimum:4,kuota:50,sisa:25,available:true},
    {id:2,nama:'Menu hampir habis',kategori:'makanan',gambar:'NK-01.png',harga:10000,min_pesan:4,minimum:4,kuota:50,sisa:24,available:true},
    {id:3,nama:'Menu habis',kategori:'makanan',gambar:'NK-01.png',harga:10000,min_pesan:4,minimum:4,kuota:50,sisa:0,available:false,stok_status:'full'}
];
const mock = `window.fixtureProducts=${JSON.stringify(fixtures)};window.fixtureMode='normal';window.fixturePosts=0;
const fixtureDetail={id:10,kode:'UJI-10',items:[],status:'menunggu_pembayaran',can_cancel:false,can_upload_proof:true,total:40000,tgl_kirim:'2030-01-10',created_at:'2030-01-01',payment_expires_at:new Date(Date.now()+3600000).toISOString(),foto_kiriman_list:[]};
window.fetch=async(input,options={})=>{const u=String(input);if(options.method==='POST'){fixturePosts++;if(fixtureMode==='upload-network')throw Error('Offline');if(fixtureMode==='upload-json')return {ok:true,status:200,json:async()=>{throw SyntaxError('Invalid JSON')}};if(fixtureMode==='upload-422')return {ok:false,status:422,json:async()=>({error:'Gunakan gambar JPG, PNG, atau WebP'})};if(fixtureMode==='upload-500')return {ok:false,status:500,json:async()=>({error:'Gagal menyimpan bukti'})};if(fixtureMode==='upload-success-refresh-error'){fixtureMode='detail-error';return {ok:true,status:200,json:async()=>({ok:true})};}}
if(u.includes('orders.php?id=')){if(fixtureMode==='detail-error')throw Error('Offline');return {ok:true,status:200,json:async()=>fixtureDetail};}
if(u.includes('payment-methods')){if(fixtureMode==='method-error')throw Error('Offline');return {ok:true,status:200,json:async()=>({items:fixtureMode==='qris-error'?[{nama:'QRIS',gambar:'uploads/payment-methods/fixture-missing.png'}]:[]})};}
let d=u.includes('catalog.php')?{items:fixtureProducts,habis:[],schedule:null,tanggal:'2030-01-10',dailyStatus:'available',dailyCapacity:150,dailyRemaining:150}:u.includes('addresses=')?{addresses:[]}:u.includes('orders.php')?{orders:[]}:{};
return {ok:true,status:200,json:async()=>d};};`;
const script = read('public/pelanggan/assets/customer.js');
for (const page of paymentOnly ? ['history'] : ['catalog','history','notifications']) {
    const file = page==='catalog'?'public/pelanggan/index.php':page==='history'?'public/pelanggan/riwayat.php':'public/pelanggan/menu-notifications.php';
    const markup = stripPhp(start) + content(file) + stripPhp(end);
    const checks = `${fail}
    window.addEventListener('load',()=>setTimeout(async()=>{try{
        if(!${paymentOnly}){
        const area=document.querySelector('.customer-content');
        const pattern=[document.querySelector('.customer-main'),area].flatMap(e=>['::before','::after'].map(p=>getComputedStyle(e,p))).find(s=>s.backgroundImage.includes('Pattern_se.noesa.png'));
        check(pattern&&Number(pattern.opacity)>0&&Number(pattern.opacity)<=.08,'Pola rendah opacity tersedia');
        check(pattern.pointerEvents==='none','Pola tidak menghalangi interaksi');
        const policy=document.querySelector('.catalog-policy'),date=document.querySelector('label[for="checkout-date"]');
        check(document.querySelectorAll('.catalog-policy').length===1,'Satu info pemesanan');
        check(!!(policy.compareDocumentPosition(date)&Node.DOCUMENT_POSITION_FOLLOWING),'Info sebelum tanggal');
        }
        check(document.documentElement.scrollWidth<=innerWidth,'Tidak ada overflow horizontal');
        if(document.body.dataset.page==='catalog'){
            const cards=document.querySelectorAll('.menu-card');check(cards.length===3,'Katalog asli dirender');
            check(!cards[0].innerText.includes('Sisa kuota'),'50% tidak tampil');check(cards[1].innerText.includes('24 porsi'),'Di bawah 50% tampil');check(cards[2].innerText.includes('Stok habis'),'Stok habis tetap tampil');
            showProduct({...fixtureProducts[0],sisa:29});let s=document.querySelector('#detail-sisa');check(!s.checkVisibility(),'Detail tepat50% tersembunyi');
            document.querySelector('[data-qty="plus"]').click();check(s.checkVisibility()&&s.innerText.includes('24'),'Detail melewati ambang tampil');
            document.querySelector('[data-qty="minus"]').click();check(!s.checkVisibility(),'Kurangi kembali menyembunyikan');
            document.querySelector('#add-to-cart').click();openCart();check(document.querySelector('#cart-dialog').open,'Keranjang terbuka');document.querySelector('#cart-dialog').close();
            await openCheckout();check(document.querySelector('#checkout-dialog').open,'Checkout terbuka');document.querySelector('#checkout-dialog').close();
        }
        if(document.body.dataset.page==='history'){
            fixtureMode='detail-error';await openHistoryDetail(10);check(document.querySelector('#history-dialog').open,'Error detail tetap membuka dialog');check(document.querySelector('[role="alert"]').checkVisibility(),'Error detail terlihat');
            fixtureMode='method-error';await openHistoryDetail(10);check(document.querySelector('#history-detail').innerText.includes('Metode pembayaran belum dapat dimuat'),'Error metode terlihat');
            check(!!document.querySelector('#proof-form'),'Form bukti tetap tersedia');
            fixtureMode='qris-error';await openHistoryDetail(10);await new Promise(resolve=>setTimeout(resolve,50));
            check(document.querySelector('.qris-display').innerText.includes('Gambar pembayaran belum dapat dimuat'),'Gagal gambar QRIS diberi pesan');
            check(!document.querySelector('.qris-display img[src*="NK-01"]'),'QRIS tidak diganti gambar menu');
            for(const mode of ['upload-422','upload-500','upload-network','upload-json']){
                fixtureMode='normal';await openHistoryDetail(10);fixtureMode=mode;
                const form=document.querySelector('#proof-form');form.elements.nama_pembayar.value='Pelanggan uji';
                const before=fixturePosts;await form.onsubmit({preventDefault(){}});
                const error=document.querySelector('#proof-error');check(error.textContent&&error.checkVisibility(),'Error upload terlihat '+mode);
                check(document.activeElement===document.querySelector('#payment-status'),'Fokus ke pesan pembayaran '+mode);
                check(form.elements.nama_pembayar.value==='Pelanggan uji','Input dipertahankan '+mode);
                check(fixturePosts===before+1,'Tidak auto retry POST '+mode);
                check(form.querySelector('button').disabled===(mode==='upload-network'||mode==='upload-json'),'Retry sesuai kepastian hasil '+mode);
            }
            fixtureMode='normal';await openHistoryDetail(10);fixtureMode='upload-success-refresh-error';
            const before=fixturePosts;await document.querySelector('#proof-form').onsubmit({preventDefault(){}});
            check(document.querySelector('#history-detail').innerText.includes('Bukti pembayaran berhasil dikirim'),'POST berhasil tidak dilaporkan gagal');
            check(!document.querySelector('#proof-form'),'Tidak menawarkan submit ulang setelah konfirmasi sukses');
            check(fixturePosts===before+1,'Tidak POST ulang saat refresh gagal');
        }
        check(!fixtureErrors.length,'Tidak ada unhandled rejection: '+fixtureErrors.join(','));
        parent.document.body.dataset.result='PASS';
    }catch(e){parent.document.body.dataset.result='FAIL: '+e.message;console.error(e);}},200));`;
    for (const width of [320,390,1280]) run(page,page,markup,customerCss,mock+'\n'+script+'\n'+checks,width,900,width===390);
}
console.log(`Screenshot/fixtures: ${output}`);
