<?php
/**
 * MoodleApi.php - Semua REST API call ke Moodle via cURL
 * Mirror dari MoodleApiService.cs
 */
class MoodleApi {
    private string $baseUrl;
    private string $token;
    private int $timeout;

    public function __construct(string $baseUrl, string $token, int $timeout = 30) {
        $this->baseUrl  = rtrim($baseUrl, '/') . '/';
        $this->token    = $token;
        $this->timeout  = $timeout;
    }

    // ─── Core HTTP ─────────────────────────────────────────────────────────────

    public function call(string $function, array $params = []): array {
        $params['wstoken']           = $this->token;
        $params['wsfunction']        = $function;
        $params['moodlewsrestformat'] = 'json';

        $parsed  = parse_url($this->baseUrl);
        $host    = $parsed['host'] ?? 'localhost';
        $port    = $parsed['port'] ?? null;
        $scheme  = $parsed['scheme'] ?? 'http';

        // Jika Moodle redirect (terjadi di Docker karena wwwroot berbeda port dari internal),
        // akses langsung via port 80/443 dengan Host header override
        $callUrl = $this->baseUrl . 'webservice/rest/server.php';
        $headers = [];
        if ($host === 'localhost' || $host === '127.0.0.1') {
            $internalPort = ($scheme === 'https') ? 443 : 80;
            $callUrl = "{$scheme}://127.0.0.1:{$internalPort}/webservice/rest/server.php";
            $headers[] = 'Host: ' . $host . ($port ? ":$port" : '');
        }

        $ch = curl_init($callUrl);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($params),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_HTTPHEADER     => $headers,
        ]);
        $raw  = curl_exec($ch);
        $err  = curl_error($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($raw === false) throw new RuntimeException("cURL error: $err");
        if ($code !== 200)  throw new RuntimeException("HTTP $code dari {$this->baseUrl}");

        $data = json_decode($raw, true);
        if (json_last_error() !== JSON_ERROR_NONE) throw new RuntimeException("JSON decode error: " . json_last_error_msg());
        if (isset($data['exception'])) throw new RuntimeException($data['message'] ?? $data['exception']);

        return $data ?? [];
    }

    // ─── Ping / Test Koneksi ───────────────────────────────────────────────────

    public function ping(): array {
        try {
            $r = $this->call('local_eujian_ping');
            return ['ok' => true, 'version' => $r['version'] ?? '?', 'site' => $r['sitename'] ?? '?', 'message' => $r['message'] ?? ''];
        } catch (Exception $e) {
            // Fallback ke core_webservice_get_site_info
            try {
                $r = $this->call('core_webservice_get_site_info');
                return ['ok' => true, 'version' => $r['release'] ?? '?', 'site' => $r['sitename'] ?? '?', 'message' => 'OK (core)'];
            } catch (Exception $e2) {
                return ['ok' => false, 'error' => $e2->getMessage()];
            }
        }
    }

    // ─── Kursus ────────────────────────────────────────────────────────────────

    public function getCourses(): array {
        $r = $this->call('core_course_get_courses');
        return array_values(array_filter($r, fn($c) => ($c['id'] ?? 0) > 1));
    }

    public function getCourseById(int $courseId): array {
        $r = $this->call('core_course_get_courses', ['options[ids][0]' => $courseId]);
        return $r[0] ?? [];
    }

    public function getCategories(): array {
        try {
            return $this->call('core_course_get_categories') ?: [];
        } catch (Exception) { return []; }
    }

    public function createCategory(string $name, int $parentId = 0): ?int {
        try {
            $r = $this->call('core_course_create_categories', [
                'categories[0][name]'     => $name,
                'categories[0][parent]'   => $parentId,
                'categories[0][idnumber]' => '',
            ]);
            return (int)($r[0]['id'] ?? 0) ?: null;
        } catch (Exception) { return null; }
    }

    // ─── Siswa / User ─────────────────────────────────────────────────────────

    public function getEnrolledUsers(int $courseId): array {
        return $this->call('core_enrol_get_enrolled_users', ['courseid' => $courseId]) ?: [];
    }

    public function createUsers(array $users): array {
        $params = [];
        foreach ($users as $i => $u) {
            foreach ($u as $k => $v) {
                $params["users[$i][$k]"] = $v;
            }
        }
        return $this->call('core_user_create_users', $params) ?: [];
    }

    public function getUserByUsername(string $username): ?array {
        try {
            $r = $this->call('core_user_get_users', [
                'criteria[0][key]'   => 'username',
                'criteria[0][value]' => $username,
            ]);
            return $r['users'][0] ?? null;
        } catch (Exception) { return null; }
    }

    public function enrollUsers(array $enrollments): bool {
        $params = [];
        foreach ($enrollments as $i => $e) {
            foreach ($e as $k => $v) {
                $params["enrolments[$i][$k]"] = $v;
            }
        }
        $this->call('enrol_manual_enrol_users', $params);
        return true;
    }

    // ─── Cohort ────────────────────────────────────────────────────────────────

    public function getCohorts(): array {
        try {
            return $this->call('core_cohort_get_cohorts') ?: [];
        } catch (Exception) { return []; }
    }

    public function createCohort(array $cohort): array {
        // core_cohort_create_cohorts butuh categorytype, bukan contextid langsung
        $params = [
            'cohorts[0][name]'                     => $cohort['name'],
            'cohorts[0][idnumber]'                 => $cohort['idnumber'] ?? '',
            'cohorts[0][description]'              => $cohort['description'] ?? '',
            'cohorts[0][descriptionformat]'        => 1,
            'cohorts[0][categorytype][type]'       => 'system',
            'cohorts[0][categorytype][value]'      => '',
        ];
        return $this->call('core_cohort_create_cohorts', $params) ?: [];
    }

    public function getCohortMembers(array $cohortIds): array {
        // Kembalikan map: cohortId => [userId, userId, ...]
        if (empty($cohortIds)) return [];
        $params = [];
        foreach ($cohortIds as $i => $id) $params["cohortids[$i]"] = $id;
        try {
            $r = $this->call('core_cohort_get_cohort_members', $params);
            $map = [];
            foreach ($r as $item) $map[$item['cohortid']] = $item['userids'] ?? [];
            return $map;
        } catch (Exception) { return []; }
    }

    public function getUsersByIds(array $userIds): array {
        if (empty($userIds)) return [];
        $params = ['field' => 'id'];
        foreach ($userIds as $i => $id) $params["values[$i]"] = $id;
        try {
            return $this->call('core_user_get_users_by_field', $params) ?: [];
        } catch (Exception) { return []; }
    }

    public function addCohortMembers(int $cohortId, array $userIds): bool {
        $params = [];
        foreach ($userIds as $i => $uid) {
            $params["members[$i][cohorttype][type]"]  = 'id';
            $params["members[$i][cohorttype][value]"] = $cohortId;
            $params["members[$i][usertype][type]"]    = 'id';
            $params["members[$i][usertype][value]"]   = $uid;
        }
        try { $this->call('core_cohort_add_cohort_members', $params); return true; }
        catch (Exception) { return false; }
    }

    // ─── Quiz ──────────────────────────────────────────────────────────────────

    public function getQuizzesInCourse(int $courseId): array {
        try {
            $r = $this->call('local_eujian_get_quiz_data', ['courseid' => $courseId]);
            return $r['quizzes'] ?? [];
        } catch (Exception) {
            try {
                $r = $this->call('mod_quiz_get_quizzes_by_courses', ['courseids[0]' => $courseId]);
                return $r['quizzes'] ?? [];
            } catch (Exception) { return []; }
        }
    }

    public function exportQuiz(int $quizId): array {
        return $this->call('local_eujian_export_quiz', ['quizid' => $quizId]);
    }

    public function importQuiz(array $quizData): array {
        $courseId = $quizData['courseid'] ?? 0;
        $masterQuizId = $quizData['quiz']['id'] ?? 0;
        // Remove courseid before encoding — plugin reads it as separate param
        unset($quizData['courseid']);
        $params = [
            'courseid'     => $courseId,
            'masterquizid' => $masterQuizId,
            'quizjson'     => json_encode($quizData, JSON_UNESCAPED_UNICODE),
        ];
        return $this->call('local_eujian_import_quiz', $params);
    }

    // ─── Hasil Ujian ──────────────────────────────────────────────────────────

    public function getQuizResults(int $quizId): array {
        try {
            $r = $this->call('local_eujian_get_quiz_results', ['quizid' => $quizId]);
            return $r['attempts'] ?? [];
        } catch (Exception) { return []; }
    }

    public function getQuizMasterId(int $localQuizId): array {
        return $this->call('local_eujian_get_quiz_masterid', ['localquizid' => $localQuizId]);
    }

    public function receiveResults(int $masterQuizId, array $results): array {
        $params = ['quizid' => $masterQuizId];
        foreach ($results as $i => $r) {
            $params["results[$i][username]"]  = $r['username'];
            $params["results[$i][rawgrade]"]  = $r['rawgrade'];
            $params["results[$i][timestart]"] = $r['timestart'];
            $params["results[$i][timefinish]"] = $r['timefinish'];
        }
        return $this->call('local_eujian_receive_results', $params);
    }
}
