document.addEventListener("DOMContentLoaded", function () {
    const form = document.querySelector("form");
    if (!form) return;

    const inputNama = form.querySelector('[name="nama_lengkap"]');
    const inputEmail = form.querySelector('[name="email"]');
    const inputSandi = form.querySelector('[name="kata_sandi"]');
    const inputKonfirmasi = form.querySelector('[name="konfirmasi_sandi"]');
    const inputIdentitas = form.querySelector('[name="nomor_identitas"]');
    const inputTelepon = form.querySelector('[name="no_telepon"]');

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