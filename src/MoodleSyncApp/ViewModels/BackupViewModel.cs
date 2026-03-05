using System.Collections.ObjectModel;
using System.Timers;
using Avalonia.Media;
using CommunityToolkit.Mvvm.ComponentModel;
using CommunityToolkit.Mvvm.Input;
using MoodleSyncApp.Core.Config;
using MoodleSyncApp.Core.Models;
using MoodleSyncApp.Core.Services;

namespace MoodleSyncApp.ViewModels;

public partial class BackupViewModel : ObservableObject
{
    private readonly AppConfig _config;
    private readonly BackupService _backupService;
    private System.Timers.Timer? _autoTimer;

    // ── Progress & status ────────────────────────────────────────────────────
    [ObservableProperty] private string _progressMessage  = "Siap untuk backup.";
    [ObservableProperty] private int    _progressValue    = 0;
    [ObservableProperty] private bool   _isBusy           = false;
    [ObservableProperty] private IBrush _statusColor      = Brushes.Gray;

    // ── Auto-backup ─────────────────────────────────────────────────────────
    [ObservableProperty] private bool   _autoBackupEnabled  = false;
    [ObservableProperty] private int    _autoIntervalHours  = 1;
    [ObservableProperty] private string _autoStatusText     = "Auto-backup: Nonaktif";
    [ObservableProperty] private string _nextBackupText     = "";

    // ── Opsi ─────────────────────────────────────────────────────────────────
    [ObservableProperty] private bool _includeDataDir = false;

    // ── Daftar backup ────────────────────────────────────────────────────────
    public ObservableCollection<BackupEntry> BackupList { get; } = new();
    [ObservableProperty] private BackupEntry? _selectedBackup;

    // ── Config path (tampilkan ke user) ──────────────────────────────────────
    [ObservableProperty] private string _backupPath = "";

    public BackupViewModel(AppConfig config, BackupService backupService)
    {
        _config        = config;
        _backupService = backupService;

        _backupService.OnProgress += (msg, pct) =>
            Avalonia.Threading.Dispatcher.UIThread.Post(() =>
            {
                ProgressMessage = msg;
                ProgressValue   = pct;
                StatusColor     = pct == 100 ? Brushes.Green
                                : pct == 0   ? Brushes.Red
                                : Brushes.Orange;
            });

        BackupPath = ResolveBackupPath(config);
        LoadBackupList();
    }

    // ─── COMMANDS ─────────────────────────────────────────────────────────────

    [RelayCommand]
    private async Task BackupNow()
    {
        IsBusy          = true;
        ProgressValue   = 0;
        StatusColor     = Brushes.Orange;
        ProgressMessage = "Memulai backup...";

        var result = await _backupService.RunBackupAsync(IncludeDataDir);

        StatusColor     = result.Success ? Brushes.Green : Brushes.Red;
        ProgressMessage = result.Message;

        if (result.Success)
            LoadBackupList();

        IsBusy = false;
    }

    [RelayCommand]
    private void ToggleAutoBackup()
    {
        if (AutoBackupEnabled)
            StartAutoTimer();
        else
            StopAutoTimer();
    }

    [RelayCommand]
    private void OpenBackupFolder()
    {
        try
        {
            var path = BackupPath;
            if (!Directory.Exists(path)) Directory.CreateDirectory(path);

            if (OperatingSystem.IsWindows())
                System.Diagnostics.Process.Start("explorer.exe", path);
            else if (OperatingSystem.IsMacOS())
                System.Diagnostics.Process.Start("open", path);
        }
        catch { /* ignore */ }
    }

    [RelayCommand]
    private void DeleteSelected()
    {
        if (SelectedBackup == null) return;

        if (_backupService.DeleteBackup(SelectedBackup.FilePath))
            LoadBackupList();
    }

    [RelayCommand]
    private void Refresh() => LoadBackupList();

    // ─── AUTO-BACKUP TIMER ────────────────────────────────────────────────────

    private void StartAutoTimer()
    {
        StopAutoTimer();

        var intervalMs = AutoIntervalHours * 60 * 60 * 1000.0;
        _autoTimer = new System.Timers.Timer(intervalMs);
        _autoTimer.Elapsed += async (_, _) =>
        {
            await _backupService.RunBackupAsync(IncludeDataDir);
            Avalonia.Threading.Dispatcher.UIThread.Post(() =>
            {
                LoadBackupList();
                UpdateNextBackupText();
            });
        };
        _autoTimer.AutoReset = true;
        _autoTimer.Start();

        AutoStatusText = $"✅ Auto-backup aktif — setiap {AutoIntervalHours} jam";
        UpdateNextBackupText();
    }

    private void StopAutoTimer()
    {
        _autoTimer?.Stop();
        _autoTimer?.Dispose();
        _autoTimer     = null;
        AutoStatusText = "Auto-backup: Nonaktif";
        NextBackupText = "";
    }

    private void UpdateNextBackupText()
    {
        var next = DateTime.Now.AddHours(AutoIntervalHours);
        NextBackupText = $"Backup berikutnya: {next:HH:mm:ss}";
    }

    // ─── HELPERS ──────────────────────────────────────────────────────────────

    private void LoadBackupList()
    {
        BackupList.Clear();
        foreach (var entry in _backupService.ListBackups())
            BackupList.Add(entry);

        ProgressMessage = BackupList.Count == 0
            ? "Belum ada backup."
            : $"Total {BackupList.Count} file backup.";
    }

    private static string ResolveBackupPath(AppConfig config)
    {
        if (!string.IsNullOrEmpty(config.BackupPath) && config.BackupPath != "/sync/backups")
            return config.BackupPath;

        return Path.Combine(
            Environment.GetFolderPath(Environment.SpecialFolder.MyDocuments),
            "MoodleKelas-Backups");
    }
}
