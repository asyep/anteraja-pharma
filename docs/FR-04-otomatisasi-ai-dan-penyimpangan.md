# Functional Requirement Document (FRD)
## FR-04: Otomatisasi AI & Deteksi Penyimpangan (AI Automation & Anomaly Detection)

**Sistem**: Pharma Logistics Workflow Architecture  
**Dokumen Modul**: FR-04  
**Nama Fitur**: AUTOMATION AI, Kondisi Evaluasi (Condition ?) & Penambahan Data Laporan  
**Referensi Sumber**: `prd-pharma-logistics.md` [1], `anterajapharma.drawio.png` [1]  
**Tanggal**: 14 Oktober 2026  
**Status**: Final Draft  

---

### 1. Deskripsi Fitur & Tujuan Bisnis
Fitur **Otomatisasi AI & Deteksi Penyimpangan (FR-04)** merupakan mesin analisis cerdas (*intelligent engine*) dalam arsitektur sistem pengiriman farmasi. Fitur ini secara berkelanjutan menerima *stream* data dari alur *Running Monitor Processed* Kurir, memproses percabangan keputusan **Condition ?**, serta secara otomatis menetapkan status **aman** atau mencatat **Penyimpangan** yang memicu prosedur **Penambahan data laporan** [1].

Tujuan utama fitur ini adalah menjamin kontrol kualitas (*quality assurance*) obat-obatan farmasi berbasis aturan AI secara otomatis tanpa bergantung pada pengawasan manual manusia, serta memastikan seluruh insiden penyimpangan terrekam secara transparan [1].

---

### 2. Aktor & Hak Akses
* **AUTOMATION AI**: Aktor sistem mandiri yang memproses stream data, mengevaluasi aturan *Condition ?*, menentukan status keselamatan, dan mengeksekusi *Penambahan data laporan* [1].
* **ADMIN**: Menerima peringatan otomatis (*real-time alert*) apabila AI menetapkan status *Penyimpangan* [1].
* **Kurir & Vendor/Customer**: Menerima pencatatan dampak dari evaluasi AI pada laporan pengiriman akhir [1].

---

### 3. Alur Kerja & Spesifikasi Gunapakai (Use Case Detail)

#### 3.1 Alur Utama (Main Flow)
1. **Ingesti Data Telemetry**: Modul **AUTOMATION AI** menyerap *stream* data real-time (suhu, koordinat GPS, kecepatan, dan durasi) dari alur *Running Monitor Processed* Kurir [1].
2. **Evaluasi Keputusan (*Condition ?*)**: AI mengevaluasi data masukan terhadap kriteria/threshold keselamatan farmasi pada titik keputusan **Condition ?** [1]:
3. **Cabang A - Kondisi Ya (Sesuai Standar)**:
   * Jika seluruh parameter berada dalam ambang batas toleransi (misal: suhu tetap 2-8°C, rute sesuai), AI menetapkan status **aman** [1].
   * Alur langsung diteruskan ke modul pembuatan laporan (*Reportment*) [1].
4. **Cabang B - Kondisi Tidak (Terjadi Penyimpangan)**:
   * Jika terdeteksi anomali (misal: lonjakan suhu > 8°C selama > 15 menit, atau kargo berhenti tidak wajar), AI menetapkan status **Penyimpangan** [1].
   * AI secara otomatis mengeksekusi fungsi **Penambahan data laporan** untuk mencatat rincian insiden (waktu, lokasi GPS, besarnya lonjakan suhu) [1].
   * Data insiden terlampir diteruskan ke modul *Reportment* [1].

#### 3.2 Alur Alternatif & Pengecualian (Alternative Flow)
* **Gagal Ingesti Sensor (Sensor Signal Loss)**: Jika *stream* data dari Kurir terputus melebihi batas waktu toleransi (misal 15 menit), AI mengklasifikasikan situasi ini sebagai *Penyimpangan* (kategori: *Telemetry Disruption*) dan mengeksekusi *Penambahan data laporan* secara otomatis.

---

### 4. Kamus Data (Data Dictionary)

| Nama Field | Tipe Data | Wajib (Mandatory) | Deskripsi / Aturan Validasi |
| :--- | :--- | :--- | :--- |
| `ai_evaluation_id` | String (UUID) | Ya | ID unik transaksi analisis AI. |
| `shipment_id` | String | Ya | ID pengiriman yang dievaluasi [1]. |
| `condition_result` | Boolean | Ya | Hasil evaluasi `Condition ?` (`True` = Ya/Aman, `False` = Tidak/Penyimpangan) [1]. |
| `final_ai_status` | Enum | Ya | Status AI (`aman`, `Penyimpangan`) [1]. |
| `incident_type` | Enum | Tidak | Jenis penyimpangan (`TEMP_EXCURSION`, `ROUTE_DEVIATION`, `SIGNAL_LOSS`). |
| `anomaly_details` | JSON/Text | Tidak | Payload data hasil dari fungsi **Penambahan data laporan** [1]. |
| `evaluation_time` | Timestamp | Ya | Waktu persis saat AI mengeksekusi keputusan *Condition ?* [1]. |

---

### 5. Aturan Bisnis (Business Rules)
1. **BR-04.1**: Penilaian **Condition ?** oleh **AUTOMATION AI** berjalan secara otomatis tanpa intervensi manual dari pengguna [1].
2. **BR-04.2**: Apabila status ditetapkan sebagai **Penyimpangan**, sistem WAJIB menjalankan alur **Penambahan data laporan** sebelum dapat melanjutkan ke tahap *Reportment* [1].
3. **BR-04.3**: Setiap insiden penyimpangan suhu di atas ambang batas toleransi > 5 menit harus mencetuskan notifikasi prioritas tinggi (*High Priority Push Notif*) ke dasbor **ADMIN**.

---

### 6. Persyaratan Antarmuka (UI/UX Requirement)
* **Dasbor AI Engine Monitoring (ADMIN)**: Antarmuka grafik real-time yang menampilkan garis kurva suhu vs waktu, dengan zona batas toleransi dan *highlight* merah pada titik-titik terjadinya *Penyimpangan*.
* **Widget Log Penambahan Data Laporan**: Tabel detail kejadian penyimpangan yang memuat timestamp, koordinat lokasi, dan nilai penyimpangan yang dicatat otomatis oleh AI [1].

---

### 7. Persyaratan Non-Fungsional Modul
* **Kecepatan Pemrosesan AI**: Logika keputusan *Condition ?* harus memproses paket data telemetry dalam waktu < 500 milidetik per *stream payload*.
* **Akurasi Evaluasi**: Mesin aturan AI harus memiliki tingkat akurasi pemrosesan ambang batas 99.99% sesuai spesifikasi standar CPOB (Cara Pembuatan Obat yang Baik) / GDP (Good Distribution Practice).