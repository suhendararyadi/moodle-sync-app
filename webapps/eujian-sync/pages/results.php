<?php
$localApi = new MoodleApi($config->localUrl(), $config->localToken(), 15);
$courses = $quizzes = [];
try { $courses = $localApi->getCourses(); } catch (Exception) {}
$selCourse = (int)($_POST['courseid'] ?? 0);
$selQuiz   = (int)($_POST['quizid']   ?? 0);
$results   = [];
if ($selCourse) {
    try { $quizzes = $localApi->getQuizzesByCourse($selCourse); } catch (Exception) {}
}
if ($selQuiz) {
    try { $results = $localApi->getQuizResults($selQuiz); } catch (Exception $e) {}
}
?>

<div class="max-w-4xl">
  <form method="POST" class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm p-6 mb-6">
    <input type="hidden" name="page" value="results">
    <div class="flex flex-wrap items-end gap-4 mb-4">
      <div class="flex-1 min-w-[200px]">
        <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1.5">Kursus Lokal</label>
        <select name="courseid" onchange="this.form.submit()"
                class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 bg-white">
          <option value="">-- pilih kursus --</option>
          <?php foreach ($courses as $c): ?>
            <option value="<?= (int)$c['id'] ?>" <?= $selCourse===$c['id']?'selected':'' ?>>
              <?= htmlspecialchars($c['shortname'].' — '.$c['fullname']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php if ($selCourse && !empty($quizzes)): ?>
      <div class="flex-1 min-w-[200px]">
        <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1.5">Quiz</label>
        <select name="quizid" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 bg-white">
          <option value="">-- pilih quiz --</option>
          <?php foreach ($quizzes as $q): ?>
            <option value="<?= (int)$q['id'] ?>" <?= $selQuiz===$q['id']?'selected':'' ?>>
              <?= htmlspecialchars($q['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php endif; ?>
      <button type="submit" class="inline-flex items-center gap-2 px-5 py-2.5 bg-primary text-white text-sm font-semibold rounded-lg hover:bg-blue-700 transition-colors">
        <span class="material-symbols-outlined text-sm">filter_list</span> Tampilkan
      </button>
    </div>
  </form>

  <?php if ($selQuiz && !empty($results)): ?>
  <div class="flex items-center justify-between mb-3">
    <p class="text-sm font-semibold text-slate-700 dark:text-slate-200 flex items-center gap-2">
      <span class="material-symbols-outlined text-slate-400">assignment</span> Hasil Ujian
      <span class="px-2 py-0.5 bg-slate-100 text-slate-500 text-xs rounded"><?= count($results) ?> siswa</span>
    </p>
    <div class="flex gap-2">
      <button onclick="exportCSV()" class="inline-flex items-center gap-1.5 px-3 py-2 bg-white dark:bg-slate-700 border border-slate-200 dark:border-slate-600 text-slate-700 dark:text-slate-200 text-xs font-semibold rounded-lg hover:bg-slate-50 dark:hover:bg-slate-600 transition-colors">
        <span class="material-symbols-outlined text-sm">download</span> CSV
      </button>
      <button onclick="exportJSON()" class="inline-flex items-center gap-1.5 px-3 py-2 bg-white dark:bg-slate-700 border border-slate-200 dark:border-slate-600 text-slate-700 dark:text-slate-200 text-xs font-semibold rounded-lg hover:bg-slate-50 dark:hover:bg-slate-600 transition-colors">
        <span class="material-symbols-outlined text-sm">code</span> JSON
      </button>
    </div>
  </div>

  <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
    <table class="w-full text-left" id="resultsTable">
      <thead class="bg-slate-50 dark:bg-slate-900/50 border-b border-slate-200 dark:border-slate-700">
        <tr>
          <th class="px-6 py-3 text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-widest">#</th>
          <th class="px-6 py-3 text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-widest">Username</th>
          <th class="px-6 py-3 text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-widest">Nama</th>
          <th class="px-6 py-3 text-[10px] font-bold text-slate-400 uppercase tracking-widest text-right">Nilai</th>
          <th class="px-6 py-3 text-[10px] font-bold text-slate-400 uppercase tracking-widest text-right">Maks</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-100 dark:divide-slate-700" id="resultsBody">
        <?php $no=1; foreach ($results as $r): ?>
        <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition-colors">
          <td class="px-6 py-3 text-xs text-slate-400 dark:text-slate-500"><?= $no++ ?></td>
          <td class="px-6 py-3 font-mono text-xs text-slate-600 dark:text-slate-300"><?= htmlspecialchars($r['username'] ?? '-') ?></td>
          <td class="px-6 py-3 text-sm text-slate-800 dark:text-slate-100"><?= htmlspecialchars($r['fullname'] ?? '-') ?></td>
          <td class="px-6 py-3 text-right">
            <span class="font-bold text-sm <?= ($r['grade'] ?? 0) >= 70 ? 'text-emerald-600' : 'text-red-500' ?>">
              <?= number_format((float)($r['grade'] ?? 0), 2) ?>
            </span>
          </td>
          <td class="px-6 py-3 text-right text-xs text-slate-500 dark:text-slate-400"><?= number_format((float)($r['maxgrade'] ?? 100), 2) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php elseif ($selQuiz): ?>
  <div class="bg-blue-50 border border-blue-200 text-blue-700 rounded-xl px-4 py-3 text-sm">Belum ada hasil ujian untuk quiz ini.</div>
  <?php endif; ?>
</div>

<script>
const resultsData = <?= json_encode($results ?? []) ?>;
function exportCSV() {
  if (!resultsData.length) return;
  const rows = [['username','fullname','grade','maxgrade'], ...resultsData.map(r => [r.username,r.fullname,r.grade,r.maxgrade])];
  const csv = rows.map(r => r.map(v => `"${String(v||'').replace(/"/g,'""')}"`).join(',')).join('\n');
  downloadFile(csv, 'hasil_ujian.csv', 'text/csv');
}
function exportJSON() {
  downloadFile(JSON.stringify(resultsData, null, 2), 'hasil_ujian.json', 'application/json');
}
function downloadFile(content, name, type) {
  const a = document.createElement('a');
  a.href = URL.createObjectURL(new Blob([content], { type }));
  a.download = name; a.click();
}
</script>
