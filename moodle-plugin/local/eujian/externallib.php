<?php
// E-UJIAN External Library
// Implementasi semua fungsi Web Service

defined('MOODLE_INTERNAL') || die();

require_once("$CFG->libdir/externallib.php");
require_once("$CFG->dirroot/mod/quiz/lib.php");

class local_eujian_external extends external_api {

    // ═══════════════════════════════════════════════════════════
    // 1. PING
    // ═══════════════════════════════════════════════════════════

    public static function ping_parameters() {
        return new external_function_parameters([]);
    }

    public static function ping() {
        global $CFG, $DB;
        return [
            'status'  => 'ok',
            'site'    => $CFG->wwwroot,
            'version' => $CFG->version,
            'time'    => time(),
        ];
    }

    public static function ping_returns() {
        return new external_single_structure([
            'status'  => new external_value(PARAM_TEXT, 'Status'),
            'site'    => new external_value(PARAM_URL,  'URL situs'),
            'version' => new external_value(PARAM_TEXT, 'Versi Moodle'),
            'time'    => new external_value(PARAM_INT,  'Timestamp server'),
        ]);
    }

    // ═══════════════════════════════════════════════════════════
    // 2. GET STUDENTS
    // ═══════════════════════════════════════════════════════════

    public static function get_students_parameters() {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'ID kursus'),
        ]);
    }

    public static function get_students($courseid) {
        global $DB;

        // Validasi parameter
        $params = self::validate_parameters(
            self::get_students_parameters(),
            ['courseid' => $courseid]
        );

        // Validasi akses context
        $context = context_course::instance($params['courseid']);
        self::validate_context($context);
        require_capability('moodle/course:viewparticipants', $context);

        // Ambil semua user yang enrolled
        $enrolled = get_enrolled_users($context, '', 0, 'u.id, u.username, u.firstname, u.lastname, u.email, u.idnumber');

        $students = [];
        foreach ($enrolled as $user) {
            $students[] = [
                'id'        => (int) $user->id,
                'username'  => $user->username,
                'firstname' => $user->firstname,
                'lastname'  => $user->lastname,
                'fullname'  => fullname($user),
                'email'     => $user->email,
                'idnumber'  => $user->idnumber, // NIS/NISN
            ];
        }

        return [
            'courseid' => $params['courseid'],
            'count'    => count($students),
            'students' => $students,
        ];
    }

    public static function get_students_returns() {
        return new external_single_structure([
            'courseid' => new external_value(PARAM_INT,  'ID kursus'),
            'count'    => new external_value(PARAM_INT,  'Jumlah siswa'),
            'students' => new external_multiple_structure(
                new external_single_structure([
                    'id'        => new external_value(PARAM_INT,  'User ID'),
                    'username'  => new external_value(PARAM_TEXT, 'Username'),
                    'firstname' => new external_value(PARAM_TEXT, 'Nama depan'),
                    'lastname'  => new external_value(PARAM_TEXT, 'Nama belakang'),
                    'fullname'  => new external_value(PARAM_TEXT, 'Nama lengkap'),
                    'email'     => new external_value(PARAM_EMAIL,'Email'),
                    'idnumber'  => new external_value(PARAM_TEXT, 'NIS/NISN', VALUE_OPTIONAL, ''),
                ])
            ),
        ]);
    }

    // ═══════════════════════════════════════════════════════════
    // 3. GET QUIZ DATA
    // ═══════════════════════════════════════════════════════════

    public static function get_quiz_data_parameters() {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'ID kursus'),
        ]);
    }

    public static function get_quiz_data($courseid) {
        global $DB;

        $params = self::validate_parameters(
            self::get_quiz_data_parameters(),
            ['courseid' => $courseid]
        );

        $context = context_course::instance($params['courseid']);
        self::validate_context($context);

        // Ambil semua quiz di kursus ini
        $quizzes_raw = $DB->get_records('quiz', ['course' => $params['courseid']]);

        $quizzes = [];
        foreach ($quizzes_raw as $quiz) {
            $quizzes[] = [
                'id'          => (int) $quiz->id,
                'name'        => $quiz->name,
                'intro'       => strip_tags($quiz->intro ?? ''),
                'timeopen'    => (int) ($quiz->timeopen ?? 0),
                'timeclose'   => (int) ($quiz->timeclose ?? 0),
                'timelimit'   => (int) ($quiz->timelimit ?? 0),
                'attempts'    => (int) ($quiz->attempts ?? 0),  // 0 = unlimited
                'grade'       => (float) ($quiz->grade ?? 0),
                'sumgrades'   => (float) ($quiz->sumgrades ?? 0),
            ];
        }

        return [
            'courseid' => $params['courseid'],
            'count'    => count($quizzes),
            'quizzes'  => $quizzes,
        ];
    }

    public static function get_quiz_data_returns() {
        return new external_single_structure([
            'courseid' => new external_value(PARAM_INT, 'ID kursus'),
            'count'    => new external_value(PARAM_INT, 'Jumlah quiz'),
            'quizzes'  => new external_multiple_structure(
                new external_single_structure([
                    'id'        => new external_value(PARAM_INT,   'Quiz ID'),
                    'name'      => new external_value(PARAM_TEXT,  'Nama quiz'),
                    'intro'     => new external_value(PARAM_TEXT,  'Deskripsi', VALUE_OPTIONAL, ''),
                    'timeopen'  => new external_value(PARAM_INT,   'Waktu buka (unix timestamp)'),
                    'timeclose' => new external_value(PARAM_INT,   'Waktu tutup (unix timestamp)'),
                    'timelimit' => new external_value(PARAM_INT,   'Batas waktu (detik)'),
                    'attempts'  => new external_value(PARAM_INT,   'Max attempt (0=unlimited)'),
                    'grade'     => new external_value(PARAM_FLOAT, 'Nilai maksimum'),
                    'sumgrades' => new external_value(PARAM_FLOAT, 'Total skor soal'),
                ])
            ),
        ]);
    }

    // ═══════════════════════════════════════════════════════════
    // 4. GET QUIZ RESULTS
    // ═══════════════════════════════════════════════════════════


    public static function get_quiz_results_parameters() {
        return new external_function_parameters([
            'quizid' => new external_value(PARAM_INT, 'ID quiz'),
        ]);
    }

    public static function get_quiz_results($quizid) {
        global $DB;

        $params = self::validate_parameters(
            self::get_quiz_results_parameters(),
            ['quizid' => $quizid]
        );

        // Ambil info quiz
        $quiz = $DB->get_record('quiz', ['id' => $params['quizid']], '*', MUST_EXIST);
        $cm   = get_coursemodule_from_instance('quiz', $quiz->id, $quiz->course, false, MUST_EXIST);
        $context = context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/quiz:viewreports', $context);

        // Ambil semua attempt yang selesai
        $attempts_raw = $DB->get_records_sql(
            "SELECT qa.*, u.username, u.firstname, u.lastname, u.idnumber
             FROM {quiz_attempts} qa
             JOIN {user} u ON u.id = qa.userid
             WHERE qa.quiz = :quizid AND qa.state = 'finished'
             ORDER BY u.lastname, u.firstname, qa.attempt",
            ['quizid' => $params['quizid']]
        );

        $attempts = [];
        foreach ($attempts_raw as $a) {
            $attempts[] = [
                'attemptid'  => (int) $a->id,
                'userid'     => (int) $a->userid,
                'username'   => $a->username,
                'fullname'   => $a->firstname . ' ' . $a->lastname,
                'idnumber'   => $a->idnumber,
                'attempt'    => (int) $a->attempt,
                'sumgrades'  => round((float) ($a->sumgrades ?? 0), 2),
                'state'      => $a->state,
                'timestart'  => (int) $a->timestart,
                'timefinish' => (int) $a->timefinish,
            ];
        }

        return [
            'quizid'   => $params['quizid'],
            'quizname' => $quiz->name,
            'count'    => count($attempts),
            'attempts' => $attempts,
        ];
    }

    public static function get_quiz_results_returns() {
        return new external_single_structure([
            'quizid'   => new external_value(PARAM_INT,  'Quiz ID'),
            'quizname' => new external_value(PARAM_TEXT, 'Nama quiz'),
            'count'    => new external_value(PARAM_INT,  'Jumlah attempt'),
            'attempts' => new external_multiple_structure(
                new external_single_structure([
                    'attemptid'  => new external_value(PARAM_INT,   'Attempt ID'),
                    'userid'     => new external_value(PARAM_INT,   'User ID'),
                    'username'   => new external_value(PARAM_TEXT,  'Username'),
                    'fullname'   => new external_value(PARAM_TEXT,  'Nama lengkap'),
                    'idnumber'   => new external_value(PARAM_TEXT,  'NIS/NISN', VALUE_OPTIONAL, ''),
                    'attempt'    => new external_value(PARAM_INT,   'Nomor attempt'),
                    'sumgrades'  => new external_value(PARAM_FLOAT, 'Total nilai'),
                    'state'      => new external_value(PARAM_TEXT,  'Status'),
                    'timestart'  => new external_value(PARAM_INT,   'Waktu mulai'),
                    'timefinish' => new external_value(PARAM_INT,   'Waktu selesai'),
                ])
            ),
        ]);
    }

    // ═══════════════════════════════════════════════════════════
    // 5. EXPORT QUIZ (soal lengkap) — dijalankan di master
    // Mendukung: multichoice, truefalse, shortanswer
    // ═══════════════════════════════════════════════════════════

    public static function export_quiz_parameters() {
        return new external_function_parameters([
            'quizid' => new external_value(PARAM_INT, 'ID quiz di master'),
        ]);
    }

    public static function export_quiz($quizid) {
        global $DB;

        $params = self::validate_parameters(
            self::export_quiz_parameters(),
            ['quizid' => $quizid]
        );

        $quiz = $DB->get_record('quiz', ['id' => $params['quizid']], '*', MUST_EXIST);

        // Gunakan course context (lebih kompatibel dengan token web service)
        $context = context_course::instance($quiz->course);
        self::validate_context($context);
        require_capability('moodle/course:manageactivities', $context);

        // File storage instance untuk ambil gambar
        $fs = get_file_storage();

        // Ambil slots beserta question lewat question_references (Moodle 4.x+)
        $slots = $DB->get_records('quiz_slots', ['quizid' => $params['quizid']], 'slot ASC');

        $questions = [];
        foreach ($slots as $slot) {
            $qref = $DB->get_record('question_references', [
                'component'    => 'mod_quiz',
                'questionarea' => 'slot',
                'itemid'       => $slot->id,
            ]);
            if (!$qref) continue;

            // Ambil versi terbaru question
            $qv = $DB->get_record_sql(
                "SELECT qv.version, qv.questionid, q.qtype, q.name, q.questiontext,
                        q.questiontextformat, q.defaultmark, q.penalty
                   FROM {question_versions} qv
                   JOIN {question} q ON q.id = qv.questionid
                  WHERE qv.questionbankentryid = :qbeid
                  ORDER BY qv.version DESC
                  LIMIT 1",
                ['qbeid' => $qref->questionbankentryid]
            );
            if (!$qv) continue;

            $supported = ['multichoice', 'truefalse', 'shortanswer'];
            if (!in_array($qv->qtype, $supported)) continue;

            $qdata = [
                'slot'        => (int) $slot->slot,
                'page'        => (int) $slot->page,
                'maxmark'     => (float) $slot->maxmark,
                'type'        => $qv->qtype,
                'name'        => $qv->name,
                'text'        => $qv->questiontext,
                'textformat'  => (int) $qv->questiontextformat,
                'defaultmark' => (float) $qv->defaultmark,
                'penalty'     => (float) $qv->penalty,
                'answers'     => [],
                'files'       => [],
            ];

            // ── Ambil file/gambar yang melekat pada questiontext ──
            // Context question ada di question category context
            $qcatctx = $DB->get_record('question_categories', ['id' =>
                $DB->get_field('question_bank_entries', 'questioncategoryid',
                    ['id' => $qref->questionbankentryid])
            ]);
            if ($qcatctx) {
                $files = $fs->get_area_files($qcatctx->contextid, 'question', 'questiontext', $qv->questionid, 'id', false);
                foreach ($files as $file) {
                    $qdata['files'][] = [
                        'filename'  => $file->get_filename(),
                        'mimetype'  => $file->get_mimetype(),
                        'filearea'  => 'questiontext',
                        'content'   => base64_encode($file->get_content()),
                    ];
                }
            }

            // Ambil jawaban
            $answers = $DB->get_records('question_answers',
                ['question' => $qv->questionid], 'id ASC',
                'id, answer, answerformat, fraction, feedback, feedbackformat');
            foreach ($answers as $ans) {
                $ansdata = [
                    'text'     => $ans->answer,
                    'format'   => (int) $ans->answerformat,
                    'fraction' => (float) $ans->fraction,
                    'feedback' => $ans->feedback ?? '',
                    'files'    => [],
                ];
                // File pada jawaban (answer area)
                if ($qcatctx) {
                    $ansfiles = $fs->get_area_files($qcatctx->contextid, 'question', 'answer', $ans->id, 'id', false);
                    foreach ($ansfiles as $file) {
                        $ansdata['files'][] = [
                            'filename'  => $file->get_filename(),
                            'mimetype'  => $file->get_mimetype(),
                            'filearea'  => 'answer',
                            'content'   => base64_encode($file->get_content()),
                        ];
                    }
                }
                $qdata['answers'][] = $ansdata;
            }

            // Opsi tambahan multichoice
            if ($qv->qtype === 'multichoice') {
                $opts = $DB->get_record('qtype_multichoice_options', ['questionid' => $qv->questionid]);
                if ($opts) {
                    $qdata['single']         = (int) $opts->single;
                    $qdata['shuffleanswers'] = (int) $opts->shuffleanswers;
                    $qdata['answernumbering'] = $opts->answernumbering;
                } else {
                    $qdata['single']         = 1;
                    $qdata['shuffleanswers'] = 1;
                    $qdata['answernumbering'] = 'abc';
                }
            }

            $questions[] = $qdata;
        }

        $export = [
            'quiz' => [
                'id'               => (int) $quiz->id,
                'name'             => $quiz->name,
                'intro'            => $quiz->intro ?? '',
                'introformat'      => (int) ($quiz->introformat ?? 1),
                'timelimit'        => (int) ($quiz->timelimit ?? 0),
                'timeopen'         => (int) ($quiz->timeopen ?? 0),
                'timeclose'        => (int) ($quiz->timeclose ?? 0),
                'attempts'         => (int) ($quiz->attempts ?? 1),
                'grade'            => (float) ($quiz->grade ?? 10),
                'sumgrades'        => (float) ($quiz->sumgrades ?? 0),
                'shuffleanswers'   => (int) ($quiz->shuffleanswers ?? 1),
                'preferredbehaviour' => $quiz->preferredbehaviour ?? 'deferredfeedback',
            ],
            'questions' => $questions,
        ];

        return [
            'quizid'        => (int) $params['quizid'],
            'questioncount' => count($questions),
            'exportjson'    => json_encode($export, JSON_UNESCAPED_UNICODE),
        ];
    }

    public static function export_quiz_returns() {
        return new external_single_structure([
            'quizid'        => new external_value(PARAM_INT,  'Quiz ID di master'),
            'questioncount' => new external_value(PARAM_INT,  'Jumlah soal yang diekspor'),
            'exportjson'    => new external_value(PARAM_RAW,  'Data quiz dalam format JSON'),
        ]);
    }

    // ═══════════════════════════════════════════════════════════
    // 6. IMPORT QUIZ (dari JSON) — dijalankan di lokal
    // ═══════════════════════════════════════════════════════════

    public static function import_quiz_parameters() {
        return new external_function_parameters([
            'courseid'     => new external_value(PARAM_INT, 'ID kursus lokal'),
            'masterquizid' => new external_value(PARAM_INT, 'Quiz ID di master (untuk cek duplikat)'),
            'quizjson'     => new external_value(PARAM_RAW, 'JSON dari export_quiz'),
        ]);
    }

    public static function import_quiz($courseid, $masterquizid, $quizjson) {
        global $DB, $USER, $CFG;

        require_once($CFG->dirroot . '/course/lib.php');
        require_once($CFG->libdir  . '/questionlib.php');

        $params = self::validate_parameters(
            self::import_quiz_parameters(),
            ['courseid' => $courseid, 'masterquizid' => $masterquizid, 'quizjson' => $quizjson]
        );

        $coursecontext = context_course::instance($params['courseid']);
        self::validate_context($coursecontext);
        require_capability('moodle/course:manageactivities', $coursecontext);

        $data = json_decode($params['quizjson'], true);
        if (!$data || !isset($data['quiz']) || !isset($data['questions'])) {
            throw new moodle_exception('invaliddata', 'local_eujian', '', 'JSON tidak valid');
        }

        $qdata       = $data['quiz'];
        $questionsdata = $data['questions'];
        $idn         = 'eujian_quiz_' . $params['masterquizid'];

        // Cek duplikat lewat idnumber course_module
        $existing = $DB->get_record_sql(
            "SELECT cm.id, cm.instance
               FROM {course_modules} cm
               JOIN {modules} m ON m.id = cm.module AND m.name = 'quiz'
              WHERE cm.course = :cid AND cm.idnumber = :idn",
            ['cid' => $params['courseid'], 'idn' => $idn]
        );
        if ($existing) {
            return [
                'quizid'        => (int) $existing->instance,
                'questioncount' => 0,
                'status'        => 'skipped',
                'message'       => 'Quiz sudah ada (ID lokal: ' . $existing->instance . ')',
            ];
        }

        // ── 1. Buat question category ─────────────────────────
        $topcat = question_get_top_category($coursecontext->id, true);
        $cat = new stdClass();
        $cat->name        = 'E-UJIAN: ' . $qdata['name'];
        $cat->contextid   = $coursecontext->id;
        $cat->info        = '';
        $cat->infoformat  = 0;
        $cat->stamp       = make_unique_id_code();
        $cat->parent      = $topcat->id;
        $cat->sortorder   = 999;
        $categoryid       = $DB->insert_record('question_categories', $cat);

        // ── 2. Buat questions ─────────────────────────────────
        $slotEntries = []; // slot_number => ['qbeid' => ..., 'maxmark' => ..., 'page' => ...]
        $sumgrades   = 0.0;

        foreach ($questionsdata as $qd) {
            // question_bank_entries
            $qbe = new stdClass();
            $qbe->questioncategoryid = $categoryid;
            $qbe->idnumber           = null;
            $qbe->ownerid            = $USER->id;
            $qbeid = $DB->insert_record('question_bank_entries', $qbe);

            // question
            $q = new stdClass();
            $q->parent              = 0;
            $q->name                = $qd['name'];
            $q->questiontext        = $qd['text'];
            $q->questiontextformat  = $qd['textformat'] ?? 1;
            $q->generalfeedback     = '';
            $q->generalfeedbackformat = 1;
            $q->defaultmark         = $qd['defaultmark'];
            $q->penalty             = $qd['penalty'] ?? 0.3333333;
            $q->qtype               = $qd['type'];
            $q->length              = 1;
            $q->stamp               = make_unique_id_code();
            $q->timecreated         = time();
            $q->timemodified        = time();
            $q->createdby           = $USER->id;
            $q->modifiedby          = $USER->id;
            $qid = $DB->insert_record('question', $q);

            // question_versions
            $qv = new stdClass();
            $qv->questionbankentryid = $qbeid;
            $qv->version             = 1;
            $qv->questionid          = $qid;
            $qv->status              = 'ready';
            $DB->insert_record('question_versions', $qv);

            // question_answers
            $answerIds = [];
            foreach (($qd['answers'] ?? []) as $ans) {
                $answer = new stdClass();
                $answer->question       = $qid;
                $answer->answer         = $ans['text'];
                $answer->answerformat   = $ans['format'] ?? 1;
                $answer->fraction       = $ans['fraction'];
                $answer->feedback       = $ans['feedback'] ?? '';
                $answer->feedbackformat = 1;
                $ansId = $DB->insert_record('question_answers', $answer);
                $answerIds[] = ['id' => $ansId, 'files' => $ans['files'] ?? []];
            }

            // ── Restore file/gambar dari base64 ──
            $fs = get_file_storage();

            // File pada questiontext
            foreach (($qd['files'] ?? []) as $fdata) {
                $fileinfo = [
                    'contextid' => $coursecontext->id,
                    'component' => 'question',
                    'filearea'  => 'questiontext',
                    'itemid'    => $qid,
                    'filepath'  => '/',
                    'filename'  => $fdata['filename'],
                ];
                $content = base64_decode($fdata['content']);
                if ($content !== false && !$fs->file_exists($fileinfo['contextid'], $fileinfo['component'],
                    $fileinfo['filearea'], $fileinfo['itemid'], $fileinfo['filepath'], $fileinfo['filename'])) {
                    $fs->create_file_from_string($fileinfo, $content);
                }
            }

            // File pada jawaban
            foreach ($answerIds as $adata) {
                foreach ($adata['files'] as $fdata) {
                    $fileinfo = [
                        'contextid' => $coursecontext->id,
                        'component' => 'question',
                        'filearea'  => 'answer',
                        'itemid'    => $adata['id'],
                        'filepath'  => '/',
                        'filename'  => $fdata['filename'],
                    ];
                    $content = base64_decode($fdata['content']);
                    if ($content !== false && !$fs->file_exists($fileinfo['contextid'], $fileinfo['component'],
                        $fileinfo['filearea'], $fileinfo['itemid'], $fileinfo['filepath'], $fileinfo['filename'])) {
                        $fs->create_file_from_string($fileinfo, $content);
                    }
                }
            }

            // opsi type-specific
            if ($qd['type'] === 'multichoice') {
                $opts = new stdClass();
                $opts->questionid                     = $qid;
                $opts->layout                         = 0;
                $opts->single                         = $qd['single'] ?? 1;
                $opts->shuffleanswers                 = $qd['shuffleanswers'] ?? 1;
                $opts->correctfeedback                = '';
                $opts->correctfeedbackformat          = 1;
                $opts->partiallycorrectfeedback       = '';
                $opts->partiallycorrectfeedbackformat = 1;
                $opts->incorrectfeedback              = '';
                $opts->incorrectfeedbackformat        = 1;
                $opts->answernumbering                = $qd['answernumbering'] ?? 'abc';
                $opts->shownumcorrect                 = 0;
                $opts->showstandardinstruction        = 1;
                $DB->insert_record('qtype_multichoice_options', $opts);
            } else if ($qd['type'] === 'shortanswer') {
                $opts = new stdClass();
                $opts->questionid = $qid;
                $opts->usecase    = 0;
                $DB->insert_record('qtype_shortanswer_options', $opts);
            }
            // truefalse tidak butuh tabel opsi tambahan

            $slotEntries[$qd['slot']] = [
                'qbeid'   => $qbeid,
                'maxmark' => $qd['maxmark'],
                'page'    => $qd['page'] ?? 1,
            ];
            $sumgrades += $qd['maxmark'];
        }

        // ── 3. Buat quiz ──────────────────────────────────────
        $quizrec = new stdClass();
        $quizrec->course             = $params['courseid'];
        $quizrec->name               = $qdata['name'];
        $quizrec->intro              = $qdata['intro'] ?? '';
        $quizrec->introformat        = $qdata['introformat'] ?? 1;
        $quizrec->timelimit          = $qdata['timelimit'] ?? 0;
        $quizrec->timeopen           = $qdata['timeopen'] ?? 0;
        $quizrec->timeclose          = $qdata['timeclose'] ?? 0;
        $quizrec->attempts           = $qdata['attempts'] ?? 1;
        $quizrec->grade              = $qdata['grade'] ?? 10;
        $quizrec->sumgrades          = $sumgrades;
        $quizrec->shuffleanswers     = $qdata['shuffleanswers'] ?? 1;
        $quizrec->preferredbehaviour = $qdata['preferredbehaviour'] ?? 'deferredfeedback';
        $quizrec->questionsperpage   = 0;
        $quizrec->navmethod          = 'free';
        $quizrec->grademethod        = 1;
        $quizrec->reviewattempt      = 69904;
        $quizrec->reviewcorrectness  = 4368;
        $quizrec->reviewmarks        = 4368;
        $quizrec->reviewspecificfeedback  = 4368;
        $quizrec->reviewgeneralfeedback   = 4368;
        $quizrec->reviewrightanswer       = 4368;
        $quizrec->reviewoverallfeedback   = 4352;
        $quizrec->timecreated        = time();
        $quizrec->timemodified       = time();
        $quizid = $DB->insert_record('quiz', $quizrec);

        // ── 4. Buat course_module ─────────────────────────────
        $module = $DB->get_record('modules', ['name' => 'quiz'], '*', MUST_EXIST);
        $section = $DB->get_record('course_sections',
            ['course' => $params['courseid'], 'section' => 0]);
        if (!$section) {
            $sec = new stdClass();
            $sec->course   = $params['courseid'];
            $sec->section  = 0;
            $sec->sequence = '';
            $sec->visible  = 1;
            $sec->id = $DB->insert_record('course_sections', $sec);
            $section = $sec;
        }

        $cm = new stdClass();
        $cm->course             = $params['courseid'];
        $cm->module             = $module->id;
        $cm->instance           = $quizid;
        $cm->section            = $section->id;
        $cm->idnumber           = $idn;
        $cm->visible            = 1;
        $cm->visibleoncoursepage = 1;
        $cm->visibleold         = 1;
        $cm->groupmode          = 0;
        $cm->groupingid         = 0;
        $cm->completion         = 0;
        $cm->indent             = 0;
        $cm->added              = time();
        $cmid = $DB->insert_record('course_modules', $cm);

        // Tambahkan ke sequence section
        $seq = !empty($section->sequence) ? $section->sequence . ',' . $cmid : (string)$cmid;
        $DB->set_field('course_sections', 'sequence', $seq, ['id' => $section->id]);

        // Buat context untuk module baru
        context_module::instance($cmid);
        $quizctx = context_module::instance($cmid);

        // ── 5. Buat quiz_sections (minimal 1) ────────────────
        $quizsec = new stdClass();
        $quizsec->quizid          = $quizid;
        $quizsec->firstslot       = 1;
        $quizsec->heading         = '';
        $quizsec->shufflequestions = 0;
        $DB->insert_record('quiz_sections', $quizsec);

        // ── 6. Buat quiz_slots + question_references ──────────
        foreach ($slotEntries as $slotnum => $info) {
            $slot = new stdClass();
            $slot->slot            = $slotnum;
            $slot->quizid          = $quizid;
            $slot->page            = $info['page'];
            $slot->requireprevious = 0;
            $slot->maxmark         = $info['maxmark'];
            $slotid = $DB->insert_record('quiz_slots', $slot);

            $qref = new stdClass();
            $qref->usingcontextid      = $quizctx->id;
            $qref->component           = 'mod_quiz';
            $qref->questionarea        = 'slot';
            $qref->itemid              = $slotid;
            $qref->questionbankentryid = $info['qbeid'];
            $qref->version             = null;
            $DB->insert_record('question_references', $qref);
        }

        rebuild_course_cache($params['courseid'], true);

        return [
            'quizid'        => (int) $quizid,
            'questioncount' => count($questionsdata),
            'status'        => 'created',
            'message'       => 'Quiz berhasil diimport: ' . $qdata['name'],
        ];
    }

    public static function import_quiz_returns() {
        return new external_single_structure([
            'quizid'        => new external_value(PARAM_INT,  'ID quiz lokal yang baru dibuat'),
            'questioncount' => new external_value(PARAM_INT,  'Jumlah soal yang diimport'),
            'status'        => new external_value(PARAM_TEXT, 'Status: created / skipped'),
            'message'       => new external_value(PARAM_TEXT, 'Pesan hasil'),
        ]);
    }

    // ═══════════════════════════════════════════════════════════
    // 7. GET QUIZ MASTER ID — dijalankan di lokal
    // Ambil master quiz ID dari course_module.idnumber
    // (disimpan saat import_quiz sebagai 'eujian_quiz_N')
    // ═══════════════════════════════════════════════════════════

    public static function get_quiz_masterid_parameters() {
        return new external_function_parameters([
            'localquizid' => new external_value(PARAM_INT, 'Quiz ID di server lokal'),
        ]);
    }

    public static function get_quiz_masterid($localquizid) {
        global $DB;

        $params = self::validate_parameters(
            self::get_quiz_masterid_parameters(),
            ['localquizid' => $localquizid]
        );

        $quiz = $DB->get_record('quiz', ['id' => $params['localquizid']], '*', MUST_EXIST);

        // Validasi akses course
        $context = context_course::instance($quiz->course);
        self::validate_context($context);

        // Ambil course_module.idnumber yang berformat 'eujian_quiz_N'
        $module = $DB->get_record('modules', ['name' => 'quiz'], '*', MUST_EXIST);
        $cm = $DB->get_record('course_modules', [
            'course'   => $quiz->course,
            'module'   => $module->id,
            'instance' => $quiz->id,
        ]);

        $masterquizid = 0;
        $idn = $cm ? ($cm->idnumber ?? '') : '';
        if (preg_match('/^eujian_quiz_(\d+)$/', $idn, $m)) {
            $masterquizid = (int) $m[1];
        }

        return [
            'localquizid'  => (int) $params['localquizid'],
            'masterquizid' => $masterquizid,
            'quizname'     => $quiz->name,
            'sumgrades'    => (float) ($quiz->sumgrades ?? 0),
            'grade'        => (float) ($quiz->grade ?? 100),
            'found'        => $masterquizid > 0,
        ];
    }

    public static function get_quiz_masterid_returns() {
        return new external_single_structure([
            'localquizid'  => new external_value(PARAM_INT,   'Quiz ID lokal'),
            'masterquizid' => new external_value(PARAM_INT,   'Quiz ID di master (0 jika tidak ada)'),
            'quizname'     => new external_value(PARAM_TEXT,  'Nama quiz'),
            'sumgrades'    => new external_value(PARAM_FLOAT, 'Total skor maksimum lokal'),
            'grade'        => new external_value(PARAM_FLOAT, 'Nilai maksimum (biasanya 100)'),
            'found'        => new external_value(PARAM_BOOL,  'True jika ada link ke master'),
        ]);
    }

    // ═══════════════════════════════════════════════════════════
    // 8. RECEIVE RESULTS — dijalankan di master
    // Terima hasil ujian dari server lokal, update gradebook
    // ═══════════════════════════════════════════════════════════

    public static function receive_results_parameters() {
        return new external_function_parameters([
            'masterquizid' => new external_value(PARAM_INT,  'Quiz ID di master'),
            'roomid'       => new external_value(PARAM_TEXT, 'ID ruangan/kelas', VALUE_OPTIONAL, ''),
            'results'      => new external_multiple_structure(
                new external_single_structure([
                    'username'   => new external_value(PARAM_TEXT,  'Username siswa'),
                    'rawgrade'   => new external_value(PARAM_FLOAT, 'Nilai siswa (skala quiz.grade)'),
                    'timestart'  => new external_value(PARAM_INT,   'Waktu mulai (unix)'),
                    'timefinish' => new external_value(PARAM_INT,   'Waktu selesai (unix)'),
                ])
            ),
        ]);
    }

    public static function receive_results($masterquizid, $roomid, $results) {
        global $DB, $CFG;

        require_once($CFG->libdir . '/gradelib.php');

        $params = self::validate_parameters(
            self::receive_results_parameters(),
            ['masterquizid' => $masterquizid, 'roomid' => $roomid, 'results' => $results]
        );

        $quiz = $DB->get_record('quiz', ['id' => $params['masterquizid']], '*', MUST_EXIST);

        $context = context_course::instance($quiz->course);
        self::validate_context($context);
        require_capability('moodle/grade:edit', $context);

        $updated  = 0;
        $skipped  = 0;
        $errors   = [];

        foreach ($params['results'] as $r) {
            $user = $DB->get_record('user', ['username' => $r['username'], 'deleted' => 0]);
            if (!$user) {
                $errors[] = 'User tidak ditemukan: ' . $r['username'];
                $skipped++;
                continue;
            }

            // grade_update expects rawgrade in the item's grademax scale
            $grade_obj = new stdClass();
            $grade_obj->userid      = $user->id;
            $grade_obj->rawgrade    = (float) $r['rawgrade'];
            $grade_obj->dategraded  = time();
            $grade_obj->datesubmitted = $r['timefinish'] > 0 ? $r['timefinish'] : time();
            $grade_obj->feedback    = 'Upload dari kelas ' . $params['roomid'];
            $grade_obj->feedbackformat = FORMAT_PLAIN;

            $ret = grade_update(
                'mod/quiz',
                $quiz->course,
                'mod',
                'quiz',
                $quiz->id,
                0,
                $grade_obj
            );

            if ($ret == GRADE_UPDATE_OK || $ret == GRADE_UPDATE_MULTIPLE) {
                $updated++;
            } else {
                $errors[] = 'Gagal update grade: ' . $r['username'] . ' (kode: ' . $ret . ')';
                $skipped++;
            }
        }

        return [
            'quizid'     => (int) $params['masterquizid'],
            'quizname'   => $quiz->name,
            'total'      => count($params['results']),
            'updated'    => $updated,
            'skipped'    => $skipped,
            'errors'     => $errors,
        ];
    }

    public static function receive_results_returns() {
        return new external_single_structure([
            'quizid'   => new external_value(PARAM_INT,  'Master quiz ID'),
            'quizname' => new external_value(PARAM_TEXT, 'Nama quiz'),
            'total'    => new external_value(PARAM_INT,  'Total hasil dikirim'),
            'updated'  => new external_value(PARAM_INT,  'Berhasil diupdate'),
            'skipped'  => new external_value(PARAM_INT,  'Dilewati / gagal'),
            'errors'   => new external_multiple_structure(
                new external_value(PARAM_TEXT, 'Pesan error')
            ),
        ]);
    }
}
