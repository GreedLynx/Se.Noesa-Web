const $ = (s, r = document) => r.querySelector(s),
    $$ = (s, r = document) => [...r.querySelectorAll(s)];
const rp = (n) => "Rp " + Number(n).toLocaleString("id-ID"),
    short = (n) => "Rp " + (n / 1000).toFixed(1).replace(".0", "") + "k";
const productImage = (value) => {
    const raw = String(value || "");
    if (/^uploads\/products\/[a-f0-9]+\.(jpg|png|webp)$/i.test(raw)) return "../" + raw;
    const file = raw.replace(/[^a-zA-Z0-9_.-]/g, "");
    return "../assets/img/produk/" + (file || "NK-01.png");
};
const page = document.body.dataset.page;
const appPrefix = /\/(owner|pelanggan)\//.test(location.pathname) ? "../" : "";
async function api(url, opt = {}) {
    if (!opt.method && $('#quota-date') && /^api\/(products|orders)\.php/.test(url))
        url += (url.includes('?') ? '&' : '?') + 'tgl_kirim=' + encodeURIComponent($('#quota-date').value);
    const multipart = opt.body instanceof FormData;
    const r = await fetch(url, {
        headers: multipart ? {} : { "Content-Type": "application/json" },
        ...opt,
        body: opt.body ? (multipart ? opt.body : JSON.stringify(opt.body)) : undefined,
    });
    const d = await r
        .json()
        .catch (() => ({ error: "Respon server tidak valid" }));
    if (r.status === 401 && page !== "login") {
        location.href = appPrefix + "login.php";
        return Promise.reject();
    }
    if (!r.ok) throw Object.assign(new Error(d.error || "Terjadi kesalahan"), d);
    return d;
}
function toast(m) {
    const t = $("#toast");
    t.textContent = m;
    t.classList.add("on");
    setTimeout(() => t.classList.remove("on"), 2200);
}
if ($('#quota-date')) $('#quota-date').onchange = () => {
    const url = new URL(location.href);
    url.searchParams.set('tgl_kirim', $('#quota-date').value);
    location.href = url.href
};

// --- FUNGSI CUSTOM CONFIRM DIALOG ---
function confirmDialog(title, message, confirmLabel = "Ya, Lanjutkan", type = "primary") {
    return new Promise((resolve) => {
        const dialog = $("#app-confirm-dialog");
        if (!dialog) return resolve(confirm(message));

        const titleEl = $("#confirm-title");
        const msgEl = $("#confirm-message");
        const btnYes = $("#confirm-yes-btn");
        const iconEl = $("#confirm-icon");

        titleEl.textContent = title;
        msgEl.textContent = message;
        btnYes.textContent = confirmLabel;

        if (type === "danger") {
            btnYes.style.background = "#a5463b";
            btnYes.style.color = "#fff";
            iconEl.style.background = "#f8e4e1";
            iconEl.style.color = "#a5463b";
            iconEl.innerHTML = "&#9888;";
        } else {
            btnYes.style.background = ""; // default (var(--g))
            btnYes.style.color = "#fff";
            iconEl.style.background = "var(--gl)";
            iconEl.style.color = "var(--gd)";
            iconEl.innerHTML = "&#10003;";
        }

        dialog.showModal();

        const close = (result) => {
            dialog.close();
            cleanup();
            resolve(result);
        };

        const onCancel = () => close(false);
        const onConfirm = () => close(true);
        const closeBtns = $$("[data-close-confirm]", dialog);

        const cleanup = () => {
            closeBtns.forEach(b => b.onclick = null);
            btnYes.onclick = null;
            dialog.onclick = null;
        };

        closeBtns.forEach(b => b.onclick = onCancel);
        btnYes.onclick = onConfirm;
        dialog.onclick = (e) => { if (e.target === dialog) onCancel(); };
    });
}
// ------------------------------------

function countUp(el, to, ms = 1100) {
    const f = +el.dataset.from || 0,
        t0 = performance.now();
    el.dataset.from = to;
    (function s(t) {
        const p = Math.min((t - t0) / ms, 1),
            e = 1 - Math.pow(1 - p, 4);
        el.textContent = Math.round(f + (to - f) * e).toLocaleString("id-ID");
        if (p < 1) requestAnimationFrame(s);
    })(t0);
}
const stagger = (r) =>
    $$(".rise", r).forEach((e, i) => e.style.setProperty("--i", i));
