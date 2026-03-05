// app.js - E-UJIAN Sync Web App

// ─── Theme (Dark / Light Mode) ───────────────────────────────────────────────

function applyTheme(dark) {
  const html = document.documentElement;
  const icon = document.getElementById('themeIcon');
  if (dark) {
    html.classList.add('dark');
    if (icon) icon.textContent = 'light_mode'; // Show Sun to switch to light
  } else {
    html.classList.remove('dark');
    if (icon) icon.textContent = 'dark_mode'; // Show Moon to switch to dark
  }
}

function toggleTheme() {
  const isDark = document.documentElement.classList.contains('dark');
  const next   = !isDark;
  localStorage.setItem('eujian-theme', next ? 'dark' : 'light');
  applyTheme(next);
}

// Sync icon with current state on DOM ready
document.addEventListener('DOMContentLoaded', () => {
  applyTheme(document.documentElement.classList.contains('dark'));
});

/**
 * Jalankan SSE (Server-Sent Events) untuk operasi panjang
 * @param {string} url     - URL endpoint SSE (ajax/stream.php?action=...)
 * @param {function} onMsg - callback(event) dipanggil tiap event 'progress'
 * @param {function} onDone - callback(data) dipanggil saat event 'done'
 * @param {function} onError - callback(msg) dipanggil saat error
 */
function startSSE(url, onMsg, onDone, onError) {
  const es = new EventSource(url);

  es.addEventListener('progress', e => {
    try { onMsg(JSON.parse(e.data)); } catch(ex) { onMsg({msg: e.data, pct: -1}); }
  });

  es.addEventListener('done', e => {
    es.close();
    try { onDone(JSON.parse(e.data)); } catch(ex) { onDone({}); }
  });

  es.addEventListener('error_msg', e => {
    es.close();
    onError(e.data || 'Terjadi kesalahan.');
  });

  es.onerror = () => {
    es.close();
    onError('Koneksi SSE terputus.');
  };

  return es;
}

/**
 * Tampilkan progress di #progressLog + #progressBar
 */
function updateProgress(data) {
  const log = document.getElementById('progressLog');
  const bar = document.getElementById('progressBar');

  if (log && data.msg) {
    const pct = data.pct != null ? String(data.pct).padStart(3) : '   ';
    const line = document.createElement('div');
    if (data.done) {
      line.className = 'log-ok';
      line.textContent = `✓ ${data.msg}`;
    } else if (String(data.msg).startsWith('[ERR]')) {
      line.className = 'log-err';
      line.textContent = data.msg;
    } else {
      line.className = 'log-info';
      line.textContent = `[${pct}%] ${data.msg}`;
    }
    log.appendChild(line);
    log.scrollTop = log.scrollHeight;
  }

  if (bar && data.pct >= 0) {
    bar.style.width = data.pct + '%';
  }
}

/**
 * Tampilkan alert (success/error) di container
 */
function showAlert(containerId, type, msg) {
  const el = document.getElementById(containerId);
  if (!el) return;
  const isSuccess = type === 'success';
  el.innerHTML = `
    <div class="flex items-start gap-3 px-4 py-3 rounded-xl border text-sm
         ${isSuccess
           ? 'bg-emerald-50 border-emerald-200 text-emerald-700'
           : 'bg-red-50 border-red-200 text-red-700'}">
      <span class="material-symbols-outlined text-base mt-0.5">${isSuccess ? 'check_circle' : 'error'}</span>
      <span>${msg}</span>
    </div>`;
}
