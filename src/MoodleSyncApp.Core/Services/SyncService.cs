using MoodleSyncApp.Core.Config;
using MoodleSyncApp.Core.Models;

namespace MoodleSyncApp.Core.Services;

/// <summary>
/// Orchestrator utama untuk semua proses sinkronisasi
/// Pre-Exam: Master → Kelas | Post-Exam: Kelas → Master
/// </summary>
public class SyncService
{
    private readonly MoodleApiService _masterApi;
    private readonly MoodleApiService _localApi;
    private readonly LogService _logger;
    private readonly AppConfig _config;

    public event Action<string, int>? OnProgress; // message, percentage

    public SyncService(AppConfig config, LogService logger)
    {
        _config = config;
        _logger = logger;

        _masterApi = new MoodleApiService();
        _masterApi.Configure(config.MasterUrl, config.MasterToken);

        _localApi = new MoodleApiService();
        _localApi.Configure(config.LocalUrl, config.LocalToken);
    }

    // ─── PRE-EXAM: Sync Master → Kelas ────────────────────────────────────────

    /// <summary>
    /// Sync semua user dari server master ke server lokal
    /// Menggunakan idnumber (NIS/NISN) sebagai anchor, bukan user ID
    /// </summary>
    public async Task<SyncResult> SyncUsersFromMasterAsync(int courseId)
    {
        var result = new SyncResult();
        var jobId = Guid.NewGuid().ToString()[..8];

        try
        {
            _logger.Log($"[{jobId}] Mulai sync user dari master, course ID: {courseId}", LogLevel.Info, jobId);
            OnProgress?.Invoke("Mengambil daftar siswa dari server utama...", 10);

            // Pakai plugin E-UJIAN jika tersedia, lebih reliable
            List<MoodleUser> masterUsers;
            try
            {
                masterUsers = await _masterApi.GetStudentsAsync(courseId);
                _logger.Log($"[{jobId}] Plugin E-UJIAN: {masterUsers.Count} siswa", LogLevel.Info, jobId);
            }
            catch
            {
                // Fallback ke API standar
                masterUsers = await _masterApi.GetEnrolledUsersAsync(courseId);
                _logger.Log($"[{jobId}] Fallback API standar: {masterUsers.Count} siswa", LogLevel.Info, jobId);
            }

            OnProgress?.Invoke($"Ditemukan {masterUsers.Count} siswa", 30);

            // 2. Ambil user yang sudah ada di lokal
            var localUsers = await _localApi.GetEnrolledUsersAsync(courseId);
            var localIdNumbers = localUsers.Select(u => u.Idnumber).ToHashSet();

            // 3. Sync hanya user yang belum ada (berdasarkan idnumber/NIS)
            int synced = 0;
            var toSync = masterUsers.Where(u => !localIdNumbers.Contains(u.Idnumber)).ToList();

            foreach (var user in toSync)
            {
                // Password default: Eujian@[NIS] — mudah diingat, memenuhi semua syarat Moodle
                var nis = string.IsNullOrEmpty(user.Idnumber) ? user.Username : user.Idnumber;
                var defaultPass = "Eujian@" + nis;

                await _localApi.CreateUserAsync(user, defaultPass);
                synced++;
                int progress = 30 + (int)(synced * 60.0 / toSync.Count);
                OnProgress?.Invoke($"Sync user {synced}/{toSync.Count}: {user.FullName}", progress);
            }

            result.Success = true;
            result.RecordsProcessed = synced;
            result.Message = $"Berhasil sync {synced} user baru dari {masterUsers.Count} total.";
            result.Logs.Add(result.Message);

            _logger.Log(result.Message, LogLevel.Success, jobId);
            OnProgress?.Invoke("Sync user selesai!", 100);
        }
        catch (Exception ex)
        {
            result.Success = false;
            result.Message = $"Gagal sync user: {ex.Message}";
            _logger.Log(result.Message, LogLevel.Error, jobId);
        }

        return result;
    }

