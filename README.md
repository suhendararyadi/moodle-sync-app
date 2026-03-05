# Moodle Sync App

Aplikasi desktop untuk sinkronisasi data ujian antara **Server Utama (Master)** dan **Server Lokal (Kelas)** menggunakan Moodle REST API.

## Arsitektur

```
Server Utama (Master) ←→ [Moodle Sync App] ←→ Server Lokal (Kelas)
                              |
                          Moodle REST API
```

## Tech Stack

- **Framework UI**: Avalonia UI 11 + .NET 8
- **Pattern**: MVVM (CommunityToolkit.Mvvm)
- **Database Lokal**: LiteDB (log & state)
- **HTTP Client**: System.Net.Http + Newtonsoft.Json
- **File Transfer**: SSH.NET (SFTP)

## Struktur Project

```
moodle-sync-app/
├── src/
│   ├── MoodleSyncApp/              ← UI Layer (Avalonia)
│   │   ├── Views/                  ← XAML Views
│   │   ├── ViewModels/             ← MVVM ViewModels
│   │   └── Assets/                 ← Icons, images
│   │
│   └── MoodleSyncApp.Core/         ← Business Logic
│       ├── Config/                 ← AppConfig
│       ├── Models/                 ← Data models
│       └── Services/               ← API, Sync, Log services
```

## Fitur

| Fitur | Status |
|---|---|
| Pre-Exam: Sync User dari Master | ✅ Done |
| Pre-Exam: Download Course/Quiz | 🚧 WIP |
| Monitor Ujian Real-time | 🚧 WIP |
| Post-Exam: Export Hasil Ujian | ✅ Done |
| Post-Exam: Upload ke Master | 🚧 WIP |
| Pengaturan Koneksi Server | ✅ Done |
| Auto-backup Database | 🚧 WIP |
| Log Aktivitas | 🚧 WIP |

## Setup Development

```bash
# 1. Install .NET 8 SDK
brew install --cask dotnet-sdk

# 2. Install Avalonia templates
dotnet new install Avalonia.Templates

# 3. Restore packages
cd moodle-sync-app
dotnet restore

# 4. Run
dotnet run --project src/MoodleSyncApp

# 5. Build untuk Windows
dotnet publish src/MoodleSyncApp -r win-x64 -c Release --self-contained
```

## Moodle API Setup

Di server Moodle (master & lokal):
1. `Site Admin → Plugins → Web services → Enable web services` ✅
2. `Site Admin → Plugins → Web services → Manage protocols → REST` ✅  
3. `Site Admin → Plugins → Web services → Add service` → tambah fungsi:
   - `core_webservice_get_site_info`
   - `core_user_get_users`
   - `core_user_create_users`
   - `core_enrol_get_enrolled_users`
   - `core_course_get_courses`
   - `mod_quiz_get_user_attempts`
4. Generate token → paste ke Settings di app
