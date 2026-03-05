<?php
/**
 * E-UJIAN Sync Web App
 * index.php — Router utama + layout Tailwind CSS
 */

define('APP_ROOT', __DIR__);
require_once APP_ROOT . '/lib/Config.php';
require_once APP_ROOT . '/lib/MoodleApi.php';
require_once APP_ROOT . '/lib/SyncService.php';

$config = new Config();

// Router
$page = preg_replace('/[^a-z_]/', '', strtolower($_GET['page'] ?? 'dashboard'));
$allowed = ['dashboard', 'courses', 'students', 'sync_course', 'sync_all',
            'sync_cohort', 'sync_quiz', 'upload', 'results', 'settings'];
if (!in_array($page, $allowed)) $page = 'dashboard';

$pageFile = APP_ROOT . "/pages/$page.php";

// Menu definisi
$menu = [
    'info' => [
        ['id' => 'dashboard',   'icon' => 'dashboard',    'label' => 'Dashboard'],
        ['id' => 'courses',     'icon' => 'book',         'label' => 'Daftar Kursus'],
        ['id' => 'students',    'icon' => 'group',        'label' => 'Daftar Siswa'],
    ],
    'Synchronization' => [
        ['id' => 'sync_course', 'icon' => 'cloud_sync',   'label' => 'Sync Kursus + Siswa'],
        ['id' => 'sync_all',    'icon' => 'person_add',   'label' => 'Sync Semua Siswa'],
        ['id' => 'sync_cohort', 'icon' => 'groups',       'label' => 'Sync Cohort/Rombel'],
        ['id' => 'sync_quiz',   'icon' => 'quiz',         'label' => 'Sync Quiz'],
        ['id' => 'upload',      'icon' => 'upload_file',  'label' => 'Upload Hasil Ujian'],
    ],
    'Configuration' => [
        ['id' => 'results',     'icon' => 'analytics',    'label' => 'Lihat Hasil Ujian'],
        ['id' => 'settings',    'icon' => 'settings',     'label' => 'Pengaturan'],
    ],
];

$pageTitles = [
    'dashboard'   => 'Dashboard Overview',
    'courses'     => 'Daftar Kursus',
    'students'    => 'Daftar Siswa',
    'sync_course' => 'Sync Kursus + Siswa',
    'sync_all'    => 'Sync Semua Siswa',
    'sync_cohort' => 'Sync Cohort / Rombel',
    'sync_quiz'   => 'Sync Quiz',
    'upload'      => 'Upload Hasil Ujian',
    'results'     => 'Lihat Hasil Ujian',
    'settings'    => 'Pengaturan',
];
$pageTitle = $pageTitles[$page] ?? 'E-UJIAN Sync';
?>
<!DOCTYPE html>
<html lang="id" id="htmlRoot">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($pageTitle) ?> — E-UJIAN Sync</title>
  <!-- Apply theme before render to prevent flash -->
  <script>(function(){const t=localStorage.getItem('eujian-theme');if(t==='dark'||(!t&&window.matchMedia('(prefers-color-scheme: dark)').matches))document.documentElement.classList.add('dark');})()</script>
  <script src="assets/tailwindcss.js"></script>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
  <script id="tailwind-config">
    tailwind.config = {
      darkMode: "class",
      theme: {
        extend: {
          colors: {
            "primary": "#0d59f2",
            "background-light": "#f5f6f8",
            "success": "#10b981",
          },
          fontFamily: { "display": ["Inter", "sans-serif"] },
          borderRadius: { "DEFAULT": "0.25rem", "lg": "0.5rem", "xl": "0.75rem", "full": "9999px" },
        },
      },
    }
  </script>
  <link rel="stylesheet" href="assets/app.css">
