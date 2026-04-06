<?php
// TEMPORAR – STERGE DUPA DEBUG
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

echo '<pre>';
echo "PHP version: " . PHP_VERSION . "\n\n";

// Test 1: config
echo "--- inc/config.php ---\n";
try {
    require_once __DIR__ . '/inc/config.php';
    echo "OK\n\n";
} catch (\Throwable $e) {
    echo "EROARE: " . $e->getMessage() . " (linia " . $e->getLine() . " in " . $e->getFile() . ")\n\n";
}

// Test 2: db
echo "--- inc/db.php ---\n";
try {
    require_once __DIR__ . '/inc/db.php';
    echo "OK\n\n";
} catch (\Throwable $e) {
    echo "EROARE: " . $e->getMessage() . " (linia " . $e->getLine() . " in " . $e->getFile() . ")\n\n";
}

// Test 3: scrapers
echo "--- inc/scrapers.php ---\n";
try {
    require_once __DIR__ . '/inc/scrapers.php';
    echo "OK\n\n";
} catch (\Throwable $e) {
    echo "EROARE: " . $e->getMessage() . " (linia " . $e->getLine() . " in " . $e->getFile() . ")\n\n";
}

// Test 4: initDb
echo "--- initDb() ---\n";
try {
    initDb();
    echo "OK\n\n";
} catch (\Throwable $e) {
    echo "EROARE: " . $e->getMessage() . " (linia " . $e->getLine() . " in " . $e->getFile() . ")\n\n";
}

// Test 5: query DB
echo "--- SELECT din ong_associations WHERE id=77746 ---\n";
try {
    $row = dbOne("SELECT id, name, cui, reg_number, status FROM `" . T_ASSOC . "` WHERE id = ?", [77746]);
    if ($row) {
        echo "Gasit: " . print_r($row, true) . "\n";
    } else {
        echo "Nu exista inregistrarea cu id=77746\n";
    }
} catch (\Throwable $e) {
    echo "EROARE: " . $e->getMessage() . " (linia " . $e->getLine() . " in " . $e->getFile() . ")\n\n";
}

// Test 6: flag files
echo "--- Flag files ---\n";
echo ".db_initialized: " . (file_exists(__DIR__ . '/.db_initialized') ? 'DA (' . file_get_contents(__DIR__ . '/.db_initialized') . ')' : 'NU') . "\n";
echo ".db_migrated_v2: " . (file_exists(__DIR__ . '/.db_migrated_v2') ? 'DA (' . file_get_contents(__DIR__ . '/.db_migrated_v2') . ')' : 'NU') . "\n";

// Test 7: columna city – ce dimensiune are in DB + forteaza ALTER daca e prea mica
echo "\n--- Schema + fix coloane ---\n";
try {
    $col = dbOne("SELECT CHARACTER_MAXIMUM_LENGTH FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND COLUMN_NAME='city'", [DB_NAME, T_ASSOC]);
    $cityLen = (int)($col['CHARACTER_MAXIMUM_LENGTH'] ?? 0);
    echo "city VARCHAR($cityLen)\n";
    if ($cityLen < 255) {
        db()->exec("ALTER TABLE `" . T_ASSOC . "` MODIFY COLUMN city VARCHAR(500)");
        echo "→ city largit la VARCHAR(500)\n";
    }

    $col2 = dbOne("SELECT CHARACTER_MAXIMUM_LENGTH FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND COLUMN_NAME='county'", [DB_NAME, T_ASSOC]);
    $countyLen = (int)($col2['CHARACTER_MAXIMUM_LENGTH'] ?? 0);
    echo "county VARCHAR($countyLen)\n";
    if ($countyLen < 255) {
        db()->exec("ALTER TABLE `" . T_ASSOC . "` MODIFY COLUMN county VARCHAR(255)");
        echo "→ county largit la VARCHAR(255)\n";
    }

    $col3 = dbOne("SELECT CHARACTER_MAXIMUM_LENGTH FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND COLUMN_NAME='name'", [DB_NAME, T_ASSOC]);
    echo "name VARCHAR(" . ($col3['CHARACTER_MAXIMUM_LENGTH'] ?? '?') . ")\n";
} catch (\Throwable $e) { echo "EROARE: " . $e->getMessage() . "\n"; }

