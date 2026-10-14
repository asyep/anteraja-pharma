# Frontend Anteraja Pharma

Aplikasi Vue 3 + TypeScript untuk alur di `../docs/`. Frontend berada di folder `client/`; API Laravel berada di `../server/`.

Kode aplikasi Vue ada di `src/`, dengan komponen bersama di `src/components/`, halaman di `src/pages/`, utilitas di `src/lib/`, dan composables di `src/composables/`. File konfigurasi dan entry point Vite tetap di root `client/`; aset statis publik berada di `public/`.

## Menjalankan

```powershell
npm ci
npm run dev
```

`npm run dev` menjalankan launcher pengembangan yang menyalakan frontend, API, dan scheduler. Untuk menjalankan frontend saja:

```powershell
npm run dev:web
```

Pastikan API lokal sudah berjalan jika frontend dijalankan terpisah. Konfigurasi API dan aplikasi dikelola oleh launcher; rahasia OpenAI hanya disimpan di `server/.env` dan tidak digunakan langsung oleh browser.

## Fitur

UI mendukung peran Admin, Checker, Kurir, dan Vendor / Customer; pemeriksaan barang dengan foto bukti; alur persetujuan dan penugasan; pembaruan lokasi/suhu; serta tampilan evaluasi insiden dan laporan akhir pengiriman.

Pelacakan browser hanya berjalan selama halaman detail terbuka. Aplikasi belum menyediakan pelacakan background, peta interaktif, atau pembuatan PDF server-side.

## Validasi

```powershell
npm test
npm run lint
npm run build
```
