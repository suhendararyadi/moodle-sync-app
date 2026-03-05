# 📦 Cara Build Installer MoodleKelas-Setup.exe

## Prasyarat (lakukan di Windows)

### 1. Install Tools yang Dibutuhkan
| Tool | Download |
|------|----------|
| [Inno Setup 6](https://jrsoftware.org/isdl.php) | Gratis, untuk compile installer |
| [.NET 10 Desktop Runtime](https://dotnet.microsoft.com/download/dotnet/10.0) | Untuk MoodleSyncApp |

---

## Struktur folder yang dibutuhkan

```
installer/
├── MoodleKelas-Setup.iss        ← file ini
├── scripts/                     ← sudah ada
├── resources/                   ← buat manual (lihat di bawah)
│   ├── icon.ico                 ← icon aplikasi (opsional)
│   ├── wizard-banner.bmp        ← 497×314 px
│   └── wizard-icon.bmp          ← 55×58 px
└── components/                  ← HARUS DI-DOWNLOAD MANUAL
    ├── laragon/                 ← extract Laragon portable di sini
    └── moodle/                  ← extract Moodle source di sini

publish/
└── windows/                     ← hasil build MoodleSyncApp (sudah ada)

dist/                            ← OUTPUT installer .exe (auto dibuat)
```

---

## Langkah 1: Download Komponen

### A. Laragon Portable
1. Download dari: https://github.com/leokhoa/laragon/releases
2. Pilih versi: **laragon-wamp.exe** (bukan installer, ini portable)
3. Jalankan `.exe` → pilih extract only → extract ke `installer/components/laragon/`

### B. Moodle Source Code
1. Download dari: https://download.moodle.org/releases/latest/
2. Pilih: **moodle-latest-500.tgz** (atau versi 5.0.x)
3. Extract → rename folder hasil extract menjadi `moodle`
4. Pindahkan ke `installer/components/moodle/`

---

## Langkah 2: Build MoodleSyncApp (dari Mac/Windows)

```bash
# Di terminal Mac atau Windows (dalam folder moodle-sync-app/)
dotnet publish src/MoodleSyncApp/MoodleSyncApp.csproj \
  -r win-x64 -c Release --self-contained true \
  -o ./publish/windows
```

---

## Langkah 3: Compile Installer

1. Buka `installer/MoodleKelas-Setup.iss` dengan **Inno Setup Compiler**
2. Klik **Build → Compile** (atau tekan `F9`)
3. Output: `installer/dist/MoodleKelas-Setup.exe` (~250–400MB)

---

## Distribusi ke Setiap Ruang Kelas

1. Copy `MoodleKelas-Setup.exe` ke USB/drive
2. Jalankan di setiap komputer server kelas sebagai **Administrator**
3. Wizard akan meminta:
   - **Room ID**: contoh `ROOM_01`, `LAB_TKJ_1`
   - **Nama Ruangan**: contoh `Lab Komputer 1`
   - **URL Server Utama**: IP server Moodle pusat, contoh `http://192.168.1.1/moodle`
   - **Port Moodle Lokal**: default `8080`

---

## Setelah Install

1. Browser otomatis membuka `http://localhost:8080/moodle`
2. Selesaikan wizard instalasi Moodle (buat admin, nama situs, dll.)
3. Aktifkan Web Services di Moodle → dapatkan token
4. Buka **Moodle Sync App** → Settings → masukkan token
5. Sync!

---

## Catatan Penting

- Laragon auto-start bersama Windows (bisa dimatikan di msconfig)
- File konfigurasi Sync App: `C:\MoodleKelas\MoodleSyncApp\appsettings.json`
- Log setup: `C:\MoodleKelas\setup.log`
- Data Moodle: `C:\MoodleKelas\moodledata\`
- Database: MySQL di `C:\MoodleKelas\laragon\data\mysql\`

## Struktur Instalasi Final di PC Kelas

```
C:\MoodleKelas\
├── laragon\              ← Web server (Apache/Nginx + PHP + MySQL)
│   ├── laragon.exe
│   ├── www\
│   │   └── moodle\       ← Source Moodle
│   └── bin\mysql\        ← MySQL executable
├── MoodleSyncApp\        ← Aplikasi sinkronisasi
│   ├── MoodleSyncApp.exe
│   └── appsettings.json  ← Config (RoomID, URL, Token)
├── moodledata\           ← File uploads Moodle
├── backups\              ← Backup otomatis
└── setup.log             ← Log instalasi
```
