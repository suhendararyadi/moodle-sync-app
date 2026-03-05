# ============================================================
# MoodleKelas - Main Setup Script
# Dijalankan oleh Inno Setup setelah extract selesai
# ============================================================

param(
    [string]$InstallDir  = "C:\MoodleKelas",
    [string]$RoomId      = "ROOM_01",
    [string]$RoomName    = "Ruang Lab Komputer 1",
    [string]$MasterUrl   = "http://192.168.1.1/moodle",
    [string]$LocalPort   = "8080",
    [string]$DbName      = "moodle_lokal",
    [string]$DbPassword  = "MoodleKelas2024!"
)

$ErrorActionPreference = "Stop"

function Write-Step($msg) {
    Write-Host "`n[SETUP] $msg" -ForegroundColor Cyan
}

function Write-OK($msg) {
    Write-Host "  [OK] $msg" -ForegroundColor Green
}

function Write-Fail($msg) {
    Write-Host "  [FAIL] $msg" -ForegroundColor Red
}

# ─── PATHS ─────────────────────────────────────────────────
$LaragonDir  = "$InstallDir\laragon"
$MoodleDir   = "$LaragonDir\www\moodle"
$MoodleData  = "$InstallDir\moodledata"
$SyncAppDir  = "$InstallDir\MoodleSyncApp"
$LogFile     = "$InstallDir\setup.log"

Start-Transcript -Path $LogFile -Append

try {
    # ─── 1. LARAGON ────────────────────────────────────────
    Write-Step "Menyiapkan Laragon..."
    & "$PSScriptRoot\setup-laragon.ps1" -InstallDir $InstallDir -LocalPort $LocalPort
    Write-OK "Laragon siap"

    # ─── 2. DATABASE ───────────────────────────────────────
    Write-Step "Membuat database MySQL..."
    & "$PSScriptRoot\setup-database.ps1" `
        -LaragonDir $LaragonDir `
        -DbName $DbName `
        -DbPassword $DbPassword
    Write-OK "Database '$DbName' dibuat"

    # ─── 3. MOODLE ─────────────────────────────────────────
    Write-Step "Mengkonfigurasi Moodle..."
    & "$PSScriptRoot\setup-moodle.ps1" `
        -MoodleDir $MoodleDir `
        -MoodleData $MoodleData `
        -DbName $DbName `
        -DbPassword $DbPassword `
        -LocalPort $LocalPort
    Write-OK "Moodle dikonfigurasi"

    # ─── 4. SYNC APP CONFIG ────────────────────────────────
    Write-Step "Menyimpan konfigurasi Sync App..."
    $config = @{
        MasterUrl   = $MasterUrl
        MasterToken = ""
        LocalUrl    = "http://localhost:$LocalPort/moodle"
        LocalToken  = ""
        RoomId      = $RoomId
        RoomName    = $RoomName
        SshHost     = ""
        SshPort     = 22
        SshUsername = ""
        SshPassword = ""
        MoodleDataPath = $MoodleData
        BackupPath  = "$InstallDir\backups"
        AutoSyncEnabled = $false
        AutoSyncIntervalMinutes = 15
    }
    $config | ConvertTo-Json -Depth 5 | `
        Set-Content "$SyncAppDir\appsettings.json" -Encoding UTF8
    Write-OK "Config Sync App disimpan"

    # ─── 5. SHORTCUTS ──────────────────────────────────────
    Write-Step "Membuat shortcut di Desktop..."
    & "$PSScriptRoot\create-shortcuts.ps1" `
        -InstallDir $InstallDir `
        -LaragonDir $LaragonDir `
        -SyncAppDir $SyncAppDir `
        -LocalPort $LocalPort
    Write-OK "Shortcut dibuat"

    # ─── 6. STARTUP ────────────────────────────────────────
    Write-Step "Mendaftarkan Laragon ke Windows Startup..."
    $startupPath = [Environment]::GetFolderPath("Startup")
    $shell   = New-Object -ComObject WScript.Shell
    $lnk     = $shell.CreateShortcut("$startupPath\EUJIAN-Laragon.lnk")
    $lnk.TargetPath  = "$LaragonDir\laragon.exe"
    $lnk.Arguments   = "--autostart"
    $lnk.WorkingDir  = $LaragonDir
    $lnk.Save()
    Write-OK "Laragon akan auto-start bersama Windows"

    Write-Host "`n============================================" -ForegroundColor Green
    Write-Host " INSTALASI E-UJIAN BERHASIL!" -ForegroundColor Green
    Write-Host " E-UJIAN SMKN 9 Garut siap digunakan." -ForegroundColor Green
    Write-Host " Pengembang: Suhendar Aryadi" -ForegroundColor Green
    Write-Host "============================================`n" -ForegroundColor Green
    Write-Host " URL Moodle Lokal  : http://localhost:$LocalPort/moodle"
    Write-Host " Sync App          : $SyncAppDir\MoodleSyncApp.exe"
    Write-Host " Room ID           : $RoomId"
    Write-Host " Master Server     : $MasterUrl"
    Write-Host ""
    Write-Host " PENTING: Buka Moodle di browser dan selesaikan"
    Write-Host " instalasi pertama (setup admin Moodle)."
    Write-Host ""

} catch {
    Write-Fail "Setup gagal: $_"
    Write-Host "`nDetail error tersimpan di: $LogFile" -ForegroundColor Yellow
    exit 1
} finally {
    Stop-Transcript
}
