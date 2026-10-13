# Functional Requirement Document (FRD)
## FR-01: Manajemen Pengiriman (Shipment Management)

**Sistem**: Pharma Logistics Workflow Architecture  
**Dokumen Modul**: FR-01  
**Nama Fitur**: Inisiasi Pengiriman & Pemantauan Awal (Create Shipments & Monitor Page)  
**Referensi Sumber**: `prd-pharma-logistics.md` [1], `anterajapharma.drawio.png` [1]  
**Tanggal**: 14 Oktober 2026  
**Status**: Final Draft  

---

### 1. Deskripsi Fitur & Tujuan Bisnis
Fitur **Manajemen Pengiriman (FR-01)** berfungsi sebagai pintu masuk utama (*entry point*) seluruh operasional logistik farmasi. Fitur ini memungkinkan aktor **ADMIN** untuk menginisiasi entri pengiriman baru (*Create Shipments*) yang secara otomatis memicu alur pemeriksaan awal (*Initial Exam*) oleh **Checker** serta mengaktifkan antarmuka pemantauan (*Monitor Page*) bagi **ADMIN** dan **Vendor/Customer** [1].

Tujuan utama fitur ini adalah menjamin validitas data barang farmasi sejak titik awal pengiriman (*Start*), memberikan transparansi real-time kepada pihak konsumen/vendor, dan mengoordinasikan kesiapan pemeriksaan barang sebelum penugasan kurir [1].

---

### 2. Aktor & Hak Akses
* **ADMIN**: Memiliki akses penuh (*Create, Read, Update*) untuk membuat pengiriman (*Create Shipments*) dan memantau status awal pada *Monitor Page* [1].
* **Checker**: Menerima pemicu (*trigger*) otomatis berupa penugasan *Initial Exam* setelah pengiriman berhasil dibuat [1].
* **Vendor / Customer**: Memiliki hak akses terbatas (*Read-Only*) pada *Monitor Page* untuk melihat ringkasan status pengiriman yang baru dibuat [1].

---

### 3. Alur Kerja & Spesifikasi Gunapakai (Use Case Detail)

#### 3.1 Alur Utama (Main Flow)
1. **Inisiasi (*Start*)**: **ADMIN** membuka modul pengiriman dan memilih menu **Create Shipments** [1].
2. **Pengisian Data**: **ADMIN** melengkapi formulir pengiriman (data vendor, tujuan, jenis obat/farmasi, jumlah, persyaratan suhu/cold-chain, dan tanggal pengiriman).
3. **Validasi & Validasi Sistem**: Sistem melakukan validasi ketersediaan data dan kelayakan nomor resi/AWB.
4. **Pemicuan Tugas Pararel (*Parallel Triggers*)**: Setelah pengiriman disimpan:
   * **Tugas Checker**: Sistem secara otomatis mengirimkan pemberitahuan dan daftar antrean *Initial Exam* ke sistem **Checker** [1].
   * **Aktivasi Monitor Page**: Sistem menginisiasi sesi *Monitor Page* yang dapat diakses oleh **ADMIN** dan **Vendor/Customer** dengan status awal `"Awaiting Initial Exam"` [1].

#### 3.2 Alur Alternatif & Pengecualian (Alternative & Exception Flow)
* **Gagal Validasi Input**: Jika data wajib (misal: spesifikasi batas suhu farmasi) kosong, sistem menolak pembuatan pengiriman dan memberikan pesan peringatan ke **ADMIN**.
* **Kegagalan Notifikasi Checker**: Jika layanan notifikasi ke **Checker** terputus, sistem akan melakukan *retry* otomatis 3x dan mencatat log kejadian pada dashboard **ADMIN**.

---

### 4. Kamus Data (Data Dictionary)

| Nama Field | Tipe Data | Wajib (Mandatory) | Deskripsi / Aturan Validasi |
| :--- | :--- | :--- | :--- |
| `shipment_id` | String (UUID/AWB) | Ya | Kode unik pengiriman, digenerate otomatis oleh sistem. |
| `vendor_id` | String | Ya | ID terdaftar dari Vendor/Customer pengirim. |
| `customer_name` | String | Ya | Nama penerima/fasilitas kesehatan tujuan. |
| `pharma_type` | Enum | Ya | Kategori obat (`Cold-Chain`, `Reguler`, `B3/Narkotika`). |
| `temp_requirement` | Float Range | Ya | Batas suhu yang diizinkan (misal: `2.0 - 8.0` °C). |
| `created_by` | String | Ya | User ID dari **ADMIN** yang membuat resi pengiriman [1]. |
| `status` | Enum | Ya | Default status awal: `CREATED_AWAITING_EXAM`. |

---

### 5. Aturan Bisnis (Business Rules)
1. **BR-01.1**: Hanya pengguna dengan peran **ADMIN** yang terotentikasi yang dapat mengeksekusi fungsi *Create Shipments* [1].
2. **BR-01.2**: Pengiriman barang farmasi jenis *Cold-Chain* wajib mencantumkan rentang parameter suhu kritis pada saat *Create Shipments*.
3. **BR-01.3**: *Monitor Page* untuk **Vendor/Customer** tidak boleh menampilkan informasi internal biaya operasional atau identitas internal **Checker** [1].

---

### 6. Persyaratan Antarmuka (UI/UX Requirement)
* **Formulir Create Shipments (ADMIN)**: Antarmuka bersih dengan input field terstruktur, tombol *Submit*, dan indikator validasi real-time.
* **Monitor Page (ADMIN & Vendor/Customer)**: Halaman dasbor interaktif yang menampilkan *stepper timeline* (Start $ightarrow$ Initial Exam $ightarrow$ Pemilihan Kurir $ightarrow$ In Transit $ightarrow$ AI Check $ightarrow$ Selesai) [1].

---

### 7. Persyaratan Non-Fungsional Modul
* **Waktu Respon (Performance)**: Pembuatan pengiriman dan pemicuan ke *Monitor Page* & **Checker** harus selesai dalam waktu < 1.5 detik.
* **Keamanan (Security)**: Akses *Monitor Page* oleh **Vendor/Customer** menggunakan enkripsi token unik (*magic link* / secure JWT).