    /// <summary>
    /// Sync SEMUA user dari master ke lokal tanpa filter kursus.
    /// Menggunakan batch API call (50 user per request) — jauh lebih cepat.
    /// </summary>
    public async Task<SyncResult> SyncAllUsersFromMasterAsync()
    {
        var result    = new SyncResult();
        var jobId     = Guid.NewGuid().ToString()[..8];
        const int BatchSize = 50;

        try
        {
            OnProgress?.Invoke("Mengambil semua user dari master...", 5);
            var masterUsers = await _masterApi.GetAllUsersAsync();

            var students = masterUsers
                .Where(u => !string.IsNullOrEmpty(u.Username)
                         && u.Username != "guest"
                         && !u.Username.StartsWith("admin")
                         && u.Id > 1)
                .ToList();

            _logger.Log($"[{jobId}] Total user master: {masterUsers.Count}, siswa: {students.Count}", LogLevel.Info, jobId);
            result.Logs.Add($"Total user di master: {masterUsers.Count}");
            result.Logs.Add($"Siswa (non-admin): {students.Count}");

            OnProgress?.Invoke($"Ditemukan {students.Count} user, memuat data lokal...", 15);

            var localUsers     = await _localApi.GetAllUsersAsync();
            var localUsernames = localUsers.Select(u => u.Username).ToHashSet();

            var toSync = students.Where(u => !localUsernames.Contains(u.Username)).ToList();
            result.Logs.Add($"Perlu dibuat: {toSync.Count} (sudah ada: {students.Count - toSync.Count})");

            int totalBatches = (int)Math.Ceiling(toSync.Count / (double)BatchSize);
            _logger.Log($"[{jobId}] Batch size: {BatchSize}, total batch: {totalBatches}", LogLevel.Info, jobId);
            OnProgress?.Invoke($"{toSync.Count} user → {totalBatches} batch @{BatchSize}...", 20);

            int created = 0, failed = 0;
            for (int b = 0; b < totalBatches; b++)
            {
                var batch = toSync
                    .Skip(b * BatchSize)
                    .Take(BatchSize)
                    .Select(u =>
                    {
                        var nis  = string.IsNullOrEmpty(u.Idnumber) ? u.Username : u.Idnumber;
                        return (User: u, Password: "Eujian@" + nis);
                    })
                    .ToList();

                int pct  = 20 + (int)((b + 1) * 75.0 / totalBatches);
                int from = b * BatchSize + 1;
                int to   = Math.Min(from + BatchSize - 1, toSync.Count);
                OnProgress?.Invoke($"Batch {b+1}/{totalBatches} — user {from}–{to} dari {toSync.Count}", pct);

                try
                {
                    var batchResult = await _localApi.CreateUsersBatchAsync(batch);
                    created += batchResult.Count;
                    int batchFailed = batch.Count - batchResult.Count;
                    if (batchFailed > 0)
                    {
                        failed += batchFailed;
                        _logger.Log($"[{jobId}] Batch {b+1}: {batchResult.Count} OK, {batchFailed} gagal", LogLevel.Warning, jobId);
                    }
                }
                catch (Exception bex)
                {
                    // Jika batch gagal total, fallback satu per satu untuk batch ini
                    _logger.Log($"[{jobId}] Batch {b+1} gagal ({bex.Message}), fallback per-user...", LogLevel.Warning, jobId);
                    foreach (var (user, pass) in batch)
                    {
                        var ok = await _localApi.CreateUserAsync(user, pass);
                        if (ok) created++; else failed++;
                    }
                }
            }

            result.Success          = true;
            result.RecordsProcessed = created;
            result.Message =
                $"Sync semua user selesai!\n" +
                $"  Berhasil dibuat : {created}\n" +
                $"  Sudah ada (skip): {students.Count - toSync.Count}\n" +
                $"  Gagal           : {failed}";

            OnProgress?.Invoke("Sync semua user selesai!", 100);
            _logger.Log(result.Message, LogLevel.Success, jobId);
        }
        catch (Exception ex)
        {
            result.Success = false;
            result.Message = $"Gagal sync semua user: {ex.Message}";
            _logger.Log(result.Message, LogLevel.Error, jobId);
        }

        return result;
    }

    // ─── SYNC COURSE + SISWA: Master → Lokal ─────────────────────────────────

