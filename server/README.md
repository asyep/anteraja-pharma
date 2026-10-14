# Backend Anteraja Pharma

API Laravel 12 untuk autentikasi, role, workflow pemeriksaan/persetujuan, penugasan, telemetry, evaluasi otomatis, audit trail, dan laporan sesuai kebutuhan di `../docs/`. Frontend Vue ada di `../client/`.

## Menyiapkan dan menjalankan

```powershell
composer install
```

Gunakan PHP CLI dari `PATH` (atau set `PHP_BINARY` ke path yang benar) dengan ekstensi `pdo_pgsql` aktif. Salin `.env.example` menjadi `.env`, isi kredensial PostgreSQL dan database khusus proyek ini, serta buat `APP_KEY`. Konfigurasi default memakai `DB_CONNECTION=pgsql` dan port `5432`. Jalankan migrasi dengan `php artisan migrate`. Jangan gunakan `migrate:fresh` pada database yang berisi data yang perlu dipertahankan.

Untuk menjalankan API saja:

```powershell
php artisan serve --host=127.0.0.1 --port=8170
```

Scheduler perlu aktif untuk mendeteksi telemetry yang terputus:

```powershell
php artisan schedule:work
```

## Konfigurasi AI

`OPENAI_API_KEY` dan `OPENAI_MODEL` opsional dan dibaca server dari `.env`. Jangan menaruh key di frontend, source control, log, atau screenshot. Evaluasi batas suhu dan penentuan status insiden dilakukan dengan aturan deterministik; OpenAI hanya membantu membuat ringkasan Bahasa Indonesia. Jika layanan tidak tersedia, sistem menggunakan ringkasan lokal.

## Tes

Jalankan Feature tests tanpa menyentuh database demo:

```powershell
php vendor/phpunit/phpunit/phpunit -c phpunit.xml --bootstrap vendor/autoload.php --testsuite Feature
```

Aturan transisi ada di `app/Services/WorkflowService.php`, endpoint di `routes/web.php`, dan skema database di `database/migrations/`.
