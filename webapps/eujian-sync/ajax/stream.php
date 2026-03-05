<?php
/** ajax/stream.php - SSE endpoint untuk semua operasi sync */

// Izinkan eksekusi panjang (sync banyak siswa bisa 2-5 menit)
set_time_limit(0);
ignore_user_abort(false);

// Konfigurasi SSE
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('X-Accel-Buffering: no');
header('Connection: keep-alive');
if (ob_get_level()) ob_end_flush();

require_once __DIR__ . '/../lib/Config.php';
require_once __DIR__ . '/../lib/MoodleApi.php';
require_once __DIR__ . '/../lib/SyncService.php';

$config = new Config();
$svc    = new SyncService($config);

function sse(string $event, mixed $data): void {
    $payload = is_string($data) ? $data : json_encode($data, JSON_UNESCAPED_UNICODE);
    echo "event: $event\ndata: $payload\n\n";
    if (ob_get_level()) ob_flush();
    flush();
}

// Kirim heartbeat agar koneksi SSE tidak timeout
function heartbeat(): void {
    echo ": heartbeat\n\n";
    if (ob_get_level()) ob_flush();
    flush();
}

$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'sync_course':
            $courseId = (int)($_GET['courseid'] ?? 0);
            if (!$courseId) throw new RuntimeException("courseid diperlukan.");
            foreach ($svc->syncCourse($courseId) as $p) {
                sse(isset($p['done']) ? 'done' : 'progress', $p);
                heartbeat();
            }
            break;

        case 'sync_all':
            foreach ($svc->syncAllUsers() as $p) {
                sse(isset($p['done']) ? 'done' : 'progress', $p);
                heartbeat();
            }
            break;

        case 'sync_cohort':
            foreach ($svc->syncCohorts() as $p) {
                sse(isset($p['done']) ? 'done' : 'progress', $p);
                heartbeat();
            }
            break;

        case 'sync_quiz':
            $quizId    = (int)($_GET['quizid']        ?? 0);
            $courseId  = (int)($_GET['local_courseid'] ?? $_GET['courseid'] ?? 0);
            if (!$quizId || !$courseId) throw new RuntimeException("quizid dan courseid diperlukan.");
            foreach ($svc->syncQuiz($quizId, $courseId) as $p) {
                sse(isset($p['done']) ? 'done' : 'progress', $p);
                heartbeat();
            }
            break;

        case 'upload':
            $quizId = (int)($_GET['quizid'] ?? 0);
            if (!$quizId) throw new RuntimeException("quizid diperlukan.");
            foreach ($svc->uploadResults($quizId) as $p) {
                sse(isset($p['done']) ? 'done' : 'progress', $p);
                heartbeat();
            }
            break;

        default:
            throw new RuntimeException("Action tidak dikenali: $action");
    }
} catch (Exception $e) {
    sse('error_msg', $e->getMessage());
}
