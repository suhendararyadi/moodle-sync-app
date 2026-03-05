#!/bin/bash
# ═══════════════════════════════════════════════════════════════
# restore.sh — Restore data Moodle Docker dari folder backup
#
# Usage:
#   ./restore.sh ./backups/2025-01-15_10-30
#
# ⚠️  PERINGATAN: Restore akan MENIMPA data yang ada!
# ═══════════════════════════════════════════════════════════════

set -e

# ── Konfigurasi ───────────────────────────────────────────────
DB_CONTAINER="eujian-db"
MOODLE_CONTAINER="eujian-moodle"
DB_NAME="moodle"
DB_USER="moodle"
DB_PASS="moodlepassword"

# ── Warna output ─────────────────────────────────────────────
GREEN='\033[0;32m'; YELLOW='\033[1;33m'; RED='\033[0;31m'; NC='\033[0m'
info()    { echo -e "${GREEN}[✓]${NC} $1"; }
warning() { echo -e "${YELLOW}[!]${NC} $1"; }
error()   { echo -e "${RED}[✗]${NC} $1"; exit 1; }

# ── Validasi argumen ──────────────────────────────────────────
BACKUP_DIR="${1:-}"
[ -z "$BACKUP_DIR" ] && error "Harap tentukan folder backup.\nContoh: ./restore.sh ./backups/2025-01-15_10-30"
[ -d "$BACKUP_DIR" ] || error "Folder backup tidak ditemukan: $BACKUP_DIR"

echo ""
echo "═══════════════════════════════════════"
echo "   E-UJIAN Moodle Restore Tool"
echo "═══════════════════════════════════════"
echo ""

# Tampilkan info backup
if [ -f "$BACKUP_DIR/backup-info.txt" ]; then
    cat "$BACKUP_DIR/backup-info.txt"
    echo ""
fi

# Konfirmasi
echo -e "${YELLOW}⚠️  PERINGATAN: Data yang ada akan DITIMPA!${NC}"
read -p "Lanjutkan restore? (ketik 'ya' untuk konfirmasi): " CONFIRM
[ "$CONFIRM" = "ya" ] || { echo "Dibatalkan."; exit 0; }
echo ""

# ── Cek container berjalan ────────────────────────────────────
docker ps --format '{{.Names}}' | grep -q "^$DB_CONTAINER$" || error "Container '$DB_CONTAINER' tidak berjalan. Jalankan: docker compose up -d"

# ── 1. Restore Database ───────────────────────────────────────
if [ -f "$BACKUP_DIR/database.sql.gz" ]; then
    echo "[ 1/3 ] Restore database..."
    # Drop & recreate database
    docker exec "$DB_CONTAINER" mysql -u"$DB_USER" -p"$DB_PASS" \
        -e "DROP DATABASE IF EXISTS $DB_NAME; CREATE DATABASE $DB_NAME CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
    # Restore
    gunzip -c "$BACKUP_DIR/database.sql.gz" | \
        docker exec -i "$DB_CONTAINER" mysql -u"$DB_USER" -p"$DB_PASS" "$DB_NAME"
    info "Database berhasil di-restore"
else
    warning "database.sql.gz tidak ditemukan, dilewati."
fi

# ── 2. Restore Moodledata ─────────────────────────────────────
if [ -f "$BACKUP_DIR/moodledata.tar.gz" ]; then
    echo "[ 2/3 ] Restore moodledata..."
    docker ps --format '{{.Names}}' | grep -q "^$MOODLE_CONTAINER$" || error "Container '$MOODLE_CONTAINER' tidak berjalan."

    # Hapus data lama dan restore
    docker exec "$MOODLE_CONTAINER" sh -c "rm -rf /var/moodledata/*" 2>/dev/null || true
    docker exec -i "$MOODLE_CONTAINER" tar xzf - -C / < "$BACKUP_DIR/moodledata.tar.gz" 2>/dev/null || \
    warning "Gagal restore moodledata, dilewati."
    info "Moodledata berhasil di-restore"
else
    warning "moodledata.tar.gz tidak ditemukan, dilewati."
fi

# ── 3. Restore config.json ────────────────────────────────────
if [ -f "$BACKUP_DIR/config.json" ]; then
    echo "[ 3/3 ] Restore konfigurasi..."
    CONFIG_DEST="$(dirname "$0")/../webapps/eujian-sync/config.json"
    cp "$BACKUP_DIR/config.json" "$CONFIG_DEST"
    info "config.json di-restore ke $CONFIG_DEST"
else
    warning "config.json tidak ditemukan, dilewati."
fi

# ── Purge Moodle cache ────────────────────────────────────────
echo ""
echo "Membersihkan cache Moodle..."
docker exec "$MOODLE_CONTAINER" php /var/www/html/admin/cli/purge_caches.php 2>/dev/null && \
    info "Cache Moodle dibersihkan" || \
    warning "Tidak bisa purge cache (jalankan manual dari admin panel)"

echo ""
echo "═══════════════════════════════════════"
info "Restore selesai!"
echo "═══════════════════════════════════════"
echo ""
echo "Buka Moodle di browser untuk memverifikasi."
echo ""
