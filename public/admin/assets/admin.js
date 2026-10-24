const $ = (s, r = document) => r.querySelector(s),
  $$ = (s, r = document) => [...r.querySelectorAll(s)];
const money = (n) => "Rp " + Number(n || 0).toLocaleString("id-ID");
const esc = (s) =>
  String(s ?? "").replace(
    /[&<>"']/g,
    (c) =>
      ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" })[
        c
      ],
  );
async function api(url, options = {}) {
  const response = await fetch(url, {
    cache: "no-store",
    headers: { "Content-Type": "application/json" },
    ...options,
    body: options.body ? JSON.stringify(options.body) : undefined,
  });
  const data = await response
    .json()
    .catch(() => ({ error: "Respons server tidak valid" }));
  if (response.status === 401) {
    location.href = "../login.php";
    throw Error("Sesi berakhir");
  }
  if (!response.ok) throw Error(data.error || "Terjadi kesalahan");
  return data;
}
function toast(message) {
  const el = $("#toast");
  if (!el) return;
  el.textContent = message;
  el.classList.add("show");
  setTimeout(() => el.classList.remove("show"), 2600);
}
function badge(status) {
  const labels = {
    menunggu_validasi: "Menunggu validasi",
    diproses: "Sedang diproses",
    selesai: "Selesai",
    ditolak: "Ditolak",
  };
  return (
    '<span class="status-badge ' +
    esc(status) +
    '">' +
    (labels[status] || esc(status)) +
    "</span>"
  );
}
function dateLabel(value, withTime = false) {
  if (!value) return "-";
  const date = new Date(value);
  return date.toLocaleString(
    "id-ID",
    withTime
      ? { day: "2-digit", month: "short", hour: "2-digit", minute: "2-digit" }
      : { day: "2-digit", month: "short", year: "numeric" },
  );
}
function countUp(el, target) {
  const start = performance.now(),
    duration = 750;
  function frame(now) {
    const p = Math.min((now - start) / duration, 1),
      ease = 1 - Math.pow(1 - p, 3);
    el.textContent = Math.round(target * ease).toLocaleString("id-ID");
    if (p < 1) requestAnimationFrame(frame);
  }
  requestAnimationFrame(frame);
}
function shell() {
  const toggle = $(".menu-toggle");
  toggle?.addEventListener("click", () =>
    $(".sidebar")?.classList.toggle("open"),
  );
  $$("[data-logout]").forEach((button) =>
    button.addEventListener("click", async () => {
      button.disabled = true;
      try {
        await api("../api/auth.php?a=logout");
        location.href = "../login.php";
      } catch (e) {
        button.disabled = false;
        toast(e.message);
      }
    }),
  );
  const label = $("#today-label");
  if (label)
    label.textContent = new Date().toLocaleDateString("id-ID", {
      weekday: "long",
      day: "numeric",
      month: "long",
      year: "numeric",
    });
}
async function dashboard() {
  const d = await api("api/dashboard.php");
  for (const [name, value] of Object.entries({
    orders: d.orders,
    pending: d.pending,
    critical: d.critical,
  })) {
    const el = $('[data-stat="' + name + '"]');
    if (el) countUp(el, value);
  }
  const ratio = d.capacity ? Math.round((d.used / d.capacity) * 100) : 0;
  $('[data-stat="capacity"]').textContent = ratio + "%";
  $('[data-stat="capacity-hint"]').textContent =
    d.used.toLocaleString("id-ID") +
    " / " +
    d.capacity.toLocaleString("id-ID") +
    " porsi";
  $("[data-meter]").style.width = Math.min(ratio, 100) + "%";
  const pending = $("#pending-badge");
  if (pending) pending.textContent = d.pending || "";
  const max = Math.max(1, ...d.chart.map((x) => Number(x.jumlah)));
  $("#orders-chart").innerHTML = d.chart
    .map(
      (x, i) =>
        '<div class="chart-column"><span class="chart-count">' +
        esc(x.jumlah) +
        '</span><i style="--bar:' +
        Math.max(5, (Number(x.jumlah) / max) * 100) +
        "%;--delay:" +
        i * 55 +
        'ms"></i><small>' +
        esc(x.label) +
        "</small></div>",
    )
    .join("");
  const priority = $("#priority-list");
  priority.innerHTML = d.priority.length
    ? d.priority
        .map(
          (o) =>
            '<a class="priority-item" href="order-detail.php?id=' +
            Number(o.id) +
            '"><div class="priority-main"><b>' +
            esc(o.kode) +
            " · " +
            esc(o.nama_pemesan) +
            '</b><span class="tag warning">Menunggu validasi</span></div><p>' +
            esc(o.keterangan || o.pax + " pax") +
            " · Kirim " +
            dateLabel(o.tgl_kirim) +
            "</p><strong>" +
            money(o.total) +
            "</strong></a>",
        )
        .join("")
    : '<div class="empty-state">Tidak ada pesanan yang menunggu validasi.</div>';
  $("#activity-list").innerHTML = d.recent.length
    ? d.recent
        .map(
          (o) =>
            '<a class="activity-row" href="order-detail.php?id=' +
            Number(o.id) +
            '"><span><b>' +
            esc(o.kode) +
            "</b><small>" +
            esc(o.nama_pemesan) +
            " · " +
            dateLabel(o.created_at, true) +
            "</small></span>" +
            badge(o.status) +
            "<strong>" +
            money(o.total) +
            "</strong></a>",
        )
        .join("")
    : '<div class="empty-state">Belum ada aktivitas pesanan.</div>';
}
let orderTab = "validasi",
  orderTimer;
