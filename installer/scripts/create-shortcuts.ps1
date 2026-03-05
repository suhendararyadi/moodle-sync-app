# ============================================================
# Buat Desktop & Start Menu shortcuts
# ============================================================

param(
    [string]$InstallDir = "C:\MoodleKelas",
    [string]$LaragonDir = "C:\MoodleKelas\laragon",
    [string]$SyncAppDir = "C:\MoodleKelas\MoodleSyncApp",
    [string]$LocalPort  = "8080"
)

$shell      = New-Object -ComObject WScript.Shell
$desktop    = [Environment]::GetFolderPath("CommonDesktopDirectory")
$startMenu  = [Environment]::GetFolderPath("CommonPrograms")
$moodleUrl  = if ($LocalPort -eq "80") { "http://localhost/moodle" } else { "http://localhost:$LocalPort/moodle" }

# Buat folder Start Menu
$smFolder = "$startMenu\E-UJIAN SMKN 9 Garut"
if (-not (Test-Path $smFolder)) { New-Item -ItemType Directory $smFolder | Out-Null }

# ─── Helper function ─────────────────────────────────────────
function New-Shortcut($dest, $target, $args="", $icon="", $desc="") {
    $lnk = $shell.CreateShortcut($dest)
    $lnk.TargetPath      = $target
    $lnk.Arguments       = $args
    $lnk.Description     = $desc
    if ($icon) { $lnk.IconLocation = $icon }
    $lnk.Save()
}

# ─── 1. Shortcut: Buka Moodle di Browser ───────────────────
$browserTarget = "cmd.exe"
$browserArgs   = "/c start $moodleUrl"

New-Shortcut "$desktop\E-UJIAN SMKN 9 Garut.lnk" `
    $browserTarget $browserArgs "" "Buka E-UJIAN di Browser"

New-Shortcut "$smFolder\Buka E-UJIAN.lnk" `
    $browserTarget $browserArgs "" "Buka E-UJIAN di Browser"

# ─── 2. Shortcut: E-UJIAN Sync App ─────────────────────────
New-Shortcut "$desktop\E-UJIAN Sync App.lnk" `
    "$SyncAppDir\MoodleSyncApp.exe" "" `
    "$SyncAppDir\MoodleSyncApp.exe,0" `
    "E-UJIAN Sync App - Sinkronisasi Ujian"

New-Shortcut "$smFolder\E-UJIAN Sync App.lnk" `
    "$SyncAppDir\MoodleSyncApp.exe" "" "" `
    "E-UJIAN Sync App"

# ─── 3. Shortcut: Laragon ──────────────────────────────────
New-Shortcut "$smFolder\Laragon.lnk" `
    "$LaragonDir\laragon.exe" "" "" "Laragon Web Server"

# ─── 4. Shortcut: Stop/Start Laragon ───────────────────────
$startPs = @"
Start-Process '$LaragonDir\laragon.exe' -ArgumentList '--autostart'
"@
Set-Content "$InstallDir\start-laragon.ps1" $startPs

New-Shortcut "$smFolder\Start Server.lnk" `
    "powershell.exe" `
    "-ExecutionPolicy Bypass -File `"$InstallDir\start-laragon.ps1`"" `
    "" "Start Laragon Server"

Write-Host "  Shortcuts dibuat di Desktop dan Start Menu"
Write-Host "  - E-UJIAN SMKN 9 Garut → $moodleUrl"
Write-Host "  - E-UJIAN Sync App     → $SyncAppDir\MoodleSyncApp.exe"
