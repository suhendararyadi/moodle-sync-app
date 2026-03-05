using MoodleSyncApp.Core.Config;
using MoodleSyncApp.Core.Services;
using Avalonia.Media;
using CommunityToolkit.Mvvm.ComponentModel;
using CommunityToolkit.Mvvm.Input;

namespace MoodleSyncApp.ViewModels;

public partial class SettingsViewModel : ObservableObject
{
    private readonly AppConfig _config;
    private readonly MoodleApiService _masterApi = new();
    private readonly MoodleApiService _localApi  = new();

    [ObservableProperty] private string _masterUrl   = "";
    [ObservableProperty] private string _masterToken = "";
    [ObservableProperty] private string _localUrl    = "";
    [ObservableProperty] private string _localToken  = "";
    [ObservableProperty] private string _roomId      = "";
    [ObservableProperty] private string _roomName    = "";

    [ObservableProperty] private string _masterTestResult = "";
    [ObservableProperty] private string _localTestResult  = "";
    [ObservableProperty] private IBrush _masterTestColor  = Brushes.Gray;
    [ObservableProperty] private IBrush _localTestColor   = Brushes.Gray;

    [ObservableProperty] private string _saveMessage      = "";
    [ObservableProperty] private IBrush _saveMessageColor = Brushes.Green;

    public SettingsViewModel(AppConfig config)
    {
        _config = config;
        // Load current config
        MasterUrl   = config.MasterUrl;
        MasterToken = config.MasterToken;
        LocalUrl    = config.LocalUrl;
        LocalToken  = config.LocalToken;
        RoomId      = config.RoomId;
        RoomName    = config.RoomName;
    }

    [RelayCommand]
    private async Task TestMaster()
    {
        MasterTestResult = "Mencoba koneksi...";
        MasterTestColor  = Brushes.Orange;
        _masterApi.Configure(MasterUrl, MasterToken);
        var ok = await _masterApi.PingAsync();
        MasterTestResult = ok ? "✅ Koneksi berhasil!" : "❌ Koneksi gagal. Periksa URL dan token.";
        MasterTestColor  = ok ? Brushes.Green : Brushes.Red;
    }

    [RelayCommand]
    private async Task TestLocal()
    {
        LocalTestResult = "Mencoba koneksi...";
        LocalTestColor  = Brushes.Orange;
        _localApi.Configure(LocalUrl, LocalToken);
        var ok = await _localApi.PingAsync();
        LocalTestResult = ok ? "✅ Koneksi berhasil!" : "❌ Koneksi gagal. Periksa URL dan token.";
        LocalTestColor  = ok ? Brushes.Green : Brushes.Red;
    }

    [RelayCommand]
    private void Save()
    {
        _config.MasterUrl   = MasterUrl;
        _config.MasterToken = MasterToken;
        _config.LocalUrl    = LocalUrl;
        _config.LocalToken  = LocalToken;
        _config.RoomId      = RoomId;
        _config.RoomName    = RoomName;

        ConfigService.Save(_config);
        SaveMessage      = $"✅ Pengaturan disimpan — {DateTime.Now:HH:mm:ss}";
        SaveMessageColor = Brushes.Green;
    }
}