    /// <summary>
    /// Sync kursus + siswa dari master ke lokal dalam satu operasi:
    /// 1. Ambil data kursus dari master
    /// 2. Buat kursus di lokal (jika belum ada)
    /// 3. Ambil semua siswa di kursus
    /// 4. Buat akun siswa di lokal (jika belum ada)
    /// 5. Enroll siswa ke kursus lokal
    /// </summary>
    public async Task<SyncResult> SyncCourseFromMasterAsync(int masterCourseId)
    {
        var result = new SyncResult();
        var jobId  = Guid.NewGuid().ToString()[..8];

        try
        {
            // ── STEP 1: Ambil data kursus dari master ─────────────────────
            OnProgress?.Invoke("Mengambil data kursus dari master...", 5);
            var course = await _masterApi.GetCourseByIdAsync(masterCourseId);
            if (course == null)
                throw new Exception($"Kursus ID {masterCourseId} tidak ditemukan di master.");

            result.Logs.Add($"Kursus master: [{course.Id}] {course.Fullname}");
            _logger.Log($"[{jobId}] Kursus: {course.Fullname}", LogLevel.Info, jobId);

            // ── STEP 2: Cek/buat kursus di lokal ─────────────────────────
            OnProgress?.Invoke($"Menyiapkan kursus '{course.Shortname}' di lokal...", 15);

            // Cari kursus lokal berdasarkan idnumber (= master ID) atau shortname
            var localCourse = await _localApi.GetCourseByIdnumber(masterCourseId.ToString())
                           ?? await _localApi.GetCourseByShortname(course.Shortname);
            int localCourseId = 0;

            if (localCourse != null)
            {
                localCourseId = localCourse.Id;
                result.Logs.Add($"Kursus sudah ada di lokal (ID lokal: {localCourseId}, '{localCourse.Shortname}')");
                _logger.Log($"[{jobId}] Kursus lokal ditemukan ID: {localCourseId}", LogLevel.Info, jobId);
            }
            else
            {
                // Buat kursus baru di lokal
                localCourseId = await _localApi.CreateCourseAsync(course);
                if (localCourseId == 0)
                    throw new Exception("Gagal membuat kursus di server lokal.");
                result.Logs.Add($"Kursus baru dibuat di lokal (ID lokal: {localCourseId})");
                _logger.Log($"[{jobId}] Kursus baru dibuat, ID lokal: {localCourseId}", LogLevel.Info, jobId);
            }

            // ── STEP 3: Ambil siswa dari master ───────────────────────────
            OnProgress?.Invoke("Mengambil daftar siswa dari master...", 25);
            List<MoodleUser> masterUsers;
            try
            {
                masterUsers = await _masterApi.GetStudentsAsync(masterCourseId);
            }
            catch
            {
                masterUsers = await _masterApi.GetEnrolledUsersAsync(masterCourseId);
            }

            // Filter hanya role siswa (bukan admin/teacher)
            var students = masterUsers
                .Where(u => !string.IsNullOrEmpty(u.Username) &&
                            u.Username != "guest" &&
                            !u.Username.StartsWith("admin"))
                .ToList();

            result.Logs.Add($"Siswa di master: {students.Count}");
            OnProgress?.Invoke($"Ditemukan {students.Count} siswa, mulai sync batch...", 35);

            // ── STEP 4: Buat user baru secara BATCH ──────────────────────
            var localUsers     = await _localApi.GetEnrolledUsersAsync(localCourseId);
            var localUsernames  = localUsers.Select(u => u.Username).ToHashSet();

            var toCreate = students.Where(s => !localUsernames.Contains(s.Username)).ToList();
            var existing = students.Where(s =>  localUsernames.Contains(s.Username)).ToList();
            int skipped  = existing.Count;
            int created  = 0, failed = 0;

            const int BatchSize = 50;
            int totalBatches = (int)Math.Ceiling(toCreate.Count / (double)BatchSize);

            for (int b = 0; b < totalBatches; b++)
            {
                var batch = toCreate
                    .Skip(b * BatchSize).Take(BatchSize)
                    .Select(u => (User: u, Password: "Eujian@" + (string.IsNullOrEmpty(u.Idnumber) ? u.Username : u.Idnumber)))
                    .ToList();

                int pct  = 35 + (int)((b + 1) * 35.0 / Math.Max(totalBatches, 1));
                int from = b * BatchSize + 1;
                int to   = Math.Min(from + BatchSize - 1, toCreate.Count);
                OnProgress?.Invoke($"Batch {b+1}/{totalBatches} — buat user {from}–{to}", pct);

                try
                {
                    var batchResult = await _localApi.CreateUsersBatchAsync(batch);
                    created += batchResult.Count;
                    failed  += batch.Count - batchResult.Count;
                    _logger.Log($"[{jobId}] Batch {b+1}: {batchResult.Count}/{batch.Count} OK", LogLevel.Info, jobId);
                }
                catch (Exception bex)
                {
                    _logger.Log($"[{jobId}] Batch {b+1} gagal, fallback per-user: {bex.Message}", LogLevel.Warning, jobId);
                    foreach (var (user, pass) in batch)
                    {
                        var ok = await _localApi.CreateUserAsync(user, pass);
                        if (ok) created++; else failed++;
                    }
                }
            }

            // ── STEP 5: Enroll semua siswa ke kursus lokal ───────────────
            OnProgress?.Invoke("Memuat ulang user lokal untuk enroll...", 72);
            var allLocalUsers    = await _localApi.GetAllUsersAsync();
            var localUserMap     = allLocalUsers.ToDictionary(u => u.Username, u => u.Id);

            int enrolled = 0;
            for (int i = 0; i < students.Count; i++)
            {
                int pct = 72 + (int)((i + 1) * 23.0 / students.Count);
                OnProgress?.Invoke($"Enroll [{i+1}/{students.Count}] {students[i].FullName}", pct);

                if (!localUserMap.TryGetValue(students[i].Username, out int localUserId)) continue;
                try
                {
                    await _localApi.EnrolUserAsync(localUserId, localCourseId);
                    enrolled++;
                }
                catch { /* sudah terdaftar */ }
            }

            result.Success          = true;
            result.RecordsProcessed = students.Count;
            result.Message = $"Sync selesai! Kursus: '{course.Fullname}'\n" +
                             $"  Siswa baru dibuat : {created}\n" +
                             $"  Sudah ada (skip)  : {skipped}\n" +
                             $"  Berhasil di-enroll: {enrolled}";

            result.Logs.Add(result.Message);
            _logger.Log(result.Message, LogLevel.Success, jobId);
            OnProgress?.Invoke("Sync kursus selesai!", 100);
        }
        catch (Exception ex)
        {
            result.Success  = false;
            result.Message  = $"Gagal sync kursus: {ex.Message}";
            _logger.Log(result.Message, LogLevel.Error, jobId);
        }

        return result;
    }



