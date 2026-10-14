# Product Requirement Document (PRD)
## Pharma Logistics Workflow Architecture
**Sistem**: Pharma Logistics Workflow Architecture (Anteraja NextGen AI)  
**Dokumen**: Product Requirement Document (PRD) - Hackathon Edition  
**Acuan Utama**: Pedoman Hackathon Anteraja x Maxy Academy (Studi Kasus 3: Pharma)  
**Tanggal**: 14 Oktober 2026  
**Status**: Final Draft (Diperbarui & Diselaraskan)

---

### 1. Ringkasan Eksekutif (Executive Summary)

Sistem **Pharma Logistics Workflow Architecture** adalah platform logistik terintegrasi *end-to-end* berbasis **Hybrid Native Automation** yang dirancang khusus untuk memenuhi standar ketat penanganan dan pengiriman produk farmasi (obat-obatan, vaksin, dan produk *cold-chain*). Sistem ini menghubungkan lima entitas operasional utama:
1. **ADMIN** (Logistics Dispatcher / Operations Center)
2. **Checker** (Petugas Gudang / Quality Control Hub)
3. **Vendor / Customer** (Distributor Farmasi, Rumah Sakit, Apotek, atau Pembeli)
4. **Kurir** (Petugas Lapangan / Driver Armada Refitted)
5. **AUTOMATION AI Engine** (Mesin Otomatisasi, Analisis Suhu/Rute, dan Notifikasi Webhook)

**Tujuan Utama Produk:**
* **Menjamin Kepatuhan Standar Farmasi (CPOB/GDP)** melalui verifikasi fisik bertingkat (*Initial Exam*) dan kontrol otorisasi ketat sebelum paket diberangkatkan.
* **Memastikan Integritas Produk Cold-Chain** dengan pemantauan telemetry suhu & GPS secara *real-time* serta deteksi penyimpangan otomatis berbasis AI.
* **Transparansi Operasional & Audit Trail Lengkap** dengan pencatatan otomatis (*siapa, kapan, alasan tindakan*) dan otomatisasi laporan (*Auto Report & BAP PDF*) yang terdistribusi secara paralel via WhatsApp/Telegram.

---

### 2. Keselarasan Pedoman Hackathon (Bare Minimum & Gap Analysis)

Sistem ini memenuhi dan melampaui **Persyaratan Minimal (Bare Minimum)** dalam Pedoman Hackathon Anteraja untuk Studi Kasus **Pharma**:

| Elemen Pedoman Hackathon | Status Pemenuhan PRD Ini | Penjelasan & Peningkatan Unggulan |
| :--- | :---: | :--- |
| **Aplikasi Hybrid (HP & Web)** | ✅ Terpenuhi | Dashboard Web untuk Admin; Aplikasi Mobile Hybrid (PWA/Mobile-friendly Web) untuk Kurir, Checker, dan Customer/Pembeli. |
| **Pemeriksaan & Checklist Wajib** | ✅ Terpenuhi | Prosedur *Initial Exam* oleh Checker (segel, jumlah koli, suhu awal) wajib disetujui Admin sebelum paket dialokasikan ke Kurir. **[Differentiator]**: Dilengkapi *AI Vision Inspection* untuk membaca foto thermometer & segel otomatis. |
| **Persetujuan Admin (Approval Gate)** | ✅ Terpenuhi | Admin memegang otorisasi penuh ("Setujui" / "Tahan"). Kurir hanya dapat ditunjuk jika paket disetujui. |
| **Penanganan Kendala & Hak Akses** | ✅ Terpenuhi | Kurir melaporkan kendala (segel rusak/suhu melonjak) -> Sistem *HOLD* otomatis -> Admin mengambil keputusan beserta alasannya (*Audit Log*). |
| **Workflow Orchestration & Automation** | ✅ Terpenuhi | Engine Orkestrasi (n8n/Make/Custom Engine) mengelola status *waiting for approval*, retries, dan keputusan percabangan. |
| **Pesan Otomatis (WA/Telegram)** | ✅ Terpenuhi | Pesan otomatis terkirim ke Admin, Kurir, dan Customer pada setiap perubahan status penting, dilengkapi *deep-link* aksi. |
| **Audit Log & History** | ✅ Terpenuhi | Setiap aksi (approve/reject/hold) mencatat *user_id*, *timestamp*, *previous_status*, dan *reason_notes* secara *immutable*. |
| **Automated Daily Report** | ✅ Terpenuhi | Kompilasi otomatis ringkasan pengiriman, grafik suhu, dan log insiden menjadi dokumen PDF/A yang didistribusikan paralel. |
| **Live Demo Simulator (Hackathon Feature)** | 🌟 Fitur Unggulan | **Control Panel Simulator**: Fitur khusus demo untuk mensimulasikan lonjakan suhu, kendala segel, atau signal loss secara *live*. |

---

### 3. Stakeholder, Personas & Matriks Hak Akses (RBAC)

