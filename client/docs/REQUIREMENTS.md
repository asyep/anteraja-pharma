# Pemetaan seluruh ketentuan resmi

Sumber utama: Study Case Hackathon Anteraja.pdf (16 halaman). Semua halaman dibaca. Tabel ini memisahkan ketentuan umum, tugas Pharma, dan aktivitas presentasi manusia. Dokumen lain tidak menambah kewajiban maupun mengganti aturan resmi.

## Ketentuan umum

| ID | Halaman | Ketentuan | Implementasi / bukti |
|---|---|---|---|
| G01 | 1–2 | Build 13–15 Oktober 2026, demo 16 Oktober | Panduan demo; belum mengaku demo resmi telah berlangsung |
| G02 | 2 | Tim dan pembagian peserta | Pharma; nomor grup berbeda di jadwal halaman 15, konfirmasi ke panitia |
| G03 | 2–4, 13 | Hybrid, pembeli dan kurir memakai HP | Vue responsif, Capacitor Android; APK debug lokal jika build berhasil; status pengujian native di QA |
| G04 | 2–4, 13 | Dashboard Admin | Ringkasan, pengiriman, pemeriksaan, penugasan, kendala, laporan |
| G05 | 3 | Pembeli: tracking/detail/konfirmasi/kendala | /kiriman/:id, kesiapan, konfirmasi penerimaan, laporan kendala |
| G06 | 3 | Kurir: menerima/menolak tugas, alamat/instruksi, update/kendala | ActionPanel + otorisasi WorkflowService |
| G07 | 3 | Admin: membuat, menugaskan, memantau, keputusan, laporan | CreateShipment + WorkflowService + ReportsPage |
| G08 | 3–4 | App + orchestration + automation terhubung | Native Laravel state machine + transactional outbox + scheduler |
| G09 | 4 | Assignment: pesan, tunggu jawaban, terima/tolak, reassignment | assignments, accept/reject, reminder 15 menit, Admin menugaskan ulang |
| G10 | 4, 13–14 | WhatsApp atau Telegram asli dan tautan balik aplikasi | MessageService Telegram, action_url; credential pemasangan menyusul sesuai arahan pengguna. Tes provider menggunakan mock ditandai sebagai tes, bukan bukti pesan nyata |
| G11 | 4, 13 | Tracking dan pemberitahuan pengirim/penerima otomatis | events + messages audience sender/buyer/courier/admin |
| G12 | 13 | Riwayat proses/pesan: pelaku, waktu, keberhasilan kirim | events.actor_name, created_at, reason; messages.sent_at, provider_message_id, status/attempts |
| G13 | 13 | Laporan otomatis ke Admin | ReportService, schedule 17.00 WIB, pesan Admin dan CSV |
| G14 | 14 | Webhook automation | HMAC + timestamp + external event id, database receipts, regression test |
| G15 | 16 | E2E jadi fokus penilaian | Alur lengkap wajib diuji browser terhadap Laravel dan database lokal |
| G16 | 16 | Tim memahami dan dapat menjelaskan sistem | README, ARCHITECTURE, flow, DEMO dan catatan pembicara |
| G17 | 16 | Repository, aplikasi, workflow dan bahan presentasi dikumpulkan 15 Okt 16.30–17.00 | Branch first-mvp pada repository kelompok; source, docs, bukti dan bahan presentasi |

## Ketentuan khusus Pharma

