<?php
require __DIR__ . '/partials/layout.php';
head('Dashboard', 'dashboard');
topbar('Dashboard');
?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/id.js"></script>

<section class="rise">
    <h2 class="greet">Selamat Pagi, <span id="nama"><?= ucfirst(me()['username']) ?></span></h2>
    <p class="sub">Pantauan performa operasional &amp; kapasitas dapur Se.Noesa.</p>
</section>

<section class="hero-card rise">
    <div class="row between">
        <small id="hLabel">OMZET TERVERIFIKASI (PERIODE INI)</small>
        <span class="chip-d" id="growth">0%</span>
    </div>
    <div class="big">Rp <b id="omzet" data-count="0">0</b></div>
    <p class="ok">✓ Pesanan Lunas</p>
    <div class="stats">
        <div><small>TIKET RATA-RATA</small><b id="tiket">-</b></div>
        <div><small id="l2">KUANTITAS PAX</small><b id="items">-</b></div>
        <div><small>STATUS LUNAS</small><b>100% Lunas</b></div>
    </div>
</section>

<section class="card rise">
    <div class="row between dashboard-header" style="align-items: center; margin-bottom: 10px;">
        <h3>Tren Pesanan</h3>
        <div class="date-filter-pills">
            <button type="button" class="date-pill-btn" id="btn-start">
                <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
                <span id="lbl-start">-</span>
            </button>
            <span style="font-size: 12px; color: var(--muted);">s/d</span>
            <button type="button" class="date-pill-btn" id="btn-end">
                <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
                <span id="lbl-end">-</span>
            </button>
        </div>
    </div>

    <div class="chart-wrap" id="chart-wrap">
        <div class="chart-tip" id="chart-tip"></div>
        <div class="chart-container" id="bars"></div>
    </div>
</section>

<section class="card rise">
    <div class="row between">
        <h3>Pesanan Masuk</h3>
    </div>
    <div class="num" id="orders">0 Pesanan</div>
    <div class="row between meta"><span id="tot">Total Periode</span><b>100% Lunas</b></div>
</section>

<section class="card rise">
    <div class="row between">
        <h3>Rincian Kategori</h3>
    </div>
    <div id="kat"></div>
</section>

<h3 class="sec rise">Produk Terlaris</h3>
<div id="top"></div>

<!-- MODAL KALENDER -->
<dialog id="date-modal" class="custom-date-dialog">
    <div class="date-modal-content">
        <div class="date-modal-header">
            <div>
                <p class="eyebrow">PILIH TANGGAL</p>
                <h2 id="modal-title">Tanggal kuota dapur dan produk</h2>
            </div>
            <button type="button" class="modal-close-btn" id="close-date-modal">
                <svg viewBox="0 0 24 24"><path d="M18 6L6 18M6 6l12 12"/></svg>
            </button>
        </div>

        <div id="calendar-mount"></div>

        <div class="date-modal-footer">
            <span class="today-indicator"><i></i> Hari ini</span>
            <button type="button" class="btn-today" id="btn-today">Ke hari ini</button>
        </div>
    </div>