#### User Personas & Responsibilities:
1. **ADMIN**: Menginisiasi pengiriman, meninjau hasil *Initial Exam*, menyetujui/menahan pengiriman, menugaskan kurir, mengambil keputusan kendala, dan memantau laporan harian.
2. **Checker**: Menerima tugas pemeriksaan awal di gudang/hub, melakukan pemindaian barcode/QR, memeriksa segel/suhu awal, mengunggah foto bukti, dan mengirimkan hasil ke Admin.
3. **Vendor / Customer**: Mengakses *Monitor Page* (via token/magic-link), memantau posisi live GPS & grafik suhu, menerima notifikasi WA/Telegram, dan mengunduh laporan pengiriman.
4. **Kurir**: Menerima tugas pengiriman beserta instruksi *handling*, mengonfirmasi/menolak tugas, memperbarui status pengiriman (*Changing status*), memancarkan telemetry GPS/Suhu, dan melaporkan kendala di lapangan.
5. **AUTOMATION AI Engine**: Menyerap data telemetry secara *continuous stream*, mengevaluasi kondisi batas toleransi (*Condition ?*), memicu peringatan penyimpangan, mengeksekusi *Penambahan data laporan*, dan menerbitkan *Auto Report*.

#### Matriks Hak Akses (Role-Based Access Control):
| Fitur / Modul | ADMIN | Checker | Vendor / Customer | Kurir | AUTOMATION AI |
| :--- | :---: | :---: | :---: | :---: | :---: |
| **Create Shipment & Setup Handling** | ✅ Write | ❌ | ❌ | ❌ | ❌ |
| **Execute Initial Exam & Upload Proof** | ❌ | ✅ Write | ❌ | ❌ | ❌ |
| **Review Exam & Give Approval (Setujui/Tahan)** | ✅ Write | ❌ | ❌ | ❌ | ❌ |
| **Courier Assignment (Pemilihan Kurir)** | ✅ Write | ❌ | ❌ | ❌ | ❌ |
| **Accept/Reject Assignment** | ❌ | ❌ | ❌ | ✅ Write | ❌ |
| **Update Transit Status & Emit Telemetry** | ❌ | ❌ | ❌ | ✅ Write | ❌ |
| **View Live GPS Tracking & Temp Chart** | ✅ Read | ❌ | ✅ Read | ✅ Read | ✅ Read |
| **Report Field Incident (Melaporkan Masalah)** | ❌ | ❌ | ❌ | ✅ Write | ❌ |
| **Evaluate Anomaly & Trigger Predictive Alert** | ❌ | ❌ | ❌ | ❌ | ✅ Execute |
| **Resolve Incident & Enter Reason Notes** | ✅ Write | ❌ | ❌ | ❌ | ❌ |
| **Generate & Receive Final PDF Report** | ✅ Read | ❌ | ✅ Read | ✅ Read | ✅ Execute |
| **Trigger Demo Simulator (Hackathon Mode)** | ✅ Write | ❌ | ❌ | ❌ | ❌ |

---

### 4. Arsitektur Sistem & Alur Kerja Utama (Workflow Orchestration)

#### Komponen Arsitektur Teknologi:
* **Frontend**:
  * **Admin Web Dashboard**: React / Next.js / Vue.js + Tailwind CSS (Interaktif, Real-time Map, Data Visualizer).
  * **Hybrid Mobile App (Kurir, Checker, Customer)**: PWA / Mobile Web teroptimasi untuk layar smartphone.
* **Backend & Database**:
  * **API Server**: Node.js (Express/NestJS) / Python (FastAPI) untuk REST API & WebSockets.
  * **Database**: PostgreSQL (Relational & JSONB untuk Audit Log / Telemetry payload).
* **Automation & Messaging Layer**:
  * **Workflow Orchestrator**: n8n / Make / Custom Webhook Engine.
  * **Notification Gateway**: WhatsApp Business API / Telegram Bot API (WPPConnect/Baileys/Telegram Bot).
* **AI & Analytics Module**:
  * **Rule Engine & Predictive Analytics**: Python (Pandas/NumPy) untuk evaluasi tren kenaikan suhu.
  * **AI Vision (Optional Differentiator)**: OpenAI GPT-4o-mini / Gemini API / Tesseract OCR untuk verifikasi foto segel & thermometer.

