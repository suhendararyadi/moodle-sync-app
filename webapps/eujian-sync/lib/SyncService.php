<?php
/**
 * SyncService.php - Logika sinkronisasi (mirror dari SyncService.cs)
 * Gunakan yield untuk streaming progress ke SSE
 */
class SyncService {
    private MoodleApi $master;
    private MoodleApi $local;
    private Config $config;
    private const BATCH = 50;

    public function __construct(Config $config) {
        $this->config = $config;
        $this->master = new MoodleApi($config->masterUrl(), $config->masterToken(), 60);
        $this->local  = new MoodleApi($config->localUrl(), $config->localToken(), 60);
    }

    // ─── Helper SSE ────────────────────────────────────────────────────────────

    public static function sseEvent(string $type, mixed $data): void {
        $payload = is_string($data) ? $data : json_encode($data, JSON_UNESCAPED_UNICODE);
        echo "event: $type\n";
        echo "data: $payload\n\n";
        if (ob_get_level()) ob_flush();
        flush();
    }

    // ─── Sync Kursus + Siswa ──────────────────────────────────────────────────

    public function syncCourse(int $masterCourseId): \Generator {
        yield ['pct' => 5, 'msg' => "Ambil info kursus dari master..."];

        $course = $this->master->getCourseById($masterCourseId);
        if (empty($course)) throw new RuntimeException("Kursus ID $masterCourseId tidak ditemukan di master.");

        $courseName = $course['fullname'] ?? "Course $masterCourseId";
        yield ['pct' => 10, 'msg' => "Kursus: $courseName"];

        // Resolve nama kategori dari master untuk dibuat di lokal
        $masterCatId   = (int)($course['categoryid'] ?? 1);
        $masterCatName = $this->resolveMasterCategoryName($masterCatId);

        // Buat kursus di lokal jika belum ada
        yield ['pct' => 15, 'msg' => "Periksa kursus di lokal..."];
        $localCourses = $this->local->getCourses();
        $localCourse  = $this->findCourseByShortname($localCourses, $course['shortname'] ?? '');

        if (!$localCourse) {
            yield ['pct' => 20, 'msg' => "Buat kursus baru di lokal (kategori: $masterCatName)..."];

            // Pastikan kategori ada di lokal — buat jika belum ada, fallback ke 1
            $catId = $this->ensureLocalCategory($masterCatId, $masterCatName);

            $created = $this->local->call('core_course_create_courses', [
                'courses[0][fullname]'   => $course['fullname'],
                'courses[0][shortname]'  => $course['shortname'],
                'courses[0][categoryid]' => $catId,
                'courses[0][format]'     => $course['format'] ?? 'topics',
                'courses[0][summary]'    => $course['summary'] ?? '',
            ]);
            $localCourseId = $created[0]['id'] ?? null;
            if (!$localCourseId) throw new RuntimeException("Gagal membuat kursus di lokal.");
        } else {
            $localCourseId = $localCourse['id'];
        }

        yield ['pct' => 25, 'msg' => "Ambil daftar siswa dari master..."];
        $masterUsers = $this->master->getEnrolledUsers($masterCourseId);
        $students    = array_filter($masterUsers, fn($u) => $this->isStudent($u));
        $students    = array_values($students);
        $total       = count($students);

        yield ['pct' => 30, 'msg' => "Total siswa: $total. Mulai sync..."];

        yield from $this->syncUsersToLocal($students, $localCourseId, 30, 85);

        yield ['pct' => 90, 'msg' => "Sync cohort/rombel..."];
        yield from $this->syncCohortsForCourse($masterCourseId, $students);

        yield ['pct' => 100, 'msg' => "Selesai! $total siswa disinkronisasi.", 'done' => true];
    }

    // ─── Sync Semua Siswa ─────────────────────────────────────────────────────

