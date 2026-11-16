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
    const listEl = element();
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
        return null;
      },
      querySelectorAll() {
        return [];
      },
    };
    const api = async () => ({
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
    });

    const context = vm.createContext({ document, api });
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
          },
        ],
        ringkasan: { jumlah_penerima: 1, sudah_merespons: 0, belum_merespons: 1, belum_dibaca: 1 },
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
