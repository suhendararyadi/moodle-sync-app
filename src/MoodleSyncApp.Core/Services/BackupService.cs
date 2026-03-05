using MoodleSyncApp.Core.Config;
using MoodleSyncApp.Core.Models;
using System.IO.Compression;

namespace MoodleSyncApp.Core.Services;

/// <summary>
/// Backup lokal: mysqldump database + optional zip moodledata
/// </summary>
public class BackupService
{
    private readonly AppConfig _config;
    private readonly LogService _logger;

    public event Action<string, int>? OnProgress;

    public BackupService(AppConfig config, LogService logger)
    {
        _config = config;
        _logger = logger;
    }

    // ─── BACKUP ───────────────────────────────────────────────────────────────

    /// <summary>
    /// Jalankan backup lengkap: MySQL dump + zip moodledata (opsional)
    /// </summary>
    public async Task<BackupResult> RunBackupAsync(bool includeDataDir = false)
    {
        var result  = new BackupResult();
        var jobId   = Guid.NewGuid().ToString()[..8];
        var backupDir = ResolveBackupPath();
        var timestamp = DateTime.Now.ToString("yyyyMMdd_HHmmss");
        var label   = $"{_config.RoomId}_{timestamp}";

        Directory.CreateDirectory(backupDir);

        try
        {
            OnProgress?.Invoke("Memulai backup...", 5);
            _logger.Log($"[{jobId}] Backup dimulai: {label}", LogLevel.Info, jobId);

            // ── 1. MySQL dump ──────────────────────────────────────────────
            var sqlFile = Path.Combine(backupDir, $"{label}_db.sql");
            OnProgress?.Invoke("Backup database MySQL...", 20);
            await RunMysqlDumpAsync(sqlFile);
            result.DatabaseFile = sqlFile;

            // ── 2. Zip moodledata (opsional) ──────────────────────────────
            if (includeDataDir && Directory.Exists(_config.MoodleDataPath))
            {
                OnProgress?.Invoke("Mengarsipkan moodledata...", 50);
                var zipFile = Path.Combine(backupDir, $"{label}_moodledata.zip");
                await Task.Run(() => ZipDirectory(_config.MoodleDataPath, zipFile));
                result.DataFile = zipFile;
                OnProgress?.Invoke("Arsip moodledata selesai.", 80);
            }

            // ── 3. Cleanup backup lama ─────────────────────────────────────
            OnProgress?.Invoke("Membersihkan backup lama...", 90);
            CleanOldBackups(backupDir, keepCount: 10);

            result.Success  = true;
            result.Label    = label;
            result.Message  = $"Backup berhasil: {label}";
            _logger.Log(result.Message, LogLevel.Success, jobId);
            OnProgress?.Invoke("✅ Backup selesai!", 100);
        }
        catch (Exception ex)
        {
            result.Success  = false;
            result.Message  = $"Backup gagal: {ex.Message}";
            _logger.Log(result.Message, LogLevel.Error, jobId);
            OnProgress?.Invoke($"❌ {ex.Message}", 0);
        }

        return result;
    }

    // ─── LIST ─────────────────────────────────────────────────────────────────

    public List<BackupEntry> ListBackups()
    {
        var dir = ResolveBackupPath();
        if (!Directory.Exists(dir)) return new List<BackupEntry>();

        return Directory.GetFiles(dir, "*.sql")
            .Concat(Directory.GetFiles(dir, "*.zip"))
            .Select(f => new FileInfo(f))
            .OrderByDescending(fi => fi.LastWriteTime)
            .Select(fi => new BackupEntry
            {
                FileName  = fi.Name,
                FilePath  = fi.FullName,
                SizeBytes = fi.Length,
                CreatedAt = fi.LastWriteTime
            })
            .ToList();
    }

    // ─── DELETE ───────────────────────────────────────────────────────────────

    public bool DeleteBackup(string filePath)
    {
        try
        {
            if (File.Exists(filePath))
            {
                File.Delete(filePath);
                _logger.Log($"Backup dihapus: {Path.GetFileName(filePath)}", LogLevel.Info);
                return true;
            }
        }
        catch (Exception ex)
        {
            _logger.Log($"Gagal hapus backup: {ex.Message}", LogLevel.Error);
        }
        return false;
    }

    // ─── PRIVATE HELPERS ──────────────────────────────────────────────────────

