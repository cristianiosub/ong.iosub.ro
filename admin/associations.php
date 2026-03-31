<?php
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/auth.php';
initDb(); sessionStart(); requireAdmin();

$pageTitle = 'Asociații';
$page      = max(1, (int)($_GET['page'] ?? 1));
$perPage   = 25;
$search    = trim($_GET['search'] ?? '');
$county    = trim($_GET['county'] ?? '');
$status    = trim($_GET['status'] ?? '');

$where  = 'WHERE 1=1';
$params = [];

if ($search) { $where .= ' AND (name LIKE ? OR cui LIKE ? OR reg_number LIKE ?)'; $params[] = "%{$search}%"; $params[] = "%{$search}%"; $params[] = "%{$search}%"; }
if ($county) { $where .= ' AND county = ?';                  $params[] = $county; }
if ($status) { $where .= ' AND UPPER(status) LIKE ?';        $params[] = '%' . strtoupper($status) . '%'; }

$total      = dbOne("SELECT COUNT(*) AS c FROM `" . T_ASSOC . "` $where", $params)['c'] ?? 0;
$totalPages = max(1, (int)ceil($total / $perPage));

$assocs  = dbAll("SELECT * FROM `" . T_ASSOC . "` $where ORDER BY created_at DESC LIMIT $perPage OFFSET " . (($page-1)*$perPage), $params);
$counties = dbAll("SELECT DISTINCT county FROM `" . T_ASSOC . "` WHERE county IS NOT NULL AND county!='' ORDER BY county");

$qs = http_build_query(['search' => $search, 'county' => $county, 'status' => $status]);

