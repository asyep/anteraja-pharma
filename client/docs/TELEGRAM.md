# Pasang notifikasi Telegram

Bot sudah terintegrasi; kamu cukup memasang credential lokal ketika siap. Tidak ada pesan yang diklaim terkirim sebelum Bot API merespons `ok=true`.

## Buat dan hubungkan akun uji

1. Buka Telegram, pilih akun resmi @BotFather, jalankan /newbot dan buat bot kelompok. Simpan token langsung pada `TELEGRAM_BOT_TOKEN` dalam backend/.env. Jangan kirim ke chat, source frontend, atau screenshot.
2. Masing-masing akun Admin, Satria, Pembeli, dan Pengirim membuka bot lalu mengirim /start. Bot tidak bisa memulai percakapan privat sebelum langkah ini.
3. Jalankan dari root: `node tools/telegram.mjs chats`. Script menyimpan daftar chat yang menghubungi bot ke `.private/telegram-chats.json`, tanpa mencetak token. Buka file privat untuk mendapatkan chat ID.
4. Isi TELEGRAM_ADMIN_CHAT_ID, TELEGRAM_COURIER_CHAT_ID, TELEGRAM_BUYER_CHAT_ID, TELEGRAM_SENDER_CHAT_ID pada backend/.env. Untuk demo awal boleh satu akun uji menerima seluruh audience, tetapi uji yang mewakili tiap peran lebih jelas.
5. Jalankan `node tools/telegram.mjs info` untuk mengecek identitas bot. Setelah mengubah .env, restart launcher supaya scheduler memakai konfigurasi terbaru. Jangan cache config sebelum pengaturan selesai.
6. Jalankan command worker dari folder backend:

```powershell
& C:\xampp\php\php.exe artisan pharma:messages --limit=10
```

Menu Notifikasi menampilkan status sebenarnya. Pesan lama yang belum punya tujuan dapat memakai chat ID role dari .env. Jika akun sudah mempunyai chat ID berbeda di menu, tujuan akun itu diprioritaskan.

## Tautan kembali ke aplikasi

FRONTEND_URL harus mengarah ke aplikasi yang bisa dibuka penerima. `localhost` di HP berarti HP itu sendiri, bukan laptop. Untuk demo satu Wi-Fi gunakan IP laptop, misalnya http://172.16.0.198:3170. Link HTTP lokal dikirim sebagai teks. Untuk link tombol Telegram dan penggunaan di luar jaringan lokal, pasang aplikasi/tunnel HTTPS dan ubah FRONTEND_URL. Link tidak memberi akses otomatis: penerima tetap masuk dengan perannya.

## Webhook Telegram

Jika FRONTEND_URL sudah HTTPS publik, jalankan `node tools/telegram.mjs webhook`. Script mendaftarkan endpoint `/api/webhooks/telegram` dengan secret server. Telegram mengirim header X-Telegram-Bot-Api-Secret-Token; endpoint menolak secret salah dan menduplikasi update berdasarkan update_id.

Saat webhook aktif, getUpdates tidak tersedia. Jangan mencoba menggunakan keduanya bersamaan. Webhook hanya mencatat update, tidak menjadikan chat ID sebagai akses ke keputusan Admin. Penerimaan tugas dan keputusan tetap melalui aplikasi terautentikasi.

## Webhook automation

POST /api/webhooks/automation menerima JSON event_id, type (`daily_report` atau `assignment_reminders`), serta date untuk daily_report. Signature adalah HMAC-SHA256 dari `timestamp + '.' + rawBody`, memakai AUTOMATION_WEBHOOK_SECRET. Header: X-Pharma-Timestamp dan X-Pharma-Signature. Timestamp maksimal berbeda 5 menit. Event yang sama mengembalikan hasil receipt lama.

Jalankan `node tools/automation.mjs` dari root untuk menguji request HTTP signed terhadap server lokal. Credential tidak muncul pada output. Tidak perlu n8n/Docker untuk memenuhi orkestrasi native.

## Gangguan dan pengujian nyata

- Token/chat belum diisi: `unconfigured`, bukan `sent`.
- Rate limit/koneksi gagal: `retry` dengan backoff, maksimal 5 percobaan.
- Token/chat ditolak provider: `failed`, Admin bisa Jadwalkan ulang setelah memperbaiki konfigurasi.
- Berhasil: sent_at + provider_message_id tercatat. Simpan screenshot Telegram akun uji serta riwayat aplikasi untuk bukti milestone 6.
- Jangan unggah screenshot token atau data kesehatan nyata.

Tes otomatis provider memakai Http::fake dengan label test, untuk menguji penanganan respons dan kegagalan tanpa mengirim spam. Itu bukan pengganti bukti pengiriman nyata ke akun Telegram.

Riwayat contoh dari seed diberi status `demo` dan tidak dikirim ketika token dipasang. Pesan baru berstatus pending tetap diprioritaskan; pesan tanpa konfigurasi dicoba ulang setelah 15 menit agar tidak menahan antrean baru.
