# ============================================================
# Persiapan Build Installer MoodleKelas
# Jalankan script ini di Windows SEBELUM compile .iss
# Script ini download Laragon + Moodle ke folder components/
# ============================================================

$ErrorActionPreference = "Stop"
$ScriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$ComponentsDir = "$ScriptDir\..\components"

# URL Download (sesuaikan versi jika perlu)
$LaragonUrl  = "https://github.com/leokhoa/laragon/releases/download/8.6.0/laragon-wamp.exe"
$MoodleUrl   = "https://download.moodle.org/download.php/stable500/moodle-5.0.6.zip"

$LaragonDir  = "$ComponentsDir\laragon"
$MoodleDir   = "$ComponentsDir\moodle"
$TempDir     = "$ComponentsDir\_temp"

Write-Host "============================================" -ForegroundColor Cyan
Write-Host " Persiapan Build - MoodleKelas Installer" -ForegroundColor Cyan
Write-Host "============================================" -ForegroundColor Cyan
Write-Host ""

# Buat folder
foreach ($dir in @($ComponentsDir, $TempDir)) {
    if (-not (Test-Path $dir)) { New-Item -ItemType Directory -Path $dir -Force | Out-Null }
}

# ─── 1. LARAGON ──────────────────────────────────────────────
if (Test-Path "$LaragonDir\laragon.exe") {
    Write-Host "[OK] Laragon sudah ada, skip." -ForegroundColor Green
} else {
    Write-Host "[1/2] Mengunduh Laragon..." -ForegroundColor Yellow
    $laragonExe = "$TempDir\laragon-wamp.exe"

    $ProgressPreference = 'SilentlyContinue'
    Invoke-WebRequest -Uri $LaragonUrl -OutFile $laragonExe -UseBasicParsing
    $ProgressPreference = 'Continue'

    Write-Host "  Download selesai. Mengekstrak..."

    # Laragon installer = Inno Setup, extract silent ke components/laragon
    Start-Process -FilePath $laragonExe `
        -ArgumentList "/VERYSILENT /SUPPRESSMSGBOXES /NORESTART /DIR=`"$LaragonDir`"" `
        -Wait -WindowStyle Hidden

    if (Test-Path "$LaragonDir\laragon.exe") {
        Write-Host "[OK] Laragon siap di $LaragonDir" -ForegroundColor Green
    } else {
        Write-Host "[FAIL] Laragon gagal di-extract!" -ForegroundColor Red
        exit 1
    }
}

# ─── 2. MOODLE ──────────────────────────────────────────────
if (Test-Path "$MoodleDir\version.php") {
    Write-Host "[OK] Moodle sudah ada, skip." -ForegroundColor Green
} else {
    Write-Host "[2/2] Mengunduh Moodle 5.0.6..." -ForegroundColor Yellow
    $moodleZip = "$TempDir\moodle.zip"

    $ProgressPreference = 'SilentlyContinue'
    Invoke-WebRequest -Uri $MoodleUrl -OutFile $moodleZip -UseBasicParsing
    $ProgressPreference = 'Continue'

    Write-Host "  Download selesai. Mengekstrak..."

    # Extract ke temp dulu
    $extractTemp = "$TempDir\moodle_extract"
    Expand-Archive -Path $moodleZip -DestinationPath $extractTemp -Force

    # Cari folder moodle di hasil extract
    $moodleSource = Get-ChildItem -Path $extractTemp -Filter "version.php" -Recurse |
                    Select-Object -First 1 -ExpandProperty DirectoryName

    if ($moodleSource) {
        if (Test-Path $MoodleDir) { Remove-Item $MoodleDir -Recurse -Force }
        Move-Item -Path $moodleSource -Destination $MoodleDir -Force
        Write-Host "[OK] Moodle siap di $MoodleDir" -ForegroundColor Green
    } else {
        Write-Host "[FAIL] Moodle gagal di-extract!" -ForegroundColor Red
        exit 1
    }
}

# ─── CLEANUP ─────────────────────────────────────────────────
if (Test-Path $TempDir) {
    Remove-Item $TempDir -Recurse -Force -ErrorAction SilentlyContinue
}

Write-Host ""
Write-Host "============================================" -ForegroundColor Green
Write-Host " SIAP! Sekarang compile MoodleKelas-Setup.iss" -ForegroundColor Green
Write-Host " dengan Inno Setup Compiler (tekan F9)" -ForegroundColor Green
Write-Host "============================================" -ForegroundColor Green
Write-Host ""
Write-Host "Ukuran komponen:" -ForegroundColor Gray
if (Test-Path $LaragonDir) {
    $lSize = (Get-ChildItem $LaragonDir -Recurse | Measure-Object -Property Length -Sum).Sum / 1MB
    Write-Host "  Laragon : $([math]::Round($lSize, 0)) MB" -ForegroundColor Gray
}
if (Test-Path $MoodleDir) {
    $mSize = (Get-ChildItem $MoodleDir -Recurse | Measure-Object -Property Length -Sum).Sum / 1MB
    Write-Host "  Moodle  : $([math]::Round($mSize, 0)) MB" -ForegroundColor Gray
}