// Test 8: simuleaza asociatie.php pas cu pas
echo "\n--- Simulare asociatie.php (id=77746) ---\n";
try {
    $cached = dbOne("SELECT * FROM `" . T_ASSOC . "` WHERE id = ?", [77746]);
    echo "cached: " . ($cached ? 'DA' : 'NU') . "\n";

    if ($cached) {
        $age = $cached['last_updated'] ? time() - strtotime($cached['last_updated']) : PHP_INT_MAX;
        echo "age: $age sec, cache_days: " . (CACHE_DAYS * 86400) . "\n";

        $assoc = null;
        if ($age < CACHE_DAYS * 86400) {
            $assoc = $cached;
            echo "Din cache\n";
        }
        if (!$assoc) {
            $assoc = $cached;
            echo "Direct din DB (cache expirat)\n";
        }

        // Decode JSON
        $assoc['representatives'] = is_array($assoc['representatives'] ?? null)
            ? $assoc['representatives']
            : (json_decode($assoc['representatives'] ?? '[]', true) ?: []);
        $assoc['founders'] = is_array($assoc['founders'] ?? null)
            ? $assoc['founders']
            : (json_decode($assoc['founders'] ?? '[]', true) ?: []);
        echo "representatives: " . count($assoc['representatives']) . "\n";
        echo "founders: " . count($assoc['founders']) . "\n";

        // raw_data size
        $rdSize = strlen($assoc['raw_data'] ?? '');
        echo "raw_data size: $rdSize bytes\n";

        // all_balances
        $allBalances = [];
        if (!empty($assoc['all_balances']) && is_array($assoc['all_balances'])) {
            $allBalances = $assoc['all_balances'];
        } elseif (!empty($assoc['raw_data'])) {
            $raw = is_array($assoc['raw_data']) ? $assoc['raw_data'] : json_decode($assoc['raw_data'], true);
            if (!empty($raw['all_balances'])) $allBalances = $raw['all_balances'];
        }
        usort($allBalances, function($a, $b) { return strcmp($a['an'] ?? '', $b['an'] ?? ''); });
        echo "all_balances: " . count($allBalances) . "\n";

        echo "STATUS TEST OK - logica de baza functioneaza\n";
    }
} catch (\Throwable $e) {
    echo "EROARE FATALA: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine() . "\n";
}

// Test 9: incearca sa includa header.php
echo "\n--- Test include header.php ---\n";
try {
    $pageTitle = 'Test';
    ob_start();
    include __DIR__ . '/inc/header.php';
    $out = ob_get_clean();
    echo "header.php OK (" . strlen($out) . " bytes)\n";
} catch (\Throwable $e) {
    ob_end_clean();
    echo "EROARE header.php: " . $e->getMessage() . " linia " . $e->getLine() . "\n";
}

// Test 10: include footer.php
echo "\n--- Test include footer.php ---\n";
try {
    ob_start();
    include __DIR__ . '/inc/footer.php';
    $out = ob_get_clean();
    echo "footer.php OK (" . strlen($out) . " bytes)\n";
} catch (\Throwable $e) {
    ob_end_clean();
    echo "EROARE footer.php: " . $e->getMessage() . " linia " . $e->getLine() . "\n";
}

// Test 11: openapi.ro search pentru ONG-ul din id=77746
echo "\n--- Test openapi.ro search (id=77746) ---\n";
try {
    $rec = dbOne("SELECT id, name, cui, reg_number FROM `" . T_ASSOC . "` WHERE id = ?", [77746]);
    $name = $rec['name'] ?? '';
    echo "Cautam: '$name'\n";

    // Apel direct POST /api/companies/search
    $body = json_encode(['q' => substr(trim($name), 0, 80), 'include_radiata' => false]);
    $ch = curl_init(OPENAPI_BASE . '/api/companies/search');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $body,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HTTPHEADER     => ['x-api-key: ' . OPENAPI_KEY, 'Accept: application/json', 'Content-Type: application/json'],
    ]);
    $raw = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    echo "HTTP $httpCode\n";
    if ($curlErr) echo "cURL error: $curlErr\n";
    echo "Raspuns raw (primii 500 chars):\n" . substr($raw, 0, 500) . "\n";

    $json = json_decode($raw, true);
    $list = $json['data'] ?? [];
    echo "Rezultate gasite: " . count($list) . "\n";
    foreach ($list as $i => $item) {
        echo "  [$i] CIF={$item['cif']} | {$item['denumire']} | {$item['judet']}\n";
        if ($i >= 4) { echo "  ...\n"; break; }
    }
} catch (\Throwable $e) {
    echo "EROARE: " . $e->getMessage() . "\n";
}

// Test 12: incearca si cu primele 3 cuvinte din nume
echo "\n--- Test openapi.ro cu primele 3 cuvinte ---\n";
try {
    $rec = dbOne("SELECT name FROM `" . T_ASSOC . "` WHERE id = ?", [77746]);
    $words = explode(' ', trim($rec['name'] ?? ''));
    $short = implode(' ', array_slice($words, 0, 3));
    echo "Cautam scurt: '$short'\n";

    $body = json_encode(['q' => $short, 'include_radiata' => false]);
    $ch = curl_init(OPENAPI_BASE . '/api/companies/search');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $body,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HTTPHEADER     => ['x-api-key: ' . OPENAPI_KEY, 'Accept: application/json', 'Content-Type: application/json'],
    ]);
    $raw = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    echo "HTTP $httpCode\n";
    $json = json_decode($raw, true);
    $list = $json['data'] ?? [];
    echo "Rezultate: " . count($list) . "\n";
    foreach ($list as $i => $item) {
        echo "  [$i] CIF={$item['cif']} | {$item['denumire']}\n";
        if ($i >= 9) { echo "  ...\n"; break; }
    }
} catch (\Throwable $e) {
    echo "EROARE: " . $e->getMessage() . "\n";
}

echo "\n--- GATA ---\n";
echo '</pre>';
