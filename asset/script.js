document.addEventListener('DOMContentLoaded', function() {
    if (localStorage.getItem('sudahPesan') === 'true') {
        const tabRiwayat = document.getElementById('tab-riwayat');
        if(tabRiwayat) {
            tabRiwayat.style.display = 'block';
        }
    }

    const fileUpload = document.getElementById('file-upload');
    const previewImage = document.getElementById('preview-image');
    const uploadIcon = document.getElementById('upload-icon');

    if (fileUpload) {
        fileUpload.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();

                reader.onload = function(event) {
                    previewImage.src = event.target.result;
                    previewImage.style.display = 'block';
                    uploadIcon.style.display = 'none';
                }

                reader.readAsDataURL(file);
            }
        });
    }
});

function konfirmasiPesanan() {
    localStorage.setItem('sudahPesan', 'true')

const tabRiwayat = document.getElementById('tab-riwayat');
if (tabRiwayat) {
    tabRiwayat.style.display = 'block'
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