using MoodleSyncApp.Core.Config;
using MoodleSyncApp.Core.Services;
using Newtonsoft.Json.Linq;

// ══════════════════════════════════════════════════════════════
//  E-UJIAN SMKN 9 Garut — CLI Sync Tool
//  Pengembang: Suhendar Aryadi
// ══════════════════════════════════════════════════════════════

Console.OutputEncoding = System.Text.Encoding.UTF8;
PrintHeader();

// ── Load atau buat konfigurasi ────────────────────────────────
var configPath = Path.Combine(AppContext.BaseDirectory, "sync-config.json");
var config     = LoadOrCreateConfig(configPath);
var logger     = new LogService();
var syncSvc    = new SyncService(config, logger);
var masterApi  = new MoodleApiService();
masterApi.Configure(config.MasterUrl, config.MasterToken);

// ── Menu utama ────────────────────────────────────────────────
bool running = true;
while (running)
{
    PrintMenu();
    var key = Console.ReadLine()?.Trim();
    Console.WriteLine();

    switch (key)
    {
        case "1": await MenuTestKoneksi();      break;
        case "2": await MenuDaftarKursus();     break;
        case "3": await MenuDaftarSiswa();      break;
        case "4": await MenuSyncKursus();       break;
        case "5": await MenuSyncSemuaSiswa();   break;
        case "6": await MenuSyncSiswa();        break;
        case "7": await MenuSyncCohort();       break;
        case "8": await MenuDaftarQuiz();       break;
        case "9": await MenuExportHasil();      break;
        case "u": await MenuUploadHasil();      break;
        case "q": await MenuSyncQuiz();         break;
        case "s": MenuAturKonfigurasi();        break;
        case "c": MenuTampilKonfigurasi();      break;
        case "0": running = false;              break;
        default:  Warn("Pilihan tidak valid, coba lagi."); break;
    }
}

OK("Sampai jumpa!");

// ══════════════════════════════════════════════════════════════
//  MENU HANDLERS
// ══════════════════════════════════════════════════════════════

async Task MenuTestKoneksi()
{
    Head("TES KONEKSI SERVER");

    // Master
    Write("Mengecek server MASTER... ");
    try
    {
        var info = await masterApi.GetSiteNameAsync();
        OK($"BERHASIL — Situs: {info}");
    }
    catch (Exception ex) { Fail($"GAGAL — {ex.Message}"); }

    // Lokal
    if (!string.IsNullOrWhiteSpace(config.LocalUrl) && !string.IsNullOrWhiteSpace(config.LocalToken))
    {
        Write("Mengecek server LOKAL...  ");
        var localApi = new MoodleApiService();
        localApi.Configure(config.LocalUrl, config.LocalToken);
        try
        {
            var info = await localApi.GetSiteNameAsync();
            OK($"BERHASIL — Situs: {info}");
        }
        catch (Exception ex) { Fail($"GAGAL — {ex.Message}"); }
    }
    else
    {
        Warn("Server lokal belum dikonfigurasi (opsional untuk test master).");
    }

    Pause();
}

async Task MenuDaftarKursus()
{
    Head("DAFTAR KURSUS DI SERVER MASTER");
    try
    {
        Info("Mengambil daftar kursus...");
        var courses = await masterApi.GetCoursesAsync();

        if (courses.Count == 0) { Warn("Tidak ada kursus atau tidak ada izin."); Pause(); return; }

        Console.WriteLine();
        Console.WriteLine($"  {"ID",-6} {"Shortname",-20} {"Fullname",-40}");
        Console.WriteLine($"  {new string('-', 70)}");
        foreach (var c in courses.OrderBy(x => x.Id))
            Console.WriteLine($"  {c.Id,-6} {c.Shortname,-20} {c.Fullname,-40}");

        OK($"\nTotal: {courses.Count} kursus");
    }
    catch (Exception ex) { Fail($"Gagal: {ex.Message}"); }
    Pause();
}

