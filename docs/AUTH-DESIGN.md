# Halaman masuk Pharma

Halaman `/masuk` memakai satu logo pada header, panel foto editorial dengan caption teal yang terpisah dari gambar, dan form login ringkas. Ini bukan layout foto fullscreen dari capstone tracking.

Tiga pilihan akun demo selalu berada pada satu baris. Legend berada di luar grid tombol; pilihan hanya mengisi email, tidak mengubah role, password, session, atau hak akses server. Pharma belum menyediakan registrasi publik; akun tetap dibuat sesuai pengaturan tim.

Pada mobile, form ditempatkan lebih dahulu dan carousel di bawahnya. Caption tidak menutupi wajah atau paket. Grid caption menyimpan ukuran seluruh slide sehingga pergantian tidak memindahkan form. Carousel memakai interval empat detik, kontrol manual, pause saat hover/focus, pause ketika tab tersembunyi, serta reduced-motion.

## Aset lokal

- `public/images/pharma-inspection.webp`: pemeriksaan segel kemasan kesehatan, 77.040 byte.
- `public/images/pharma-delivery.webp`: pengantaran paket kesehatan ke apotek, 106.522 byte.
- `public/images/pharma-handover.webp`: serah terima di konter apotek, 79.224 byte.

Ketiganya adalah visual ilustratif untuk prototype, bukan foto dokumentasi pengiriman nyata. Tidak memuat logo atau tulisan produk palsu. File lokal WebP tidak memakai hotlink. Logo asli tetap berasal dari aset Anteraja yang sudah tersedia.

## Verifikasi

Screenshot login 375, 768, 1440 dan 1920px ada di `output/screenshots/pharma-login-*.png`. Pemeriksaan tambahan memastikan satu logo, tiga tombol role sejajar, tidak ada overflow horizontal, form mobile berada di awal, tombol masuk terlihat pada viewport desktop, pergantian tiga slide, pause hover/focus, dan error login terhubung ke input. Autentikasi, database, API dan workflow pengiriman tidak diubah.
