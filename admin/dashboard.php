<?php
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/auth.php';
initDb(); sessionStart(); requireAdmin();

$pageTitle = 'Dashboard';

$stats = [
    ['Asociații în DB',  dbOne("SELECT COUNT(*) AS c FROM `" . T_ASSOC . "`")['c'] ?? 0,  'bg-indigo-500', 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5'],
    ['Căutări totale',   dbOne("SELECT COUNT(*) AS c FROM `" . T_SEARCH . "`")['c'] ?? 0, 'bg-purple-500', 'M21 21l-4.35-4.35M17 11A6 6 0 115 11a6 6 0 0112 0z'],
    ['Căutări azi',      dbOne("SELECT COUNT(*) AS c FROM `" . T_SEARCH . "` WHERE DATE(created_at)=CURDATE()")['c'] ?? 0, 'bg-blue-500', 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z'],
    ['Active',           dbOne("SELECT COUNT(*) AS c FROM `" . T_ASSOC . "` WHERE UPPER(status) LIKE '%ACTIV%'")['c'] ?? 0, 'bg-green-500', 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z'],
];

$recentSearches = dbAll("SELECT * FROM `" . T_SEARCH . "` ORDER BY created_at DESC LIMIT 20");
$topCounties    = dbAll("SELECT county, COUNT(*) AS cnt FROM `" . T_ASSOC . "` WHERE county IS NOT NULL AND county!='' GROUP BY county ORDER BY cnt DESC LIMIT 8");
$maxCnt         = $topCounties[0]['cnt'] ?? 1;

ob_start();
?>
<div class="max-w-6xl mx-auto">
  <h1 class="text-2xl font-bold text-gray-800 mb-6">Dashboard</h1>

  <!-- STATS -->
  <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
    <?php foreach ($stats as [$label, $val, $bg, $icon]): ?>
    <div class="bg-white rounded-2xl shadow-sm p-5 flex items-center gap-4">
      <div class="w-10 h-10 rounded-xl <?= $bg ?> flex items-center justify-center flex-shrink-0">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?= $icon ?>"/>
        </svg>
      </div>
      <div>
        <p class="text-2xl font-bold text-gray-800"><?= $val ?></p>
        <p class="text-xs text-gray-500"><?= $label ?></p>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    <!-- RECENT SEARCHES -->
    <div class="lg:col-span-2 bg-white rounded-2xl shadow-sm p-6">
      <div class="flex items-center justify-between mb-4">
        <h2 class="font-semibold text-gray-800">Căutări recente</h2>
        <button onclick="if(confirm('Ștergi tot istoricul?')) fetch('/admin/api.php?action=clear_searches',{method:'POST'}).then(()=>location.reload())"
          class="text-xs text-red-400 hover:text-red-600 transition">Șterge istoric</button>
      </div>
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead><tr class="border-b border-gray-100">
            <th class="pb-2 text-left text-xs text-gray-400 font-medium">Interogare</th>
            <th class="pb-2 text-left text-xs text-gray-400 font-medium">Asociație găsită</th>
            <th class="pb-2 text-left text-xs text-gray-400 font-medium">Data</th>
          </tr></thead>
          <tbody class="divide-y divide-gray-50">
            <?php foreach ($recentSearches as $s): ?>
            <tr class="hover:bg-gray-50">
              <td class="py-2.5 font-medium text-gray-700 max-w-xs truncate pr-2"><?= htmlspecialchars($s['query']) ?></td>
              <td class="py-2.5 text-gray-500 pr-2">
                <?php if ($s['association_name']): ?>
                  <a href="/asociatie.php?cui=<?= urlencode($s['cui_found']) ?>" class="text-indigo-600 hover:underline">
                    <?= htmlspecialchars(mb_substr($s['association_name'], 0, 40)) ?><?= mb_strlen($s['association_name']) > 40 ? '…' : '' ?>
                  </a>
                <?php else: ?><span class="text-gray-300">—</span><?php endif; ?>
              </td>
              <td class="py-2.5 text-gray-400 text-xs whitespace-nowrap"><?= htmlspecialchars(substr($s['created_at'] ?? '', 0, 16)) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$recentSearches): ?>
              <tr><td colspan="3" class="py-6 text-center text-gray-400 text-sm">Nu există căutări.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- TOP JUDETE -->
    <div class="bg-white rounded-2xl shadow-sm p-6">
      <h2 class="font-semibold text-gray-800 mb-4">Top județe</h2>
      <?php if ($topCounties): ?>
        <div class="space-y-3">
          <?php foreach ($topCounties as $county): ?>
          <div>
            <div class="flex items-center justify-between text-sm mb-1">
              <span class="text-gray-700 font-medium"><?= htmlspecialchars($county['county']) ?></span>
              <span class="text-gray-400 text-xs"><?= $county['cnt'] ?></span>
            </div>
            <div class="w-full bg-gray-100 rounded-full h-1.5">
              <div class="bg-indigo-500 h-1.5 rounded-full" style="width:<?= round($county['cnt'] / $maxCnt * 100) ?>%"></div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <p class="text-sm text-gray-400 text-center py-8">Date insuficiente</p>
      <?php endif; ?>
    </div>

  </div>
</div>
<?php
$pageContent = ob_get_clean();
include __DIR__ . '/_layout.php';
