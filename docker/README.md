# Docker Deployment — E-UJIAN Sync

## Struktur

```
docker/
├── Dockerfile                 # Image untuk E-UJIAN Sync Web App
├── docker-compose.yml         # Full stack: Moodle + DB + Web App
├── docker-compose.simple.yml  # Simple: hanya Web App (Moodle terpisah)
├── .env.example               # Template environment variables
├── backup.sh                  # Script backup data Moodle
├── restore.sh                 # Script restore data dari backup
├── clone-to-new-server.sh     # Helper kloning ke server baru
└── README.md
```

---

## Opsi 1: Full Stack (Moodle + DB + Web App)

Gunakan jika ingin semua service dalam satu Docker stack.

```bash
cd docker/
cp .env.example .env
docker compose up -d
```

| Service      | URL                                        | Keterangan        |
|--------------|--------------------------------------------|-------------------|
| Moodle Lokal | http://localhost:8080                      | LMS server kelas  |
| E-UJIAN Sync | http://localhost:8081/eujian-sync          | Web App sinkronisasi |

> ⚠️ Instalasi Moodle pertama kali membutuhkan ~5-10 menit.

Setelah Moodle siap, install plugin:
1. Buka http://localhost:8080 → login admin
2. **Site Administration → Plugins → Install plugins**
3. Upload `plugin/local_eujian.zip`

---

## Opsi 2: Simple (Moodle sudah ada di Laragon/server lain)

```bash
cd docker/
docker compose -f docker-compose.simple.yml up -d
```

Akses Web App: **http://localhost:8081/eujian-sync**

Di Settings Web App, atur:
- **Local URL:** `http://host.docker.internal/` (jika Moodle di Laragon host machine)
- **Local Token:** token dari Moodle lokal Anda

---

## Backup & Restore

### Kredensial Default Docker

| Setting | Nilai |
|---|---|
| DB User | `moodle` |
| DB Password | `moodlepassword` |
| DB Root Password | `rootpassword` |
| DB Name | `moodle` |
| Moodle Admin | `admin` / `Admin1234!` |

### Backup Data

Jalankan script backup untuk menyimpan database + file uploads:

```bash
cd docker/
./backup.sh
```

Hasil backup disimpan di:
```
docker/backups/YYYY-MM-DD_HH-MM/
├── database.sql.gz     # Seluruh data Moodle (user, kursus, soal, nilai)
├── moodledata.tar.gz   # File upload (gambar soal, lampiran)
├── config.json         # Konfigurasi koneksi Web App
└── backup-info.txt     # Metadata backup
```

> **Catatan:** Folder `backups/` tidak masuk ke Git (ada di `.gitignore`).

### Restore Data

```bash
cd docker/
./restore.sh ./backups/2026-03-05_11-02
```

### Kloning ke Komputer/Server Baru

**Di komputer lama:**
```bash
cd docker/
./backup.sh
# Copy folder backups/YYYY-MM-DD_HH-MM ke USB/cloud
```

**Di komputer baru:**
```bash
git clone https://github.com/suhendararyadi/moodle-sync-app
cd moodle-sync-app/docker/
# Taruh folder backup di sini: docker/backups/YYYY-MM-DD_HH-MM/
./clone-to-new-server.sh ./backups/2026-03-05_11-02
```

Script `clone-to-new-server.sh` akan otomatis:
1. Menjalankan `docker compose up -d`
2. Menunggu database siap
3. Memanggil `restore.sh` untuk memuat semua data

---

## Perintah Berguna

```bash
# Status container
docker compose ps

# Lihat log
docker compose logs -f

# Stop semua
docker compose down

# Reset total (hapus semua data)
docker compose down -v

# Masuk ke container Moodle
docker exec -it eujian-moodle bash

# Purge cache Moodle
docker exec eujian-moodle php /var/www/html/admin/cli/purge_caches.php

# Backup manual
cd docker/ && ./backup.sh
```

