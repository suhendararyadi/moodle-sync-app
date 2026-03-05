<div class="max-w-2xl">
  <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm p-6 mb-6">
    <h3 class="font-semibold text-slate-800 dark:text-slate-100 mb-1">Sync Semua Siswa</h3>
    <p class="text-sm text-slate-500 dark:text-slate-400 mb-4">Sinkronisasi semua siswa dari <strong>seluruh kursus</strong> di master ke server lokal secara sekaligus.</p>
    <div class="flex items-center gap-3 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-700 text-amber-700 dark:text-amber-300 rounded-lg px-4 py-3 text-xs mb-5">
      <span class="material-symbols-outlined text-sm">warning</span>
      Operasi ini memakan waktu beberapa menit tergantung jumlah siswa.
    </div>
    <button id="btnSync" onclick="startSync()"
            class="inline-flex items-center gap-2 px-5 py-2.5 bg-amber-500 text-white text-sm font-semibold rounded-lg hover:bg-amber-600 transition-colors disabled:opacity-50">
      <span class="material-symbols-outlined text-sm">person_add</span> Mulai Sync Semua
    </button>
  </div>
  <div id="progressSection" class="hidden">
    <div class="relative h-2 bg-slate-100 dark:bg-slate-700 rounded-full overflow-hidden mb-4">
      <div id="progressBar" class="h-full bg-amber-500 rounded-full transition-all duration-500" style="width:0%"></div>
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
  startSSE('ajax/stream.php?action=sync_all',
    data => updateProgress(data),
    data => { updateProgress({...data,done:true}); showAlert('alertBox','success', data.msg); document.getElementById('btnSync').disabled=false; },
    err  => { showAlert('alertBox','error', err); document.getElementById('btnSync').disabled=false; }
  );
}
</script>
