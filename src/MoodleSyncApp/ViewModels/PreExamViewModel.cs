using Avalonia.Media;
using CommunityToolkit.Mvvm.ComponentModel;
using CommunityToolkit.Mvvm.Input;
using MoodleSyncApp.Core.Config;
using MoodleSyncApp.Core.Models;
using MoodleSyncApp.Core.Services;

namespace MoodleSyncApp.ViewModels;

public partial class PreExamViewModel : ObservableObject
{
    private readonly AppConfig _config;
    private readonly SyncService _syncService;
    private readonly MoodleApiService _masterApi;

    [ObservableProperty] private string _courseId = "";
    [ObservableProperty] private List<MoodleCourse> _courses = new();
    [ObservableProperty] private MoodleCourse? _selectedCourse;
    [ObservableProperty] private bool _isSyncing = false;
    [ObservableProperty] private string _progressMessage = "";
    [ObservableProperty] private double _progressValue = 0;
    [ObservableProperty] private bool _hasResult = false;
    [ObservableProperty] private string _resultMessage = "";
    [ObservableProperty] private IBrush _resultBg = Brushes.LightGreen;
    [ObservableProperty] private IBrush _resultBorder = Brushes.Green;

    public PreExamViewModel(AppConfig config, SyncService syncService)
    {
        _config = config;
        _syncService = syncService;
        _masterApi = new MoodleApiService();
        _masterApi.Configure(config.MasterUrl, config.MasterToken);

        _syncService.OnProgress += (msg, pct) =>
        {
            ProgressMessage = msg;
            ProgressValue   = pct;
        };
    }

    [RelayCommand]
    private async Task FetchCourses()
    {
        if (!int.TryParse(CourseId.Trim(), out int cid) || cid <= 0)
        {
            ShowResult(false, "Masukkan Course ID yang valid (angka).");
            return;
        }

        ProgressMessage = $"Mengambil data kursus ID {cid}...";
        HasResult = false;

        try
        {
            // ── Coba plugin E-UJIAN dulu ────────────────────────────────
            List<MoodleUser> students;
            List<MoodleCourse> quizzes;
            bool usingPlugin = false;

            try
            {
                students    = await _masterApi.GetStudentsAsync(cid);
                quizzes     = await _masterApi.GetQuizzesInCourseAsync(cid);
                usingPlugin = true;
            }
            catch (Exception pluginEx) when (
                pluginEx.Message.Contains("local_eujian") ||
                pluginEx.Message.Contains("tidak ada") ||
                pluginEx.Message.Contains("does not exist") ||
                pluginEx.Message.Contains("kontrol akses") ||
                pluginEx.Message.Contains("access control") ||
                pluginEx.Message.Contains("Invalid function"))
            {
                // ── Fallback: API standar Moodle ────────────────────────
                ProgressMessage = "Plugin belum terpasang, menggunakan API standar...";
                students = await _masterApi.GetEnrolledUsersAsync(cid);

                // Untuk quiz, tampilkan kursus saja sebagai pilihan
                var course = await _masterApi.GetCourseByIdAsync(cid);
                quizzes = course != null ? new List<MoodleCourse> { course } : new();
            }

            Courses        = quizzes;
            SelectedCourse = quizzes.FirstOrDefault();

            var mode = usingPlugin ? "via plugin E-UJIAN" : "via API standar";
            var msg  = $"✅ Kursus ID {cid}: {students.Count} siswa ditemukan ({mode})";

            ProgressMessage = msg;
            ShowResult(true, msg);

            if (!usingPlugin)
            {
                ProgressMessage += "\n⚠️ Install plugin E-UJIAN di Moodle untuk fitur lengkap.";
            }
        }
        catch (Exception ex)
        {
            ShowResult(false, $"Gagal: {ex.Message}\n\nPastikan:\n• URL & Token benar di Pengaturan\n• Token memiliki izin 'core_enrol_get_enrolled_users'");
        }
    }

    [RelayCommand]
    private async Task SyncUsers()
    {
        if (SelectedCourse == null) return;

        IsSyncing  = true;
        HasResult  = false;
        ProgressValue = 0;

        var result = await _syncService.SyncUsersFromMasterAsync(SelectedCourse.Id);

        IsSyncing = false;
        ShowResult(result.Success, result.Message);
    }

    private void ShowResult(bool success, string message)
    {
        HasResult     = true;
        ResultMessage = message;
        ResultBg     = success
            ? new SolidColorBrush(new Avalonia.Media.Color(255, 200, 230, 201))  // light green
            : new SolidColorBrush(new Avalonia.Media.Color(255, 255, 205, 210)); // light red
        ResultBorder  = success ? Brushes.Green : Brushes.Red;
    }
}
