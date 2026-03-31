<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_error_debug.log');
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    file_put_contents(__DIR__ . '/php_error_debug.log',
        date('[Y-m-d H:i:s]') . " E$errno: $errstr in $errfile:$errline\n", FILE_APPEND);
});
register_shutdown_function(function() {
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        file_put_contents(__DIR__ . '/php_error_debug.log',
            date('[Y-m-d H:i:s]') . " FATAL: {$e['message']} in {$e['file']}:{$e['line']}\n", FILE_APPEND);
    }
});
require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/inc/scrapers.php';
initDb();

$cui = preg_replace('/[^0-9]/', '', $_GET['cui'] ?? '');
$id  = (int)($_GET['id'] ?? 0);

if (!$cui && !$id) { header('Location: /index.php'); exit; }

$assoc     = null;
$fromCache = false;

// ── Încarcă din DB (prin CUI sau ID) ─────────────────────────────────────────
if ($cui) {
    $cached = dbOne("SELECT * FROM `" . T_ASSOC . "` WHERE cui = ?", [$cui]);
} else {
    $cached = dbOne("SELECT * FROM `" . T_ASSOC . "` WHERE id = ?", [$id]);
}

if ($cached) {
    $age = $cached['last_updated'] ? time() - strtotime($cached['last_updated']) : PHP_INT_MAX;
    if ($age < CACHE_DAYS * 86400) {
        $assoc     = $cached;
        $fromCache = true;
    }
    if (!$cui && !empty($cached['cui'])) $cui = $cached['cui'];
}

// ── Fetch live doar dacă avem CUI și cache e vechi ───────────────────────────
if (!$assoc) {
    if ($cui) {
        $fresh = fetchAllData($cui);
        if (!empty($fresh['name'])) {
            saveAssociation($fresh);
            $assoc = $fresh;
        }
    }
    // Fără CUI: afișăm direct ce avem în DB (datele MJ)
    // Îmbogățirea cu openapi.ro se face MANUAL prin butonul "Caută CUI"
    if (!$assoc && $cached) {
        $assoc     = $cached;
        $fromCache = true;
    }
}

// Decode JSON fields
if ($assoc) {
    $assoc['representatives'] = is_array($assoc['representatives'] ?? null)
        ? $assoc['representatives']
        : (json_decode($assoc['representatives'] ?? '[]', true) ?: []);
    $assoc['founders'] = is_array($assoc['founders'] ?? null)
        ? $assoc['founders']
        : (json_decode($assoc['founders'] ?? '[]', true) ?: []);
}

// ── Normalizare diacritice ────────────────────────────────────────────────────
function fixDiacritics(?string $s): string {
    if ($s === null) return '';
    // Corectăm codificări greșite ale diacriticelor românești
    $map = [
        'ÃŽ'=>'Î','Ã®'=>'î','Ã‚'=>'Â','Ã¢'=>'â','Åž'=>'Ș','åž'=>'ș',
        'ÅŸ'=>'ș','Å¢'=>'Ț','Å£'=>'ț','Ã '=>'à','Ã¡'=>'á',
        "\xc5\x9f"=>'ș',"\xc5\x9e"=>'Ș',"\xc8\x99"=>'ș',"\xc8\x98"=>'Ș',
        "\xc8\x9b"=>'ț',"\xc8\x9a"=>'Ț',"\xc5\xa2"=>'Ț',"\xc5\xa3"=>'ț',
        'Ã¢'=>'â','Ã‚'=>'Â','Ã®'=>'î','ÃŽ'=>'Î','Ã '=>'ă','Ä‚'=>'Ă',
        'rÔndul'=>'rândul', 'Ã¢ndul'=>'ândul',
    ];
    return strtr($s, $map);
}

// Statusul din registrul MJ → afișare prietenoasă (compatibil PHP 7.4+)
function statusMjLabel(?string $s): array {
    switch (strtolower(trim($s ?? ''))) {
        case '':             return ['label' => 'Activă',       'cls' => 'bg-green-100 text-green-700',  'dot' => 'bg-green-400'];
        case 'radiata':      return ['label' => 'Radiată',      'cls' => 'bg-red-100 text-red-600',      'dot' => 'bg-red-400'];
        case 'in lichidare': return ['label' => 'În lichidare', 'cls' => 'bg-amber-100 text-amber-700',  'dot' => 'bg-amber-400'];
        case 'dizolvata':    return ['label' => 'Dizolvată',    'cls' => 'bg-orange-100 text-orange-700','dot' => 'bg-orange-400'];
        default:             return ['label' => ucfirst((string)$s), 'cls' => 'bg-gray-100 text-gray-600', 'dot' => 'bg-gray-400'];
    }
}

