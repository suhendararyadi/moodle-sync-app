<?php
$masterApi = new MoodleApi($config->masterUrl(), $config->masterToken(), 10);
$localApi  = new MoodleApi($config->localUrl(), $config->localToken(), 10);
$masterStatus = $masterApi->ping();
$localStatus  = $localApi->ping();
$masterCount = '-'; $localCount = '-';
if ($masterStatus['ok']) try { $masterCount = count($masterApi->getCourses()); } catch(Exception){}
if ($localStatus['ok'])  try { $localCount  = count($localApi->getCourses());  } catch(Exception){}
?>

<!-- Server Status -->
<section class="mb-10">
  <div class="flex items-center justify-between mb-6">
    <h3 class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest">Server Status</h3>
    <a href="?page=dashboard" class="text-primary text-sm font-semibold flex items-center gap-1 hover:underline">
      <span class="material-symbols-outlined text-sm">refresh</span> Refresh
    </a>
  </div>
  <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

    <!-- Master -->
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6 flex flex-col relative overflow-hidden">
      <div class="absolute top-0 right-0 p-4">
        <?php if ($masterStatus['ok']): ?>
          <span class="flex items-center gap-1.5 px-3 py-1.5 bg-emerald-50 dark:bg-emerald-900/30 text-emerald-600 dark:text-emerald-400 text-xs font-bold rounded-full border border-emerald-200 dark:border-emerald-700 pulse-badge">
            <span class="size-1.5 rounded-full bg-emerald-500"></span>Online
          </span>
        <?php else: ?>
          <span class="flex items-center gap-1.5 px-3 py-1.5 bg-red-50 dark:bg-red-900/30 text-red-600 dark:text-red-400 text-xs font-bold rounded-full border border-red-200 dark:border-red-700">
            <span class="size-1.5 rounded-full bg-red-500"></span>Offline
          </span>
        <?php endif; ?>
      </div>
      <div class="flex items-start gap-4 mb-6">
        <div class="size-14 rounded-xl bg-blue-50 dark:bg-blue-900/30 text-primary flex items-center justify-center flex-shrink-0">
          <span class="material-symbols-outlined text-3xl">dns</span>
        </div>
        <div>
          <h4 class="text-xl font-bold text-slate-800 dark:text-slate-100">SERVER MASTER</h4>
          <p class="text-sm text-slate-500 dark:text-slate-400 font-medium"><?= htmlspecialchars(parse_url($config->masterUrl(), PHP_URL_HOST)) ?></p>
        </div>
      </div>
      <div class="grid grid-cols-2 gap-4 mt-auto">
        <div class="bg-slate-50 dark:bg-slate-900/50 p-4 rounded-lg">
          <p class="text-[10px] text-slate-500 dark:text-slate-400 uppercase font-bold tracking-tighter mb-1">Moodle Version</p>
          <p class="text-sm font-mono font-bold text-slate-700 dark:text-slate-200"><?= htmlspecialchars($masterStatus['version'] ?? '-') ?></p>
        </div>
        <div class="bg-slate-50 dark:bg-slate-900/50 p-4 rounded-lg">
          <p class="text-[10px] text-slate-500 dark:text-slate-400 uppercase font-bold tracking-tighter mb-1">Total Kursus</p>
          <p class="text-sm font-bold text-slate-700 dark:text-slate-200"><?= $masterCount ?> Kursus</p>
        </div>
      </div>
      <?php if (!$masterStatus['ok']): ?>
        <div class="mt-3 text-xs text-red-500 dark:text-red-400 bg-red-50 dark:bg-red-900/20 rounded-lg p-3"><?= htmlspecialchars($masterStatus['error'] ?? 'Tidak dapat terhubung') ?></div>
      <?php endif; ?>
    </div>

    <!-- Lokal -->
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6 flex flex-col relative overflow-hidden">
      <div class="absolute top-0 right-0 p-4">
        <?php if ($localStatus['ok']): ?>
          <span class="flex items-center gap-1.5 px-3 py-1.5 bg-emerald-50 dark:bg-emerald-900/30 text-emerald-600 dark:text-emerald-400 text-xs font-bold rounded-full border border-emerald-200 dark:border-emerald-700 pulse-badge">
            <span class="size-1.5 rounded-full bg-emerald-500"></span>Online
          </span>
        <?php else: ?>
          <span class="flex items-center gap-1.5 px-3 py-1.5 bg-red-50 dark:bg-red-900/30 text-red-600 dark:text-red-400 text-xs font-bold rounded-full border border-red-200 dark:border-red-700">
            <span class="size-1.5 rounded-full bg-red-500"></span>Offline
          </span>
        <?php endif; ?>
      </div>
      <div class="flex items-start gap-4 mb-6">
        <div class="size-14 rounded-xl bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 flex items-center justify-center flex-shrink-0">
          <span class="material-symbols-outlined text-3xl">storage</span>
        </div>
        <div>
          <h4 class="text-xl font-bold text-slate-800 dark:text-slate-100">SERVER LOKAL</h4>
          <p class="text-sm text-slate-500 dark:text-slate-400 font-medium"><?= htmlspecialchars($config->localUrl()) ?></p>
        </div>
      </div>
      <div class="grid grid-cols-3 gap-3 mt-auto">
        <div class="bg-slate-50 dark:bg-slate-900/50 p-4 rounded-lg">
          <p class="text-[10px] text-slate-500 dark:text-slate-400 uppercase font-bold tracking-tighter mb-1">Room ID</p>
          <p class="text-sm font-bold text-slate-700 dark:text-slate-200"><?= htmlspecialchars($config->roomId()) ?></p>
        </div>
        <div class="bg-slate-50 dark:bg-slate-900/50 p-4 rounded-lg">
          <p class="text-[10px] text-slate-500 dark:text-slate-400 uppercase font-bold tracking-tighter mb-1">Kursus</p>
          <p class="text-sm font-bold text-slate-700 dark:text-slate-200"><?= $localCount ?></p>
        </div>
        <div class="bg-slate-50 dark:bg-slate-900/50 p-4 rounded-lg">
          <p class="text-[10px] text-slate-500 dark:text-slate-400 uppercase font-bold tracking-tighter mb-1">Moodle Ver.</p>
          <p class="text-xs font-mono font-bold text-slate-700 dark:text-slate-200"><?= htmlspecialchars($localStatus['version'] ?? '-') ?></p>
        </div>
      </div>
      <?php if (!$localStatus['ok']): ?>
        <div class="mt-3 text-xs text-red-500 dark:text-red-400 bg-red-50 dark:bg-red-900/20 rounded-lg p-3"><?= htmlspecialchars($localStatus['error'] ?? 'Tidak dapat terhubung') ?></div>
      <?php endif; ?>
    </div>
  </div>