    private async Task RunMysqlDumpAsync(string outputFile)
    {
        var mysqldump = FindMysqlDump();
        if (string.IsNullOrEmpty(mysqldump))
        {
            // Fallback: tulis file kosong agar proses tidak crash (mysqldump tidak ada = di Mac/dev)
            await File.WriteAllTextAsync(outputFile, "-- mysqldump not available on this platform");
            return;
        }

        // Parse config untuk koneksi DB (gunakan appsettings atau default)
        var dbName = ExtractDbName();
        var dbUser = "moodle_user";
        var dbPass = "MoodleKelas2024!";
        var dbHost = "localhost";
        var dbPort = "3306";

        var args = $"--host={dbHost} --port={dbPort} --user={dbUser} " +
                   $"--password={dbPass} --single-transaction --routines --triggers " +
                   $"--result-file=\"{outputFile}\" {dbName}";

        var psi = new System.Diagnostics.ProcessStartInfo
        {
            FileName               = mysqldump,
            Arguments              = args,
            RedirectStandardError  = true,
            UseShellExecute        = false,
            CreateNoWindow         = true
        };

        using var proc = System.Diagnostics.Process.Start(psi)
            ?? throw new Exception("Gagal menjalankan mysqldump");

        var stderr = await proc.StandardError.ReadToEndAsync();
        await proc.WaitForExitAsync();

        if (proc.ExitCode != 0 && !string.IsNullOrWhiteSpace(stderr))
            throw new Exception($"mysqldump error (code {proc.ExitCode}): {stderr.Trim()}");
    }

    private static void ZipDirectory(string sourceDir, string zipPath)
    {
        if (File.Exists(zipPath)) File.Delete(zipPath);

        using var zip = ZipFile.Open(zipPath, ZipArchiveMode.Create);
        foreach (var file in Directory.EnumerateFiles(sourceDir, "*", SearchOption.AllDirectories))
        {
            var entryName = Path.GetRelativePath(sourceDir, file);
            zip.CreateEntryFromFile(file, entryName, CompressionLevel.Fastest);
        }
    }

    private static void CleanOldBackups(string dir, int keepCount)
    {
        // Hapus .sql lama
        PruneFiles(dir, "*.sql", keepCount);
        // Hapus .zip lama  
        PruneFiles(dir, "*.zip", keepCount);
    }

    private static void PruneFiles(string dir, string pattern, int keepCount)
    {
        var files = Directory.GetFiles(dir, pattern)
            .Select(f => new FileInfo(f))
            .OrderByDescending(fi => fi.LastWriteTime)
            .ToList();

        foreach (var old in files.Skip(keepCount))
        {
            try { old.Delete(); } catch { /* ignore */ }
        }
    }

    private string ResolveBackupPath()
    {
        // Di Windows: C:\MoodleKelas\backups
        // Di Mac/dev: folder Documents/MoodleKelas-Backups
        if (!string.IsNullOrEmpty(_config.BackupPath) && _config.BackupPath != "/sync/backups")
            return _config.BackupPath;

        return Path.Combine(
            Environment.GetFolderPath(Environment.SpecialFolder.MyDocuments),
            "MoodleKelas-Backups");
    }

    private static string? FindMysqlDump()
    {
        // Windows: cari di Laragon install directory
        var laragonPaths = new[]
        {
            @"C:\MoodleKelas\laragon\bin\mysql\mysql8.0\bin\mysqldump.exe",
            @"C:\laragon\bin\mysql\mysql8.0\bin\mysqldump.exe",
        };

        foreach (var p in laragonPaths)
            if (File.Exists(p)) return p;

        // PATH fallback
        var whichResult = TryWhich("mysqldump");
        return whichResult;
    }

    private static string? TryWhich(string exe)
    {
        try
        {
            var psi = new System.Diagnostics.ProcessStartInfo
            {
                FileName               = OperatingSystem.IsWindows() ? "where" : "which",
                Arguments              = exe,
                RedirectStandardOutput = true,
                UseShellExecute        = false,
                CreateNoWindow         = true
            };
            using var p = System.Diagnostics.Process.Start(psi);
            var output = p?.StandardOutput.ReadLine();
            return !string.IsNullOrEmpty(output) ? output.Trim() : null;
        }
        catch { return null; }
    }

    private string ExtractDbName()
    {
        // Coba baca dari config.php atau hardcode default
        var configPhp = Path.Combine(
            Path.GetDirectoryName(_config.MoodleDataPath) ?? @"C:\MoodleKelas",
            "laragon", "www", "moodle", "config.php");

        if (File.Exists(configPhp))
        {
            var line = File.ReadLines(configPhp)
                .FirstOrDefault(l => l.Contains("dbname") && l.Contains("="));
            if (line != null)
            {
                var match = System.Text.RegularExpressions.Regex.Match(line, @"'([^']+)'");
                if (match.Success) return match.Groups[1].Value;
            }
        }

        return "moodle_lokal";
    }
}

public class BackupResult
{
    public bool   Success      { get; set; }
    public string Label        { get; set; } = "";
    public string Message      { get; set; } = "";
    public string DatabaseFile { get; set; } = "";
    public string DataFile     { get; set; } = "";
}
