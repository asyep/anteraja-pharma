# Functional Requirement Document (FRD)
## Modul FR-01: Manajemen Pengiriman (Shipment Management)

**Sistem**: Pharma Logistics Workflow Architecture (Anteraja NextGen AI)  
**Dokumen Modul**: FR-01 - Manajemen Pengiriman  
**Acuan**: Pedoman Hackathon Anteraja x Maxy Academy (Group 3 - Pharma)  
**Tanggal**: 14 Oktober 2026  
**Status**: Final Draft  

---

### 1. Deskripsi Fitur & Tujuan Bisnis
Modul **FR-01 (Manajemen Pengiriman)** merupakan gerbang utama pendaftaran dan inisiasi pengiriman produk farmasi (obat-obatan, vaksin, kargo *cold-chain*). Modul ini menyediakan fungsi bagi **ADMIN** untuk memasukkan spesifikasi kritis pengiriman—termasuk kategori obat, rentang ambang batas suhu (*temperature threshold*), jumlah koli, dan instruksi penanganan khusus (*handling instructions*).

Setelah pengiriman berhasil dibuat, sistem secara otomatis memicu tugas *Initial Examination* ke terminal **Checker** di gudang/hub dan menerbitkan tautan *Monitor Page* bagi **Vendor/Customer** via WhatsApp/Telegram.

---

### 2. Aktor & Hak Akses
* **ADMIN**: Hak Akses `Write` (Membuat resi pengiriman baru, mengatur konfigurasi penanganan, membatalkan resi).
* **Checker**: Hak Akses `Read` (Menerima pemicu pengerjaan tugas *Initial Exam*).
* **Vendor / Customer**: Hak Akses `Read-Only` (Memantau status dan progres pengiriman awal via *Monitor Page*).

---

### 3. Spesifikasi Alur Kerja (Use Case Detail)
#### 3.1 Alur Utama (Main Flow)
1. **Inisiasi Form**: Admin membuka Dashboard Web Admin dan memilih menu **"Create New Shipment"**.
2. **Input Data Resi**: Admin mengisi formulir pengiriman yang mencakup:
   * Nomor AWB/Resi (Otomatis dieksekusi oleh sistem atau diinput manual).
   * Nama Produk & Kategori Farmasi (*Cold-Chain 2-8°C*, *Deep Freeze -20°C*, atau *Reguler 15-25°C*).
   * Batas Suhu Minimum (`temp_min`) dan Maksimum (`temp_max`).
   * Jumlah koli/paket dan berat total.
   * Data Kontak Pengirim (Vendor/Apotik) & Penerima (Rumah Sakit/Klinik/Customer).
3. **Validasi System**: Sistem mengecek kelengkapan bidang wajib (*mandatory fields*).
4. **Pemicuan Tugas & Status Awal**:
   * Sistem menyimpan record dengan status awal `CREATED_AWAITING_EXAM`.
   * Pemicu tugas dikirimkan secara otomatis ke aplikasi *Checker Terminal*.
   * Tautan *Monitor Page* unik diterbitkan dan dikirimkan via bot pesan otomatis ke Vendor/Customer.

#### 3.2 Alur Alternatif & Pengecualian (Alternative Flow)
* **Pengecualian Input Suhu Kosong**: Jika Admin memilih kategori *Cold-Chain* namun tidak memasukkan rentang suhu `temp_min` / `temp_max`, sistem memblokir tindakan submit dan memberikan pesan error: *"Produk Cold-Chain wajib mencantumkan rentang suhu keselamatan."*

---

### 4. Kamus Data (Data Dictionary)
| Nama Field | Tipe Data | Mandatory | Deskripsi & Aturan Validasi |
| :--- | :--- | :--- | :--- |
| `shipment_id` | String (UUID) | Ya | Primary Key unik resi pengiriman. |
| `awb_number` | String | Ya | Nomor AWB resi unik (misal: `ANT-PHAR-20261014-001`). |
| `pharma_category` | Enum | Ya | `COLD_CHAIN_2_8`, `DEEP_FREEZE`, `CONTROLLED_ROOM_TEMP`. |
| `product_name` | String | Ya | Nama obat / vaksin (misal: `Vaksin MMR Cold-Chain`). |
| `temp_min` | Float | Ya* | Minimum suhu aman (°C) (*Wajib untuk Cold-Chain/Deep Freeze). |
| `temp_max` | Float | Ya* | Maksimum suhu aman (°C) (*Wajib untuk Cold-Chain/Deep Freeze). |
| `total_packages` | Integer | Ya | Jumlah koli/kardus paket (Min: 1). |
| `status` | Enum | Ya | Status awal: `CREATED_AWAITING_EXAM`. |

---

### 5. Aturan Bisnis (Business Rules)
* **BR-01.1**: Setiap resi kategori farmasi khusus wajib memiliki konfigurasi `temp_min` dan `temp_max`.
* **BR-01.2**: Paket yang baru dibuat berstatus `CREATED_AWAITING_EXAM` dan **TIDAK DAPAT** dialokasikan ke Kurir sebelum melewati tahapan FR-02 (*Initial Exam*) serta persetujuan Admin.
* **BR-01.3**: Sistem wajib mengirimkan notifikasi konfirmasi ke Vendor/Customer melalui WhatsApp/Telegram begitu resi berhasil dibuat.

---

### 6. Persyaratan UI/UX & Antarmuka
* **Dashboard Admin - Create Shipment Form**:
  * Antarmuka bersih (*clean grid*) dengan pemilah kategori berpembuat warna (*badge color*): Biru untuk Cold-Chain, Ungu untuk Deep Freeze, Hijau untuk Reguler.
  * Preview otomatis pesan WhatsApp/Telegram yang akan dikirim ke Customer saat tombol submit ditekan.

---

### 7. Persyaratan Non-Fungsional
* **Waktu Respon API**: Pemrosesan *Create Shipment* harus diselesaikan dalam waktu `< 300 ms`.
* **Keamanan Akses**: Form *Create Shipment* hanya dapat diakses oleh akun Admin terautentikasi (JWT / Session Token).
