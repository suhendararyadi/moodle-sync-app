using Avalonia;
using System.Runtime.InteropServices;

namespace MoodleSyncApp;

class Program
{
    [STAThread]
    public static void Main(string[] args)
    {
        try
        {
            BuildAvaloniaApp().StartWithClassicDesktopLifetime(args);
        }
        catch (Exception ex)
        {
            var logPath = Path.Combine(
                Environment.GetFolderPath(Environment.SpecialFolder.Desktop),
                "E-UJIAN-error.txt");

            var errorText =
                $"[{DateTime.Now}] CRASH\n\n" +
                $"OS      : {RuntimeInformation.OSDescription}\n" +
                $"Runtime : {RuntimeInformation.FrameworkDescription}\n\n" +
                $"Message :\n{ex.Message}\n\n" +
                $"StackTrace:\n{ex.StackTrace}\n\n" +
                $"Inner   : {ex.InnerException?.Message}";

            File.WriteAllText(logPath, errorText);

            // Native Windows MessageBox via P/Invoke
            if (RuntimeInformation.IsOSPlatform(OSPlatform.Windows))
                NativeMessageBox(IntPtr.Zero,
                    $"Aplikasi gagal start!\n\nError: {ex.Message}\n\nDetail tersimpan di Desktop:\nE-UJIAN-error.txt",
                    "E-UJIAN - Error", 0x10);
        }
    }

    [DllImport("user32.dll", CharSet = CharSet.Unicode, EntryPoint = "MessageBoxW")]
    static extern int NativeMessageBox(IntPtr hWnd, string text, string caption, uint type);

    public static AppBuilder BuildAvaloniaApp()
        => AppBuilder.Configure<App>()
            .UsePlatformDetect()
            .WithInterFont()
            .LogToTrace();
}

