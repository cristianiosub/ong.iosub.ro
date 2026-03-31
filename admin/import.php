<?php
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/scrapers.php';
initDb(); sessionStart(); requireAdmin();

$pageTitle = 'Import asociații';

// ── Procesare import ──────────────────────────────────────────────────────────
$results   = [];
$processed = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mode = $_POST['mode'] ?? 'cui';
    $raw  = trim($_POST['lista'] ?? '');
    $lines = array_filter(array_map('trim', explode("\n", $raw)));

    if ($mode === 'cui') {
        // Import după CUI
        set_time_limit(300); // 5 minute pentru import mare
        foreach ($lines as $line) {
            $cui = preg_replace('/[^0-9]/', '', $line);
            if (!$cui || strlen($cui) < 5) continue;

            // Verifică dacă există deja
            $exists = dbOne("SELECT id, name FROM `" . T_ASSOC . "` WHERE cui = ?", [$cui]);
            if ($exists) {
                $results[] = ['cui' => $cui, 'name' => $exists['name'], 'status' => 'exists', 'msg' => 'Deja în baza de date'];
                continue;
            }

            // Fetch din ANAF (rapid, sigur)
            $data = getAnafData($cui);
            if ($data && !empty($data['name'])) {
                saveAssociation($data);
                $results[] = ['cui' => $cui, 'name' => $data['name'], 'status' => 'ok', 'msg' => 'Importat ✓'];
            } else {
                $results[] = ['cui' => $cui, 'name' => '', 'status' => 'notfound', 'msg' => 'CUI negăsit în ANAF'];
            }

            // Pauza mica sa nu supraincarcam ANAF
            usleep(200000); // 0.2 secunde
        }
    } elseif ($mode === 'name') {
        // Import după nume — cauta pe totalfirme, ia primele rezultate
        set_time_limit(300);
        foreach ($lines as $name) {
            if (strlen($name) < 3) continue;
            $found = searchAssociations($name);
            foreach ($found as $r) {
                $exists = dbOne("SELECT id FROM `" . T_ASSOC . "` WHERE cui = ?", [$r['cui']]);
                if ($exists) {
                    $results[] = ['cui' => $r['cui'], 'name' => $r['name'], 'status' => 'exists', 'msg' => 'Deja în baza de date'];
                    continue;
                }
                $data = getAnafData($r['cui']);
                if ($data && !empty($data['name'])) {
                    saveAssociation($data);
                    $results[] = ['cui' => $r['cui'], 'name' => $data['name'], 'status' => 'ok', 'msg' => 'Importat ✓'];
                } else {
                    // Salvam macar ce stim din totalfirme
                    saveAssociation($r);
                    $results[] = ['cui' => $r['cui'], 'name' => $r['name'], 'status' => 'partial', 'msg' => 'Importat parțial (fără ANAF)'];
                }
                usleep(300000);
            }
        }
    } elseif ($mode === 'csv' && !empty($_FILES['csv_file']['tmp_name'])) {
        // Import din CSV cu coloane: CUI sau CUI,Nume
        set_time_limit(300);
        $handle = fopen($_FILES['csv_file']['tmp_name'], 'r');
        $firstRow = true;
        while (($row = fgetcsv($handle, 1000, ';')) !== false) {
            if ($firstRow && !is_numeric(preg_replace('/[^0-9]/', '', $row[0]))) {
                $firstRow = false; continue; // skip header
            }
            $firstRow = false;
            $cui = preg_replace('/[^0-9]/', '', $row[0] ?? '');
            if (!$cui || strlen($cui) < 5) continue;

            $exists = dbOne("SELECT id, name FROM `" . T_ASSOC . "` WHERE cui = ?", [$cui]);
            if ($exists) {
                $results[] = ['cui' => $cui, 'name' => $exists['name'], 'status' => 'exists', 'msg' => 'Deja în baza de date'];
                continue;
            }

            $data = getAnafData($cui);
            if ($data && !empty($data['name'])) {
                saveAssociation($data);
                $results[] = ['cui' => $cui, 'name' => $data['name'], 'status' => 'ok', 'msg' => 'Importat ✓'];
            } else {
                $results[] = ['cui' => $cui, 'name' => $row[1] ?? '', 'status' => 'notfound', 'msg' => 'Negăsit în ANAF'];
            }
            usleep(200000);
        }
        fclose($handle);
    }

    $processed = true;
}

$ok      = count(array_filter($results, function($r) { return $r['status'] === 'ok'; }));
$partial = count(array_filter($results, function($r) { return $r['status'] === 'partial'; }));
$exists  = count(array_filter($results, function($r) { return $r['status'] === 'exists'; }));
$nf      = count(array_filter($results, function($r) { return $r['status'] === 'notfound'; }));