    /// <summary>
    /// Export hasil ujian dari server lokal dan siapkan untuk upload ke master
    /// </summary>
    public async Task<SyncResult> ExportQuizResultsAsync(int quizId, string outputPath)
    {
        var result = new SyncResult();
        var jobId = Guid.NewGuid().ToString()[..8];

        try
        {
            _logger.Log($"[{jobId}] Export hasil quiz ID: {quizId}", LogLevel.Info, jobId);
            OnProgress?.Invoke("Mengambil data hasil ujian...", 20);

            var attempts = await _localApi.GetQuizAttemptsAsync(quizId);
            _logger.Log($"[{jobId}] Ditemukan {attempts.Count} attempt", LogLevel.Info, jobId);

            // Buat payload dengan room identifier
            var exportPayload = new
            {
                RoomId = _config.RoomId,
                RoomName = _config.RoomName,
                ExportedAt = DateTime.Now,
                QuizId = quizId,
                Attempts = attempts
            };

            var json = Newtonsoft.Json.JsonConvert.SerializeObject(exportPayload, Newtonsoft.Json.Formatting.Indented);
            var fileName = $"{_config.RoomId}_quiz{quizId}_{DateTime.Now:yyyyMMdd_HHmm}.json";
            var filePath = Path.Combine(outputPath, fileName);

            Directory.CreateDirectory(outputPath);
            await File.WriteAllTextAsync(filePath, json);

            result.Success = true;
            result.RecordsProcessed = attempts.Count;
            result.Message = $"Berhasil export {attempts.Count} attempt ke: {fileName}";
            result.Logs.Add(filePath);

            _logger.Log(result.Message, LogLevel.Success, jobId);
            OnProgress?.Invoke("Export selesai!", 100);
        }
        catch (Exception ex)
        {
            result.Success = false;
            result.Message = $"Gagal export: {ex.Message}";
            _logger.Log(result.Message, LogLevel.Error, jobId);
        }

        return result;
    }

