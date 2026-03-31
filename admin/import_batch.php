<?php
/**
 * import_batch.php — endpoint AJAX pentru import automat batches
 * Apelat de import_auto.php prin fetch()
 */
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/auth.php';
sessionStart(); requireAdmin();

header('Content-Type: application/json');
set_time_limit(120);

$action = $_GET['action'] ?? 'import';
$zipFile      = __DIR__ . '/asociatii_chunks.zip';
$dataDir      = __DIR__ . '/data/chunks';
$metaFile     = __DIR__ . '/data/chunks/meta.json';
$progressFile = __DIR__ . '/data/import_progress.json';

// ── SETUP: Extrage ZIP-ul ─────────────────────────────────────────────────────
if ($action === 'setup') {
    if (!file_exists($zipFile)) {
        echo json_encode(['success' => false, 'message' => 'Fișierul asociatii_chunks.zip lipsește din admin/.']);
        exit;
    }

    if (!class_exists('ZipArchive')) {
        echo json_encode(['success' => false, 'message' => 'Extensia ZipArchive nu este disponibilă pe acest server.']);
        exit;
    }

    // Creare director dacă nu există
    if (!is_dir(__DIR__ . '/data')) mkdir(__DIR__ . '/data', 0755, true);

    $zip = new ZipArchive();
    if ($zip->open($zipFile) !== true) {
        echo json_encode(['success' => false, 'message' => 'Nu s-a putut deschide ZIP-ul.']);
        exit;
    }

    $zip->extractTo(__DIR__ . '/data/');
    $zip->close();

    if (!file_exists($metaFile)) {
        echo json_encode(['success' => false, 'message' => 'ZIP extras, dar meta.json lipsește.']);
        exit;
    }

    // Asigurare schemă DB
    try {
        db()->exec("ALTER TABLE `" . T_ASSOC . "` ADD COLUMN reg_number VARCHAR(60) NULL");
    } catch (\Exception $e) { /* deja există */ }
    try {
        db()->exec("ALTER TABLE `" . T_ASSOC . "` ADD UNIQUE INDEX idx_reg_number (reg_number)");
    } catch (\Exception $e) { /* deja există */ }
    try {
        db()->exec("ALTER TABLE `" . T_ASSOC . "` MODIFY COLUMN cui VARCHAR(20) NULL");
    } catch (\Exception $e) { /* ignorat */ }

    $meta = json_decode(file_get_contents($metaFile), true);
    echo json_encode([
        'success'       => true,
        'total_chunks'  => $meta['total_chunks'],
        'total_records' => $meta['total_records'],
        'message'       => 'ZIP extras cu succes. Gata de import.',
    ]);
    exit;
}

// ── STATUS: Câte înregistrări sunt deja în DB + chunk curent ─────────────────
if ($action === 'status') {
    $count     = dbOne("SELECT COUNT(*) AS c FROM `" . T_ASSOC . "`")['c'] ?? 0;
    $meta      = file_exists($metaFile)     ? json_decode(file_get_contents($metaFile),     true) : null;
    $progress  = file_exists($progressFile) ? json_decode(file_get_contents($progressFile), true) : null;
    $extracted = is_dir($dataDir) && file_exists($metaFile);

    $totalChunks = (int)($meta['total_chunks'] ?? 0);
    $lastChunk   = (int)($progress['last_chunk'] ?? 0);
    $isDone      = !empty($progress['done']);

    echo json_encode([
        'db_count'      => (int)$count,
        'extracted'     => $extracted,
        'total_chunks'  => $totalChunks,
        'total_records' => $meta['total_records'] ?? 0,
        'last_chunk'    => $lastChunk,   // chunk de la care să continue
        'done'          => $isDone,
    ]);
    exit;
}