    public function syncAllUsers(): \Generator {
        yield ['pct' => 5, 'msg' => "Ambil semua kursus dari master..."];
        $courses = $this->master->getCourses();
        $total   = count($courses);

        yield ['pct' => 10, 'msg' => "Total kursus: $total"];

        $allCreated = 0;
        foreach ($courses as $idx => $course) {
            $pctBase = 10 + (int)(($idx / $total) * 80);
            yield ['pct' => $pctBase, 'msg' => "Kursus: " . ($course['shortname'] ?? $course['fullname'])];

            $users    = $this->master->getEnrolledUsers($course['id']);
            $students = array_values(array_filter($users, fn($u) => $this->isStudent($u)));

            foreach (array_chunk($students, self::BATCH) as $batch) {
                $toCreate = [];
                foreach ($batch as $u) {
                    $uname = $u['username'] ?? '';
                    if (!$uname) continue;
                    $toCreate[] = [
                        'username'    => $uname,
                        'password'    => 'Eujian@' . $uname,
                        'firstname'   => $u['firstname'] ?? $uname,
                        'lastname'    => $u['lastname'] ?? '-',
                        'email'       => $u['email'] ?? ($uname . '@local.eujian'),
                    ];
                }
                if (!empty($toCreate)) {
                    try {
                        $this->local->createUsers($toCreate);
                        $allCreated += count($toCreate);
                    } catch (Exception) {
                        // Batch gagal — coba satu per satu agar user valid tetap dibuat
                        foreach ($toCreate as $u) {
                            try { $this->local->createUsers([$u]); $allCreated++; } catch (Exception) {}
                        }
                    }
                }
            }
        }
        yield ['pct' => 100, 'msg' => "Selesai! Total ~$allCreated user diproses.", 'done' => true];
    }

    // ─── Sync Cohort (independen, tanpa kursus) ───────────────────────────────

    public function syncCohorts(): \Generator {
        yield ['pct' => 5, 'msg' => "Ambil semua cohort dari master..."];

        $masterCohorts = $this->master->getCohorts();
        if (empty($masterCohorts)) {
            yield ['pct' => 100, 'msg' => "Tidak ada cohort di master.", 'done' => true];
            return;
        }

        $total = count($masterCohorts);
        yield ['pct' => 10, 'msg' => "Total cohort: $total. Ambil anggota..."];

        // Ambil semua anggota cohort sekaligus dari master
        $cohortIds  = array_column($masterCohorts, 'id');
        $membersMap = $this->master->getCohortMembers($cohortIds);

        // Siapkan peta cohort lokal (idnumber/name -> local cohort id)
        $localCohorts = $this->local->getCohorts();
        $localMap     = [];
        foreach ($localCohorts as $c) {
            $key = ($c['idnumber'] !== '') ? $c['idnumber'] : $c['name'];
            $localMap[$key] = $c['id'];
        }

        $synced = 0;
        foreach ($masterCohorts as $idx => $cohort) {
            $pct  = 15 + (int)(($idx / $total) * 80);
            $name = $cohort['name'];
            $key  = ($cohort['idnumber'] !== '') ? $cohort['idnumber'] : $name;

            yield ['pct' => $pct, 'msg' => "Cohort: $name (" . ($idx+1) . "/$total)"];

            // Buat cohort lokal jika belum ada
            if (!isset($localMap[$key])) {
                $created = $this->local->createCohort([
                    'name'        => $name,
                    'idnumber'    => $cohort['idnumber'] ?? '',
                    'description' => $cohort['description'] ?? '',
                ]);
                $localCohortId = $created[0]['id'] ?? null;
                if (!$localCohortId) {
                    yield ['pct' => $pct, 'msg' => "  Gagal buat cohort: $name"];
                    continue;
                }
                $localMap[$key] = $localCohortId;
            } else {
                $localCohortId = $localMap[$key];
            }

            // Ambil anggota cohort dari master, cocokkan ke user lokal
            $masterUserIds = $membersMap[$cohort['id']] ?? [];
            if (empty($masterUserIds)) continue;

            $masterUsers = $this->master->getUsersByIds($masterUserIds);
            $localIds    = [];
            foreach ($masterUsers as $u) {
                $uname = $u['username'] ?? '';
                if (!$uname) continue;
                $localUser = $this->local->getUserByUsername($uname);
                if ($localUser) $localIds[] = (int)$localUser['id'];
            }

            if (!empty($localIds)) {
                $this->local->addCohortMembers($localCohortId, $localIds);
                $synced += count($localIds);
            }
        }

        yield ['pct' => 100, 'msg' => "Selesai! $total cohort disync, $synced anggota ditambahkan.", 'done' => true];
    }

    // ─── Sync Quiz ────────────────────────────────────────────────────────────

