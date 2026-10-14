# Anteraja Pharma

Prototipe alur logistik farmasi berdasarkan kebutuhan di [`docs/`](./docs): pengiriman dibuat, diperiksa Checker, disetujui Admin, ditugaskan ke Kurir, dilacak selama perjalanan, lalu ditutup dengan laporan.

## Struktur proyek

- `client/`: aplikasi web Vue 3, TypeScript, dan Vite.
- `server/`: API Laravel 12, autentikasi, aturan workflow, telemetry, evaluasi otomatis, serta laporan.
- `docs/`: PRD dan functional requirements yang menjadi acuan scope.

## Menjalankan aplikasi lokal

Persyaratan: Node.js 22+, PHP 8.2+ dengan ekstensi `pdo_pgsql`, Composer, dan PostgreSQL.

1. Pasang dependency frontend dan backend:

   ```powershell
   cd client
   npm ci
   cd ..\server
   composer install
   ```

2. Siapkan `server/.env` berdasarkan `server/.env.example`. Atur host, port, nama database, role, dan kata sandi PostgreSQL. Dari `client/`, jalankan `npm run setup` untuk membuat database khusus Pharma bila belum ada, membuat `APP_KEY`/password demo yang belum diisi, menjalankan migrasi, dan seed data demo. Password demo tersimpan privat di `client/.private/akun-demo.txt`. Jangan gunakan database proyek lain atau menjalankan `migrate:fresh` pada database yang berisi data yang ingin dipertahankan.
3. Untuk fitur ringkasan insiden berbantuan OpenAI, atur `OPENAI_API_KEY` dan `OPENAI_MODEL` hanya di `server/.env`. Key tidak boleh dimasukkan ke frontend atau repository. Integrasi AI hanya merangkum insiden; keputusan aman/penyimpangan berasal dari aturan suhu deterministik.
4. Kembali ke `client/` dan jalankan:

   ```powershell
   cd ..\client
   npm run dev
   ```

   Launcher menjalankan web app, API, dan scheduler. Buka `http://localhost:3170`. PHP CLI diambil dari `PATH` (atau `PHP_BINARY` jika path tersebut valid); jika port API `8170` sudah dipakai, launcher memilih port tersedia berikutnya hingga `8190`.

PRD dan functional requirements yang menjadi acuan alur ada di `docs/`.

## Alur dan peran

- **Admin:** membuat pengiriman, meninjau hasil pemeriksaan, menyetujui, menugaskan Kurir, menangani kendala, dan melihat laporan.
- **Checker:** memeriksa kondisi barang dan kemasan serta mengunggah foto bukti; checklist atau suhu di luar rentang dapat memblokir persetujuan/pemberangkatan.
- **Kurir:** menerima tugas, mencatat keberangkatan/perjalanan, mengirim telemetry lokasi dan suhu, serta melaporkan kendala dan hasil pengantaran/retur.
- **Vendor / Customer:** memantau pengiriman, menyampaikan kesiapan atau kendala, dan mengonfirmasi penerimaan.

## Verifikasi

Frontend dari `client/`:

```powershell
npm test
npm run lint
npm run build
```

Feature tests backend dari `server/`:

```powershell
php vendor/phpunit/phpunit/phpunit -c phpunit.xml --bootstrap vendor/autoload.php --testsuite Feature
```

## Batas prototipe

Telemetry GPS otomatis dikirim oleh web app saat halaman detail aktif; pelacakan background dan integrasi sensor suhu belum tersedia. Suhu untuk paket terkendali dicatat melalui input telemetry. Lokasi ditampilkan sebagai koordinat dan riwayat titik, belum sebagai peta interaktif. Laporan akhir dapat dicetak melalui browser, belum dibuat sebagai PDF server-side. Sistem ini prototipe dan bukan perangkat medis atau bukti sertifikasi CDOB.
