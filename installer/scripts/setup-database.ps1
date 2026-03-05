# ============================================================
# Setup MySQL Database untuk Moodle
# ============================================================

param(
    [string]$LaragonDir = "C:\MoodleKelas\laragon",
    [string]$DbName     = "moodle_lokal",
    [string]$DbPassword = "MoodleKelas2024!",
    [string]$DbUser     = "moodle_user"
)

$MySqlExe = "$LaragonDir\bin\mysql\mysql8.0\bin\mysql.exe"

# Fallback ke mysql versi lain jika tidak ada
if (-not (Test-Path $MySqlExe)) {
    $MySqlExe = Get-ChildItem "$LaragonDir\bin\mysql" -Filter "mysql.exe" -Recurse |
                Select-Object -First 1 -ExpandProperty FullName
}

if (-not $MySqlExe) {
    throw "MySQL tidak ditemukan di $LaragonDir\bin\mysql"
}

# Tunggu MySQL benar-benar ready
$maxRetry = 10
for ($i = 0; $i -lt $maxRetry; $i++) {
    $test = & $MySqlExe -u root --connect-timeout=3 -e "SELECT 1;" 2>&1
    if ($LASTEXITCODE -eq 0) { break }
    Write-Host "  Menunggu MySQL ready... ($($i+1)/$maxRetry)"
    Start-Sleep -Seconds 3
}

# Buat database dan user
$sql = @"
CREATE DATABASE IF NOT EXISTS ``$DbName``
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

CREATE USER IF NOT EXISTS '$DbUser'@'localhost' IDENTIFIED BY '$DbPassword';
GRANT ALL PRIVILEGES ON ``$DbName``.* TO '$DbUser'@'localhost';
FLUSH PRIVILEGES;
"@

$sql | & $MySqlExe -u root
Write-Host "  Database '$DbName' dan user '$DbUser' berhasil dibuat"