$pageTitle = $assoc ? (fixDiacritics($assoc['name']) . ' – ' . SITE_NAME) : 'Asociație';
include __DIR__ . '/inc/header.php';

function field(string $label, ?string $value): void {
    $v = fixDiacritics($value);
    if (!$v) return;
    echo '<div class="flex gap-2"><dt class="text-gray-400 w-44 flex-shrink-0 text-sm">' . htmlspecialchars($label) . '</dt>';
    echo '<dd class="text-gray-800 font-medium text-sm flex-1">' . htmlspecialchars($v) . '</dd></div>';
}

function money(?string $val): string {
    if ($val === null || $val === '') return '';
    return number_format((float)$val, 0, ',', '.') . ' RON';
}
?>

<div class="max-w-4xl mx-auto px-4 py-8" x-data="{ refreshing: false, statusMsg: '' }">
  <a href="javascript:history.back()"
     class="inline-flex items-center gap-1 text-sm text-indigo-600 hover:text-indigo-800 mb-6 transition">
    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
    </svg>
    Înapoi
  </a>

<?php if (!$assoc): ?>
  <div class="bg-white rounded-2xl shadow p-12 text-center">
    <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 mx-auto text-gray-300 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
    </svg>
    <h2 class="text-xl font-bold text-gray-600 mb-2">Nu am găsit date</h2>
    <a href="/index.php" class="mt-4 inline-block bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2.5 rounded-lg text-sm font-medium transition">Caută din nou</a>
  </div>

<?php else:
  $statusMj  = statusMjLabel($assoc['status'] ?? '');
  $hasCui    = !empty($assoc['cui']);
  $name      = fixDiacritics($assoc['name'] ?? '');
