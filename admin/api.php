<?php
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/auth.php';
sessionStart(); requireAdmin();

$action = $_GET['action'] ?? '';

// ── Export CSV ────────────────────────────────────────────────────────────────
if ($action === 'export') {
    $rows = dbAll("SELECT name, cui, address, county, city, status, registration_date,
                          legal_form, caen_code, caen_description, phone, email, website, last_updated
                   FROM `" . T_ASSOC . "` ORDER BY name");

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="asociatii_' . date('Y-m-d') . '.csv"');

    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF)); // UTF-8 BOM
    fputcsv($out, ['Denumire','CUI','Adresă','Județ','Localitate','Status',
                   'Data înregistrării','Formă juridică','Cod CAEN','Descriere CAEN',
                   'Telefon','Email','Website','Ultima actualizare'], ';');
    foreach ($rows as $r) {
        fputcsv($out, array_values($r), ';');
    }
    fclose($out);
    exit;
}

// ── Răspunsuri JSON ───────────────────────────────────────────────────────────
header('Content-Type: application/json');

if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_GET['id'] ?? 0);
    if ($id > 0) {
        dbExec("DELETE FROM `" . T_ASSOC . "` WHERE id = ?", [$id]);
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false]);
    }
    exit;
}

if ($action === 'clear_searches' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    dbExec("DELETE FROM `" . T_SEARCH . "`");
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'change_password' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $current = $_POST['current'] ?? '';
    $new     = $_POST['new'] ?? '';

    if (strlen($new) < 6) {
        echo json_encode(['success' => false, 'message' => 'Parola nouă trebuie să aibă minim 6 caractere.']);
        exit;
    }

    $user = dbOne("SELECT * FROM `" . T_ADMIN . "` WHERE username = ?", [adminUsername()]);
    if ($user && password_verify($current, $user['password_hash'])) {
        $hash = password_hash($new, PASSWORD_DEFAULT);
        dbExec("UPDATE `" . T_ADMIN . "` SET password_hash = ? WHERE username = ?", [$hash, adminUsername()]);
        echo json_encode(['success' => true, 'message' => 'Parola a fost schimbată cu succes!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Parola curentă este incorectă.']);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Acțiune necunoscută.']);