stagger();
/* transisi antar halaman */
document.addEventListener("click", (e) => {
    const a = e.target.closest("a[href]");
    if (!a || a.target || a.href.includes("#")) return;
    e.preventDefault();
    document.body.classList.add("leave");
    setTimeout(() => (location.href = a.href), 230);
});
/* pill navigasi */
const on = $(".nav a.on");
if (on) {
    const mv = () => {
        const pill = $("#pill");
        if (matchMedia("(min-width: 900px)").matches) {
            pill.style.left = "12px";
            pill.style.top = on.offsetTop + "px";
            pill.style.width = "calc(100% - 24px)";
        } else {
            pill.style.top = "8px";
            pill.style.left = on.offsetLeft + on.offsetWidth / 2 - 35 + "px";
            pill.style.width = "70px";
        }
    };
    mv();
    addEventListener("resize", mv);
}
/* toggle password */
$$(".eye").forEach(
    (b) =>
        (b.onclick = () => {
            const i = b.previousElementSibling;
            i.type = i.type === "password" ? "text" : "password";
        }),
);

// UPDATE LOGOUT MENGGUNAKAN CONFIRM DIALOG
$$("[data-logout]").forEach(
    (b) =>
        (b.onclick = async () => {
            const confirmed = await confirmDialog(
                "Keluar Akun",
                "Apakah Anda yakin ingin keluar dari panel owner Se.Noesa?",
                "Ya, Keluar",
                "primary"
            );
            if (!confirmed) return;

            b.disabled = true;
            try {
                await api("api/auth.php?a=logout");
                location.href = appPrefix + "login.php";
            } catch (e) {
                b.disabled = false;
                toast("Gagal keluar: " + e.message);
            }
        }),
);
const PAGES = {
    splash() {
        setTimeout(() => {
<<<<<<< HEAD
            document.body.classList.add("leave");
            const next = document.body.dataset.next;
            const destinations = ["login.php", "owner/dashboard.php", "admin/dashboard.php", "pelanggan/index.php"];
            setTimeout(
                () =>
                    (location.href =
                        destinations.includes(next) ? next : "login.php"),
                230,
            );
        }, 2600);
    },
    async form() {
        const f = $("#f");
        f.onsubmit = async (e) => {
            e.preventDefault();
            const b = $(".btn-main", f),
                er = $("#err");
            er.textContent = "";
            er.classList.remove("on");
            b.disabled = true;
            b.textContent = "Memproses…";
            try {
                const d = await api(f.dataset.api, {
                    method: "POST",
                    body: Object.fromEntries(new FormData(f)),
                });
                toast("Berhasil");
                setTimeout(() => {
                    document.body.classList.add("leave");
                    setTimeout(() => (location.href = d.redirect), 230);
                }, 300);
            } catch (x) {
                er.textContent = x.message;
                void er.offsetWidth;
                er.classList.add("on");
                b.disabled = false;
                b.textContent = page === "login" ? "Login" : "Sign Up";
            }
=======
          document.body.classList.add("leave");
          setTimeout(() => (location.href = d.redirect), 230);
        }, 300);
      } catch (x) {
        er.textContent = x.message;
        void er.offsetWidth;
        er.classList.add("on");
        b.disabled = false;
        b.textContent = page === "login" ? "Login" : "Sign Up";
      }
    };
  },
  async "forgot-password"() {
    const emailForm = $("#forgot-password-form");
    const resetForm = $("#reset-password-form");
    const emailInput = emailForm.elements.email;
    const requestButton = $("#request-otp-button");
    const resendButton = $("#resend-otp-button");
    const changeEmailButton = $("#change-reset-email");
    const requestError = $("#forgot-password-error");
    const notice = $("#forgot-password-notice");
    const resetError = $("#reset-password-error");
    const otpInput = resetForm.elements.otp;
    let cooldownTimer;

    const showError = (element, message) => {
      element.textContent = message;
      element.classList.add("on");
    };
    const clearError = (element) => {
      element.textContent = "";
      element.classList.remove("on");
    };
    const startCooldown = () => {
      clearInterval(cooldownTimer);
      let remaining = 60;
      resendButton.disabled = true;
      resendButton.textContent = `Kirim ulang kode (${remaining})`;
      cooldownTimer = setInterval(() => {
        remaining -= 1;
        if (remaining <= 0) {
          clearInterval(cooldownTimer);
          resendButton.disabled = false;
          resendButton.textContent = "Kirim ulang kode OTP";
        } else {
          resendButton.textContent = `Kirim ulang kode (${remaining})`;
        }
      }, 1000);
    };
    const requestOtp = async () => {
      clearError(requestError);
      requestButton.disabled = true;
      resendButton.disabled = true;
      try {
        const result = await api("api/password-reset.php", {
          method: "POST",
          body: { action: "request", email: emailInput.value },
        });
        notice.textContent = result.message;
        notice.classList.add("on");
        resetForm.hidden = false;
        emailInput.disabled = true;
        requestButton.hidden = true;
        otpInput.focus();
        startCooldown();
      } catch (error) {
        showError(requestError, error.message);
        requestButton.disabled = false;
        resendButton.disabled = false;
        resendButton.textContent = "Kirim ulang kode OTP";
      }
    };

    emailForm.onsubmit = async (event) => {
      event.preventDefault();
      await requestOtp();
    };
    otpInput.addEventListener("input", () => {
      otpInput.value = otpInput.value.replace(/\D/g, "").slice(0, 6);
    });
    resendButton.onclick = requestOtp;
    changeEmailButton.onclick = () => {
      clearInterval(cooldownTimer);
      resetForm.reset();
      resetForm.hidden = true;
      emailInput.disabled = false;
      requestButton.hidden = false;
      requestButton.disabled = false;
      resendButton.disabled = true;
      resendButton.textContent = "Kirim ulang kode (60)";
      notice.textContent = "";
      notice.classList.remove("on");
      clearError(requestError);
      clearError(resetError);
      emailInput.focus();
    };
    resetForm.onsubmit = async (event) => {
      event.preventDefault();
      clearError(resetError);
      const saveButton = resetForm.querySelector('button[type="submit"]');
      saveButton.disabled = true;
      saveButton.textContent = "Menyimpan…";
      try {
        const result = await api("api/password-reset.php", {
          method: "POST",
          body: {
            action: "reset",
            email: emailInput.value,
            ...Object.fromEntries(new FormData(resetForm)),
          },
        });
        toast(result.message);
        setTimeout(() => {
          document.body.classList.add("leave");
          setTimeout(() => (location.href = "login.php"), 230);
        }, 900);
      } catch (error) {
        showError(resetError, error.message);
        saveButton.disabled = false;
        saveButton.textContent = "Simpan Password Baru";
      }
    };
  },
  async dashboard() {
    const L = {
      today: [
        "OMZET TERVERIFIKASI HARI INI",
        "Total Hari Ini",
        "KUANTITAS PAX",
      ],
      week: [
        "OMZET TERVERIFIKASI 7 HARI TERAKHIR",
        "Total 7 Hari",
        "TOTAL TERJUAL",
      ],
      month: [
        "OMZET TERVERIFIKASI BULAN INI",
        "Total Bulan Ini",
        "KUANTITAS PAX",
      ],
    };
    const pageParams = new URLSearchParams(location.search);
    const initialPeriod = ["today", "week", "month"].includes(pageParams.get("period")) ? pageParams.get("period") : "today";
    const initialSelection = pageParams.get("selected");
    let selected = null, requestId = 0;
    function selectedLabel(period, key) {
      if (period === "week") {
        const [y, m, day] = key.split("-").map(Number);
        return new Date(y, m - 1, day, 12).toLocaleDateString("id-ID", {
          weekday: "long", day: "numeric", month: "long",
        });
      }
      const [y, m] = key.split("-").map(Number);
      return new Date(y, m - 1, 1, 12).toLocaleDateString("id-ID", {
        month: "long", year: "numeric",
      });
    }
    async function load(p, chosen = null) {
      const request = ++requestId;
      const d = await api("api/dashboard.php?period=" + p + (chosen ? "&selected=" + encodeURIComponent(chosen) : ""));
      if (request !== requestId) return;
      selected = d.selected;
      const pickedLabel = selected ? selectedLabel(p, selected) : null;
      $("#hLabel").textContent = pickedLabel
        ? "OMZET TERVERIFIKASI " + (p === "week" ? "HARI " : "BULAN ") + pickedLabel.toLocaleUpperCase("id-ID")
        : L[p][0];
      $("#tot").textContent = pickedLabel ? "Total " + pickedLabel : L[p][1];
      $("#l2").textContent = L[p][2];
      countUp($("#omzet"), d.omzet);
      $("#tiket").textContent = short(d.tiket);
      $("#items").textContent = d.items.toLocaleString("id-ID") + " Item";
      $("#growth").textContent =
        (d.growth >= 0 ? "↗ +" : "↘ ") + d.growth + "%";
      $("#orders").textContent = d.orders + " Pesanan";
      const max = Math.max(...d.chart.map((c) => Number(c.v)), 1),
        hi = d.selected ? d.chart.findIndex((c) => c.k === d.selected) : p === "month" ? new Date().getMonth() : d.chart.length - 1;
      const chartPeriod = p === "month" ? "month" : "week";
      $("#bars").innerHTML = d.chart
        .map(
          (c, i) => {
            const label = chartPeriod === "month"
              ? new Date(Number(c.k.slice(0, 4)), Number(c.k.slice(5, 7)) - 1, 1, 12).toLocaleDateString("id-ID", { month: "short" }).replace(/\.$/, "")
              : selectedLabel("week", c.k).split(" ")[0].slice(0, 3);
            return `<a class="bar${i === hi ? " hi" : ""}" href="dashboard.php?period=${chartPeriod}&amp;selected=${encodeURIComponent(c.k)}" title="${selectedLabel(chartPeriod, c.k)}: ${rp(c.v)}"><i style="--n:${i}"></i>${label}</a>`;
          },
        )
        .join("");
      requestAnimationFrame(() =>
        requestAnimationFrame(() =>
          $$("#bars .bar i").forEach(
            (el, i) =>
              (el.style.height =
                Math.max((d.chart[i].v / max) * 100, 10) + "%"),
          ),
        ),
      );
      const kn = { makanan: "Makanan", minuman: "Minuman", snack: "Snack" };
      $("#kat").innerHTML =
        d.kategori
          .map(
            (k) =>
              `<div class="krow"><span>${kn[k.k]} (${short(k.h).replace("Rp ", "Rp ")})</span><b>${k.n} ${k.k === "minuman" ? "Botol" : "Porsi"}</b></div>`,
          )
          .join("") || '<p class="empty">Belum ada data</p>';
      $("#top").innerHTML = d.top
        .map(
          (t, i) =>
            `<div class="prod rise" style="--i:${i}"><span class="rk">${i + 1}</span><img src="${productImage(t.gambar)}" alt=""><div><b>${t.nama}</b>${t.n} Terjual • ${rp(t.harga)}<span class="om"><span>Kontribusi Omzet</span><strong>${rp(t.om)}</strong></span></div></div>`,
        )
        .join("");
    }
    $$("#period button").forEach(
      (b) =>
        (b.onclick = () => {
          $$("#period button").forEach((x) =>
            x.classList.toggle("on", x === b),
          );
          selected = null;
          load(b.dataset.p);
        }),
    );
    $$("#period button").forEach((b) => b.classList.toggle("on", b.dataset.p === initialPeriod));
    load(initialPeriod, initialSelection);
  },
  async orders() {
    let tab = "validasi",
      t;
    async function load() {
      const d = await api(
        `api/orders.php?tab=${tab}&q=${encodeURIComponent($("#q").value)}`,
      );
      $("#cv").textContent = d.count.v;
      $("#cp").textContent = d.count.p;
      const c = d.kapasitas || { kapasitas: 1, pakai: 0 },
        pct = Math.round((c.pakai / c.kapasitas) * 100);
      $("#gfg").style.strokeDashoffset = 100 - Math.min(pct, 100);
      countUp($("#gpct"), pct, 1400);
      setTimeout(() => ($("#gpct").textContent = pct + "%"), 1500);
      $("#gtxt").textContent =
        `${c.pakai.toLocaleString("id-ID")} / ${c.kapasitas.toLocaleString("id-ID")} porsi · sisa ${(c.kapasitas - c.pakai).toLocaleString("id-ID")} pax aman`;
      $("#list").innerHTML =
        d.orders
          .map(
            (o, i) =>
              `<a class="ord" style="--i:${i}" href="order-detail.php?id=${o.id}"><div class="h"><span>${o.kode}</span><span class="tag">${o.jam} WIB</span></div><div class="b"><span class="ic">▤</span><div><b>${o.nama_pemesan}</b>${o.keterangan || ""}</div><strong>${rp(o.total)}</strong></div>${tab === "validasi" ? '<span class="go">Cek Detail Pesanan</span>' : ""}</a>`,
          )
          .join("") || '<p class="empty">Tidak ada pesanan</p>';
    }
    $$("#tabs button").forEach(
      (b) =>
        (b.onclick = () => {
          tab = b.dataset.t;
          $$("#tabs button").forEach((x) => x.classList.toggle("on", x === b));
          load();
        }),
    );
    $("#q").oninput = () => {
      clearTimeout(t);
      t = setTimeout(load, 300);
    };
    load();
  },
  async detail() {
    const el = $("#detail"),
      id = el.dataset.id,
      btn = $("#act");
    async function load() {
      const o = await api("api/orders.php?id=" + id),
        m = {
          menunggu_validasi: "Menunggu",
          diproses: "Diproses",
          selesai: "Selesai",
          ditolak: "Ditolak",
>>>>>>> ee7409047cd73012b889f455d127c3b869aed233
        };
    },
    async "forgot-password"() {
        const emailForm = $("#forgot-password-form");
        const resetForm = $("#reset-password-form");
        const emailInput = emailForm.elements.email;
        const requestButton = $("#request-otp-button");
        const resendButton = $("#resend-otp-button");
        const changeEmailButton = $("#change-reset-email");
        const requestError = $("#forgot-password-error");
        const notice = $("#forgot-password-notice");
        const resetError = $("#reset-password-error");
        const otpInput = resetForm.elements.otp;
        let cooldownTimer;

        const showError = (element, message) => {
            element.textContent = message;
            element.classList.add("on");
        };
        const clearError = (element) => {
            element.textContent = "";
            element.classList.remove("on");
        };
        const startCooldown = () => {
            clearInterval(cooldownTimer);
            let remaining = 60;
            resendButton.disabled = true;
            resendButton.textContent = `Kirim ulang kode (${remaining})`;
            cooldownTimer = setInterval(() => {
                remaining -= 1;
                if (remaining <= 0) {
                    clearInterval(cooldownTimer);
                    resendButton.disabled = false;
                    resendButton.textContent = "Kirim ulang kode OTP";
                } else {
                    resendButton.textContent = `Kirim ulang kode (${remaining})`;
                }
            }, 1000);
        };
        const requestOtp = async () => {
            clearError(requestError);
            requestButton.disabled = true;
            resendButton.disabled = true;
            try {
                const result = await api("api/password-reset.php", {
                    method: "POST",
                    body: { action: "request", email: emailInput.value },
                });
                notice.textContent = result.message;
                notice.classList.add("on");
                resetForm.hidden = false;
                emailInput.disabled = true;
                requestButton.hidden = true;
                otpInput.focus();
                startCooldown();
            } catch (error) {
                showError(requestError, error.message);
                requestButton.disabled = false;
                resendButton.disabled = false;
                resendButton.textContent = "Kirim ulang kode OTP";
            }
        };

        emailForm.onsubmit = async (event) => {
            event.preventDefault();
            await requestOtp();
        };
        otpInput.addEventListener("input", () => {
            otpInput.value = otpInput.value.replace(/\D/g, "").slice(0, 6);
        });
        resendButton.onclick = requestOtp;
        changeEmailButton.onclick = () => {
            clearInterval(cooldownTimer);
            resetForm.reset();
            resetForm.hidden = true;
            emailInput.disabled = false;
            requestButton.hidden = false;
            requestButton.disabled = false;
            resendButton.disabled = true;
            resendButton.textContent = "Kirim ulang kode (60)";
            notice.textContent = "";
            notice.classList.remove("on");
            clearError(requestError);
            clearError(resetError);
            emailInput.focus();
        };
        resetForm.onsubmit = async (event) => {
            event.preventDefault();
            clearError(resetError);
            const saveButton = resetForm.querySelector('button[type="submit"]');
            saveButton.disabled = true;
            saveButton.textContent = "Menyimpan…";
            try {
                const result = await api("api/password-reset.php", {
                    method: "POST",
                    body: {
                        action: "reset",
                        email: emailInput.value,
                        ...Object.fromEntries(new FormData(resetForm)),
                    },
                });
                toast(result.message);
                setTimeout(() => {
                    document.body.classList.add("leave");
                    setTimeout(() => (location.href = "login.php"), 230);
                }, 900);
            } catch (error) {
                showError(resetError, error.message);
                saveButton.disabled = false;
                saveButton.textContent = "Simpan Password Baru";
            }
        };
    },
    async dashboard() {
        const L = {
            today: [
                "OMZET TERVERIFIKASI HARI INI",
                "Total Hari Ini",
                "KUANTITAS PAX",
            ],
            week: [
                "OMZET TERVERIFIKASI 7 HARI TERAKHIR",
                "Total 7 Hari",
                "TOTAL TERJUAL",
            ],
            month: [
                "OMZET TERVERIFIKASI BULAN INI",
                "Total Bulan Ini",
                "KUANTITAS PAX",
            ],
        };
        const pageParams = new URLSearchParams(location.search);
        const initialPeriod = ["today", "week", "month"].includes(pageParams.get("period")) ? pageParams.get("period") : "today";
        const initialSelection = pageParams.get("selected");
        let selected = null, requestId = 0;
        function selectedLabel(period, key) {
            if (period === "week") {
                const [y, m, day] = key.split("-").map(Number);
                return new Date(y, m - 1, day, 12).toLocaleDateString("id-ID", {
                    weekday: "long", day: "numeric", month: "long",
                });
            }
            const [y, m] = key.split("-").map(Number);
            return new Date(y, m - 1, 1, 12).toLocaleDateString("id-ID", {
                month: "long", year: "numeric",
            });
        }
        async function load(p, chosen = null) {
            const request = ++requestId;
            const d = await api("api/dashboard.php?period=" + p + (chosen ? "&selected=" + encodeURIComponent(chosen) : ""));
            if (request !== requestId) return;
            selected = d.selected;
            const pickedLabel = selected ? selectedLabel(p, selected) : null;
            $("#hLabel").textContent = pickedLabel
                ? "OMZET TERVERIFIKASI " + (p === "week" ? "HARI " : "BULAN ") + pickedLabel.toLocaleUpperCase("id-ID")
                : L[p][0];
            $("#tot").textContent = pickedLabel ? "Total " + pickedLabel : L[p][1];
            $("#l2").textContent = L[p][2];
            countUp($("#omzet"), d.omzet);
            $("#tiket").textContent = short(d.tiket);
            $("#items").textContent = d.items.toLocaleString("id-ID") + " Item";
            $("#growth").textContent =
                (d.growth >= 0 ? "↗ +" : "↘ ") + d.growth + "%";
            $("#orders").textContent = d.orders + " Pesanan";
            const max = Math.max(...d.chart.map((c) => Number(c.v)), 1),
                hi = d.selected ? d.chart.findIndex((c) => c.k === d.selected) : p === "month" ? new Date().getMonth() : d.chart.length - 1;
            const chartPeriod = p === "month" ? "month" : "week";
            $("#bars").innerHTML = d.chart
                .map(
                    (c, i) => {
                        const label = chartPeriod === "month"
                            ? new Date(Number(c.k.slice(0, 4)), Number(c.k.slice(5, 7)) - 1, 1, 12).toLocaleDateString("id-ID", { month: "short" }).replace(/\.$/, "")
                            : selectedLabel("week", c.k).split(" ")[0].slice(0, 3);
                        return `<a class="bar${i === hi ? " hi" : ""}" href="dashboard.php?period=${chartPeriod}&amp;selected=${encodeURIComponent(c.k)}" title="${selectedLabel(chartPeriod, c.k)}: ${rp(c.v)}"><i style="--n:${i}"></i>${label}</a>`;
                    },
                )
                .join("");
            requestAnimationFrame(() =>
                requestAnimationFrame(() =>
                    $$("#bars .bar i").forEach(
                        (el, i) =>
                            (el.style.height =
                                Math.max((d.chart[i].v / max) * 100, 10) + "%"),
                    ),
                ),
            );
            const kn = { makanan: "Makanan", minuman: "Minuman", snack: "Snack" };
            $("#kat").innerHTML =
                d.kategori
                    .map(
                        (k) =>
                            `<div class="krow"><span>${kn[k.k]} (${short(k.h).replace("Rp ", "Rp ")})</span><b>${k.n} ${k.k === "minuman" ? "Botol" : "Porsi"}</b></div>`,
                    )
                    .join("") || '<p class="empty">Belum ada data</p>';
            $("#top").innerHTML = d.top
                .map(
                    (t, i) =>
                        `<div class="prod rise" style="--i:${i}"><span class="rk">${i + 1}</span><img src="${productImage(t.gambar)}" alt=""><div><b>${t.nama}</b>${t.n} Terjual • ${rp(t.harga)}<span class="om"><span>Kontribusi Omzet</span><strong>${rp(t.om)}</strong></span></div></div>`,
                )
                .join("");
        }
        $$("#period button").forEach(
            (b) =>
                (b.onclick = () => {
                    $$("#period button").forEach((x) =>
                        x.classList.toggle("on", x === b),
                    );
                    selected = null;
                    load(b.dataset.p);
                }),
        );
        $$("#period button").forEach((b) => b.classList.toggle("on", b.dataset.p === initialPeriod));
        load(initialPeriod, initialSelection);
    },
    async orders() {
        let tab = "validasi",
            t;
        async function load() {
            const d = await api(
                `api/orders.php?tab=${tab}&q=${encodeURIComponent($("#q").value)}`,
            );
            $("#cv").textContent = d.count.v;
            $("#cp").textContent = d.count.p;
            const c = d.kapasitas || { kapasitas: 1, pakai: 0 },
                pct = c.kapasitas>0?Math.round((c.pakai / c.kapasitas) * 100):0;
            $("#gfg").style.strokeDashoffset = 100 - Math.min(pct, 100);
            countUp($("#gpct"), pct, 1400);
            setTimeout(() => ($("#gpct").textContent = pct + "%"), 1500);
            $("#gtxt").textContent =
                `${c.pakai.toLocaleString("id-ID")} / ${c.kapasitas.toLocaleString("id-ID")} porsi · sisa ${Math.max(0,c.kapasitas - c.pakai).toLocaleString("id-ID")} porsi`;
            $("#list").innerHTML =
                d.orders
                    .map(
                        (o, i) =>
                            `<a class="ord" style="--i:${i}" href="order-detail.php?id=${o.id}"><div class="h"><span>${o.kode}</span><span class="tag">${o.jam} WIB</span></div><div class="b"><span class="ic">▤</span><div><b>${o.nama_pemesan}</b>${o.keterangan || ""}</div><strong>${rp(o.total)}</strong></div>${tab === "validasi" ? '<span class="go">Cek Detail Pesanan</span>' : ""}</a>`,
                    )
                    .join("") || '<p class="empty">Tidak ada pesanan</p>';
        }
        $$("#tabs button").forEach(
            (b) =>
                (b.onclick = () => {
                    tab = b.dataset.t;
                    $$("#tabs button").forEach((x) => x.classList.toggle("on", x === b));
                    load();
                }),
        );
        $("#q").oninput = () => {
            clearTimeout(t);
            t = setTimeout(load, 300);
        };
        load();
    },
    async detail() {
        const el = $("#detail"),
            id = el.dataset.id,
            btn = $("#act");
        async function load() {
            const o = await api("api/orders.php?id=" + id),
                m = {
                    menunggu_validasi: "Menunggu",
                    diproses: "Diproses",
                    selesai: "Selesai",
                    ditolak: "Ditolak",
                };
            const dt = (s) =>
                s
                    ? new Date(s)
                            .toLocaleString("id-ID", {
                                day: "numeric",
                                month: "short",
                                year: "numeric",
                                hour: "2-digit",
                                minute: "2-digit",
                            })
                            .replace(".", ":") + " WIB"
                    : "-";
            el.innerHTML = `<div class="dbox" style="--i:0">
        <div class="row between">
            <div>
                <small>Kode Pesanan</small>
                <b style="display:block;font-size:18px">${o.kode}</b>
            </div>
            <span class="tag">${m[o.status]}</span>
        </div>
        <hr>
        <div class="row">
            <span class="avatar" style="background:var(--gl);color:var(--br)">☻</span>
            <div>
                <small>Pemesan</small>
                <b style="display:block">${o.nama_pemesan}</b>
            </div>
        </div>
        <hr>
        <div class="jd"><span>Tanggal &amp; Waktu Pemesanan</span><b>${dt(o.created_at)}</b></div>
        <div class="jd"><span>Tanggal &amp; Waktu Pengiriman</span><b>${dt(o.tgl_kirim)}</b></div>
        <div class="jd"><span>Alamat Tujuan</span><b>${o.alamat_pengiriman || '-'}</b></div>
      </div>

      <div class="dbox" style="--i:1">
        <b>Bukti Pesanan Pelanggan</b>
        <div class="proof">${o.bukti_bayar ? `<img src="${o.bukti_bayar}" alt="Bukti">` : "Foto bukti pesanan"}</div>
      </div>

      <div class="dbox" style="--i:2">
        <div class="row between"><b>Daftar Menu</b><span class="tag">${o.menu.length} Jenis</span></div>
        ${o.menu.map((x) => `<div class="mi" style="padding-left:0;"><span><b>${x.nama}</b><small>${x.harga.toLocaleString("id-ID")} x ${x.qty} porsi</small></span><strong>${rp(x.sub)}</strong></div>`).join("")}
      </div>`;
            btn.hidden = true; // owner read-only
        }
        load();
    },
    async products() {
        let k = "",
            t;
        async function load() {
            const d = await api(
                `api/products.php?kategori=${k}&q=${encodeURIComponent($("#q").value)}`,
            );
            $("#cs").textContent = d.count.semua;
            if ($('#quota-summary'))$('#quota-summary').textContent='Kapasitas dapur '+d.maxKitchen+' porsi · Terpakai '+d.kitchenUsed+' porsi · Sisa '+d.kitchenRemaining+' porsi';
            $("#ck").textContent = d.count.kritis;
            $("#plist").innerHTML =
                d.items
                    .map((p, i) => {
                        const pct = p.kuota>0?(p.sisa / p.kuota) * 100:0;
                        const img = p.gambar ? `<img src="../${p.gambar}" alt="${p.nama}" onerror="this.src='../assets/img/produk/NK-01.png'">` : '<img src="../assets/img/produk/NK-01.png" alt="placeholder">';
                        return `<div class="pr" style="--i:${i}">${img}<span class="kode">${p.kode}</span><div><b>${p.nama}</b><small>${rp(p.harga)} · ${p.sisa}/${p.kuota} porsi${p.sisa <= 0 ? " · habis" : ""}</small><div class="meter${pct <= 20 ? " low" : ""}"><i data-w="${pct}"></i></div></div></div>`;
                    })
                    .join("") || '<p class="empty">Produk tidak ditemukan</p>';
            requestAnimationFrame(() =>
                requestAnimationFrame(() =>
                    $$(".meter i").forEach((i) => (i.style.width = i.dataset.w + "%")),
                ),
            );
        }
        $$("#chips button").forEach(
            (b) =>
                (b.onclick = () => {
                    k = b.dataset.k;
                    $$("#chips button").forEach((x) => x.classList.toggle("on", x === b));
                    load();
                }),
        );
        $("#q").oninput = () => {
            clearTimeout(t);
            t = setTimeout(load, 300);
        };
        load();
    },
};
(page === "login" || page === "register"
    ? PAGES.form
    : PAGES[page] || (() => {}))();