async function loadOrders() {
  const query = encodeURIComponent($("#order-search")?.value || "");
  const d = await api("api/orders.php?tab=" + orderTab + "&q=" + query);
  $("#pending-count").textContent = d.counts.pending;
  $("#processing-count").textContent = d.counts.processing;
  document.querySelector("#completed-count").textContent = d.counts.completed;
  $("#orders-capacity").textContent =
    (Number(d.capacity.pakai) || 0).toLocaleString("id-ID") +
    " / " +
    (Number(d.capacity.kapasitas) || 0).toLocaleString("id-ID") +
    " porsi";
  const rows = d.items
    .map(
      (o) =>
        "<tr><td><b>" +
        esc(o.kode) +
        "</b><small>" +
        dateLabel(o.created_at, true) +
        "</small></td><td><b>" +
        esc(o.nama_pemesan) +
        "</b><small>" +
        esc(o.keterangan || "") +
        "</small></td><td>" +
        Number(o.pax || 0).toLocaleString("id-ID") +
        " pax</td><td><b>" +
        money(o.total) +
        "</b></td><td>" +
        badge(o.status) +
        '</td><td><a class="button small secondary" href="order-detail.php?id=' +
        Number(o.id) +
        '">Lihat detail</a></td></tr>',
    )
    .join("");
  $("#order-rows").innerHTML =
    rows ||
    '<tr><td colspan="6" class="empty-state">Tidak ada pesanan untuk filter ini.</td></tr>';
  $("#mobile-orders").innerHTML =
    d.items
      .map(
        (o) =>
          '<a class="mobile-order" href="order-detail.php?id=' +
          Number(o.id) +
          '"><span class="row-between"><b>' +
          esc(o.kode) +
          "</b>" +
          badge(o.status) +
          "</span><b>" +
          esc(o.nama_pemesan) +
          "</b><small>" +
          esc(o.keterangan || "") +
          " · " +
          dateLabel(o.created_at, true) +
          '</small><span class="row-between"><small>' +
          Number(o.pax || 0) +
          " pax</small><strong>" +
          money(o.total) +
          "</strong></span></a>",
      )
      .join("") || '<div class="empty-state">Tidak ada pesanan.</div>';
}
function ordersPage() {
  $$("#order-tabs button").forEach(
    (b) =>
      (b.onclick = () => {
        $$("#order-tabs button").forEach((x) =>
          x.classList.toggle("active", x === b),
        );
        orderTab = b.dataset.tab;
        loadOrders();
      }),
  );
  $("#order-search").oninput = () => {
    clearTimeout(orderTimer);
    orderTimer = setTimeout(loadOrders, 220);
  };
  loadOrders();
}
let productCategory = "",
  productTimer;