#### Alur Kerja Utama Sistem (End-to-End Steps):
1. **Inisiasi Pengiriman**: Admin mengisi resi, data vendor, kriteria suhu (2-8°C), dan instruksi *handling* -> Sistem menyimpan status `CREATED_AWAITING_EXAM` -> Pemicu otomatis dikirim ke Checker.
2. **Pemeriksaan Awal (Initial Examination)**: Checker memindai QR, mengisi *checklist* fisik, mengunggah foto -> AI Vision mengecek foto (opsional) -> Status berubah `EXAMINED_PENDING_APPROVAL`.
3. **Keputusan Persetujuan Admin**: Admin meninjau hasil *checklist* pada Monitor Page -> Admin memilih **"Setujui"** -> Status `APPROVED_READY_FOR_ASSIGNMENT` (jika "Tahan", alur terhenti dan notifikasi dikirim ke gudang).
4. **Penugasan & Diterima Kurir**: Admin menunjuk Kurir -> WA/Telegram terkirim ke Kurir -> Kurir menekan "Terima Tugas" -> Status `ASSIGNED_COURIER_ACCEPTED`.
5. **Perjalanan & Telemetry Stream**: Kurir memulai pengantaran (`IN_TRANSIT`) -> Telemetry GPS & Suhu dipancarkan tiap 10 detik -> Monitor Page Admin & Customer memperbarui posisi di peta dan grafik suhu.
6. **Deteksi AI & Penanganan Penyimpangan**:
   * *Alur Normal*: Suhu tetap 2-8°C -> Pengiriman tiba (`DELIVERED`) -> Customer konfirmasi penerimaan.
   * *Alur Penyimpangan (Anomaly)*: Suhu melonjak > 8°C atau Kurir melaporkan segel rusak -> AUTOMATION AI menetapkan status `DEVIATION_HOLD` -> Notifikasi *High Priority Alert* dikirim ke Admin & WA Kurir -> Admin memasukkan Keputusan + Alasan Tindakan -> Alur berlanjut sesuai keputusan Admin.
7. **Penerbitan Laporan & Penutupan (Closure)**: System mengompilasi seluruh riwayat ke PDF *Laporan Pengiriman & BAP* -> Didistribusikan secara paralel via WA/Telegram/Email -> Status berubah menjadi `COMPLETED_CLOSED`.

---

### 5. Rencana Fitur & Cakupan Lengkap (FR Overview)

Sistem terdiri dari 6 Modul Utama yang diperinci dalam dokumen **FRD**:
* **FR-01: Manajemen Pengiriman & Inisiasi (Shipment Management)**
* **FR-02: Pemeriksaan Barang & AI Vision (Initial Examination)**
* **FR-03: Penugasan Kurir, Live Tracking GPS & WhatsApp Messaging**
* **FR-04: Automasi AI, Deteksi Penyimpangan & Predictive Cold-Chain**
* **FR-05: Pelaporan Otomatis, Berita Acara (BAP) & Penutupan Pengiriman**
* **FR-06: Control Panel Simulator & Demo Mode (Special Hackathon Feature)**

---

### 6. Persyaratan Non-Fungsional (NFR) & Compliance

1. **Akurasi & Integritas Data (GDP / CPOB Compliance)**:
   * Seluruh catatan inspeksi, keputusan persetujuan, dan riwayat kendala bersifat *immutable* (tidak dapat diubah setelah disubmit).
   * Setiap log mencatat `user_id`, `role`, `timestamp` (ISO 8601), dan `ip/gps_coordinates`.
2. **Performa & Real-time Responsiveness**:
   * Latensi pemutakhiran koordinat GPS & suhu di Monitor Page < 2 detik.
   * Waktu eksekusi evaluasi AI terhadap *telemetry payload* < 300 ms.
   * Waktu generasi PDF Laporan Akhir < 3 detik.
3. **Usability & Mobile Optimization**:
   * Antarmuka Kurir dan Checker dirancang dengan tombol besar (*thumb-friendly*) dan mode pemindaian kamera cepat.
   * Dukungan *Offline Buffering*: Data *checklist* dan koordinat GPS tersimpan sementara di lokal jika sinyal terputus, dan otomatis disinkronkan (*auto-sync*) saat jaringan pulih.

---

### 7. Peta Jalan Pengembangan (3-Day Hackathon Roadmap)

* **Day 1: Design, Database & Inisiasi**
  * Finalisasi Skema Database (Tabel Shipments, Checklists, Assignments, Telemetry, AuditLogs).
  * REST API Setup & Admin Dashboard (Create Shipment & Initial Exam Monitor Page).
  * Checker Terminal App (Form Checklist & Upload Foto).
* **Day 2: Kurir App, Live GPS Tracking & Automasi WA/Telegram**
  * Aplikasi Kurir (Daftar Tugas, Terima/Tolak, Update Status).
  * Integration Live GPS & Real-time Temperature Charting.
  * n8n / Webhook Orchestration & Bot WA/Telegram Alerts.
* **Day 3: Engine AI Anomaly, Auto-Report & Demo Simulator**
  * AUTOMATION AI Engine (Rule evaluation & Anomaly Hold).
  * Auto PDF Generator (Reportment & BAP) + Parallel Distribution.
  * Control Panel Telemetry Simulator untuk Live Demo.
  * End-to-End Testing & Repository Submission (Pukul 16.30–17.00).
* **Day 4: Live Demo, Pitching & Final Judging**
  * Presentasi 45 Menit (5 m Problem/Solution, 5 m Architecture, 20 m Live Demo, 5 m Business Impact, 10 m Q&A).
