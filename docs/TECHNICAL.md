# Referensi Teknis E-UJIAN Sync

Dokumen ini ditujukan untuk **pengembang** yang ingin memahami, mengembangkan, atau memelihara sistem E-UJIAN Sync.

---

## Daftar Isi

1. [Arsitektur Sistem](#1-arsitektur-sistem)
2. [Alur API (API Flow)](#2-alur-api-api-flow)
3. [Plugin Moodle: local_eujian](#3-plugin-moodle-local_eujian)
4. [Dokumentasi 8 Fungsi Web Service](#4-dokumentasi-8-fungsi-web-service)
5. [Implementasi SSE (Server-Sent Events)](#5-implementasi-sse-server-sent-events)
6. [Web App — Struktur dan Routing](#6-web-app--struktur-dan-routing)
7. [CLI Tool — Struktur](#7-cli-tool--struktur)
8. [Sinkronisasi Gambar Quiz (Base64)](#8-sinkronisasi-gambar-quiz-base64)
9. [Skema Database & Konfigurasi](#9-skema-database--konfigurasi)
10. [Keterbatasan yang Diketahui](#10-keterbatasan-yang-diketahui)

---

## 1. Arsitektur Sistem

```
┌─────────────────────────────────────────────────────────────────────────┐
│                     E-UJIAN SYNC — KOMPONEN                             │
│                                                                         │
│  ┌───────────────────────────────────────────────────────────────────┐  │
│  │                    WEB APP (PHP 8.2)                              │  │
│  │  index.php (Router)  ←→  pages/*.php (UI)                        │  │
│  │        │                      │                                   │  │
│  │  lib/Config.php          ajax/stream.php (SSE)                   │  │
│  │  lib/MoodleApi.php  ←←←────────┘                                │  │
│  │  lib/SyncService.php                                              │  │
│  └──────────────────────────┬────────────────────────────────────────┘  │
│                             │ HTTP REST (cURL)                          │
│  ┌──────────────────────────┼────────────────────────────────────────┐  │
│  │            CLI TOOL (.NET 8 / C#)                                 │  │
│  │  MoodleSync.CLI/Program.cs                                        │  │
│  │  Core/Services/MoodleApiService.cs                                │  │
│  │  Core/Services/SyncService.cs                                     │  │
│  └──────────────────────────┼────────────────────────────────────────┘  │
│                             │ REST API                                   │
└─────────────────────────────┼───────────────────────────────────────────┘
                              │
           ┌──────────────────┼──────────────────┐
           ▼                                      ▼
  ┌─────────────────┐               ┌─────────────────────────┐
  │  MOODLE MASTER  │               │    MOODLE LOKAL          │
  │  (VPS/Internet) │               │  (Docker/Laragon/LAN)   │
  │                 │               │                          │
  │ Plugin: local_  │               │  Plugin: local_eujian   │
  │ eujian          │               │                          │
  │                 │               │  ┌────────────────────┐  │
  │ Fungsi:         │               │  │   SISWA (Browser)  │  │
  │ - export_quiz   │               │  │   (via LAN/WiFi)   │  │
  │ - receive_      │               │  └────────────────────┘  │
  │   results       │               │                          │
  └─────────────────┘               └─────────────────────────┘
```

### Stack Teknologi

| Layer | Teknologi | Versi |
|---|---|---|
| Web App UI | PHP + Tailwind CSS | PHP 8.2 |
| Web App Server | Apache HTTP Server | 2.4+ |
| CLI Tool | C# / .NET | .NET 8 |
| Plugin Moodle | PHP | Moodle 4.1+ |
| Database | MySQL/MariaDB (Moodle) | MariaDB 10.11 |
| Konfigurasi | JSON file | — |
| Containerisasi | Docker | 24+ |

---

## 2. Alur API (API Flow)

### 2.1 Sync Kursus + Siswa

```
Web App          Master Moodle         Lokal Moodle
   │                   │                     │
   ├──getCourseById()──►│                     │
   │◄──course data─────┤                     │
   │                   │                     │
   ├──────────────────────────getCourses()──►│
   │◄─────────────────────────local courses──┤
   │                   │                     │
   ├── (buat/skip kursus lokal) ─────────────►
   │                   │                     │
   ├──getEnrolledUsers()►                    │
   │◄──siswa[]─────────┤                     │
   │                   │                     │
   ├── (batch 50) ──── createUsers() ───────►│
   ├── (batch 50) ──── enrollUsers() ───────►│
   │                   │                     │
   ├──getCohorts()─────►                     │
   │◄──cohorts[]───────┤                     │
   ├─────────────────────────createCohort()─►│
   ├───────────────────────addCohortMembers()►
   │                   │                     │
   ■ SSE: done event                         │
```

### 2.2 Sync Quiz (Export → Import)

```
Web App          Master Moodle         Lokal Moodle
   │                   │                     │
   ├──exportQuiz(id)───►                     │
   │  (fungsi: local_eujian_export_quiz)     │
   │◄──exportjson──────┤                     │
   │  {quiz:{...},                           │
   │   questions:[                           │
   │     {soal, answers, files:[base64]}     │
   │   ]}                                    │
   │                   │                     │
   ├──────────────────────importQuiz(json)──►│
   │  (fungsi: local_eujian_import_quiz)     │
   │  courseid, masterquizid, quizjson       │
   │◄────────────────────────{quizid, status}┤
   │                   │                     │
   ■ SSE: done event (dengan quizid lokal)   │
```

### 2.3 Upload Hasil Ujian

```
Web App          Master Moodle         Lokal Moodle
   │                   │                     │
   ├────────────────────getQuizMasterId()────►│
   │  (local_eujian_get_quiz_masterid)        │
   │◄───────────────────{masterquizid, ...}───┤
   │                   │                     │
   ├────────────────────getQuizResults()─────►│
   │  (local_eujian_get_quiz_results)         │
   │◄───────────────────attempts[]────────────┤
   │                   │                     │
   │  [hitung nilai: (sumgrades/max)*grade]  │
   │                   │                     │
   ├──receiveResults(masterQuizId, results)──►
   │  (local_eujian_receive_results)          │
   │◄──{processed: N}──┤                     │
   │                   │                     │
   ■ SSE: done event                         │
```

---

## 3. Plugin Moodle: local_eujian

### Struktur Plugin

```
plugin/eujian/
├── db/
│   └── services.php     ← Registrasi fungsi & service
├── lang/
│   └── en/
│       └── local_eujian.php  ← String bahasa
├── externallib.php      ← Implementasi semua fungsi WS
└── version.php          ← Versi plugin
```

### Registrasi Service

Plugin mendaftarkan satu service bernama **"E-UJIAN Sync Service"** (shortname: `eujian_sync`) yang berisi semua 8 fungsi web service.

```php
// db/services.php
$services = [
    'E-UJIAN Sync Service' => [
        'functions'       => [
            'local_eujian_get_students',
            'local_eujian_get_quiz_data',
            'local_eujian_get_quiz_results',
            'local_eujian_ping',
            'local_eujian_export_quiz',
            'local_eujian_import_quiz',
            'local_eujian_get_quiz_masterid',
            'local_eujian_receive_results',
        ],
        'restrictedusers' => 0,
        'enabled'         => 1,
        'shortname'       => 'eujian_sync',
    ],
];
```

---

## 4. Dokumentasi 8 Fungsi Web Service

### 4.1 `local_eujian_ping`

**Deskripsi**: Health check — cek koneksi dan info dasar server.

**Parameter**: *(tidak ada)*

**Return**:
```json
{
  "status":  "ok",
  "site":    "https://lms.sekolah.sch.id/",
  "version": "2024042200",
  "time":    1718000000
}
```

**Digunakan oleh**: Dashboard (status server), MoodleApi::ping()

---

### 4.2 `local_eujian_get_students`

**Deskripsi**: Ambil daftar siswa yang terdaftar di suatu kursus.

**Capability**: `moodle/course:viewparticipants`

**Parameter**:
| Nama | Tipe | Keterangan |
|---|---|---|
| `courseid` | int | ID kursus Moodle |

**Return**:
```json
{
  "courseid": 42,
  "count": 35,
  "students": [
    {
      "id": 123,
      "username": "siswa001",
      "firstname": "Budi",
      "lastname": "Santoso",
      "fullname": "Budi Santoso",
      "email": "budi@sekolah.id",
      "idnumber": "202401001"
    }
  ]
}
```

**Catatan**: Hanya mengambil user dengan role `student` (roleid = 5).

---

### 4.3 `local_eujian_get_quiz_data`

**Deskripsi**: Ambil info semua quiz di suatu kursus (metadata, tanpa soal).

**Capability**: `mod/quiz:viewreports`

**Parameter**:
| Nama | Tipe | Keterangan |
|---|---|---|
| `courseid` | int | ID kursus |

**Return**:
```json
{
  "courseid": 42,
  "count": 2,
  "quizzes": [
    {
      "id": 10,
      "name": "UTS Matematika",
      "intro": "Kerjakan dengan jujur",
      "timeopen": 1718000000,
      "timeclose": 1718003600,
      "timelimit": 5400,
      "attempts": 1,
      "grade": 100.0,
      "sumgrades": 30.0
    }
  ]
}
```

---

### 4.4 `local_eujian_get_quiz_results`

**Deskripsi**: Ambil semua hasil attempt quiz yang sudah selesai (`state = 'finished'`).

**Capability**: `mod/quiz:viewreports`

**Parameter**:
| Nama | Tipe | Keterangan |
|---|---|---|
| `quizid` | int | ID quiz |

**Return**:
```json
{
  "quizid": 10,
  "quizname": "UTS Matematika",
  "count": 35,
  "attempts": [
    {
      "attemptid": 201,
      "userid": 123,
      "username": "siswa001",
      "fullname": "Budi Santoso",
      "idnumber": "202401001",
      "attempt": 1,
      "sumgrades": 25.0,
      "state": "finished",
      "timestart": 1718000100,
      "timefinish": 1718002500
    }
  ]
}
```

---

### 4.5 `local_eujian_export_quiz`

**Deskripsi**: Export quiz beserta semua soal (termasuk gambar) dalam format JSON. **Dijalankan di server master.**

**Capability**: `moodle/course:manageactivities`

**Parameter**:
| Nama | Tipe | Keterangan |
|---|---|---|
| `quizid` | int | ID quiz di master |

**Return**:
```json
{
  "quizid": 10,
  "questioncount": 30,
  "exportjson": "{...JSON lengkap...}"
}
```

**Struktur `exportjson` (setelah di-parse)**:
```json
{
  "quiz": {
    "id": 10,
    "name": "UTS Matematika",
    "timelimit": 5400,
    "attempts": 1,
    "grade": 100.0,
    "sumgrades": 30.0,
    "shuffleanswers": 1
  },
  "questions": [
    {
      "slot": 1,
      "page": 1,
      "maxmark": 1.0,
      "type": "multichoice",
      "name": "Soal 1",
      "text": "Berapakah 2+2? @@PLUGINFILE@@/gambar.png",
      "answers": [
        {"text": "3", "fraction": 0.0, "feedback": ""},
        {"text": "4", "fraction": 1.0, "feedback": "Benar!"}
      ],
      "files": [
        {
          "filename": "gambar.png",
          "mimetype": "image/png",
          "filearea": "questiontext",
          "content": "iVBORw0KGgo..."
        }
      ],
      "single": 1,
      "shuffleanswers": 1
    }
  ]
}
```

**Tipe soal yang didukung**: `multichoice`, `truefalse`, `shortanswer`

---

### 4.6 `local_eujian_import_quiz`

**Deskripsi**: Import quiz dari JSON hasil `export_quiz` ke kursus lokal. **Dijalankan di server lokal.**

**Capability**: `moodle/course:manageactivities`

**Parameter**:
| Nama | Tipe | Keterangan |
|---|---|---|
| `courseid` | int | ID kursus lokal tujuan |
| `masterquizid` | int | Quiz ID di master (untuk deteksi duplikat) |
| `quizjson` | string | JSON dari export_quiz (di-serialize) |

**Return**:
```json
{
  "quizid": 5,
  "questioncount": 30,
  "status": "created",
  "message": "Quiz berhasil diimport: UTS Matematika"
}
```

Nilai `status`:
- `"created"` — quiz berhasil diimport
- `"skipped"` — quiz sudah ada (idnumber duplikat), tidak dibuat ulang

**Mekanisme deteksi duplikat**: Fungsi menyimpan `idnumber = eujian_quiz_[masterquizid]` pada `course_modules`. Setiap import baru dicek terhadap nilai ini.

---

### 4.7 `local_eujian_get_quiz_masterid`

**Deskripsi**: Ambil master quiz ID dari quiz lokal (kebalikan dari import). **Dijalankan di server lokal.**

**Parameter**:
| Nama | Tipe | Keterangan |
|---|---|---|
| `localquizid` | int | Quiz ID di server lokal |

**Return**:
```json
{
  "localquizid": 5,
  "masterquizid": 10,
  "quizname": "UTS Matematika",
  "sumgrades": 30.0,
  "grade": 100.0,
  "found": true
}
```

**Cara kerja**: Membaca `course_modules.idnumber`, mengekstrak angka dari pola `eujian_quiz_[N]`.

---

### 4.8 `local_eujian_receive_results`

**Deskripsi**: Terima hasil ujian dari server kelas, simpan ke gradebook Moodle master. **Dijalankan di server master.**

**Capability**: `moodle/grade:edit`

**Parameter**:
| Nama | Tipe | Keterangan |
|---|---|---|
| `masterquizid` | int | Quiz ID di master |
| `roomid` | string | ID ruangan (opsional) |
| `results` | array | Array hasil siswa |

**Struktur `results[]`**:
| Field | Tipe | Keterangan |
|---|---|---|
| `username` | string | Username siswa |
| `rawgrade` | float | Nilai (sudah dikonversi ke skala master) |
| `timestart` | int | Unix timestamp mulai ujian |
| `timefinish` | int | Unix timestamp selesai ujian |

**Return**:
```json
{
  "processed": 35,
  "skipped": 0,
  "message": "35 nilai berhasil disimpan"
}
```

---

## 5. Implementasi SSE (Server-Sent Events)

Web App menggunakan **Server-Sent Events** untuk menampilkan progress sync secara real-time tanpa perlu polling.

### Endpoint SSE

```
GET /eujian-sync/ajax/stream.php?action=[action]&[params]
```

**Actions yang tersedia**:
| Action | Parameter | Keterangan |
|---|---|---|
| `sync_course` | `courseid` | Sync kursus + siswa |
| `sync_all` | — | Sync semua siswa |
| `sync_cohort` | — | Sync cohort |
| `sync_quiz` | `quizid`, `local_courseid` | Sync quiz |
| `upload` | `quizid` | Upload hasil ujian |

### Format Event SSE

```
Content-Type: text/event-stream

event: progress
data: {"pct":25,"msg":"Ambil daftar siswa dari master..."}

event: progress
data: {"pct":50,"msg":"Batch 1/2 — buat user..."}

event: done
data: {"pct":100,"msg":"Selesai! 35 siswa disinkronisasi.","done":true}

: heartbeat

event: error_msg
data: "Kursus ID 99 tidak ditemukan di master."
```

**Event types**:
- `progress` — update kemajuan (pct: 0-100, msg: string)
- `done` — proses selesai (selalu pct: 100)
- `error_msg` — terjadi error
- `: heartbeat` — ping untuk mencegah timeout koneksi (setiap event)

### Implementasi Server-Side (PHP Generator)

`SyncService.php` menggunakan **PHP Generator** (`yield`) untuk streaming progress:

```php
// SyncService.php
public function syncCourse(int $masterCourseId): \Generator {
    yield ['pct' => 5,  'msg' => "Ambil info kursus dari master..."];
    // ... proses ...
    yield ['pct' => 100, 'msg' => "Selesai!", 'done' => true];
}
```

`stream.php` mengiterasi generator dan mengirim setiap item sebagai event SSE:

```php
// ajax/stream.php
foreach ($svc->syncCourse($courseId) as $p) {
    sse(isset($p['done']) ? 'done' : 'progress', $p);
    heartbeat();
}
```

### Implementasi Client-Side (JavaScript)

```javascript
// assets/app.js
const es = new EventSource(`ajax/stream.php?action=sync_course&courseid=${id}`);

es.addEventListener('progress', e => {
    const data = JSON.parse(e.data);
    updateProgressBar(data.pct);
    appendLog(data.msg);
});

es.addEventListener('done', e => {
    const data = JSON.parse(e.data);
    showSuccess(data.msg);
    es.close();
});

es.addEventListener('error_msg', e => {
    showError(e.data);
    es.close();
});
```

### Konfigurasi PHP untuk SSE

`Dockerfile` mengatur nilai PHP yang diperlukan:

```
max_execution_time = 300    ← proses sync bisa panjang
memory_limit = 512M         ← buffer untuk data banyak siswa
```

Header HTTP yang diperlukan:
```
Content-Type: text/event-stream
Cache-Control: no-cache
X-Accel-Buffering: no       ← nonaktifkan buffering Nginx/proxy
Connection: keep-alive
```

---

## 6. Web App — Struktur dan Routing

### Router Utama (`index.php`)

```php
$page = preg_replace('/[^a-z_]/', '', strtolower($_GET['page'] ?? 'dashboard'));
$allowed = ['dashboard', 'courses', 'students', 'sync_course', 'sync_all',
            'sync_cohort', 'sync_quiz', 'upload', 'results', 'settings'];
if (!in_array($page, $allowed)) $page = 'dashboard';
include "pages/$page.php";
```

Router menggunakan whitelist untuk keamanan.

### Halaman-halaman

| File | URL | Keterangan |
|---|---|---|
| `pages/dashboard.php` | `?page=dashboard` | Status server + aksi cepat |
| `pages/courses.php` | `?page=courses` | Daftar kursus master |
| `pages/students.php` | `?page=students` | Daftar siswa per kursus |
| `pages/sync_course.php` | `?page=sync_course` | Form + SSE viewer sync kursus |
| `pages/sync_all.php` | `?page=sync_all` | Form + SSE viewer sync semua siswa |
| `pages/sync_cohort.php` | `?page=sync_cohort` | Form + SSE viewer sync cohort |
| `pages/sync_quiz.php` | `?page=sync_quiz` | Form + SSE viewer sync quiz |
| `pages/upload.php` | `?page=upload` | Form + SSE viewer upload hasil |
| `pages/results.php` | `?page=results` | Tabel hasil ujian |
| `pages/settings.php` | `?page=settings` | Form konfigurasi |

### Dark Mode

Dark mode diimplementasikan via Tailwind CSS `darkMode: "class"`:

```html
<!-- Cegah flash of wrong theme saat load halaman -->
<script>
(function(){
  const t = localStorage.getItem('eujian-theme');
  if (t === 'dark' || (!t && window.matchMedia('(prefers-color-scheme: dark)').matches))
    document.documentElement.classList.add('dark');
})()
</script>
```

State disimpan di `localStorage` dengan key `eujian-theme`.

---

## 7. CLI Tool — Struktur

### File Utama

```
src/MoodleSync.CLI/Program.cs     ← Semua logika CLI (top-level statements)
src/MoodleSyncApp.Core/
  Config/AppConfig.cs             ← Model konfigurasi
  Services/
    MoodleApiService.cs           ← HTTP client Moodle REST API
    SyncService.cs                ← Logika sync (shared dengan .NET app)
    LogService.cs                 ← Logging ke SQLite
    ConfigService.cs              ← Load/save konfigurasi
```

### Konfigurasi CLI

CLI membaca dari file `sync-config.json` di direktori yang sama dengan binary:

```json
{
  "MasterUrl": "https://lms.sekolah.sch.id/",
  "MasterToken": "token...",
  "LocalUrl": "http://localhost:8080/",
  "LocalToken": "token...",
  "RoomId": "1",
  "RoomName": "Server Kelas",
  "SshHost": "",
  "SshPort": 22,
  "SshUsername": "",
  "SshPassword": "",
  "MoodleDataPath": "/var/www/moodledata",
  "BackupPath": "/sync/backups"
}
```

### Arsitektur CLI

```csharp
// Program.cs menggunakan top-level statements (C# 10+)
Console.OutputEncoding = System.Text.Encoding.UTF8;

var config   = LoadOrCreateConfig(configPath);
var logger   = new LogService();
var syncSvc  = new SyncService(config, logger);
var masterApi = new MoodleApiService();
masterApi.Configure(config.MasterUrl, config.MasterToken);

// Event-based progress reporting
syncSvc.OnProgress += (msg, pct) =>
    Console.Write($"\r  [{pct,3}%] {msg,-60}");
```

---

## 8. Sinkronisasi Gambar Quiz (Base64)

### Masalah

Soal ujian sering mengandung gambar (diagram, grafik, ilustrasi). Gambar di Moodle disimpan sebagai file terpisah di `moodledata/` dan direferensikan dengan URL khusus `@@PLUGINFILE@@/nama-gambar.ext` dalam teks soal.

Ketika quiz di-sync ke server lokal yang berbeda domain, URL gambar tersebut tidak akan bekerja karena mengacu ke server master.

### Solusi: Embed Base64

Fungsi `export_quiz` mengambil setiap file yang terlampir pada soal (`questiontext`) dan jawaban (`answer`) dari Moodle file storage API, mengkonversinya ke **Base64**, dan menyertakannya dalam JSON ekspor:

```php
// externallib.php — export_quiz()
$files = $fs->get_area_files(
    $qcatctx->contextid,
    'question',
    'questiontext',
    $qv->questionid,
    'id',
    false
);
foreach ($files as $file) {
    $qdata['files'][] = [
        'filename' => $file->get_filename(),
        'mimetype' => $file->get_mimetype(),
        'filearea' => 'questiontext',
        'content'  => base64_encode($file->get_content()),  // ← embed
    ];
}
```

### Proses Import

Saat `import_quiz` dijalankan di server lokal, file di-decode dari Base64 dan disimpan ke Moodle file storage lokal:

```php
// externallib.php — import_quiz()
$content = base64_decode($fdata['content']);
if ($content !== false) {
    $fs->create_file_from_string($fileinfo, $content);
}
```

### Pertimbangan Ukuran

| Skenario | Ukuran per soal | Total 30 soal |
|---|---|---|
| Teks saja | ~1 KB | ~30 KB |
| Dengan gambar PNG kecil (20KB) | ~27 KB | ~810 KB |
| Dengan gambar foto (200KB) | ~270 KB | ~8 MB |

Untuk quiz dengan banyak gambar besar, proses export/import bisa memakan waktu lebih lama dan membutuhkan `memory_limit` PHP yang cukup (default sudah diatur ke 512M di Dockerfile).

### Keterbatasan

- Hanya file yang terlampir langsung pada soal (`questiontext`) dan jawaban (`answer`) yang di-export
- File yang di-embed dalam teks HTML menggunakan tag `<img src="@@PLUGINFILE@@...">` akan ter-handle otomatis karena Moodle mengganti URL saat rendering
- Ukuran JSON export bisa sangat besar untuk soal dengan gambar resolusi tinggi

---

## 9. Skema Database & Konfigurasi

### Tabel Moodle yang Digunakan Plugin

**Tabel yang dibaca:**
| Tabel | Kegunaan |
|---|---|
| `quiz` | Info quiz (name, timelimit, grade, dll) |
| `quiz_slots` | Mapping slot ke soal |
| `quiz_attempts` | Hasil attempt siswa |
| `question_references` | Link slot ke question bank entry |
| `question_versions` | Versi soal |
| `question` | Data soal |
| `question_answers` | Pilihan jawaban |
| `question_bank_entries` | Entri di bank soal |
| `question_categories` | Kategori bank soal |
| `qtype_multichoice_options` | Opsi khusus pilihan ganda |
| `qtype_shortanswer_options` | Opsi khusus isian singkat |

**Tabel yang ditulis (saat import):**
| Tabel | Operasi |
|---|---|
| `question_categories` | INSERT (kategori baru per quiz) |
| `question_bank_entries` | INSERT |
| `question_versions` | INSERT |
| `question` | INSERT |
| `question_answers` | INSERT |
| `quiz` | INSERT |
| `course_modules` | INSERT (dengan `idnumber = eujian_quiz_N`) |
| `course_sections` | UPDATE sequence |
| `quiz_sections` | INSERT |
| `quiz_slots` | INSERT |
| `question_references` | INSERT |

**Tabel yang ditulis (saat receive_results):**
| Tabel | Operasi |
|---|---|
| `grade_grades` | INSERT/UPDATE |
| `grade_items` | SELECT (lookup) |

### File Konfigurasi Aplikasi

Web App dan CLI menggunakan format JSON yang **identik**:

```
config.json / sync-config.json
{
  "MasterUrl":   string   URL server Moodle master
  "MasterToken": string   Token WS server master
  "LocalUrl":    string   URL server Moodle lokal
  "LocalToken":  string   Token WS server lokal
  "RoomId":      string   ID ruangan
  "RoomName":    string   Nama ruangan
}
```

CLI memiliki field tambahan untuk fitur SSH/SFTP (belum digunakan di Web App):
```
  "SshHost":         string
  "SshPort":         int
  "SshUsername":     string
  "SshPassword":     string
  "MoodleDataPath":  string
  "BackupPath":      string
```

### Database SQLite (CLI)

CLI menggunakan SQLite untuk menyimpan log dan status (via `LogService.cs`):

| File | Isi |
|---|---|
| `moodle-sync.db` | State sinkronisasi |
| `moodle-sync-log.db` | Log aktivitas |

---

## 10. Keterbatasan yang Diketahui

### Tipe Soal

Plugin hanya mendukung **3 tipe soal**:
- `multichoice` (pilihan ganda)
- `truefalse` (benar/salah)
- `shortanswer` (isian singkat)

Tipe soal lain (essay, calculated, matching, dll) akan **dilewati** saat export. Soal yang dilewati tidak akan muncul di quiz lokal.

### Struktur Soal

- Soal bertingkat (question nesting/subquestion) tidak didukung
- Soal dengan media audio/video tidak didukung (hanya gambar)
- Random question dari question bank tidak didukung

### Sinkronisasi

- **Tidak ada sync dua arah untuk soal**: Jika soal di master diubah setelah sync, perlu sync ulang manual
- **Tidak ada delta sync**: Setiap sync kursus selalu mengambil semua siswa (bukan hanya yang baru)
- **Batch create_users**: Jika ada 1 user invalid dalam batch 50, seluruh batch gagal, lalu di-retry satu per satu (lebih lambat)
- **Cohort sync**: Hanya menambahkan anggota, tidak menghapus anggota yang sudah dihapus di master

### Upload Hasil

- **Hanya quiz yang diimport via plugin** yang bisa diupload (quiz manual tidak memiliki `masterquizid`)
- **Grade method**: Selalu mengambil nilai attempt pertama yang selesai (bukan nilai tertinggi)
- Upload tidak bisa dibatalkan setelah dimulai

### Infrastruktur

- Web App tidak memiliki autentikasi — siapa pun yang bisa mengakses URL dapat menggunakan semua fitur
- Tidak ada logging audit untuk operasi yang dilakukan via Web App
- SSE connection timeout bergantung pada konfigurasi reverse proxy (Nginx default 60s — perlu disesuaikan untuk sync besar)

### Kompatibilitas Moodle

- Plugin menggunakan struktur tabel Moodle **4.x** (`question_versions`, `question_references`)
- **Tidak kompatibel** dengan Moodle 3.x yang menggunakan struktur tabel lama
- Diuji pada Moodle 4.1 dan 4.3
