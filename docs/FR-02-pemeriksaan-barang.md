# Functional Requirement Document (FRD)
## FR-02: Pemeriksaan Barang (Initial Examination)

**Sistem**: Pharma Logistics Workflow Architecture  
**Dokumen Modul**: FR-02  
**Nama Fitur**: Pemeriksaan Awal Barang & Integrasi Hasil (Initial Exam & Monitor Integration)  
**Referensi Sumber**: `prd-pharma-logistics.md` [1], `anterajapharma.drawio.png` [1]  
**Tanggal**: 14 Oktober 2026  
**Status**: Final Draft  

---

### 1. Deskripsi Fitur & Tujuan Bisnis
Fitur **Pemeriksaan Barang (FR-02)** mencakup prosedur verifikasi fisik dan teknis awal yang dilakukan oleh **Checker** (*Initial Exam*) segera setelah pengiriman diciptakan oleh **ADMIN** [1]. Hasil dari pemeriksaan ini ditransmisikan secara langsung ke *Monitor Page* milik **ADMIN** sebagai dasar kelayakan sebelum dilakukan proses pemilihan kurir [1].

Tujuan utama fitur ini adalah memastikan bahwa seluruh muatan farmasi berada dalam kondisi fisik, jumlah, dan segel kemasan yang sesuai standar mutu kesehatan sebelum memasuki tahap transportasi fisik [1].

---

### 2. Aktor & Hak Akses
* **Checker**: Eksekutor utama yang melakukan *Initial Exam*, menginput hasil verifikasi, dan mengunggah bukti fisik [1].
* **ADMIN**: Penerima hasil *Initial Exam* melalui *Monitor Page* untuk membuat keputusan penugasan kurir (*Pemilihan Kurir*) [1].

---

### 3. Alur Kerja & Spesifikasi Gunapakai (Use Case Detail)

#### 3.1 Alur Utama (Main Flow)
1. **Penerimaan Tugas**: **Checker** menerima pemicu tugas pengiriman baru pada aplikasi *Checker Terminal* [1].
2. **Pemindaian & Identifikasi**: **Checker** memindai QR Code/Barcode `shipment_id` pada kemasan fisik barang.
3. **Pelaksanaan Initial Exam**: **Checker** melakukan inspeksi fisik mencakup:
   * Keutuhan segel (*seal integrity*).
   * Verifikasi jumlah koli/paket.
   * Pemeriksaan kondisi suhu awal muatan *cold-chain*.
4. **Input Hasil & Pengiriman Data**: **Checker** memasukkan hasil inspeksi (`PASSED` / `REJECTED`), menyertakan foto bukti, dan memilih tombol **Submit Exam** [1].
5. **Pemutakhiran Monitor Page**: Sistem secara otomatis mengirimkan hasil *Initial Exam* ke *Monitor Page* milik **ADMIN** [1].
6. **Perubahan Status**: Status pengiriman pada *Monitor Page* berubah menjadi `"EXAMINED_READY_FOR_ASSIGNMENT"`.

#### 3.2 Alur Alternatif & Pengecualian (Alternative Flow)
* **Pemeriksaan Gagal (Initial Exam Failed)**: Jika barang rusak atau segel terbuka, **Checker** memilih status `REJECTED` dan mencantumkan alasan kerusakan. Sistem memperbarui *Monitor Page* **ADMIN** dengan bendera (*flag*) peringatan merah, serta menghentikan alur ke pemilihan kurir hingga ada tindakan korektif dari **ADMIN**.

---

### 4. Kamus Data (Data Dictionary)

| Nama Field | Tipe Data | Wajib (Mandatory) | Deskripsi / Aturan Validasi |
| :--- | :--- | :--- | :--- |
| `exam_id` | String (UUID) | Ya | ID unik transaksi pemeriksaan. |
| `shipment_id` | String | Ya | ID pengiriman yang diperiksa [1]. |
| `checker_id` | String | Ya | ID akun **Checker** yang bertugas [1]. |
| `exam_result` | Enum | Ya | Nilai status (`PASSED`, `REJECTED`, `PASSED_WITH_NOTES`). |
| `initial_temp` | Float | Tidak | Suhu aktual awal barang saat diperiksa (°C). |
| `photo_proof_url` | String (URL) | Ya | Tautan foto bukti fisik hasil pemeriksaan. |
| `exam_timestamp` | Timestamp | Ya | Waktu persis saat pemeriksaan diselesaikan. |

---

### 5. Aturan Bisnis (Business Rules)
1. **BR-02.1**: **ADMIN** tidak dapat melakukan *Pemilihan Kurir* sebelum **Checker** menyelesaikan *Initial Exam* dengan hasil `PASSED` atau `PASSED_WITH_NOTES` [1].
2. **BR-02.2**: Setiap hasil *Initial Exam* wajib menyertakan sekurang-kurangnya 1 (satu) lampiran foto fisik paket/segel.
3. **BR-02.3**: Hasil pemeriksaan harus tersinkronisasi ke *Monitor Page* **ADMIN** secara *near real-time* (< 2 detik) [1].

---

### 6. Persyaratan Antarmuka (UI/UX Requirement)
* **Aplikasi Mobile/Terminal Checker**: Antarmuka berbasis langkah (*wizard*) yang memandu inspeksi komponen satu per satu dengan tombol ambil foto cepat.
* **Komponen Monitor Page ADMIN**: Widget indikator status *Initial Exam* (Warna Hijau untuk `PASSED`, Merah untuk `REJECTED`) lengkap dengan tombol modal untuk melihat detail catatan dan foto dari **Checker** [1].

---

### 7. Persyaratan Non-Fungsional Modul
* **Dukungan Offline (Offline Capability)**: Apabila perangkat **Checker** kehilangan sinyal di area gudang, data *Initial Exam* disimpan secara lokal dan diunggah otomatis (*auto-sync*) saat koneksi pulih.
* **Integritas Data**: Foto dan hasil inspeksi yang telah di-submit oleh **Checker** bersifat *immutable* (tidak dapat diubah atau dihapus).