// Uji kontrak UI notifikasi perubahan menu tanpa dependensi browser tambahan.
const assert = require("node:assert/strict");
const fs = require("node:fs");
const vm = require("node:vm");

const flush = async (times = 12) => {
  for (let index = 0; index < times; index += 1) {
    await new Promise((resolve) => setImmediate(resolve));
  }
};

const element = (extra = {}) => ({
  innerHTML: "",
  textContent: "",
  hidden: false,
  html: "",
  insertAdjacentHTML(_position, markup) {
    this.html += markup;
  },
  addEventListener() {},
  querySelector() {
    return null;
  },
  querySelectorAll() {
    return [];
  },
  ...extra,
});

async function testAdminAndOwner() {
  for (const mode of ["admin", "owner"]) {
    const listEl = element({
      listeners: {},
      addEventListener(type, handler) {
        this.listeners[type] = handler;
      },
    });
    const detailBody = element();
    const detailDialog = {
      open: false,
      showModal() {
        this.open = true;
      },
      close() {
        this.open = false;
      },
      querySelector(selector) {
        return selector === "[data-mn-detail-body]" ? detailBody : null;
      },
      querySelectorAll() {
        return [];
      },
      addEventListener() {},
    };
    const root = {
      dataset: {
        mode,
        api: "api/menu-notifications.php",
        products: "api/products.php",
        today: "2026-11-16",
      },
    };
    const document = {
      getElementById(id) {
        if (id === "mn-page") return root;
        if (id === "mn-list") return listEl;
        if (id === "mn-detail-dialog") return detailDialog;
        return null;
      },
      querySelectorAll() {
        return [];
      },
    };
    const recipients = [
      {
        id: 21,
        status_respons: "menunggu_respons",
        status_penyelesaian_selisih: "belum_dihitung",
        opsi: null,
        menu_lama: { nama: "Menu Lama", qty: 3, harga: 12000, total: 36000 },
        menu_pengganti: null,
        selisih_total: null,
        total_pesanan_lama: null,
        total_pesanan_baru: null,
        catatan_respons: null,
        responded_at: null,
        catatan_tindak_lanjut: null,
        pesanan: {
          id: 31,
          kode: "SN-OFF-1",
          status: "diproses",
          tanggal_kirim: "2026-11-20",
        },
        pelanggan: { nama: "Pelanggan Offline", kanal: "offline" },
      },
      {
        id: 22,
        status_respons: "menunggu_respons",
        status_penyelesaian_selisih: "belum_dihitung",
        opsi: null,
        menu_lama: { nama: "Menu Lama", qty: 4, harga: 12000, total: 48000 },
        menu_pengganti: null,
        selisih_total: null,
        total_pesanan_lama: null,
        total_pesanan_baru: null,
        catatan_respons: null,
        responded_at: null,
        catatan_tindak_lanjut: null,
        pesanan: {
          id: 32,
          kode: "SN-ON-1",
          status: "diproses",
          tanggal_kirim: "2026-11-20",
        },
        pelanggan: { nama: "Pelanggan Online", kanal: "online" },
      },
      {
        id: 23,
        status_respons: "menunggu_tindak_lanjut",
        status_penyelesaian_selisih: "belum_dihitung",
        opsi: { kode_opsi: "hubungi_admin", label: "Hubungi Admin" },
        menu_lama: { nama: "Menu Lama", qty: 2, harga: 12000, total: 24000 },
        menu_pengganti: null,
        selisih_total: null,
        total_pesanan_lama: null,
        total_pesanan_baru: null,
        catatan_respons: null,
        responded_at: "2026-11-16T01:00:00+07:00",
        catatan_tindak_lanjut: null,
        pesanan: {
          id: 33,
          kode: "SN-ON-2",
          status: "diproses",
          tanggal_kirim: "2026-11-20",
        },
        pelanggan: { nama: "Pelanggan Online 2", kanal: "online" },
      },
      {
        id: 24,
        status_respons: "menunggu_konfirmasi",
        status_penyelesaian_selisih: "belum_dihitung",
        opsi: { kode_opsi: "menu_pengganti", label: "Pilih menu pengganti" },
        menu_lama: { nama: "Menu Lama", qty: 4, harga: 12000, total: 48000 },
        menu_pengganti: { nama: "Menu Pengganti", qty: 4, harga: 15000, total: 60000 },
        selisih_total: 12000,
        total_pesanan_lama: null,
        total_pesanan_baru: null,
        catatan_respons: null,
        responded_at: "2026-11-16T01:00:00+07:00",
        catatan_tindak_lanjut: null,
        jenis_penutupan: null,
        alasan_penutupan: null,
        pesanan: {
          id: 34,
          kode: "SN-ON-3",
          status: "diproses",
          tanggal_kirim: "2026-11-20",
        },
        pelanggan: { nama: "Pelanggan Online 3", kanal: "online" },
      },
      {
        id: 25,
        status_respons: "selesai",
        status_penyelesaian_selisih: "belum_dihitung",
        opsi: { kode_opsi: "menu_pengganti", label: "Pilih menu pengganti" },
        menu_lama: { nama: "Menu Lama", qty: 4, harga: 12000, total: 48000 },
        menu_pengganti: { nama: "Menu Pengganti", qty: 4, harga: 15000, total: 60000 },
        selisih_total: 12000,
        total_pesanan_lama: null,
        total_pesanan_baru: null,
        catatan_respons: null,
        responded_at: "2026-11-16T01:00:00+07:00",
        catatan_tindak_lanjut: null,
        jenis_penutupan: "penolakan_pengganti",
        alasan_penutupan: "Stok pengganti habis.",
        pesanan: {
          id: 35,
          kode: "SN-ON-4",
          status: "dibatalkan",
          tanggal_kirim: "2026-11-20",
        },
        pelanggan: { nama: "Pelanggan Online 4", kanal: "online" },
      },
    ];
    const detailPayload = {
      id: 5,
      produk: { kode: "OLD-01", nama: "Menu Lama" },
      catatan_admin: "Menu sedang tidak tersedia.",
      created_at: "2026-11-16T01:00:00+07:00",
      created_by: { username: "admin" },
      opsi: [{ kode_opsi: "hubungi_admin", label: "Hubungi Admin" }],
      penerima: recipients,
      ringkasan: { jumlah_penerima: 5, sudah_merespons: 2, belum_merespons: 3, offline: 1 },
    };
    const listPayload = {
      items: [
        {
          id: 5,
          nama_produk_snapshot: "<img src=x onerror=alert(1)>",
          kode_produk_snapshot: "OLD-01",
          catatan_admin: "</p><script>alert(1)</script>",
          created_at: "2026-11-16T01:00:00+07:00",
          created_by_username: "admin",
          jumlah_target: 2,
          jumlah_pesanan: 1,
          online: 0,
          offline: 1,
          sudah_merespons: 0,
          belum_merespons: 1,
        },
      ],
      total: 1,
    };
    const api = async (url) =>
      String(url).includes("action=detail") ? detailPayload : listPayload;

    const context = vm.createContext({ document, api, confirm: () => true });
    vm.runInContext(
      fs.readFileSync("public/assets/js/menu-notifications.js", "utf8"),
      context,
    );
    await flush();

    assert.match(listEl.html, /&lt;img src=x onerror=alert\(1\)&gt;/);
    assert.doesNotMatch(listEl.html, /<img src=x/);
    assert.match(listEl.html, /&lt;script&gt;alert\(1\)&lt;\/script&gt;/);
    assert.doesNotMatch(listEl.html, /<\/p><script>/);
    assert.match(listEl.html, /1 offline/);
    assert.match(listEl.html, /1 menunggu respons/);
    assert.match(
      listEl.html,
      mode === "owner" ? /owner-secondary/ : /button secondary small/,
    );

    listEl.listeners.click({
      target: {
        closest(selector) {
          return selector === "[data-mn-detail]"
            ? { dataset: { mnDetail: "5" } }
            : null;
        },
      },
    });
    await flush();

    assert.match(detailBody.innerHTML, /Pelanggan Offline/);
    assert.match(detailBody.innerHTML, /Pelanggan Online/);
    assert.match(detailBody.innerHTML, /mn-chip offline">Offline/);
    assert.match(detailBody.innerHTML, /mn-chip">Online/);
    if (mode === "owner") {
      assert.doesNotMatch(detailBody.innerHTML, /data-followup-note=/);
      assert.doesNotMatch(detailBody.innerHTML, /complete_follow_up/);
      assert.doesNotMatch(detailBody.innerHTML, /reject_replacement/);
      assert.doesNotMatch(detailBody.innerHTML, /data-reject-reason=/);
    } else {
      assert.match(detailBody.innerHTML, /data-followup-note="21"/);
      assert.match(detailBody.innerHTML, /complete_follow_up" data-recipient="21"/);
      assert.doesNotMatch(detailBody.innerHTML, /data-followup-note="22"/);
      assert.doesNotMatch(detailBody.innerHTML, /complete_follow_up" data-recipient="22"/);
      assert.match(detailBody.innerHTML, /data-followup-note="23"/);
      assert.match(detailBody.innerHTML, /complete_follow_up" data-recipient="23"/);
      assert.match(detailBody.innerHTML, /data-mn-action="confirm" data-recipient="24"/);
      assert.match(detailBody.innerHTML, /data-reject-reason="24"/);
      assert.match(detailBody.innerHTML, /data-mn-action="reject_replacement" data-recipient="24"/);
    }
    assert.match(
      detailBody.innerHTML,
      /Penggantian ditolak Admin: Stok pengganti habis\./,
    );
    assert.doesNotMatch(detailBody.innerHTML, /data-reject-reason="25"/);
  }
}

async function testCustomer() {
  const listEl = element();
  const detailEl = element();
  const summaryEl = element();
  const summaryHistory = [];
  Object.defineProperty(summaryEl, "textContent", {
    get() {
      return summaryHistory[summaryHistory.length - 1] || "";
    },
    set(value) {
      summaryHistory.push(String(value));
    },
  });
  const badges = [
    { textContent: "1", hidden: false },
    { textContent: "1", hidden: false },
  ];
  const card = {
    classList: { removed: [], remove(value) { this.removed.push(value); } },
    querySelector() {
      return null;
    },
  };
  listEl.querySelector = (selector) => (selector === '[data-notice="7"]' ? card : null);
  const dialog = {
    open: false,
    showModal() {
      this.open = true;
    },
    close() {
      this.open = false;
    },
    querySelectorAll() {
      return [];
    },
    addEventListener() {},
  };
  const root = { dataset: { api: "api/menu-notifications.php" } };
  const document = {
    getElementById(id) {
      return {
        "mnc-page": root,
        "mnc-list": listEl,
        "mnc-summary": summaryEl,
        "mnc-more": element(),
        "mnc-refresh": element(),
        "mnc-detail-dialog": dialog,
        "mnc-detail": detailEl,
      }[id] || null;
    },
    querySelectorAll(selector) {
      return selector === "[data-notif-count]" ? badges : [];
    },
  };
  const calls = [];
  const api = async (url, options = {}) => {
    calls.push({ url, options });
    if (url.includes("action=detail")) {
      return {
        id: 7,
        produk: { kode: "OLD-01", nama: "<b>Menu Lama</b>" },
        catatan_admin: "Menu sedang tidak tersedia.",
        created_at: "2026-11-16T01:00:00+07:00",
        opsi: [{ kode_opsi: "menu_pengganti", label: "Pilih menu pengganti" }],
        penerima: [
          {
            id: 11,
            status_respons: "menunggu_respons",
            status_penyelesaian_selisih: "belum_dihitung",
            bisa_merespons: true,
            opsi: null,
            menu_lama: { nama: "Menu Lama", qty: 4, harga: 12000, total: 48000 },
            menu_pengganti: null,
            selisih_total: null,
            total_pesanan_lama: null,
            total_pesanan_baru: null,
            catatan_respons: null,
            responded_at: null,
            pilihan_menu: [
              { id: 2, kode: "ALT-01", nama: "Menu Pengganti", harga: 15000, kuota_efektif: 5 },
            ],
            pesanan: {
              id: 9,
              kode: "SN-9",
              status: "diproses",
              tgl_kirim: "2026-11-20T12:00:00+07:00",
              tanggal_kirim: "2026-11-20",
            },
            jenis_penutupan: null,
            alasan_penutupan: null,
          },
          {
            id: 12,
            status_respons: "selesai",
            status_penyelesaian_selisih: "belum_dihitung",
            bisa_merespons: false,
            opsi: { kode_opsi: "menu_pengganti", label: "Pilih menu pengganti" },
            menu_lama: { nama: "Menu Lama", qty: 4, harga: 12000, total: 48000 },
            menu_pengganti: {
              nama: "Menu Pengganti",
              qty: 4,
              harga: 15000,
              total: 60000,
            },
            selisih_total: 12000,
            total_pesanan_lama: null,
            total_pesanan_baru: null,
            catatan_respons: null,
            responded_at: "2026-11-16T01:00:00+07:00",
            pilihan_menu: [],
            pesanan: {
              id: 10,
              kode: "SN-10",
              status: "dibatalkan",
              tgl_kirim: "2026-11-20T12:00:00+07:00",
              tanggal_kirim: "2026-11-20",
            },
            jenis_penutupan: "penolakan_pengganti",
            alasan_penutupan: "Stok habis.",
          },
        ],
        ringkasan: { jumlah_penerima: 2, sudah_merespons: 1, belum_merespons: 1, belum_dibaca: 1 },
        belum_dibaca: 1,
      };
    }
    if (options.method === "POST") {
      return { ok: true, dibaca: 1, belum_dibaca: 0 };
    }
    return {
      items: [
        {
          id: 7,
          produk: { kode: "OLD-01", nama: "<b>Menu Lama</b>" },
          catatan_admin: "<script>alert(1)</script>",
          created_at: "2026-11-16T01:00:00+07:00",
          jumlah_pesanan: 1,
          jumlah_penerima: 1,
          sudah_merespons: 0,
          status_respons: ["menunggu_respons"],
          belum_dibaca: 1,
        },
      ],
      total: 1,
      belum_dibaca: 1,
    };
  };

  const context = vm.createContext({
    document,
    api,
    location: { search: "?notice_id=7" },
    URLSearchParams,
  });
  vm.runInContext(
    fs.readFileSync("public/pelanggan/assets/menu-notifications.js", "utf8"),
    context,
  );
  await flush();

  assert.match(listEl.html, /&lt;b&gt;Menu Lama&lt;\/b&gt;/);
  assert.match(listEl.html, /&lt;script&gt;alert\(1\)&lt;\/script&gt;/);
  assert.doesNotMatch(listEl.html, /<script>/);
  assert.match(listEl.html, /mnc-card unread/);
  assert.ok(
    summaryHistory.includes("1 notifikasi · 1 belum dibaca"),
    "Jumlah belum dibaca tampil sebelum dibaca",
  );
  assert.equal(summaryEl.textContent, "1 notifikasi · 0 belum dibaca");

  assert.match(detailEl.innerHTML, /Pilih tindakan/);
  assert.match(detailEl.innerHTML, /data-respond="11"/);
  assert.match(detailEl.innerHTML, /Rp 48.000/);
  assert.match(detailEl.innerHTML, /Penggantian ditolak Admin — Stok habis\./);
  assert.match(detailEl.innerHTML, /Admin menolak pilihan menu pengganti/);
  assert.doesNotMatch(detailEl.innerHTML, /data-respond="12"/);
  assert.ok(dialog.open, "Detail dialog dibuka dari tautan notice_id");

  const readCall = calls.find(
    (call) => call.options.method === "POST" && call.options.body?.action === "read",
  );
  assert.ok(readCall, "Membuka detail mengirim aksi read terpisah");
  assert.equal(readCall.options.body.id, 7);
  assert.equal(badges[0].textContent, "0");
  assert.equal(badges[0].hidden, true);
  assert.deepEqual(card.classList.removed, ["unread"]);
}

(async () => {
  await testAdminAndOwner();
  await testCustomer();
  console.log(
    "PASS: Notifikasi admin/owner dan pelanggan meng-escape data, membedakan mode, serta menandai dibaca lewat POST.",
  );
})().catch((error) => {
  console.error(error);
  process.exit(1);
});
