# Hasil pengujian aktual

Tanggal: 13 Oktober 2026. Scope aplikasi Pharma baru; capstone lama tidak diubah.

## Quality gate

| Pemeriksaan | Hasil nyata |
|---|---|
| TypeScript Vue | Lulus `npm run typecheck` |
| ESLint | Lulus, warning format telah diperbaiki tanpa menonaktifkan pemeriksaan |
| Unit UI | 4 tes lulus: nama semua tahap, tone status, invalid date, format WIB |
| Laravel regression | 16 tes, 109 assertion lulus |
| Browser E2E | 3 tes besar lulus: alur wajib lengkap, QA seluruh peran/breakpoint, carousel/keyboard/network recovery |
| Production build | Lulus; bundle JS sekitar 167 kB (59 kB gzip), CSS 23 kB (5,5 kB gzip) |
| npm audit | 0 vulnerability setelah upgrade test dependency yang sempat memiliki advisori |
| Composer | Dependency lock diselaraskan; audit tidak menemukan advisori |
| Database sebenarnya | Migrasi dan seed berhasil pada MariaDB XAMPP dengan driver mysql; E2E memakai database ini |
| Webhook HTTP | `node tools/automation.mjs` berhasil menghasilkan report_id melalui HMAC/timestamp |
| Android debug | Gradle `assembleDebug` berhasil; APK sekitar 5,5 MB, ikon Anteraja dan app name Pharma |
| Perangkat Android fisik | Belum diuji; tidak ada perangkat/emulator yang tersedia pada pengecekan |
| Telegram nyata | Credential ditunda pengguna. Adapter/retry diuji dengan mock; milestone kirim nyata belum diklaim lulus |

## Cakupan backend

Checklist belum lengkap dan penugasan ditolak, approve/assign hanya Admin, accept/reject hanya Satria yang mendapat tugas, perjalanan normal, penerimaan dan konfirmasi Pembeli, segel rusak/auto-hold/retur, larangan courier decide, recheck/persetujuan ulang, suhu di luar range, input kosong, stale version 409, isolasi Pembeli, request tanpa session, pesan unconfigured, respons provider berhasil/rate-limit/ditolak, reminder idempotent, laporan idempotent dan revisi, signed webhook/dedupe, Admin hold.

Tes backend memakai SQLite in-memory. Pengujian browser memakai database mysql-compatible lokal sebenarnya. Mock Telegram hanya untuk transport test, tidak mengganti API bisnis dengan fake response pada aplikasi.

## Cakupan browser

- Admin membuat paket melalui form nyata, approve diblokir, Satria melengkapi pemeriksaan, Admin approve/assign, Satria accept/depart/issue, Admin return, Satria return_complete, Pembeli melihat hasil.
- Laporan dibuat setelah perjalanan; file CSV diperiksa mengandung resi uji dan alasan segel bermasalah.
- Admin, Satria dan Pembeli diuji pada 375, 768, 1440px: dashboard, daftar dan pesan. Admin juga pembuatan kiriman dan laporan. Tidak ditemukan overflow halaman setelah perbaikan.
- Pencarian kosong menghasilkan empty state dengan pemulihan.
- Navigasi Link mempertahankan state sentinel, sehingga tidak terjadi full page reload saat perpindahan SPA.
- Login menggunakan Enter; focus ring terlihat di CSS. Semua kontrol menggunakan input/button/link native dan label. Mobile navigation yang tertutup dibuat inert, Esc mengembalikan focus ke tombol menu.
- Carousel otomatis 4 detik, kontrol manual, tombol pause, dan reduced-motion berhasil diuji. Hover/focus pause disediakan dalam handler; tidak mengganggu form.
- Request dashboard gagal menghasilkan pesan pemulihan; setelah jaringan dipulihkan, Coba lagi berhasil. Request mutation tanpa CSRF ditolak 419.
- Tidak muncul exception JavaScript baru dalam pengujian normal. Error jaringan sengaja dipicu pada tes pemulihan dan tidak dianggap keberhasilan jaringan.

Tidak mengklaim audit WCAG menyeluruh, performa produksi, semua kombinasi perangkat, atau zero bug di masa depan. Native install dan pesan Telegram nyata harus diverifikasi pada perangkat/akun tim sebelum demo resmi.

## Temuan yang diperbaiki

| Temuan | Perbaikan |
|---|---|
| Version default hasil create belum terbaca pada object Eloquent | Default version model eksplisit; seed dan workflow tidak mengirim versi kosong |
| Opsi select memiliki penutup tag salah saat scaffolding | Markup option diperbaiki dan Vue TypeScript diperiksa |
| Overflow mobile dari teks sr-only di tabel | Posisi sr-only dan containing block tabel diperbaiki, scroll horizontal dibatasi di tabel |
| Aksi Tahan menjadi pilihan awal sesudah persetujuan | Penugasan diprioritaskan; persetujuan menjadi pilihan awal untuk pending_approval |
| Sidebar mobile tersembunyi masih bisa mendapat Tab | inert saat tertutup, menu disclosure dan Esc focus restore |
| Laporan pertama hari itu berpotensi tertinggal dari perubahan baru | Revisi snapshot berdasarkan event watermark; CSV terbaru diuji terhadap resi saat ini |
| Screenshot awal sempat diambil sebelum data selesai dimuat | Bukti diperbarui setelah status/komponen utama benar-benar muncul |
| Tes QA memicu batas login karena login berulang per ukuran | Sesi per peran dipakai ulang; rate limiter produksi tetap aktif |
| Audit gabungan 33 halaman melebihi batas tes 90 detik pada pengulangan | Batas waktu audit gabungan menjadi 240 detik dan baseURL konteks eksplisit; assertion, timeout request aplikasi, serta batas login tetap dipertahankan |
| Path Windows dengan & mengganggu launcher dependency | Node/PHP/Java dipanggil langsung dengan path relatif/ter-quote yang aman |
| Credential role baru belum mengikat pesan lama | Delivery dapat memakai konfigurasi chat ID per role setelah token dipasang |
| Pesan belum terkonfigurasi dapat menahan antrean baru | Pesan pending baru diprioritaskan; konfigurasi yang belum tersedia dicoba ulang setelah 15 menit |
| Riwayat seed berpotensi terkirim ketika token dipasang belakangan | Pesan contoh diberi status demo, bukan antrean pengiriman; 664 pesan seed lokal ditandai tanpa menghapus audit |

## Bukti

`output/e2e-results.json`, `output/screenshots/`, `output/e2e-report.csv`, dan APK lokal. Trace kegagalan pengembangan tidak masuk Git. Environment dan akun demo ada pada file privat yang di-ignore. Milestone presentasi/review/judging hanya dapat dicatat setelah kegiatan nyata.
