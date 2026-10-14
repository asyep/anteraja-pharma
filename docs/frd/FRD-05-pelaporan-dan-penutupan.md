# Functional Requirement Document (FRD)
## Modul FR-05: Pelaporan Otomatis, Berita Acara & Penutupan (Reportment & Closure)

**Sistem**: Pharma Logistics Workflow Architecture (Anteraja NextGen AI)  
**Dokumen Modul**: FR-05 - Pelaporan & Penutupan  
**Acuan**: Pedoman Hackathon Anteraja x Maxy Academy (Group 3 - Pharma)  
**Tanggal**: 14 Oktober 2026  
**Status**: Final Draft  

---

### 1. Deskripsi Fitur & Tujuan Bisnis
Modul **FR-05 (Pelaporan Otomatis & Penutupan)** merupakan tahap akhir dari alur operasional pengiriman farmasi. Modul ini bertugas mengompilasi seluruh jejak audit (*audit trail*)—mulai dari *Initial Exam*, grafik histori suhu, log pergerakan GPS, hingga Berita Acara Penyimpangan (BAP) jika ada insiden—menjadi dokumen resmi **Laporan Pengiriman PDF/A**.

Dokumen laporan ini didistribusikan secara otomatis dan serentak kepada **Admin**, **Kurir**, dan **Vendor/Customer** sebagai bukti kepatuhan hukum (*legal accountability*) dan standar mutu CPOB.

---

### 2. Aktor & Hak Akses
* **AUTOMATION AI / Report Engine**: Mengompilasi data dan menerbitkan dokumen PDF otomatis.
* **ADMIN**: Menerima salinan laporan resmi & mengakses arsip laporan.
* **Kurir**: Menerima salinan laporan penutupan tugas via WhatsApp/Email.
* **Vendor / Customer**: Menerima salinan laporan resmi pengiriman & BAP.

---

### 3. Spesifikasi Alur Kerja (Use Case Detail)
#### 3.1 Pemicuan & Kompilasi Laporan
1. **Pemicu Penutupan**: Kurir menekan tombol **"Selesai Serah Terima"** pada aplikasi dan mengunggah foto *Proof of Delivery (POD)*.
2. **Kompilasi Data Audit**: Modul *Reportment* mengumpulkan data lengkap:
   * Metadata Resi & Spesifikasi Produk Farmasi.
   * Hasil Checklist & Foto Bukti *Initial Exam* oleh Checker.
   * Grafik Kurva Histori Suhu dari awal hingga akhir perjalanan.
   * Log Catatan Insiden & Alasan Keputusan Admin (BAP) jika terjadi penyimpangan.
   * Metadata Bukti Serah Terima (POD) & Timestamp.
3. **Generasi PDF/A**: Sistem menerbitkan file PDF/A resmi terenkripsi dilengkapi dengan QR Code Verifikasi Integritas Dokumen.

#### 3.2 Distribusi Paralel & Penutupan
1. **Pengiriman Pesan Otomatis**: Bot sistem mengirimkan file PDF / tautan unduh resmi via WhatsApp, Telegram, dan Email secara bersamaan ke Admin, Kurir, dan Customer.
2. **Penutupan Status (Closure)**: Status transaksi diubah menjadi `COMPLETED_CLOSED`. Rekaman dikunci (*read-only*) dan diarsipkan.

---

### 4. Struktur Dokumen Laporan PDF (Content Specifications)
Dokumen Laporan Resmi memuat bagian-bagian berikut:
1. **Header**: Logo Anteraja, Judul *Laporan Pengiriman Produk Farmasi*, Nomor AWB, dan Kode QR Verifikasi.
2. **Bagian I - Informasi Pengiriman**: Detail pengirim, penerima, nama produk, kategori, dan rentang suhu aman.
3. **Bagian II - Hasil Inspection Checker**: Status segel, suhu awal, nama Checker, dan foto fisik.
4. **Bagian III - Grafik Monitoring Suhu & Tracking**: Grafik kurva suhu vs waktu, min/max suhu terrekam, dan ringkasan rute GPS.
5. **Bagian IV - Berita Acara Penyimpangan (BAP)** *(Jika ada insiden)*: Detail waktu penyimpangan, deviasi suhu, dan alasan keputusan yang diambil oleh Admin.
6. **Bagian V - Proof of Delivery (POD)**: Foto penerima, timestamp serah terima, dan tanda tangan digital.

---

### 5. Aturan Bisnis (Business Rules)
* **BR-05.1**: Dokumen laporan PDF WAJIB diterbitkan dalam waktu `< 5 detik` setelah status POD berhasil dikonfirmasi.
* **BR-05.2**: Setelah status berubah menjadi `COMPLETED_CLOSED`, seluruh data transaksi bersifat *immutable* (tidak dapat diubah, diedit, atau dihapus oleh siapapun).
* **BR-05.3**: Setiap dokumen laporan wajib menyertakan kode QR verifikasi untuk memvalidasi keaslian dokumen di portal resmi Anteraja.

---

### 6. Persyaratan Non-Fungsional
* **Arsip & Retensi Data**: File PDF dan log transaksi disimpan di penyimpanan aman (*Cloud Object Storage*) dengan masa retensi minimal 5 tahun sesuai regulasi farmasi BPOM.
* **Ketersediaan Distribusi**: Tingkat keberhasilan pengiriman notifikasi WhatsApp/Email laporan akhir harus `≥ 99%`.