async Task MenuDaftarSiswa()
{
    Head("DAFTAR SISWA DI KURSUS");
    var cid = TanyaInt("Masukkan Course ID: ");
    if (cid <= 0) { Warn("ID tidak valid."); return; }

    try
    {
        Info($"Mengambil siswa di kursus ID {cid}...");
        var users = await masterApi.GetEnrolledUsersAsync(cid);

        if (users.Count == 0) { Warn("Tidak ada siswa atau kursus tidak ditemukan."); Pause(); return; }

        Console.WriteLine();
        Console.WriteLine($"  {"ID",-6} {"Username",-20} {"NIS/Idnumber",-15} {"Nama Lengkap",-30}");
        Console.WriteLine($"  {new string('-', 75)}");
        foreach (var u in users)
            Console.WriteLine($"  {u.Id,-6} {u.Username,-20} {u.Idnumber,-15} {u.FullName,-30}");

        OK($"\nTotal: {users.Count} siswa di kursus ID {cid}");
    }
    catch (Exception ex) { Fail($"Gagal: {ex.Message}"); }
    Pause();
}

async Task MenuSyncKursus()
{
    Head("SYNC KURSUS + SISWA — MASTER → LOKAL");

    if (string.IsNullOrWhiteSpace(config.LocalUrl) || string.IsNullOrWhiteSpace(config.LocalToken))
    {
        Fail("Konfigurasi server lokal belum diisi! Pilih menu [8].");
        Pause(); return;
    }

    // Tampilkan daftar kursus dulu sebagai referensi
    Info("Mengambil daftar kursus dari master...");
    try
    {
        var courses = await masterApi.GetCoursesAsync();
        var real    = courses.Where(c => c.Id != 1).OrderBy(c => c.Id).ToList();
        Console.WriteLine();
        Console.WriteLine($"  {"ID",-6} {"Shortname",-20} {"Nama Kursus"}");
        Console.WriteLine($"  {new string('-', 65)}");
        foreach (var c in real.Take(20))
            Console.WriteLine($"  {c.Id,-6} {c.Shortname,-20} {c.Fullname}");
        if (real.Count > 20) Info($"  ... dan {real.Count - 20} kursus lainnya");
    }
    catch (Exception ex) { Warn($"Tidak bisa tampilkan daftar: {ex.Message}"); }

    Console.WriteLine();
    var cid = TanyaInt("Masukkan Course ID yang akan disync: ");
    if (cid <= 0) { Warn("ID tidak valid."); return; }

    Console.WriteLine();
    Info($"Mulai sync kursus ID {cid} beserta semua siswanya...");
    Console.WriteLine();

    syncSvc.OnProgress += (msg, pct) =>
        Console.Write($"\r  [{pct,3}%] {msg,-60}");

    var result = await syncSvc.SyncCourseFromMasterAsync(cid);
    Console.WriteLine("\n");

    if (result.Success)
    {
        OK(result.Message);
        Console.WriteLine();
        Info("Detail log:");
        foreach (var log in result.Logs) Console.WriteLine($"    {log}");
    }
    else
        Fail(result.Message);

    Pause();
}

async Task MenuSyncSemuaSiswa()
{
    Head("SYNC SEMUA SISWA MASTER → LOKAL");

    if (string.IsNullOrWhiteSpace(config.LocalUrl) || string.IsNullOrWhiteSpace(config.LocalToken))
    {
        Fail("Konfigurasi server lokal belum diisi! Pilih menu [S].");
        Pause(); return;
    }

    Console.WriteLine();
    Info("Proses ini akan sync SEMUA siswa dari master (2000+ user).");
    Info("Gunakan ini sebelum sync cohort agar semua anggota rombel tersedia.");
    Warn("Proses bisa memakan waktu 5-15 menit tergantung koneksi internet.");
    Console.WriteLine();

    syncSvc.OnProgress += (msg, pct) =>
        Console.Write($"\r  [{pct,3}%] {msg,-65}");

    var result = await syncSvc.SyncAllUsersFromMasterAsync();
    Console.WriteLine("\n");

    if (result.Success)
    {
        OK(result.Message);
        Console.WriteLine();
        Info("Detail:");
        foreach (var log in result.Logs) Console.WriteLine($"    {log}");
    }
    else
        Fail(result.Message);

    Pause();
}