    // ─── HEALTH CHECK ──────────────────────────────────────────────────────────

    public async Task<(bool masterOk, bool localOk)> CheckConnectionsAsync()
    {
        var masterTask = _masterApi.PingAsync();
        var localTask = _localApi.PingAsync();
        await Task.WhenAll(masterTask, localTask);
        return (masterTask.Result, localTask.Result);
    }

    // ─── COHORT SYNC ───────────────────────────────────────────────────────────

    public async Task<SyncResult> SyncCohortsFromMasterAsync()
    {
        var result = new SyncResult();
        var jobId  = Guid.NewGuid().ToString()[..8];

        try
        {
            OnProgress?.Invoke("Mengambil daftar cohort/rombel dari master...", 5);
            var masterCohorts = await _masterApi.GetAllCohortsAsync();
            _logger.Log($"[{jobId}] Ditemukan {masterCohorts.Count} cohort di master", LogLevel.Info, jobId);
            result.Logs.Add($"Cohort di master: {masterCohorts.Count}");

            OnProgress?.Invoke("Mengecek cohort yang sudah ada di lokal...", 10);
            var localCohorts   = await _localApi.GetAllCohortsAsync();
            var localCohortMap = localCohorts.ToDictionary(c => c.Idnumber, c => c.Id);

            OnProgress?.Invoke("Memuat daftar user lokal...", 15);
            var localUsers      = await _localApi.GetAllUsersAsync();
            var localUserByName = localUsers.ToDictionary(u => u.Username, u => u.Id);

            OnProgress?.Invoke("Memuat daftar user master untuk mapping...", 20);
            var masterUsers    = await _masterApi.GetAllUsersAsync();
            var masterUserById = masterUsers.ToDictionary(u => u.Id, u => u);

            int cohortCreated = 0, cohortExist = 0, memberAdded = 0, memberSkip = 0;

            for (int i = 0; i < masterCohorts.Count; i++)
            {
                var mc  = masterCohorts[i];
                int pct = 25 + (int)((i + 1) * 70.0 / masterCohorts.Count);
                OnProgress?.Invoke($"[{i+1}/{masterCohorts.Count}] Cohort: {mc.Name}", pct);

                int localCohortId;
                if (localCohortMap.TryGetValue(mc.Idnumber, out localCohortId))
                {
                    cohortExist++;
                }
                else
                {
                    localCohortId = await _localApi.CreateCohortAsync(mc);
                    if (localCohortId == 0) { result.Logs.Add($"GAGAL buat cohort: {mc.Name}"); continue; }
                    localCohortMap[mc.Idnumber] = localCohortId;
                    cohortCreated++;
                    _logger.Log($"[{jobId}] Cohort baru: {mc.Name} (ID lokal {localCohortId})", LogLevel.Info, jobId);
                }

                var masterMemberIds = await _masterApi.GetCohortMembersAsync(mc.Id);
                var localMemberIds  = await _localApi.GetCohortMembersAsync(localCohortId);
                var localMemberSet  = localMemberIds.ToHashSet();

                int added = 0;
                foreach (var masterUserId in masterMemberIds)
                {
                    if (!masterUserById.TryGetValue(masterUserId, out var masterUser)) continue;
                    if (!localUserByName.TryGetValue(masterUser.Username, out int localUserId)) continue;
                    if (localMemberSet.Contains(localUserId)) { memberSkip++; continue; }

                    await _localApi.AddCohortMemberAsync(localCohortId, localUserId);
                    memberAdded++;
                    added++;
                }
                result.Logs.Add($"{mc.Name}: {masterMemberIds.Count} anggota, +{added} ditambahkan ke lokal");
            }

            result.Success          = true;
            result.RecordsProcessed = cohortCreated + memberAdded;
            result.Message =
                $"Sync cohort selesai!\n" +
                $"  Cohort baru   : {cohortCreated}\n" +
                $"  Sudah ada     : {cohortExist}\n" +
                $"  Anggota baru  : {memberAdded}\n" +
                $"  Skip (sudah)  : {memberSkip}";

            OnProgress?.Invoke("Sync cohort selesai!", 100);
            _logger.Log(result.Message, LogLevel.Success, jobId);
        }
        catch (Exception ex)
        {
            result.Success = false;
            result.Message = $"Gagal sync cohort: {ex.Message}";
            _logger.Log(result.Message, LogLevel.Error, jobId);
        }

        return result;
    }

