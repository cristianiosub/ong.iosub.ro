<?php
require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/inc/scrapers.php';
initDb();

$q = trim($_GET['q'] ?? '');
if (!$q) { header('Location: /index.php'); exit; }

$ip = $_SERVER['HTTP_X_REAL_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? '';

// 1. Cache local
$cached = dbAll(
    "SELECT * FROM `" . T_ASSOC . "` WHERE name LIKE ? ORDER BY name LIMIT 15",
    ["%{$q}%"]
);

$results = [];
$source  = 'live';

if ($cached) {
    $source  = 'cache';
    $results = $cached;
    dbExec("INSERT INTO `" . T_SEARCH . "` (query, cui_found, association_name, ip_address) VALUES (?,?,?,?)",
           [$q, $cached[0]['cui'], $cached[0]['name'], $ip]);
} else {
    // 2. Live scraping
    $results = searchAssociations($q);
    $cui1    = $results[0]['cui'] ?? null;
    $name1   = $results[0]['name'] ?? null;
    dbExec("INSERT INTO `" . T_SEARCH . "` (query, cui_found, association_name, ip_address) VALUES (?,?,?,?)",
           [$q, $cui1, $name1, $ip]);
}

// Dacă un singur rezultat → redirect direct la fișă
if (count($results) === 1) {
    $r1 = $results[0];
    if (!empty($r1['cui'])) {
        header('Location: /asociatie.php?cui=' . urlencode($r1['cui']));
    } else {
        header('Location: /asociatie.php?id=' . (int)$r1['id']);
    }
    exit;
}

$pageTitle = 'Rezultate: ' . $q;
include __DIR__ . '/inc/header.php';
?>

<div class="max-w-4xl mx-auto px-4 py-10">
  <!-- Search bar -->
  <div class="flex items-center gap-3 mb-6">
    <a href="/index.php" class="text-indigo-600 hover:text-indigo-800 transition">
      <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
      </svg>
    </a>
    <form action="/search.php" method="get" class="flex-1 flex gap-2">
      <input type="text" name="q" value="<?= htmlspecialchars($q) ?>"
        class="flex-1 border border-gray-300 rounded-lg px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400"/>
      <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition">
        Caută din nou
      </button>
    </form>
  </div>

  <div class="mb-6">
    <h1 class="text-xl font-bold text-gray-800">
      Rezultate pentru <span class="text-indigo-600">"<?= htmlspecialchars($q) ?>"</span>
    </h1>
    <p class="text-sm text-gray-500 mt-1">
      <?= count($results) ?> rezultat<?= count($results) !== 1 ? 'e' : '' ?> găsite
      <?php if ($source === 'cache'): ?>
        <span class="ml-2 text-xs bg-green-100 text-green-700 px-2 py-0.5 rounded-full">din cache local</span>
      <?php endif; ?>
    </p>
  </div>

  <?php if ($results): ?>
    <div class="space-y-3">
      <?php foreach ($results as $r):
        $href = !empty($r['cui'])
            ? '/asociatie.php?cui=' . urlencode($r['cui'])
            : '/asociatie.php?id=' . (int)$r['id'];
      ?>
      <a href="<?= $href ?>"
         class="block bg-white border border-gray-100 rounded-xl p-5 shadow-sm card-hover hover:border-indigo-200 transition">
        <div class="flex items-start justify-between gap-4">
          <div class="min-w-0">
            <h2 class="font-semibold text-gray-900 text-base leading-snug"><?= htmlspecialchars($r['name']) ?></h2>
            <?php if (!empty($r['cui'])): ?>
            <p class="text-xs text-gray-400 mt-1">CUI: <span class="font-mono"><?= htmlspecialchars($r['cui']) ?></span></p>
            <?php elseif (!empty($r['reg_number'])): ?>
            <p class="text-xs text-gray-400 mt-1">Nr. reg.: <span class="font-mono"><?= htmlspecialchars($r['reg_number']) ?></span></p>
            <?php endif; ?>
            <?php if (!empty($r['address'])): ?>
              <p class="text-sm text-gray-500 mt-1"><?= htmlspecialchars($r['address']) ?></p>
            <?php endif; ?>
          </div>
          <div class="flex-shrink-0 flex flex-col items-end gap-2">
            <?php if (!empty($r['status'])): ?>
              <span class="text-xs font-medium px-2 py-0.5 rounded-full
                <?= stripos($r['status'], 'activ') !== false ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-600' ?>">
                <?= htmlspecialchars($r['status']) ?>
              </span>
            <?php endif; ?>
            <span class="text-indigo-500">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
              </svg>
            </span>
          </div>
        </div>
      </a>
      <?php endforeach; ?>
    </div>

  <?php else: ?>
    <div class="bg-white rounded-xl shadow-sm p-10 text-center">
      <svg xmlns="http://www.w3.org/2000/svg" class="h-14 w-14 mx-auto text-gray-300 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
      </svg>
      <h2 class="text-lg font-semibold text-gray-600 mb-2">Nu am găsit nicio asociație</h2>
      <p class="text-sm text-gray-400 mb-6">Încearcă un termen diferit sau verifică ortografia.</p>
      <a href="/index.php" class="inline-block bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-5 py-2.5 rounded-lg transition">
        Întoarce-te acasă
      </a>
    </div>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/inc/footer.php'; ?>
