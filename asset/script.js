// =========================================
// FUNGSI UMUM (dipakai di halaman pembayaran)
// =========================================

document.addEventListener('DOMContentLoaded', function () {
    // Tampilkan ringkasan pesanan jika ada
    const ringkasan = document.getElementById('ringkasan');
    if (ringkasan) {
        try {
            const isiRingkasan = localStorage.getItem('ringkasanPesanan');
            if (isiRingkasan) ringkasan.value = isiRingkasan;
        } catch (err) { /* abaikan */ }
    }

    // Preview gambar bukti pembayaran
    const fileUpload = document.getElementById('file-upload');
    const previewImage = document.getElementById('preview-image');
    const uploadIcon = document.querySelector('.upload-icon');

    if (fileUpload && previewImage) {
        fileUpload.addEventListener('change', function (e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function (event) {
                    previewImage.src = event.target.result;
                    previewImage.style.display = 'block';
                    if (uploadIcon) uploadIcon.style.display = 'none';
                };
                reader.readAsDataURL(file);
            }
        });
    }
});

function konfirmasiPesanan() {
    try {
        localStorage.setItem('sudahPesan', 'true');
        sessionStorage.removeItem('cart');
        localStorage.removeItem('ringkasanPesanan');
        localStorage.removeItem('detailPesanan');
    } catch (err) { /* abaikan */ }

    alert('Pesanan berhasil dikonfirmasi');
    window.location.href = 'riwayat.php';
}

alert('Pesanan berhasil dikonfirmasi');
window.location.href = 'riwayat.php'
}

// drop logout dashboard admin

function toggleLogout(event) {
    event.preventDefault();
    const logoutBtn = document.getElementById('logoutMenu');
    if(logoutBtn) {
        logoutBtn.classList.toggle('show')
    }
}

document.addEventListener('click', function(event) {
    const profileMenu = document.querySelector('.profile-menu');
    const logoutBtn = document.getElementById('logoutMenu');

    if (profileMenu && !profileMenu.contains(event.target)) {
        if(logoutBtn) {
            logoutBtn.classList.remove('show');
        }
    }
});
