document.addEventListener("DOMContentLoaded", function () {
    const form = document.querySelector("form");
    if (!form) return;

    const inputNama = form.querySelector('[name="nama_lengkap"]');
    const inputEmail = form.querySelector('[name="email"]');
    const inputSandi = form.querySelector('[name="kata_sandi"]');
    const inputKonfirmasi = form.querySelector('[name="konfirmasi_sandi"]');
    const inputIdentitas = form.querySelector('[name="nomor_identitas"]');
    const inputTelepon = form.querySelector('[name="no_telepon"]');

    
    // Validasi input email
    const statusEmailEl = document.getElementById("status-email");
    let emailSudahDipakai = false;
    let timerCekEmail = null;
    let emailTerakhirDicek = "";

    function perbaruiStatusEmail(status, teks) {
        if (!statusEmailEl || !inputEmail) return;
        statusEmailEl.textContent = teks;
        statusEmailEl.className = "status-email" + (status ? " " + status : "");
        inputEmail.classList.remove("input-error", "input-sukses");
        if (status === "terpakai") {
            inputEmail.classList.add("input-error");
        } else if (status === "tersedia") {
            inputEmail.classList.add("input-sukses");
        }
    }

    async function cekKetersediaanEmail(emailRaw) {
        const emailBersih = emailRaw.trim().toLowerCase();
        if (!statusEmailEl) return;

        if (emailBersih === "") {
            emailSudahDipakai = false;
            emailTerakhirDicek = "";
            perbaruiStatusEmail("", "");
            return;
        }

        const polaEmail = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!polaEmail.test(emailBersih)) {
            emailSudahDipakai = false;
            perbaruiStatusEmail("terpakai", "Format email belum valid.");
            return;
        }

        if (emailBersih === emailTerakhirDicek) return;
        emailTerakhirDicek = emailBersih;

        perbaruiStatusEmail("memeriksa", "Memeriksa ketersediaan email...");

        try {
            const respons = await fetch("cek_email.php?email=" + encodeURIComponent(emailBersih));
            if (!respons.ok) return;
            const hasil = await respons.json();

            if (inputEmail.value.trim().toLowerCase() !== emailBersih) return;

            if (!hasil.valid) {
                emailSudahDipakai = false;
                perbaruiStatusEmail("terpakai", hasil.pesan || "Format email tidak valid.");
            } else if (!hasil.tersedia) {
                emailSudahDipakai = true;
                perbaruiStatusEmail("terpakai", hasil.pesan || "Email sudah terdaftar.");
            } else {
                emailSudahDipakai = false;
                perbaruiStatusEmail("tersedia", hasil.pesan || "Email tersedia.");
            }
        } catch (err) {
            emailSudahDipakai = false;
            perbaruiStatusEmail("", "");
        }
    }

    if (inputEmail && statusEmailEl) {
        inputEmail.addEventListener("input", function () {
            clearTimeout(timerCekEmail);
            emailTerakhirDicek = "";
            timerCekEmail = setTimeout(function () {
                cekKetersediaanEmail(inputEmail.value);
            }, 350);
        });

        inputEmail.addEventListener("blur", function () {
            clearTimeout(timerCekEmail);
            cekKetersediaanEmail(inputEmail.value);
        });

        if (inputEmail.value.trim() !== "") {
            cekKetersediaanEmail(inputEmail.value);
        }
    }

    // Validasi input foto profil
    const previewFotoEl = document.getElementById("preview-foto-profil");
    const infoFotoEl = document.getElementById("info-foto-profil");
    const checkboxHapusFoto = document.getElementById("hapus_foto");

    if (inputFoto) {
        inputFoto.addEventListener("change", function () {
            const file = inputFoto.files && inputFoto.files[0];
            if (!file) return;

            const tipeValid = ["image/jpeg", "image/png", "image/webp"];
            if (!tipeValid.includes(file.type)) {
                alert("Format foto harus JPG, PNG, atau WEBP!");
                inputFoto.value = "";
                return;
            }

            if (file.size > 2 * 1024 * 1024) {
                alert("Ukuran foto profil maksimal 2 MB!");
                inputFoto.value = "";
                return;
            }

            if (checkboxHapusFoto) {
                checkboxHapusFoto.checked = false;
            }

            if (previewFotoEl) {
                const reader = new FileReader();
                reader.onload = function (e) {
                    previewFotoEl.src = e.target.result;
                };
                reader.readAsDataURL(file);
            }

            if (infoFotoEl) {
                const ukuranKb = Math.round(file.size / 1024);
                infoFotoEl.textContent = "Foto dipilih: " + file.name + " (" + ukuranKb + " KB) — klik Simpan Perubahan untuk menerapkan.";
            }
        });
    }

    // Tambahkan checkbox "Tampilkan Password" (cegah duplikasi dengan pengecekan ID)
    if (inputSandi && !document.getElementById("toggle_sandi")) {
        const wadahToggle = document.createElement("div");
        wadahToggle.style.marginTop = "8px";
        wadahToggle.style.fontSize = "14px";

        const checkbox = document.createElement("input");
        checkbox.type = "checkbox";
        checkbox.id = "toggle_sandi";
        checkbox.style.width = "auto";
        checkbox.style.marginRight = "6px";

        const labelToggle = document.createElement("label");
        labelToggle.htmlFor = "toggle_sandi";
        labelToggle.textContent = "Tampilkan password";
        labelToggle.style.display = "inline";
        labelToggle.style.fontWeight = "normal";
        labelToggle.style.cursor = "pointer";

        wadahToggle.appendChild(checkbox);
        wadahToggle.appendChild(labelToggle);

        const targetSisip = inputKonfirmasi || inputSandi;
        targetSisip.insertAdjacentElement("afterend", wadahToggle);

        checkbox.addEventListener("change", function () {
            const tipeBaru = checkbox.checked ? "text" : "password";
            inputSandi.type = tipeBaru;
            if (inputKonfirmasi) inputKonfirmasi.type = tipeBaru;
        });
    }

    // Validasi form sebelum submit
    form.addEventListener("submit", function (event) {
        if (inputNama && inputNama.value.trim() === "") {
            event.preventDefault();
            alert("Nama lengkap wajib diisi!");
            inputNama.focus();
            return;
        }

        if (inputEmail) {
            const emailVal = inputEmail.value.trim();
            if (emailVal === "" || !emailVal.includes("@")) {
                event.preventDefault();
                alert("Masukkan alamat email yang valid!");
                inputEmail.focus();
                return;
            }
        }

        if (inputSandi) {
            if (inputSandi.value === "") {
                event.preventDefault();
                alert("Password wajib diisi!");
                inputSandi.focus();
                return;
            }

            if (inputKonfirmasi && inputSandi.value.length < 8) {
                event.preventDefault();
                alert("Password minimal harus 8 karakter!");
                inputSandi.focus();
                return;
            }
        }

        if (inputSandi && inputKonfirmasi && inputSandi.value !== inputKonfirmasi.value) {
            event.preventDefault();
            alert("Konfirmasi password tidak cocok!");
            inputKonfirmasi.focus();
            return;
        }

        if (inputIdentitas && inputIdentitas.value.trim() !== "") {
            if (!/^[0-9]+$/.test(inputIdentitas.value.trim())) {
                event.preventDefault();
                alert("Nomor Identitas / NPM hanya boleh berisi angka!");
                inputIdentitas.focus();
                return;
            }
        }

        if (inputTelepon && inputTelepon.value.trim() !== "") {
            if (!/^[0-9+\-\s]+$/.test(inputTelepon.value.trim())) {
                event.preventDefault();
                alert("Nomor telepon hanya boleh berisi angka!");
                inputTelepon.focus();
                return;
            }
        }
    });


});