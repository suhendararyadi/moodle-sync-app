using MoodleSyncApp.Core.Models;
using Newtonsoft.Json;
using Newtonsoft.Json.Linq;

namespace MoodleSyncApp.Core.Services;

/// <summary>
/// Service untuk komunikasi dengan Moodle REST API
/// </summary>
public class MoodleApiService
{
    private readonly HttpClient _httpClient;
    private string _baseUrl = "";
    private string _token = "";

    public MoodleApiService()
    {
        _httpClient = new HttpClient { Timeout = TimeSpan.FromSeconds(30) };
    }

    public void Configure(string baseUrl, string token)
    {
        _baseUrl = baseUrl.TrimEnd('/');
        _token = token;
    }

    private string ApiEndpoint => $"{_baseUrl}/webservice/rest/server.php";

    private async Task<JToken?> CallAsync(string function, Dictionary<string, string>? parameters = null)
    {
        var formData = new Dictionary<string, string>
        {
            ["wstoken"] = _token,
            ["wsfunction"] = function,
            ["moodlewsrestformat"] = "json"
        };

        if (parameters != null)
            foreach (var kv in parameters)
                formData[kv.Key] = kv.Value;

        var response = await _httpClient.PostAsync(ApiEndpoint, new FormUrlEncodedContent(formData));
        response.EnsureSuccessStatusCode();

        var json = await response.Content.ReadAsStringAsync();
        var result = JToken.Parse(json);

        // Moodle error hanya ada di JObject, bukan JArray
        if (result is JObject obj && obj["exception"] != null)
            throw new Exception($"Moodle API Error: {obj["message"]}");

        return result;
    }

    // ─── USERS ───────────────────────────────────────────────────────────────

    public async Task<List<MoodleUser>> GetAllUsersAsync()
    {
        var result = await CallAsync("core_user_get_users", new()
        {
            ["criteria[0][key]"] = "confirmed",
            ["criteria[0][value]"] = "1"
        });

        return result?["users"]?.ToObject<List<MoodleUser>>() ?? new();
    }

    public async Task<List<MoodleUser>> GetEnrolledUsersAsync(int courseId)
    {
        var result = await CallAsync("core_enrol_get_enrolled_users", new()
        {
            ["courseid"] = courseId.ToString()
        });

        // Response bisa JArray langsung
        if (result is JArray arr)
            return arr.ToObject<List<MoodleUser>>() ?? new();

        return result?["users"]?.ToObject<List<MoodleUser>>() ?? new();
    }

    public async Task<bool> CreateUserAsync(MoodleUser user, string password)
    {
        var result = await CallAsync("core_user_create_users", new()
        {
            ["users[0][username]"]  = user.Username,
            ["users[0][firstname]"] = user.Firstname,
            ["users[0][lastname]"]  = user.Lastname,
            ["users[0][email]"]     = user.Email,
            ["users[0][idnumber]"]  = user.Idnumber,
            ["users[0][password]"]  = password
        });

        return result?[0]?["id"] != null;
    }

    /// <summary>
    /// Buat banyak user sekaligus dalam 1 API call (jauh lebih cepat dari satu per satu).
    /// Mengembalikan list user ID yang berhasil dibuat.
    /// </summary>
    public async Task<List<(string Username, int Id)>> CreateUsersBatchAsync(
        List<(MoodleUser User, string Password)> batch)
    {
        var parameters = new Dictionary<string, string>();
        for (int i = 0; i < batch.Count; i++)
        {
            var (user, pass) = batch[i];
            // Pastikan email unik — gunakan username@domain jika kosong
            var email = string.IsNullOrWhiteSpace(user.Email)
                ? $"{user.Username}@smknegeri9garut.sch.id"
                : user.Email;

            parameters[$"users[{i}][username]"]  = user.Username;
            parameters[$"users[{i}][firstname]"] = user.Firstname;
            parameters[$"users[{i}][lastname]"]  = user.Lastname;
            parameters[$"users[{i}][email]"]     = email;
            parameters[$"users[{i}][idnumber]"]  = user.Idnumber ?? "";
            parameters[$"users[{i}][password]"]  = pass;
        }

        var result = await CallAsync("core_user_create_users", parameters);

        var created = new List<(string, int)>();
        if (result is JArray arr)
        {
            foreach (var item in arr)
            {
                var username = item["username"]?.Value<string>() ?? "";
                var id       = item["id"]?.Value<int>() ?? 0;
                if (id > 0) created.Add((username, id));
            }
        }
        return created;
    }

    // ─── COURSES ─────────────────────────────────────────────────────────────

