# Hybrid Android untuk demo

Frontend Vue dikemas dengan Capacitor 7. APK debug berhasil dibangun menggunakan Java 21, Android SDK yang ada di laptop, dan Gradle 8.11.1. Ukuran awal sekitar 5,5 MB. Build berhasil tidak sama dengan pengujian pada HP fisik; hasil pengujian perangkat harus dicatat terpisah.

## Memakai APK lokal

APK disediakan di output/anteraja-pharma-debug.apk. Mode demo menggunakan server Vite laptop: http://172.16.0.198:3170 pada saat build. Laptop menjalankan run.bat, MySQL aktif, dan HP berada pada Wi-Fi yang sama. Jangan membuka localhost di HP untuk mengakses laptop.

Jika alamat IP berubah, set PHARMA_MOBILE_URL sebelum sync dan rebuild. Config server.url dan cleartext hanya untuk demo lokal, bukan konfigurasi produksi. Tidak ada password atau token yang dibundel ke APK.

## Build ulang

Dari root aplikasi:

```powershell
$env:PHARMA_MOBILE_URL='http://172.16.0.198:3170'
npm run mobile:sync
```

Kemudian dari folder android:

```powershell
$env:ANDROID_HOME='D:\Tools\Android Studio\sdk'
& "$env:JAVA_HOME\bin\java.exe" -classpath 'gradle/wrapper/gradle-wrapper.jar' org.gradle.wrapper.GradleWrapperMain assembleDebug --no-daemon
```

Jalur Java langsung dipakai untuk menghindari masalah launcher batch Windows pada folder yang mengandung karakter &. APK ada pada android/app/build/outputs/apk/debug/app-debug.apk.

## Untuk produksi

Jangan menyalin mode live reload HTTP lokal sebagai konfigurasi produksi. Gunakan domain HTTPS, konfigurasi autentikasi mobile yang benar, restriction origin, review cookie/CORS, build release ter-sign, lalu uji pada perangkat nyata. API Laravel dan database tetap berada pada server, bukan dalam APK. Hindari database root tanpa password untuk hosting.
