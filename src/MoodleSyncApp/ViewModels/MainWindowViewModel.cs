using System.Collections.ObjectModel;
using System.Timers;
using Avalonia.Media;
using CommunityToolkit.Mvvm.ComponentModel;
using CommunityToolkit.Mvvm.Input;
using MoodleSyncApp.Core.Config;
using MoodleSyncApp.Core.Services;
using MoodleSyncApp.Views;

namespace MoodleSyncApp.ViewModels;

public partial class MainWindowViewModel : ObservableObject
{
    private readonly AppConfig _config;
    private readonly LogService _logger;
    private readonly SyncService _syncService;
    private readonly BackupService _backupService;
    private readonly System.Timers.Timer _clockTimer;

    [ObservableProperty] private object? _currentView;
    [ObservableProperty] private string _statusMessage = "Siap.";
    [ObservableProperty] private string _currentTime = DateTime.Now.ToString("HH:mm:ss");
    [ObservableProperty] private string _roomInfo = "";

    // Koneksi status
    [ObservableProperty] private string _masterStatusText = "Master: Belum dicek";
    [ObservableProperty] private string _localStatusText  = "Lokal: Belum dicek";
    [ObservableProperty] private IBrush _masterStatusColor = Brushes.Gray;
    [ObservableProperty] private IBrush _localStatusColor  = Brushes.Gray;

    // Nav highlight
    [ObservableProperty] private IBrush _preExamBg  = Brushes.Transparent;
    [ObservableProperty] private IBrush _monitorBg  = Brushes.Transparent;
    [ObservableProperty] private IBrush _uploadBg   = Brushes.Transparent;
    [ObservableProperty] private IBrush _logBg      = Brushes.Transparent;
    [ObservableProperty] private IBrush _backupBg   = Brushes.Transparent;

    public MainWindowViewModel()
    {
        _config = LoadConfig();
        _logger = new LogService();
        _syncService = new SyncService(_config, _logger);
        _backupService = new BackupService(_config, _logger);

        RoomInfo = $"Ruangan: {_config.RoomName} ({_config.RoomId})";

        // Default view
        NavigatePreExam();

        // Clock update
        _clockTimer = new System.Timers.Timer(1000);
        _clockTimer.Elapsed += (_, _) => CurrentTime = DateTime.Now.ToString("HH:mm:ss");
        _clockTimer.Start();
    }

    // ─── NAVIGATION ───────────────────────────────────────────────────────────

    [RelayCommand]
    private void NavigatePreExam()
    {
        CurrentView = new PreExamView { DataContext = new PreExamViewModel(_config, _syncService) };
        SetNavHighlight("preexam");
        StatusMessage = "Pre-Exam: Sinkronisasi sebelum ujian";
    }

    [RelayCommand]
    private void NavigateMonitor()
    {
        CurrentView = new MonitorView { DataContext = new MonitorViewModel(_config) };
        SetNavHighlight("monitor");
        StatusMessage = "Monitor: Pantau siswa yang sedang ujian";
    }

    [RelayCommand]
    private void NavigateUpload()
    {
        CurrentView = new UploadView { DataContext = new UploadViewModel(_config, _syncService) };
        SetNavHighlight("upload");
        StatusMessage = "Upload: Kirim hasil ke server utama";
    }

    [RelayCommand]
    private void NavigateLog()
    {
        CurrentView = new LogView { DataContext = new LogViewModel(_logger) };
        SetNavHighlight("log");
        StatusMessage = "Log: Riwayat aktivitas";
    }

    [RelayCommand]
    private void NavigateBackup()
    {
        CurrentView = new BackupView { DataContext = new BackupViewModel(_config, _backupService) };
        SetNavHighlight("backup");
        StatusMessage = "Backup: Cadangan database lokal";
    }

    [RelayCommand]
    private void NavigateSettings()
    {
        CurrentView = new SettingsView { DataContext = new SettingsViewModel(_config) };
        SetNavHighlight("settings");
        StatusMessage = "Pengaturan koneksi server";
    }

    // ─── CHECK CONNECTION ─────────────────────────────────────────────────────

    [RelayCommand]
    private async Task CheckConnection()
    {
        StatusMessage = "Memeriksa koneksi...";
        MasterStatusColor = Brushes.Orange;
        LocalStatusColor  = Brushes.Orange;

        var (masterOk, localOk) = await _syncService.CheckConnectionsAsync();

        MasterStatusText  = masterOk ? "Master: ✅ Terhubung" : "Master: ❌ Gagal";
        LocalStatusText   = localOk  ? "Lokal: ✅ Terhubung"  : "Lokal: ❌ Gagal";
        MasterStatusColor = masterOk ? Brushes.LightGreen : Brushes.Red;
        LocalStatusColor  = localOk  ? Brushes.LightGreen : Brushes.Red;

        StatusMessage = $"Cek koneksi selesai — {DateTime.Now:HH:mm:ss}";
    }

    private void SetNavHighlight(string active)
    {
        var hl = new SolidColorBrush(new Avalonia.Media.Color(255, 187, 222, 251)); // #BBDEFB
        PreExamBg = active == "preexam"  ? hl : Brushes.Transparent;
        MonitorBg = active == "monitor"  ? hl : Brushes.Transparent;
        UploadBg  = active == "upload"   ? hl : Brushes.Transparent;
        LogBg     = active == "log"      ? hl : Brushes.Transparent;
        BackupBg  = active == "backup"   ? hl : Brushes.Transparent;
    }

    private static AppConfig LoadConfig() => ConfigService.Load();
}