    /// <summary>
    /// Ambil semua course (mungkin lambat kalau banyak)
    /// </summary>
    public async Task<List<MoodleCourse>> GetCoursesAsync()
    {
        var result = await CallAsync("core_course_get_courses");

        // Moodle bisa return JArray langsung atau JObject dengan key "courses"
        if (result is JArray arr)
            return arr.ToObject<List<MoodleCourse>>() ?? new();

        return result?["courses"]?.ToObject<List<MoodleCourse>>() ?? new();
    }

    /// <summary>
    /// Ambil course by ID spesifik
    /// </summary>
    public async Task<MoodleCourse?> GetCourseByIdAsync(int courseId)
    {
        var result = await CallAsync("core_course_get_courses_by_field", new()
        {
            ["field"] = "id",
            ["value"] = courseId.ToString()
        });

        // Response: { "courses": [...], "warnings": [] }
        var courses = result?["courses"]?.ToObject<List<MoodleCourse>>() ?? new();
        return courses.FirstOrDefault();
    }

    public async Task<MoodleCourse?> GetCourseByShortname(string shortname)
    {
        var result = await CallAsync("core_course_get_courses_by_field", new()
        {
            ["field"] = "shortname",
            ["value"] = shortname
        });

        var courses = result?["courses"]?.ToObject<List<MoodleCourse>>() ?? new();
        return courses.FirstOrDefault();
    }

    public async Task<MoodleCourse?> GetCourseByIdnumber(string idnumber)
    {
        var result = await CallAsync("core_course_get_courses_by_field", new()
        {
            ["field"] = "idnumber",
            ["value"] = idnumber
        });

        var courses = result?["courses"]?.ToObject<List<MoodleCourse>>() ?? new();
        return courses.FirstOrDefault();
    }

    /// <summary>
    /// Buat course baru di server lokal
    /// </summary>
    public async Task<int> CreateCourseAsync(MoodleCourse course)
    {
        var result = await CallAsync("core_course_create_courses", new()
        {
            ["courses[0][fullname]"]  = course.Fullname,
            ["courses[0][shortname]"] = course.Shortname,
            ["courses[0][categoryid]"] = "1",
            ["courses[0][summary]"]  = course.Summary,
            ["courses[0][idnumber]"] = course.Id.ToString(), // simpan ID master sebagai idnumber
            ["courses[0][visible]"]  = "1"
        });

        if (result is JArray arr && arr.Count > 0)
            return arr[0]?["id"]?.Value<int>() ?? 0;
        return 0;
    }

    /// <summary>
    /// Enroll user ke course di server lokal
    /// </summary>
    public async Task<bool> EnrolUserAsync(int userId, int courseId, int roleId = 5) // 5 = student
    {
        var result = await CallAsync("enrol_manual_enrol_users", new()
        {
            ["enrolments[0][roleid]"]   = roleId.ToString(),
            ["enrolments[0][userid]"]   = userId.ToString(),
            ["enrolments[0][courseid]"] = courseId.ToString()
        });
        // enrol_manual_enrol_users returns null on success
        return true;
    }

    /// <summary>
    /// Ambil quiz di kursus via API standar
    /// </summary>
    public async Task<List<MoodleQuiz>> GetQuizzesAsync(int courseId)
    {
        var result = await CallAsync("mod_quiz_get_quizzes_by_courses", new()
        {
            ["courseids[0]"] = courseId.ToString()
        });
        return result?["quizzes"]?.ToObject<List<MoodleQuiz>>() ?? new();
    }



    public async Task<List<QuizAttempt>> GetQuizAttemptsAsync(int quizId)
    {
        // Gunakan plugin E-UJIAN untuk mendapatkan username sekaligus
        try
        {
            var pluginResult = await CallAsync("local_eujian_get_quiz_results", new()
            {
                ["quizid"] = quizId.ToString()
            });

            if (pluginResult?["attempts"] is JArray arr)
            {
                return arr.Select(a => new QuizAttempt
                {
                    Id         = a["attemptid"]?.Value<int>() ?? 0,
                    UserId     = a["userid"]?.Value<int>() ?? 0,
                    Username   = a["username"]?.Value<string>() ?? "",
                    State      = a["state"]?.Value<string>() ?? "",
                    TimeStart  = a["timestart"]?.Value<long>() ?? 0,
                    TimeFinish = a["timefinish"]?.Value<long>() ?? 0,
                    SumGrades  = a["sumgrades"]?.Value<double>() ?? 0
                }).ToList();
            }
        }
        catch { /* fallback ke API standar */ }

        // Fallback: mod_quiz_get_user_attempts (tanpa username)
        var result = await CallAsync("mod_quiz_get_user_attempts", new()
        {
            ["quizid"] = quizId.ToString(),
            ["status"] = "all"
        });
        return result?["attempts"]?.ToObject<List<QuizAttempt>>() ?? new();
    }

