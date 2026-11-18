// Menunggu seluruh halaman HTML selesai dimuat
document.addEventListener("DOMContentLoaded", function () {
    const formPeminjaman = document.getElementById("form_peminjaman");

    if (formPeminjaman) {
        formPeminjaman.addEventListener("submit", function (event) {
            // 1. Ambil semua nilai berdasarkan kolom tabel 'peminjaman'
            const selectRuangan = document.getElementById("id_ruangan");
            const tanggal = document.getElementById("tanggal").value;
            const jamMulai = document.getElementById("jam_mulai").value;
            const jamSelesai = document.getElementById("jam_selesai").value;
            const jumlahPeserta = parseInt(document.getElementById("jumlah_peserta").value);
            const keperluan = document.getElementById("keperluan").value.trim();

            // Ambil tanggal hari ini format YYYY-MM-DD
            const hariIni = new Date().toISOString().split("T")[0];

            // 2. Cek kolom kosong
            if (!selectRuangan.value || !tanggal || !jamMulai || !jamSelesai || !jumlahPeserta || !keperluan) {
                event.preventDefault();
                alert("Semua kolom wajib diisi sebelum mengajukan peminjaman!");
                return;
            }

            // 3. Validasi tanggal (tidak boleh memilih hari yang sudah lewat)
            if (tanggal < hariIni) {
                event.preventDefault();
                alert("Tanggal peminjaman tidak boleh di masa lalu!");
                return;
            }

            // 4. Validasi jam (jam_selesai harus lebih besar dari jam_mulai)
            if (jamMulai >= jamSelesai) {
                event.preventDefault();
                alert("Jam selesai harus lebih akhir dari jam mulai!");
                return;
            }

            // 5. Validasi kapasitas ruangan (mengambil atribut data-kapasitas dari option ruangan)
            const opsiTerpilih = selectRuangan.options[selectRuangan.selectedIndex];
            const maksKapasitas = parseInt(opsiTerpilih.getAttribute("data-kapasitas"));

            if (maksKapasitas && jumlahPeserta > maksKapasitas) {
                event.preventDefault();
                alert("Jumlah peserta (" + jumlahPeserta + " orang) melebihi kapasitas maksimal ruangan ini (" + maksKapasitas + " orang)!");
                return;
            }
        });
    }
});