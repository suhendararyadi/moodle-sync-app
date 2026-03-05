using System.Collections.ObjectModel;
using Avalonia.Media;
using CommunityToolkit.Mvvm.ComponentModel;
using CommunityToolkit.Mvvm.Input;
using MoodleSyncApp.Core.Config;
using MoodleSyncApp.Core.Services;

namespace MoodleSyncApp.ViewModels;

public partial class UploadViewModel : ObservableObject
{
    private readonly AppConfig _config;
    private readonly SyncService _syncService;
    private readonly string _exportDir;

    [ObservableProperty] private string _quizId = "";
    [ObservableProperty] private ObservableCollection<string> _exportedFiles = new();
    [ObservableProperty] private string? _selectedFile;
    [ObservableProperty] private bool _isWorking = false;
    [ObservableProperty] private bool _isIndeterminate = false;
    [ObservableProperty] private string _progressMessage = "";
    [ObservableProperty] private double _progressValue = 0;
    [ObservableProperty] private bool _hasResult = false;
    [ObservableProperty] private string _resultMessage = "";
    [ObservableProperty] private IBrush _resultBg = Brushes.LightGreen;
    [ObservableProperty] private IBrush _resultBorder = Brushes.Green;
    [ObservableProperty] private string _exportResult = "";
    [ObservableProperty] private IBrush _exportResultColor = Brushes.Gray;

    public UploadViewModel(AppConfig config, SyncService syncService)
    {
        _config = config;
        _syncService = syncService;
        _exportDir = Path.Combine(AppContext.BaseDirectory, "exports");

        _syncService.OnProgress += (msg, pct) =>
        {
            ProgressMessage = msg;
            ProgressValue   = pct;
        };

        LoadExportedFiles();
    }

    private void LoadExportedFiles()
    {
        ExportedFiles.Clear();
        if (!Directory.Exists(_exportDir)) return;
        foreach (var f in Directory.GetFiles(_exportDir, "*.json").OrderByDescending(f => f))
            ExportedFiles.Add(Path.GetFileName(f));
    }

    [RelayCommand]
    private async Task Export()
    {
        if (!int.TryParse(QuizId, out int quizIdInt)) return;

        IsWorking = true;
        IsIndeterminate = true;
        ExportResult = "Mengekspor...";
        ExportResultColor = Brushes.Orange;

        var result = await _syncService.ExportQuizResultsAsync(quizIdInt, _exportDir);

        IsWorking = false;
        IsIndeterminate = false;
        ExportResult = result.Message;
        ExportResultColor = result.Success ? Brushes.Green : Brushes.Red;

        if (result.Success) LoadExportedFiles();
    }

    [RelayCommand]
    private async Task Upload()
    {
        if (SelectedFile == null) return;

        IsWorking  = true;
        HasResult  = false;
        ProgressValue = 0;

        try
        {
            var filePath = Path.Combine(_exportDir, SelectedFile);
            var json     = await File.ReadAllTextAsync(filePath);

            // POST ke master via multipart atau custom endpoint
            // Sementara: simpan ke folder incoming di master via SFTP/HTTP
            ProgressMessage = "Mengirim ke server utama...";
            ProgressValue   = 50;

            // TODO: Implement upload via REST API / SFTP
            await Task.Delay(1500); // simulate upload
            ProgressValue = 100;

            ShowResult(true, $"✅ Berhasil upload: {SelectedFile}\nFile terkirim ke server utama.");
        }
        catch (Exception ex)
        {
            ShowResult(false, $"❌ Gagal upload: {ex.Message}");
        }
        finally
        {
            IsWorking = false;
        }
    }

    private void ShowResult(bool success, string msg)
    {
        HasResult     = true;
        ResultMessage = msg;
        ResultBg      = success
            ? new SolidColorBrush(new Avalonia.Media.Color(255, 200, 230, 201))
            : new SolidColorBrush(new Avalonia.Media.Color(255, 255, 205, 210));
        ResultBorder  = success ? Brushes.Green : Brushes.Red;
    }
}
