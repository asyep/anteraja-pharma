# Panduan demo Pharma — 45 menit

Gunakan data fiktif. Tim harus menjelaskan prosesnya sendiri, bukan membaca source tanpa memahami. Milestone presentasi dan penilaian belum dianggap selesai sebelum demo resmi berlangsung.

## Pembagian waktu resmi

| Durasi | Bagian |
|---|---|
| 5 menit | Masalah dan solusi bisnis |
| 5 menit | Arsitektur |
| 20 menit | Live demo end-to-end |
| 5 menit | Dampak bisnis dan KPI |
| 10 menit | Tanya jawab teknis |

## Pembukaan yang mudah dipahami

"Paket kesehatan perlu perhatian berbeda. Bayangkan segelnya terlihat terbuka tepat sebelum diserahkan. Satria tidak seharusnya menebak apakah boleh lanjut. Aplikasi kami memastikan paket diperiksa sebelum berangkat, dan ketika ada masalah, Admin memberi keputusan yang tercatat. Penerima juga tahu apa yang sedang terjadi."

Jelaskan siapa yang terbantu: staf QA/Admin, Satria, dan penerima fasilitas kesehatan. Jangan mengklaim aplikasi bersertifikasi atau telah menurunkan kerusakan nyata. Ini prototype dengan aturan yang dapat didemonstrasikan.

## Arsitektur dalam bahasa sehari-hari

"Vue adalah tampilan yang dipakai orang. Laravel adalah petugas penjaga aturan di server. Database menyimpan paket, pemeriksaan, keputusan, dan riwayatnya. Ketika satu tindakan berhasil, sistem juga menaruh pesan pada antrean. Pengirim pesan bekerja terpisah supaya pengguna tidak harus menunggu Telegram."

Orchestration berarti aturan urutan kerja: setelah checklist lengkap, tunggu persetujuan; sesudah ditugaskan, tunggu Satria; sesudah kendala, tunggu keputusan. Automation berarti tindakan yang berjalan sendiri ketika dipicu: menahan paket, menyiapkan pesan, mengingatkan tugas, membuat laporan.

## Persiapan sebelum panggung

1. Aktifkan MySQL lokal dan run.bat. Buka http://localhost:3170.
2. Buka tiga browser profile/window terpisah untuk Admin, Satria, Pembeli. Incognito terpisah context diperlukan supaya cookie tidak saling mengganti akun.
3. Baca akun dari .private/akun-demo.txt. Jangan menampilkan file password pada projector.
4. Jika credential Telegram sudah dipasang, pastikan masing-masing akun uji telah /start bot. Buka Telegram di window terpisah dan menu Notifikasi aplikasi.
5. Jika demo memakai HP/APK, HP dan laptop satu Wi-Fi, alamat IP laptop sesuai docs/MOBILE.md.

## Live demo wajib (20 menit)

1. **Admin membuat kiriman:** penerima fasilitas kesehatan, pilih akun Pembeli dan Satria pemeriksa. Tulis instruksi "Jaga segel utuh. Jika segel rusak, hentikan pengiriman dan tunggu keputusan." Jadwal harus di masa depan.
2. **Uji pemblokiran:** pada detail, pilih Setujui pengiriman tanpa checklist. Isi alasan dan kirim. Tunjukkan pesan bahwa paket belum boleh berangkat. Muat ulang: status tidak berubah dan percobaan diblokir tercatat dalam riwayat.
3. **Satria memeriksa:** buka resi yang sama, centang kemasan, segel, label, tulis hasil pemeriksaan. Simpan. Paket masuk Menunggu persetujuan.
4. **Admin menyetujui:** muat ulang detail, beri alasan persetujuan. Sistem baru membuka penugasan.
5. **Admin menugaskan:** pilih Satria, tulis instruksi. Status Menunggu jawaban. Tunjukkan pesan tugas, instruksi, tautan ke aplikasi. Jika Telegram belum dipasang, jelaskan status menunggu konfigurasi secara jujur.
6. **Satria menerima dan berangkat:** isi alasan penerimaan, terima. Isi lokasi dan catatan, Mulai perjalanan. Pembeli membuka paket dan melihat pembaruan.
7. **Segel bermasalah:** Satria memilih Laporkan kendala, jenis Segel tidak utuh. Tulis kondisi konkret. Sesudah dikirim, status otomatis ditahan. Tunjukkan tidak ada tombol untuk melanjutkan pengantaran.
8. **Admin memilih retur:** buka detail, baca kendala, pilih Kembalikan paket (retur). Alasan: "Jangan serahkan kepada penerima. Kembalikan ke Hub Cakung untuk pemeriksaan QA." Simpan. Kurir dan pembeli mendapat instruksi/kabar.
9. **Satria mencatat paket kembali:** isi lokasi hub dan orang/petugas yang menerima kembali pada catatan. Status Sudah dikembalikan.
10. **Riwayat dan laporan:** tunjukkan siapa melakukan apa, jam, alasan, hasil pemeriksaan serta keputusan. Buat laporan hari ini, unduh CSV dan cocokkan resi. Pembaruan baru membuat revisi; snapshot lama tidak diubah.

