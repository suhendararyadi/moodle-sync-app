# E-UJIAN Sync Web App

Aplikasi web berbasis PHP untuk sinkronisasi data ujian semi-offline antara server Moodle pusat (VPS) dan server lokal di ruang kelas.

## Fitur Utama
- **Sync Kursus & Siswa**: Tarik data kursus dan enroll siswa secara otomatis.
- **Sync Cohort (Rombel)**: Sinkronisasi rombongan belajar agar siswa terkelompok dengan benar.
- **Sync Quiz**: Tarik bank soal dan konfigurasi ujian terbaru.
- **Upload Hasil**: Kirim nilai ujian siswa kembali ke server pusat.
- **Server Monitoring**: Cek status koneksi ke master dan lokal secara realtime.

---

## 🚀 Panduan Deployment

### 1. Persiapan Awal (Server Master & Lokal)

1. **Install Plugin Master**:
   - Pastikan plugin `local_eujian.zip` sudah terinstall di Moodle Master (VPS).
   - Lokasi plugin: `Site administration > Plugins > Install plugins`.
   - Pastikan service `E-UJIAN` aktif di `Site administration > Server > Web services > External services`.

2. **Generate Token**:
   - **Master**: Buat token untuk user admin/manager di Moodle Master.
     - `Site administration > Server > Web services > Manage tokens`.
     - Pilih service **E-UJIAN**.
   - **Lokal**: Buat token untuk user admin di Moodle Lokal (Server Kelas).
     - `Site administration > Server > Web services > Manage tokens`.
     - Pilih service **Moodle mobile web service** (atau buat service baru dengan permission full).

---

### 2. Deployment di Server Lokal (Laragon / XAMPP)

Jika server kelas menggunakan Laragon atau XAMPP di Windows:

1. **Copy Source Code**:
   - Salin folder `eujian-sync` ke dalam folder `www` (Laragon) atau `htdocs` (XAMPP).
   - Contoh: `C:\laragon\www\eujian-sync`.

2. **Akses Aplikasi**:
   - Buka browser dan akses: `http://localhost/eujian-sync/` (atau `http://eujian-sync.test/` jika auto virtual host aktif di Laragon).

3. **Konfigurasi**:
   - Saat pertama kali dibuka, masuk ke menu **Pengaturan**.
   - Isi **URL Master** & **Token Master**.
   - Isi **URL Lokal** (misal: `http://localhost/moodle`) & **Token Lokal**.
   - Klik **Simpan Konfigurasi**.

4. **Tips PHP**:
   - Pastikan ekstensi `curl` dan `mbstring` aktif di `php.ini`.
   - Set `max_execution_time` di `php.ini` ke angka tinggi (misal 300 detik) untuk proses sync yang lancar, meskipun aplikasi sudah mencoba mengaturnya secara otomatis.

---

### 3. Deployment Menggunakan Docker

Jika Moodle lokal berjalan di dalam container Docker (seperti saat development):

1. **Copy Source Code ke Container**:
   Gunakan perintah `docker cp` untuk menyalin folder aplikasi ke dalam container Moodle.
   ```bash
   docker cp ./eujian-sync <nama_container_moodle>:/var/www/html/
   ```

2. **Atur Permission**:
   Masuk ke container dan pastikan web server bisa menulis file `config.json`.
   ```bash
   docker exec -it <nama_container_moodle> bash
   chown -R www-data:www-data /var/www/html/eujian-sync
   chmod -R 755 /var/www/html/eujian-sync
   ```

3. **Akses Aplikasi**:
   - Buka browser: `http://localhost:8080/eujian-sync/` (sesuaikan port Docker Anda).

---

## 🛠 Troubleshooting

- **Error: SSE connection lost / timeout**:
  - Cek konfigurasi PHP `max_execution_time`.
  - Pastikan tidak ada firewall/proxy yang memblokir koneksi HTTP stream (EventSource).

- **Error: Invalid parameter value detected**:
  - Biasanya terjadi karena token tidak memiliki permission yang cukup.
  - Cek permission user token di Moodle (baik master maupun lokal). Pastikan role memiliki akses ke fungsi-fungsi Core Moodle (create users, enrol users, dll).

- **Tampilan berantakan**:
  - Aplikasi menggunakan Tailwind CSS via CDN. Pastikan server lokal memiliki koneksi internet saat memuat halaman pertama kali untuk cache CSS, atau download library CSS secara offline jika server benar-benar terisolasi (perlu langkah tambahan).
