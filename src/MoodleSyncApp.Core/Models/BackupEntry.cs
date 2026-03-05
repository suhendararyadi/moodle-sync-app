namespace MoodleSyncApp.Core.Models;

public class BackupEntry
{
    public string FileName   { get; set; } = "";
    public string FilePath   { get; set; } = "";
    public long   SizeBytes  { get; set; }
    public DateTime CreatedAt { get; set; }

    public string SizeDisplay => SizeBytes >= 1024 * 1024
        ? $"{SizeBytes / 1024.0 / 1024:F1} MB"
        : $"{SizeBytes / 1024.0:F0} KB";

    public string DateDisplay => CreatedAt.ToString("dd MMM yyyy  HH:mm");
    public string Label       => $"{FileName}  ({SizeDisplay})  —  {DateDisplay}";
}
