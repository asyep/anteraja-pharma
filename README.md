# Anteraja Pharma

Pantau pemeriksaan paket kesehatan, persetujuan Admin, tugas Satria, dan tindak lanjut kendala dalam satu alur yang tercatat.

Aplikasi utama adalah web app Vue + Laravel: ketiga peran dapat membuka aplikasi lewat browser desktop maupun HP. APK Android hanya kemasan tambahan untuk demo hybrid; tidak wajib dipasang untuk menggunakan web.

## Yang bisa dijalankan

Versi cloud: [anteraja-pharma.vercel.app](https://anteraja-pharma.vercel.app). Arsitektur, credential dan batas scheduler gratis dijelaskan di [Pharma di cloud](docs/CLOUD.md).

- **Admin:** membuat kiriman, memeriksa, menyetujui/menahan, menugaskan, memutuskan kendala, melihat laporan dan riwayat pesan.
- **Satria:** memeriksa paket sebagai petugas terpilih, menerima/menolak tugas, memperbarui perjalanan, melaporkan masalah, dan mencatat hasil retur/penerimaan.
- **Pembeli:** melihat paket miliknya, memberikan kesiapan penerimaan, melaporkan kendala, dan mengonfirmasi paket yang sudah diterima.
- **Orkestrasi:** checklist tidak lengkap memblokir keberangkatan. Segel bermasalah otomatis menahan paket. Admin menentukan langkah berikutnya dengan alasan.
- **Otomatisasi:** outbox Telegram, pengingat tugas, webhook terautentikasi, laporan harian dan export audit CSV.

## Mulai di laptop ini

Node 22.12+, PHP 8.2+ dengan PDO mysql, Composer, dan database MySQL-compatible diperlukan. Runtime yang diuji: PHP XAMPP 8.2.12, MariaDB 10.4.32, Vue 3, Laravel 12, Vite 7. Database baru bernama `anteraja_pharma_hackathon`, terpisah dari capstone lama.

Aktifkan MySQL dari XAMPP, kemudian:

```powershell
cd "D:\Work & Organization\Work\Maxy Academy\Modules\Hackathon\anteraja-pharma"
npm run dev
```

Buka [http://localhost:3170](http://localhost:3170). `run.bat` menjalankan perintah yang sama. Launcher menjalankan Vite, Laravel pada 8170, dan scheduler. Ctrl+C menghentikan proses launcher, tidak menghapus database.

Akun dan kata sandi lokal ada di `.private/akun-demo.txt`, tidak masuk repository. Tombol peran pada halaman masuk mengisi email saja; server tetap menentukan hak akses.

Untuk checkout baru: `npm ci`, jalankan `composer install` di backend dengan PHP 8.2+, aktifkan MySQL, lalu `npm run setup` dari root. Setup membuat `.env` privat, database khusus, migrasi dan 32 kiriman dummy. Setup tidak menghapus data yang sudah ada.

## Konfigurasi

| Variabel backend/.env | Kegunaan | Wajib |
|---|---|---|
| APP_KEY | Enkripsi Laravel, dibuat otomatis oleh setup | Ya |
| DB_CONNECTION, DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD | Database khusus Pharma; driver mysql | Ya |
| APP_URL, FRONTEND_URL | Alamat API dan tautan balik aplikasi | Ya |
| DEMO_PASSWORD | Kata sandi akun dummy ketika seed | Untuk seed |
| SESSION_DRIVER, SESSION_COOKIE, SESSION_SAME_SITE | Session file lokal, cookie Pharma terpisah | Ya |
| CACHE_STORE, QUEUE_CONNECTION, LOG_CHANNEL | Cache lokal, konfigurasi antrean dan logging Laravel | Ya |
| TELEGRAM_BOT_TOKEN | Token BotFather, hanya server | Untuk Telegram nyata |
| TELEGRAM_ADMIN_CHAT_ID, TELEGRAM_COURIER_CHAT_ID, TELEGRAM_BUYER_CHAT_ID, TELEGRAM_SENDER_CHAT_ID | Tujuan pesan akun uji | Untuk pesan tiap audience |
| TELEGRAM_WEBHOOK_SECRET | Header secret webhook Telegram | Untuk webhook Telegram |
| AUTOMATION_WEBHOOK_SECRET | HMAC endpoint automation | Untuk webhook automation |

Semua nilai privat di `.env`, jangan masukkan token ke Vue, Git atau screenshot. Template kosong tersedia di backend/.env.example. Pembatasan koneksi dan cara memasang bot ada di [panduan Telegram](docs/TELEGRAM.md).

## Verifikasi

```powershell
npm run typecheck
npm run lint
npm run test
npm run build
npm run test:e2e
& C:\xampp\php\php.exe backend/vendor/phpunit/phpunit/phpunit -c backend/phpunit.xml --bootstrap backend/vendor/autoload.php
```

Tes browser membutuhkan server/database aktif, memakai Chrome dan membuat paket dummy baru. Test backend memakai SQLite in-memory sehingga tidak menghapus database demo. Bootstrap eksplisit menghindari masalah path Windows dengan tanda `&` pada runner bawaan.

## Dokumen tim

- [Pemetaan setiap requirement resmi](docs/REQUIREMENTS.md)
- [Arsitektur, tabel, file dan alur](docs/ARCHITECTURE.md)
- [Skenario demo 45 menit dan Q&A](docs/DEMO.md)
- [Pemasangan Telegram](docs/TELEGRAM.md)
- [Hybrid Android dan batas demo lokal](docs/MOBILE.md)
- [Hasil pengujian aktual](docs/QA.md)
- [Rencana implementasi dan sumber riset](docs/IMPLEMENTATION.md)

## Batas penggunaan

Prototype hackathon dengan data fiktif, bukan sistem medis produksi atau bukti sertifikasi CDOB. Suhu dicatat manual sesuai instruksi per paket, bukan sensor. Pesan Telegram nyata menunggu credential pengguna; status `sent` hanya muncul setelah provider mengonfirmasi. Demo/judging resmi merupakan kegiatan manusia yang belum berlangsung.

Logo Anteraja berasal dari aset project sebelumnya. Descriptor Pharma membedakan produk prototype; tidak mengaku sebagai lockup logo resmi baru. Materi official berada di folder Material di luar aplikasi, sedangkan BMC dan ALUR dipakai sebagai referensi saja.

Kode aplikasi disiapkan untuk latihan kelompok. Identitas dan aset Anteraja tetap milik pemegang merek, bukan dilisensikan ulang oleh repository ini.
