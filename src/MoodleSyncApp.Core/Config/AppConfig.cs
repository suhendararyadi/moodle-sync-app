namespace MoodleSyncApp.Core.Config;

public class AppConfig
{
    // Server Utama (Master)
    public string MasterUrl { get; set; } = "http://192.168.1.1/moodle";
    public string MasterToken { get; set; } = "";

    // Server Lokal (Kelas)
    public string LocalUrl { get; set; } = "http://localhost/moodle";
    public string LocalToken { get; set; } = "";

    // Identitas Ruangan
    public string RoomId { get; set; } = "ROOM_01";
    public string RoomName { get; set; } = "Ruang Kelas 1";

    // SSH/SFTP untuk transfer backup
    public string SshHost { get; set; } = "";
    public int SshPort { get; set; } = 22;
    public string SshUsername { get; set; } = "";
    public string SshPassword { get; set; } = "";
    public string MoodleDataPath { get; set; } = "/var/www/moodledata";
    public string BackupPath { get; set; } = "/sync/backups";

    // Auto-sync
    public bool AutoSyncEnabled { get; set; } = false;
    public int AutoSyncIntervalMinutes { get; set; } = 15;
}
