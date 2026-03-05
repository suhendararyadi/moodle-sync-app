<?php
$saved = false; $error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach (['MasterUrl','MasterToken','LocalUrl','LocalToken','RoomId','RoomName'] as $f)
        if (isset($_POST[$f])) $config->set($f, trim($_POST[$f]));
    if ($config->save()) $saved = true;
    else $error = 'Gagal menyimpan config.json — periksa permission file.';
}
?>

<?php if ($saved): ?>
  <div class="mb-6 flex items-center gap-3 bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-700 text-emerald-700 dark:text-emerald-300 rounded-xl px-4 py-3 text-sm font-medium">
    <span class="material-symbols-outlined">check_circle</span> Konfigurasi berhasil disimpan.
  </div>
<?php endif; ?>
<?php if ($error): ?>
  <div class="mb-6 flex items-center gap-3 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-700 text-red-700 dark:text-red-300 rounded-xl px-4 py-3 text-sm font-medium">
    <span class="material-symbols-outlined">error</span> <?= htmlspecialchars($error) ?>
  </div>
<?php endif; ?>

<div class="max-w-xl bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm p-8">
  <form method="POST" class="space-y-6">

    <div>
      <p class="text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-4 flex items-center gap-2">
        <span class="material-symbols-outlined text-sm">dns</span> Server Master (VPS / Pusat)
      </p>
      <div class="space-y-4">
        <div>
          <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">URL Master</label>
          <input type="url" name="MasterUrl" required
                 class="w-full rounded-lg border border-slate-200 dark:border-slate-600 px-3 py-2.5 text-sm bg-white dark:bg-slate-700 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary"
                 value="<?= htmlspecialchars($config->masterUrl()) ?>" placeholder="https://lms.sekolah.sch.id/">
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">Token Master</label>
          <input type="text" name="MasterToken"
                 class="w-full rounded-lg border border-slate-200 dark:border-slate-600 px-3 py-2.5 text-sm font-mono bg-white dark:bg-slate-700 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary"
                 value="<?= htmlspecialchars($config->masterToken()) ?>" placeholder="token dari Moodle Admin → Web Services">
        </div>
      </div>
    </div>

    <div class="border-t border-slate-100 dark:border-slate-700 pt-6">
      <p class="text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-4 flex items-center gap-2">
        <span class="material-symbols-outlined text-sm">storage</span> Server Lokal (Kelas)
      </p>
      <div class="space-y-4">
        <div>
          <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">URL Lokal</label>
          <input type="url" name="LocalUrl"
                 class="w-full rounded-lg border border-slate-200 dark:border-slate-600 px-3 py-2.5 text-sm bg-white dark:bg-slate-700 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary"
                 value="<?= htmlspecialchars($config->localUrl()) ?>" placeholder="http://localhost:8080/">
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">Token Lokal</label>
          <input type="text" name="LocalToken"
                 class="w-full rounded-lg border border-slate-200 dark:border-slate-600 px-3 py-2.5 text-sm font-mono bg-white dark:bg-slate-700 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary"
                 value="<?= htmlspecialchars($config->localToken()) ?>">
        </div>
      </div>
    </div>

    <div class="border-t border-slate-100 dark:border-slate-700 pt-6">
      <p class="text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-4 flex items-center gap-2">
        <span class="material-symbols-outlined text-sm">meeting_room</span> Identitas Ruangan
      </p>
      <div class="grid grid-cols-3 gap-4">
        <div>
          <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">Room ID</label>
          <input type="text" name="RoomId"
                 class="w-full rounded-lg border border-slate-200 dark:border-slate-600 px-3 py-2.5 text-sm bg-white dark:bg-slate-700 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary"
                 value="<?= htmlspecialchars($config->roomId()) ?>">
        </div>
        <div class="col-span-2">
          <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">Nama Ruangan</label>
          <input type="text" name="RoomName"
                 class="w-full rounded-lg border border-slate-200 dark:border-slate-600 px-3 py-2.5 text-sm bg-white dark:bg-slate-700 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary"
                 value="<?= htmlspecialchars($config->roomName()) ?>" placeholder="Server Kelas X TEI 1">
        </div>
      </div>
    </div>

    <div class="flex items-center gap-3 pt-2">
      <button type="submit" class="inline-flex items-center gap-2 px-5 py-2.5 bg-primary text-white text-sm font-semibold rounded-lg hover:bg-blue-700 transition-colors">
        <span class="material-symbols-outlined text-sm">save</span> Simpan Konfigurasi
      </button>
      <a href="?page=dashboard" class="px-4 py-2.5 text-slate-600 dark:text-slate-400 text-sm font-medium hover:text-slate-900 dark:hover:text-slate-200 transition-colors">Kembali</a>
    </div>
  </form>
</div>

<p class="mt-4 text-xs text-slate-400 dark:text-slate-500">
  Lokasi file: <code class="bg-slate-100 dark:bg-slate-700 dark:text-slate-300 px-2 py-0.5 rounded font-mono"><?= htmlspecialchars(realpath(APP_ROOT . '/config.json') ?: APP_ROOT . '/config.json') ?></code>
</p>
