# Alur aplikasi dan database

## Alur normal

Admin membuat paket → Satria yang dipilih memeriksa → pemeriksaan lengkap → Admin menyetujui → Admin menugaskan → Satria menerima → berangkat → tiba → penerimaan tercatat → Pembeli mengonfirmasi.

Satria sebagai pemeriksa dapat mengakses paket sebelum mendapat tugas pengantaran. Penunjukan pemeriksa bukan izin mengantar. Persetujuan Admin tetap wajib, termasuk setelah pemeriksaan ulang.

## Alur kendala

Satria/Pembeli melapor kendala saat perjalanan → shipment ditahan dalam transaksi yang sama → pesan masuk antrean → Admin memilih retur, pemeriksaan ulang, atau lanjut untuk kendala non-integritas → Satria mengikuti instruksi → hasil akhir masuk riwayat.

Untuk segel, kemasan, suhu, dan label, pilihan lanjut langsung diblokir server. Pemeriksaan ulang membatalkan penugasan lama dan menuntut pemeriksaan, persetujuan, serta penugasan baru. Retur hanya dapat diselesaikan Satria yang menerima tugas. Admin tidak memalsukan laporan lapangan.

## Folder dan hubungan file

| Folder / file | Peran |
|---|---|
| frontend/main.ts, App.vue | Memasang Vue Router, global error fallback |
| frontend/router.ts | URL SPA, session guard, admin-only routes, fokus setelah navigasi |
| frontend/lib/api.ts | Fetch timeout, session + CSRF, error Indonesia; password tidak masuk URL |
| frontend/composables/useResource.ts | Loading/error/data; membatalkan request lama dan saat halaman ditutup |
| frontend/lib/presentation.ts | Nama tahap, status non-warna, tanggal WIB, penjelasan langkah berikutnya |
| frontend/components | Layout bersama, logo, tabel, status, state loader, tindakan berizin |
| frontend/pages | Masuk, dashboard, daftar, detail, pembuatan, notifikasi, laporan, 404 |
| frontend/styles.css | Token tunggal dan responsive 375/768/1440, focus, motion dan reduced-motion |
| backend/routes/web.php | API pada middleware web Laravel supaya session dan CSRF tetap aktif |
| backend/app/Http/Controllers/PharmaController.php | Validasi, query sesuai peran, input/output API, export |
| backend/app/Services/WorkflowService.php | Gate transisi dan pelaku, transaksi, lockForUpdate, version check, audit |
| backend/app/Services/MessageService.php | Outbox, tujuan notifikasi, Telegram, retry dan status keberhasilan sebenarnya |
| backend/app/Services/AutomationService.php | Pengingat penugasan yang belum dijawab selama 15 menit |
| backend/app/Services/ReportService.php | Snapshot harian, idempotensi per tanggal, pesan laporan Admin |
| backend/app/Http/Controllers/WebhookController.php | HMAC automation dan secret Telegram webhook, receipt dedupe |
| backend/routes/console.php | Command worker, reminder, laporan, jadwal otomatis |
| backend/database/migrations | Struktur relational dan index |
| backend/database/seeders | Akun berperan dan 32 paket dummy; seed tidak menghapus data |
| backend/config/pharma.php | Konfigurasi server-only; tidak mengirim token ke frontend |
| tools/setup.mjs, init-db.php | Membuat database bernama khusus, environment privat, migrasi dan seed |
| tools/dev.mjs, run.bat | Menjalankan Vite, API Laravel dan scheduler; tidak membunuh server proyek lain |
| android/, capacitor.config.ts | Kemasan hybrid Android; konfigurasi jaringan demo lokal bukan konfigurasi produksi |
| tests/unit, tests/e2e, backend/tests | Test UI helpers, browser nyata, dan semua gate backend |
| public/ | Logo asli, font lokal, ilustrasi operasional yang digunakan kembali dari prototype capstone |
| output/ | Bukti hasil tes dan screenshot; bukan database operasional |
| .private/, backend/.env | Credential lokal; dilarang masuk Git |

## Tabel dan relasi

users (1) → shipments (N) melalui buyer_id, inspector_id, created_by.
shipments (1) → inspections, approvals, assignments, issues, events, messages (N).
inspections (1) → approvals (N), approval selalu menunjuk hasil pemeriksaan.
issues (1) → decisions (N), dengan pelaku Admin.
events (1) → messages (N), setiap perubahan memiliki audience berbeda.
users (1) → assignments (N) melalui courier_id / assigned_by.
reports menyimpan snapshot harian dengan revision dan last_event_id. Pembaruan baru membuat revisi; snapshot lama tetap utuh.
webhook_receipts menyimpan event eksternal yang pernah diproses, unique(source, external_id).

Core index: tracking_number unique, (buyer_id,status), shipment_id+created_at events, (courier_id,status) assignments, dedupe_key unique messages, (report_date,revision) unique reports. Tidak menyimpan isi medis pasien atau token bot di tabel bisnis.

## Keamanan dan konsistensi

Session HttpOnly dan CSRF wajib untuk mutation. Backend menentukan role dari users, tidak dari pilihan form masuk. Pembeli melihat paket miliknya; Satria melihat pemeriksaan/tugas yang terkait dengannya; Admin melihat operasi Pharma. Transaksi dan row lock menjaga state bersama audit dan outbox, sedangkan version menolak halaman yang sudah basi (409). Nama pelaku berasal dari akun server.

CSV menetralkan prefix formula. Pesan tidak membawa kata sandi, token, atau diagnosis. Provider error dicatat tanpa exception URL yang dapat memuat token bot. API key hanya di backend/.env.

## Otomatisasi yang jujur

Outbox membuat request pengguna tidak perlu menunggu Telegram. Scheduler mengirim per menit, pengingat per 5 menit, laporan pada 17.00 WIB. Pesan tanpa credential berstatus unconfigured. Respons provider ok=true menyimpan sent_at dan provider_message_id. Error koneksi/rate limit dicoba ulang, maksimum 5 kali lalu membutuhkan tindakan Admin. Ada kemungkinan duplikat bila proses mati sesudah provider menerima sebelum status lokal tersimpan: Bot API tidak menyediakan idempotency key. Deduplikasi lokal tidak boleh diklaim exactly-once di jaringan.

## Batas runtime

Local database yang tersedia di laptop adalah MariaDB 10.4.32 lewat XAMPP, memakai driver mysql Laravel. Struktur dan API SQL kompatibel MySQL; bukan mengaku binary Oracle MySQL sedang digunakan. Migrasi tidak menyentuh database capstone Supabase maupun database lama. Pengujian unit backend memakai SQLite in-memory, sedangkan browser E2E memakai database lokal mysql-compatible sebenarnya.