    // ─── PING / HEALTH CHECK ──────────────────────────────────────────────────

    public async Task<bool> PingAsync()
    {
        try
        {
            // Coba plugin dulu, fallback ke default Moodle API
            try
            {
                var r = await CallAsync("local_eujian_ping");
                return r?["status"]?.ToString() == "ok";
            }
            catch
            {
                var result = await CallAsync("core_webservice_get_site_info");
                return result?["sitename"] != null;
            }
        }
        catch
        {
            return false;
        }
    }

    public async Task<string> GetSiteNameAsync()
    {
        var result = await CallAsync("core_webservice_get_site_info");
        return result?["sitename"]?.ToString() ?? "Unknown";
    }

    // ─── PLUGIN: E-UJIAN FUNCTIONS ────────────────────────────────────────────

    /// <summary>
    /// Ambil siswa via plugin local_eujian (lebih reliable)
    /// </summary>
    public async Task<List<MoodleUser>> GetStudentsAsync(int courseId)
    {
        var result = await CallAsync("local_eujian_get_students", new()
        {
            ["courseid"] = courseId.ToString()
        });

        var students = result?["students"]?.ToObject<List<MoodleUser>>() ?? new();
        return students;
    }

    /// <summary>
    /// Ambil semua quiz di kursus via plugin
    /// </summary>
    public async Task<List<MoodleCourse>> GetQuizzesInCourseAsync(int courseId)
    {
        var result = await CallAsync("local_eujian_get_quiz_data", new()
        {
            ["courseid"] = courseId.ToString()
        });

        // Map ke MoodleCourse sebagai placeholder quiz list
        if (result?["quizzes"] is JArray arr)
        {
            return arr.Select(q => new MoodleCourse
            {
                Id       = q["id"]?.Value<int>() ?? 0,
                Fullname = q["name"]?.ToString() ?? "",
                Shortname = $"quiz-{q["id"]}"
            }).ToList();
        }
        return new();
    }

    /// <summary>
    /// Ambil hasil quiz via plugin
    /// </summary>
    public async Task<JToken?> GetQuizResultsAsync(int quizId)
    {
        return await CallAsync("local_eujian_get_quiz_results", new()
        {
            ["quizid"] = quizId.ToString()
        });
    }

    // ─── COHORT ──────────────────────────────────────────────────────────────

    /// <summary>
    /// Ambil semua cohort dari sistem
    /// </summary>
    public async Task<List<MoodleCohort>> GetAllCohortsAsync()
    {
        var result = await CallAsync("core_cohort_search_cohorts", new()
        {
            ["context[contextlevel]"] = "system",
            ["context[instanceid]"]   = "0",
            ["query"] = ""
        });
        return result?["cohorts"]?.ToObject<List<MoodleCohort>>() ?? new();
    }

    /// <summary>
    /// Ambil anggota (user IDs) dari satu cohort
    /// </summary>
    public async Task<List<int>> GetCohortMembersAsync(int cohortId)
    {
        var result = await CallAsync("core_cohort_get_cohort_members", new()
        {
            ["cohortids[0]"] = cohortId.ToString()
        });
        if (result is not JArray arr || arr.Count == 0) return new();
        return arr[0]?["userids"]?.ToObject<List<int>>() ?? new();
    }

    /// <summary>
    /// Buat cohort baru di lokal
    /// </summary>
    public async Task<int> CreateCohortAsync(MoodleCohort cohort)
    {
        var result = await CallAsync("core_cohort_create_cohorts", new()
        {
            ["cohorts[0][name]"]                    = cohort.Name,
            ["cohorts[0][idnumber]"]                = cohort.Idnumber,
            ["cohorts[0][description]"]             = cohort.Description ?? "",
            ["cohorts[0][categorytype][type]"]      = "system",
            ["cohorts[0][categorytype][value]"]     = "0"
        });
        if (result is JArray arr && arr.Count > 0)
            return arr[0]?["id"]?.Value<int>() ?? 0;
        return 0;
    }

    /// <summary>
    /// Tambahkan user ke cohort di lokal
    /// </summary>
    public async Task AddCohortMemberAsync(int cohortId, int userId)
    {
        await CallAsync("core_cohort_add_cohort_members", new()
        {
            ["members[0][cohorttype][type]"]  = "id",
            ["members[0][cohorttype][value]"] = cohortId.ToString(),
            ["members[0][usertype][type]"]    = "id",
            ["members[0][usertype][value]"]   = userId.ToString()
        });
    }