async Task MenuSyncSiswa()
{
    Head("SYNC SISWA MASTER → LOKAL");

    if (string.IsNullOrWhiteSpace(config.LocalUrl) || string.IsNullOrWhiteSpace(config.LocalToken))
    {
        Fail("Konfigurasi server lokal belum diisi! Pilih menu [7] Atur Konfigurasi.");
        Pause();
        return;
    }

    var cid = TanyaInt("Masukkan Course ID: ");
    if (cid <= 0) { Warn("ID tidak valid."); return; }

    syncSvc.OnProgress += (msg, pct) =>
    {
        Console.Write($"\r  [{pct,3}%] {msg,-60}");
    };

    Info($"Mulai sync siswa dari kursus ID {cid}...\n");
    var result = await syncSvc.SyncUsersFromMasterAsync(cid);

    Console.WriteLine();
    if (result.Success) OK(result.Message);
    else                Fail(result.Message);

    Pause();
}

async Task MenuSyncCohort()
{
    Head("SYNC COHORT / ROMBEL — MASTER → LOKAL");

    if (string.IsNullOrWhiteSpace(config.LocalUrl) || string.IsNullOrWhiteSpace(config.LocalToken))
    {
        Fail("Konfigurasi server lokal belum diisi! Pilih menu [9].");
        Pause(); return;
    }

    Console.WriteLine();
    Info("Proses ini akan menyinkronkan semua rombel/kelas beserta anggotanya.");
    Info("Pastikan siswa sudah di-sync terlebih dahulu (menu [4] atau [5]).");
    Console.WriteLine();

    syncSvc.OnProgress += (msg, pct) =>
        Console.Write($"\r  [{pct,3}%] {msg,-65}");

    var result = await syncSvc.SyncCohortsFromMasterAsync();
    Console.WriteLine("\n");

    if (result.Success)
    {
        OK(result.Message);
        Console.WriteLine();
        Info("Detail per cohort:");
        foreach (var log in result.Logs)
            Console.WriteLine($"    {log}");
    }
    else
        Fail(result.Message);

    Pause();
}

