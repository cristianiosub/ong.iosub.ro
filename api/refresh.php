<?php
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/scrapers.php';
header('Content-Type: application/json');
set_time_limit(60);

$cui = preg_replace('/[^0-9]/', '', $_GET['cui'] ?? '');
$id  = (int)($_GET['id'] ?? 0);

// Dacă avem doar ID, încearcă să găsim CUI din DB sau prin openapi search
if (!$cui && $id) {
    $rec = dbOne("SELECT * FROM `" . T_ASSOC . "` WHERE id = ?", [$id]);
    if ($rec && !empty($rec['cui'])) {
        $cui = $rec['cui'];
    } elseif ($rec) {
        // Încearcă enrichment prin căutare după nume
        $enriched = enrichAssociationRecord($rec);
        if (!empty($enriched['cui'])) {
            $enriched['reg_number'] = $rec['reg_number'];
            saveAssociation($enriched);
            echo json_encode(['success' => true, 'name' => $enriched['name'], 'cui_found' => $enriched['cui']]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Nu am găsit CUI-ul asociației în openapi.ro']);
        }
        exit;
    }
}

if (!$cui) {
    echo json_encode(['success' => false, 'message' => 'CUI lipsă']);
    exit;
}

$data = fetchAllData($cui);
if (!empty($data['name'])) {
    saveAssociation($data);
    echo json_encode(['success' => true, 'name' => $data['name'], 'cui' => $cui]);
} else {
    echo json_encode(['success' => false, 'message' => 'Niciun rezultat de la openapi.ro sau ANAF pentru CUI ' . $cui]);
}