    public function syncQuiz(int $masterQuizId, int $localCourseId): \Generator {
        yield ['pct' => 10, 'msg' => "Export quiz dari master (ID: $masterQuizId)..."];
        $raw = $this->master->exportQuiz($masterQuizId);

        // Response format: { quizid, questioncount, exportjson: "{...}" }
        // Parse exportjson string menjadi array
        $exported = isset($raw['exportjson'])
            ? json_decode($raw['exportjson'], true) ?? []
            : $raw;

        $qCount = count($exported['questions'] ?? []);
        yield ['pct' => 40, 'msg' => "Berhasil export $qCount soal. Import ke lokal..."];

        $exported['courseid'] = $localCourseId;
        $result = $this->local->importQuiz($exported);

        $quizId  = $result['quizid'] ?? 0;
        $soal    = $result['questioncount'] ?? 0;
        $status  = $result['status'] ?? '?';

        yield ['pct' => 100, 'msg' => "Selesai! Quiz ID lokal: $quizId, $soal soal ($status).", 
               'done' => true, 'quizid' => $quizId, 'questioncount' => $soal];
    }

    // ─── Upload Hasil Ujian ────────────────────────────────────────────────────

    public function uploadResults(int $localQuizId): \Generator {
        yield ['pct' => 10, 'msg' => "Ambil info quiz lokal (ID: $localQuizId)..."];
        $masterInfo = $this->local->getQuizMasterId($localQuizId);

        if (!($masterInfo['found'] ?? false)) {
            throw new RuntimeException("Quiz ID $localQuizId tidak memiliki master ID. Pastikan quiz ini diimport via Sync Quiz.");
        }

        $masterQuizId = (int)$masterInfo['masterquizid'];
        $quizName     = $masterInfo['quizname'] ?? "Quiz $localQuizId";
        $sumgrades    = (float)($masterInfo['sumgrades'] ?? 0);
        $grade        = (float)($masterInfo['grade'] ?? 100);

        yield ['pct' => 20, 'msg' => "Quiz: $quizName → Master ID: $masterQuizId"];

        yield ['pct' => 30, 'msg' => "Ambil hasil ujian dari lokal..."];
        $attempts = $this->local->getQuizResults($localQuizId);

        if (empty($attempts)) {
            yield ['pct' => 100, 'msg' => "Tidak ada hasil ujian di lokal.", 'done' => true, 'count' => 0];
            return;
        }

        yield ['pct' => 40, 'msg' => count($attempts) . " attempt ditemukan. Kirim ke master..."];

        $results = [];
        foreach ($attempts as $a) {
            $raw = $sumgrades > 0
                ? round(((float)($a['sumgrades'] ?? 0) / $sumgrades) * $grade, 2)
                : 0;
            $results[] = [
                'username'   => $a['username'] ?? $a['userid'],
                'rawgrade'   => $raw,
                'timestart'  => $a['timestart'] ?? 0,
                'timefinish' => $a['timefinish'] ?? 0,
            ];
        }

        $r   = $this->master->receiveResults($masterQuizId, $results);
        $cnt = $r['processed'] ?? count($results);

        yield ['pct' => 100, 'msg' => "Selesai! $cnt nilai dikirim ke master.", 
               'done' => true, 'count' => $cnt, 'results' => $results];
    }

    // ─── Private Helpers ──────────────────────────────────────────────────────

    private function syncUsersToLocal(array $students, int $localCourseId, int $pctStart, int $pctEnd): \Generator {
        $total   = count($students);
        $batches = array_chunk($students, self::BATCH);
        $created = 0;

        foreach ($batches as $bIdx => $batch) {
            $pct = $pctStart + (int)(($bIdx / count($batches)) * ($pctEnd - $pctStart));
            yield ['pct' => $pct, 'msg' => "Batch " . ($bIdx + 1) . "/" . count($batches) . " — buat user..."];

            $toCreate = [];
            foreach ($batch as $u) {
                $uname = $u['username'] ?? '';
                if (!$uname) continue;
                $toCreate[] = [
                    'username'  => $uname,
                    'password'  => 'Eujian@' . $uname,
                    'firstname' => $u['firstname'] ?? $uname,
                    'lastname'  => $u['lastname'] ?? '-',
                    'email'     => $u['email'] ?? ($uname . '@local.eujian'),
                ];
            }

            if (!empty($toCreate)) {
                try {
                    $this->local->createUsers($toCreate);
                    $created += count($toCreate);
                } catch (Exception) {
                    // Batch gagal — coba satu per satu
                    foreach ($toCreate as $u) {
                        try { $this->local->createUsers([$u]); $created++; } catch (Exception) {}
                    }
                }
            }

            // Enroll ke kursus
            $userIds = $this->getLocalUserIds($batch);
            if (!empty($userIds)) {
                $enroll = array_map(fn($id) => ['roleid' => 5, 'userid' => $id, 'courseid' => $localCourseId], $userIds);
                try { $this->local->enrollUsers($enroll); } catch (Exception) {}
            }
        }
        yield ['pct' => $pctEnd, 'msg' => "Total $created user dibuat/diperbarui."];
    }

