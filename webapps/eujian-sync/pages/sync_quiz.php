<?php
$masterApi = new MoodleApi($config->masterUrl(), $config->masterToken(), 15);
$localApi  = new MoodleApi($config->localUrl(),  $config->localToken(),  15);
$masterCourses = $localCourses = $masterQuizzes = [];
try { $masterCourses = $masterApi->getCourses(); } catch (Exception) {}
try { $localCourses  = $localApi->getCourses();  } catch (Exception) {}
$selMasterCourse = (int)($_POST['master_courseid'] ?? 0);
$selLocalCourse  = (int)($_POST['local_courseid']  ?? 0);
$selQuiz         = (int)($_POST['quizid']          ?? 0);
if ($selMasterCourse) {
    try { $masterQuizzes = $masterApi->getQuizzesInCourse($selMasterCourse); } catch (Exception) {}
}
?>

<div class="max-w-2xl">
  <!-- Step 1: Pilih kursus & quiz master -->
  <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm p-6 mb-4">
    <div class="flex items-center gap-3 mb-4">
      <span class="flex items-center justify-center w-7 h-7 bg-primary text-white text-xs font-bold rounded-full">1</span>
      <h3 class="font-semibold text-slate-800">Pilih Quiz dari Master</h3>
    </div>
    <form method="POST">
      <input type="hidden" name="page" value="sync_quiz">
      <input type="hidden" name="local_courseid" value="<?= $selLocalCourse ?>">
      <div class="mb-4">
        <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1.5">Kursus Master</label>
        <select name="master_courseid" onchange="this.form.submit()"
                class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 bg-white">
          <option value="">-- pilih kursus master --</option>
          <?php foreach ($masterCourses as $c): ?>
            <option value="<?= (int)$c['id'] ?>" <?= $selMasterCourse===$c['id']?'selected':'' ?>>
              <?= htmlspecialchars($c['shortname'].' — '.$c['fullname']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php if ($selMasterCourse && !empty($masterQuizzes)): ?>
      <div>
        <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1.5">Quiz</label>
        <select name="quizid" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 bg-white">
          <option value="">-- pilih quiz --</option>
          <?php foreach ($masterQuizzes as $q): ?>
            <option value="<?= (int)$q['id'] ?>" <?= $selQuiz===$q['id']?'selected':'' ?>>
              <?= htmlspecialchars($q['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php elseif ($selMasterCourse): ?>
      <p class="text-xs text-slate-400">Tidak ada quiz di kursus ini.</p>
      <?php endif; ?>
    </form>
  </div>

  <!-- Step 2: Pilih kursus lokal tujuan -->
  <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm p-6 mb-6">
    <div class="flex items-center gap-3 mb-4">
      <span class="flex items-center justify-center w-7 h-7 bg-slate-200 dark:bg-slate-600 text-slate-600 dark:text-slate-200 text-xs font-bold rounded-full">2</span>
      <h3 class="font-semibold text-slate-800">Pilih Kursus Lokal Tujuan</h3>
    </div>
    <div class="mb-5">
      <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1.5">Kursus Lokal</label>
      <select id="localCourseSelect" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 bg-white">
        <option value="">-- pilih kursus lokal --</option>
        <?php foreach ($localCourses as $c): ?>
          <option value="<?= (int)$c['id'] ?>" <?= $selLocalCourse===$c['id']?'selected':'' ?>>
            <?= htmlspecialchars($c['shortname'].' — '.$c['fullname']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <button id="btnSync" onclick="startSync()"
            class="inline-flex items-center gap-2 px-5 py-2.5 bg-primary text-white text-sm font-semibold rounded-lg hover:bg-blue-700 transition-colors disabled:opacity-50">
      <span class="material-symbols-outlined text-sm">upload</span> Sync Quiz
    </button>
  </div>

  <div id="progressSection" class="hidden">
    <div class="relative h-2 bg-slate-100 dark:bg-slate-700 rounded-full overflow-hidden mb-4">
      <div id="progressBar" class="h-full bg-primary rounded-full transition-all duration-500" style="width:0%"></div>
    </div>
    <div id="progressLog"></div>
    <div id="alertBox" class="mt-4"></div>
  </div>
</div>

<script>
const preQuiz  = <?= $selQuiz ?>;
const preMC    = <?= $selMasterCourse ?>;
// Restore quizid select if page was submitted
if (preQuiz) {
  document.querySelectorAll('select[name="quizid"] option').forEach(o => {
    if (parseInt(o.value) === preQuiz) o.selected = true;
  });
}
function startSync() {
  const qid = parseInt(document.querySelector('select[name="quizid"]')?.value || 0);
  const lid = parseInt(document.getElementById('localCourseSelect')?.value || 0);
  if (!qid) { alert('Pilih quiz dari master.'); return; }
  if (!lid) { alert('Pilih kursus lokal tujuan.'); return; }
  document.getElementById('progressSection').classList.remove('hidden');
  document.getElementById('progressLog').innerHTML = '';
  document.getElementById('alertBox').innerHTML = '';
  document.getElementById('btnSync').disabled = true;
  startSSE(`ajax/stream.php?action=sync_quiz&quizid=${qid}&local_courseid=${lid}`,
    data => updateProgress(data),
    data => { updateProgress({...data,done:true}); showAlert('alertBox','success', data.msg); document.getElementById('btnSync').disabled=false; },
    err  => { showAlert('alertBox','error', err); document.getElementById('btnSync').disabled=false; }
  );
}
</script>
