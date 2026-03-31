<?php
require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/db.php';
initDb();

$pageTitle = SITE_NAME;
$recent       = dbAll("SELECT DISTINCT association_name, cui_found FROM `" . T_SEARCH . "` WHERE association_name IS NOT NULL ORDER BY created_at DESC LIMIT 6");
$totalAssoc   = dbOne("SELECT COUNT(*) AS cnt FROM `" . T_ASSOC . "`")['cnt'] ?? 0;
$totalSearch  = dbOne("SELECT COUNT(*) AS cnt FROM `" . T_SEARCH . "`")['cnt'] ?? 0;

include __DIR__ . '/inc/header.php';
?>

<!-- HERO -->
<section class="gradient-hero py-20 px-4 text-white text-center">
  <h1 class="text-4xl sm:text-5xl font-extrabold mb-3 tracking-tight">Registru Asociații România</h1>
  <p class="text-indigo-200 text-lg mb-10 max-w-xl mx-auto">
    Caută orice asociație sau fundație din România și obține toate datele publice disponibile.
  </p>
  <form action="/search.php" method="get" class="max-w-2xl mx-auto flex gap-2">
    <input type="text" name="q" placeholder="Ex: Asociația Hecate, Fundația X…" required
      class="flex-1 px-5 py-4 rounded-xl text-gray-800 text-base shadow-lg focus:outline-none focus:ring-4 focus:ring-indigo-300"/>
    <button type="submit"
      class="bg-indigo-500 hover:bg-indigo-400 text-white font-semibold px-6 py-4 rounded-xl shadow-lg transition flex items-center gap-2">
      <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11A6 6 0 115 11a6 6 0 0112 0z"/>
      </svg>
      Caută
    </button>
  </form>
</section>

<!-- STATS -->
<section class="max-w-6xl mx-auto px-4 -mt-6">
  <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
    <?php foreach ([
      [$totalAssoc,  'Asociații în baza de date', 'bg-indigo-100 text-indigo-700', 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.768-.231-1.48-.634-2.066M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.768.231-1.48.634-2.066M12 10a4 4 0 100-8 4 4 0 000 8z'],
      [$totalSearch, 'Căutări efectuate',         'bg-green-100 text-green-700',   'M21 21l-4.35-4.35M17 11A6 6 0 115 11a6 6 0 0112 0z'],
    ] as [$val, $label, $cls, $icon]): ?>
    <div class="bg-white rounded-xl shadow p-5 flex items-center gap-4 card-hover">
      <div class="<?= $cls ?> rounded-full p-3">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?= $icon ?>"/>
        </svg>
      </div>
      <div>
        <p class="text-2xl font-bold text-gray-800"><?= $val ?></p>
        <p class="text-sm text-gray-500"><?= $label ?></p>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</section>

<!-- RECENT -->
<?php if ($recent): ?>
<section class="max-w-6xl mx-auto px-4 mt-10">
  <h2 class="text-lg font-semibold text-gray-700 mb-4">Căutate recent</h2>
  <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
    <?php foreach ($recent as $item): ?>
    <a href="/asociatie.php?cui=<?= urlencode($item['cui_found']) ?>"
       class="bg-white border border-gray-100 rounded-xl p-4 flex items-center gap-3 shadow-sm card-hover hover:border-indigo-200">
      <div class="bg-indigo-50 text-indigo-600 rounded-full p-2 flex-shrink-0">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5"/>
        </svg>
      </div>
      <div class="min-w-0">
        <p class="text-sm font-medium text-gray-800 truncate"><?= htmlspecialchars($item['association_name']) ?></p>
        <p class="text-xs text-gray-400">CUI: <?= htmlspecialchars($item['cui_found']) ?></p>
      </div>
    </a>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<!-- HOW IT WORKS -->
<section class="max-w-6xl mx-auto px-4 mt-12 mb-10">
  <h2 class="text-lg font-semibold text-gray-700 mb-6 text-center">Cum funcționează</h2>
  <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
    <?php foreach ([
      ['1', 'Caută după nume',  'Introdu orice parte din numele asociației.',                          'bg-indigo-100 text-indigo-700'],
      ['2', 'Găsim datele',     'Interogăm ANAF + totalfirme.ro + asociatii.net automat.',             'bg-purple-100 text-purple-700'],
      ['3', 'Vezi fișa completă','Adresă, CUI, reprezentanți, status fiscal și mai mult.',             'bg-green-100 text-green-700'],
    ] as [$num, $title, $desc, $cls]): ?>
    <div class="bg-white rounded-xl shadow-sm p-6 text-center card-hover">
      <div class="inline-flex items-center justify-center w-12 h-12 rounded-full <?= $cls ?> text-lg font-bold mb-4"><?= $num ?></div>
      <h3 class="font-semibold text-gray-800 mb-1"><?= $title ?></h3>
      <p class="text-sm text-gray-500"><?= $desc ?></p>
    </div>
    <?php endforeach; ?>
  </div>
</section>

<?php include __DIR__ . '/inc/footer.php'; ?>
