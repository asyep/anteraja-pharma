# Functional Requirement Document (FRD)
## Modul FR-04: Automasi AI, Deteksi Penyimpangan & Predictive Cold-Chain

**Sistem**: Pharma Logistics Workflow Architecture (Anteraja NextGen AI)  
**Dokumen Modul**: FR-04 - Automasi AI & Penyimpangan  
**Acuan**: Pedoman Hackathon Anteraja x Maxy Academy (Group 3 - Pharma)  
**Tanggal**: 14 Oktober 2026  
**Status**: Final Draft  

---

### 1. Deskripsi Fitur & Tujuan Bisnis
Modul **FR-04 (Automasi AI & Deteksi Penyimpangan)** berfungsi sebagai mesin pengawas cerdas (*Intelligent Quality Engine*) yang menyerap *stream payload telemetry* selama pengantaran. AI secara berkelanjutan mengevaluasi kondisi keselamatan obat (*Condition ?*) dan memicu tindakan intervensi otomatis apabila terjadi penyimpangan (misal: suhu melampaui batas, segel dibuka, atau signal loss).

Fitur ini diperkaya dengan **Predictive Cold-Chain Alert (Differentiator)** yang memberikan peringatan dini (*early warning*) sebelum lonjakan suhu merusak produk farmasi.

---

### 2. Aktor & Hak Akses
* **AUTOMATION AI**: Aktor sistem mandiri yang mengevaluasi stream data, menentukan status keselamatan, dan memicu *Penambahan data laporan*.
* **ADMIN**: Menerima *High Priority Alert* di Dashboard, meninjau rincian insiden, dan memilih keputusan intervensi beserta alasan.
* **Kurir**: Menerima instruksi darurat dari Admin via aplikasi & WhatsApp.

---

### 3. Spesifikasi Evaluasi AI & Decision Flow
#### 3.1 Cabang A - Kondisi Normal (Aman)
* Jika seluruh data telemetry berada dalam rentang toleransi suhu (`temp_min` s/d `temp_max`), AI menetapkan status `IN_TRANSIT_SAFE`.
* Pengiriman berjalan normal tanpa interupsi.

#### 3.2 Cabang B - Kondisi Penyimpangan (Deviation Detected)
* **Pemicu Anomali**:
  1. Suhu kargo melampaui batas maks (misal: > 8.0°C selama > 3 menit).
  2. Kurir menekan tombol *"Lapor Kendala Segel/Fisik"*.
  3. Signal telemetry GPS/Suhu terputus (*Signal Loss*) > 15 menit.
* **Eksekusi AI**:
  1. AI mengubah status pengiriman secara seketika menjadi `DEVIATION_HOLD`.
  2. AI mencatat rincian insiden (*Penambahan data laporan*): timestamp, koordinat lokasi kejadian, dan nilai deviasi suhu.
  3. Sistem memicu **High Priority Push Alert** & pesan WhatsApp darurat ke Admin dan Kurir.

#### 3.3 [Unggulan Differentiator] Predictive Cold-Chain Alert
* Engine AI menghitung tren laju perubahan suhu ($\Delta T / \Delta t$).
* Jika tren menunjukkan kenaikan konsisten (+0.5°C per 2 menit), AI memicu *Early Warning Alert* di Dashboard Admin:  
  📢 *"Peringatan Dini: Suhu kargo naik ke 7.2°C. Diprediksi melampaui batas 8.0°C dalam 5 menit! Rekomendasi: Instruksikan Kurir periksa cold-box."*

---

### 4. Alur Penyelesaian Kendala oleh Admin (Incident Resolution)
1. Admin membuka modal **Incident Resolution** di Dashboard.
2. Admin melihat grafik kurva suhu, lokasi GPS insiden, dan saran rekomendasi AI.
3. Admin memilih salah satu keputusan intervensi:
   * **"Lanjutkan Pengantaran"** (Jika suhu sudah normal kembali).
   * **"Pengalihan Fleet / Cold-Storage"** (Pindahkan kargo ke hub terdekat).
   * **"Retur ke Gudang Pengirim"** (Jika produk mengalami kerusakan fisik/segel).
4. Admin WAJIB memasukkan **Alasan Keputusan (Notes)**.
5. Sistem mencatat keputusan ke *Audit Log* permanen dan meneruskan instruksi ke HP Kurir via WhatsApp.

---

### 5. Kamus Data & Payload Anomali
| Nama Field | Tipe Data | Mandatory | Deskripsi & Aturan Validasi |
| :--- | :--- | :--- | :--- |
| `incident_id` | String (UUID) | Ya | Primary Key unik catatan insiden. |
| `shipment_id` | String (UUID) | Ya | Foreign Key merujuk ke tabel shipment. |
| `incident_type` | Enum | Ya | `TEMP_EXCURSION`, `SEAL_BREACH`, `SIGNAL_LOSS`. |
| `recorded_temp` | Float | Ya | Nilai suhu saat insiden terjadi (°C). |
| `incident_location` | String/JSON | Ya | Koordinat Latitude & Longitude lokasi insiden. |
| `resolution_action` | Enum | Ya* | Decisions: `CONTINUE`, `REROUTE_HUB`, `RETURN_ORIGIN`. |
| `admin_reason_notes` | Text | Ya* | Alasan tertulis keputusan Admin (*Mandatory). |

---

### 6. Aturan Bisnis (Business Rules)
* **BR-04.1**: Penilaian *Condition ?* oleh AUTOMATION AI berjalan otomatis tanpa intervensi manual (< 500 ms per payload).
* **BR-04.2**: Status `DEVIATION_HOLD` **MEMBEKUKAN** alur pengiriman hingga Admin memasukkan keputusan resmi dan alasan tertulis.
* **BR-04.3**: Seluruh riwayat insiden dan alasan keputusan Admin WAJIB disertakan ke dalam modul laporan akhir (FR-05).

---

### 7. Persyaratan Non-Fungsional
* **Kecepatan Deteksi AI**: AI harus mengidentifikasi penyimpangan suhu dalam waktu `< 1 detik` setelah payload diterima.
* **Akurasi Evaluasi Rules Engine**: 99.99% akurat sesuai ambang batas yang ditetapkan dalam konfigurasi produk farmasi.
