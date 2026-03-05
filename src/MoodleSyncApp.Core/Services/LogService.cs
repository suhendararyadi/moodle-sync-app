using LiteDB;
using MoodleSyncApp.Core.Models;

namespace MoodleSyncApp.Core.Services;

/// <summary>
/// Service untuk logging dan persistensi state via LiteDB
/// </summary>
public class LogService : IDisposable
{
    private readonly LiteDatabase _db;
    private readonly ILiteCollection<SyncLog> _logs;

    public LogService(string dbPath = "moodle-sync.db")
    {
        _db = new LiteDatabase(dbPath);
        _logs = _db.GetCollection<SyncLog>("logs");
        _logs.EnsureIndex(x => x.Timestamp);
    }

    public void Log(string message, LogLevel level = LogLevel.Info, string? jobId = null)
    {
        _logs.Insert(new SyncLog
        {
            Message = message,
            Level = level,
            Timestamp = DateTime.Now,
            JobId = jobId
        });

        // Output ke console untuk debug
        var prefix = level switch
        {
            LogLevel.Error => "[ERROR]",
            LogLevel.Warning => "[WARN] ",
            LogLevel.Success => "[OK]   ",
            _ => "[INFO] "
        };
        Console.WriteLine($"{DateTime.Now:HH:mm:ss} {prefix} {message}");
    }

    public List<SyncLog> GetRecentLogs(int count = 100)
    {
        return _logs.Query()
            .OrderByDescending(x => x.Timestamp)
            .Limit(count)
            .ToList();
    }

    public List<SyncLog> GetLogsByJob(string jobId)
    {
        return _logs.Find(x => x.JobId == jobId)
            .OrderBy(x => x.Timestamp)
            .ToList();
    }

    public void Dispose() => _db.Dispose();
}

public class SyncLog
{
    public int Id { get; set; }
    public string Message { get; set; } = "";
    public LogLevel Level { get; set; }
    public DateTime Timestamp { get; set; }
    public string? JobId { get; set; }
}

public enum LogLevel
{
    Info,
    Warning,
    Error,
    Success
}