async Task MenuSyncQuiz()
{
    Head("SYNC QUIZ MASTER → LOKAL");
    Console.WriteLine("  Sync soal ujian dari master ke server lokal.");
    Console.WriteLine("  Pastikan kursus sudah disync terlebih dahulu (menu [4]).\n");

    var cid = TanyaInt("Masukkan Course ID (master): ");
    if (cid <= 0) { Warn("ID tidak valid."); return; }

    // Tampilkan daftar quiz di kursus tersebut
    try
    {
        Info($"Mengambil daftar quiz di kursus ID {cid}...");
        List<(int id, string name)> quizList = new();
        try
        {
            var r = await masterApi.GetQuizzesInCourseAsync(cid);
            quizList = r.Select(q => (q.Id, q.Fullname)).ToList();
        }
        catch
        {
            var endpoint = $"{config.MasterUrl}/webservice/rest/server.php";
            using var http = new HttpClient();
            var body = new FormUrlEncodedContent(new Dictionary<string, string>
            {
                ["wstoken"]            = config.MasterToken,
                ["wsfunction"]         = "mod_quiz_get_quizzes_by_courses",
                ["moodlewsrestformat"] = "json",
                ["courseids[0]"]       = cid.ToString()
            });
            var resp = await http.PostAsync(endpoint, body);
            var json = await resp.Content.ReadAsStringAsync();
            var parsed = JToken.Parse(json);
            if (parsed?["quizzes"] is JArray arr)
                quizList = arr.Select(q => (q["id"]?.Value<int>() ?? 0, q["name"]?.ToString() ?? "")).ToList();
        }

        if (quizList.Count == 0) { Warn("Tidak ada quiz di kursus ini."); Pause(); return; }

        Console.WriteLine();
        Console.WriteLine($"  {"ID",-6} {"Nama Quiz",-55}");
        Console.WriteLine($"  {new string('-', 63)}");
        foreach (var (id, name) in quizList)
            Console.WriteLine($"  {id,-6} {name,-55}");
        Console.WriteLine();
    }
    catch (Exception ex) { Warn($"Tidak bisa ambil daftar quiz: {ex.Message}"); }

    var qid = TanyaInt("Masukkan Quiz ID yang akan disync: ");
    if (qid <= 0) { Warn("ID tidak valid."); return; }

    try
    {
        var syncSvc = new SyncService(config, logger);
        syncSvc.OnProgress += (msg, pct) =>
        {
            Console.CursorLeft = 0;
            Console.Write($"  [{pct,3}%] {msg,-55}");
        };

        var result = await syncSvc.SyncQuizFromMasterAsync(cid, qid);
        Console.WriteLine();

        if (result.Success)
        {
            OK(result.Message);
            if (result.Logs.Count > 0)
            {
                Console.WriteLine("\n  → Detail:");
                foreach (var log in result.Logs) Console.WriteLine($"    {log}");
            }
        }
        else Fail(result.Message);
    }
    catch (Exception ex) { Fail($"Gagal: {ex.Message}"); }
    Pause();
}

async Task MenuDaftarQuiz()
{
    Head("DAFTAR QUIZ DI KURSUS");
    var cid = TanyaInt("Masukkan Course ID: ");
    if (cid <= 0) { Warn("ID tidak valid."); return; }

    try
    {
        Info($"Mengambil quiz di kursus ID {cid}...");

        // Coba plugin E-UJIAN dulu
        List<(int id, string name)> quizList = new();
        try
        {
            var r = await masterApi.GetQuizzesInCourseAsync(cid);
            quizList = r.Select(q => (q.Id, q.Fullname)).ToList();
            Info("(via plugin E-UJIAN)");
        }
        catch
        {
            // Fallback: mod_quiz standard API
            var endpoint = $"{config.MasterUrl}/webservice/rest/server.php";
            using var http = new HttpClient();
            var body = new FormUrlEncodedContent(new Dictionary<string, string>
            {
                ["wstoken"]            = config.MasterToken,
                ["wsfunction"]         = "mod_quiz_get_quizzes_by_courses",
                ["moodlewsrestformat"] = "json",
                ["courseids[0]"]       = cid.ToString()
            });
            var resp = await http.PostAsync(endpoint, body);
            var json = await resp.Content.ReadAsStringAsync();
            var parsed = JToken.Parse(json);
            if (parsed?["quizzes"] is JArray arr)
                quizList = arr.Select(q => (q["id"]?.Value<int>() ?? 0, q["name"]?.ToString() ?? "")).ToList();
            Info("(via API standar)");
        }

        if (quizList.Count == 0) { Warn("Tidak ada quiz di kursus ini."); Pause(); return; }

        Console.WriteLine();
        Console.WriteLine($"  {"ID",-6} {"Nama Quiz",-50}");
        Console.WriteLine($"  {new string('-', 58)}");
        foreach (var (id, name) in quizList)
            Console.WriteLine($"  {id,-6} {name,-50}");

        OK($"\nTotal: {quizList.Count} quiz");
    }
    catch (Exception ex) { Fail($"Gagal: {ex.Message}"); }
    Pause();
}

