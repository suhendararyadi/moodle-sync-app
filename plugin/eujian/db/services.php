<?php
// Daftarkan Web Service Functions untuk E-UJIAN
defined('MOODLE_INTERNAL') || die();

$functions = [

    // ── 1. Ambil siswa terdaftar di kursus ────────────────────────────────
    'local_eujian_get_students' => [
        'classname'     => 'local_eujian_external',
        'methodname'    => 'get_students',
        'classpath'     => 'local/eujian/externallib.php',
        'description'   => 'Ambil daftar siswa yang terdaftar di kursus',
        'type'          => 'read',
        'capabilities'  => 'moodle/course:viewparticipants',
        'ajax'          => true,
    ],

    // ── 2. Ambil data quiz beserta soal ──────────────────────────────────
    'local_eujian_get_quiz_data' => [
        'classname'     => 'local_eujian_external',
        'methodname'    => 'get_quiz_data',
        'classpath'     => 'local/eujian/externallib.php',
        'description'   => 'Ambil info quiz + daftar soal',
        'type'          => 'read',
        'capabilities'  => 'mod/quiz:viewreports',
        'ajax'          => true,
    ],

    // ── 3. Ambil hasil attempt quiz ──────────────────────────────────────
    'local_eujian_get_quiz_results' => [
        'classname'     => 'local_eujian_external',
        'methodname'    => 'get_quiz_results',
        'classpath'     => 'local/eujian/externallib.php',
        'description'   => 'Ambil semua hasil attempt quiz',
        'type'          => 'read',
        'capabilities'  => 'mod/quiz:viewreports',
        'ajax'          => true,
    ],

    // ── 4. Ping / health check ───────────────────────────────────────────
    'local_eujian_ping' => [
        'classname'     => 'local_eujian_external',
        'methodname'    => 'ping',
        'classpath'     => 'local/eujian/externallib.php',
        'description'   => 'Cek koneksi ke server E-UJIAN',
        'type'          => 'read',
        'capabilities'  => '',
        'ajax'          => true,
    ],

    // ── 5. Export quiz beserta soal (master) ─────────────────────────────
    'local_eujian_export_quiz' => [
        'classname'     => 'local_eujian_external',
        'methodname'    => 'export_quiz',
        'classpath'     => 'local/eujian/externallib.php',
        'description'   => 'Export quiz beserta semua soal dalam format JSON',
        'type'          => 'read',
        'capabilities'  => 'mod/quiz:manage',
        'ajax'          => true,
    ],

    // ── 6. Import quiz dari JSON (lokal) ─────────────────────────────────
    'local_eujian_import_quiz' => [
        'classname'     => 'local_eujian_external',
        'methodname'    => 'import_quiz',
        'classpath'     => 'local/eujian/externallib.php',
        'description'   => 'Import quiz dari JSON hasil export ke kursus lokal',
        'type'          => 'write',
        'capabilities'  => 'moodle/course:manageactivities',
        'ajax'          => true,
    ],

    // ── 7. Ambil master quiz ID dari quiz lokal ───────────────────────────
    'local_eujian_get_quiz_masterid' => [
        'classname'     => 'local_eujian_external',
        'methodname'    => 'get_quiz_masterid',
        'classpath'     => 'local/eujian/externallib.php',
        'description'   => 'Ambil master quiz ID dari idnumber course_module lokal',
        'type'          => 'read',
        'capabilities'  => '',
        'ajax'          => true,
    ],

    // ── 8. Terima hasil ujian dari kelas (master) ─────────────────────────
    'local_eujian_receive_results' => [
        'classname'     => 'local_eujian_external',
        'methodname'    => 'receive_results',
        'classpath'     => 'local/eujian/externallib.php',
        'description'   => 'Terima dan simpan hasil ujian dari server kelas ke gradebook master',
        'type'          => 'write',
        'capabilities'  => 'moodle/grade:edit',
        'ajax'          => true,
    ],
];

// Token yang bisa mengakses semua fungsi di atas
$services = [
    'E-UJIAN Sync Service' => [
        'functions'       => [
            'local_eujian_get_students',
            'local_eujian_get_quiz_data',
            'local_eujian_get_quiz_results',
            'local_eujian_ping',
            'local_eujian_export_quiz',
            'local_eujian_import_quiz',
            'local_eujian_get_quiz_masterid',
            'local_eujian_receive_results',
        ],
        'restrictedusers' => 0,
        'enabled'         => 1,
        'shortname'       => 'eujian_sync',
    ],
];
