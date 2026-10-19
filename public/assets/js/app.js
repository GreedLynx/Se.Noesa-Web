const $=(s,r=document)=>r.querySelector(s),$$=(s,r=document)=>[...r.querySelectorAll(s)];
const rp=n=>'Rp '+Number(n).toLocaleString('id-ID'),short=n=>'Rp '+(n/1000).toFixed(1).replace('.0','')+'k';
const page=document.body.dataset.page;
async function api(url,opt={}){
  const r=await fetch(url,{headers:{'Content-Type':'application/json'},...opt,body:opt.body&&JSON.stringify(opt.body)});
  const d=await r.json().catch(()=>({error:'Respon server tidak valid'}));
  if(r.status===401&&page!=='login'){location.href='login.php';return Promise.reject()}
  if(!r.ok)throw Object.assign(new Error(d.error||'Terjadi kesalahan'),d);return d;
}
function toast(m){const t=$('#toast');t.textContent=m;t.classList.add('on');setTimeout(()=>t.classList.remove('on'),2200)}
function countUp(el,to,ms=1100){const f=+el.dataset.from||0,t0=performance.now();el.dataset.from=to;
  (function s(t){const p=Math.min((t-t0)/ms,1),e=1-Math.pow(1-p,4);el.textContent=Math.round(f+(to-f)*e).toLocaleString('id-ID');if(p<1)requestAnimationFrame(s)})(t0)}
const stagger=r=>$$('.rise',r).forEach((e,i)=>e.style.setProperty('--i',i));
stagger();
/* transisi antar halaman */
document.addEventListener('click',e=>{const a=e.target.closest('a[href]');if(!a||a.target||a.href.includes('#'))return;
  e.preventDefault();document.body.classList.add('leave');setTimeout(()=>location.href=a.href,230)});
