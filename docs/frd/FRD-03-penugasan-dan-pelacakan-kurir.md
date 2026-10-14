# Functional Requirement Document (FRD)
## Modul FR-03: Penugasan Kurir, Live Tracking & Messaging (Courier Assignment)

**Sistem**: Pharma Logistics Workflow Architecture (Anteraja NextGen AI)  
**Dokumen Modul**: FR-03 - Penugasan & Pelacakan Kurir  
**Acuan**: Pedoman Hackathon Anteraja x Maxy Academy (Group 3 - Pharma)  
**Tanggal**: 14 Oktober 2026  
**Status**: Final Draft  

---

### 1. Deskripsi Fitur & Tujuan Bisnis
Modul **FR-03 (Penugasan & Pelacakan Kurir)** menangani alokasi kurir oleh Admin untuk paket yang telah disetujui, konfirmasi penugasan oleh Kurir, pembaruan status operasional pengiriman, pemancaran *telemetry* lokasi & suhu, serta penyajian *Live GPS Map Tracking* secara *real-time* kepada Admin dan Customer.

Selain itu, modul ini terintegrasi dengan **WhatsApp / Telegram Messaging Engine** yang mengirimkan pemberitahuan otomatis pada setiap perubahan status pengiriman.

---

### 2. Aktor & Hak Akses
* **ADMIN**: Memilih dan menugaskan kurir terdaftar melalui Dashboard Web.
* **Kurir**: Menerima/menolak penugasan, mengubah status (`Start Delivery`, `Arrived`), memancarkan data GPS/suhu *background*.
* **Vendor / Customer**: Melihat pergerakan kurir dan posisi terkini pada peta *Live GPS Tracking*.

---

### 3. Spesifikasi Alur Kerja (Use Case Detail)
#### 3.1 Alur Penugasan & Messaging
1. **Pemilihan Kurir**: Admin membuka antrean paket berstatus `APPROVED_READY_FOR_ASSIGNMENT` dan memilih Kurir terdaftar.
2. **Pesan Otomatis Penugasan**: Sistem mengirimkan pesan **WhatsApp / Telegram** otomatis ke Kurir berisi detail tugas, nama produk farmasi, dan tautan konfirmasi.
3. **Konfirmasi Kurir**: Kurir membuka aplikasi HP dan menekan **"Terima Tugas"** -> Status berubah menjadi `ASSIGNED_ACCEPTED`.

#### 3.2 Alur Pengantaran & Live Tracking GPS
1. **Mulai Pengantaran**: Kurir menekan tombol **"Mulai Pengantaran"** pada aplikasi -> Status berubah menjadi `IN_TRANSIT`.
2. **Pesan Otomatis ke Customer**: Bot otomatis mengirimkan pesan WA ke Customer: *"Paket farmasi Anda dalam perjalanan oleh Kurir [Nama]. Pantau lokasi real-time di: [Link Tracking]"*.
3. **Pemancaran Telemetry**: Selama perjalanan, aplikasi Kurir memancarkan *payload telemetry* (Latitude, Longitude, Speed, Suhu Kargo) setiap 10 detik.
4. **Pembaruan Peta**: Peta *Monitor Page* pada Admin dan Customer memperbarui marker posisi Kurir serta kurva grafik suhu secara *near real-time* (< 2 detik delay).

---

### 4. Kamus Data & Telemetry Payload
#### 4.1 Telemetry Payload Structure (JSON)
```json
{
  "shipment_id": "8f1a2b3c-4d5e-6f7a-8b9c-0d1e2f3a4b5c",
  "courier_id": "KURIR-ANTERAJA-007",
  "latitude": -6.2088,
  "longitude": 106.8456,
  "current_temp": 4.5,
  "speed_kmh": 28.4,
  "timestamp": "2026-10-14T10:30:00Z"
}
```

---

### 5. Aturan Bisnis (Business Rules)
* **BR-03.1**: Admin hanya dapat menugaskan kurir yang berstatus *Active* dan tidak sedang membawa kargo *Cold-Chain* melebihi kapasitas.
* **BR-03.2**: Aplikasi Kurir wajib memiliki izin *Background Location Permission* untuk tetap memancarkan GPS saat layar HP terkunci.
* **BR-03.3**: Setiap pembaruan status utama (`ASSIGNED`, `IN_TRANSIT`, `DELIVERED`) WAJIB memicu pemicuan pesan WhatsApp/Telegram secara otomatis.

---

### 6. Persyaratan UI/UX & Antarmuka
* **Admin Dashboard - Map View**: Peta interaktif Leaflet/Google Maps yang menampilkan marker posisi kurir, rute perjalanan, serta indikator suhu warna hijau (2-8°C).
* **Kurir Hybrid Mobile App**: Tampilan ramah pengemudi (*driver-friendly interface*) dengan tombol aksi besar **"Mulai Pengantaran"** dan **"Lapor Kendala"**.

---

### 7. Persyaratan Non-Fungsional
* **GPS Buffering / Offline Sync**: Jika sinyal seluler kurir terputus di jalan, data koordinat & suhu disimpan di penyimpanan lokal HP dan di-sync otomatis saat sinyal kembali stabil.
* **Latensi Peta**: Pembaruan koordinat GPS pada peta Customer harus memiliki delay `< 2 detik`.
