document.addEventListener("DOMContentLoaded", function () {
    // 1. Konfirmasi tombol Setujui & Tolak pada admin_peminjaman.php
    document.querySelectorAll("button.setuju").forEach(function (btn) {
        btn.addEventListener("click", function (event) {
            if (!confirm("Setujui pengajuan peminjaman ruangan ini?")) {
                event.preventDefault();
            }
        });
    });

    document.querySelectorAll("button.tolak").forEach(function (btn) {
        btn.addEventListener("click", function (event) {
            if (!confirm("Yakin ingin menolak pengajuan peminjaman ini?")) {
                event.preventDefault();
            }
        });
    });

    // 2. Konfirmasi tombol Logout pada dashboard.php
    const formLogout = document.querySelector('form[action="logout.php"]');
    if (formLogout) {
        formLogout.addEventListener("submit", function (event) {
            if (!confirm("Apakah Anda yakin ingin keluar dari CampusSpace?")) {
                event.preventDefault();
            }
        });
    }

    // 3. Tambahkan filter status otomatis di atas tabel admin & riwayat peminjaman
    const tabel = document.querySelector("table");
    const path = window.location.pathname;
    if (tabel && (path.includes("admin_peminjaman.php") || path.includes("riwayat_peminjaman.php"))) {
        const wadahFilter = document.createElement("div");
        wadahFilter.style.marginBottom = "15px";

        const label = document.createElement("label");
        label.textContent = "Filter Status: ";
        label.style.fontWeight = "bold";
        label.style.marginRight = "8px";

        const selectStatus = document.createElement("select");
        selectStatus.style.padding = "8px";

        const opsiStatus = [
            { val: "semua", teks: "Semua Status" },
            { val: "pending", teks: "Pending (Menunggu)" },
            { val: "approved", teks: "Approved (Disetujui)" },
            { val: "rejected", teks: "Rejected (Ditolak)" },
            { val: "cancelled", teks: "Cancelled (Dibatalkan)" }
        ];

        opsiStatus.forEach(function (item) {
            const opt = document.createElement("option");
            opt.value = item.val;
            opt.textContent = item.teks;
            selectStatus.appendChild(opt);
        });

        wadahFilter.appendChild(label);
        wadahFilter.appendChild(selectStatus);
        tabel.insertAdjacentElement("beforebegin", wadahFilter);

        selectStatus.addEventListener("change", function () {
            const dipilih = selectStatus.value.toLowerCase();
            const baris = tabel.querySelectorAll("tbody tr");

            baris.forEach(function (row) {
                if (row.cells.length === 1) return;
                const teksRow = row.textContent.toLowerCase();
                if (dipilih === "semua" || teksRow.includes(dipilih)) {
                    row.style.display = "";
                } else {
                    row.style.display = "none";
                }
            });
        });
    }
});