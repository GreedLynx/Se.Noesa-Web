const paymentForm = document.querySelector("#payment-method-form");
if (paymentForm) {
  const list = document.querySelector("#payment-method-list");
  const preview = document.querySelector("#payment-preview");
  const error = document.querySelector("#payment-form-error");
  const cancel = document.querySelector("#payment-cancel");
  let currentImage = "";
  const safeImage = (path) => /^uploads\/payment-methods\/method-[a-f0-9]+\.(?:jpg|png|webp)$/i.test(String(path || "")) ? "../" + path : "";
  const loadMethods = async () => {
    try {
      const data = await api("api/payments.php");
      list.innerHTML = data.items.length ? data.items.map((item) => {
        const image = safeImage(item.gambar);
        return `<article class="card payment-method-item"><div class="payment-method-image">${image ? `<img src="${image}" alt="${esc(item.nama)}">` : ""}</div><div class="payment-method-copy"><span class="payment-state ${item.aktif ? "active" : "inactive"}">${item.aktif ? "Aktif" : "Disembunyikan"}</span><h3>${esc(item.nama)}</h3><small>Metode pembayaran #${Number(item.id)}</small></div><div class="payment-item-actions"><button type="button" data-edit="${Number(item.id)}">Edit</button><button type="button" data-toggle="${Number(item.id)}" data-active="${item.aktif ? "1" : "0"}">${item.aktif ? "Nonaktifkan" : "Aktifkan"}</button><button type="button" class="danger" data-delete="${Number(item.id)}">Hapus</button></div></article>`;
      }).join("") : `<div class="card center">Belum ada metode pembayaran. Tambahkan QRIS toko untuk menampilkannya kepada pelanggan.</div>`;
      list.querySelectorAll("[data-edit]").forEach((button) => button.onclick = () => {
        const item = data.items.find((row) => Number(row.id) === Number(button.dataset.edit));
        paymentForm.elements.id.value = item.id; paymentForm.elements.nama.value = item.nama; paymentForm.elements.aktif.checked = Boolean(item.aktif); paymentForm.elements.gambar.required = false; currentImage = item.gambar;
        document.querySelector("#payment-form-title").textContent = "Ubah metode pembayaran"; cancel.hidden = false;
        preview.innerHTML = safeImage(item.gambar) ? `<img src="${safeImage(item.gambar)}" alt="Pratinjau ${esc(item.nama)}">` : ""; paymentForm.scrollIntoView({behavior:"smooth",block:"start"});
      });
      list.querySelectorAll("[data-toggle]").forEach((button) => button.onclick = async () => {
        const form = new FormData(); form.set("action", "toggle"); form.set("id", button.dataset.toggle); form.set("aktif", button.dataset.active === "1" ? "0" : "1");
        try { await api("api/payments.php", {method:"POST",body:form}); toast("Status metode pembayaran diperbarui"); await loadMethods(); } catch (e) { toast(e.message); }
      });
      list.querySelectorAll("[data-delete]").forEach((button) => button.onclick = async () => {
        if (!confirm("Hapus metode pembayaran ini? Gambar yang tersimpan juga akan dihapus.")) return;
        const form = new FormData(); form.set("action", "delete"); form.set("id", button.dataset.delete);
        try { await api("api/payments.php", {method:"POST",body:form}); toast("Metode pembayaran dihapus"); await loadMethods(); } catch (e) { toast(e.message); }
      });
    } catch (e) { list.innerHTML = `<div class="card center payment-form-error">${esc(e.message)}</div>`; }
  };
  paymentForm.elements.gambar.onchange = () => {
    const file = paymentForm.elements.gambar.files[0];
    if (!file) { preview.innerHTML = currentImage && safeImage(currentImage) ? `<img src="${safeImage(currentImage)}" alt="Pratinjau metode pembayaran">` : "<span>Pratinjau QR / instruksi pembayaran</span>"; return; }
    const url = URL.createObjectURL(file); preview.innerHTML = `<img src="${url}" alt="Pratinjau gambar pembayaran">`;
  };
  const resetForm = () => { paymentForm.reset(); paymentForm.elements.id.value = ""; paymentForm.elements.gambar.required = true; document.querySelector("#payment-form-title").textContent = "Tambah metode pembayaran"; cancel.hidden = true; currentImage = ""; error.textContent = ""; preview.innerHTML = "<span>Pratinjau QR / instruksi pembayaran</span>"; };
  cancel.onclick = resetForm;
  paymentForm.onsubmit = async (event) => {
    event.preventDefault(); error.textContent = ""; const submit = paymentForm.querySelector('[type="submit"]'); submit.disabled = true;
    const form = new FormData(paymentForm); form.set("action", "save"); form.set("aktif", paymentForm.elements.aktif.checked ? "1" : "0");
    try { await api("api/payments.php", {method:"POST",body:form}); toast("Metode pembayaran tersimpan"); resetForm(); await loadMethods(); } catch (e) { error.textContent = e.message; } finally { submit.disabled = false; }
  };
  loadMethods();
}
