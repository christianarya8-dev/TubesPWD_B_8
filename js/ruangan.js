document.addEventListener("DOMContentLoaded", function () {
    const tabelRuangan = document.querySelector("table");

    // BAGIAN A: Tambahkan kotak pencarian live di halaman daftar_ruangan.php
    if (tabelRuangan && window.location.pathname.includes("daftar_ruangan.php") && !document.getElementById("kotak_cari_ruangan")) {
        const kotakCari = document.createElement("input");
        kotakCari.type = "text";
        kotakCari.id = "kotak_cari_ruangan";
        kotakCari.placeholder = "Cari kode ruangan, nama ruangan, atau gedung...";
        kotakCari.style.width = "100%";
        kotakCari.style.maxWidth = "400px";
        kotakCari.style.padding = "10px";
        kotakCari.style.marginBottom = "15px";
        kotakCari.style.boxSizing = "border-box";

        tabelRuangan.insertAdjacentElement("beforebegin", kotakCari);

        kotakCari.addEventListener("keyup", function () {
            const keyword = kotakCari.value.toLowerCase().trim();
            const baris = tabelRuangan.querySelectorAll("tbody tr");

            baris.forEach(function (row) {
                if (row.cells.length === 1) return;
                const teks = row.textContent.toLowerCase();
                row.style.display = teks.includes(keyword) ? "" : "none";
            });
        });
    }

    // BAGIAN B: Validasi form tambah_ruangan.php & edit_ruangan.php
    const formRuangan = document.querySelector('form[action*="ruangan.php"]');
    if (formRuangan && !formRuangan.getAttribute("action").includes("hapus_ruangan")) {
        const inputKode = formRuangan.querySelector('[name="kode_ruangan"]');
        const inputNama = formRuangan.querySelector('[name="nama_ruangan"]');
        const inputKapasitas = formRuangan.querySelector('[name="kapasitas"]');

        if (inputKode) {
            inputKode.addEventListener("input", function () {
                inputKode.value = inputKode.value.toUpperCase();
            });
        }

        formRuangan.addEventListener("submit", function (event) {
            if (inputKode && inputKode.value.trim() === "") {
                event.preventDefault();
                alert("Kode ruangan wajib diisi!");
                inputKode.focus();
                return;
            }

            if (inputNama && inputNama.value.trim() === "") {
                event.preventDefault();
                alert("Nama ruangan wajib diisi!");
                inputNama.focus();
                return;
            }

            if (inputKapasitas) {
                const kap = parseInt(inputKapasitas.value, 10);
                if (isNaN(kap) || kap < 1) {
                    event.preventDefault();
                    alert("Kapasitas ruangan minimal 1 orang!");
                    inputKapasitas.focus();
                    return;
                }
            }
        });
    }
});