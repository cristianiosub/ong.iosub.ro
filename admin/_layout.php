<!DOCTYPE html>
<html lang="ro" class="h-full">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title><?= htmlspecialchars($pageTitle ?? 'Admin') ?> – <?= SITE_NAME ?></title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
  <style>[x-cloak]{display:none!important}</style>
</head>
<body class="h-full bg-gray-100 flex" x-data="{ pwModal: false }">

<!-- SIDEBAR -->
<aside class="hidden lg:flex lg:flex-col lg:w-64 bg-slate-900 min-h-screen flex-shrink-0">
  <div class="px-6 py-5 border-b border-white/10">
    <a href="/index.php" class="flex items-center gap-2 text-white font-bold text-base">
      <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
          d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5"/>
      </svg>
      ONG Search
    </a>
    <p class="text-xs text-slate-400 mt-0.5">Panou Administrator</p>
  </div>

  <nav class="flex-1 px-3 py-4 space-y-1">
    <?php
    $links = [
      ['/admin/dashboard.php',    'Dashboard',    'M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z'],
      ['/admin/associations.php', 'Asociații',   'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-2 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4'],
      ['/admin/import_auto.php',  'Import date', 'M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12'],
      ['/index.php',              'Site public', 'M21 21l-4.35-4.35M17 11A6 6 0 115 11a6 6 0 0112 0z'],
    ];
    $current = $_SERVER['PHP_SELF'];
    foreach ($links as [$href, $label, $icon]):
      $active = strpos($current, basename($href)) !== false;
    ?>
    <a href="<?= $href ?>"
       class="flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm font-medium transition
              <?= $active ? 'bg-indigo-600/20 text-indigo-400' : 'text-slate-400 hover:bg-white/5 hover:text-slate-200' ?>">
      <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?= $icon ?>"/>
      </svg>
      <?= $label ?>
    </a>
    <?php endforeach; ?>
  </nav>

  <div class="px-3 py-4 border-t border-white/10">
    <div class="flex items-center gap-3 px-4 py-2 rounded-xl bg-white/5 mb-2">
      <div class="w-7 h-7 rounded-full bg-indigo-600 flex items-center justify-center text-white text-xs font-bold">
        <?= strtoupper(substr(adminUsername(), 0, 1)) ?>
      </div>
      <span class="text-sm text-slate-300"><?= htmlspecialchars(adminUsername()) ?></span>
    </div>
    <button @click="pwModal=true"
      class="w-full flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm font-medium text-slate-400 hover:bg-white/5 hover:text-slate-200 transition">
      <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
          d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>
      </svg>
      Schimbă parola
    </button>
    <a href="/admin/logout.php"
       class="flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm font-medium text-red-400 hover:bg-red-900/20 transition">
      <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
          d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
      </svg>
      Deconectare
    </a>
  </div>
</aside>

<!-- MAIN -->
<div class="flex-1 flex flex-col min-h-screen overflow-hidden">
  <header class="lg:hidden bg-slate-900 px-4 py-3 flex items-center justify-between">
    <span class="text-white font-bold text-sm">ONG Search Admin</span>
    <a href="/admin/logout.php" class="text-red-400 text-sm">Deconectare</a>
  </header>
  <main class="flex-1 overflow-y-auto p-6">
    <?= $pageContent ?? '' ?>
  </main>
</div>

<!-- MODAL SCHIMBARE PAROLĂ -->
<div x-show="pwModal" x-cloak class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
  <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-6" @click.stop>
    <h3 class="text-lg font-bold text-gray-800 mb-5">Schimbă parola</h3>
    <form id="pw-form" class="space-y-4">
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Parola curentă</label>
        <input type="password" name="current" required class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400"/>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Parola nouă</label>
        <input type="password" name="new" required minlength="6" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400"/>
      </div>
      <div id="pw-msg" class="hidden text-sm rounded-lg px-3 py-2"></div>
      <div class="flex gap-3 pt-1">
        <button type="button" @click="pwModal=false" class="flex-1 border border-gray-300 text-gray-700 py-2.5 rounded-xl text-sm font-medium hover:bg-gray-50 transition">Anulează</button>
        <button type="submit" class="flex-1 bg-indigo-600 hover:bg-indigo-700 text-white py-2.5 rounded-xl text-sm font-medium transition">Salvează</button>
      </div>
    </form>
  </div>
</div>

<script>
document.getElementById('pw-form').addEventListener('submit', async function(e) {
  e.preventDefault();
  const fd   = new FormData(this);
  const msg  = document.getElementById('pw-msg');
  const resp = await fetch('/admin/api.php?action=change_password', { method: 'POST', body: fd });
  const d    = await resp.json();
  msg.className = 'text-sm rounded-lg px-3 py-2 ' + (d.success ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700');
  msg.textContent = d.message;
  msg.classList.remove('hidden');
  if (d.success) setTimeout(() => location.reload(), 1500);
});
</script>

</body>
</html>