    // ─── QUIZ EXPORT / IMPORT ─────────────────────────────────────────────

    /// <summary>
    /// Export quiz beserta semua soal dari server (biasanya master)
    /// Menggunakan local_eujian_export_quiz
    /// </summary>
    public async Task<QuizExportResponse> ExportQuizAsync(int quizId)
    {
        var result = await CallAsync("local_eujian_export_quiz", new()
        {
            ["quizid"] = quizId.ToString()
        });

        if (result is JObject obj)
        {
            return new QuizExportResponse
            {
                QuizId        = obj["quizid"]?.Value<int>() ?? 0,
                QuestionCount = obj["questioncount"]?.Value<int>() ?? 0,
                ExportJson    = obj["exportjson"]?.Value<string>() ?? ""
            };
        }
        throw new Exception("Gagal export quiz: response tidak valid");
    }

    /// <summary>
    /// Import quiz dari JSON ke kursus lokal
    /// Menggunakan local_eujian_import_quiz
    /// </summary>
    public async Task<QuizImportResponse> ImportQuizAsync(int localCourseId, int masterQuizId, string exportJson)
    {
        var result = await CallAsync("local_eujian_import_quiz", new()
        {
            ["courseid"]     = localCourseId.ToString(),
            ["masterquizid"] = masterQuizId.ToString(),
            ["quizjson"]     = exportJson
        });

        if (result is JObject obj)
        {
            return new QuizImportResponse
            {
                QuizId        = obj["quizid"]?.Value<int>() ?? 0,
                QuestionCount = obj["questioncount"]?.Value<int>() ?? 0,
                Status        = obj["status"]?.Value<string>() ?? "",
                Message       = obj["message"]?.Value<string>() ?? ""
            };
        }
        throw new Exception("Gagal import quiz: response tidak valid");
    }

    // ─── UPLOAD HASIL UJIAN ───────────────────────────────────────────────

    /// <summary>
    /// Ambil master quiz ID dari quiz lokal (via idnumber course_module)
    /// Menggunakan local_eujian_get_quiz_masterid (dijalankan di server lokal)
    /// </summary>
    public async Task<QuizMasterIdResponse> GetQuizMasterIdAsync(int localQuizId)
    {
        var result = await CallAsync("local_eujian_get_quiz_masterid", new()
        {
            ["localquizid"] = localQuizId.ToString()
        });

        if (result is JObject obj)
        {
            return new QuizMasterIdResponse
            {
                LocalQuizId  = obj["localquizid"]?.Value<int>() ?? 0,
                MasterQuizId = obj["masterquizid"]?.Value<int>() ?? 0,
                QuizName     = obj["quizname"]?.Value<string>() ?? "",
                SumGrades    = obj["sumgrades"]?.Value<double>() ?? 0,
                Grade        = obj["grade"]?.Value<double>() ?? 100,
                Found        = obj["found"]?.Value<bool>() ?? false
            };
        }
        throw new Exception("Gagal ambil master quiz ID");
    }

    /// <summary>
    /// Kirim hasil ujian ke master untuk update gradebook
    /// Menggunakan local_eujian_receive_results (dijalankan di server master)
    /// </summary>
    public async Task<ReceiveResultsResponse> ReceiveQuizResultsAsync(
        int masterQuizId, string roomId, List<QuizResultItem> results)
    {
        var args = new Dictionary<string, string>
        {
            ["masterquizid"] = masterQuizId.ToString(),
            ["roomid"]       = roomId
        };

        for (int i = 0; i < results.Count; i++)
        {
            args[$"results[{i}][username]"]   = results[i].Username;
            args[$"results[{i}][rawgrade]"]   = results[i].RawGrade.ToString("F4", System.Globalization.CultureInfo.InvariantCulture);
            args[$"results[{i}][timestart]"]  = results[i].TimeStart.ToString();
            args[$"results[{i}][timefinish]"] = results[i].TimeFinish.ToString();
        }

        var result = await CallAsync("local_eujian_receive_results", args);

        if (result is JObject obj)
        {
            return new ReceiveResultsResponse
            {
                QuizId   = obj["quizid"]?.Value<int>() ?? 0,
                QuizName = obj["quizname"]?.Value<string>() ?? "",
                Total    = obj["total"]?.Value<int>() ?? 0,
                Updated  = obj["updated"]?.Value<int>() ?? 0,
                Skipped  = obj["skipped"]?.Value<int>() ?? 0,
                Errors   = obj["errors"] is JArray arr
                           ? arr.Select(e => e.Value<string>() ?? "").ToList()
                           : new List<string>()
            };
        }
        throw new Exception("Gagal mengirim hasil ujian: response tidak valid");
    }
}
