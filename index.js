<!DOCTYPE html>
<html>
<head>
    <title>Latihan Satpam Waktu</title>
</head>
<body>
    <h3>Form Peminjaman Waktu</h3>
    
    <!-- Bagian HTML -->
    <label>Jam Mulai:</label>
    <input type="time" id="jam_mulai">
    <br><br>

    <label>Jam Selesai:</label>
    <input type="time" id="jam_selesai">
    <br><br>

    <button id="btn_cek">Cek Jadwal</button>

    <!-- Bagian JavaScript (Tugasmu) -->
    <script>
        // 1. TANGKAP tombolnya
        let tombol = document.getElementById("btn_cek");

        // 2. TUNGGU tombol diklik
        tombol.addEventListener("click", function() {
            
            // 3. AMBIL nilai dari kedua kotak jam
            let waktuMulai = document.getElementById("jam_mulai").value;
            let waktuSelesai = document.getElementById("jam_selesai").value;

            // Kita pastikan dulu user beneran ngisi dua-duanya
            if (waktuMulai === "" || waktuSelesai === "") {
                alert("Tolong isi jam mulai dan jam selesai dulu!");
                return; // 'return' artinya hentikan kode sampai di sini, jangan lanjut ke bawah
            }

            // 4. REAKSI: Logika membandingkan waktu
            // Kita pakai tanda >= (lebih besar atau sama dengan)
            if (waktuMulai >= waktuSelesai) {
                alert("Error: Jam selesai tidak boleh sama atau lebih awal dari jam mulai!");
            } else {
                alert("Sip! Jadwal masuk akal. Jam " + waktuMulai + " sampai " + waktuSelesai);
            }
        });
    </script>
</body>
</html>