<?php
$courseId = (int)($_GET['courseid'] ?? 0);
$source   = $_GET['source'] ?? 'master';
$api      = $source === 'local'
    ? new MoodleApi($config->localUrl(), $config->localToken(), 20)
    : new MoodleApi($config->masterUrl(), $config->masterToken(), 20);
$students = []; $courseName = ''; $error = '';
if ($courseId > 0) {
    try {
        $students = $api->getEnrolledUsers($courseId);
        $c = $api->getCourseById($courseId);
        $courseName = $c['fullname'] ?? "Kursus $courseId";
    } catch (Exception $e) { $error = $e->getMessage(); }
}
?>

<form method="GET" class="flex flex-wrap items-end gap-3 mb-6">
  <input type="hidden" name="page" value="students">
  <div>
    <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1.5">Sumber Data</label>
    <select name="source" class="rounded-lg border border-slate-200 dark:border-slate-600 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 bg-white dark:bg-slate-700 dark:text-slate-100">
      <option value="master" <?= $source==='master'?'selected':'' ?>>Server Master</option>
      <option value="local"  <?= $source==='local' ?'selected':'' ?>>Server Lokal</option>
    </select>
  </div>
  <div>
    <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1.5">Course ID</label>
    <input type="number" name="courseid" value="<?= $courseId ?: '' ?>" placeholder="misal: 2"
           class="rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 bg-white w-32">
  </div>
  <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 bg-primary text-white text-sm font-semibold rounded-lg hover:bg-blue-700 transition-colors">
    <span class="material-symbols-outlined text-sm">search</span> Tampilkan
  </button>
  <?php if ($courseId): ?>
  <a href="?page=sync_course&courseid=<?= $courseId ?>"
     class="inline-flex items-center gap-2 px-4 py-2 bg-emerald-600 text-white text-sm font-semibold rounded-lg hover:bg-emerald-700 transition-colors">
    <span class="material-symbols-outlined text-sm">cloud_sync</span> Sync Kursus Ini
  </a>
  <?php endif; ?>
</form>

<?php if ($courseName): ?>
  <p class="text-sm font-semibold text-slate-700 dark:text-slate-200 mb-4 flex items-center gap-2">
    <span class="material-symbols-outlined text-slate-400">book</span> <?= htmlspecialchars($courseName) ?>
    <span class="px-2 py-0.5 bg-slate-100 dark:bg-slate-700 text-slate-500 dark:text-slate-400 text-xs rounded font-normal"><?= count($students) ?> pengguna</span>
  </p>
<?php endif; ?>

<?php if ($error): ?>
  <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-700 text-red-700 dark:text-red-300 rounded-xl px-4 py-3 text-sm"><?= htmlspecialchars($error) ?></div>
<?php elseif ($courseId && empty($students)): ?>
  <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-700 text-blue-700 dark:text-blue-300 rounded-xl px-4 py-3 text-sm">Tidak ada siswa di kursus ini.</div>
<?php elseif (!empty($students)): ?>

<div class="mb-3">
  <div class="relative max-w-sm">
    <span class="material-symbols-outlined absolute left-3 top-2.5 text-slate-400 text-lg">search</span>
    <input type="text" id="studentSearch" placeholder="Cari nama/username..."
           class="w-full pl-10 pr-4 py-2 rounded-lg border border-slate-200 dark:border-slate-600 text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 bg-white dark:bg-slate-700 dark:text-slate-100">
  </div>
</div>

<div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
  <table class="w-full text-left" id="studentTable">
    <thead class="bg-slate-50 dark:bg-slate-900/50 border-b border-slate-200 dark:border-slate-700">
      <tr>
        <th class="px-6 py-3 text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-widest">Username</th>
        <th class="px-6 py-3 text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-widest">Nama</th>
        <th class="px-6 py-3 text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-widest">Email</th>
        <th class="px-6 py-3 text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-widest">Role</th>
      </tr>
    </thead>
    <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
      <?php foreach ($students as $u): ?>
      <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition-colors">
        <td class="px-6 py-3 font-mono text-xs text-slate-600 dark:text-slate-300"><?= htmlspecialchars($u['username'] ?? '-') ?></td>
        <td class="px-6 py-3 text-sm text-slate-800 dark:text-slate-100"><?= htmlspecialchars(($u['firstname']??'').' '.($u['lastname']??'')) ?></td>
        <td class="px-6 py-3 text-xs text-slate-500 dark:text-slate-400"><?= htmlspecialchars($u['email'] ?? '-') ?></td>
        <td class="px-6 py-3">
          <?php foreach ($u['roles'] ?? [] as $r): ?>
            <span class="px-1.5 py-0.5 bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 text-[10px] font-semibold rounded"><?= htmlspecialchars($r['shortname'] ?? '') ?></span>
          <?php endforeach; ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<script>
document.getElementById('studentSearch').addEventListener('input', function() {
  const q = this.value.toLowerCase();
  document.querySelectorAll('#studentTable tbody tr').forEach(tr =>
    tr.style.display = tr.textContent.toLowerCase().includes(q) ? '' : 'none');
});
</script>
<?php endif; ?>
