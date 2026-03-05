using Newtonsoft.Json;

namespace MoodleSyncApp.Core.Models;

public class MoodleUser
{
    public int Id { get; set; }
    public string Username { get; set; } = "";
    public string Firstname { get; set; } = "";
    public string Lastname { get; set; } = "";
    public string Email { get; set; } = "";
    public string Idnumber { get; set; } = ""; // NIS/NISN
    public string FullName => $"{Firstname} {Lastname}";
}

public class MoodleCourse
{
    public int Id { get; set; }
    public string Shortname { get; set; } = "";
    public string Fullname { get; set; } = "";
    public string Summary { get; set; } = "";
    public long TimeModified { get; set; }
}

public class MoodleQuiz
{
    public int Id { get; set; }
    public int CourseId { get; set; }
    public string Name { get; set; } = "";
    public long TimeOpen { get; set; }
    public long TimeClose { get; set; }
    public int TimeLimit { get; set; }
}

public class QuizAttempt
{
    public int Id { get; set; }
    public int QuizId { get; set; }
    public int UserId { get; set; }
    public string Username { get; set; } = "";
    public string State { get; set; } = ""; // inprogress, finished, abandoned
    public long TimeStart { get; set; }
    public long TimeFinish { get; set; }
    public double SumGrades { get; set; }
}

public class SyncJob
{
    public string Id { get; set; } = Guid.NewGuid().ToString();
    public string RoomId { get; set; } = "";
    public SyncJobType Type { get; set; }
    public SyncStatus Status { get; set; } = SyncStatus.Pending;
    public DateTime CreatedAt { get; set; } = DateTime.Now;
    public DateTime? CompletedAt { get; set; }
    public string? ErrorMessage { get; set; }
    public string Notes { get; set; } = "";
}

public enum SyncJobType
{
    DownloadCourse,     // Master → Kelas
    SyncUsers,          // Master → Kelas
    UploadResults,      // Kelas → Master
    BackupLocal,        // Backup lokal
    RestoreLocal        // Restore lokal
}

public enum SyncStatus
{
    Pending,
    Running,
    Completed,
    Failed
}

public class SyncResult
{
    public bool Success { get; set; }
    public string Message { get; set; } = "";
    public List<string> Logs { get; set; } = new();
    public int RecordsProcessed { get; set; }
    public DateTime Timestamp { get; set; } = DateTime.Now;
}

public class MoodleCohort
{
    [JsonProperty("id")]
    public int Id { get; set; }

    [JsonProperty("name")]
    public string Name { get; set; } = "";

    [JsonProperty("idnumber")]
    public string Idnumber { get; set; } = "";

    [JsonProperty("description")]
    public string? Description { get; set; }

    [JsonProperty("memberscount")]
    public int MembersCount { get; set; }
}

/// <summary>Hasil export quiz dari master (response API local_eujian_export_quiz)</summary>
public class QuizExportResponse
{
    [JsonProperty("quizid")]
    public int QuizId { get; set; }

    [JsonProperty("questioncount")]
    public int QuestionCount { get; set; }

    [JsonProperty("exportjson")]
    public string ExportJson { get; set; } = "";
}

/// <summary>Hasil import quiz ke lokal (response API local_eujian_import_quiz)</summary>
public class QuizImportResponse
{
    [JsonProperty("quizid")]
    public int QuizId { get; set; }

    [JsonProperty("questioncount")]
    public int QuestionCount { get; set; }

    [JsonProperty("status")]
    public string Status { get; set; } = ""; // "created" | "skipped"

    [JsonProperty("message")]
    public string Message { get; set; } = "";
}

/// <summary>Info quiz lokal termasuk link ke master (local_eujian_get_quiz_masterid)</summary>
public class QuizMasterIdResponse
{
    [JsonProperty("localquizid")]
    public int LocalQuizId { get; set; }

    [JsonProperty("masterquizid")]
    public int MasterQuizId { get; set; }

    [JsonProperty("quizname")]
    public string QuizName { get; set; } = "";

    [JsonProperty("sumgrades")]
    public double SumGrades { get; set; }

    [JsonProperty("grade")]
    public double Grade { get; set; }

    [JsonProperty("found")]
    public bool Found { get; set; }
}

/// <summary>Satu baris hasil ujian untuk dikirim ke master</summary>
public class QuizResultItem
{
    public string Username   { get; set; } = "";
    public double RawGrade   { get; set; }
    public long   TimeStart  { get; set; }
    public long   TimeFinish { get; set; }
}

/// <summary>Response dari master setelah receive_results</summary>
public class ReceiveResultsResponse
{
    [JsonProperty("quizid")]
    public int QuizId { get; set; }

    [JsonProperty("quizname")]
    public string QuizName { get; set; } = "";

    [JsonProperty("total")]
    public int Total { get; set; }

    [JsonProperty("updated")]
    public int Updated { get; set; }

    [JsonProperty("skipped")]
    public int Skipped { get; set; }

    [JsonProperty("errors")]
    public List<string> Errors { get; set; } = new();
}
