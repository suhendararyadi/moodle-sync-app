#!/bin/bash
# ═══════════════════════════════════════════════════════════════
# clone-to-new-server.sh
# Panduan interaktif untuk kloning Moodle ke komputer/server baru
#
# Jalankan di KOMPUTER BARU setelah file backup dipindahkan
# Usage:
#   ./clone-to-new-server.sh ./backups/2025-01-15_10-30
# ═══════════════════════════════════════════════════════════════

set -e

GREEN='\033[0;32m'; YELLOW='\033[1;33m'; CYAN='\033[0;36m'; NC='\033[0m'
info()  { echo -e "${GREEN}[✓]${NC} $1"; }
step()  { echo -e "${CYAN}[→]${NC} $1"; }
warn()  { echo -e "${YELLOW}[!]${NC} $1"; }

BACKUP_DIR="${1:-}"
SCRIPT_DIR="$(dirname "$0")"

echo ""
echo "═══════════════════════════════════════════════════"
echo "   E-UJIAN — Clone ke Server Baru"
echo "═══════════════════════════════════════════════════"
echo ""

# ── Cek Docker tersedia ───────────────────────────────────────
command -v docker &>/dev/null || { echo "❌ Docker belum terinstall."; exit 1; }
command -v docker-compose &>/dev/null || docker compose version &>/dev/null || { echo "❌ Docker Compose belum tersedia."; exit 1; }
info "Docker tersedia"

# ── Langkah 1: Jalankan docker compose ───────────────────────
step "Langkah 1: Menjalankan Docker containers..."
if docker ps --format '{{.Names}}' | grep -q "eujian"; then
    info "Container sudah berjalan"
else
    cd "$SCRIPT_DIR"
    docker compose up -d
    echo "Menunggu database siap..."
    sleep 15
    info "Container berjalan"
fi

# ── Langkah 2: Restore dari backup ───────────────────────────
if [ -n "$BACKUP_DIR" ] && [ -d "$BACKUP_DIR" ]; then
    step "Langkah 2: Restore data dari backup..."
    bash "$SCRIPT_DIR/restore.sh" "$BACKUP_DIR"
else
    warn "Tidak ada folder backup yang diberikan."
    echo ""
    echo "Untuk restore data, jalankan:"
    echo "   ./restore.sh ./backups/<folder-backup>"
    echo ""
    echo "Jika ini instalasi baru (tanpa data), Moodle sudah siap di:"
    echo "   http://localhost:8080"
fi

echo ""
echo "═══════════════════════════════════════════════════"
info "Selesai! Akses aplikasi di:"
echo "   🌐 Moodle    : http://localhost:8080"
echo "   🔧 Sync App  : http://localhost:8081/eujian-sync"
echo "═══════════════════════════════════════════════════"
echo ""