async Task MenuUploadHasil()
{
    Head("UPLOAD HASIL UJIAN (LOKAL → MASTER)");
    Console.WriteLine("  Kirim nilai hasil ujian dari server lokal ke gradebook master.");
    Console.WriteLine("  Hanya quiz yang disync via menu [Q] yang bisa diupload.");
    Console.WriteLine("  Gunakan menu [8] untuk melihat daftar quiz lokal terlebih dahulu.\n");

    var qid = TanyaInt("Masukkan Quiz ID lokal: ");
    if (qid <= 0) { Warn("ID tidak valid."); return; }

    try
    {
        var uploadSvc = new SyncService(config, logger);
        uploadSvc.OnProgress += (msg, pct) =>
        {
            Console.CursorLeft = 0;
            Console.Write($"  [{pct,3}%] {msg,-58}");
        };

        var result = await uploadSvc.UploadResultsToMasterAsync(qid);
        Console.WriteLine();

        if (result.Success)
        {
            OK(result.Message);
            if (result.Logs.Count > 0)
            {
                Console.WriteLine("\n  → Detail:");
                foreach (var log in result.Logs) Console.WriteLine($"    {log}");
            }
        }
        else Fail(result.Message);
    }
    catch (Exception ex) { Fail($"Gagal: {ex.Message}"); }
    Pause();
}

async Task MenuExportHasil()
{
    Head("EXPORT HASIL UJIAN (LOKAL → FILE JSON)");

    var qid = TanyaInt("Masukkan Quiz ID: ");
    if (qid <= 0) { Warn("ID tidak valid."); return; }

    var outDir = Path.Combine(AppContext.BaseDirectory, "export-hasil");
    Info($"Menyimpan ke: {outDir}");

    var result = await syncSvc.ExportQuizResultsAsync(qid, outDir);

    if (result.Success) OK(result.Message);
    else                Fail(result.Message);

    Pause();
}

void MenuAturKonfigurasi()
{
    Head("ATUR KONFIGURASI");

    Console.WriteLine($"  [Enter = skip, pertahankan nilai sekarang]\n");

    config.MasterUrl   = TanyaString($"URL Server Master   [{config.MasterUrl}]: ", config.MasterUrl);
    config.MasterToken = TanyaString($"Token Master        [{Mask(config.MasterToken)}]: ", config.MasterToken);
    config.LocalUrl    = TanyaString($"URL Server Lokal    [{config.LocalUrl}]: ", config.LocalUrl);
    config.LocalToken  = TanyaString($"Token Lokal         [{Mask(config.LocalToken)}]: ", config.LocalToken);
    config.RoomId      = TanyaString($"ID Ruangan          [{config.RoomId}]: ", config.RoomId);
    config.RoomName    = TanyaString($"Nama Ruangan        [{config.RoomName}]: ", config.RoomName);

    // Simpan
    var json = Newtonsoft.Json.JsonConvert.SerializeObject(config, Newtonsoft.Json.Formatting.Indented);
    File.WriteAllText(configPath, json);
    masterApi.Configure(config.MasterUrl, config.MasterToken);

    OK($"Konfigurasi disimpan ke: {configPath}");
    Pause();
}

void MenuTampilKonfigurasi()
{
    Head("KONFIGURASI SAAT INI");
    Console.WriteLine($"  URL Master    : {config.MasterUrl}");
    Console.WriteLine($"  Token Master  : {Mask(config.MasterToken)}");
    Console.WriteLine($"  URL Lokal     : {config.LocalUrl}");
    Console.WriteLine($"  Token Lokal   : {Mask(config.LocalToken)}");
    Console.WriteLine($"  ID Ruangan    : {config.RoomId}");
    Console.WriteLine($"  Nama Ruangan  : {config.RoomName}");
    Console.WriteLine($"  File Config   : {configPath}");
    Pause();
}

// ══════════════════════════════════════════════════════════════
//  HELPERS
// ══════════════════════════════════════════════════════════════

