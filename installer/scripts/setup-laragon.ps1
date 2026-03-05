# ============================================================
# Setup Laragon Portable
# ============================================================

param(
    [string]$InstallDir = "C:\MoodleKelas",
    [string]$LocalPort  = "8080"
)

$LaragonDir = "$InstallDir\laragon"
$NginxConf  = "$LaragonDir\etc\nginx\nginx.conf"

# Cari php.ini secara dinamis (versi PHP bisa berbeda)
$PhpIni = Get-ChildItem "$LaragonDir\bin\php" -Filter "php.ini" -Recurse -ErrorAction SilentlyContinue |
          Select-Object -First 1 -ExpandProperty FullName

# Cari Apache httpd.conf juga (Laragon bisa pakai Apache atau Nginx)
$ApacheConf = Get-ChildItem "$LaragonDir\etc\apache2" -Filter "httpd.conf" -Recurse -ErrorAction SilentlyContinue |
              Select-Object -First 1 -ExpandProperty FullName

# Pastikan Laragon sudah di-extract ke folder ini
if (-not (Test-Path "$LaragonDir\laragon.exe")) {
    throw "Laragon tidak ditemukan di $LaragonDir. Pastikan file installer lengkap."
}

# ─── Konfigurasi port Nginx ─────────────────────────────────
if (Test-Path $NginxConf) {
    (Get-Content $NginxConf) `
        -replace 'listen\s+80;', "listen $LocalPort;" `
        -replace 'listen\s+\[::\]:80;', "listen [::]:$LocalPort;" |
    Set-Content $NginxConf
    Write-Host "  Nginx port diset ke $LocalPort"
}

# ─── Konfigurasi port Apache ─────────────────────────────────
if ($ApacheConf -and (Test-Path $ApacheConf)) {
    (Get-Content $ApacheConf) `
        -replace 'Listen\s+80', "Listen $LocalPort" `
        -replace 'ServerName\s+localhost:80', "ServerName localhost:$LocalPort" |
    Set-Content $ApacheConf
    Write-Host "  Apache port diset ke $LocalPort"
}

# ─── Konfigurasi PHP ─────────────────────────────────────────
if ($PhpIni -and (Test-Path $PhpIni)) {
    $phpSettings = @{
        "memory_limit"       = "256M"
        "upload_max_filesize"= "128M"
        "post_max_size"      = "128M"
        "max_execution_time" = "300"
        "max_input_vars"     = "5000"
        "date.timezone"      = "Asia/Jakarta"
    }
    $content = Get-Content $PhpIni
    foreach ($key in $phpSettings.Keys) {
        $val     = $phpSettings[$key]
        $content = $content -replace "^;?$key\s*=.*", "$key = $val"
    }
    $content | Set-Content $PhpIni
    Write-Host "  PHP dikonfigurasi (memory, upload, timezone)"
}

# ─── Buat folder moodledata ──────────────────────────────────
$moodleData = "$InstallDir\moodledata"
if (-not (Test-Path $moodleData)) {
    New-Item -ItemType Directory -Path $moodleData | Out-Null
    Write-Host "  Folder moodledata dibuat: $moodleData"
}

# ─── Start Laragon services ──────────────────────────────────
Write-Host "  Memulai Laragon services..."
Start-Process -FilePath "$LaragonDir\laragon.exe" -ArgumentList "--autostart" -WindowStyle Hidden
Start-Sleep -Seconds 5
Write-Host "  Laragon services dimulai"
