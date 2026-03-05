<?php
$masterApi = new MoodleApi($config->masterUrl(), $config->masterToken(), 15);
$courses = []; $preselect = (int)($_GET['courseid'] ?? 0);
try { $courses = $masterApi->getCourses(); } catch (Exception) {}
?>

<div class="max-w-2xl">
  <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm p-6 mb-6">
    <h3 class="font-semibold text-slate-800 dark:text-slate-100 mb-1">Sinkronisasi Kursus + Siswa</h3>
    <p class="text-sm text-slate-500 dark:text-slate-400 mb-5">Pilih kursus dari master, lalu klik sync untuk membuat kursus dan mendaftarkan semua siswa ke server lokal.</p>
    <div class="mb-5">
      <label class="block text-sm font-medium text-slate-700 mb-2">Pilih Kursus dari Master</label>
      <select id="courseSelect" class="w-full rounded-lg border border-slate-200 dark:border-slate-600 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary bg-white dark:bg-slate-700 dark:text-slate-100">
        <option value="">-- pilih kursus --</option>
        <?php foreach ($courses as $c): ?>
          <option value="<?= (int)$c['id'] ?>" <?= $preselect===$c['id']?'selected':'' ?>>
            <?= htmlspecialchars($c['shortname'].' — '.$c['fullname']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <button id="btnSync" onclick="startSync()"
            class="inline-flex items-center gap-2 px-5 py-2.5 bg-primary text-white text-sm font-semibold rounded-lg hover:bg-blue-700 transition-colors disabled:opacity-50">
      <span class="material-symbols-outlined text-sm">cloud_sync</span> Mulai Sync
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
function startSync() {
  const id = document.getElementById('courseSelect').value;
  if (!id) { alert('Pilih kursus terlebih dahulu.'); return; }
  document.getElementById('progressSection').classList.remove('hidden');
  document.getElementById('progressLog').innerHTML = '';
  document.getElementById('alertBox').innerHTML = '';
  document.getElementById('btnSync').disabled = true;
  startSSE(`ajax/stream.php?action=sync_course&courseid=${id}`,
    data => updateProgress(data),
    data => { updateProgress({...data,done:true}); showAlert('alertBox','success', data.msg); document.getElementById('btnSync').disabled=false; },
    err  => { showAlert('alertBox','error', err); document.getElementById('btnSync').disabled=false; }
  );
}
</script>