AppConfig LoadOrCreateConfig(string path)
{
    if (File.Exists(path))
    {
        try
        {
            var json = File.ReadAllText(path);
            return Newtonsoft.Json.JsonConvert.DeserializeObject<AppConfig>(json) ?? new AppConfig();
        }
        catch { }
    }
    return new AppConfig();
}

void PrintHeader()
{
    Console.ForegroundColor = ConsoleColor.Cyan;
    Console.WriteLine(@"
  ╔══════════════════════════════════════════════╗
  ║         E-UJIAN  —  SMKN 9 Garut            ║
  ║      Tools Sinkronisasi Moodle CLI           ║
  ║        Pengembang: Suhendar Aryadi           ║
  ╚══════════════════════════════════════════════╝");
    Console.ResetColor();
}

void PrintMenu()
{
    Console.WriteLine();
    Console.ForegroundColor = ConsoleColor.Yellow;
    Console.WriteLine("  ─── MENU UTAMA ───────────────────────────────");
    Console.ResetColor();
    Console.WriteLine("  [1] Tes Koneksi Server (Master & Lokal)");
    Console.WriteLine("  [2] Lihat Daftar Kursus (Master)");
    Console.WriteLine("  [3] Lihat Daftar Siswa di Kursus");
    Console.WriteLine("  [4] Sync Kursus + Siswa per Kursus      ⭐");
    Console.WriteLine("  [5] Sync SEMUA Siswa Master → Lokal     ⭐ Baru!");
    Console.WriteLine("  [6] Sync Siswa saja (per kursus)");
    Console.WriteLine("  [7] Sync Cohort/Rombel Master → Lokal   ⭐");
    Console.WriteLine("  [8] Lihat Daftar Quiz di Kursus");
    Console.WriteLine("  [9] Export Hasil Ujian → File JSON");
    Console.WriteLine("  [U] Upload Hasil Ujian → Master          ⭐ Baru!");
    Console.WriteLine("  [Q] Sync Quiz Master → Lokal             ⭐ Baru!");
    Console.WriteLine("  [S] Atur Konfigurasi");
    Console.WriteLine("  [C] Tampilkan Konfigurasi");
    Console.WriteLine("  [0] Keluar");
    Console.ForegroundColor = ConsoleColor.Yellow;
    Console.WriteLine("  ─────────────────────────────────────────────");
    Console.ResetColor();
    Console.Write("  Pilihan: ");
}

void Head(string msg)
{
    Console.ForegroundColor = ConsoleColor.Yellow;
    Console.WriteLine($"\n  ── {msg} ──");
    Console.ResetColor();
}
void OK(string msg)   { Console.ForegroundColor = ConsoleColor.Green;  Console.WriteLine($"  ✓ {msg}"); Console.ResetColor(); }
void Fail(string msg) { Console.ForegroundColor = ConsoleColor.Red;    Console.WriteLine($"  ✗ {msg}"); Console.ResetColor(); }
void Info(string msg) { Console.ForegroundColor = ConsoleColor.Cyan;   Console.WriteLine($"  → {msg}"); Console.ResetColor(); }
void Warn(string msg) { Console.ForegroundColor = ConsoleColor.DarkYellow; Console.WriteLine($"  ! {msg}"); Console.ResetColor(); }
void Write(string msg){ Console.Write($"  {msg}"); }
void Pause()          { Console.WriteLine(); Console.Write("  [Enter untuk lanjut] "); Console.ReadLine(); }

int TanyaInt(string prompt)
{
    Console.Write($"  {prompt}");
    return int.TryParse(Console.ReadLine()?.Trim(), out int v) ? v : 0;
}

string TanyaString(string prompt, string current)
{
    Console.Write($"  {prompt}");
    var input = Console.ReadLine()?.Trim();
    return string.IsNullOrEmpty(input) ? current : input;
}

string Mask(string s) => s.Length <= 6 ? "***" : s[..4] + new string('*', s.Length - 4);