    // ─── UPLOAD HASIL UJIAN (Lokal → Master) ──────────────────────────────────

    /// <summary>
    /// Upload semua hasil ujian dari server lokal ke gradebook master.
    /// Quiz lokal harus sudah disync dari master (ada idnumber eujian_quiz_N).
    /// </summary>
    public async Task<SyncResult> UploadResultsToMasterAsync(int localQuizId)
    {
        var result = new SyncResult();
        var jobId  = Guid.NewGuid().ToString()[..8];

        try
        {
            _logger.Log($"[{jobId}] Upload hasil quiz lokal ID {localQuizId} ke master", LogLevel.Info, jobId);

            // 1. Ambil info quiz lokal + master quiz ID
            OnProgress?.Invoke("Mengambil info quiz lokal...", 10);
            QuizMasterIdResponse quizInfo;
            try
            {
                quizInfo = await _localApi.GetQuizMasterIdAsync(localQuizId);
            }
            catch (Exception ex)
            {
                result.Success = false;
                result.Message = $"Gagal ambil info quiz lokal: {ex.Message}. Pastikan plugin lokal sudah diupdate.";
                return result;
            }

            if (!quizInfo.Found || quizInfo.MasterQuizId == 0)
            {
                result.Success = false;
                result.Message = $"Quiz lokal ID {localQuizId} tidak memiliki link ke master. " +
                                 "Hanya quiz yang disync via menu [Q] yang bisa diupload.";
                return result;
            }

            _logger.Log($"[{jobId}] Quiz: '{quizInfo.QuizName}', master ID: {quizInfo.MasterQuizId}", LogLevel.Info, jobId);
            result.Logs.Add($"Quiz       : {quizInfo.QuizName}");
            result.Logs.Add($"Master ID  : {quizInfo.MasterQuizId}");

            // 2. Ambil semua hasil attempt dari lokal
            OnProgress?.Invoke("Mengambil hasil ujian dari lokal...", 30);
            List<QuizAttempt> attempts;
            try
            {
                attempts = await _localApi.GetQuizAttemptsAsync(localQuizId);
            }
            catch (Exception ex)
            {
                result.Success = false;
                result.Message = $"Gagal ambil hasil ujian: {ex.Message}";
                return result;
            }

            if (attempts.Count == 0)
            {
                result.Success = true;
                result.Message = "Tidak ada hasil ujian di server lokal untuk quiz ini.";
                result.Logs.Add("Attempt: 0");
                return result;
            }

            _logger.Log($"[{jobId}] {attempts.Count} attempt ditemukan", LogLevel.Info, jobId);
            result.Logs.Add($"Attempt    : {attempts.Count}");

            // 3. Konversi sumgrades → rawgrade (skala 0 s.d. quiz.grade)
            double sumGradesMax = quizInfo.SumGrades > 0 ? quizInfo.SumGrades : 1;
            double gradeMax     = quizInfo.Grade > 0 ? quizInfo.Grade : 100;

            var items = attempts.Select(a => new QuizResultItem
            {
                Username   = a.Username,
                RawGrade   = Math.Round((a.SumGrades / sumGradesMax) * gradeMax, 4),
                TimeStart  = a.TimeStart,
                TimeFinish = a.TimeFinish
            }).ToList();

            // 4. Kirim ke master
            OnProgress?.Invoke($"Mengirim {items.Count} hasil ke master...", 70);
            ReceiveResultsResponse response;
            try
            {
                response = await _masterApi.ReceiveQuizResultsAsync(
                    quizInfo.MasterQuizId, _config.RoomId, items);
            }
            catch (Exception ex)
            {
                result.Success = false;
                result.Message = $"Gagal kirim ke master: {ex.Message}. " +
                                 "Pastikan plugin master sudah diupdate dan fungsi receive_results aktif.";
                return result;
            }

            _logger.Log($"[{jobId}] Master: {response.Updated}/{response.Total} updated", LogLevel.Success, jobId);

            result.Success = true;
            result.RecordsProcessed = response.Updated;
            result.Message =
                $"Upload hasil ujian selesai!\n" +
                $"  Berhasil update : {response.Updated}\n" +
                $"  Dilewati/gagal  : {response.Skipped}\n" +
                $"  Total dikirim   : {response.Total}";

            result.Logs.Add($"Berhasil   : {response.Updated}");
            result.Logs.Add($"Gagal      : {response.Skipped}");
            if (response.Errors.Count > 0)
            {
                result.Logs.Add("Error detail:");
                foreach (var e in response.Errors.Take(5))
                    result.Logs.Add($"  - {e}");
            }

            OnProgress?.Invoke("Upload selesai!", 100);
        }
        catch (Exception ex)
        {
            result.Success = false;
            result.Message = $"Error upload: {ex.Message}";
            _logger.Log(result.Message, LogLevel.Error, jobId);
        }

        return result;
    }

