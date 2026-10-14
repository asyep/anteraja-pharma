# Functional Requirement Document (FRD)
## Modul FR-06: Control Panel Simulator & Hackathon Demo Mode

**Sistem**: Pharma Logistics Workflow Architecture (Anteraja NextGen AI)  
**Dokumen Modul**: FR-06 - Control Panel Simulator (Hackathon Special)  
**Acuan**: Pedoman Hackathon Anteraja x Maxy Academy (Group 3 - Pharma)  
**Tanggal**: 14 Oktober 2026  
**Status**: Final Draft  

---

### 1. Deskripsi Fitur & Tujuan Bisnis
Modul **FR-06 (Control Panel Simulator & Demo Mode)** merupakan panel kendali simulasi khusus yang dibangun untuk kebutuhan **Live Demo Day 4 (20 Menit Presentasi)**. Modul ini memungkinkan tim untuk memperagakan alur penyimpangan suhu, kendala segel, pemicuan pesan WhatsApp/Telegram otomatis, serta penutupan laporan secara *real-time* tanpa harus menunggu kurir berjalan secara fisik di lapangan.

Fitur ini menjamin presentasi 20 menit berjalan lancar, interaktif, dan teruji di depan para juri hackathon.

---

### 2. Aktor & Hak Akses
* **Presenter / Demo Operator**: Memiliki akses ke tombol-tombol pemicu simulasi pada Control Panel tersembunyi.
* **Juri / Mentor (Audience)**: Menyaksikan efek pemicuan simulasi secara *live* pada Dashboard Admin dan pesan WhatsApp/Telegram yang masuk ke HP uji.

---

### 3. Fitur Control Panel Simulator
Modul simulator menyediakan kontrol berikut:

#### 3.1 Telemetry Temperature Spike Simulator
* **Komponen UI**: Slider Suhu (°C) & Tombol *"Simulasikan Lonjakan Suhu 12.5°C"*.
* **Efek Sistem**:
  * Mengirimkan payload telemetry buatan dengan nilai suhu 12.5°C ke engine AI.
  * Memicu alur FR-04: Status berubah menjadi `DEVIATION_HOLD`, alarm merah berbunyi pada Dashboard Admin, dan pesan WA peringatan darurat terkirim secara instan.

#### 3.2 Signal Loss Simulator
* **Komponen UI**: Tombol *"Simulasikan Terputus Sinyal 15 Menit"*.
* **Efek Sistem**: Memicu deteksi *Telemetry Disruption* pada AI, menandai kurir berstatus offline, dan membuat catatan insiden otomatis pada log Admin.

#### 3.3 Trigger Instant WhatsApp/Telegram Test
* **Komponen UI**: Input Nomor HP Uji Juri & Tombol *"Test Kirim Pesan WA"*.
* **Efek Sistem**: Memverifikasi integrasi n8n/WPPConnect dengan langsung mengirimkan contoh pesan notifikasi pengiriman ke HP juri.

#### 3.4 Fast-Forward Timeline Simulator
* **Komponen UI**: Dropdown Kecepatan Simulasi (`1x`, `10x`, `60x`).
* **Efek Sistem**: Mempercepat pergerakan kurir pada peta GPS dari durasi pengiriman asli 2 jam menjadi gerakan cepat 30 detik untuk kebutuhan demo.

---

### 4. Kamus Data & Simulator Controls
| Nama Kontrol | Tipe UI | Nilai Input | Aksi Pemicu |
| :--- | :--- | :--- | :--- |
| `sim_temp_slider` | Range Slider | -10°C s/d +40°C | Mengirimkan payload telemetry suhu buatan. |
| `trigger_seal_breach` | Button | True/False | Memicu laporan kendala segel rusak dari Kurir. |
| `trigger_signal_drop` | Button | True/False | Memutus koneksi stream telemetry kurir. |
| `test_wa_number` | String | Nomor HP (+62) | Mengirimkan pesan tes notifikasi WA. |

---

### 5. Aturan Bisnis & Safeguards
* **BR-06.1**: Modul FR-06 **HANYA AKTIF** apabila lingkungan aplikasi berada dalam mode `ENVIRONMENT=DEMO` atau `ENVIRONMENT=DEVELOPMENT`.
* **BR-06.2**: Penggunaan tombol simulator WAJIB mencatat log simulasi khusus agar tidak tercampur dengan data produksi asli.

---

### 6. Persyaratan UI/UX
* **Floating Demo Panel**: Floating widget di pojok kanan bawah Dashboard Admin (dapat di-collapse/expand) berisi tombol-tombol pemicu skenario demo.
