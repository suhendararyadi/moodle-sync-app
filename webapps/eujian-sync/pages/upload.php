<?php
$localApi = new MoodleApi($config->localUrl(), $config->localToken(), 15);
$localCourses = $localQuizzes = [];
try { $localCourses = $localApi->getCourses(); } catch (Exception) {}
$selCourse = (int)($_POST['courseid'] ?? 0);
$selQuiz   = (int)($_POST['quizid']   ?? 0);
if ($selCourse) {
    try { $localQuizzes = $localApi->getQuizzesByCourse($selCourse); } catch (Exception) {}
}
?>

<div class="max-w-2xl">
  <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm p-6 mb-6">
    <h3 class="font-semibold text-slate-800 dark:text-slate-100 mb-1">Upload Hasil Ujian ke Master</h3>
    <p class="text-sm text-slate-500 dark:text-slate-400 mb-5">Ambil hasil ujian dari server lokal dan kirim ke server master.</p>

    <form method="POST">
      <input type="hidden" name="page" value="upload">
      <div class="mb-4">
        <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1.5">Kursus Lokal</label>
        <select name="courseid" onchange="this.form.submit()"
                class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 bg-white">
          <option value="">-- pilih kursus --</option>
          <?php foreach ($localCourses as $c): ?>
            <option value="<?= (int)$c['id'] ?>" <?= $selCourse===$c['id']?'selected':'' ?>>
              <?= htmlspecialchars($c['shortname'].' — '.$c['fullname']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php if ($selCourse && !empty($localQuizzes)): ?>
      <div class="mb-5">
        <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1.5">Quiz</label>
        <select name="quizid" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 bg-white">
          <option value="">-- pilih quiz --</option>
          <?php foreach ($localQuizzes as $q): ?>
            <option value="<?= (int)$q['id'] ?>" <?= $selQuiz===$q['id']?'selected':'' ?>>
              <?= htmlspecialchars($q['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php endif; ?>
    </form>

    <button id="btnUpload" onclick="startUpload()"
            class="inline-flex items-center gap-2 px-5 py-2.5 bg-emerald-600 text-white text-sm font-semibold rounded-lg hover:bg-emerald-700 transition-colors disabled:opacity-50">
      <span class="material-symbols-outlined text-sm">upload</span> Upload Hasil
    </button>
  </div>

  <div id="progressSection" class="hidden">
    <div class="relative h-2 bg-slate-100 dark:bg-slate-700 rounded-full overflow-hidden mb-4">
      <div id="progressBar" class="h-full bg-emerald-500 rounded-full transition-all duration-500" style="width:0%"></div>
    </div>
    <div id="progressLog"></div>
    <div id="alertBox" class="mt-4"></div>
  </div>
</div>

<script>
const preCourse = <?= $selCourse ?>, preQuiz = <?= $selQuiz ?>;
if (preQuiz) {
  document.querySelectorAll('select[name="quizid"] option').forEach(o => {
    if (parseInt(o.value) === preQuiz) o.selected = true;
  });
}
function startUpload() {
  const qid = parseInt(document.querySelector('select[name="quizid"]')?.value || 0);
  if (!qid) { alert('Pilih quiz lokal terlebih dahulu.'); return; }
  document.getElementById('progressSection').classList.remove('hidden');
  document.getElementById('progressLog').innerHTML = '';
  document.getElementById('alertBox').innerHTML = '';
  document.getElementById('btnUpload').disabled = true;
  startSSE(`ajax/stream.php?action=upload&quizid=${qid}`,
    data => updateProgress(data),
    data => { updateProgress({...data,done:true}); showAlert('alertBox','success', data.msg); document.getElementById('btnUpload').disabled=false; },
    err  => { showAlert('alertBox','error', err); document.getElementById('btnUpload').disabled=false; }
  );
}
</script>
