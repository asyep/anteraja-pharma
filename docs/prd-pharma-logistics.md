# Product Requirement Document (PRD)
## Pharma Logistics Workflow Architecture

**Dokumen**: Product Requirement Document (PRD)  
**Sistem**: Pharma Logistics Workflow Architecture  
**Sumber Terkait**: `anterajapharma.drawio.png` [1]  
**Tanggal**: 14 Oktober 2026  
**Status**: Draft Final  

---

### 1. Ringkasan Eksekutif (Executive Summary)
Sistem **Pharma Logistics Workflow Architecture** dirancang untuk mengelola dan memantau alur pengiriman barang farmasi secara *end-to-end*. Sistem ini menghubungkan lima entitas utama—**ADMIN**, **Checker**, **Vendor/Customer**, **Kurir**, dan **Artificial Intellegent**—dalam satu rantai operasional yang terintegrasi mulai dari pemenuhan pengiriman awal hingga pembuatan laporan otomatis dan penutupan status pengiriman [1].

---

### 2. Stakeholder & Aktor Sistem (User Personas & Roles)

1. **ADMIN**
   - Memulai pembuatan pengiriman barang [1].
   - Mengakses dan memantau status pengiriman pada *Monitor Page* [1].
   - Melakukan penugasan dan pemilihan kurir (*Pemilihan Kurir*) [1].
   - Melakukan pemantauan posisi pengiriman secara *real-time* via *tracking gps* [1].
   - Menerima hasil *Laporan* pengiriman akhir [1].

2. **Checker**
   - Menerima instruksi pemeriksaan barang setelah pengiriman dibuat [1].
   - Melakukan pemeriksaan fisik/awal barang (*Initial Exam*) [1].
   - Mengirimkan hasil pemeriksaan awal ke *Monitor Page* milik ADMIN [1].

3. **Vendor / Customer**
   - Mengakses *Monitor Page* untuk memantau status awal pengiriman [1].
   - Memantau pergerakan barang secara *real-time* melalui *tracking gps* [1].
   - Menerima dokumen *Laporan* akhir pengiriman [1].

4. **Kurir**
   - Menerima konfirmasi penugasan (*terpilih*) [1].
   - Memperbarui status operasional pengiriman (*Changing status*) [1].
   - Menjalankan proses pengiriman terpantau (*Running Monitor Processed*) [1].
   - Mengirimkan telemetry lokasi untuk *tracking gps* [1].
   - Menerima salinan *Laporan* pengiriman [1].

5. **Artificial Intellegent (AUTOMATION AI)**
   - Menerima data *stream* dari proses *Running Monitor Processed* milik Kurir [1].
   - Memproses logika keputusan kondisi pengiriman (*Condition ?*) [1].
   - Menentukan status pengiriman: `aman` atau `Penyimpangan` [1].
   - Melakukan aksi *Penambahan data laporan* apabila terdeteksi penyimpangan [1].
   - Menghasilkan modul laporan otomatis (*Reportment*) [1].

---

### 3. Persyaratan Fungsional (Functional Requirements)

#### FR-01: Manajemen Pengiriman (Shipment Management)
- **FR-01.1**: Sistem harus menyediakan fungsi `Create Shipments` bagi ADMIN untuk menginisiasi pengiriman [1].
- **FR-01.2**: Saat pengiriman dibuat, sistem secara otomatis meneruskan tugas `Initial Exam` ke Checker dan menampilkan `Monitor Page` bagi Vendor/Customer serta ADMIN [1].

#### FR-02: Pemeriksaan Barang (Initial Examination)
- **FR-02.1**: Checker melakukan fungsi `Initial Exam` untuk memeriksa kondisi awal paket farmasi [1].
- **FR-02.2**: Hasil dari `Initial Exam` harus terhubung langsung ke `Monitor Page` milik ADMIN untuk ditinjau sebelum pemilihan kurir [1].

#### FR-03: Penugasan Kurir & Pelacakan (Courier Assignment & Tracking)
- **FR-03.1**: ADMIN dapat melakukan `Pemilihan Kurir` berdasarkan data dari `Monitor Page` [1].
- **FR-03.2**: Kurir yang ditunjuk berubah status menjadi `terpilih` dan melakukan pembaruan status (`Changing status`) [1].
- **FR-03.3**: Kurir menjalankan alur `Running Monitor Processed` selama pengiriman berlangsung [1].
- **FR-03.4**: Sistem mempublikasikan data lokasi `tracking gps` dari Kurir agar dapat diakses oleh ADMIN dan Vendor/Customer [1].

