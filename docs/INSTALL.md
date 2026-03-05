# Panduan Instalasi E-UJIAN Sync

Dokumen ini menjelaskan langkah-langkah instalasi lengkap sistem E-UJIAN Sync, mulai dari pemasangan plugin Moodle, konfigurasi Web Service, hingga deployment Web App menggunakan Docker atau Laragon (Windows).

---

## Daftar Isi

1. [Prasyarat](#1-prasyarat)
2. [Instalasi Plugin Moodle (local_eujian)](#2-instalasi-plugin-moodle-local_eujian)
3. [Konfigurasi Web Service Moodle](#3-konfigurasi-web-service-moodle)
4. [Daftar Fungsi Web Service](#4-daftar-fungsi-web-service)
5. [Deployment: Docker (Full Stack)](#5-deployment-docker-full-stack)
6. [Deployment: Docker (Sederhana — Hanya Web App)](#6-deployment-docker-sederhana--hanya-web-app)
7. [Deployment: Laragon (Windows)](#7-deployment-laragon-windows)
8. [Konfigurasi Aplikasi](#8-konfigurasi-aplikasi)
9. [Instalasi CLI Tool](#9-instalasi-cli-tool)

---

## 1. Prasyarat

### Server Master (VPS/Pusat)
- Moodle **4.1 atau lebih baru**
- PHP 8.0+
- Akses admin Moodle

### Server Lokal (Kelas)
Pilih salah satu:
- **Docker** (direkomendasikan): Docker Engine 24+ dan Docker Compose v2+
- **Laragon** (Windows): Laragon Full 6.0+ dengan PHP 8.2

### Komputer Operator
- Browser modern (Chrome, Firefox, Edge)
- Koneksi ke server lokal via LAN

---

## 2. Instalasi Plugin Moodle (local_eujian)

Plugin ini **wajib dipasang di kedua server** (Master dan Lokal).

### Langkah-langkah

**a. Siapkan file plugin**

File plugin tersedia dalam dua format di folder `plugin/`:
```
plugin/
├── local_eujian.zip   ← File zip untuk upload via UI Moodle
└── eujian/            ← Folder sumber (untuk instalasi manual)
```

**b. Instalasi via UI Moodle (direkomendasikan)**

1. Login ke Moodle sebagai **Admin**
2. Buka menu: **Administrasi Situs → Plugin → Pasang Plugin**
3. Klik tombol **"Pasang plugin dari file ZIP"**
4. Upload file `local_eujian.zip`
5. Klik **"Pasang plugin"** → konfirmasi
6. Moodle akan mendeteksi plugin `local_eujian` dan meminta upgrade database
7. Klik **"Upgrade database sekarang"**
8. Pastikan muncul pesan **"Plugin berhasil dipasang"**

**c. Instalasi manual (via FTP/SSH)**

```bash
# Salin folder plugin ke direktori Moodle
cp -r plugin/eujian/ /var/www/moodle/local/eujian/

# Set permission
chown -R www-data:www-data /var/www/moodle/local/eujian/

# Jalankan upgrade via CLI
php /var/www/moodle/admin/cli/upgrade.php --non-interactive
```

### Verifikasi Instalasi

Setelah instalasi, plugin akan muncul di:
**Administrasi Situs → Plugin → Plugin Overview → Local → E-UJIAN**

---

## 3. Konfigurasi Web Service Moodle

Konfigurasi ini dilakukan di **kedua server** (Master dan Lokal).

### Langkah 1 — Aktifkan Web Services

1. Buka **Administrasi Situs → Fitur Lanjutan**
2. Centang **"Aktifkan layanan web"** → Simpan

### Langkah 2 — Aktifkan Protokol REST

1. Buka **Administrasi Situs → Plugin → Layanan Web → Kelola Protokol**
2. Klik ikon **mata** pada baris **REST** untuk mengaktifkan

### Langkah 3 — Verifikasi Service E-UJIAN

Plugin `local_eujian` otomatis mendaftarkan service bernama **"E-UJIAN Sync Service"** saat instalasi. Untuk verifikasi:

1. Buka **Administrasi Situs → Plugin → Layanan Web → Layanan Eksternal**
2. Pastikan **"E-UJIAN Sync Service"** sudah muncul dengan status **Aktif**

Jika belum muncul, tambahkan manual:
1. Klik **"Tambah"**
2. Isi **Nama**: `E-UJIAN Sync Service`
3. **Nama singkat**: `eujian_sync`
4. Centang **"Aktif"** → Simpan
5. Klik **"Fungsi"** pada service tersebut → tambahkan semua fungsi dari tabel di [Bagian 4](#4-daftar-fungsi-web-service)

### Langkah 4 — Buat Token Akses

1. Buka **Administrasi Situs → Plugin → Layanan Web → Kelola Token**
2. Klik **"Tambah"**
3. Pilih **Pengguna**: `admin` (atau akun khusus yang dibuat untuk sync)
4. Pilih **Layanan**: `E-UJIAN Sync Service`
5. Klik **"Simpan perubahan"**
6. **Salin token** yang muncul — akan digunakan di konfigurasi Web App

> ⚠️ **Penting**: Token ini bersifat sensitif. Jangan bagikan ke pihak yang tidak berwenang.

---

## 4. Daftar Fungsi Web Service

Berikut 8 fungsi yang disediakan plugin `local_eujian`:

| No | Nama Fungsi | Tipe | Deskripsi | Server |
|---|---|---|---|---|
| 1 | `local_eujian_ping` | read | Cek koneksi dan info server | Master & Lokal |
| 2 | `local_eujian_get_students` | read | Ambil daftar siswa di kursus | Master & Lokal |
| 3 | `local_eujian_get_quiz_data` | read | Ambil info quiz di kursus | Master & Lokal |
| 4 | `local_eujian_get_quiz_results` | read | Ambil hasil attempt quiz | Master & Lokal |
| 5 | `local_eujian_export_quiz` | read | Export quiz + soal ke JSON | **Master** |
| 6 | `local_eujian_import_quiz` | write | Import quiz dari JSON ke kursus | **Lokal** |
| 7 | `local_eujian_get_quiz_masterid` | read | Ambil master quiz ID dari lokal | **Lokal** |
| 8 | `local_eujian_receive_results` | write | Terima hasil ujian ke gradebook | **Master** |

Selain fungsi plugin, Web App juga menggunakan fungsi standar Moodle berikut:

| Fungsi Standar | Kegunaan |
|---|---|
| `core_webservice_get_site_info` | Cek koneksi (fallback) |
| `core_course_get_courses` | Ambil daftar kursus |
| `core_course_create_courses` | Buat kursus di lokal |
| `core_course_get_categories` | Ambil kategori kursus |
| `core_course_create_categories` | Buat kategori di lokal |
| `core_enrol_get_enrolled_users` | Ambil siswa terdaftar |
| `core_user_create_users` | Buat akun siswa |
| `core_user_get_users` | Cari user berdasarkan kriteria |
| `core_user_get_users_by_field` | Cari user batch |
| `enrol_manual_enrol_users` | Daftarkan siswa ke kursus |
| `core_cohort_get_cohorts` | Ambil daftar cohort/rombel |
| `core_cohort_create_cohorts` | Buat cohort di lokal |
| `core_cohort_get_cohort_members` | Ambil anggota cohort |
| `core_cohort_add_cohort_members` | Tambah anggota cohort |

> **Catatan**: Semua fungsi standar ini sudah tersedia di instalasi Moodle default. Jika menggunakan service terpisah (bukan `eujian_sync`), tambahkan fungsi-fungsi tersebut secara manual ke service.

---

## 5. Deployment: Docker (Full Stack)

Mode ini menjalankan **Moodle + Web App** sekaligus dalam Docker. Cocok untuk setup server kelas baru dari awal.

### Prasyarat
- Docker Engine 24+
- Docker Compose v2+
- RAM minimal 4 GB untuk Moodle

### Langkah-langkah

```bash
# 1. Clone repositori
git clone <repo-url> moodle-sync-app
cd moodle-sync-app/docker

# 2. Salin file environment
cp .env.example .env

# 3. Edit .env jika diperlukan (opsional)
nano .env
```

**Isi `.env` yang perlu disesuaikan:**

```env
# Password database (ganti untuk produksi)
MYSQL_ROOT_PASSWORD=moodle_root
MYSQL_PASSWORD=moodle

# Akun admin Moodle
MOODLE_USERNAME=admin
MOODLE_PASSWORD=Admin1234!
MOODLE_EMAIL=admin@eujian.local
MOODLE_SITE_NAME=E-UJIAN Lokal

# Port akses
MOODLE_PORT=8080
SYNC_APP_PORT=8081
```

```bash
# 4. Jalankan containers
docker compose up -d

# 5. Cek status (tunggu Moodle selesai inisialisasi, ~3-5 menit)
docker compose logs -f moodle
```

**Akses setelah siap:**
- **Moodle Lokal**: http://localhost:8080
- **E-UJIAN Sync Web App**: http://localhost:8081/eujian-sync/

### Struktur Containers

| Container | Image | Port |
|---|---|---|
| `eujian-db` | mariadb:10.11 | (internal) |
| `eujian-moodle` | bitnami/moodle:4.3 | 8080 |
| `eujian-sync` | php:8.2-apache (custom) | 8081 |

### Pasang Plugin di Moodle Docker

Setelah Moodle siap, pasang plugin `local_eujian`:

```bash
# Salin plugin ke dalam container Moodle
docker cp plugin/eujian/ eujian-moodle:/bitnami/moodle/local/eujian/

# Set permission
docker exec eujian-moodle chown -R daemon:daemon /bitnami/moodle/local/eujian/

# Jalankan upgrade
docker exec eujian-moodle php /bitnami/moodle/admin/cli/upgrade.php --non-interactive
```

---

## 6. Deployment: Docker (Sederhana — Hanya Web App)

Gunakan mode ini jika **Moodle sudah berjalan** di server lain atau via Laragon.

```bash
cd moodle-sync-app/docker

# Jalankan hanya Web App
docker compose -f docker-compose.simple.yml up -d
```

Web App tersedia di: **http://localhost:8081/eujian-sync/**

Konfigurasi URL dan token Moodle dilakukan via halaman **Pengaturan** di Web App.

---

## 7. Deployment: Laragon (Windows)

Laragon adalah paket PHP/MySQL/Apache lokal untuk Windows. Cocok digunakan di komputer operator atau server kelas berbasis Windows.

### Prasyarat

- [Laragon Full](https://laragon.org/download/) 6.0+ (sudah termasuk PHP 8.2, Apache, MySQL)
- Git for Windows (opsional)

### Langkah-langkah

**a. Salin Web App ke folder Laragon**

```
Salin folder webapps/eujian-sync/
ke dalam: C:\laragon\www\eujian-sync\
```

Atau gunakan symlink:
```bat
mklink /D "C:\laragon\www\eujian-sync" "C:\path\ke\moodle-sync-app\webapps\eujian-sync"
```

**b. Jalankan Laragon**

1. Buka Laragon
2. Klik **"Start All"**
3. Akses Web App di: **http://localhost/eujian-sync/**

**c. Konfigurasi permission (jika diperlukan)**

Pastikan folder `eujian-sync/` dapat ditulis oleh Apache agar `config.json` dapat disimpan:
- Klik kanan folder → Properties → Security → Edit → tambah Write permission untuk user `www-data` atau `NETWORK SERVICE`

**d. Konfigurasi Moodle di Laragon (opsional)**

Jika Moodle juga dijalankan di Laragon:
1. Salin folder Moodle ke `C:\laragon\www\moodle\`
2. Buat database `moodle` di Laragon MySQL (port 3306)
3. Akses installer Moodle di `http://localhost/moodle/`

---

## 8. Konfigurasi Aplikasi

Konfigurasi disimpan di file `config.json` dalam folder Web App. Dapat diedit via:
- **Halaman Pengaturan** di Web App (direkomendasikan)
- Langsung edit file `config.json` dengan teks editor

### Format `config.json`

```json
{
  "MasterUrl":   "https://lms.sekolah.sch.id/",
  "MasterToken": "token_dari_moodle_master",
  "LocalUrl":    "http://localhost:8080/",
  "LocalToken":  "token_dari_moodle_lokal",
  "RoomId":      "1",
  "RoomName":    "Server Kelas X TEI 1"
}
```

| Field | Keterangan |
|---|---|
| `MasterUrl` | URL lengkap server Moodle master (dengan trailing slash `/`) |
| `MasterToken` | Token Web Service dari server master |
| `LocalUrl` | URL server Moodle lokal |
| `LocalToken` | Token Web Service dari server lokal |
| `RoomId` | Nomor/ID ruangan ujian (bebas, untuk identifikasi) |
| `RoomName` | Nama ruangan yang tampil di header Web App |

---

## 9. Instalasi CLI Tool

CLI Tool tersedia sebagai binary pre-built di folder `publish/`.

### Windows

```bat
cd publish\
MoodleSync.CLI.exe
```

### Linux / macOS

```bash
cd publish/
chmod +x MoodleSync.CLI
./MoodleSync.CLI
```

### Build dari Source (memerlukan .NET 8 SDK)

```bash
# Restore dependencies
dotnet restore

# Build & publish untuk Windows
dotnet publish src/MoodleSync.CLI -r win-x64 -c Release --self-contained -o publish/win/

# Build & publish untuk Linux
dotnet publish src/MoodleSync.CLI -r linux-x64 -c Release --self-contained -o publish/linux/
```

File konfigurasi CLI (`sync-config.json`) akan dibuat otomatis di folder yang sama dengan binary saat pertama kali dijalankan.

---

## Troubleshooting Instalasi

| Masalah | Kemungkinan Penyebab | Solusi |
|---|---|---|
| Plugin tidak muncul setelah upload | Cache Moodle | Jalankan: `Admin → Dev → Purge all caches` |
| "Service tidak ditemukan" | Plugin belum di-upgrade | Jalankan upgrade database |
| Token tidak valid | Token sudah kedaluwarsa atau salah service | Buat token baru di Manage Tokens |
| Web App tidak bisa simpan config | Permission file | `chmod 666 config.json` atau sesuaikan user Apache |
| Docker: Moodle lambat inisialisasi | Normal untuk setup pertama | Tunggu 5-10 menit, cek `docker logs eujian-moodle` |
| "SSL verify failed" | Sertifikat self-signed | Normal — Web App menonaktifkan SSL verify untuk koneksi lokal |