ob_start();
?>
<div class="max-w-4xl mx-auto">
  <h1 class="text-2xl font-bold text-gray-800 mb-2">Import asociații</h1>
  <p class="text-sm text-gray-500 mb-6">Populează baza de date cu asociații din România.</p>

  <!-- INFO BOX -->
  <div class="bg-blue-50 border border-blue-200 rounded-2xl p-5 mb-6">
    <h2 class="font-semibold text-blue-800 mb-2">📂 De unde iei CUI-urile?</h2>
    <div class="text-sm text-blue-700 space-y-2">
      <p><strong>Opțiunea 1 – ANAF Open Data (recomandat pentru import masiv):</strong><br>
        Descarcă fișierul de la
        <a href="https://data.anaf.ro/dataset/lista-platitori-inregistrati-in-scopuri-tva" target="_blank" class="underline">data.anaf.ro</a>
        sau caută pe <a href="https://openapi.anaf.ro/" target="_blank" class="underline">openapi.anaf.ro</a>.
        Filtrează după câmpul "forma_juridica" = "A" (Asociație) / "F" (Fundație).
      </p>
      <p><strong>Opțiunea 2 – totalfirme.ro:</strong><br>
        Caută "asociatia" sau "fundatia" pe site, copiază CUI-urile din pagini.
      </p>
      <p><strong>Opțiunea 3 – propria ta listă:</strong><br>
        Dacă lucrezi cu anumite asociații, tastează direct CUI-urile sau numele lor mai jos.
      </p>
    </div>
  </div>

  <?php if ($processed): ?>
  <!-- REZULTATE -->
  <div class="bg-white rounded-2xl shadow-sm p-6 mb-6">
    <h2 class="font-semibold text-gray-800 mb-4">Rezultate import</h2>
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-5">
      <div class="bg-green-50 rounded-xl p-3 text-center">
        <p class="text-2xl font-bold text-green-700"><?= $ok ?></p>
        <p class="text-xs text-green-600">Importate</p>
      </div>
      <div class="bg-yellow-50 rounded-xl p-3 text-center">
        <p class="text-2xl font-bold text-yellow-700"><?= $partial ?></p>
        <p class="text-xs text-yellow-600">Parțial</p>
      </div>
      <div class="bg-gray-50 rounded-xl p-3 text-center">
        <p class="text-2xl font-bold text-gray-600"><?= $exists ?></p>
        <p class="text-xs text-gray-500">Existente</p>
      </div>
      <div class="bg-red-50 rounded-xl p-3 text-center">
        <p class="text-2xl font-bold text-red-600"><?= $nf ?></p>
        <p class="text-xs text-red-500">Negăsite</p>
      </div>
    </div>

    <div class="overflow-x-auto max-h-96 overflow-y-auto">
      <table class="w-full text-sm">
        <thead class="sticky top-0 bg-white">
          <tr class="border-b border-gray-100">
            <th class="pb-2 text-left text-xs text-gray-400 font-medium">CUI</th>
            <th class="pb-2 text-left text-xs text-gray-400 font-medium">Denumire</th>
            <th class="pb-2 text-left text-xs text-gray-400 font-medium">Status</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
          <?php foreach ($results as $r): ?>
          <tr>
            <td class="py-2 font-mono text-xs text-gray-500"><?= htmlspecialchars($r['cui']) ?></td>
            <td class="py-2 text-gray-800">
              <?php if ($r['name'] && $r['status'] !== 'notfound'): ?>
                <a href="/asociatie.php?cui=<?= urlencode($r['cui']) ?>" target="_blank" class="hover:text-indigo-600">
                  <?= htmlspecialchars($r['name']) ?>
                </a>
              <?php else: ?>
                <span class="text-gray-400"><?= htmlspecialchars($r['name'] ?: '—') ?></span>
              <?php endif; ?>
            </td>
            <td class="py-2">
              <span class="text-xs font-medium px-2 py-0.5 rounded-full
                <?php
                switch ($r['status']) {
                  case 'ok':      echo 'bg-green-100 text-green-700';  break;
                  case 'partial': echo 'bg-yellow-100 text-yellow-700'; break;
                  case 'exists':  echo 'bg-gray-100 text-gray-600';    break;
                  default:        echo 'bg-red-100 text-red-600';
                }
                ?>">
                <?= htmlspecialchars($r['msg']) ?>
              </span>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php endif; ?>

  <!-- FORM IMPORT -->
  <div class="bg-white rounded-2xl shadow-sm p-6" x-data="{ tab: 'cui' }">
    <h2 class="font-semibold text-gray-800 mb-4">Adaugă asociații</h2>

    <!-- TABS -->
    <div class="flex gap-1 mb-6 bg-gray-100 p-1 rounded-xl">
      <?php foreach ([
        ['cui',  '🔢 Listă CUI-uri'],
        ['name', '🔤 Căutare după nume'],
        ['csv',  '📁 Upload CSV'],
      ] as [$t, $label]): ?>
      <button @click="tab='<?= $t ?>'"
        :class="tab==='<?= $t ?>' ? 'bg-white shadow text-gray-800' : 'text-gray-500 hover:text-gray-700'"
        class="flex-1 py-2 px-3 rounded-lg text-sm font-medium transition">
        <?= $label ?>
      </button>
      <?php endforeach; ?>
    </div>

    <!-- TAB: CUI -->
    <div x-show="tab==='cui'">
      <form method="POST" class="space-y-4">
        <input type="hidden" name="mode" value="cui"/>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">
            CUI-uri (unul pe linie) <span class="text-gray-400 font-normal">— Ex: 48106635, RO45115272</span>
          </label>
          <textarea name="lista" rows="10" placeholder="48106635&#10;45115272&#10;12345678&#10;..."
            class="w-full border border-gray-200 rounded-xl px-4 py-3 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-indigo-400 resize-y"></textarea>
        </div>
        <div class="bg-amber-50 border border-amber-200 rounded-xl p-3 text-xs text-amber-700">
          ⚠️ Importul poate dura câteva minute dacă ai multe CUI-uri. Nu închide pagina.
          Recomandăm maxim <strong>50 CUI-uri per import</strong> pentru a evita timeout.
        </div>
        <button type="submit"
          class="bg-indigo-600 hover:bg-indigo-700 text-white font-medium px-6 py-2.5 rounded-xl transition text-sm">
          Importă din ANAF
        </button>
      </form>
    </div>

    <!-- TAB: NUME -->
    <div x-show="tab==='name'" x-cloak>
      <form method="POST" class="space-y-4">
        <input type="hidden" name="mode" value="name"/>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">
            Nume asociații (unul pe linie) <span class="text-gray-400 font-normal">— caută pe totalfirme.ro</span>
          </label>
          <textarea name="lista" rows="8" placeholder="Asociatia Educatie in Securitate Cibernetica&#10;Fundatia Hecate&#10;..."
            class="w-full border border-gray-200 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400 resize-y"></textarea>
        </div>
        <div class="bg-amber-50 border border-amber-200 rounded-xl p-3 text-xs text-amber-700">
          ⚠️ Căutarea după nume este mai lentă (scraping live). Maxim <strong>10 nume per import</strong>.
        </div>
        <button type="submit"
          class="bg-indigo-600 hover:bg-indigo-700 text-white font-medium px-6 py-2.5 rounded-xl transition text-sm">
          Caută și importă
        </button>
      </form>
    </div>

    <!-- TAB: CSV -->
    <div x-show="tab==='csv'" x-cloak>
      <form method="POST" enctype="multipart/form-data" class="space-y-4">
        <input type="hidden" name="mode" value="csv"/>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">
            Fișier CSV cu CUI-uri
          </label>
          <div class="border-2 border-dashed border-gray-300 rounded-xl p-8 text-center hover:border-indigo-400 transition">
            <input type="file" name="csv_file" accept=".csv,.txt" class="hidden" id="csv-input"/>
            <label for="csv-input" class="cursor-pointer">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 mx-auto text-gray-400 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                  d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
              </svg>
              <p class="text-sm text-gray-500">Click pentru a selecta fișierul CSV</p>
              <p class="text-xs text-gray-400 mt-1">Format: CUI;Nume (separator ; sau ,)</p>
            </label>
            <p id="csv-name" class="mt-3 text-sm text-indigo-600 hidden"></p>
          </div>
        </div>
        <div class="bg-gray-50 rounded-xl p-4 text-xs text-gray-600">
          <p class="font-medium mb-1">Format CSV acceptat:</p>
          <pre class="text-gray-500">48106635;Asociatia Educatie...