#### FR-04: Otomatisasi AI & Deteksi Penyimpangan (AI Automation & Anomaly Detection)
- **FR-04.1**: Modul `AUTOMATION AI` secara otomatis menerima masukan data dari `Running Monitor Processed` [1].
- **FR-04.2**: AI mengevaluasi kondisi pengiriman melalui gerbang keputusan `Condition ?` [1]:
  - **Kondisi Ya (Sesuai Standar)**: Ditetapkan status `aman` [1].
  - **Kondisi Tidak (Penyimpangan)**: Ditetapkan status `Penyimpangan`, yang memicu alur `Penambahan data laporan` untuk mencatat detail insiden/penyimpangan [1].

#### FR-05: Pelaporan & Penutupan (Reporting & Closure)
- **FR-05.1**: Modul `Reportment` mengompilasi seluruh hasil pengawasan (baik status `aman` maupun `Penyimpangan` dengan data laporan tambahan) [1].
- **FR-05.2**: Modul `Reportment` menerbitkan dokumen `Laporan` resmi dan mendistribusikannya secara paralel kepada Kurir, ADMIN, dan Vendor/Customer [1].
- **FR-05.3**: Sistem menyelesaikan pengiriman dan mengakhiri alur pada titik `Selesai` [1].

---

### 4. Alur Kerja Sistem (System Workflow Diagram Mapping)

1. **Inisiasi (Start)** $\rightarrow$ **ADMIN** melakukan `Create Shipments` [1].
2. **Pemeriksaan & Pemantauan Awal** $\rightarrow$ `Create Shipments` memicu `Initial Exam` pada **Checker** dan `Monitor Page` pada **Vendor/Customer** [1].
3. **Persetujuan Admin** $\rightarrow$ Hasil `Initial Exam` dikirim ke `Monitor Page` **ADMIN**, yang kemudian melakukan `Pemilihan Kurir` [1].
4. **Eksekusi Kurir** $\rightarrow$ Kurir menjadi `terpilih`, mengubah status (`Changing status`), dan menjalankan pengiriman (`Running Monitor Processed`) [1].
5. **Pelacakan GPS** $\rightarrow$ Selama `Running Monitor Processed`, data `tracking gps` dibagikan ke **ADMIN** dan **Vendor/Customer** [1].
6. **Analisis AI** $\rightarrow$ Data pengiriman masuk ke `AUTOMATION AI` $\rightarrow$ Evaluasi `Condition ?` [1]:
   - Jika **Sesuai (Ya)** $\rightarrow$ Status `aman` $\rightarrow$ `Reportment` [1].
   - Jika **Penyimpangan (Tidak)** $\rightarrow$ Status `Penyimpangan` $\rightarrow$ `Penambahan data laporan` $\rightarrow$ `Reportment` [1].
7. **Penerbitan Laporan** $\rightarrow$ `Reportment` menghasilkan `Laporan` untuk **Kurir**, **ADMIN**, dan **Vendor/Customer** [1].
8. **Pengakhiran** $\rightarrow$ Proses berakhir pada status `Selesai` [1].

---

### 5. Matriks Hak Akses & Fitur Aktor

| Fitur / Modul | ADMIN | Checker | Vendor / Customer | Kurir | AUTOMATION AI |
| :--- | :---: | :---: | :---: | :---: | :---: |
| **Create Shipments** |  [1] | ❌ | ❌ | ❌ | ❌ |
| **Initial Exam** | ❌ |  [1] | ❌ | ❌ | ❌ |
| **Monitor Page** |  [1] | ❌ |  [1] | ❌ | ❌ |
| **Pemilihan Kurir** |  [1] | ❌ | ❌ | ❌ | ❌ |
| **Changing Status / Running Process** | ❌ | ❌ | ❌ |  [1] | ❌ |
| **Tracking GPS (View)** |  [1] | ❌ |  [1] |  (Source) [1] | ❌ |
| **Condition Evaluation & Anomaly Handling** | ❌ | ❌ | ❌ | ❌ |  [1] |
| **Terima Laporan Akhir** |  [1] | ❌ |  [1] |  [1] | ❌ |

---

### 6. Persyaratan Non-Fungsional (Non-Functional Requirements)

1. **Integrasi & Otomatisasi Real-Time**
   - Transisi data dari *Running Monitor Processed* ke *AUTOMATION AI* serta pembaruan *tracking gps* harus berjalan secara real-time tanpa delay signifikan [1].
2. **Keterandalan Evaluasi AI**
   - Logika keputusan `Condition ?` harus mampu mengidentifikasi penyimpangan secara akurat dan secara otomatis menambahkan data laporan (*Penambahan data laporan*) saat terjadi anomali [1].
3. **Transparansi Stakeholder**
   - Semua entitas utama (*ADMIN*, *Vendor/Customer*, dan *Kurir*) menerima salinan *Laporan* akhir yang konsisten dari modul *Reportment* [1].