// ── IMPORT: Procesează chunk-ul N ────────────────────────────────────────────
if ($action === 'import') {
    $chunk = (int)($_GET['chunk'] ?? 0);

    if (!file_exists($metaFile)) {
        echo json_encode(['success' => false, 'message' => 'Datele nu sunt extrase. Rulează setup mai întâi.']);
        exit;
    }

    $meta        = json_decode(file_get_contents($metaFile), true);
    $totalChunks = (int)($meta['total_chunks'] ?? 0);

    if ($chunk >= $totalChunks) {
        echo json_encode(['success' => true, 'done' => true, 'chunk' => $chunk, 'total_chunks' => $totalChunks]);
        exit;
    }

    $chunkFile = sprintf('%s/chunk_%04d.csv', $dataDir, $chunk);
    if (!file_exists($chunkFile)) {
        echo json_encode(['success' => false, 'message' => "Chunk $chunk lipsește: $chunkFile"]);
        exit;
    }

    $imported   = 0;
    $skipped    = 0;
    $errors     = 0;
    $errorMsgs  = [];

    $fh = fopen($chunkFile, 'r');
    if (!$fh) {
        echo json_encode(['success' => false, 'message' => "Nu s-a putut deschide chunk $chunk"]);
        exit;
    }

    // Skip header
    fgetcsv($fh, 0, ';');

    $db = db();

    // Asigurăm schema corectă (auto-fix la fiecare chunk, e rapid dacă nu e nevoie)
    try { $db->exec("ALTER TABLE `" . T_ASSOC . "` ADD COLUMN IF NOT EXISTS reg_number VARCHAR(60) NULL"); } catch (\Exception $e) {}
    try { $db->exec("ALTER TABLE `" . T_ASSOC . "` MODIFY COLUMN cui VARCHAR(20) NULL"); } catch (\Exception $e) {}
    try { $db->exec("ALTER TABLE `" . T_ASSOC . "` MODIFY COLUMN city VARCHAR(500)"); } catch (\Exception $e) {}
    try { $db->exec("ALTER TABLE `" . T_ASSOC . "` MODIFY COLUMN county VARCHAR(255)"); } catch (\Exception $e) {}
    try { $db->exec("ALTER TABLE `" . T_ASSOC . "` MODIFY COLUMN name VARCHAR(500)"); } catch (\Exception $e) {}
    try { $db->exec("ALTER TABLE `" . T_ASSOC . "` MODIFY COLUMN status VARCHAR(100)"); } catch (\Exception $e) {}

    // Statement 1: rânduri CU reg_number → UPSERT pe reg_number UNIQUE
    $stmtWithReg = $db->prepare("
        INSERT INTO `" . T_ASSOC . "`
            (name, reg_number, status, county, city, address, purpose, source, last_updated)
        VALUES (?, ?, ?, ?, ?, ?, ?, 'mj_registry', NOW())
        ON DUPLICATE KEY UPDATE
            name         = VALUES(name),
            status       = VALUES(status),
            county       = VALUES(county),
            city         = VALUES(city),
            address      = VALUES(address),
            purpose      = VALUES(purpose),
            last_updated = NOW()
    ");

    // Statement 2: rânduri FĂRĂ reg_number → INSERT dacă nu există deja un rând cu același nume
    $stmtNoReg = $db->prepare("
        INSERT INTO `" . T_ASSOC . "`
            (name, reg_number, status, county, city, address, purpose, source, last_updated)
        SELECT ?, NULL, ?, ?, ?, ?, ?, 'mj_registry', NOW()
        FROM DUAL
        WHERE NOT EXISTS (
            SELECT 1 FROM `" . T_ASSOC . "` WHERE name = ? AND reg_number IS NULL LIMIT 1
        )
    ");

    $lineNum = 1; // după header
    while (($row = fgetcsv($fh, 0, ';')) !== false) {
        $lineNum++;
        if (count($row) < 2) { $skipped++; continue; }

        // Citire + curățare câmpuri (fără caractere de control)
        $clean = function(?string $s, int $maxLen = 0): ?string {
            if ($s === null) return null;
            $s = trim($s);
            $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $s);
            if ($maxLen > 0 && mb_strlen($s) > $maxLen) $s = mb_substr($s, 0, $maxLen);
            return $s === '' ? null : $s;
        };

        $name    = $clean(trim($row[0] ?? ''), 490);   // name VARCHAR(500)
        $regNum  = $clean(trim($row[1] ?? ''), 55);    // reg_number VARCHAR(60)
        $status  = $clean(trim($row[2] ?? ''), 45);    // status VARCHAR(50)
        $county  = $clean(trim($row[3] ?? ''), 140);   // county VARCHAR(150)
        $city    = $clean(trim($row[4] ?? ''), 240);   // city VARCHAR(255)
        $address = $clean(trim($row[5] ?? ''));         // address TEXT – nelimitat
        $purpose = $clean(trim($row[6] ?? ''));         // purpose TEXT – nelimitat

        // Sărind DOAR rândurile fără nume (cele fără reg_number le importăm cu NULL)
        if (!$name) { $skipped++; continue; }

        try {
            if ($regNum !== null) {
                $stmtWithReg->execute([$name, $regNum, $status, $county, $city, $address, $purpose]);
            } else {
                // Fără reg_number: inserăm doar dacă nu există deja un rând cu același nume și reg_number NULL
                $stmtNoReg->execute([$name, $status, $county, $city, $address, $purpose, $name]);
            }
            $imported++;
        } catch (\Exception $e) {
            $errors++;
            // Colectăm primele 5 mesaje de eroare pentru diagnosticare
            if (count($errorMsgs) < 5) {
                $errorMsgs[] = "Linia $lineNum [" . substr($name, 0, 30) . "]: " . $e->getMessage();
            }
        }
    }

    fclose($fh);

    $nextChunk = $chunk + 1;
    $done      = ($nextChunk >= $totalChunks);

    // Salvăm progresul pe server (persistent peste reload-uri)
    file_put_contents($progressFile, json_encode([
        'last_chunk'  => $done ? $totalChunks : $nextChunk,
        'done'        => $done,
        'updated_at'  => date('Y-m-d H:i:s'),
    ]));

    echo json_encode([
        'success'      => true,
        'done'         => $done,
        'chunk'        => $chunk,
        'next_chunk'   => $nextChunk,
        'total_chunks' => $totalChunks,
        'imported'     => $imported,
        'skipped'      => $skipped,
        'errors'       => $errors,
        'error_msgs'   => $errorMsgs,
    ]);
    exit;
}

// ── CLEANUP: Șterge fișierele extrase ────────────────────────────────────────
if ($action === 'cleanup') {
    function rmdirRecursive(string $dir): void {
        if (!is_dir($dir)) return;
        foreach (scandir($dir) as $f) {
            if ($f === '.' || $f === '..') continue;
            $path = "$dir/$f";
            is_dir($path) ? rmdirRecursive($path) : unlink($path);
        }
        rmdir($dir);
    }
    rmdirRecursive(__DIR__ . '/data');
    echo json_encode(['success' => true, 'message' => 'Fișierele temporare au fost șterse.']);
    exit;
}

// ── RESET PROGRESS: Șterge fișierul de progres → reimport de la 0 ────────────
if ($action === 'reset_progress') {
    if (file_exists($progressFile)) unlink($progressFile);
    echo json_encode(['success' => true, 'message' => 'Progresul a fost resetat.']);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Acțiune necunoscută.']);
