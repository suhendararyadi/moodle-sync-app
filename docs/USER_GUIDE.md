# Panduan Pengguna E-UJIAN Sync

Dokumen ini ditujukan untuk **operator sekolah** yang bertugas mengelola sinkronisasi data ujian antara server pusat (Master) dan server kelas (Lokal).

---

## Daftar Isi

1. [Pengenalan Sistem](#1-pengenalan-sistem)
2. [Persiapan Awal (Setup Pertama Kali)](#2-persiapan-awal-setup-pertama-kali)
3. [Mengenal Tampilan Web App](#3-mengenal-tampilan-web-app)
4. [Menu Dashboard](#4-menu-dashboard)
5. [Menu Daftar Kursus](#5-menu-daftar-kursus)
6. [Menu Daftar Siswa](#6-menu-daftar-siswa)
7. [Menu Sync Kursus + Siswa](#7-menu-sync-kursus--siswa)
8. [Menu Sync Semua Siswa](#8-menu-sync-semua-siswa)
9. [Menu Sync Cohort / Rombel](#9-menu-sync-cohort--rombel)
10. [Menu Sync Quiz](#10-menu-sync-quiz)
11. [Menu Upload Hasil Ujian](#11-menu-upload-hasil-ujian)
12. [Menu Lihat Hasil Ujian](#12-menu-lihat-hasil-ujian)
13. [Menu Pengaturan](#13-menu-pengaturan)
14. [Menggunakan CLI Tool](#14-menggunakan-cli-tool)
15. [Alur Kerja Ujian](#15-alur-kerja-ujian)
16. [Troubleshooting Umum](#16-troubleshooting-umum)

---

## 1. Pengenalan Sistem

**E-UJIAN Sync** adalah aplikasi yang membantu operator sekolah melakukan sinkronisasi data ujian antara:

- **Server Master** (VPS/server pusat sekolah, terhubung internet)
- **Server Lokal** (server kelas, terhubung LAN)

Siswa mengerjakan ujian via browser yang terhubung ke **Server Lokal** — tidak membutuhkan internet. Setelah ujian selesai, operator mengirimkan hasil nilai ke Server Master.

**Apa yang perlu di-sync:**

| Data | Dari | Ke | Waktu |
|---|---|---|---|
| Data kursus | Master | Lokal | Sebelum ujian |
| Akun siswa | Master | Lokal | Sebelum ujian |
| Cohort/Rombel | Master | Lokal | Sebelum ujian |
| Soal quiz | Master | Lokal | Sebelum ujian |
| Hasil ujian (nilai) | Lokal | Master | Setelah ujian |

---

## 2. Persiapan Awal (Setup Pertama Kali)

### Langkah 1 — Buka Web App

Buka browser dan akses Web App di:
```
http://[IP-SERVER-KELAS]/eujian-sync/
```

Contoh: `http://192.168.1.10/eujian-sync/` atau `http://localhost:8081/eujian-sync/`

### Langkah 2 — Buka Halaman Pengaturan

Klik menu **"Pengaturan"** di sidebar kiri (ikon roda gigi).

### Langkah 3 — Isi Konfigurasi Server

Isi formulir dengan informasi berikut:

**Server Master (VPS / Pusat)**
- **URL Master**: Alamat server Moodle pusat, contoh: `https://lms.smknegeri9garut.sch.id/`
- **Token Master**: Token Web Service dari server master (dapatkan dari Admin Moodle → Layanan Web → Kelola Token)

**Server Lokal (Kelas)**
- **URL Lokal**: Alamat Moodle lokal, contoh: `http://localhost:8080/` atau `http://192.168.1.10:8080/`
- **Token Lokal**: Token Web Service dari Moodle lokal

**Identitas Ruangan**
- **Room ID**: Nomor ruangan, contoh: `1`, `2`, `R01`
- **Nama Ruangan**: Nama ruangan, contoh: `Server Kelas X TEI 1`

### Langkah 4 — Simpan dan Verifikasi

1. Klik tombol **"Simpan Konfigurasi"**
2. Muncul notifikasi hijau "Konfigurasi berhasil disimpan"
3. Klik menu **Dashboard** untuk cek status koneksi
4. Pastikan kedua server menampilkan status **"Online"** (badge hijau)

---

## 3. Mengenal Tampilan Web App

```
┌─────────────────────────────────────────────────────────┐
│  [Logo]  E-UJIAN Sync          [Status] [🌙] [⚙️]       │← Header
│  SERVER KELAS                                           │
├──────────┬──────────────────────────────────────────────┤
│          │                                              │
│ Dashboard│           KONTEN HALAMAN                     │
│ Kursus   │                                              │
│ Siswa    │                                              │
│──────────│                                              │
│ SYNC     │                                              │
│ Kursus+Siswa                                            │
│ Semua Siswa                                             │
│ Cohort   │                                              │
│ Quiz     │                                              │
│ Upload   │                                              │
│──────────│                                              │
│ Hasil    │                                              │
│ Pengaturan                                              │
│          │                                              │
│──────────│                                              │
│ [Admin]  │                                              │
└──────────┴──────────────────────────────────────────────┘
```

**Elemen UI:**
- **Sidebar kiri**: Navigasi menu utama
- **Header atas**: Judul halaman, status koneksi lokal, tombol dark mode
- **Tombol bulan 🌙**: Toggle antara mode terang dan gelap
- **Badge hijau berkedip**: Server lokal online

---

## 4. Menu Dashboard

**Akses**: Klik "Dashboard" di sidebar

Dashboard menampilkan:

### Status Server
Dua kartu menampilkan kondisi real-time:
- **Server Master** — status online/offline, versi Moodle, jumlah kursus
- **Server Lokal** — status online/offline, Room ID, jumlah kursus, versi Moodle

Jika server **Offline**, periksa:
- Koneksi internet (untuk master)
- Token yang diisi (mungkin salah atau kedaluwarsa)
- URL server (pastikan ada trailing slash `/`)

### Aksi Cepat
Empat tombol pintasan ke menu sync utama:
- **Sync Kursus + Siswa**
- **Sync Semua Siswa**
- **Sync Quiz**
- **Upload Hasil Ujian**

---

## 5. Menu Daftar Kursus

**Akses**: Klik "Daftar Kursus" di sidebar

Menampilkan semua kursus yang ada di **Server Master**, dengan informasi:
- **ID Kursus** — diperlukan saat melakukan sync
- **Shortname** — kode kursus singkat
- **Nama Lengkap** — nama kursus lengkap

> **Tips**: Catat ID Kursus yang akan diujikan, karena ID ini digunakan pada menu Sync Kursus dan Sync Quiz.

---

## 6. Menu Daftar Siswa

**Akses**: Klik "Daftar Siswa" di sidebar

Masukkan **Course ID** dari server master untuk melihat daftar siswa yang terdaftar di kursus tersebut. Informasi yang ditampilkan:
- ID User, Username, NIS/NISN, Nama Lengkap, Email

---

## 7. Menu Sync Kursus + Siswa

**Akses**: Sidebar → Synchronization → "Sync Kursus + Siswa"

Fungsi ini melakukan sync **satu kursus beserta semua siswanya** dari Master ke Lokal.

**Apa yang dilakukan:**
1. Mengambil info kursus dari master
2. Membuat kursus di lokal (jika belum ada), termasuk kategorinya
3. Mengambil semua siswa yang terdaftar di kursus
4. Membuat akun siswa di lokal (batch 50 per batch)
5. Mendaftarkan siswa ke kursus lokal
6. Sync cohort/rombel terkait

**Cara penggunaan:**
1. Buka menu "Sync Kursus + Siswa"
2. Masukkan **Course ID** (dari menu Daftar Kursus)
3. Klik tombol **"Mulai Sync"**
4. Pantau progress bar dan log yang muncul secara real-time
5. Tunggu hingga muncul status **"Selesai!"** dengan tanda ✓ hijau

**Progress yang ditampilkan:**
```
[ 5%] Ambil info kursus dari master...
[10%] Kursus: Matematika XI
[15%] Periksa kursus di lokal...
[20%] Buat kursus baru di lokal...
[30%] Total siswa: 35. Mulai sync...
[45%] Batch 1/2 — buat user...
[70%] Batch 2/2 — buat user...
[85%] Total 35 user dibuat/diperbarui.
[90%] Sync cohort/rombel...
[100%] Selesai! 35 siswa disinkronisasi.
```

> **Catatan**: Password akun siswa di lokal diatur otomatis menjadi `Eujian@[username]`. Siswa tidak perlu login — ujian biasanya diakses langsung dari browser tanpa autentikasi tambahan.

---

## 8. Menu Sync Semua Siswa

**Akses**: Sidebar → Synchronization → "Sync Semua Siswa"

Fungsi ini men-sync **semua siswa dari semua kursus** di master ke lokal. Digunakan untuk memastikan seluruh basis data pengguna ter-sinkronisasi.

**Kapan digunakan:**
- Persiapan pertama kali sebelum musim ujian
- Sebelum melakukan Sync Cohort (agar semua anggota rombel tersedia di lokal)
- Ketika ada banyak siswa baru yang perlu ditambahkan

**Perkiraan waktu**: 5–15 menit untuk 2.000+ siswa, tergantung kecepatan koneksi.

**Cara penggunaan:**
1. Buka menu "Sync Semua Siswa"
2. Baca peringatan yang muncul
3. Klik tombol **"Mulai Sync Semua Siswa"**
4. Tunggu proses selesai (jangan tutup browser)

---

## 9. Menu Sync Cohort / Rombel

**Akses**: Sidebar → Synchronization → "Sync Cohort/Rombel"

Fungsi ini menyinkronkan semua **cohort (rombongan belajar/kelas)** beserta anggotanya dari master ke lokal.

**Prasyarat**: Siswa harus sudah di-sync terlebih dahulu (menu [7] atau [8]).

**Apa yang dilakukan:**
1. Mengambil semua cohort dari master
2. Membuat cohort di lokal jika belum ada
3. Mencocokkan anggota cohort berdasarkan username
4. Menambahkan anggota ke cohort lokal

**Cara penggunaan:**
1. Buka menu "Sync Cohort/Rombel"
2. Klik tombol **"Mulai Sync Cohort"**
3. Pantau progress — akan menampilkan nama setiap cohort yang diproses
4. Tunggu hingga selesai

---

## 10. Menu Sync Quiz

**Akses**: Sidebar → Synchronization → "Sync Quiz"

Fungsi ini men-download **soal ujian (quiz)** dari master dan mengimportnya ke kursus lokal. Gambar pada soal ikut disinkronkan (di-embed sebagai base64).

**Prasyarat**: Kursus harus sudah di-sync terlebih dahulu (menu [7]).

**Tipe soal yang didukung:**
- Pilihan ganda (multichoice)
- Benar/Salah (truefalse)
- Isian singkat (shortanswer)

**Cara penggunaan:**

1. Buka menu "Sync Quiz"
2. Masukkan **Course ID Master** — daftar quiz di kursus tersebut akan muncul
3. Masukkan **Quiz ID** yang akan di-sync
4. Masukkan **Course ID Lokal** (kursus tujuan di server lokal)
5. Klik tombol **"Mulai Sync Quiz"**
6. Pantau progress:
   ```
   [10%] Export quiz dari master (ID: 42)...
   [40%] Berhasil export 30 soal. Import ke lokal...
   [100%] Selesai! Quiz ID lokal: 5, 30 soal (created).
   ```
7. Catat **Quiz ID Lokal** yang muncul — diperlukan saat Upload Hasil

> **Info**: Jika quiz yang sama di-sync ulang, sistem akan mendeteksi duplikat (via `idnumber = eujian_quiz_[masterID]`) dan menampilkan status `skipped` tanpa membuat duplikat.

---

## 11. Menu Upload Hasil Ujian

**Akses**: Sidebar → Synchronization → "Upload Hasil Ujian"

Setelah ujian selesai, gunakan menu ini untuk mengirimkan nilai siswa dari server lokal ke **gradebook server master**.

**Prasyarat**: Quiz harus diimport via menu Sync Quiz (bukan dibuat manual), agar sistem dapat mengetahui ID quiz di master.

**Cara penggunaan:**

1. Buka menu "Upload Hasil Ujian"
2. Pilih quiz dari daftar yang tersedia
3. Klik tombol **"Mulai Upload"**
4. Pantau progress:
   ```
   [10%] Ambil info quiz lokal (ID: 5)...
   [20%] Quiz: Soal UTS Matematika → Master ID: 42
   [30%] Ambil hasil ujian dari lokal...
   [40%] 35 attempt ditemukan. Kirim ke master...
   [100%] Selesai! 35 nilai dikirim ke master.
   ```
5. Setelah selesai, verifikasi nilai di gradebook server master

**Penghitungan nilai:**
Nilai yang dikirim ke master dihitung otomatis:
```
Nilai = (sumgrades_lokal / sumgrades_max) × grade_master
```

---

## 12. Menu Lihat Hasil Ujian

**Akses**: Sidebar → Configuration → "Lihat Hasil Ujian"

Menampilkan rekapitulasi hasil ujian dari server **lokal**:
- Daftar attempt per siswa
- Nilai/skor setiap siswa
- Waktu mulai dan waktu selesai ujian
- Status attempt (finished/in progress)

Masukkan **Quiz ID** untuk melihat hasil quiz tertentu.

---

## 13. Menu Pengaturan

**Akses**: Sidebar → Configuration → "Pengaturan"

Halaman untuk mengubah konfigurasi koneksi server. Lihat [Bagian 2](#2-persiapan-awal-setup-pertama-kali) untuk detail pengisian.

**Lokasi file konfigurasi** ditampilkan di bagian bawah halaman pengaturan (contoh: `/var/www/html/eujian-sync/config.json`).

---

## 14. Menggunakan CLI Tool

CLI Tool adalah alternatif berbasis teks yang berjalan di terminal/command prompt. Cocok untuk operator teknis atau untuk penggunaan via SSH.

### Menjalankan CLI

```bash
# Windows
MoodleSync.CLI.exe

# Linux/macOS
./MoodleSync.CLI
```

### Menu Utama CLI

```
  ─── MENU UTAMA ───────────────────────────────
  [1] Tes Koneksi Server (Master & Lokal)
  [2] Lihat Daftar Kursus (Master)
  [3] Lihat Daftar Siswa di Kursus
  [4] Sync Kursus + Siswa per Kursus      ⭐
  [5] Sync SEMUA Siswa Master → Lokal     ⭐
  [6] Sync Siswa saja (per kursus)
  [7] Sync Cohort/Rombel Master → Lokal   ⭐
  [8] Lihat Daftar Quiz di Kursus
  [9] Export Hasil Ujian → File JSON
  [U] Upload Hasil Ujian → Master          ⭐
  [Q] Sync Quiz Master → Lokal             ⭐
  [S] Atur Konfigurasi
  [C] Tampilkan Konfigurasi
  [0] Keluar
  ─────────────────────────────────────────────
  Pilihan:
```

### Panduan Menu CLI

| Pilihan | Fungsi | Setara Web App |
|---|---|---|
| `1` | Tes koneksi master & lokal | Dashboard → Status Server |
| `2` | Lihat daftar kursus | Menu Daftar Kursus |
| `3` | Lihat daftar siswa di kursus | Menu Daftar Siswa |
| `4` | Sync kursus + siswa | Menu Sync Kursus + Siswa |
| `5` | Sync semua siswa | Menu Sync Semua Siswa |
| `6` | Sync siswa per kursus saja | (subset dari menu 4) |
| `7` | Sync cohort/rombel | Menu Sync Cohort/Rombel |
| `8` | Lihat daftar quiz | (informasi saja) |
| `9` | Export hasil ujian ke file JSON | Menu Lihat Hasil Ujian |
| `U` | Upload hasil ujian ke master | Menu Upload Hasil Ujian |
| `Q` | Sync quiz master → lokal | Menu Sync Quiz |
| `S` | Atur konfigurasi server | Menu Pengaturan |
| `C` | Tampilkan konfigurasi saat ini | (informasi saja) |

### Konfigurasi CLI

Pertama kali dijalankan, pilih **[S] Atur Konfigurasi**:

```
  URL Server Master   []: https://lms.sekolah.sch.id/
  Token Master        []: (paste token Moodle master)
  URL Server Lokal    []: http://localhost:8080/
  Token Lokal         []: (paste token Moodle lokal)
  ID Ruangan          []: 1
  Nama Ruangan        []: Server Kelas X TEI 1
```

Tekan **Enter** untuk mempertahankan nilai yang sudah ada.

---

## 15. Alur Kerja Ujian

### Fase 1: Persiapan (H-1 atau hari H sebelum ujian)

```
1. Buka Web App → Cek Dashboard (kedua server harus Online)
2. Sync Kursus + Siswa  →  [Menu: Sync Kursus + Siswa]
   Masukkan Course ID kursus yang akan diujikan
3. Sync Cohort/Rombel   →  [Menu: Sync Cohort/Rombel]
   (opsional, untuk pengelompokan kelas)
4. Sync Quiz            →  [Menu: Sync Quiz]
   Masukkan Quiz ID soal ujian dari master
   Catat Quiz ID Lokal yang dihasilkan
5. Verifikasi di Moodle Lokal:
   Login Moodle lokal → pastikan kursus, siswa, dan quiz tersedia
```

### Fase 2: Pelaksanaan Ujian

```
1. Siswa masuk ruangan, nyalakan komputer
2. Buka browser → akses: http://[IP-SERVER-KELAS]/moodle/
   (sesuaikan dengan URL Moodle lokal)
3. Login dengan username masing-masing
   Password default: Eujian@[username]
4. Pilih kursus → kerjakan quiz
5. Operator memantau dari Web App → Dashboard
```

### Fase 3: Setelah Ujian

```
1. Tunggu semua siswa selesai mengerjakan
2. Buka Web App → Menu "Upload Hasil Ujian"
3. Pilih quiz yang baru saja diujikan
4. Klik "Mulai Upload" → pantau progress
5. Verifikasi di Moodle Master:
   Login Moodle master → Kursus → Nilai
   Pastikan nilai siswa sudah masuk ke gradebook
6. (Opsional) Lakukan backup data server kelas
```

### Backup Data Setelah Ujian

Sangat disarankan melakukan backup setelah setiap ujian sebagai cadangan data:

```bash
cd moodle-sync-app/docker/
./backup.sh
```

Hasil backup tersimpan di `docker/backups/YYYY-MM-DD_HH-MM/` berisi:
- Database lengkap (nilai, attempt siswa)
- File upload (gambar soal)
- Konfigurasi koneksi

**Untuk memindahkan data ke server lain:**
```bash
# 1. Backup di komputer lama
cd docker/ && ./backup.sh

# 2. Copy folder backup ke USB/cloud/jaringan

# 3. Di komputer baru (setelah git clone):
cd docker/ && ./clone-to-new-server.sh ./backups/2026-03-05_11-02
```

---

## 16. Troubleshooting Umum

### Server Offline di Dashboard

| Gejala | Penyebab | Solusi |
|---|---|---|
| Master Offline | Token salah/kedaluwarsa | Buat token baru di Moodle master |
| Master Offline | URL salah | Pastikan URL diakhiri `/` dan bisa diakses |
| Master Offline | Tidak ada internet | Periksa koneksi internet server kelas |
| Lokal Offline | Moodle lokal belum jalan | Jalankan Docker atau Laragon |
| Lokal Offline | Port salah | Periksa URL lokal (default `:8080`) |

### Sync Gagal di Tengah Proses

| Pesan Error | Solusi |
|---|---|
| `Kursus ID X tidak ditemukan di master` | Periksa Course ID, gunakan menu Daftar Kursus |
| `Gagal membuat kursus di lokal` | Periksa token lokal memiliki izin manageactivities |
| `cURL error: Connection refused` | Server tujuan tidak berjalan |
| `HTTP 403` | Token tidak memiliki izin yang cukup |
| `exception: invalidtoken` | Token salah atau kedaluwarsa, buat token baru |

### Quiz Tidak Bisa Di-sync

| Gejala | Solusi |
|---|---|
| "Tidak ada quiz di kursus ini" | Pastikan ada quiz di kursus master, cek Course ID |
| "Quiz sudah ada (skipped)" | Quiz sudah pernah di-sync, tidak perlu di-sync ulang |
| Soal gambar tidak muncul di lokal | Pastikan plugin `local_eujian` aktif di kedua server |
| Tipe soal tidak tersync | Hanya multichoice, truefalse, shortanswer yang didukung |

### Upload Hasil Gagal

| Pesan Error | Solusi |
|---|---|
| "Quiz tidak memiliki master ID" | Quiz ini bukan hasil Sync Quiz, tidak bisa diupload |
| "Tidak ada hasil ujian di lokal" | Belum ada siswa yang selesai mengerjakan |
| Nilai 0 semua | Periksa `sumgrades` quiz lokal di Moodle lokal |

### Konfigurasi Tidak Tersimpan

```bash
# Periksa permission file config.json
ls -la /var/www/html/eujian-sync/config.json

# Perbaiki permission
chmod 666 /var/www/html/eujian-sync/config.json
chown www-data:www-data /var/www/html/eujian-sync/config.json
```

### Mendapatkan Bantuan

Jika masalah tidak teratasi:
1. Buka browser developer tools (F12) → tab Console — periksa error JavaScript
2. Periksa log server: `docker logs eujian-sync` atau Apache error log
3. Coba akses endpoint ping langsung: `http://[moodle-url]/webservice/rest/server.php?wstoken=[TOKEN]&wsfunction=local_eujian_ping&moodlewsrestformat=json`