</section>

<!-- Quick Actions -->
<section>
  <div class="flex items-center gap-4 mb-6">
    <h3 class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest">Aksi Cepat</h3>
    <div class="h-px flex-1 bg-slate-200 dark:bg-slate-700"></div>
  </div>
  <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
    <?php
    $actions = [
      ['page'=>'sync_course','icon'=>'cloud_sync',  'title'=>'Sync Kursus + Siswa',  'desc'=>'Sinkronisasi metadata kursus dan pendaftaran siswa dari Master ke Lokal.', 'cta'=>'Start Sync'],
      ['page'=>'sync_all',   'icon'=>'person_add',  'title'=>'Sync Semua Siswa',      'desc'=>'Pastikan seluruh basis data pengguna ter-sinkronisasi ke server lokal.', 'cta'=>'Start Sync'],
      ['page'=>'sync_quiz',  'icon'=>'quiz',        'title'=>'Sync Quiz',             'desc'=>'Tarik bank soal dan konfigurasi kuis terbaru dari server pusat.', 'cta'=>'Start Sync'],
      ['page'=>'upload',     'icon'=>'upload_file', 'title'=>'Upload Hasil Ujian',    'desc'=>'Unggah rekap nilai dan log aktivitas ujian dari lokal ke server master.', 'cta'=>'Start Upload'],
    ];
    foreach ($actions as $a): ?>
    <a href="?page=<?= $a['page'] ?>"
       class="bg-white dark:bg-slate-800 p-6 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm hover:shadow-md hover:border-primary/30 dark:hover:border-primary/50 transition-all text-left group flex flex-col h-full no-underline">
      <div class="size-12 bg-primary/10 text-primary rounded-lg flex items-center justify-center mb-5 group-hover:scale-110 transition-transform">
        <span class="material-symbols-outlined"><?= $a['icon'] ?></span>
      </div>
      <h5 class="text-slate-800 dark:text-slate-100 font-bold mb-2 text-sm"><?= $a['title'] ?></h5>
      <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed mb-6"><?= $a['desc'] ?></p>
      <span class="mt-auto inline-flex items-center text-primary text-xs font-bold gap-1 uppercase tracking-wider">
        <?= $a['cta'] ?> <span class="material-symbols-outlined text-sm">arrow_forward</span>
      </span>
    </a>
    <?php endforeach; ?>
  </div>
</section>