    private function syncCohortsForCourse(int $masterCourseId, array $students): \Generator {
        $masterCohorts = $this->master->getCohorts();
        $localCohorts  = $this->local->getCohorts();
        $localMap      = [];
        foreach ($localCohorts as $c) $localMap[$c['idnumber'] ?? $c['name']] = $c['id'];

        foreach ($masterCohorts as $cohort) {
            $key = $cohort['idnumber'] ?? $cohort['name'];
            yield ['pct' => 92, 'msg' => "Cohort: " . $cohort['name']];

            if (!isset($localMap[$key])) {
                $created = $this->local->createCohort([
                    'name'        => $cohort['name'],
                    'idnumber'    => $cohort['idnumber'] ?? '',
                    'description' => $cohort['description'] ?? '',
                ]);
                $localCohortId = $created[0]['id'] ?? null;
            } else {
                $localCohortId = $localMap[$key];
            }

            if ($localCohortId) {
                $memberIds = $this->getLocalUserIds($students);
                if (!empty($memberIds)) {
                    $this->local->addCohortMembers($localCohortId, $memberIds);
                }
            }
        }
    }

    private function isStudent(array $user): bool {
        $roles = $user['roles'] ?? [];
        // Kalau tidak ada data role sama sekali, skip (bukan siswa yang valid)
        if (empty($roles)) return false;
        foreach ($roles as $r) {
            if (($r['roleid'] ?? 0) == 5) return true;
        }
        return false;
    }

    private function getLocalUserIds(array $masterUsers): array {
        $usernames = [];
        foreach ($masterUsers as $u) {
            $uname = $u['username'] ?? '';
            if ($uname) $usernames[] = $uname;
        }
        if (empty($usernames)) return [];

        // Batch: ambil semua user lokal sekaligus via core_user_get_users_by_field
        $ids = [];
        foreach (array_chunk($usernames, 50) as $chunk) {
            $params = ['field' => 'username'];
            foreach ($chunk as $i => $uname) $params["values[$i]"] = $uname;
            try {
                $users = $this->local->call('core_user_get_users_by_field', $params);
                foreach ($users as $u) {
                    if (!empty($u['id'])) $ids[] = (int)$u['id'];
                }
            } catch (\Exception) {}
        }
        return $ids;
    }

    private function findCourseByShortname(array $courses, string $shortname): ?array {
        foreach ($courses as $c) {
            if (($c['shortname'] ?? '') === $shortname) return $c;
        }
        return null;
    }

    private array $masterCategoryCache = [];

    private function resolveMasterCategoryName(int $catId): string {
        if (isset($this->masterCategoryCache[$catId])) {
            return $this->masterCategoryCache[$catId];
        }
        $cats = $this->master->getCategories();
        foreach ($cats as $cat) {
            $this->masterCategoryCache[(int)$cat['id']] = $cat['name'];
        }
        return $this->masterCategoryCache[$catId] ?? 'Miscellaneous';
    }

    private array $localCategoryCache = [];

    /**
     * Pastikan kategori ada di lokal. Cari berdasarkan nama, buat jika belum ada, fallback ke ID 1.
     */
    private function ensureLocalCategory(int $masterCatId, ?string $catName = null): int {
        if (isset($this->localCategoryCache[$masterCatId])) {
            return $this->localCategoryCache[$masterCatId];
        }

        // Ambil semua kategori lokal
        $localCats = $this->local->getCategories();

        // Cocokkan berdasarkan nama jika ada
        if ($catName) {
            foreach ($localCats as $cat) {
                if (strtolower(trim($cat['name'])) === strtolower(trim($catName))) {
                    $this->localCategoryCache[$masterCatId] = (int)$cat['id'];
                    return (int)$cat['id'];
                }
            }
            // Buat kategori baru di lokal dengan nama dari master
            $newId = $this->local->createCategory($catName);
            if ($newId) {
                $this->localCategoryCache[$masterCatId] = $newId;
                return $newId;
            }
        }

        // Fallback: gunakan kategori ID 1 (Miscellaneous/default)
        $this->localCategoryCache[$masterCatId] = 1;
        return 1;
    }
}
