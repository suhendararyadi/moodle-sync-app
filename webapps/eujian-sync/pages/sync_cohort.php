<?php
$masterApi = new MoodleApi($config->masterUrl(), $config->masterToken(), 15);
$cohorts = [];
try { $cohorts = $masterApi->getCohorts(); } catch (Exception) {}
?>

<div class="max-w-2xl">
  <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm p-6 mb-6">
    <h3 class="font-semibold text-slate-800 dark:text-slate-100 mb-1">Sync Cohort / Rombel</h3>
    <p class="text-sm text-slate-500 dark:text-slate-400 mb-4">Sinkronisasi semua cohort (rombel/kelas) dari master ke lokal beserta anggotanya. Pastikan siswa sudah disinkronisasi terlebih dahulu.</p>

    <?php if (!empty($cohorts)): ?>
    <div class="mb-5">
      <p class="text-xs font-medium text-slate-500 dark:text-slate-400 mb-2">Cohort di Master (<?= count($cohorts) ?>):</p>
      <div class="flex flex-wrap gap-2">
        <?php foreach (array_slice($cohorts, 0, 20) as $c): ?>
          <span class="px-2.5 py-1 bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-200 text-xs font-medium rounded-full border border-slate-200 dark:border-slate-600">
            <?= htmlspecialchars($c['name']) ?>
          </span>
        <?php endforeach; ?>
        <?php if (count($cohorts) > 20): ?>
          <span class="px-2.5 py-1 bg-primary/10 text-primary text-xs font-medium rounded-full">+<?= count($cohorts)-20 ?> lainnya</span>
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>

    <button id="btnSync" onclick="startSync()"
            class="inline-flex items-center gap-2 px-5 py-2.5 bg-cyan-600 text-white text-sm font-semibold rounded-lg hover:bg-cyan-700 transition-colors disabled:opacity-50">
      <span class="material-symbols-outlined text-sm">groups</span> Sync Semua Cohort
    </button>
  </div>

  <div id="progressSection" class="hidden">
    <div class="relative h-2 bg-slate-100 dark:bg-slate-700 rounded-full overflow-hidden mb-4">
      <div id="progressBar" class="h-full bg-cyan-500 rounded-full transition-all duration-500" style="width:0%"></div>
    </div>
    <div id="progressLog"></div>
    <div id="alertBox" class="mt-4"></div>
  </div>
</div>

<script>
function startSync() {
  document.getElementById('progressSection').classList.remove('hidden');
  document.getElementById('progressLog').innerHTML = '';
  document.getElementById('alertBox').innerHTML = '';
  document.getElementById('btnSync').disabled = true;
  startSSE('ajax/stream.php?action=sync_cohort',
    data => updateProgress(data),
    data => { updateProgress({...data,done:true}); showAlert('alertBox','success', data.msg); document.getElementById('btnSync').disabled=false; },
    err  => { showAlert('alertBox','error', err); document.getElementById('btnSync').disabled=false; }
  );
}
</script>