/* pill navigasi */
const on=$('.nav a.on');if(on){const mv=()=>{$('#pill').style.left=on.offsetLeft+on.offsetWidth/2-35+'px'};mv();addEventListener('resize',mv)}
/* toggle password */
$$('.eye').forEach(b=>b.onclick=()=>{const i=b.previousElementSibling;i.type=i.type==='password'?'text':'password'});
$$('[data-logout]').forEach(b=>b.onclick=async()=>{b.disabled=true;try{await api('api/auth.php?a=logout');location.href='login.php'}catch(e){b.disabled=false;toast('Gagal keluar: '+e.message)}});
const PAGES={
splash(){setTimeout(()=>{document.body.classList.add('leave');const next=document.body.dataset.next;setTimeout(()=>location.href=(next==='dashboard.php'?'dashboard.php':'login.php'),230)},2600)},
async form(){const f=$('#f');f.onsubmit=async e=>{e.preventDefault();const b=$('.btn-main',f),er=$('#err');er.textContent='';er.classList.remove('on');b.disabled=true;b.textContent='Memproses…';
  try{const d=await api(f.dataset.api,{method:'POST',body:Object.fromEntries(new FormData(f))});toast('Berhasil');setTimeout(()=>{document.body.classList.add('leave');setTimeout(()=>location.href=d.redirect,230)},300)}
  catch(x){er.textContent=x.message;void er.offsetWidth;er.classList.add('on');b.disabled=false;b.textContent=page==='login'?'Login':'Sign Up'}}},
async dashboard(){
  const L={today:['OMZET TERVERIFIKASI HARI INI','Total Hari Ini','KUANTITAS PAX'],week:['OMZET TERVERIFIKASI 7 HARI TERAKHIR','Total 7 Hari','TOTAL TERJUAL'],month:['OMZET TERVERIFIKASI BULAN INI','Total Bulan Ini','KUANTITAS PAX']};
  async function load(p){const d=await api('api/dashboard.php?period='+p);
    $('#hLabel').textContent=L[p][0];$('#tot').textContent=L[p][1];$('#l2').textContent=L[p][2];
    countUp($('#omzet'),d.omzet);$('#tiket').textContent=short(d.tiket);$('#items').textContent=d.items.toLocaleString('id-ID')+' Item';
    $('#growth').textContent=(d.growth>=0?'↗ +':'↘ ')+d.growth+'%';$('#orders').textContent=d.orders+' Pesanan';
    const max=Math.max(...d.chart.map(c=>c.v),1),hi=p==='month'?new Date().getMonth():d.chart.length-1;
    $('#bars').innerHTML=d.chart.map((c,i)=>`<div class="bar${i===hi?' hi':''}"><i style="--n:${i}"></i>${c.l}</div>`).join('');
    requestAnimationFrame(()=>requestAnimationFrame(()=>$$('#bars .bar i').forEach((el,i)=>el.style.height=Math.max(d.chart[i].v/max*100,10)+'%')));
    const kn={makanan:'Makanan',minuman:'Minuman',snack:'Snack'};
    $('#kat').innerHTML=d.kategori.map(k=>`<div class="krow"><span>${kn[k.k]} (${short(k.h).replace('Rp ','Rp ')})</span><b>${k.n} ${k.k==='minuman'?'Botol':'Porsi'}</b></div>`).join('')||'<p class="empty">Belum ada data</p>';
    $('#top').innerHTML=d.top.map((t,i)=>`<div class="prod rise" style="--i:${i}"><span class="rk">${i+1}</span><img src="assets/img/produk/${t.gambar||'NK-01.png'}" alt=""><div><b>${t.nama}</b>${t.n} Terjual • ${rp(t.harga)}<span class="om"><span>Kontribusi Omzet</span><strong>${rp(t.om)}</strong></span></div></div>`).join('')}
  $$('#period button').forEach(b=>b.onclick=()=>{$$('#period button').forEach(x=>x.classList.toggle('on',x===b));load(b.dataset.p)});load('today')},
async orders(){let tab='validasi',t;
  async function load(){const d=await api(`api/orders.php?tab=${tab}&q=${encodeURIComponent($('#q').value)}`);
    $('#cv').textContent=d.count.v;$('#cp').textContent=d.count.p;const c=d.kapasitas||{kapasitas:1,pakai:0},pct=Math.round(c.pakai/c.kapasitas*100);
    $('#gfg').style.strokeDashoffset=100-Math.min(pct,100);countUp($('#gpct'),pct,1400);setTimeout(()=>$('#gpct').textContent=pct+'%',1500);
    $('#gtxt').textContent=`${c.pakai.toLocaleString('id-ID')} / ${c.kapasitas.toLocaleString('id-ID')} porsi · sisa ${(c.kapasitas-c.pakai).toLocaleString('id-ID')} pax aman`;
    $('#list').innerHTML=d.orders.map((o,i)=>`<a class="ord" style="--i:${i}" href="order-detail.php?id=${o.id}"><div class="h"><span>${o.kode}</span><span class="tag">${o.jam} WIB</span></div><div class="b"><span class="ic">▤</span><div><b>${o.nama_pemesan}</b>${o.keterangan||''}</div><strong>${rp(o.total)}</strong></div>${tab==='validasi'?'<span class="go">Cek Detail Pesanan</span>':''}</a>`).join('')||'<p class="empty">Tidak ada pesanan</p>'}
  $$('#tabs button').forEach(b=>b.onclick=()=>{tab=b.dataset.t;$$('#tabs button').forEach(x=>x.classList.toggle('on',x===b));load()});
  $('#q').oninput=()=>{clearTimeout(t);t=setTimeout(load,300)};load()},
async detail(){const el=$('#detail'),id=el.dataset.id,btn=$('#act');
  async function load(){const o=await api('api/orders.php?id='+id),m={menunggu_validasi:'Menunggu',diproses:'Diproses',selesai:'Selesai',ditolak:'Ditolak'};
    const dt=s=>s?new Date(s).toLocaleString('id-ID',{day:'numeric',month:'short',year:'numeric',hour:'2-digit',minute:'2-digit'}).replace('.',':')+' WIB':'-';
    el.innerHTML=`<div class="dbox" style="--i:0"><div class="row between"><div><small>Kode Pesanan</small><b style="display:block;font-size:18px">${o.kode}</b></div><span class="tag">${m[o.status]}</span></div><hr><div class="row"><span class="avatar" style="background:var(--gl);color:var(--br)">☻</span><div><small>Pemesan</small><b style="display:block">${o.nama_pemesan}</b></div></div></div>
    <div class="dbox" style="--i:1"><b>Bukti Pesanan Pelanggan</b><div class="proof">${o.bukti_bayar?`<img src="${o.bukti_bayar}" alt="Bukti">`:'Foto bukti pesanan'}</div></div>
    <div class="dbox" style="--i:2"><div class="row between"><b>Daftar Menu</b><span class="tag">${o.menu.length} Jenis</span></div>${o.menu.map(x=>`<div class="mi"><i></i><span><b>${x.nama}</b><small>${x.harga.toLocaleString('id-ID')} x ${x.qty} porsi</small></span><strong>${rp(x.sub)}</strong></div>`).join('')}</div>
    <div class="dbox" style="--i:3"><b>Jadwal</b><div class="jd"><span>Tanggal &amp; Waktu Pemesanan</span><b>${dt(o.created_at)}</b></div><div class="jd"><span>Tanggal &amp; Waktu Pengiriman</span><b>${dt(o.tgl_kirim)}</b></div></div>`;
    $$('.mi i').forEach(i=>i.onclick=()=>i.classList.toggle('ck'));
    const a={menunggu_validasi:['validate','✓ Validasi Pesanan'],diproses:['complete','✓ Selesai dan Kirim']}[o.status];btn.hidden=!a;if(a){btn.dataset.a=a[0];btn.textContent=a[1]}}
  btn.onclick=async()=>{btn.disabled=true;try{await api('api/orders.php',{method:'POST',body:{id:+id,action:btn.dataset.a}});toast('Status pesanan diperbarui');await load()}catch(e){toast(e.message)}btn.disabled=false};load()},
async products(){let k='',t;
  async function load(){const d=await api(`api/products.php?kategori=${k}&q=${encodeURIComponent($('#q').value)}`);$('#cs').textContent=d.count.semua;$('#ck').textContent=d.count.kritis;
    $('#plist').innerHTML=d.items.map((p,i)=>{const pct=p.sisa/p.kuota*100;return`<div class="pr" style="--i:${i}"><span class="kode">${p.kode}</span><div><b>${p.nama}</b><small>${rp(p.harga)} · ${p.sisa}/${p.kuota} porsi${p.sisa<=0?' · habis':''}</small><div class="meter${pct<=20?' low':''}"><i data-w="${pct}"></i></div></div></div>`}).join('')||'<p class="empty">Produk tidak ditemukan</p>';
    requestAnimationFrame(()=>requestAnimationFrame(()=>$$('.meter i').forEach(i=>i.style.width=i.dataset.w+'%')))}
  $$('#chips button').forEach(b=>b.onclick=()=>{k=b.dataset.k;$$('#chips button').forEach(x=>x.classList.toggle('on',x===b));load()});
  $('#q').oninput=()=>{clearTimeout(t);t=setTimeout(load,300)};load()}};
(page==='login'||page==='register'?PAGES.form:PAGES[page]||(()=>{}))();