45115272;Asociatia Hecate
12345678</pre>
          <p class="mt-2">Primul rând poate fi header (va fi ignorat dacă prima coloană nu e număr).</p>
        </div>
        <button type="submit"
          class="bg-indigo-600 hover:bg-indigo-700 text-white font-medium px-6 py-2.5 rounded-xl transition text-sm">
          Importă din CSV
        </button>
      </form>
    </div>
  </div>

  <!-- QUICK TIP -->
  <div class="mt-5 bg-indigo-50 border border-indigo-100 rounded-2xl p-5">
    <h3 class="font-semibold text-indigo-800 mb-2">💡 Cum obții rapid o listă de CUI-uri de asociații</h3>
    <ol class="text-sm text-indigo-700 space-y-1 list-decimal list-inside">
      <li>Mergi la <a href="https://www.totalfirme.ro" target="_blank" class="underline">totalfirme.ro</a> și caută "asociatia" sau "fundatia"</li>
      <li>Copiază CUI-urile din URL-urile rezultatelor (ex: <code class="bg-white/60 px-1 rounded">...-<strong>48106635</strong></code>)</li>
      <li>Lipește-le în câmpul de mai sus, unul pe linie</li>
      <li>Sau descarcă fișierele open data de la <a href="https://openapi.anaf.ro/" target="_blank" class="underline">openapi.anaf.ro</a></li>
    </ol>
  </div>
</div>

<script>
document.getElementById('csv-input').addEventListener('change', function() {
  const label = document.getElementById('csv-name');
  if (this.files[0]) {
    label.textContent = '✓ ' + this.files[0].name;
    label.classList.remove('hidden');
  }
});
</script>
<?php
$pageContent = ob_get_clean();
include __DIR__ . '/_layout.php';
