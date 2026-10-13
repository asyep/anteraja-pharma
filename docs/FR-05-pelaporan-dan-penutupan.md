# Functional Requirement Document (FRD)
## FR-05: Pelaporan & Penutupan (Reporting & Closure)

**Sistem**: Pharma Logistics Workflow Architecture  
**Dokumen Modul**: FR-05  
**Nama Fitur**: Pembuatan Laporan (Reportment), Distribusi Multi-Stakeholder & Penutupan Pengiriman  
**Referensi Sumber**: `prd-pharma-logistics.md` [1], `anterajapharma.drawio.png` [1]  
**Tanggal**: 14 Oktober 2026  
**Status**: Final Draft  

---

### 1. Deskripsi Fitur & Tujuan Bisnis
Fitur **Pelaporan & Penutupan (FR-05)** merupakan tahap akhir (*final stage*) dari seluruh rantai operasional pengiriman barang farmasi. Modul **Reportment** mengompilasi seluruh jejak audit (*audit trail*)—termasuk hasil *Initial Exam*, riwayat *tracking gps*, serta luaran evaluasi AI (baik status **aman** maupun detail **Penambahan data laporan**)—menjadi dokumen resmi **Laporan** [1]. Dokumen ini didistribusikan secara paralel kepada tiga stakeholder utama (**Kurir**, **ADMIN**, dan **Vendor/Customer**) sebelum alur pengiriman diakhiri pada status **Selesai** [1].

Tujuan utama fitur ini adalah memberikan pertanggungjawaban hukum/kepatuhan mutu farmasi, menyediakan bukti penerimaan resmi (*Proof of Delivery*), dan menutup transaksi logistik secara sah [1].

---

### 1. Aktor & Hak Akses
* **AUTOMATION AI / System**: Mengompilasi data dan memicu modul pembuatan dokumen **Reportment** [1].
* **Kurir**: Menerima salinan dokumen **Laporan** sebagai bukti penyelesaian tugas pengiriman [1].
* **ADMIN**: Menerima dokumen **Laporan** lengkap untuk arsip audit operasional perusahaan [1].
* **Vendor / Customer**: Menerima dokumen **Laporan** akhir sebagai bukti kepatuhan kualitas dan bukti serah terima kargo farmasi [1].

---

### 3. Alur Kerja & Spesifikasi Gunapakai (Use Case Detail)

#### 3.1 Alur Utama (Main Flow)
1. **Pemicuan Modul Reportment**: Modul **Reportment** diaktifkan secara otomatis setelah hasil evaluasi AI (*aman* atau *Penyimpangan* dengan data laporan tambahan) diselesaikan [1].
2. **Kompilasi Dokumen Laporan**: Sistem menyusun dokumen resmi **Laporan** yang mengkonsolidasikan:
   * Metadata Pengiriman (`shipment_id`, vendor, tujuan, tanggal).
   * Hasil *Initial Exam* dari **Checker** [1].
   * Riwayat perjalanan dan pelacakan GPS Kurir [1].
   * Hasil Keputusan AI (**aman** atau detail **Penyimpangan**) [1].
3. **Distribusi Paralel (*Parallel Distribution*)**: Sistem menerbitkan dokumen **Laporan** dan mendistribusikannya secara bersamaan kepada:
   * **Kurir** (via aplikasi mobile/email) [1].
   * **ADMIN** (via portal admin/dashboard) [1].
   * **Vendor / Customer** (via portal customer/email) [1].
4. **Pengakhiran Alur (*Selesai*)**: Setelah seluruh laporan terdistribusi, sistem memperbarui status pengiriman menjadi **Selesai** dan mengunci rekaman transaksi (*lock record*) [1].

#### 3.2 Alur Alternatif & Pengecualian (Alternative Flow)
* **Gagal Distribusi Email/Notifikasi**: Jika pengiriman dokumen **Laporan** ke **Vendor/Customer** gagal karena kendala jaringan, sistem mencatat status `DISTRIBUTION_FAILED` dan menjadwalkan *auto-retry* pengiriman ulang setiap 15 menit hingga berhasil.

---

### 4. Kamus Data (Data Dictionary)

| Nama Field | Tipe Data | Wajib (Mandatory) | Deskripsi / Aturan Validasi |
| :--- | :--- | :--- | :--- |
| `report_id` | String (UUID) | Ya | Kode unik dokumen laporan resmi. |
| `shipment_id` | String | Ya | ID pengiriman induk [1]. |
| `pdf_report_url` | String (URL) | Ya | Tautan unduh dokumen PDF **Laporan** resmi. |
| `final_status` | Enum | Ya | Nilai status penutupan (`COMPLETED_SAFE`, `COMPLETED_WITH_DEVIATION`). |
| `delivered_to` | Array of Strings | Ya | Daftar entitas penerima (`["ADMIN", "KURIR", "VENDOR_CUSTOMER"]`) [1]. |
| `completed_at` | Timestamp | Ya | Waktu tepat saat alur mencapai titik **Selesai** [1]. |

---

### 5. Aturan Bisnis (Business Rules)
1. **BR-05.1**: Dokumen **Laporan** harus mencakup ringkasan dari seluruh 4 modul sebelumnya (*Create Shipments*, *Initial Exam*, *Courier Tracking*, dan *AI Evaluation*) [1].
2. **BR-05.2**: Distribusi dokumen **Laporan** wajib dilakukan secara paralel kepada ketiga pihak (**Kurir**, **ADMIN**, **Vendor/Customer**) [1].
3. **BR-05.3**: Setelah status berubah menjadi **Selesai**, data transaksi pengiriman bersifat *read-only* dan diarsipkan sesuai regulasi retensi data farmasi (minimal 5 tahun).

---

### 6. Persyaratan Antarmuka (UI/UX Requirement)
* **Antarmuka Tinjauan Laporan (Report View)**: Tampilan pembaca PDF (*built-in PDF viewer*) pada portal **ADMIN** dan **Vendor/Customer** lengkap dengan watermark keabsahan digital dan tombol *Download PDF*.
* **Notifikasi Penyelesaian**: Banner pemberitahuan hijau berbunyi *"Pengiriman Selesai - Laporan Telah Diterbitkan"* pada aplikasi **Kurir** dan portal **Vendor/Customer**.

---

### 7. Persyaratan Non-Fungsional Modul
* **Format & Standar Dokumen**: Dokumen **Laporan** digenerate dalam format PDF/A (standard archiving) yang dapat dicetak dan memenuhi standar audit BPOM/GDP.
* **Kecepatan Generasi Dokumen**: Modul **Reportment** harus mampu menghasilkan dan menyimpan PDF Laporan dalam waktu < 3 detik setelah memicu alur penutupan.