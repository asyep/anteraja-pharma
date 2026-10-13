# Pharma di cloud

Buka [anteraja-pharma.vercel.app](https://anteraja-pharma.vercel.app). Kamu tidak perlu menjalankan server laptop untuk memakai situs ini. Akun demo sama dengan file privat lokal; password tidak masuk Git atau bundle frontend.

## Apa yang berjalan di mana

| Bagian | Tempat berjalan | Kegunaan |
|---|---|---|
| Vue + Vite | Berkas statis pada Vercel | Halaman masuk, dashboard, form, daftar, detail, laporan dan carousel |
| Laravel | Function PHP `api/index.php` pada Vercel | Login, validasi, role, workflow, audit, laporan dan outbox |
| Database | TiDB Cloud Starter, Singapore | Data Pharma, akun, session, cache dan jejak keputusan |
| TLS | Laravel ke TiDB | Koneksi terenkripsi dengan verifikasi sertifikat |

TiDB adalah database MySQL-compatible. Laravel tetap memakai driver `mysql`; proyek tidak dipindahkan ke PostgreSQL atau Supabase. Database cloud baru dibuat dengan migrasi Pharma yang sama dan 32 kiriman seed. Perubahan data di situs cloud tidak otomatis tersinkron ke database laptop. Database capstone lama tidak diubah.

Vercel menggunakan runtime komunitas `vercel-php@0.9.0`, bukan layanan PHP/Laravel native resmi. Composer memasang dependency backend ketika build. Entrypoint memetakan PSR-4 App ke folder backend secara eksplisit dan memakai `/tmp` hanya untuk cache bootstrap/scratch. Session dan cache aplikasi tersimpan pada database, bukan filesystem sementara function.

## Credential dan batas biaya

Cluster memakai paket Starter dengan spending limit bulanan 0. Aplikasi bisa berhenti melayani ketika kuota gratis habis; ini bukan janji kapasitas tanpa batas. Akun SQL migrasi dan aplikasi dipisahkan. Akun aplikasi memakai role read/write, bukan akun administrator database. APP_KEY dan secret cloud berbeda dari konfigurasi lokal.

Credential hanya disimpan pada `.private/cloud.json`, `.private/vercel-production.json` dan environment server-only proyek Vercel. Jangan membagikan file itu, mengambil screenshot nilainya, atau memasukkannya ke repository. `APP_DEBUG=false`, cookie session Secure, enkripsi session aktif, dan backend/.env tidak disertakan dalam deployment.

## Otomatisasi di paket gratis

Vercel Hobby mengizinkan cron harian, bukan proses Laravel `schedule:work` terus-menerus. Konfigurasi saat ini memanggil `/api/cron` pada 10:00 UTC, yaitu sekitar 17:00 WIB; ketepatan jam pada Hobby tidak dijamin. Endpoint memerlukan bearer CRON_SECRET, memakai lock dan tidak membuat laporan harian duplikat. Setiap invocation juga memproses pengingat dan maksimal dua pesan untuk menjaga durasi function.

Pengiriman/retry pesan setiap menit dan pengingat setiap lima menit seperti server lokal membutuhkan scheduler eksternal yang memanggil endpoint yang sama. Scheduler eksternal tersebut belum dipasang. Jangan menganggap cadence harian sama dengan cadence lokal. Setelah token Telegram tersedia, uji kirim nyata dan tentukan frekuensi scheduler sebelum demo operasional. Token Telegram dan akun penerima masih menunggu konfigurasi tim.

## Hasil verifikasi

Tiga tes browser pada domain produksi lulus: alur wajib hingga retur, seluruh peran pada 375/768/1440px, serta carousel/reduced-motion/keyboard/network recovery. Tes membuat kiriman dummy cloud tambahan dan memeriksa export CSV berisi resi uji. Session API dan health merespons JSON HTTP 200 setelah perbaikan autoload. Tes backend lokal terbaru: 18 tes, 118 assertion, termasuk autentikasi scheduler dan pencegahan laporan duplikat.

## Referensi

- [Runtime Vercel](https://vercel.com/docs/functions/runtimes)
- [Runtime PHP komunitas](https://github.com/vercel-community/php)
- [TiDB Starter](https://docs.pingcap.com/tidbcloud/select-cluster-tier/)
- [Batas cron Vercel](https://vercel.com/docs/cron-jobs/usage-and-pricing)