| ID | Halaman | Ketentuan | Lokasi |
|---|---|---|---|
| P01 | 9–10 | Admin membuat kiriman dengan data dan instruksi | PharmaController.create + CreateShipment.vue |
| P02 | 9–10 | Sistem menyiapkan checklist wajib | Packaging/seal/label untuk setiap shipment; tidak ada bypass checklist |
| P03 | 9–10 | Petugas/kurir mengisi pemeriksaan | Inspector terpilih atau Admin sebagai petugas, inspeksi append-only |
| P04 | 9–10 | Checklist tidak lengkap memblokir berangkat | approve dan assign validasi backend; event dispatch_blocked + pesan Admin |
| P05 | 9–10 | Admin menyetujui / menahan dengan alasan | approvals + approve/hold |
| P06 | 9–10 | Yang disetujui boleh masuk penugasan | status approved saja dapat assign |
| P07 | 10 | Admin menugaskan kurir dengan instruksi | assignments + pesan courier dengan handling instructions |
| P08 | 10 | Kurir update dan pembeli diberi kabar | accept/depart/arrive/deliver + outbox otomatis |
| P09 | 9–10 | Kurir menemukan masalah segel dan melaporkan | issue(type=seal) + issue.description |
| P10 | 9–10 | Kendala otomatis menahan, meminta keputusan | held atomik + pesan Admin/Satria/Pembeli/Pengirim |
| P11 | 9–10 | Kurir melapor, Admin memutuskan | Gate server per role; kurir dilarang decide |
| P12 | 10 | Admin memilih tindakan + alasan, sistem mengirim instruksi | return/recheck/resume untuk jenis kendala yang aman; segel/kemasan/suhu tidak boleh resume langsung |
| P13 | 10 | Hasil akhir, pemeriksaan dan keputusan ikut laporan | returned/delivered, completed_at, snapshot full histories + export |
| P14 | 9 | Semua tindakan: nama, waktu, alasan | events append-only termasuk blocked, reminder; role account nama bukan field dari client |
| P15 | 10 | Demo wajib checklist belum lengkap → ditolak → lengkap → disetujui → segel bermasalah → retur | tests/e2e/pharma.spec.ts + backend/tests/Feature/PharmaWorkflowTest.php |
| P16 | 10 | Pesan Pembeli: jadwal, perjalanan, pemeriksaan tambahan, hasil | event created/depart/issue/recheck/return_complete/deliver |
| P17 | 10 | Pesan Kurir: tugas, instruksi, tunggu keputusan, tindakan Admin | MessageService + seluruh event terkait |
| P18 | 10 | Pesan Admin: pemeriksaan belum lengkap, permintaan persetujuan, kendala, laporan harian | inspect/dispatch_blocked/issue + ReportService |

## Dua belas milestone

1. Problem statement dan customer journey: IMPLEMENTATION + ARCHITECTURE.
2. Database dan API dasar: migrasi, route, seed, backend test.
3. Pembuatan kiriman dan tracking awal: create API dan detail.
4. Dashboard dan riwayat status: dashboard API + events.
5. Webhook automation: signed endpoint + regression.
6. Notifikasi pengirim/penerima diuji berhasil: adapter dan pengujian mock tersedia; pesan Telegram nyata menunggu credential. Jangan tandai selesai sebelum sent/provider_message_id tercatat.
7. Fitur khusus Pharma: P01–P18.
8. Laporan otomatis: native scheduler + ReportService.
9. E2E dan final submission: QA + paket source/bukti.
10. Live demo seluruh alur: rehearsal tersedia; presentasi resmi belum berlangsung.
11. Pitch dan dampak bisnis: deck, KPI terukur dari log; presentasi resmi belum berlangsung.
12. Tanya jawab teknis dan penilaian: Q&A disiapkan; penilaian nyata dilakukan panitia.

## Halaman yang bukan kebutuhan Pharma

Halaman 5–6: kasus Satria, penerima tidak tersedia dan retry. Halaman 7–8: Frozen, kesiapan jadwal dan kemasan bocor (sensor tidak wajib). Halaman 11–12: Cargo, 5 paket/partial delivery. Ketiganya dibaca sebagai konteks, tidak ditambahkan sebagai tuntutan Pharma.

## Konflik referensi dan batas yang tetap dipertahankan

BMC menggambarkan strategi B2B dan persona QA; tidak dianggap kontrak tarif, sertifikasi, kampanye aktif, atau data performa nyata. ALUR menampilkan customer membuat dan auto-assign; aplikasi mengikuti official: Admin membuat dan memilih Satria. Rentang suhu khusus ditentukan per kiriman, bukan asumsi semua obat 2–8°C. Tidak ada diagnosis, sensor palsu, lokasi GPS palsu, atau klaim aplikasi sudah memenuhi sertifikasi CDOB.
