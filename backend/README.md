# Backend Anteraja Pharma

Laravel 12 menjaga autentikasi, akses peran, aturan pemeriksaan/persetujuan, penugasan, keputusan kendala, audit, Telegram dan laporan. Frontend Vue berada di folder frontend pada root project. Tidak ada server JavaScript backend maupun database capstone yang dipakai ulang.

Jalankan aplikasi dari root menggunakan npm run dev. Panduan lengkap ada di ../how_to_run.txt dan ../README.md.

Untuk menjalankan API saja dari folder ini:

```powershell
& C:\xampp\php\php.exe artisan serve --host=127.0.0.1 --port=8170
```

Scheduler:

```powershell
& C:\xampp\php\php.exe artisan schedule:work
```

Regression tests tanpa menyentuh database demo:

```powershell
& C:\xampp\php\php.exe vendor/phpunit/phpunit/phpunit -c phpunit.xml --bootstrap vendor/autoload.php
```

Lihat app/Services/WorkflowService.php untuk aturan transisi, routes/web.php untuk API, dan database/migrations untuk skema. Credential hanya pada .env yang di-ignore Git. Database harus bernama khusus anteraja_pharma_hackathon untuk setup otomatis; jangan gunakan migrate:fresh pada database lama.