    /// <summary>
    /// Sync satu quiz (beserta soal) dari master ke lokal.
    /// Kursus harus sudah disync terlebih dahulu.
    /// </summary>
    public async Task<SyncResult> SyncQuizFromMasterAsync(int masterCourseId, int masterQuizId)
    {
        var result = new SyncResult();
        var jobId  = Guid.NewGuid().ToString()[..8];

        try
        {
            _logger.Log($"[{jobId}] Sync quiz ID {masterQuizId} dari kursus master {masterCourseId}", LogLevel.Info, jobId);

            // 1. Cari kursus lokal berdasarkan idnumber = masterCourseId
            OnProgress?.Invoke("Mencari kursus lokal...", 10);
            var localCourse = await _localApi.GetCourseByIdnumber(masterCourseId.ToString());
            if (localCourse == null)
            {
                result.Success = false;
                result.Message = $"Kursus master ID {masterCourseId} belum disync ke lokal. Sync kursus dulu!";
                return result;
            }
            _logger.Log($"[{jobId}] Kursus lokal ditemukan: [{localCourse.Id}] {localCourse.Fullname}", LogLevel.Info, jobId);

            // 2. Export quiz dari master
            OnProgress?.Invoke($"Mengekspor quiz dari master...", 30);
            QuizExportResponse export;
            try
            {
                export = await _masterApi.ExportQuizAsync(masterQuizId);
            }
            catch (Exception ex)
            {
                result.Success = false;
                result.Message = $"Gagal export quiz dari master: {ex.Message}. Pastikan plugin local_eujian sudah diupdate di master.";
                return result;
            }

            if (string.IsNullOrEmpty(export.ExportJson))
            {
                result.Success = false;
                result.Message = "Export quiz gagal: data JSON kosong";
                return result;
            }

            _logger.Log($"[{jobId}] Export OK: {export.QuestionCount} soal", LogLevel.Info, jobId);
            result.Logs.Add($"Jumlah soal diekspor: {export.QuestionCount}");

            // 3. Import quiz ke lokal
            OnProgress?.Invoke($"Mengimport quiz ({export.QuestionCount} soal) ke lokal...", 70);
            QuizImportResponse import;
            try
            {
                import = await _localApi.ImportQuizAsync(localCourse.Id, masterQuizId, export.ExportJson);
            }
            catch (Exception ex)
            {
                result.Success = false;
                result.Message = $"Gagal import quiz ke lokal: {ex.Message}. Pastikan plugin local_eujian sudah diupdate di lokal.";
                return result;
            }

            _logger.Log($"[{jobId}] Import: {import.Status} — {import.Message}", LogLevel.Success, jobId);

            result.Success = true;
            result.Message = import.Message;
            result.RecordsProcessed = import.QuestionCount;
            result.Logs.Add($"Status     : {import.Status}");
            result.Logs.Add($"Quiz lokal : ID {import.QuizId}");
            result.Logs.Add($"Soal       : {import.QuestionCount}");

            OnProgress?.Invoke("Sync quiz selesai!", 100);
        }
        catch (Exception ex)
        {
            result.Success = false;
            result.Message = $"Error sync quiz: {ex.Message}";
            _logger.Log(result.Message, LogLevel.Error, jobId);
        }

        return result;
    }
}