</head>
<body class="bg-[#f5f6f8] dark:bg-slate-950 font-display text-slate-900 dark:text-slate-100 transition-colors duration-200">
<div class="flex min-h-screen">

  <!-- ── SIDEBAR ─────────────────────────────────────────── -->
  <aside class="w-64 bg-white dark:bg-slate-900 border-r border-slate-200 dark:border-slate-800 text-slate-600 dark:text-slate-300 flex flex-col fixed inset-y-0 z-50 transition-colors duration-200">
    <!-- Logo -->
    <div class="p-5 flex items-center gap-3 border-b border-slate-200 dark:border-slate-800">
      <div class="size-10 bg-primary rounded-lg flex items-center justify-center text-white flex-shrink-0">
        <span class="material-symbols-outlined text-2xl">sync_alt</span>
      </div>
      <div>
        <h1 class="text-slate-800 dark:text-white text-base font-bold leading-none">E-UJIAN Sync</h1>
        <p class="text-slate-500 text-[10px] mt-1 uppercase tracking-wider font-semibold"><?= htmlspecialchars($config->roomName()) ?></p>
      </div>
    </div>

    <!-- Nav -->
    <nav class="flex-1 overflow-y-auto py-4 px-3 space-y-1">
      <?php foreach ($menu as $group => $items): ?>
        <?php if ($group !== 'info'): ?>
          <div class="pt-4 pb-1">
            <p class="px-3 text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-widest"><?= $group ?></p>
          </div>
        <?php endif; ?>
        <?php foreach ($items as $item):
          $isActive = $page === $item['id'];
        ?>
          <a href="?page=<?= $item['id'] ?>"
             class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-colors text-sm font-medium
             <?= $isActive
               ? 'sidebar-item-active text-primary dark:text-white font-semibold'
               : 'hover:bg-slate-50 dark:hover:bg-slate-800 text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white' ?>">
            <span class="material-symbols-outlined text-xl <?= $isActive ? 'text-primary dark:text-primary' : '' ?>"><?= $item['icon'] ?></span>
            <span><?= $item['label'] ?></span>
          </a>
        <?php endforeach; ?>
      <?php endforeach; ?>
    </nav>

    <!-- Footer Sidebar -->
    <div class="p-3 border-t border-slate-200 dark:border-slate-800">
      <div class="flex items-center gap-3 bg-slate-50 dark:bg-slate-800/50 p-3 rounded-xl">
        <div class="size-8 rounded-full bg-primary/10 dark:bg-primary/20 flex items-center justify-center flex-shrink-0">
          <span class="material-symbols-outlined text-primary text-lg">admin_panel_settings</span>
        </div>
        <div class="flex-1 overflow-hidden">
          <p class="text-sm font-semibold text-slate-700 dark:text-white truncate">Administrator</p>
          <p class="text-[10px] text-slate-500 truncate">SMKN 9 Garut</p>
        </div>
      </div>
    </div>
  </aside>

  <!-- ── MAIN CONTENT ────────────────────────────────────── -->
  <main class="flex-1 ml-64 flex flex-col min-h-screen">

    <!-- Top Header -->
    <header class="h-16 bg-white dark:bg-slate-900 border-b border-slate-200 dark:border-slate-700 px-8 flex items-center justify-between sticky top-0 z-40 transition-colors duration-200">
      <div class="flex items-center gap-3">
        <h2 class="text-lg font-bold text-slate-800 dark:text-slate-100"><?= htmlspecialchars($pageTitle) ?></h2>
      </div>
      <div class="flex items-center gap-4">
        <div class="flex items-center gap-2 bg-slate-100 dark:bg-slate-700 px-3 py-1.5 rounded-full">
          <div class="size-2 rounded-full bg-success pulse-badge"></div>
          <span class="text-xs font-semibold text-slate-600 dark:text-slate-300">
            <?= parse_url($config->localUrl(), PHP_URL_HOST) ?>:<?= parse_url($config->localUrl(), PHP_URL_PORT) ?>
          </span>
        </div>
        <!-- Dark Mode Toggle -->
        <button id="themeToggle" onclick="toggleTheme()"
                class="p-1.5 text-slate-400 dark:text-slate-500 hover:text-primary dark:hover:text-primary transition-colors rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700"
                title="Toggle tema terang/gelap">
          <span id="themeIcon" class="material-symbols-outlined text-xl">light_mode</span>
        </button>
        <a href="?page=settings" class="p-1.5 text-slate-400 dark:text-slate-500 hover:text-primary dark:hover:text-primary transition-colors rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700">
          <span class="material-symbols-outlined">settings</span>
        </a>
      </div>
    </header>

    <!-- Page Content -->
    <div class="flex-1 p-8">
      <?php if (file_exists($pageFile)): ?>
        <?php include $pageFile; ?>
      <?php else: ?>
        <div class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-700 text-yellow-800 dark:text-yellow-300 rounded-xl p-4">
          Halaman <code><?= htmlspecialchars($page) ?></code> tidak ditemukan.
        </div>
      <?php endif; ?>
    </div>

    <!-- Footer -->
    <footer class="px-8 py-4 border-t border-slate-200 dark:border-slate-700 flex justify-between items-center text-slate-400 dark:text-slate-500 text-xs transition-colors duration-200">
      <p>© <?= date('Y') ?> E-UJIAN Sync — SMKN 9 Garut. Pengembang: Suhendar Aryadi</p>
      <div class="flex gap-4">
        <span>Room: <strong><?= htmlspecialchars($config->roomName()) ?></strong></span>
        <span>ID: <strong><?= htmlspecialchars($config->roomId()) ?></strong></span>
      </div>
    </footer>
  </main>

</div>

<script src="assets/app.js?v=<?= time() ?>"></script>
</body>
</html>
