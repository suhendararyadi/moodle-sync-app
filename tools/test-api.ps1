# ============================================================
# E-UJIAN SMKN 9 Garut — Tes Koneksi Moodle API
# Jalankan: powershell -ExecutionPolicy Bypass -File test-api.ps1
# ============================================================

param(
    [string]$Url   = "",
    [string]$Token = "",
    [int]   $CourseId = 0
)

# ── Warna output ──────────────────────────────────────────────
function OK   { param($msg) Write-Host "  [OK] $msg"    -ForegroundColor Green  }
function FAIL { param($msg) Write-Host "  [GAGAL] $msg" -ForegroundColor Red    }
function INFO { param($msg) Write-Host "  [INFO] $msg"  -ForegroundColor Cyan   }
function HEAD { param($msg) Write-Host "`n=== $msg ===" -ForegroundColor Yellow }

# ── Ambil input kalau belum diisi ────────────────────────────
if (-not $Url)   { $Url   = Read-Host "Masukkan URL Moodle (contoh: http://moodle.sekolah.id)" }
if (-not $Token) { $Token = Read-Host "Masukkan Token API Moodle" }

$Url = $Url.TrimEnd('/')
$endpoint = "$Url/webservice/rest/server.php"

# ── Fungsi helper call API ────────────────────────────────────
function CallApi {
    param([string]$function, [hashtable]$params = @{})
    $body = @{
        wstoken            = $Token
        wsfunction         = $function
        moodlewsrestformat = "json"
    }
    foreach ($k in $params.Keys) { $body[$k] = $params[$k] }

    try {
        $resp = Invoke-RestMethod -Uri $endpoint -Method Post -Body $body -TimeoutSec 15
        return $resp
    } catch {
        return $null
    }
}

# ════════════════════════════════════════════════════════════
HEAD "1. TES KONEKSI SERVER"
# ════════════════════════════════════════════════════════════

try {
    $ping = Invoke-WebRequest -Uri $Url -TimeoutSec 10 -UseBasicParsing
    OK "Server dapat dijangkau (HTTP $($ping.StatusCode))"
} catch {
    FAIL "Server tidak dapat dijangkau: $_"
    Write-Host "`nPastikan URL benar dan server aktif." -ForegroundColor Red
    exit 1
}

# ════════════════════════════════════════════════════════════
HEAD "2. TES TOKEN API"
# ════════════════════════════════════════════════════════════

$siteInfo = CallApi "core_webservice_get_site_info"

if ($siteInfo -and $siteInfo.sitename) {
    OK "Token valid!"
    INFO "Nama Situs : $($siteInfo.sitename)"
    INFO "Versi      : $($siteInfo.release)"
    INFO "Username   : $($siteInfo.username)"
    INFO "Fullname   : $($siteInfo.fullname)"
} elseif ($siteInfo -and $siteInfo.exception) {
    FAIL "Token tidak valid: $($siteInfo.message)"
    Write-Host "`nSolusi:" -ForegroundColor Yellow
    Write-Host "  1. Login Moodle Admin → Site Administration → Server → Web Services → Manage Tokens"
    Write-Host "  2. Buat token baru atau copy token yang ada"
    exit 1
} else {
    FAIL "Tidak ada respons dari API"
    exit 1
}

# ════════════════════════════════════════════════════════════
HEAD "3. TES IZIN (CAPABILITIES)"
# ════════════════════════════════════════════════════════════

$functions = @(
    "core_enrol_get_enrolled_users",
    "core_course_get_courses",
    "core_course_get_courses_by_field",
    "core_user_get_users",
    "mod_quiz_get_quizzes_by_courses"
)

$allowedFunctions = $siteInfo.functions | ForEach-Object { $_.name }

foreach ($fn in $functions) {
    if ($allowedFunctions -contains $fn) {
        OK "$fn"
    } else {
        FAIL "$fn  ← BELUM DIIZINKAN di service token ini"
    }
}

Write-Host ""
INFO "Kalau ada yang GAGAL: Moodle Admin → Web Services → Manage Services → Add functions"

# ════════════════════════════════════════════════════════════
HEAD "4. TES AMBIL DAFTAR KURSUS"
# ════════════════════════════════════════════════════════════

$courses = CallApi "core_course_get_courses"

if ($courses -and $courses.Count -gt 0) {
    OK "Berhasil ambil $($courses.Count) kursus"
    $courses | Select-Object -First 5 | ForEach-Object {
        INFO "  ID: $($_.id) | $($_.shortname) | $($_.fullname)"
    }
    if ($courses.Count -gt 5) { INFO "  ... dan $($courses.Count - 5) kursus lainnya" }
} elseif ($courses -and $courses.exception) {
    FAIL "Gagal ambil kursus: $($courses.message)"
} else {
    FAIL "Tidak ada kursus atau tidak ada izin"
}

# ════════════════════════════════════════════════════════════
HEAD "5. TES AMBIL SISWA DI KURSUS"
# ════════════════════════════════════════════════════════════

if ($CourseId -eq 0) {
    $CourseId = Read-Host "`nMasukkan Course ID untuk tes ambil siswa (0 = skip)"
    $CourseId = [int]$CourseId
}

if ($CourseId -gt 0) {
    $users = CallApi "core_enrol_get_enrolled_users" @{ courseid = "$CourseId" }

    if ($users -and $users.Count -gt 0) {
        OK "Berhasil ambil $($users.Count) pengguna di kursus ID $CourseId"
        $users | Select-Object -First 5 | ForEach-Object {
            INFO "  ID: $($_.id) | $($_.username) | $($_.fullname)"
        }
        if ($users.Count -gt 5) { INFO "  ... dan $($users.Count - 5) pengguna lainnya" }
    } elseif ($users -and $users.exception) {
        FAIL "Gagal: $($users.message)"
    } else {
        FAIL "Tidak ada siswa atau kursus ID $CourseId tidak ditemukan"
    }

    # ── Cek quiz di kursus
    HEAD "6. TES AMBIL QUIZ DI KURSUS $CourseId"
    $quizzes = CallApi "mod_quiz_get_quizzes_by_courses" @{ "courseids[0]" = "$CourseId" }

    if ($quizzes -and $quizzes.quizzes -and $quizzes.quizzes.Count -gt 0) {
        OK "Ditemukan $($quizzes.quizzes.Count) quiz"
        $quizzes.quizzes | Select-Object -First 5 | ForEach-Object {
            INFO "  ID: $($_.id) | $($_.name) | Kursus: $($_.course)"
        }
    } elseif ($quizzes -and $quizzes.exception) {
        FAIL "Gagal ambil quiz: $($quizzes.message)"
    } else {
        INFO "Tidak ada quiz di kursus ini (atau tidak ada izin mod_quiz)"
    }
} else {
    INFO "Tes kursus dilewati."
}

# ════════════════════════════════════════════════════════════
HEAD "7. CEK PLUGIN E-UJIAN (opsional)"
# ════════════════════════════════════════════════════════════

$ping = CallApi "local_eujian_ping"
if ($ping -and $ping.status -eq "ok") {
    OK "Plugin E-UJIAN terpasang! Versi: $($ping.version)"
} else {
    INFO "Plugin E-UJIAN belum terpasang (tidak wajib, fitur standar tetap bisa dipakai)"
}

# ════════════════════════════════════════════════════════════
Write-Host "`n============================================" -ForegroundColor Yellow
Write-Host "  Tes selesai!" -ForegroundColor Green
Write-Host "============================================" -ForegroundColor Yellow
Write-Host ""
