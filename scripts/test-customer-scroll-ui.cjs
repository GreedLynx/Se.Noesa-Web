const assert = require('node:assert/strict');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const { spawnSync } = require('node:child_process');
const { pathToFileURL } = require('node:url');
const chrome = process.argv[2];
if (!chrome) throw Error('Berikan path Chrome.');
const source = fs.readFileSync('public/pelanggan/assets/customer.js', 'utf8');
const searchInit = source.slice(source.indexOf('    const shortcut =', source.indexOf('function catalogPage()')), source.indexOf('    const search =', source.indexOf('function catalogPage()')));
const sidebarInit = source.slice(source.indexOf('(() => {', source.indexOf('const page = document.body.dataset.page')), source.indexOf('wireLogout();', source.indexOf('const page = document.body.dataset.page')));
const css = ['customer.css', 'catalog-mobile.css'].map(f => fs.readFileSync('public/pelanggan/assets/' + f, 'utf8')).join('\n');
const temp = fs.mkdtempSync(path.join(os.tmpdir(), 'senoesa-scroll-ui-'));
try {
    for (const [width, page, reduced] of [[320,'catalog',false],[390,'catalog',false],[1280,'catalog',false],[390,'history',false],[1280,'notifications',false],[390,'catalog',true]]) {
        const fixture = `<!doctype html><style>${css}</style><body data-page="${page}"><div class="customer-shell">
        <aside class="customer-rail" id="customer-sidebar"><button class="customer-sidebar-close">Close</button><a href="#menu">Menu</a></aside><button class="customer-sidebar-backdrop" hidden>Close menu</button>
        <main class="customer-main"><header class="customer-top"><button class="customer-sidebar-toggle" aria-expanded="false">Menu</button><div>se.noesa</div></header><div class="customer-content"><div style="height:150px"></div><label class="customer-search"><input id="catalog-search" value="ayam"></label><div style="height:2200px"></div></div></main>
        <button class="catalog-search-shortcut" hidden aria-label="Cari menu">Search</button><div class="cart-bar" hidden>Cart</div></div><script>${searchInit}${sidebarInit}
        (async()=>{try {
            const check=(v,m)=>{if(!v)throw Error(m)};
            const pause=()=>new Promise(r=>setTimeout(r,350));
            const header=document.querySelector('.customer-top'), button=document.querySelector('.catalog-search-shortcut'), input=document.querySelector('#catalog-search');
            await pause(); check(button.hidden,'Shortcut awal tersembunyi');
            scrollTo(0,700); await pause();
            check(Math.abs(header.getBoundingClientRect().top)<1,'Header sticky setelah scroll');
            check(!button.hidden,'Shortcut muncul setelah search terlewati');
            check(button.getBoundingClientRect().width>=44,'Target search minimal 44px');
            document.body.classList.add('has-cart'); document.querySelector('.cart-bar').hidden=false;
            check(button.getBoundingClientRect().bottom<document.querySelector('.cart-bar').getBoundingClientRect().top,'Search di atas cart');
            button.click(); await pause();
            check(document.activeElement===input && input.value==='ayam','Search mendapat fokus dan mempertahankan nilai');
            check(input.getBoundingClientRect().top>=header.getBoundingClientRect().bottom,'Search tidak tertutup header');
            await pause(); check(button.hidden,'Shortcut hilang saat search terlihat');
            const rail=document.querySelector('.customer-rail'), toggle=document.querySelector('.customer-sidebar-toggle');
            if(innerWidth<=700){
                check(rail.inert,'Sidebar tertutup inert'); toggle.click(); await pause();
                check(!rail.inert && toggle.getAttribute('aria-expanded')==='true','Sidebar terbuka');
                check(document.activeElement===document.querySelector('.customer-sidebar-close'),'Fokus ke sidebar');
                const duration=parseFloat(getComputedStyle(rail).transitionDuration);
                check(${reduced} ? duration<.01 : duration>0,'Preferensi gerak sidebar');
                document.dispatchEvent(new KeyboardEvent('keydown',{key:'Escape',bubbles:true})); await pause();
                check(rail.inert && document.activeElement===toggle,'Escape menutup dan mengembalikan fokus');
                check(getComputedStyle(rail).visibility==='hidden','Sidebar selesai tersembunyi');
            }
            check(document.documentElement.scrollWidth<=innerWidth,'Tidak ada overflow'); parent.document.body.dataset.result='PASS';
        }catch(e){parent.document.body.dataset.result='FAIL: '+e.message}})();</script>`;
        const file = path.join(temp,'fixture.html');
        fs.writeFileSync(file, `<!doctype html><body><iframe style="border:0;width:${width}px;height:800px" srcdoc="${fixture.replaceAll('&','&amp;').replaceAll('"','&quot;')}"></iframe>`);
        const result=spawnSync(chrome,['--headless','--disable-gpu','--no-first-run',`--user-data-dir=${path.join(temp,'profile')}`, ...(reduced ? ['--force-prefers-reduced-motion'] : []),'--virtual-time-budget=5000','--dump-dom',pathToFileURL(file).href],{encoding:'utf8',timeout:30000});
        assert.equal(result.status,0,result.error?.message||result.stderr);
        assert.equal(result.stdout.match(/data-result="([^"]*)"/)?.[1],'PASS',`${page}/${width}/reduced=${reduced}`);
        console.log(`PASS: scroll/search/sidebar ${page} ${width}px reduced=${reduced}`);
    }
} finally { fs.rmSync(temp,{recursive:true,force:true}); }
