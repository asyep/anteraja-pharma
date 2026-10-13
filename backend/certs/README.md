# CA untuk koneksi database TLS

`cacert.pem` adalah bundle CA publik dari https://curl.se/ca/cacert.pem (Mozilla CA collection). File ini bukan credential dan tidak memuat private key. Konfigurasi `DB_SSL=true` memakai bundle ini untuk memverifikasi server TiDB. Jangan menonaktifkan verifikasi sertifikat untuk mengatasi gangguan koneksi; perbarui bundle dari sumber resminya.
