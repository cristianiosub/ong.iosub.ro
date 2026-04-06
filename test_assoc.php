<?php
// Test pas cu pas logica din asociatie.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Step 1: config\n";
require_once __DIR__ . '/inc/config.php';
echo "OK\n";

echo "Step 2: db\n";
require_once __DIR__ . '/inc/db.php';
echo "OK\n";

echo "Step 3: scrapers\n";
require_once __DIR__ . '/inc/scrapers.php';
echo "OK\n";

echo "Step 4: initDb\n";
initDb();
echo "OK\n";

echo "Step 5: query id=77746\n";
$cached = dbOne("SELECT * FROM `" . T_ASSOC . "` WHERE id = ?", [77746]);
echo "Gasit: " . ($cached ? "DA, name=" . $cached['name'] : "NU") . "\n";

echo "Step 6: json decode representatives\n";
$assoc = $cached;
$assoc['representatives'] = is_array($assoc['representatives'] ?? null)
    ? $assoc['representatives']
    : json_decode($assoc['representatives'] ?? '[]', true) ?: [];
echo "OK: " . count($assoc['representatives']) . " reprezentanti\n";

echo "Step 7: json decode founders\n";
$assoc['founders'] = is_array($assoc['founders'] ?? null)
    ? $assoc['founders']
    : json_decode($assoc['founders'] ?? '[]', true) ?: [];
echo "OK: " . count($assoc['founders']) . " fondatori\n";

echo "Step 8: fixDiacritics\n";
function fixDiacritics(?string $s): string {
    if ($s === null) return '';
    $map = ['ÃŽ'=>'Î','Ã®'=>'î','Ã‚'=>'Â','Ã¢'=>'â'];
    return strtr($s, $map);
}
$pageTitle = fixDiacritics($assoc['name'] ?? '') . ' – ' . SITE_NAME;
echo "pageTitle=$pageTitle\n";

echo "Step 9: include header.php\n";
ob_start();
include __DIR__ . '/inc/header.php';
$headerOut = ob_get_clean();
echo "Header OK, " . strlen($headerOut) . " bytes\n";

echo "Step 10: statusMjLabel\n";
function statusMjLabel(?string $s): array {
    switch (strtolower(trim($s ?? ''))) {
        case '': return ['label'=>'Activa','cls'=>'bg-green-100','dot'=>'bg-green-400'];
        default: return ['label'=>ucfirst((string)$s),'cls'=>'bg-gray-100','dot'=>'bg-gray-400'];
    }
}
$statusMj = statusMjLabel($assoc['status'] ?? '');
echo "status label=" . $statusMj['label'] . "\n";

echo "Step 11: all_balances\n";
$allBalances = [];
if (!empty($assoc['all_balances']) && is_array($assoc['all_balances'])) {
    $allBalances = $assoc['all_balances'];
} elseif (!empty($assoc['raw_data'])) {
    $raw = is_array($assoc['raw_data']) ? $assoc['raw_data'] : json_decode($assoc['raw_data'], true);
    if (!empty($raw['all_balances'])) $allBalances = $raw['all_balances'];
}
usort($allBalances, function($a, $b) { return strcmp($a['an'] ?? '', $b['an'] ?? ''); });
echo "allBalances count=" . count($allBalances) . "\n";

echo "\nTOT OK - problema nu e in logica de baza!\n";
echo "Incearca acum sa incluzi footer:\n";

echo "Step 12: include footer.php\n";
ob_start();
include __DIR__ . '/inc/footer.php';
$footerOut = ob_get_clean();
echo "Footer OK, " . strlen($footerOut) . " bytes\n";

echo "\nDIAGNOSTIC COMPLET TRECUT!\n";
