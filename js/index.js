document.addEventListener("DOMContentLoaded", function () {
    const form = document.querySelector("form");
    if (!form) return;

    const selectRuangan = form.querySelector('[name="id_ruangan"]');
    const inputTanggal = form.querySelector('[name="tanggal"]');
    const inputMulai = form.querySelector('[name="jam_mulai"]');
    const inputSelesai = form.querySelector('[name="jam_selesai"]');
    const inputPeserta = form.querySelector('[name="jumlah_peserta"]');
    const inputKeperluan = form.querySelector('[name="keperluan"]');

    const hariIni = new Date().toISOString().split("T")[0];
    if (inputTanggal) {
        inputTanggal.setAttribute("min", hariIni);
    }

    // Buat teks info kapasitas otomatis di bawah input peserta
    let infoKapasitas = null;
    if (inputPeserta) {
        infoKapasitas = document.createElement("small");
        infoKapasitas.style.display = "block";
        infoKapasitas.style.marginTop = "4px";
        infoKapasitas.style.color = "#243b53";
        inputPeserta.insertAdjacentElement("afterend", infoKapasitas);
    }

    // Buat teks info durasi otomatis di bawah input jam selesai
    let infoDurasi = null;
    if (inputSelesai) {
        infoDurasi = document.createElement("small");
        infoDurasi.style.display = "block";
        infoDurasi.style.marginTop = "4px";
        inputSelesai.insertAdjacentElement("afterend", infoDurasi);
    }

    // Fungsi membaca kapasitas maksimal ruangan
    function ambilMaksKapasitas() {
        if (selectRuangan && selectRuangan.selectedIndex >= 0) {
            const teksOpsi = selectRuangan.options[selectRuangan.selectedIndex].text;
            const cocok = teksOpsi.match(/\((\d+)\s*orang\)/i);
            if (cocok) return parseInt(cocok[1], 10);
        }
        if (inputPeserta && inputPeserta.hasAttribute("max")) {
            return parseInt(inputPeserta.getAttribute("max"), 10);
        }
        return null;
    }

    function perbaruiInfoKapasitas() {
        if (!inputPeserta || !infoKapasitas) return;
        const maks = ambilMaksKapasitas();
        if (maks) {
            inputPeserta.setAttribute("max", maks);
            infoKapasitas.textContent = "Kapasitas maksimal ruangan ini: " + maks + " orang.";
        } else {
            inputPeserta.removeAttribute("max");
            infoKapasitas.textContent = "";
        }
    }

    if (selectRuangan) {
        selectRuangan.addEventListener("change", perbaruiInfoKapasitas);
    }
    // Jalankan juga saat halaman pertama kali dibuka (pinjam & edit)
    perbaruiInfoKapasitas();

    // Hitung durasi peminjaman secara real-time
    function hitungDurasi() {
        if (!inputMulai || !inputSelesai || !infoDurasi) return;
        if (!inputMulai.value || !inputSelesai.value) {
            infoDurasi.textContent = "";
            return;
        }

        const [jamM, menitM] = inputMulai.value.split(":").map(Number);
        const [jamS, menitS] = inputSelesai.value.split(":").map(Number);
        const selisihMenit = (jamS * 60 + menitS) - (jamM * 60 + menitM);

        if (selisihMenit <= 0) {
            infoDurasi.style.color = "#b91c1c";
            infoDurasi.textContent = "Perhatian: Jam selesai harus lebih akhir dari jam mulai.";
        } else {
            const jam = Math.floor(selisihMenit / 60);
            const menit = selisihMenit % 60;
            infoDurasi.style.color = "#15803d";
            infoDurasi.textContent = "Durasi peminjaman: " + (jam > 0 ? jam + " jam " : "") + (menit > 0 ? menit + " menit" : "");
        }
    }

    if (inputMulai) inputMulai.addEventListener("change", hitungDurasi);
    if (inputSelesai) inputSelesai.addEventListener("change", hitungDurasi);
    hitungDurasi();

    // Validasi saat tombol submit ditekan
    form.addEventListener("submit", function (event) {
        if (selectRuangan && !selectRuangan.value) {
            event.preventDefault();
            alert("Silakan pilih ruangan terlebih dahulu!");
            selectRuangan.focus();
            return;
        }

        if (inputTanggal && inputTanggal.value < hariIni) {
            event.preventDefault();
            alert("Tanggal peminjaman tidak boleh di masa lalu!");
            inputTanggal.focus();
            return;
        }

        if (inputMulai && inputSelesai && inputMulai.value >= inputSelesai.value) {
            event.preventDefault();
            alert("Jam selesai harus lebih besar dari jam mulai!");
            inputSelesai.focus();
            return;
        }

        if (inputKeperluan && inputKeperluan.value.trim() === "") {
            event.preventDefault();
            alert("Kolom keperluan tidak boleh kosong!");
            inputKeperluan.focus();
            return;
        }

        if (inputPeserta) {
            const jumlah = parseInt(inputPeserta.value, 10);
            const maks = ambilMaksKapasitas();
            if (isNaN(jumlah) || jumlah < 1) {
                event.preventDefault();
                alert("Jumlah peserta minimal 1 orang!");
                inputPeserta.focus();
                return;
            }
            if (maks && jumlah > maks) {
                event.preventDefault();
                alert("Jumlah peserta (" + jumlah + " orang) melebihi kapasitas ruangan (" + maks + " orang)!");
                inputPeserta.focus();
                return;
            }
        }
    });
});