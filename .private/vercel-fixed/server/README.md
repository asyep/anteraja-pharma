# Backend Anteraja Pharma

Laravel 12 + MySQL untuk workflow sesuai flowchart main. API membatasi akses berdasarkan role dan kepemilikan, bukan hanya menyembunyikan tombol frontend.

## Jalankan API

1. Siapkan PHP 8.2+, Composer dan MySQL. Aktifkan pdo_mysql, GD dan ZIP.
2. Jalankan composer install di folder ini. Salin .env.example menjadi .env, isi database khusus proyek dan konfigurasi lokal; jangan commit credential.
3. Jalankan:

   ~~~powershell
   php artisan key:generate
   php artisan migrate
   php artisan serve --host=127.0.0.1 --port=8170
   ~~~

Di terminal terpisah jalankan scheduler dan worker:

~~~powershell
php artisan schedule:work
php artisan queue:work database --sleep=1 --tries=2 --timeout=15
~~~

Jika folder saudara client tersedia, npm run setup dan npm run dev dari client menyiapkan akun demo dan menyalakan semua proses. Untuk XAMPP Windows, set PHP_INI_SCAN_DIR ke path absolut server/runtime/windows agar GD/ZIP lokal dimuat. Jangan menjalankan migrate:fresh pada data yang perlu dipertahankan.

## Role dan transisi

Admin membuat instruksi, memilih Checker, menyetujui hasil, memilih kurir aktif yang memiliki kapasitas cold-box, serta memutuskan kendala. Checker hanya memeriksa paket yang ditugaskan. Kurir menjawab tugas, memulai perjalanan, mengirim GPS/suhu, melaporkan kendala dan mencatat POD. Vendor / Customer hanya melihat paket miliknya beserta laporan.

Pemeriksaan gagal menghasilkan HOLD. Persetujuan harus merujuk pemeriksaan terakhir yang lulus. Penugasan, telemetry dan tindakan memakai transaksi/row lock; aksi memiliki versi dan request/event ID untuk retry idempoten.

Suhu di luar rentang selama sedikitnya 180 detik pada sampel berkesinambungan menghasilkan HOLD. Jeda sampel lebih dari 180 detik memutus bukti paparan. Sinyal tanpa pembaruan selama 15 menit juga menghasilkan HOLD. GPS kembali dicatat tetapi tidak melepas HOLD otomatis. Resume untuk suhu/sinyal memerlukan pembacaan baru dan suhu normal untuk paket controlled.

Serah terima paket controlled memerlukan suhu normal terbaru. Deliver/return_complete memakai foto dan nama penerima persetujuan; laporan akhir diterbitkan sekali, kemudian kiriman ditutup dan mutasi workflow ditolak.

## Berkas penting

| Berkas | Fungsi |
| --- | --- |
| routes/web.php | Session, API privat, demo, PDF, webhook dan verifikasi QR |
| app/Http/Controllers/PharmaController.php | Validasi, scoping role/owner dan respons API |
| app/Services/WorkflowService.php | Transisi, lock, versi, audit dan pemicu outbox |
| app/Services/TelemetryAutomationService.php | Sampel GPS/suhu, paparan, tren dan kehilangan sinyal |
| app/Jobs/SummarizeIncident.php | Ringkasan OpenAI opsional di database queue |
| app/Services/ProofService.php | Foto terenkripsi di DB dan unduhan privat |
| app/Services/FinalShipmentReportService.php | Snapshot final, deduplikasi dan distribusi outbox |
| app/Services/ShipmentPdfService.php | PDF branded, foto, QR dan digest |
| resources/views/reports/shipment.blade.php | Template laporan server |
| app/Services/MessageService.php | Outbox, provider Telegram, retry dan status nyata |
| app/Http/Controllers/DemoController.php | Skenario Admin, data demo dan virtual elapsed time |
| database/migrations/, database/seeders/ | Skema incremental dan akun/data contoh |

Foto serta PDF berupa ciphertext di database, tidak mengandalkan disk ephemeral Vercel. Simpan APP_KEY dengan aman; kehilangan key membuat bukti tidak dapat didekripsi. QR publik hanya mengembalikan ID laporan, waktu dan SHA-256, bukan penerima/alamat.

## Telegram: pasang nanti

Di .env server isi TELEGRAM_BOT_TOKEN dan TELEGRAM_ADMIN_CHAT_ID, TELEGRAM_CHECKER_CHAT_ID, TELEGRAM_COURIER_CHAT_ID, TELEGRAM_BUYER_CHAT_ID untuk akun uji yang sudah /start ke bot. Untuk akun yang sudah ada, pasang Chat ID lewat halaman Notifikasi/pengaturan akun. Jangan kirim token di chat atau Git.

Restart proses setelah mengubah env; jika config pernah di-cache, jalankan php artisan config:clear. Outbox disiapkan sekarang, tetapi pengiriman nyata belum terverifikasi tanpa credential. Gunakan panel demo Uji Telegram nyata setelah token siap. Status sent hanya muncul setelah Telegram mengonfirmasi; unconfigured/retry tetap terlihat sebagai kegagalan konfigurasi/pengiriman.

## AI opsional dan simulasi

OPENAI_API_KEY/OPENAI_MODEL hanya di server. Job mengirim fakta anomali, bukan nama atau alamat, dan tidak mengganti keputusan rule engine. HTTP timeout, kegagalan kuota serta respons kosong memakai alasan lokal. Worker harus berjalan; tidak ada call provider di jalur telemetry. API mengikuti [dokumentasi Chat Completions resmi](https://developers.openai.com/api/reference/resources/chat/subresources/completions/methods/create).

PHARMA_DEMO_ENABLED default false. Simulator hanya tersedia untuk Admin, environment local/testing/demo/development, dan shipment is_demo. Spike memakai waktu virtual 3 menit, sinyal putus memakai virtual 15 menit; semua diberi label simulasi dan audit. Sampel virtual tidak dicampur dengan GPS nyata dalam perhitungan paparan.

## Tes terisolasi

~~~powershell
php vendor/phpunit/phpunit/phpunit -c phpunit.xml --bootstrap vendor/autoload.php
~~~

Tes memakai SQLite in-memory dan HTTP fake, bukan MySQL/TiDB produksi. Cakupan: role, checklist, ownership, stale version, outbox, suhu, sinyal, simulasi, POD, PDF privat, closure dan fallback AI.

## Batas dan branch

PDF/A, retensi 5 tahun, uptime, sertifikasi farmasi dan keaslian tanda tangan harus divalidasi sebelum produksi. Nama persetujuan POD bukan tanda tangan digital tersertifikasi. Web belum menyediakan GPS background saat layar terkunci.

feature-server hanya memuat server/. Jangan merge snapshot langsung ke main: folder client/docs akan hilang dari tree. Integrasikan perubahan server saja ke checkout gabungan. Dokumen flowchart/PRD/FRD main tidak diubah.