</dialog>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const dateModal     = document.getElementById('date-modal');
    const btnStart      = document.getElementById('btn-start');
    const btnEnd        = document.getElementById('btn-end');
    const lblStart      = document.getElementById('lbl-start');
    const lblEnd        = document.getElementById('lbl-end');
    const modalTitle    = document.getElementById('modal-title');
    const closeBtn      = document.getElementById('close-date-modal');
    const btnToday      = document.getElementById('btn-today');
    const calendarMount = document.getElementById('calendar-mount');

    const now = new Date();
    let startDate = new Date(now.getFullYear(), now.getMonth(), 1);
    let endDate   = new Date(now);
    let activeTarget = 'start';
    let picker = null;
    let ignoreChange = false;

    const pad = n => String(n).padStart(2, '0');
    const fmtShort = d => d.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
    const ymd = d => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;

    function updateLabels() {
        lblStart.textContent = fmtShort(startDate);
        lblEnd.textContent   = fmtShort(endDate);
    }

    function initPicker() {
        if (picker) return picker;
        picker = flatpickr(calendarMount, {
            locale: "id",
            inline: true,
            disableMobile: true,
            monthSelectorType: 'static',
            dateFormat: 'Y-m-d',
            onChange: function(selectedDates) {
                if (ignoreChange || !selectedDates[0]) return;
                const d = selectedDates[0];
                if (activeTarget === 'start') {
                    startDate = d;
                    if (endDate < startDate) endDate = startDate;
                } else {
                    endDate = d;
                }
                updateLabels();
                dateModal.close();
                fetchDashboardData();
            }
        });
        return picker;
    }

    function openModal(target) {
        activeTarget = target;
        modalTitle.textContent = target === 'start' ? 'Pilih Tanggal Mulai' : 'Pilih Tanggal Selesai';
        dateModal.showModal();
        const p = initPicker();
        ignoreChange = true;
        p.set('maxDate', 'today');
        if (target === 'start') {
            p.set('minDate', null);
            p.setDate(startDate, false);
        } else {
            p.set('minDate', startDate);
            p.setDate(endDate, false);
        }
        ignoreChange = false;
    }

    btnStart.addEventListener('click', () => openModal('start'));
    btnEnd.addEventListener('click', () => openModal('end'));
    closeBtn.addEventListener('click', () => dateModal.close());
    dateModal.addEventListener('click', (e) => {
        if (e.target === dateModal) dateModal.close();
    });

    btnToday.addEventListener('click', () => {
        const today = new Date();
        ignoreChange = true;
        picker.setDate(today, false);
        ignoreChange = false;
        if (activeTarget === 'start') {
            startDate = today;
            if (endDate < startDate) endDate = startDate;
        } else {
            endDate = today;
        }
        updateLabels();
        dateModal.close();
        fetchDashboardData();
    });

    const chartWrap = document.getElementById('chart-wrap');
    const chartTip  = document.getElementById('chart-tip');
    let activeCol = null;

    function hideTip() {
        chartTip.classList.remove('show');
        if (activeCol) activeCol.classList.remove('active');
        activeCol = null;
    }

    function showTip(col) {
        if (activeCol) activeCol.classList.remove('active');
        activeCol = col;
        col.classList.add('active');

        chartTip.innerHTML = `<small>${col.dataset.label}</small>Rp ${Number(col.dataset.val).toLocaleString('id-ID')}`;
        const bar = col.querySelector('.chart-bar').getBoundingClientRect();
        const wrap = chartWrap.getBoundingClientRect();
        const tipW = chartTip.offsetWidth, tipH = chartTip.offsetHeight;

        const center = bar.left - wrap.left + bar.width / 2;
        const left = Math.min(Math.max(center, tipW / 2 + 2), wrap.width - tipW / 2 - 2);
        chartTip.style.setProperty('--arrow-x', (center - left + tipW / 2) + 'px');
        chartTip.style.left = (left - tipW / 2) + 'px';
        chartTip.style.top  = Math.max(0, bar.top - wrap.top - tipH - 8) + 'px';
        chartTip.classList.add('show');
    }

    function bindChartTooltip() {
        hideTip();
        document.querySelectorAll('#bars .chart-col').forEach(col => {
            col.addEventListener('mouseenter', () => showTip(col));
            col.addEventListener('mouseleave', hideTip);
            col.addEventListener('click', (e) => { e.stopPropagation(); showTip(col); });
        });
    }
    document.addEventListener('click', hideTip);

    async function fetchDashboardData() {
        const start = ymd(startDate);
        const end   = ymd(endDate);
        const barsEl = document.getElementById('bars');
        barsEl.innerHTML = '<div style="width:100%;text-align:center;color:var(--muted);font-size:12px;padding:20px;">Memuat data...</div>';

        try {
            const response = await fetch(`api/dashboard.php?start_date=${start}&end_date=${end}`);
            if (!response.ok) throw new Error('HTTP ' + response.status);
            const data = await response.json();

            document.getElementById('omzet').textContent  = Number(data.omzet).toLocaleString('id-ID');
            document.getElementById('tiket').textContent  = 'Rp ' + Number(data.tiket).toLocaleString('id-ID');
            document.getElementById('items').textContent  = Number(data.items).toLocaleString('id-ID') + ' Item';
            document.getElementById('orders').textContent = Number(data.orders).toLocaleString('id-ID') + ' Pesanan';

            const growthEl  = document.getElementById('growth');
            const growthVal = Number(data.growth);
            growthEl.textContent = (growthVal > 0 ? '↑ +' : growthVal < 0 ? '↓ ' : '') + growthVal + '%';
            growthEl.style.background = growthVal < 0 ? '#f8e4e1' : (growthVal > 0 ? '#e4efe5' : '#f0f0f0');
            growthEl.style.color      = growthVal < 0 ? '#a5463b' : (growthVal > 0 ? '#3b513e' : '#687067');

            if (!data.chart || data.chart.length === 0) {
                barsEl.innerHTML = '<div style="width:100%;text-align:center;color:var(--muted);font-size:12px;padding:20px;">Tidak ada data pada rentang ini.</div>';
            } else {
                const maxVal = Math.max(...data.chart.map(x => Number(x.v)), 1);
                const lastIdx = data.chart.length - 1;
                barsEl.innerHTML = data.chart.map((item, idx) => {
                    const val    = Number(item.v);
                    const isZero = val === 0;
                    const isLast = idx === lastIdx;
                    // Nilai 0 tetap jadi pil kecil (10px) kecuali item terakhir yang kosong/0 seperti pada gambar kiri
                    const height = (isZero && isLast) ? '0px' : (isZero ? '10px' : `max(18px, ${(val / maxVal) * 100}%)`);

                    return `
                        <div class="chart-col ${isLast ? 'is-last' : ''}" data-val="${val}" data-label="${item.l}">
                            <div class="bar-wrapper">
                                <div class="chart-bar ${isZero ? 'zero' : ''}" style="height:${height};"></div>
                            </div>
                            <div class="chart-label">${item.l}</div>
                        </div>`;
                }).join('');
                bindChartTooltip();
            }

            const kat = document.getElementById('kat');
            if (!data.kategori || data.kategori.length === 0) {
                kat.innerHTML = '<div style="font-size:12px;color:var(--muted);padding:10px 0;">Belum ada data.</div>';
            } else {
                kat.innerHTML = data.kategori.map(k => `
                    <div class="row between" style="padding:12px 0; border-bottom:1px solid var(--line); font-size:13px;">
                        <span style="text-transform:capitalize; display:flex; align-items:center; gap:6px;">
                            ${k.k}
                            <small style="color:var(--muted); font-size:10px; background:var(--soft); padding:2px 6px; border-radius:4px;">Rp ${Number(k.h).toLocaleString('id-ID')}/porsi</small>
                        </span>
                        <b>${Number(k.n).toLocaleString('id-ID')} Porsi</b>
                    </div>`).join('');
            }

            const top = document.getElementById('top');
            if (!data.top || data.top.length === 0) {
                top.innerHTML = '<div style="font-size:12px;color:var(--muted);padding:10px 0;">Belum ada data.</div>';
            } else {
                top.innerHTML = data.top.map((t, i) => `
                    <div class="card" style="display:flex; align-items:center; gap:14px; padding:14px; margin-bottom:10px;">
                        <div style="width:24px; height:24px; border-radius:50%; background:var(--soft); color:var(--green-dark); display:grid; place-items:center; font-weight:700; font-size:12px;">${i+1}</div>
                        <img src="../${t.gambar || 'assets/img/produk/NK-01.png'}" alt="${t.nama}" style="width:46px; height:46px; border-radius:10px; object-fit:cover;">
                        <div style="flex:1;">
                            <div style="font-weight:600; font-size:14px; color:var(--br);">${t.nama}</div>
                            <div style="font-size:11px; color:var(--muted); margin-top:2px;">${Number(t.n).toLocaleString('id-ID')} Terjual &middot; Rp ${Number(t.harga).toLocaleString('id-ID')}</div>
                        </div>
                        <div style="font-weight:700; font-size:14px; color:var(--green-dark);">Rp ${Number(t.om).toLocaleString('id-ID')}</div>
                    </div>`).join('');
            }
        } catch (error) {
            console.error('Gagal memuat data:', error);
            barsEl.innerHTML = '<div style="width:100%;text-align:center;color:#a5463b;font-size:12px;padding:20px;">Gagal memuat data.</div>';
        }
    }

    updateLabels();
    fetchDashboardData();
});
</script>

<?php
nav('dashboard');
foot();
?>