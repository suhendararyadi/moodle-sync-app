#!/bin/bash
# ═══════════════════════════════════════════════════════════════
# backup.sh — Backup data Moodle Docker ke satu folder arsip
#
# Usage:
#   ./backup.sh              → backup ke ./backups/YYYY-MM-DD_HH-MM/
#   ./backup.sh /path/to/dir → backup ke direktori tertentu
# ═══════════════════════════════════════════════════════════════

set -e

# ── Konfigurasi ───────────────────────────────────────────────
DB_CONTAINER="eujian-db"
MOODLE_CONTAINER="eujian-moodle"
DB_NAME="moodle"
DB_USER="moodle"
DB_PASS="moodlepassword"

BACKUP_BASE="${1:-$(dirname "$0")/backups}"
TIMESTAMP=$(date +"%Y-%m-%d_%H-%M")
BACKUP_DIR="$BACKUP_BASE/$TIMESTAMP"

# ── Warna output ─────────────────────────────────────────────
GREEN='\033[0;32m'; YELLOW='\033[1;33m'; RED='\033[0;31m'; NC='\033[0m'
info()    { echo -e "${GREEN}[✓]${NC} $1"; }
warning() { echo -e "${YELLOW}[!]${NC} $1"; }
error()   { echo -e "${RED}[✗]${NC} $1"; exit 1; }

# ── Cek container berjalan ────────────────────────────────────
echo ""
echo "═══════════════════════════════════════"
echo "   E-UJIAN Moodle Backup Tool"
echo "═══════════════════════════════════════"
echo ""

docker ps --format '{{.Names}}' | grep -q "^$DB_CONTAINER$" || error "Container '$DB_CONTAINER' tidak berjalan."
docker ps --format '{{.Names}}' | grep -q "^$MOODLE_CONTAINER$" || error "Container '$MOODLE_CONTAINER' tidak berjalan."

mkdir -p "$BACKUP_DIR"
info "Backup ke: $BACKUP_DIR"
echo ""

# ── 1. Backup Database ────────────────────────────────────────
echo "[ 1/3 ] Backup database..."
docker exec "$DB_CONTAINER" mysqldump \
    -u"$DB_USER" -p"$DB_PASS" \
    --single-transaction \
    --routines \
    --triggers \
    "$DB_NAME" | gzip > "$BACKUP_DIR/database.sql.gz"

DB_SIZE=$(du -sh "$BACKUP_DIR/database.sql.gz" | cut -f1)
info "Database: $DB_SIZE"

# ── 2. Backup Moodledata (file uploads, gambar soal, dll) ─────
echo "[ 2/3 ] Backup moodledata (file uploads)..."
docker run --rm \
    --volumes-from "$MOODLE_CONTAINER" \
    -v "$BACKUP_DIR:/backup" \
    alpine \
    tar czf /backup/moodledata.tar.gz -C / var/moodledata 2>/dev/null || \
docker exec "$MOODLE_CONTAINER" tar czf - /var/moodledata 2>/dev/null > "$BACKUP_DIR/moodledata.tar.gz" || \
warning "moodledata tidak ditemukan, dilewati."

if [ -f "$BACKUP_DIR/moodledata.tar.gz" ]; then
    MD_SIZE=$(du -sh "$BACKUP_DIR/moodledata.tar.gz" | cut -f1)
    info "Moodledata: $MD_SIZE"
fi

# ── 3. Backup config.json web app ─────────────────────────────
echo "[ 3/3 ] Backup konfigurasi..."
CONFIG_SRC="$(dirname "$0")/../webapps/eujian-sync/config.json"
if [ -f "$CONFIG_SRC" ]; then
    cp "$CONFIG_SRC" "$BACKUP_DIR/config.json"
    info "config.json disalin"
else
    warning "config.json tidak ditemukan, dilewati."
fi

# ── Simpan metadata ───────────────────────────────────────────
cat > "$BACKUP_DIR/backup-info.txt" << EOF
E-UJIAN Moodle Backup
=====================
Tanggal  : $(date "+%Y-%m-%d %H:%M:%S")
Hostname : $(hostname)
Docker   :
  DB Container    : $DB_CONTAINER
  Moodle Container: $MOODLE_CONTAINER
  DB Name         : $DB_NAME

Files:
  database.sql.gz  — dump database MySQL
  moodledata.tar.gz — file uploads Moodle (gambar soal, dll)
  config.json       — konfigurasi koneksi Web App
EOF

# ── Ringkasan ─────────────────────────────────────────────────
TOTAL_SIZE=$(du -sh "$BACKUP_DIR" | cut -f1)
echo ""
echo "═══════════════════════════════════════"
info "Backup selesai! Total: $TOTAL_SIZE"
echo "   Lokasi: $BACKUP_DIR"
echo "═══════════════════════════════════════"
echo ""
echo "Untuk restore, jalankan:"
echo "   ./restore.sh $BACKUP_DIR"
echo ""
