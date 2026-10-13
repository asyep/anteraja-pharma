# Functional Requirement Document (FRD)
## FR-03: Penugasan Kurir & Pelacakan GPS (Courier Assignment & Tracking)

**Sistem**: Pharma Logistics Workflow Architecture  
**Dokumen Modul**: FR-03  
**Nama Fitur**: Pemilihan Kurir, Perubahan Status, Running Monitor & Pelacakan GPS  
**Referensi Sumber**: `prd-pharma-logistics.md` [1], `anterajapharma.drawio.png` [1]  
**Tanggal**: 14 Oktober 2026  
**Status**: Final Draft  

---

### 1. Deskripsi Fitur & Tujuan Bisnis
Fitur **Penugasan Kurir & Pelacakan GPS (FR-03)** mengelola tahapan eksekusi transportasi fisik pengiriman barang farmasi. Berdasarkan hasil inspeksi pada *Monitor Page*, **ADMIN** melakukan **Pemilihan Kurir** [1]. Kurir yang ditunjuk (*terpilih*) memperbarui status pengiriman (*Changing status*), menjalankan alur pengawasan operasional (*Running Monitor Processed*), serta memancarkan data telemetry lokasi (*tracking gps*) secara real-time yang dapat dipantau bersama oleh **ADMIN** dan **Vendor/Customer** [1].

Tujuan utama fitur ini adalah menjamin visibilitas pergerakan armadatranspor farmasi, transparansi estimasi waktu tiba (ETA), dan ketersediaan data telemetry kontinu untuk pengawasan AI pada tahap berikutnya [1].

---

### 2. Aktor & Hak Akses
* **ADMIN**: Melakukan eksekusi *Pemilihan Kurir* dan memantau posisi armada via *tracking gps* pada *Monitor Page* [1].
* **Kurir**: Menerima status *terpilih*, memperbarui status operasional (*Changing status*), dan menjalankan *Running Monitor Processed* [1].
* **Vendor / Customer**: Mengakses tampilan peta pelacakan *tracking gps* secara real-time pada *Monitor Page* [1].

---

### 3. Alur Kerja & Spesifikasi Gunapakai (Use Case Detail)

#### 3.1 Alur Utama (Main Flow)
1. **Pemilihan Kurir oleh Admin**: **ADMIN** membuka *Monitor Page*, melihat daftar pengiriman berstatus `EXAMINED_READY_FOR_ASSIGNMENT`, lalu mengeksekusi menu **Pemilihan Kurir** [1].
2. **Penetapan Status Terpilih**: Kurir yang ditunjuk menerima notifikasi penugasan dan statusnya berubah menjadi *terpilih* [1].
3. **Pembaruan Status Kurir (*Changing status*)**: **Kurir** mengonfirmasi penerimaan tugas pada aplikasi mobile dan mengubah status pengiriman (misal: `"Picked Up"` / `"In Transit"`) [1].
4. **Menjalankan Running Monitor Processed**: **Kurir** mengaktifkan mode *Running Monitor Processed* saat armada mulai bergerak [1].
5. **Transmisi Pelacakan GPS**: Selama *Running Monitor Processed* aktif, aplikasi Kurir mengirimkan koordinat latitude/longitude dan telemetry suhu secara kontinu.
6. **Pemantauan Bersama**: Data *tracking gps* ditayangkan secara interaktif pada peta peta *Monitor Page* milik **ADMIN** dan **Vendor/Customer** [1].

#### 3.2 Alur Alternatif & Pengecualian (Alternative Flow)
* **Kurir Menolak/Batal**: Jika **Kurir** yang *terpilih* mengalami kendala armada, Kurir dapat menolak penugasan. Sistem mengembalikan status pengiriman ke *Monitor Page* **ADMIN** untuk proses *Pemilihan Kurir* ulang.
* **Otomatisasi Re-connection GPS**: Jika sinyal GPS Kurir terputus saat *Running Monitor Processed*, sistem menyimpan titik koordinat secara *buffer* di perangkat Kurir dan mengirimkannya sekaligus saat sinyal kembali normal.

---

### 4. Kamus Data (Data Dictionary)

| Nama Field | Tipe Data | Wajib (Mandatory) | Deskripsi / Aturan Validasi |
| :--- | :--- | :--- | :--- |
| `assignment_id` | String (UUID) | Ya | ID transaksi penugasan kurir. |
| `shipment_id` | String | Ya | ID pengiriman terkait [1]. |
| `courier_id` | String | Ya | ID unik akun **Kurir** yang ditunjuk [1]. |
| `courier_status` | Enum | Ya | Status Kurir (`terpilih`, `accepted`, `in_transit`, `arrived`) [1]. |
| `latitude` | Float | Ya | Koordinat Lintang GPS (misal: `-6.2088`). |
| `longitude` | Float | Ya | Koordinat Bujur GPS (misal: `106.8456`). |
| `speed_kmh` | Float | Tidak | Kecepatan armada Kurir saat *Running Monitor Processed*. |

---

### 5. Aturan Bisnis (Business Rules)
1. **BR-03.1**: **ADMIN** hanya dapat memilih **Kurir** yang memiliki kualifikasi lisensi penanganan obat/farmasi dan armada berfasilitas *cold-chain* (jika dipersyaratkan) [1].
2. **BR-03.2**: Fitur *tracking gps* wajib memperbarui titik koordinat lokasi sekurang-kurangnya setiap 10 (sepuluh) detik selama mode *Running Monitor Processed* aktif [1].
3. **BR-03.3**: Peta *tracking gps* pada *Monitor Page* milik **Vendor/Customer** tidak boleh menampilkan data rute internal Kurir lain yang tidak relevan [1].

---

### 6. Persyaratan Antarmuka (UI/UX Requirement)
* **Aplikasi Mobile Kurir**: Tombol besar *Changing Status*, indikator status GPS aktif (Warna Hijau/Merah), dan petunjuk rute pengiriman.
* **Peta Tracking GPS (ADMIN & Vendor/Customer)**: Tampilan peta peta interaktif (Mapbox/Google Maps) yang menampilkan ikon bergerak posisi armada Kurir, estimasi waktu tiba (ETA), dan log riwayat jalur [1].

---

### 7. Persyaratan Non-Fungsional Modul
* **Frekuensi Stream Telemetry**: Latensi pemutakhiran koordinat GPS pada antarmuka *Monitor Page* tidak boleh melebihi 3 detik.
* **Efisiensi Baterai**: Aplikasi Kurir harus dioptimalkan agar konsumsi daya GPS background tidak menghabiskan baterai perangkat dalam durasi pengiriman 8+ jam.