ob_start();
?>
<div class="max-w-7xl mx-auto">
  <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
    <div>
      <h1 class="text-2xl font-bold text-gray-800">Asociații</h1>
      <p class="text-sm text-gray-500 mt-1"><?= $total ?> înregistrări</p>
    </div>
    <a href="/admin/api.php?action=export"
       class="inline-flex items-center gap-2 bg-green-600 hover:bg-green-700 text-white text-sm font-medium px-4 py-2.5 rounded-xl transition">
      <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
      </svg>
      Export CSV
    </a>
  </div>

  <!-- FILTERS -->
  <form method="get" action="/admin/associations.php"
        class="bg-white rounded-2xl shadow-sm p-4 mb-5 flex flex-wrap gap-3 items-end">
    <div class="flex-1 min-w-48">
      <label class="block text-xs text-gray-500 mb-1 font-medium">Caută după nume / CUI / Nr. Reg.</label>
      <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Ex: Hecate, 45115272, 1234/A/2005…"
        class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400"/>
    </div>
    <div class="min-w-36">
      <label class="block text-xs text-gray-500 mb-1 font-medium">Județ</label>
      <select name="county" class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400 bg-white">
        <option value="">Toate</option>
        <?php foreach ($counties as $c): ?>
          <option value="<?= htmlspecialchars($c['county']) ?>" <?= $county === $c['county'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($c['county']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="min-w-32">
      <label class="block text-xs text-gray-500 mb-1 font-medium">Status</label>
      <select name="status" class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400 bg-white">
        <option value="">Toate</option>
        <option value="activ"   <?= $status === 'activ'   ? 'selected' : '' ?>>Active</option>
        <option value="inactiv" <?= $status === 'inactiv' ? 'selected' : '' ?>>Inactive</option>
      </select>
    </div>
    <div class="flex gap-2">
      <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2 rounded-xl transition">Filtrează</button>
      <a href="/admin/associations.php" class="border border-gray-300 text-gray-600 hover:bg-gray-50 text-sm font-medium px-4 py-2 rounded-xl transition">Reset</a>
    </div>
  </form>

  <!-- TABLE -->
  <div class="bg-white rounded-2xl shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="bg-gray-50 border-b border-gray-100">
          <tr>
            <th class="px-4 py-3 text-left text-xs text-gray-500 font-semibold uppercase tracking-wider">Denumire</th>
            <th class="px-4 py-3 text-left text-xs text-gray-500 font-semibold uppercase tracking-wider hidden sm:table-cell">Nr. Reg. MJ</th>
            <th class="px-4 py-3 text-left text-xs text-gray-500 font-semibold uppercase tracking-wider hidden md:table-cell">CUI</th>
            <th class="px-4 py-3 text-left text-xs text-gray-500 font-semibold uppercase tracking-wider hidden md:table-cell">Județ</th>
            <th class="px-4 py-3 text-left text-xs text-gray-500 font-semibold uppercase tracking-wider">Status</th>
            <th class="px-4 py-3 text-left text-xs text-gray-500 font-semibold uppercase tracking-wider hidden xl:table-cell">Actualizat</th>
            <th class="px-4 py-3 text-right text-xs text-gray-500 font-semibold uppercase tracking-wider">Acțiuni</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
          <?php foreach ($assocs as $a):
            $isActive = stripos($a['status'] ?? '', 'activ') !== false;
          ?>
          <?php
            $href = !empty($a['cui'])
                ? '/asociatie.php?cui=' . urlencode($a['cui'])
                : '/asociatie.php?id=' . (int)$a['id'];
          ?>
          <tr class="hover:bg-gray-50 transition group">
            <td class="px-4 py-3 max-w-xs">
              <a href="<?= $href ?>" target="_blank"
                 class="font-medium text-gray-800 hover:text-indigo-600 transition line-clamp-2 leading-snug">
                <?= htmlspecialchars($a['name'] ?? '—') ?>
              </a>
            </td>
            <!-- Nr. Reg. MJ -->
            <td class="px-4 py-3 font-mono text-gray-400 text-xs hidden sm:table-cell whitespace-nowrap">
              <?= !empty($a['reg_number']) ? htmlspecialchars($a['reg_number']) : '<span class="text-gray-200">—</span>' ?>
            </td>
            <!-- CUI -->
            <td class="px-4 py-3 font-mono text-gray-700 text-sm hidden md:table-cell whitespace-nowrap">
              <?php if (!empty($a['cui'])): ?>
                <span class="font-semibold"><?= htmlspecialchars($a['cui']) ?></span>
              <?php else: ?>
                <span class="text-gray-300 text-xs">—</span>
              <?php endif; ?>
            </td>
            <td class="px-4 py-3 text-gray-500 hidden md:table-cell whitespace-nowrap"><?= htmlspecialchars($a['county'] ?? '—') ?></td>
            <td class="px-4 py-3 whitespace-nowrap">
              <?php if ($a['status']): ?>
                <span class="inline-flex items-center gap-1 text-xs font-medium px-2 py-0.5 rounded-full
                  <?= $isActive ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-600' ?>">
                  <span class="w-1.5 h-1.5 rounded-full <?= $isActive ? 'bg-green-400' : 'bg-red-400' ?>"></span>
                  <?= htmlspecialchars($a['status']) ?>
                </span>
              <?php else: ?><span class="text-gray-300 text-xs">—</span><?php endif; ?>
            </td>
            <td class="px-4 py-3 text-gray-400 text-xs hidden xl:table-cell whitespace-nowrap">
              <?= htmlspecialchars(substr($a['last_updated'] ?? '', 0, 10)) ?: '—' ?>
            </td>
            <td class="px-4 py-3 text-right whitespace-nowrap">
              <div class="flex items-center justify-end gap-1 opacity-0 group-hover:opacity-100 transition">
                <a href="<?= $href ?>" target="_blank"
                   class="text-indigo-500 hover:text-indigo-700 p-1.5 rounded-lg hover:bg-indigo-50 transition" title="Vezi fișa">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                  </svg>
                </a>
                <?php if (!empty($a['cui'])): ?>
                <button onclick="fetch('/api/refresh.php?cui=<?= urlencode($a['cui']) ?>').then(r=>r.json()).then(d=>{ if(d.success) location.reload(); else alert('Eroare la actualizare.'); })"
                  class="text-gray-400 hover:text-green-600 p-1.5 rounded-lg hover:bg-green-50 transition" title="Actualizează ANAF">
                <?php else: ?>
                <button disabled class="text-gray-200 p-1.5 rounded-lg cursor-not-allowed" title="Nu are CUI – nu poate fi actualizată din ANAF">
                <?php endif; ?>
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582M20 20v-5h-.581M4.582 9A8 8 0 0120 15M19.418 15A8 8 0 014 9"/>
                  </svg>
                </button>
                <button onclick="if(confirm('Ștergi această asociație?')) fetch('/admin/api.php?action=delete&id=<?= (int)$a['id'] ?>',{method:'POST'}).then(()=>location.reload())"
                  class="text-gray-400 hover:text-red-600 p-1.5 rounded-lg hover:bg-red-50 transition" title="Șterge">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                  </svg>
                </button>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (!$assocs): ?>
            <tr><td colspan="7" class="px-4 py-12 text-center text-gray-400">
              Nicio asociație nu corespunde filtrelor.
            </td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <!-- PAGINARE -->
    <?php if ($totalPages > 1): ?>
    <div class="px-4 py-3 border-t border-gray-100 flex items-center justify-between">
      <p class="text-xs text-gray-400">Pagina <?= $page ?> din <?= $totalPages ?> · <?= $total ?> total</p>
      <div class="flex gap-1">
        <?php if ($page > 1): ?>
          <a href="?page=<?= $page-1 ?>&<?= $qs ?>" class="px-3 py-1.5 text-xs border border-gray-200 rounded-lg text-gray-600 hover:bg-gray-50">← Anterior</a>
        <?php endif; ?>
        <?php for ($p = max(1, $page-2); $p <= min($totalPages, $page+2); $p++): ?>
          <a href="?page=<?= $p ?>&<?= $qs ?>"
             class="px-3 py-1.5 text-xs border rounded-lg <?= $p === $page ? 'bg-indigo-600 text-white border-indigo-600' : 'border-gray-200 text-gray-600 hover:bg-gray-50' ?>">
            <?= $p ?>
          </a>
        <?php endfor; ?>
        <?php if ($page < $totalPages): ?>
          <a href="?page=<?= $page+1 ?>&<?= $qs ?>" class="px-3 py-1.5 text-xs border border-gray-200 rounded-lg text-gray-600 hover:bg-gray-50">Următor →</a>
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>
<?php
$pageContent = ob_get_clean();
include __DIR__ . '/_layout.php';
