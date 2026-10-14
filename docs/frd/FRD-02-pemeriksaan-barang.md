# Functional Requirement Document (FRD)
## Modul FR-02: Pemeriksaan Barang & AI Vision (Initial Examination)

**Sistem**: Pharma Logistics Workflow Architecture (Anteraja NextGen AI)  
**Dokumen Modul**: FR-02 - Pemeriksaan Barang  
**Acuan**: Pedoman Hackathon Anteraja x Maxy Academy (Group 3 - Pharma)  
**Tanggal**: 14 Oktober 2026  
**Status**: Final Draft  

---

### 1. Deskripsi Fitur & Tujuan Bisnis
Modul **FR-02 (Pemeriksaan Barang)** mengatur prosedur verifikasi fisik dan teknis di hub/gudang oleh **Checker** (*Initial Exam*) sebelum kargo disetujui untuk diangkut kurir. Fitur ini menjamin keutuhan segel kemasan, kesesuaian jumlah koli, serta kesesuaian suhu awal sesuai standar Cara Pembuatan Obat yang Baik (CPOB) & Good Distribution Practice (GDP).

Sebagai **Fitur Unggulan (Differentiator)**, modul ini dilengkapi dengan **AI Vision Inspection / OCR** yang mampu membaca foto termometer/segel secara otomatis dan memverifikasi kesesuaian data input Checker.

---

### 2. Aktor & Hak Akses
* **Checker**: Hak Akses `Write` (Melakukan scan AWB, mengisi *checklist*, mengunggah foto fisik, submit exam).
* **ADMIN**: Hak Akses `Read & Approve` (Meninjau hasil *checklist* & foto di Monitor Page, memilih keputusan **"Setujui"** atau **"Tahan"**).
* **AUTOMATION AI**: Hak Akses `Process` (Menjalankan OCR/Vision check pada foto bukti).

---

### 3. Spesifikasi Alur Kerja (Use Case Detail)
#### 3.1 Alur Utama (Main Flow)
1. **Penerimaan Tugas**: Checker membuka aplikasi mobile *Checker Terminal* dan melihat antrean tugas pemeriksaan.
2. **Scan & Verifikasi**: Checker memindai QR Code/Barcode resi AWB pada fisik kemasan obat.
3. **Pengisian Checklist**: Checker mengisi poin pemeriksaan fisik wajib:
   * Keutuhan Segel (*Intact / Damaged*)
   * Kesesuaian Label & Koli (*Match / Mismatch*)
   * Input Suhu Fisik Termometer (°C)
4. **Upload Foto Bukti**: Checker mengunggah sekurang-kurangnya 1 foto kondisi fisik kemasan dan termometer.
5. **[AI Vision Check - Differentiator]**: AI secara otomatis mengekstrak angka pada foto termometer via OCR dan membandingkan dengan input suhu Checker.
6. **Submit & Pending Approval**: Checker menekan **"Submit Exam"**. Status berubah menjadi `EXAMINED_PENDING_APPROVAL`.
7. **Admin Approval Gate**: Admin menerima indikator di Dashboard Monitor Page:
   * Jika checklist valid dan foto terverifikasi, Admin memilih **"Setujui"** -> Status berubah menjadi `APPROVED_READY_FOR_ASSIGNMENT`.
   * Jika segel rusak/suhu tidak sesuai, Admin memilih **"Tahan"** -> Status berubah menjadi `EXAM_REJECTED_HOLD`.

#### 3.2 Alur Alternatif (Alternative Flow)
* **Checklist Ditolak (Exam Failed)**: Jika Checker menemukan segel rusak, Checker memilih status `REJECTED` beserta alasan. Sistem secara otomatis menandai resi dengan bendera merah pada Monitor Page Admin dan menahan proses penugasan kurir.

---

### 4. Kamus Data (Data Dictionary)
| Nama Field | Tipe Data | Mandatory | Deskripsi & Aturan Validasi |
| :--- | :--- | :--- | :--- |
| `exam_id` | String (UUID) | Ya | Primary Key unik transaksi pemeriksaan. |
| `shipment_id` | String (UUID) | Ya | Foreign Key merujuk ke tabel shipment. |
| `checker_id` | String | Ya | ID akun Checker yang melakukan inspeksi. |
| `seal_status` | Enum | Ya | `SEAL_INTACT`, `SEAL_DAMAGED`. |
| `initial_temp` | Float | Ya | Suhu terukur pada termometer kemasan (°C). |
| `ocr_detected_temp` | Float | Tidak | Hasil ekstraksi suhu oleh AI Vision dari foto. |
| `photo_url` | String (URL) | Ya | Tautan foto bukti pemeriksaan fisik. |
| `admin_approval` | Enum | Ya | `APPROVED`, `REJECTED_HOLD`. |
| `admin_notes` | Text | Ya* | Catatan keputusan Admin (Wajib jika REJECTED_HOLD). |

---

### 5. Aturan Bisnis (Business Rules)
* **BR-02.1**: **ADMIN** tidak dapat melakukan penugasan kurir sebelum *Initial Exam* diselesaikan dan disetujui dengan status `APPROVED_READY_FOR_ASSIGNMENT`.
* **BR-02.2**: Setiap *Initial Exam* WAJIB menyertakan minimal 1 foto bukti fisik kemasan yang valid.
* **BR-02.3**: Seluruh keputusan Admin (Setujui/Tahan) mencatat `admin_id`, `timestamp`, dan `notes` secara permanen (*Audit Log*).

---

### 6. Persyaratan UI/UX & Antarmuka
* **Checker Mobile Wizard**: Tampilan bertahap (*Step-by-Step Wizard*) dengan tombol foto cepat dan indikator visual status segel.
* **Admin Approval Modal**: Modal pop-up pada Dashboard Admin yang menampilkan perbandingan foto fisik, checklist Checker, hasil OCR AI, serta tombol konfirmasi **Setujui / Tahan**.

---

### 7. Persyaratan Non-Fungsional
* **Dukungan Offline (Offline Capability)**: Apabila Checker berada di area blindspot gudang, data disimpan lokal di memori HP dan diunggah otomatis saat koneksi internet pulih.
* **Immutability Data**: Hasil pemeriksaan dan foto yang sudah di-submit bersifat permanen dan tidak dapat diedit/dihapus.
