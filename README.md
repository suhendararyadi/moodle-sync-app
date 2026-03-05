# E-UJIAN Sync

Sistem sinkronisasi data ujian antara **Server Master (VPS/Pusat)** dan **Server Lokal (Kelas)** berbasis Moodle REST API. Dirancang untuk ujian semi-offline: siswa terhubung ke server kelas via LAN tanpa membutuhkan internet.

> **Pengembang**: Suhendar Aryadi — SMKN 9 Garut

---

## Arsitektur Sistem

```
┌─────────────────────────────────────────────────────────────────────┐
│                          INTERNET / VPN                             │
│                                                                     │
│   ┌─────────────────┐      REST API      ┌────────────────────┐    │
│   │  Server Master  │ ◄─────────────────► │  E-UJIAN Sync App  │   │
│   │  (VPS / Pusat)  │                     │  (Web App + CLI)   │   │
│   │   Moodle LMS    │                     └────────┬───────────┘   │
│   └─────────────────┘                              │               │
│                                                    │ REST API       │
└────────────────────────────────────────────────────┼───────────────┘
                                                     │
                        ─────────────── LAN ─────────┼───────────────
                                                     │
                   ┌─────────────────────────────────▼───────────┐
                   │          SERVER LOKAL (Kelas)                │
                   │         Moodle LMS (Docker/Laragon)          │
                   │                                              │
                   │  ┌──────────┐  ┌──────────┐  ┌──────────┐  │
                   │  │ Siswa 1  │  │ Siswa 2  │  │ Siswa N  │  │
                   │  │ (Browser)│  │ (Browser)│  │ (Browser)│  │
                   │  └──────────┘  └──────────┘  └──────────┘  │
                   └──────────────────────────────────────────────┘
```

**Alur kerja:**
1. **Sebelum ujian** — operator sync kursus, siswa, cohort, dan soal quiz dari Master ke Lokal
2. **Saat ujian** — siswa mengerjakan di browser via LAN, tanpa internet
3. **Setelah ujian** — operator upload hasil ujian dari Lokal ke gradebook Master

---

## Fitur

| Fitur | Komponen | Status |
|---|---|---|
| Sync Kursus + Siswa per Kursus | Web App & CLI | ✅ |
| Sync Semua Siswa Master → Lokal | Web App & CLI | ✅ |
| Sync Cohort / Rombel | Web App & CLI | ✅ |
| Sync Quiz (soal + gambar) | Web App & CLI | ✅ |
| Upload Hasil Ujian → Master | Web App & CLI | ✅ |
| Dashboard Status Server | Web App | ✅ |
| Lihat Daftar Kursus & Siswa | Web App & CLI | ✅ |
| Lihat Hasil Ujian | Web App | ✅ |
| Dark Mode | Web App | ✅ |
| Progress Real-time (SSE) | Web App | ✅ |
| Docker Deployment | Docker | ✅ |
| Plugin Moodle (local_eujian) | PHP Plugin | ✅ |

---

## Komponen

```
moodle-sync-app/
├── src/
│   ├── MoodleSync.CLI/             ← CLI Tool (.NET 8, C#)
│   │   └── Program.cs              ← Menu interaktif
│   │
│   └── MoodleSyncApp.Core/         ← Business Logic (shared)
│       ├── Config/AppConfig.cs     ← Model konfigurasi
│       ├── Models/                 ← Data models
│       └── Services/               ← MoodleApiService, SyncService, LogService
│
├── webapps/
│   └── eujian-sync/                ← Web App (PHP + Tailwind CSS)
│       ├── index.php               ← Router + layout
│       ├── pages/                  ← Halaman-halaman UI
│       ├── lib/                    ← Config, MoodleApi, SyncService
│       └── ajax/stream.php         ← SSE endpoint
│
├── plugin/
│   └── eujian/                     ← Plugin Moodle (local_eujian)
│       ├── externallib.php         ← 8 fungsi Web Service
│       ├── db/services.php         ← Registrasi fungsi & service
│       └── lang/                   ← String bahasa
│
├── docker/
│   ├── docker-compose.yml          ← Full stack (Moodle + Web App)
│   ├── docker-compose.simple.yml   ← Hanya Web App
│   └── Dockerfile                  ← Image PHP Apache
│
└── docs/
    ├── INSTALL.md                  ← Panduan instalasi lengkap
    ├── USER_GUIDE.md               ← Panduan penggunaan operator
    └── TECHNICAL.md                ← Referensi teknis
```

---

## Quick Start

### Prasyarat
- Docker & Docker Compose **atau** PHP 8.2 + Apache/Nginx (Laragon)
- Moodle 4.x terinstal di server master dan lokal
- Plugin `local_eujian` terpasang di kedua server Moodle

### Instalasi Web App (Docker)

```bash
# Clone repositori
git clone <repo-url> moodle-sync-app
cd moodle-sync-app/docker

# Salin dan sesuaikan konfigurasi
cp .env.example .env

# Jalankan (termasuk Moodle lokal)
docker compose up -d

# Atau hanya Web App saja (jika Moodle sudah ada)
docker compose -f docker-compose.simple.yml up -d
```

Web App dapat diakses di: **http://localhost:8081/eujian-sync/**

### Instalasi CLI Tool

```bash
# Buka folder publish yang sudah ada
cd publish/

# Jalankan (Linux/macOS)
./MoodleSync.CLI

# Jalankan (Windows)
MoodleSync.CLI.exe
```

---

## Tech Stack

| Komponen | Teknologi |
|---|---|
| CLI Tool | C# / .NET 8 |
| Web App | PHP 8.2, Tailwind CSS, dark mode |
| Real-time progress | Server-Sent Events (SSE) |
| Plugin Moodle | PHP (local plugin) |
| Database konfigurasi | JSON file (`config.json`) |
| Containerisasi | Docker, Apache |

---

## Dokumentasi

| Dokumen | Deskripsi |
|---|---|
| [docs/INSTALL.md](docs/INSTALL.md) | Panduan instalasi lengkap (plugin, Docker, Laragon) |
| [docs/USER_GUIDE.md](docs/USER_GUIDE.md) | Panduan penggunaan untuk operator sekolah |
| [docs/TECHNICAL.md](docs/TECHNICAL.md) | Referensi teknis (API, SSE, skema database) |
| [docker/README.md](docker/README.md) | Panduan cepat Docker deployment |

---

## Lisensi

Dikembangkan untuk kebutuhan internal **SMKN 9 Garut**. Bebas digunakan dan dimodifikasi untuk keperluan pendidikan.
