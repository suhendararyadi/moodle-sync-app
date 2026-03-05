# Docker Deployment — E-UJIAN Sync

## Struktur

```
docker/
├── Dockerfile                 # Image untuk E-UJIAN Sync Web App
├── docker-compose.yml         # Full stack: Moodle + DB + Web App
├── docker-compose.simple.yml  # Simple: hanya Web App (Moodle terpisah)
├── .env.example               # Template environment variables
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

# Masuk ke container
docker exec -it eujian-moodle bash

# Purge cache Moodle
docker exec eujian-moodle php /bitnami/moodle/admin/cli/purge_caches.php
```
