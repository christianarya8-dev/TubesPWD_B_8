// 1. TANGKAP tombolnya dari halaman HTML/PHP
let tombol = document.getElementById("btn_cek");

// Cek dulu apakah tombolnya ada di halaman yang sedang dibuka
if (tombol) {
    // 2. TUNGGU tombol diklik
    tombol.addEventListener("click", function() {
        
        // 3. AMBIL nilai dari kedua kotak jam
        let waktuMulai = document.getElementById("jam_mulai").value;
        let waktuSelesai = document.getElementById("jam_selesai").value;

        // Pastikan kedua kolom sudah diisi
        if (waktuMulai === "" || waktuSelesai === "") {
            alert("Tolong isi jam mulai dan jam selesai dulu!");
            return;
        }

        // 4. REAKSI: Cek apakah jam selesai mendahului jam mulai
        if (waktuMulai >= waktuSelesai) {
            alert("Error: Jam selesai tidak boleh sama atau lebih awal dari jam mulai!");
        } else {
            alert("Sip! Jadwal masuk akal. Jam " + waktuMulai + " sampai " + waktuSelesai);
        }
    });
}