?>

  <!-- HEADER CARD -->
  <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 mb-5">
    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
      <div class="min-w-0">
        <h1 class="text-2xl font-extrabold text-gray-900 leading-tight"><?= htmlspecialchars($name) ?></h1>

        <!-- Badge-uri -->
        <div class="flex flex-wrap items-center gap-2 mt-3">

          <!-- Status Registru MJ -->
          <span class="inline-flex items-center gap-1.5 text-xs font-semibold px-2.5 py-0.5 rounded-full <?= $statusMj['cls'] ?>">
            <span class="w-1.5 h-1.5 rounded-full <?= $statusMj['dot'] ?>"></span>
            <?= $statusMj['label'] ?> (Registru MJ)
          </span>

          <?php if ($hasCui): ?>
          <!-- Status fiscal (ANAF/openapi) -->
          <?php
            $statusFiscal = $assoc['status'] ?? '';
            $fiscalActive = stripos($statusFiscal, 'activ') !== false || empty($statusFiscal);
          ?>
          <span class="inline-flex items-center gap-1.5 text-xs font-semibold px-2.5 py-0.5 rounded-full <?= $fiscalActive ? 'bg-blue-100 text-blue-700' : 'bg-red-100 text-red-600' ?>">
            <span class="w-1.5 h-1.5 rounded-full <?= $fiscalActive ? 'bg-blue-400' : 'bg-red-400' ?>"></span>
            <?= $fiscalActive ? 'Activă fiscal' : htmlspecialchars($statusFiscal) ?> (ANAF)
          </span>
          <?php endif; ?>

          <?php if (!empty($assoc['vat_payer'])): ?>
          <span class="text-xs font-semibold px-2.5 py-0.5 rounded-full bg-purple-100 text-purple-700">TVA</span>
          <?php endif; ?>

          <?php if (!empty($assoc['legal_form'])): ?>
          <span class="text-xs font-medium px-2 py-0.5 rounded-full bg-gray-100 text-gray-600"><?= htmlspecialchars($assoc['legal_form']) ?></span>
          <?php endif; ?>
        </div>
      </div>

      <!-- Butoane refresh -->
      <?php if ($hasCui): ?>
      <button
        @click="refreshing=true; fetch('/api/refresh.php?cui=<?= urlencode($cui) ?>').then(r=>r.json()).then(d=>{ if(d.success) location.reload(); else { refreshing=false; alert('Eroare: '+d.message); } })"
        :disabled="refreshing"
        class="flex-shrink-0 inline-flex items-center gap-2 border border-indigo-200 text-indigo-600 hover:bg-indigo-50 px-4 py-2 rounded-lg text-sm font-medium transition disabled:opacity-50">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" :class="refreshing?'animate-spin':''" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582M20 20v-5h-.581M4.582 9A8 8 0 0120 15M19.418 15A8 8 0 014 9"/>
        </svg>
        <span x-text="refreshing?'Se actualizează…':'Actualizează din openapi.ro'"></span>
      </button>
      <?php else: ?>
      <!-- Fără CUI: buton de căutare CUI -->
      <button
        @click="refreshing=true; statusMsg='Se caută CUI pe openapi.ro…'; fetch('/api/refresh.php?id=<?= (int)($assoc['id'] ?? 0) ?>').then(r=>r.json()).then(d=>{ if(d.success && d.cui_found) { statusMsg='CUI găsit: '+d.cui_found+'. Se reîncarcă…'; setTimeout(()=>location.reload(),1000); } else { refreshing=false; statusMsg=d.message||'CUI negăsit pe openapi.ro'; } })"
        :disabled="refreshing"
        class="flex-shrink-0 inline-flex items-center gap-2 border border-amber-300 text-amber-700 hover:bg-amber-50 px-4 py-2 rounded-lg text-sm font-medium transition disabled:opacity-50">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" :class="refreshing?'animate-spin':''" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11A6 6 0 115 11a6 6 0 0112 0z"/>
        </svg>
        <span x-text="refreshing?statusMsg:'Caută CUI pe openapi.ro'"></span>
      </button>
      <?php endif; ?>
    </div>

    <?php if ($fromCache): ?>
    <p class="mt-3 text-xs text-gray-400">
      ⏱ Date din cache · Ultima actualizare: <?= htmlspecialchars(substr($assoc['last_updated'] ?? '', 0, 10)) ?>
    </p>
    <?php endif; ?>
  </div>

  <!-- GRID INFORMAȚII -->
  <div class="grid grid-cols-1 md:grid-cols-2 gap-5">

    <!-- Identificare & Registru -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
      <h2 class="text-xs font-semibold text-indigo-600 uppercase tracking-wider mb-4">📋 Identificare & Registru</h2>
      <dl class="space-y-3">
        <?php
        field('Nr. reg. MJ',       $assoc['reg_number'] ?? null);
        field('CUI / CIF',         $hasCui ? $assoc['cui'] : null);
        field('Județ',             fixDiacritics($assoc['county'] ?? null));
        field('Localitate',        fixDiacritics($assoc['city'] ?? null));
        field('Adresă',            fixDiacritics($assoc['address'] ?? null));
        field('Data înregistrării',$assoc['registration_date'] ?? null);
        field('Formă juridică',    $assoc['legal_form'] ?? null);
        ?>
      </dl>
    </div>

    <!-- Informații fiscale (numai dacă avem CUI) -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
      <h2 class="text-xs font-semibold text-purple-600 uppercase tracking-wider mb-4">💼 Date fiscale<?= $hasCui ? '' : ' <span class="text-gray-300 normal-case">(necesită CUI)</span>' ?></h2>
      <dl class="space-y-3">
        <?php if ($hasCui): ?>
        <div class="flex gap-2">
          <dt class="text-gray-400 w-44 flex-shrink-0 text-sm">Plătitor TVA</dt>
          <dd>
            <?php if (!empty($assoc['vat_payer'])): ?>
              <span class="bg-green-100 text-green-700 text-xs font-semibold px-2 py-0.5 rounded-full">Da</span>
            <?php else: ?>
              <span class="bg-gray-100 text-gray-500 text-xs font-semibold px-2 py-0.5 rounded-full">Nu / Nespecificat</span>
            <?php endif; ?>
          </dd>
        </div>
        <?php
        field('Cod CAEN',          $assoc['caen_code'] ?? null);
        field('Domeniu CAEN',      $assoc['caen_description'] ?? null);
        ?>
        <!-- Date financiare din bilanț -->
        <?php if (!empty($assoc['balance_year'])): ?>
          <div class="border-t border-gray-50 pt-3 mt-3">
            <p class="text-xs text-gray-400 mb-2">Bilanț <?= htmlspecialchars($assoc['balance_year']) ?></p>
            <?php
            if (!empty($assoc['cifra_afaceri']))  field('Cifră afaceri', money($assoc['cifra_afaceri']));
            if (!empty($assoc['profit_net']))      field('Profit net',    money($assoc['profit_net']));
            if (!empty($assoc['active_totale']))   field('Active totale', money($assoc['active_totale']));
            if (!empty($assoc['nr_angajati']))     field('Nr. angajați',  (string)$assoc['nr_angajati']);
            ?>
          </div>
        <?php endif; ?>
        <?php else: ?>
        <p class="text-sm text-gray-400">Datele fiscale sunt disponibile numai dacă cunoaștem codul fiscal (CUI) al asociației.</p>
        <?php endif; ?>
      </dl>
    </div>

    <!-- Contact -->
    <?php if (!empty($assoc['phone']) || !empty($assoc['email']) || !empty($assoc['website'])): ?>
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
      <h2 class="text-xs font-semibold text-teal-600 uppercase tracking-wider mb-4">📞 Contact</h2>
      <dl class="space-y-3">
        <?php if (!empty($assoc['phone'])): ?>
          <div class="flex gap-2"><dt class="text-gray-400 w-44 flex-shrink-0 text-sm">Telefon</dt>
            <dd><a href="tel:<?= htmlspecialchars($assoc['phone']) ?>" class="text-indigo-600 hover:underline text-sm"><?= htmlspecialchars($assoc['phone']) ?></a></dd></div>
        <?php endif; ?>
        <?php if (!empty($assoc['email'])): ?>
          <div class="flex gap-2"><dt class="text-gray-400 w-44 flex-shrink-0 text-sm">Email</dt>
            <dd><a href="mailto:<?= htmlspecialchars($assoc['email']) ?>" class="text-indigo-600 hover:underline text-sm"><?= htmlspecialchars($assoc['email']) ?></a></dd></div>
        <?php endif; ?>
        <?php if (!empty($assoc['website'])): ?>
          <div class="flex gap-2"><dt class="text-gray-400 w-44 flex-shrink-0 text-sm">Website</dt>
            <dd><a href="<?= htmlspecialchars($assoc['website']) ?>" target="_blank" rel="noopener" class="text-indigo-600 hover:underline text-sm break-all"><?= htmlspecialchars($assoc['website']) ?></a></dd></div>
        <?php endif; ?>
      </dl>
    </div>
    <?php endif; ?>

    <!-- Reprezentanți -->
    <?php if (!empty($assoc['representatives'])): ?>
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
      <h2 class="text-xs font-semibold text-orange-600 uppercase tracking-wider mb-4">👤 Reprezentanți legali</h2>
      <ul class="space-y-2">
        <?php foreach ($assoc['representatives'] as $i => $rep): ?>
          <li class="flex items-center gap-2 text-sm text-gray-800">
            <span class="w-6 h-6 rounded-full bg-orange-100 text-orange-600 flex items-center justify-center text-xs font-bold flex-shrink-0"><?= $i+1 ?></span>
            <?= htmlspecialchars(fixDiacritics($rep)) ?>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
    <?php endif; ?>

    <!-- Fondatori -->
    <?php if (!empty($assoc['founders'])): ?>
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
      <h2 class="text-xs font-semibold text-pink-600 uppercase tracking-wider mb-4">👥 Fondatori</h2>
      <ul class="space-y-2">
        <?php foreach ($assoc['founders'] as $f): ?>
          <li class="text-sm text-gray-800 flex items-center gap-2">
            <span class="w-1.5 h-1.5 rounded-full bg-pink-400 flex-shrink-0"></span>
            <?= htmlspecialchars(fixDiacritics($f)) ?>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
    <?php endif; ?>

    <!-- Scop / Obiect de activitate -->
    <?php if (!empty($assoc['purpose'])): ?>
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 md:col-span-2">
      <h2 class="text-xs font-semibold text-cyan-600 uppercase tracking-wider mb-4">📄 Scop și obiect de activitate</h2>
      <p class="text-sm text-gray-700 leading-relaxed"><?= nl2br(htmlspecialchars(fixDiacritics($assoc['purpose']))) ?></p>
    </div>
    <?php endif; ?>

    <!-- Evoluție financiară (din bilanțuri openapi.ro) -->
    <?php
    // Extragem all_balances din raw_data dacă nu e direct pe $assoc
    $allBalances = [];
    if (!empty($assoc['all_balances']) && is_array($assoc['all_balances'])) {
        $allBalances = $assoc['all_balances'];
    } elseif (!empty($assoc['raw_data'])) {
        $raw = is_array($assoc['raw_data']) ? $assoc['raw_data'] : json_decode($assoc['raw_data'], true);
        if (!empty($raw['all_balances'])) $allBalances = $raw['all_balances'];
    }
    // Sortăm crescător după an pentru afișare
    usort($allBalances, function($a, $b) { return strcmp($a['an'] ?? '', $b['an'] ?? ''); });
    ?>
    <?php if (!empty($allBalances) && count($allBalances) >= 1): ?>
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 md:col-span-2">
      <h2 class="text-xs font-semibold text-emerald-600 uppercase tracking-wider mb-4">📊 Evoluție financiară (bilanțuri anuale)</h2>

      <!-- Tabel -->
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b border-gray-100 text-xs text-gray-400">
              <th class="py-2 pr-4 text-left font-medium">An</th>
              <th class="py-2 pr-4 text-right font-medium">Cifră afaceri</th>
              <th class="py-2 pr-4 text-right font-medium">Profit net</th>
              <th class="py-2 pr-4 text-right font-medium">Active totale</th>
              <th class="py-2 text-right font-medium">Angajați</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-50">
            <?php foreach ($allBalances as $b): ?>
            <tr class="hover:bg-gray-50 transition">
              <td class="py-2.5 pr-4 font-semibold text-gray-700"><?= htmlspecialchars($b['an']) ?></td>
              <td class="py-2.5 pr-4 text-right font-mono text-gray-800">
                <?= $b['cifra_afaceri'] !== null ? number_format((float)$b['cifra_afaceri'], 0, ',', '.') . ' RON' : '<span class="text-gray-300">—</span>' ?>
              </td>
              <td class="py-2.5 pr-4 text-right font-mono <?= isset($b['profit_net']) && $b['profit_net'] < 0 ? 'text-red-500' : 'text-gray-800' ?>">
                <?php if ($b['profit_net'] !== null):
                  echo ($b['profit_net'] < 0 ? '▼ ' : ($b['profit_net'] > 0 ? '▲ ' : ''));
                  echo number_format(abs((float)$b['profit_net']), 0, ',', '.') . ' RON';
                else: echo '<span class="text-gray-300">—</span>'; endif; ?>
              </td>
              <td class="py-2.5 pr-4 text-right font-mono text-gray-800">
                <?= $b['active_totale'] !== null ? number_format((float)$b['active_totale'], 0, ',', '.') . ' RON' : '<span class="text-gray-300">—</span>' ?>
              </td>
              <td class="py-2.5 text-right text-gray-700">
                <?= $b['nr_angajati'] !== null ? (int)$b['nr_angajati'] : '<span class="text-gray-300">—</span>' ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <?php if (count($allBalances) >= 2):
        // Mini grafic cifră afaceri cu bare simple
        $maxCA = max(array_map(function($b) { return (float)($b['cifra_afaceri'] ?? 0); }, $allBalances)) ?: 1;
      ?>
      <div class="mt-5 pt-4 border-t border-gray-50">
        <p class="text-xs text-gray-400 mb-3">Evoluție cifră de afaceri</p>
        <div class="flex items-end gap-1.5 h-16">
          <?php foreach ($allBalances as $b):
            $pct = $maxCA > 0 ? max(2, round((float)($b['cifra_afaceri'] ?? 0) / $maxCA * 100)) : 0;
          ?>
          <div class="flex flex-col items-center gap-1 flex-1">
            <div class="w-full rounded-t bg-emerald-400 hover:bg-emerald-500 transition cursor-default"
                 style="height:<?= $pct ?>%"
                 title="<?= htmlspecialchars($b['an']) ?>: <?= number_format((float)($b['cifra_afaceri'] ?? 0), 0, ',', '.') ?> RON"></div>
            <span class="text-xs text-gray-400 writing-mode-vertical" style="font-size:10px"><?= substr($b['an'], 2) ?></span>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

      <p class="text-xs text-gray-400 mt-3">Sursa: openapi.ro / Ministerul Finanțelor Publice</p>
    </div>
    <?php endif; ?>

  </div><!-- end grid -->

  <!-- Surse date -->
  <div class="mt-5 text-xs text-gray-400 text-right">
    Surse: Registrul Național ONG – Ministerul Justiției
    <?= $hasCui ? '· openapi.ro · ANAF' : '' ?>
    · Ultima actualizare: <?= htmlspecialchars(substr($assoc['last_updated'] ?? '', 0, 10) ?: 'necunoscut') ?>
  </div>

<?php endif; ?>
</div>

<?php include __DIR__ . '/inc/footer.php'; ?>