## Jika masih ada waktu

- Tugas ditolak Satria: Admin dapat menugaskan ulang, tidak berubah seolah pengantaran selesai.
- Alur normal: berangkat, tiba, catat penerimaan, Pembeli mengonfirmasi.
- Kendala integritas: opsi lanjut langsung tidak tersedia untuk segel/kemasan/suhu/label.
- Halaman stale: dua window mencoba bertindak dengan version lama. Server menolak 409, bukan membuat keputusan ganda.
- Tampilan mobile, keyboard, error jaringan dan hasil pencarian kosong.

## Dampak bisnis dan KPI (5 menit)

Manfaat yang ingin diuji pada pilot: pemeriksaan yang tidak terlewat, keputusan lebih mudah ditelusuri, dan penerima mendapat kabar. Jangan mengarang persentase penghematan atau menganggap data dummy adalah hasil lapangan.

KPI dapat dihitung dari data aplikasi: jumlah dispatch_blocked; proporsi paket yang mempunyai pemeriksaan lengkap sebelum approval; waktu dari issue.created_at ke keputusan; umur penugasan pending; status dan jumlah percobaan pesan; jumlah retur dan penerimaan. Paparkan target pilot sebagai target, bukan hasil yang sudah tercapai.

## Tanya jawab (10 menit)

**Mengapa kurir tidak boleh memutuskan segel rusak?** Kurir mencatat fakta lapangan. Admin menentukan langkah dan alasannya, sehingga tanggung jawab jelas.

**Bagaimana mencegah melewati checklist?** Server memeriksa hasil inspection terbaru, tahap shipment dan role akun. Menyembunyikan tombol saja tidak cukup; request langsung juga ditolak.

**Bagaimana saat Telegram mati?** Tindakan bisnis tetap tersimpan dalam transaksi dengan pesan outbox. Worker menandai retry/failed, bukan mengaku terkirim. Admin dapat menjadwalkan ulang.

**Apakah setiap pesan pasti hanya sekali?** Deduplikasi lokal mencegah pembuatan pesan ganda. Bila proses mati sesudah Telegram menerima tetapi sebelum database diperbarui, pengiriman ulang bisa terjadi. Kami tidak mengklaim exactly-once di jaringan.

**Apakah ini cold chain dengan sensor?** Tidak. Range suhu ditetapkan per paket dan pembacaan dicatat manual. Tidak ada sensor maupun GPS palsu.

**Apakah sudah bersertifikat CDOB?** Tidak. Prinsip pencatatan dan kontrol menjadi referensi desain, bukan pengganti sertifikasi/audit regulasi.

**Mengapa tidak memakai n8n?** Official meminta orkestrasi dan otomatisasi, tidak mewajibkan produk tertentu. Native Laravel mengatur transisi, outbox, webhook, scheduler yang dapat diuji.

**Bisakah dipakai tanpa komputer?** Buyer/Satria dapat memakai browser HP atau kemasan Android demo, dengan koneksi ke server yang sama. APK lokal memerlukan server laptop dan satu jaringan.

## Berkas untuk pengumpulan

Branch GitHub: https://github.com/asyep/anteraja-pharma/tree/first-mvp
Source frontend/backend, docs aturan+arsitektur+demo+Telegram, output screenshot/hasil test, APK debug lokal dan bahan presentasi. Credential privat tidak masuk pengumpulan. Bukti Telegram nyata dilengkapi setelah pemasangan akun uji. Jangan mengaku kegiatan review/demo/judging sudah dilakukan jika belum.
