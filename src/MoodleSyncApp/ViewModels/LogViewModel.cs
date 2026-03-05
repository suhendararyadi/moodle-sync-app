using System.Collections.ObjectModel;
using System.Text;
using CommunityToolkit.Mvvm.ComponentModel;
using CommunityToolkit.Mvvm.Input;
using MoodleSyncApp.Core.Services;

namespace MoodleSyncApp.ViewModels;

public partial class LogViewModel : ObservableObject
{
    private readonly LogService _logger;

    [ObservableProperty] private ObservableCollection<LogRow> _logs = new();
    [ObservableProperty] private string _selectedFilter = "Semua";
    [ObservableProperty] private string _statusMessage = "";

    public LogViewModel(LogService logger)
    {
        _logger = logger;
        LoadLogs();
    }

    [RelayCommand]
    private void Refresh() => LoadLogs();

    [RelayCommand]
    private void Clear()
    {
        // TODO: implement clear in LogService
        Logs.Clear();
        StatusMessage = "Log dihapus.";
    }

    [RelayCommand]
    private void ExportCsv()
    {
        try
        {
            var path = Path.Combine(
                Environment.GetFolderPath(Environment.SpecialFolder.Desktop),
                $"moodle-sync-log-{DateTime.Now:yyyyMMdd_HHmm}.csv");

            var sb = new StringBuilder();
            sb.AppendLine("Waktu,Level,Pesan");
            foreach (var log in Logs)
                sb.AppendLine($"{log.TimeText},{log.LevelText},\"{log.Message}\"");

            File.WriteAllText(path, sb.ToString());
            StatusMessage = $"✅ Log diekspor ke Desktop: {Path.GetFileName(path)}";
        }
        catch (Exception ex)
        {
            StatusMessage = $"❌ Gagal export: {ex.Message}";
        }
    }

    private void LoadLogs()
    {
        var rawLogs = _logger.GetRecentLogs(200);
        Logs.Clear();

        foreach (var log in rawLogs)
        {
            var levelText = log.Level.ToString();
            if (SelectedFilter != "Semua" &&
                !string.Equals(levelText, SelectedFilter, StringComparison.OrdinalIgnoreCase))
                continue;

            Logs.Add(new LogRow
            {
                TimeText  = log.Timestamp.ToString("HH:mm:ss dd/MM"),
                LevelText = log.Level switch
                {
                    LogLevel.Error   => "❌ Error",
                    LogLevel.Warning => "⚠️ Warn",
                    LogLevel.Success => "✅ OK",
                    _                => "ℹ️ Info"
                },
                Message = log.Message
            });
        }

        StatusMessage = $"Menampilkan {Logs.Count} entri log.";
    }
}

public class LogRow
{
    public string TimeText { get; set; } = "";
    public string LevelText { get; set; } = "";
    public string Message { get; set; } = "";
}
