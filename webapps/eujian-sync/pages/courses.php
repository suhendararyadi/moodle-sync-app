<?php
$masterApi = new MoodleApi($config->masterUrl(), $config->masterToken(), 15);
$courses = []; $error = '';
try { $courses = $masterApi->getCourses(); } catch (Exception $e) { $error = $e->getMessage(); }
?>

<?php if ($error): ?>
  <div class="flex items-center gap-3 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-700 text-red-700 dark:text-red-300 rounded-xl px-4 py-3 text-sm mb-6">
    <span class="material-symbols-outlined">error</span> <?= htmlspecialchars($error) ?>
  </div>
<?php elseif (empty($courses)): ?>
  <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-700 text-blue-700 dark:text-blue-300 rounded-xl px-4 py-3 text-sm">Tidak ada kursus ditemukan.</div>
<?php else: ?>

<div class="mb-4 flex items-center gap-3">
  <div class="relative flex-1 max-w-sm">
    <span class="material-symbols-outlined absolute left-3 top-2.5 text-slate-400 text-lg">search</span>
    <input type="text" id="courseSearch" placeholder="Cari kursus..."
           class="w-full pl-10 pr-4 py-2 rounded-lg border border-slate-200 text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary bg-white">
  </div>
  <span class="text-xs text-slate-500 font-medium"><?= count($courses) ?> kursus</span>
</div>

<div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
  <table class="w-full text-left" id="courseTable">
    <thead class="bg-slate-50 dark:bg-slate-900/50 border-b border-slate-200 dark:border-slate-700">
      <tr>
        <th class="px-6 py-3 text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-widest">ID</th>
        <th class="px-6 py-3 text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-widest">Nama Kursus</th>
        <th class="px-6 py-3 text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-widest">Shortname</th>
        <th class="px-6 py-3 text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-widest">Format</th>
        <th class="px-6 py-3 text-[10px] font-bold text-slate-400 uppercase tracking-widest text-right">Aksi</th>
      </tr>
    </thead>
    <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
      <?php foreach ($courses as $c): ?>
      <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition-colors">
        <td class="px-6 py-4 font-mono text-xs text-slate-500 dark:text-slate-400"><?= (int)$c['id'] ?></td>
        <td class="px-6 py-4 text-sm font-medium text-slate-800 dark:text-slate-100"><?= htmlspecialchars($c['fullname'] ?? '-') ?></td>
        <td class="px-6 py-4">
          <span class="px-2 py-0.5 bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 text-xs font-mono rounded"><?= htmlspecialchars($c['shortname'] ?? '-') ?></span>
        </td>
        <td class="px-6 py-4 text-xs text-slate-500 dark:text-slate-400"><?= htmlspecialchars($c['format'] ?? '-') ?></td>
        <td class="px-6 py-4 text-right">
          <a href="?page=students&courseid=<?= (int)$c['id'] ?>"
             class="inline-flex items-center gap-1 text-primary text-xs font-semibold hover:underline">
            <span class="material-symbols-outlined text-sm">group</span> Siswa
          </a>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<script>
document.getElementById('courseSearch').addEventListener('input', function() {
  const q = this.value.toLowerCase();
  document.querySelectorAll('#courseTable tbody tr').forEach(tr =>
    tr.style.display = tr.textContent.toLowerCase().includes(q) ? '' : 'none');
});
</script>
<?php endif; ?>
