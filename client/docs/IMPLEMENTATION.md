# Anteraja Pharma — rencana dan batas produk

Sumber aturan: `../Material/Study Case Hackathon Anteraja.pdf`, seluruh 16 halaman dibaca. DOCX, BMC.jpeg, dan ALUR.jpeg hanya referensi. Aplikasi dibangun terpisah dari capstone agar data dan fitur project sebelumnya tidak berubah.

## Masalah, pengguna, dan ukuran keberhasilan

Paket kesehatan tidak boleh melanjutkan perjalanan sebelum kemasan, segel, dan label diperiksa serta disetujui. Ketika ada kendala, kurir perlu instruksi yang jelas; Admin perlu bukti dan alasan keputusan; pembeli perlu tahu apa yang sedang terjadi tanpa membaca istilah internal.

Tiga peran: Admin (membuat, menyetujui, menugaskan, memutuskan, melaporkan); Satria (memeriksa paket sebagai petugas, menerima/menolak tugas, memperbarui perjalanan, melaporkan kendala); Pembeli (melihat paket miliknya, kesiapan penerimaan, konfirmasi dan kendala).

Ukuran utama: alur wajib checklist belum lengkap → pengiriman ditolak → checklist lengkap → Admin menyetujui → penugasan diterima → segel rusak → otomatis ditahan → Admin memilih retur → retur tercatat dalam laporan. Alur normal, penolakan tugas, menunggu respons, dan pengecekan ulang juga harus berjalan.

## Arsitektur dan urutan kerja

1. Backend Laravel 12 / PHP 8.2, database khusus MySQL-compatible, autentikasi session + CSRF, otorisasi server per peran.
2. Skema normalisasi: users, shipments, inspections, approvals, assignments, issues, decisions, events, messages, reports. Riwayat append-only dan transaksi dengan row lock + version untuk menghindari keputusan ganda.
3. Orkestrasi native Laravel: aturan transisi dalam service, transactional outbox, worker pesan, reminder tugas menunggu, scheduler laporan harian. Telegram dipilih sebagai kanal resmi alternatif WhatsApp; tidak mengklaim terkirim sebelum provider mengonfirmasi.
4. Vue 3 + TypeScript + Vite, Vue Router, komponen shared dan CSS token terpusat. Header, tabel operasional, halaman detail, formulir, status, empty/loading/error; interface mobile Pembeli/Satria dan desktop Admin.
5. Capacitor untuk fondasi hybrid; build native bergantung ketersediaan Android SDK/JDK. Kesiapan kemasan mobile tidak boleh dilaporkan sebagai APK teruji apabila belum dibangun.
6. Uji backend, alur browser end-to-end, mobile/tablet/desktop, keyboard, reduced motion, error jaringan, dan pengiriman Telegram nyata setelah credential tersedia.
7. Dokumentasi menjalankan, audit requirements, skenario demo 45 menit, bahan presentasi dan bukti hasil aktual.

## Arah visual

Magenta Anteraja untuk aksi utama dan identitas; teal untuk pemeriksaan yang lolos; amber untuk menunggu; merah untuk kendala. Surface terang hangat, teks gelap yang lembut, logo Anteraja asli dan descriptor Pharma (bukan mengaku logo Pharma resmi). Font Open Sans lokal dari aset capstone. Tabel/list tetap menjadi pusat pekerjaan Admin. Motion singkat hanya membantu feedback dan perubahan konteks, dengan prefers-reduced-motion.

## Batas dan konflik referensi

- Official menyatakan Admin membuat dan menugaskan; diagram referensi menyatakan customer membuat dan sistem auto-assign. Ikuti official.
- Rentang suhu tidak dibuat universal 2–8°C; instruksi mengikuti paket. Tidak ada sensor/GPS nyata atau klaim sertifikasi CDOB.
- Ini prototype hackathon dengan data fiktif, bukan layanan medis atau sistem produksi bersertifikasi.
- Nomor kelompok Pharma berbeda antara halaman 2 dan 15. Jadwal resmi harus dikonfirmasi kepada panitia; jangan mengubah substansi alur karena nomor kelompok.
- Review/presentasi/penilaian pada Day 4 merupakan kegiatan manusia mendatang, bukan hasil yang dapat dibuat-buat oleh aplikasi.

## Riset primer

- https://anteraja.id/id/services — Pharma: distribusi obat dan alat kesehatan dengan penanganan khusus.
- https://anteraja.id/id/business-solution — layanan bisnis Anteraja.
- https://www.linkedin.com/posts/anteraja_demi-memperluas-pasar-di-bidang-pharma-ceo-activity-7108312113181331456-j35B — kerja sama distribusi farmasi.
- https://peraturan.bpk.go.id/Download/388087/peraturan-bpom-no-20-tahun-2025.pdf — Standar CDOB 2025; acuan prinsip traceability, bukan klaim sertifikasi.
- https://core.telegram.org/bots/api — pesan, status provider, dan secret webhook.
- https://laravel.com/framework/docs/12.x/installation dan https://vuejs.org/guide/ — dokumentasi stack.
