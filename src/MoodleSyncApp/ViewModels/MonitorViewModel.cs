using System.Collections.ObjectModel;
using System.Timers;
using CommunityToolkit.Mvvm.ComponentModel;
using CommunityToolkit.Mvvm.Input;
using MoodleSyncApp.Core.Config;
using MoodleSyncApp.Core.Services;

namespace MoodleSyncApp.ViewModels;

public partial class MonitorViewModel : ObservableObject
{
    private readonly MoodleApiService _localApi;
    private System.Timers.Timer? _autoTimer;

    [ObservableProperty] private string _quizId = "";
    [ObservableProperty] private ObservableCollection<StudentAttemptRow> _students = new();
    [ObservableProperty] private int _totalStudents;
    [ObservableProperty] private int _inProgressCount;
    [ObservableProperty] private int _finishedCount;
    [ObservableProperty] private int _notStartedCount;
    [ObservableProperty] private string _lastRefresh = "Belum direfresh";
    [ObservableProperty] private string _statusMessage = "Masukkan Quiz ID lalu klik Refresh.";
    [ObservableProperty] private bool _autoRefreshEnabled = false;
    [ObservableProperty] private string _autoRefreshLabel = "⏱ Auto-Refresh: OFF";

    public MonitorViewModel(AppConfig config)
    {
        _localApi = new MoodleApiService();
        _localApi.Configure(config.LocalUrl, config.LocalToken);
    }

    [RelayCommand]
    private async Task Refresh()
    {
        if (string.IsNullOrWhiteSpace(QuizId)) return;
        if (!int.TryParse(QuizId, out int quizIdInt)) return;

        StatusMessage = "Memuat data...";
        try
        {
            var attempts = await _localApi.GetQuizAttemptsAsync(quizIdInt);

            Students.Clear();
            int no = 1;
            foreach (var a in attempts.OrderBy(x => x.State))
            {
                Students.Add(new StudentAttemptRow
                {
                    No         = no++,
                    FullName   = $"User {a.UserId}",
                    Username   = $"user{a.UserId}",
                    StatusText = a.State switch
                    {
                        "inprogress" => "🟡 Mengerjakan",
                        "finished"   => "✅ Selesai",
                        "abandoned"  => "⚫ Ditinggal",
                        _            => a.State
                    },
                    StartTime  = a.TimeStart > 0
                        ? DateTimeOffset.FromUnixTimeSeconds(a.TimeStart).LocalDateTime.ToString("HH:mm:ss")
                        : "-",
                    FinishTime = a.TimeFinish > 0
                        ? DateTimeOffset.FromUnixTimeSeconds(a.TimeFinish).LocalDateTime.ToString("HH:mm:ss")
                        : "-",
                    Duration   = a.TimeFinish > 0 && a.TimeStart > 0
                        ? TimeSpan.FromSeconds(a.TimeFinish - a.TimeStart).ToString(@"mm\:ss")
                        : "-",
                    Grade      = a.SumGrades > 0 ? a.SumGrades.ToString("F1") : "-"
                });
            }

            TotalStudents    = Students.Count;
            InProgressCount  = Students.Count(s => s.StatusText.Contains("Mengerjakan"));
            FinishedCount    = Students.Count(s => s.StatusText.Contains("Selesai"));
            NotStartedCount  = TotalStudents - InProgressCount - FinishedCount;
            LastRefresh      = $"Update: {DateTime.Now:HH:mm:ss}";
            StatusMessage    = $"Menampilkan {TotalStudents} siswa.";
        }
        catch (Exception ex)
        {
            StatusMessage = $"Gagal: {ex.Message}";
        }
    }

    [RelayCommand]
    private void ToggleAutoRefresh()
    {
        AutoRefreshEnabled = !AutoRefreshEnabled;
        if (AutoRefreshEnabled)
        {
            AutoRefreshLabel = "⏱ Auto-Refresh: ON (30s)";
            _autoTimer = new System.Timers.Timer(30000);
            _autoTimer.Elapsed += async (_, _) => await Refresh();
            _autoTimer.Start();
        }
        else
        {
            AutoRefreshLabel = "⏱ Auto-Refresh: OFF";
            _autoTimer?.Stop();
            _autoTimer?.Dispose();
        }
    }
}

public class StudentAttemptRow
{
    public int No { get; set; }
    public string FullName { get; set; } = "";
    public string Username { get; set; } = "";
    public string StatusText { get; set; } = "";
    public string StartTime { get; set; } = "";
    public string FinishTime { get; set; } = "";
    public string Duration { get; set; } = "";
    public string Grade { get; set; } = "";
}
