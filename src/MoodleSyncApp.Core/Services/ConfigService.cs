using System.Text.Json;
using MoodleSyncApp.Core.Config;

namespace MoodleSyncApp.Core.Services;

/// <summary>
/// Simpan dan load AppConfig dari file appsettings.json
/// </summary>
public static class ConfigService
{
    private static readonly string ConfigPath = Path.Combine(
        AppContext.BaseDirectory, "appsettings.json");

    private static readonly JsonSerializerOptions JsonOpts = new()
    {
        WriteIndented = true
    };

    public static AppConfig Load()
    {
        try
        {
            if (File.Exists(ConfigPath))
            {
                var json = File.ReadAllText(ConfigPath);
                return JsonSerializer.Deserialize<AppConfig>(json, JsonOpts)
                       ?? new AppConfig();
            }
        }
        catch { /* fallback ke default */ }

        return new AppConfig();
    }

    public static void Save(AppConfig config)
    {
        var json = JsonSerializer.Serialize(config, JsonOpts);
        File.WriteAllText(ConfigPath, json);
    }
}
