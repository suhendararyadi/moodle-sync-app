# ============================================================
# Download & Extract Laragon + Moodle
# Dijalankan oleh setup-main.ps1 sebelum konfigurasi
# ============================================================

param(
    [string]$InstallDir = "C:\MoodleKelas"
)

$LaragonDir = "$InstallDir\laragon"
$MoodleDir  = "$LaragonDir\www\moodle"
$TempDir    = "$InstallDir\_temp_download"

# URL Download
$LaragonUrl = "https://github.com/leokhoa/laragon/releases/download/6.0/laragon-wamp.exe"
$MoodleUrl  = "https://download.moodle.org/download.php/direct/stable500/moodle-latest-500.zip"

# Buat temp folder
if (-not (Test-Path $TempDir)) {
    New-Item -ItemType Directory -Path $TempDir -Force | Out-Null
}

# ─── HELPER ─────────────────────────────────────────────────
function Download-File($url, $dest) {
    Write-Host "  Mendownload: $url"
    Write-Host "  Ke: $dest"

    # Coba BITS transfer dulu (lebih cepat, ada progress)
    try {
        Import-Module BitsTransfer -ErrorAction Stop
        Start-BitsTransfer -Source $url -Destination $dest -DisplayName "Downloading..." -ErrorAction Stop
        return
    } catch {
        Write-Host "  BITS gagal, menggunakan Invoke-WebRequest..."
    }

    # Fallback ke Invoke-WebRequest
    $ProgressPreference = 'SilentlyContinue'
    Invoke-WebRequest -Uri $url -OutFile $dest -UseBasicParsing
    $ProgressPreference = 'Continue'
}

# ═══════════════════════════════════════════════════════════
# 1. DOWNLOAD & INSTALL LARAGON
# ═══════════════════════════════════════════════════════════
if (-not (Test-Path "$LaragonDir\laragon.exe")) {
    Write-Host "`n[DOWNLOAD] Mengunduh Laragon..." -ForegroundColor Cyan

    $laragonExe = "$TempDir\laragon-wamp.exe"
    Download-File $LaragonUrl $laragonExe

    Write-Host "  Menginstall Laragon ke $LaragonDir ..."
    
    # Laragon installer = Inno Setup, jalankan silent install
    $args = "/VERYSILENT /SUPPRESSMSGBOXES /NORESTART /DIR=`"$LaragonDir`""
    $proc = Start-Process -FilePath $laragonExe -ArgumentList $args `
        -Wait -PassThru -WindowStyle Hidden
    
    if ($proc.ExitCode -ne 0) {
        throw "Laragon installer gagal (exit code: $($proc.ExitCode))"
    }

    # Pastikan laragon.exe ada setelah install
    if (-not (Test-Path "$LaragonDir\laragon.exe")) {
        throw "Laragon tidak terinstall dengan benar. laragon.exe tidak ditemukan."
    }

    Write-Host "  [OK] Laragon terinstall di $LaragonDir" -ForegroundColor Green
} else {
    Write-Host "  [OK] Laragon sudah ada, skip download." -ForegroundColor Green
}

# ═══════════════════════════════════════════════════════════
# 2. DOWNLOAD & EXTRACT MOODLE
# ═══════════════════════════════════════════════════════════
if (-not (Test-Path "$MoodleDir\version.php")) {
    Write-Host "`n[DOWNLOAD] Mengunduh Moodle 5.0..." -ForegroundColor Cyan

    $moodleZip = "$TempDir\moodle.zip"
    Download-File $MoodleUrl $moodleZip

    Write-Host "  Mengekstrak Moodle ke $MoodleDir ..."

    # Buat folder www jika belum ada
    $wwwDir = "$LaragonDir\www"
    if (-not (Test-Path $wwwDir)) {
        New-Item -ItemType Directory -Path $wwwDir -Force | Out-Null
    }

    # Extract zip - isinya folder "moodle/" di root
    Expand-Archive -Path $moodleZip -DestinationPath $wwwDir -Force

    # Verifikasi
    if (-not (Test-Path "$MoodleDir\version.php")) {
        # Mungkin di-extract ke subfolder lain, cari
        $found = Get-ChildItem -Path $wwwDir -Filter "version.php" -Recurse | Select-Object -First 1
        if ($found) {
            $extractedDir = $found.DirectoryName
            if ($extractedDir -ne $MoodleDir) {
                # Rename/move ke lokasi yang benar
                if (Test-Path $MoodleDir) { Remove-Item $MoodleDir -Recurse -Force }
                Move-Item -Path $extractedDir -Destination $MoodleDir -Force
            }
        } else {
            throw "Moodle gagal di-extract. version.php tidak ditemukan."
        }
    }

    Write-Host "  [OK] Moodle 5.0 terinstall di $MoodleDir" -ForegroundColor Green
} else {
    Write-Host "  [OK] Moodle sudah ada, skip download." -ForegroundColor Green
}

# ═══════════════════════════════════════════════════════════
# 3. CLEANUP TEMP
# ═══════════════════════════════════════════════════════════
if (Test-Path $TempDir) {
    Remove-Item -Path $TempDir -Recurse -Force -ErrorAction SilentlyContinue
    Write-Host "  Temp files dibersihkan."
}

Write-Host "`n[DOWNLOAD] Semua komponen siap!" -ForegroundColor Green