function productImagePath(value) {
  const raw = String(value || "");
  if (/^uploads\/products\/[a-f0-9]+\.(jpg|png|webp)$/i.test(raw))
    return "../" + raw;
  const file = raw.replace(/[^a-zA-Z0-9_.-]/g, "");
  return file
    ? "../assets/img/produk/" + file
    : "../assets/img/produk/NK-01.png";
}
async function loadProducts() {
  const q = encodeURIComponent($("#product-search")?.value || "");
  const d = await api(
    "api/products.php?kategori=" +
      encodeURIComponent(productCategory) +
      "&q=" +
      q,
  );
  $("#all-count").textContent = d.count.semua;
  $("#critical-count").textContent = d.count.kritis;
  const grid = $("#product-grid");
  grid.innerHTML = d.items.length
    ? d.items
        .map((p) => {
          const percent = p.kuota
            ? Math.round((p.terpakai / p.kuota) * 100)
            : 0;
          return (
            '<article class="product-card"><img class="product-card-image" src="' +
            esc(productImagePath(p.gambar)) +
            '" alt="" onerror="this.src=\'../assets/img/produk/NK-01.png\'"><div class="product-info"><div class="row-between"><span class="category-label">' +
            esc(p.kategori) +
            "</span>" +
            (Number(p.sisa) <= Number(p.kuota) * 0.2
              ? '<span class="tag warning">Kritis</span>'
              : "") +
            "</div><h3>" +
            esc(p.nama) +
            "</h3><p>" +
            money(p.harga) +
            " · " +
            Number(p.sisa).toLocaleString("id-ID") +
            "/" +
            Number(p.kuota).toLocaleString("id-ID") +
            ' tersisa</p><div class="stock-meter"><i style="width:' +
            Math.max(0, Math.min(percent, 100)) +
            '%"></i></div><div class="product-actions"><button class="button small secondary" data-edit="' +
            Number(p.id) +
            '">Edit</button><button class="button small danger-outline" data-delete="' +
            Number(p.id) +
            '">Hapus</button></div></div></article>'
          );
        })
        .join("")
    : '<div class="empty-state">Produk tidak ditemukan.</div>';
  $$("[data-edit]").forEach(
    (b) =>
      (b.onclick = () =>
        editProduct(
          d.items.find((p) => Number(p.id) === Number(b.dataset.edit)),
        )),
  );
  $$("[data-delete]").forEach(
    (b) =>
      (b.onclick = async () => {
        if (!confirm("Hapus produk ini?")) return;
        try {
          await api("api/products.php", {
            method: "POST",
            body: { action: "delete", id: Number(b.dataset.delete) },
          });
          toast("Produk dihapus");
          loadProducts();
        } catch (e) {
          toast(e.message);
        }
      }),
  );
  return d.items;
}
function refreshProducts() {
  loadProducts().catch((e) => {
    const grid = $("#product-grid");
    if (grid)
      grid.innerHTML =
        '<div class="empty-state">Gagal memuat produk: ' +
        esc(e.message) +
        "</div>";
    toast(e.message);
  });
}
function editProduct(p) {
  const form = $("#product-form"),
    preview = $("#product-image-preview");
  form.reset();
  form.elements.id.value = p?.id || "";
  form.elements.kode.value = p?.kode || "";
  form.elements.nama.value = p?.nama || "";
  form.elements.kategori.value = p?.kategori || "makanan";
  form.elements.harga.value = p?.harga || "";
  form.elements.kuota.value = p ? p.kuota : 1;
  form.elements.terpakai.value = p ? p.terpakai : 0;
  form.elements.min_pesan.value = p?.min_pesan || 1;
  form.elements.gambar.value = p?.gambar || "";
  form.elements.gambar_file.required = !p;
  $("#image-help").textContent = p
    ? "Kosongkan jika gambar tidak ingin diganti. JPG, PNG, atau WebP · maksimal 2 MB"
    : "Wajib untuk produk baru. JPG, PNG, atau WebP · maksimal 2 MB. Stok tersedia akan tampil ke pelanggan.";
  preview.hidden = !p?.gambar;
  preview.src = p?.gambar ? productImagePath(p.gambar) : "";
  $("#product-form-title").textContent = p ? "Edit produk" : "Tambah produk";
  $("#product-error").textContent = "";
  $("#product-dialog").showModal();
}
function productsPage() {
  $$("#product-filters button").forEach(
    (b) =>
      (b.onclick = () => {
        $$("#product-filters button").forEach((x) =>
          x.classList.toggle("active", x === b),
        );
        productCategory = b.dataset.category;
        refreshProducts();
      }),
  );
  $("#product-search").oninput = () => {
    clearTimeout(productTimer);
    productTimer = setTimeout(refreshProducts, 220);
  };
  $("#new-product").onclick = () => editProduct(null);
  $$("[data-close-dialog]").forEach(
    (b) => (b.onclick = () => $("#product-dialog").close()),
  );
  $("#product-form").elements.gambar_file.onchange = (e) => {
    const file = e.target.files[0],
      preview = $("#product-image-preview");
    if (file) {
      preview.src = URL.createObjectURL(file);
      preview.hidden = false;
    }
  };
  $("#product-form").onsubmit = async (e) => {
    e.preventDefault();
    const f = e.currentTarget,
      body = new FormData(f);
    body.set("action", "save");
    try {
      const response = await fetch("api/products.php", {
          method: "POST",
          body,
        }),
        data = await response
          .json()
          .catch(() => ({ error: "Respons server tidak valid" }));
      if (response.status === 401) {
        location.href = "../login.php";
        return;
      }
      if (!response.ok) throw Error(data.error || "Terjadi kesalahan");
      const savedCode = f.elements.kode.value.trim().toUpperCase();
      productCategory = "";
      $("#product-search").value = "";
      $$("#product-filters button").forEach((x) =>
        x.classList.toggle("active", x.dataset.category === ""),
      );
      const loadedProducts = await loadProducts();
      if (
        !loadedProducts.some(
          (p) => String(p.kode).trim().toUpperCase() === savedCode,
        )
      )
        throw Error(
          "API menjawab " +
            JSON.stringify(data) +
            " tetapi produk belum muncul pada daftar.",
        );
      $("#product-dialog").close();
      toast("Produk dan gambar tersimpan. Daftar admin diperbarui.");
    } catch (err) {
      $("#product-error").textContent = err.message;
    }
  };
  refreshProducts();
}
async function orderDetail() {
  const id = Number(new URLSearchParams(location.search).get("id"));
  if (!id) {
    $("#order-detail").innerHTML =
      '<article class="panel empty-state">Kode pesanan tidak valid.</article>';
    return;
  }
  const o = await api("api/orders.php?id=" + id);
  $("#detail-status").outerHTML = badge(o.status);
  const raw = o.bukti_bayar || "",
    proofUrl = raw
      ? /^https?:\/\//.test(raw) || raw.startsWith("/")
        ? raw
        : "../" + raw.replace(/^\.\//, "")
      : "";
  const delivery = o.tgl_kirim ? dateLabel(o.tgl_kirim, true) : "-";
  const proof = raw
    ? '<a class="proof-image" href="' +
      esc(proofUrl) +
      '" target="_blank" rel="noopener"><img src="' +
      esc(proofUrl) +
      '" alt="Bukti transfer pelanggan"></a>'
    : '<div class="proof-empty"><span>?</span><b>Belum ada bukti pembayaran</b></div>';
  const receipt =
    '<article class="panel"><div class="panel-heading"><h2>Data pada pembayaran</h2><span class="tag soft">QRIS</span></div><div class="detail-pair"><span>Nama pengirim</span><b>' +
    esc(o.nama_pembayar || "-") +
    '</b></div><div class="detail-pair"><span>Nama pemesan</span><b>' +
    esc(o.nama_pemesan || "-") +
    '</b></div><div class="detail-pair"><span>Nominal tagihan</span><b>' +
    money(o.total) +
    '</b></div><div class="detail-pair"><span>Total pesanan</span><b>' +
    money(o.total) +
    '</b></div><div class="detail-pair"><span>Jadwal pengiriman</span><b>' +
    delivery +
    "</b></div></article>";
  const menu = o.menu
    .map(
      (m) =>
        '<label class="dispatch-item"><input type="checkbox" data-item-check="' +
        Number(m.item_id) +
        '" ' +
        (m.sudah_dicek ? "checked" : "") +
        " " +
        (o.status === "diproses" ? "" : "disabled") +
        "><span><b>" +
        esc(m.nama) +
        "</b><small>" +
        Number(m.qty).toLocaleString("id-ID") +
        " porsi ? " +
        money(m.harga) +
        "</small></span><strong>" +
        money(m.subtotal) +
        "</strong></label>",
    )
    .join("");
  const sentPhoto = o.foto_kiriman
    ? '<img class="dispatch-photo" src="../' +
      esc(o.foto_kiriman) +
      '" alt="Foto makanan yang akan dikirim">'
    : "";
  const dispatch =
    o.status === "diproses"
      ? '<article class="panel"><div class="panel-heading"><h2>Bukti pesanan pelanggan</h2><span class="tag soft">Foto kiriman</span></div>' +
        sentPhoto +
        '<form id="dispatch-photo-form" class="dispatch-upload"><label for="dispatch-photo">' +
        (o.foto_kiriman
          ? "Ganti foto makanan"
          : "Unggah foto makanan yang akan dikirim") +
        '</label><input id="dispatch-photo" name="foto_kiriman" type="file" accept="image/jpeg,image/png,image/webp" required><small>JPG, PNG, atau WebP ? Maksimal 2 MB</small><button class="button secondary">Simpan foto</button><span id="dispatch-photo-error" class="form-error"></span></form></article>'
      : "";
  const checklist =
    '<article class="panel"><div class="panel-heading"><h2>Daftar menu</h2><span class="tag soft">' +
    o.menu.length +
    ' jenis</span></div><p class="muted">Centang setiap menu setelah jumlah dan jenis makanan dipastikan sesuai.</p>' +
    menu +
    "</article>";
  const isValidation = o.status === "menunggu_validasi";
  const validationView =
    '<article class="panel order-summary"><div class="panel-heading"><div><p class="eyebrow">VALIDASI QRIS</p><h2>' +
    esc(o.kode) +
    '</h2></div><span class="tag soft">' +
    dateLabel(o.created_at, true) +
    '</span></div><div class="detail-pair"><span>Nama pemesan</span><b>' +
    esc(o.nama_pemesan) +
    '</b></div><div class="detail-pair"><span>Catatan pesanan</span><b>' +
    esc(o.keterangan || "-") +
    '</b></div><div class="detail-pair total-row"><span>Total tagihan</span><b>' +
    money(o.total) +
    '</b></div></article><article class="panel proof-panel"><div class="panel-heading"><h2>Bukti transfer pelanggan</h2><span class="tag soft">QRIS</span></div>' +
    proof +
    "</article>" +
    receipt;
  const dispatchView =
    '<article class="panel order-summary"><div class="panel-heading"><div><p class="eyebrow">DETAIL PESANAN</p><h2>' +
    esc(o.kode) +
    "</h2></div>" +
    badge(o.status) +
    '</div><div class="detail-pair"><span>Nama pemesan</span><b>' +
    esc(o.nama_pemesan) +
    '</b></div><div class="detail-pair"><span>Kontak</span><b>' +
    esc(o.phone || "-") +
    '</b></div><div class="detail-pair"><span>Jadwal pengiriman</span><b>' +
    delivery +
    '</b></div><div class="detail-pair total-row"><span>Total pesanan</span><b>' +
    money(o.total) +
    "</b></div></article>" +
    checklist +
    dispatch;
  $("#order-detail").innerHTML = isValidation ? validationView : dispatchView;
  const actions = $("#detail-actions");
  actions.hidden = false;
  $$("[data-order-action]").forEach((btn) => {
    const action = btn.dataset.orderAction;
    btn.hidden = !(
      (o.status === "menunggu_validasi" &&
        (action === "validate" || action === "reject")) ||
      (o.status === "diproses" && action === "complete")
    );
    btn.onclick = async () => {
      if (
        action === "reject" &&
        !confirm("Tolak pembayaran untuk pesanan ini?")
      )
        return;
      btn.disabled = true;
      try {
        await api("api/orders.php", { method: "POST", body: { id, action } });
        toast("Status pesanan diperbarui");
        setTimeout(() => location.reload(), 500);
      } catch (e) {
        toast(e.message);
        btn.disabled = false;
      }
    };
  });
  $$("[data-item-check]").forEach(
    (box) =>
      (box.onchange = async () => {
        box.disabled = true;
        try {
          await api("api/orders.php", {
            method: "POST",
            body: {
              action: "check_item",
              id,
              item_id: Number(box.dataset.itemCheck),
              checked: box.checked,
            },
          });
        } catch (e) {
          box.checked = !box.checked;
          toast(e.message);
        } finally {
          box.disabled = false;
        }
      }),
  );
  const upload = $("#dispatch-photo-form");
  upload?.addEventListener("submit", async (e) => {
    e.preventDefault();
    const button = $("button", upload),
      form = new FormData(upload);
    form.append("action", "upload_dispatch");
    form.append("id", String(id));
    button.disabled = true;
    try {
      const response = await fetch("api/orders.php", {
          method: "POST",
          body: form,
          cache: "no-store",
        }),
        data = await response.json();
      if (!response.ok)
        throw Error(data.error || "Foto kiriman gagal disimpan");
      toast("Foto makanan tersimpan");
      setTimeout(() => location.reload(), 400);
    } catch (error) {
      $("#dispatch-photo-error").textContent = error.message;
      button.disabled = false;
    }
  });
}
shell();
const page = document.body.dataset.page;
const run = {
  dashboard,
  orders: ordersPage,
  "order-detail": orderDetail,
  products: productsPage,
}[page];
if (run)
  run().catch((e) => {
    toast(e.message);
    if (page === "order-detail") {
      const detail = $("#order-detail");
      if (detail)
        detail.innerHTML =
          '<article class="panel empty-state">Gagal memuat detail pesanan: ' +
          esc(e.message) +
          '<br><a class="button small secondary" href="orders.php">Kembali ke daftar pesanan</a></article>';
    }
  });
