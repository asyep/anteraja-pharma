# Frontend Anteraja Pharma

Vue 3, TypeScript dan Vite untuk Admin, Checker, Kurir, serta Vendor / Customer. API Laravel berada di folder saudara server/.

## Jalankan lokal

1. Siapkan Node 22, PHP 8.2+ (pdo_mysql, GD, ZIP) dan MySQL. Folder client/ dan server/ harus berdampingan.
2. Di server/, jalankan composer install. Isi koneksi database khusus Pharma di server/.env. Jangan memakai database produksi untuk demo atau tes.
3. Di client/, jalankan:

   ~~~powershell
   npm ci
   npm run setup
   npm run dev
   ~~~

4. Buka http://localhost:3170. Akun dan password lokal tersimpan di .private/akun-demo.txt; jangan unggah file ini.

Launcher menjalankan Vite, API, scheduler, dan worker ringkasan AI. Ctrl+C menghentikan proses milik launcher, bukan server proyek lain. API memakai port 8170–8190 yang tersedia. Port Vite 3170 harus kosong.

Di Windows, launcher mencari PHP di PATH lalu XAMPP. Set PHP_BINARY bila instalasi PHP berbeda. Ekstensi GD/ZIP lokal XAMPP diaktifkan lewat server/runtime/windows/dev.ini, bukan mengubah php.ini global.

Untuk frontend saja: npm run dev:web. Pastikan API sudah berjalan pada port 8170, atau set PHARMA_API_PORT sesuai API.

## Alur dan tempat mengubah kode

| Lokasi | Tanggung jawab |
| --- | --- |
| src/pages/CreateShipment.vue | Instruksi, kategori suhu, berat, penerima dan Checker |
| src/components/ActionPanel.vue | Checklist dua langkah, scan/manual AWB, foto, keputusan, GPS dan POD |
| src/components/ShipmentMonitor.vue | Peta Leaflet/OSM dengan titik tercatat, jejak GPS dan grafik suhu |
| src/components/DemoPanel.vue | Simulator Admin untuk kiriman bertanda demo |
| src/pages/ShipmentDetail.vue | Riwayat, evaluasi, foto privat dan laporan akhir |
| src/lib/api.ts | Session, CSRF, timeout dan pesan kegagalan API |
| src/lib/offline.ts | Antrean IndexedDB milik akun: pemeriksaan/foto dan GPS |
| src/composables/useResource.ts | Fetch, abort dan pencegahan polling bertumpuk |
| src/styles.css, src/login.css | Layout, warna, responsivitas dan halaman login |

Admin tidak mengisi pemeriksaan Checker. Customer hanya memantau. Persetujuan diblokir bila pemeriksaan belum lulus. Paparan suhu di luar rentang minimal 3 menit atau sinyal hilang 15 menit menghasilkan HOLD. Pemulihan GPS sendiri tidak melepas HOLD.

Foto/PDF dilindungi session dan kepemilikan. Unduh laporan dari detail kiriman; laporan diterbitkan sekali setelah diterima atau retur. QR memverifikasi digest tanpa membuka data penerima.

## Offline dan kegagalan

Pemeriksaan serta foto dapat diantrekan ketika koneksi gagal. GPS otomatis berjalan setiap 10 detik selama halaman terbuka. Draft hanya dikirim untuk akun pembuatnya, memakai event ID untuk mencegah duplikasi.

Jika versi berubah, sistem menyimpan draft dan meminta pemeriksaan ulang, tidak menimpa data. Buka kiriman sebagai Checker, pilih Pulihkan draft offline, periksa checklist/foto lalu kirim ulang. Data lebih dari 24 jam tidak dikirim otomatis. Perangkat bersama harus memakai profil browser pribadi agar bukti offline tidak terbaca pengguna perangkat lain.

Peta memakai titik terukur, bukan koordinat alamat yang ditebak. Tanpa GPS, peta hanya menunjukkan area Jakarta dan menjelaskan belum ada lokasi. Gangguan tile memiliki pesan serta retry; OSM membutuhkan internet.

## Tes

~~~powershell
npm test
npm run lint
npm run build
npm run test:e2e
~~~

E2E memerlukan server lokal aktif dan akun seed. Tes membuat kiriman QA pada database lokal yang dikonfigurasi; jangan arahkan ke produksi. GPS pada tes di-emulasi browser, bukan bukti perangkat nyata.

## Batas implementasi

- Ini web app, bukan pelacakan background saat HP terkunci.
- Data suhu manual atau simulasi ditandai; tidak mengaku sensor tersertifikasi.
- Barcode foto memakai kemampuan browser; ketik AWB fisik jika tidak didukung.
- Telegram baru berstatus terkirim setelah provider mengonfirmasi. Tanpa token/chat ID, status tetap belum dikonfigurasi.
- Ringkasan OpenAI opsional berjalan di worker; keputusan keselamatan selalu memakai aturan lokal.
- PDF memiliki QR/digest dan penyimpanan terenkripsi. Kepatuhan PDF/A penuh, tanda tangan tersertifikasi, SLA, serta sertifikasi farmasi belum diklaim.

## Branch satu folder

feature-client berisi hanya client/, sesuai permintaan tim. Jangan merge langsung ke main karena snapshot ini menghilangkan server/ dan docs/ dari tree branch. Untuk menggabungkan, ambil perubahan folder client saja ke checkout gabungan yang tetap memiliki server/docs. Dokumen acuan ada di main, bukan branch snapshot ini.
