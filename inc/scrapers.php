<?php
// ── HTTP helpers ──────────────────────────────────────────────────────────────
function httpGet(string $url, array $extraHeaders = []): string {
    $headers = array_merge([
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/123.0.0.0 Safari/537.36',
        'Accept-Language: ro-RO,ro;q=0.9,en;q=0.8',
        'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
        'Referer: https://www.google.ro/',
    ], $extraHeaders);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_ENCODING       => 'gzip, deflate',
    ]);
    $result = curl_exec($ch);
    curl_close($ch);
    return $result ?: '';
}

function httpPost(string $url, string $body, array $extraHeaders = []): string {
    $headers = array_merge(['Content-Type: application/json'], $extraHeaders);
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $body,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HTTPHEADER     => $headers,
    ]);
    $result = curl_exec($ch);
    curl_close($ch);
    return $result ?: '';
}

// ── HTML parser helpers ───────────────────────────────────────────────────────
function parseDom(string $html): DOMXPath {
    $doc = new DOMDocument();
    libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="utf-8" ?>' . $html);
    libxml_clear_errors();
    return new DOMXPath($doc);
}

function xpathText(DOMXPath $xp, string $query, DOMNode $ctx = null): string {
    $nodes = $ctx ? $xp->query($query, $ctx) : $xp->query($query);
    return $nodes && $nodes->length > 0 ? trim($nodes->item(0)->textContent) : '';
}

function xpathAttr(DOMXPath $xp, string $query, string $attr, DOMNode $ctx = null): string {
    $nodes = $ctx ? $xp->query($query, $ctx) : $xp->query($query);
    if ($nodes && $nodes->length > 0) {
        return trim($nodes->item(0)->getAttribute($attr));
    }
    return '';
}

// ── openapi.ro ────────────────────────────────────────────────────────────────
function openApiHeaders(): array {
    return [
        'x-api-key: ' . OPENAPI_KEY,
        'Accept: application/json',
        'Content-Type: application/json',
    ];
}

/**
 * Caută asociații/firme după nume.
 * Returnează array de ['name', 'cui', 'county'] cu max 15 rezultate.
 */
function searchOpenApi(string $query): array {
    $body = json_encode([
        'q'               => $query,
        'include_radiata' => false,
    ]);
    $raw  = httpPost(OPENAPI_BASE . '/api/companies/search', $body, openApiHeaders());
    if (!$raw) return [];

    $json = json_decode($raw, true);
    $list = $json['data'] ?? [];
    if (!is_array($list)) return [];

    $results = [];
    foreach ($list as $item) {
        if (empty($item['cif'])) continue;
        $results[] = [
            'name'   => trim($item['denumire'] ?? ''),
            'cui'    => (string)$item['cif'],
            'county' => trim($item['judet'] ?? ''),
            'source' => 'openapi.ro',
        ];
        if (count($results) >= 15) break;
    }
    return $results;
}

/**
 * Preia detalii companie/asociație după CUI de la openapi.ro.
 * Returnează array complet cu TOATE câmpurile disponibile.
 */
function getOpenApiDetails(string $cui): ?array {
    $cuiInt = intval(preg_replace('/[^0-9]/', '', $cui));
    if ($cuiInt <= 0) return null;

    $raw  = httpGet(OPENAPI_BASE . '/api/companies/' . $cuiInt, openApiHeaders());
    if (!$raw) return null;

    $http_code = null;
    // Verificăm dacă e JSON valid
    $json = json_decode($raw, true);
    if (empty($json) || isset($json['error']) || isset($json['message'])) return null;

    $d = $json;

    // ── Status ───────────────────────────────────────────────────────────────
    if (!empty($d['radiata'])) {
        $status = 'Radiată';
    } elseif (!empty($d['stare'])) {
        $status = trim($d['stare']); // ex: "INREGISTRAT din data 05 Mai 2023"
    } else {
        $status = 'Activă';
    }

    // ── Localitate: ultimul element din adresă (după ultimul virgulă) ─────────
    $adresa = trim($d['adresa'] ?? '');
    $city   = '';
    if ($adresa) {
        $parts = explode(',', $adresa);
        $city  = trim(end($parts));
        if (strlen($city) > 50) $city = ''; // prea lung = nu e localitate
    }

    // ── TVA la încasare – verificăm dacă e curent activ ──────────────────────
    $tvaLaIncasare = false;
    if (!empty($d['tva_la_incasare']) && is_array($d['tva_la_incasare'])) {
        foreach ($d['tva_la_incasare'] as $tvaEntry) {
            if (empty($tvaEntry['data_sfarsit'])) {
                $tvaLaIncasare = true;
                break;
            }
        }
    }

    $result = [
        // Identificare
        'name'              => trim($d['denumire'] ?? ''),
        'cui'               => (string)$cuiInt,
        'source'            => 'openapi.ro',

        // Locație
        'address'           => $adresa,
        'county'            => trim($d['judet'] ?? ''),
        'city'              => $city,
        'postal_code'       => trim($d['cod_postal'] ?? '') ?: null,

        // Status
        'status'            => $status,
        'radiata'           => !empty($d['radiata']),

        // Contact
        'phone'             => trim($d['telefon'] ?? '') ?: null,
        'fax'               => trim($d['fax'] ?? '') ?: null,

        // Fiscal
        'vat_payer'         => !empty($d['tva']),             // plătitor TVA
        'vat_since'         => $d['tva'] ?? null,             // data înreg TVA
        'tva_la_incasare'   => $tvaLaIncasare,
        'impozit_profit'    => $d['impozit_profit'] ?? null,
        'impozit_micro'     => $d['impozit_micro']  ?? null,
        'accize'            => $d['accize'] ?? null,

        // Registru
        'act_autorizare'    => trim($d['act_autorizare'] ?? '') ?: null,  // ex: "INCHEIERE JUD. NR. 2100/27.02.2023"
        'numar_reg_com'     => trim($d['numar_reg_com'] ?? '') ?: null,

        // Actualizare
        'ultima_prelucrare' => $d['ultima_prelucrare'] ?? null,
        'ultima_declaratie' => $d['ultima_declaratie'] ?? null,
        'openapi_updated'   => $d['meta']['updated_at'] ?? null,

        // Câmpuri ce vor fi completate de alte surse
        'registration_date' => $d['impozit_profit'] ?? null, // approx. data constituirii
        'caen_code'         => '',
        'legal_form'        => '',
        'representatives'   => [],
        'founders'          => [],
    ];

    // Județ fallback din adresă
    if (empty($result['county'])) {
        if (preg_match('/JUD\.\s*([^,]+)/i', $adresa, $m))     $result['county'] = ucwords(strtolower(trim($m[1])));
        elseif (stripos($adresa, 'bucure') !== false)            $result['county'] = 'BUCURESTI';
    }

    return $result;
}

/**
 * Preia TOATE bilanțurile disponibile de la openapi.ro pentru un CUI.
 * Returnează ['latest' => [...], 'all' => [[an, cifra_afaceri, ...], ...]]
 */
function getOpenApiBalance(string $cui): ?array {
    $cuiInt = intval(preg_replace('/[^0-9]/', '', $cui));
    if ($cuiInt <= 0) return null;

    // Lista tuturor anilor disponibili
    $raw  = httpGet(OPENAPI_BASE . '/api/companies/' . $cuiInt . '/balances', openApiHeaders());
    if (!$raw) return null;

    $list = json_decode($raw, true);
    if (!is_array($list) || empty($list)) return null;

    // Sortăm după an descrescător
    usort($list, function($a, $b) { return strcmp($b['an'] ?? '0', $a['an'] ?? '0'); });

    $allBalances = [];
    $latest      = null;

    foreach ($list as $entry) {
        $an = $entry['an'] ?? null;
        if (!$an) continue;

        $rawB = httpGet(OPENAPI_BASE . '/api/companies/' . $cuiInt . '/balances/' . $an, openApiHeaders());
        if (!$rawB) continue;

        $b = json_decode($rawB, true);
        if (!$b) continue;

        $row = [
            'an'                => $an,
            'caen_code'         => $b['caen_code'] ?? null,
            'caen_description'  => $b['data']['denumire_caen'] ?? null,
            'cifra_afaceri'     => $b['data']['cifra_de_afaceri_neta'] ?? null,
            'profit_net'        => $b['data']['profitul_net_al_exercitiului_financiar'] ?? null,
            'profit_brut'       => $b['data']['profitul_brut'] ?? null,
            'nr_angajati'       => $b['data']['numar_mediu_de_salariati'] ?? null,
            'active_totale'     => $b['data']['total_active'] ?? null,
            'datorii_totale'    => $b['data']['datorii_ce_trebuie_platite_intr_o_perioada_de_pana_la_un_an'] ?? null,
            'capitaluri_proprii'=> $b['data']['capitaluri_proprii'] ?? null,
        ];

        $allBalances[] = $row;
        if ($latest === null) $latest = $row; // primul = cel mai recent
    }

    if (empty($allBalances)) return null;

    return [
        'caen_code'        => $latest['caen_code'],
        'caen_description' => $latest['caen_description'],
        'balance_year'     => $latest['an'],
        'cifra_afaceri'    => $latest['cifra_afaceri'],
        'nr_angajati'      => $latest['nr_angajati'],
        'profit_net'       => $latest['profit_net'],
        'active_totale'    => $latest['active_totale'],
        'all_balances'     => $allBalances,   // toate anii pentru evoluție
    ];
}

// ── ANAF (fallback fiscal) ────────────────────────────────────────────────────
function getAnafData(string $cui): ?array {
    $cuiInt = intval(preg_replace('/[^0-9]/', '', $cui));
    if ($cuiInt <= 0) return null;

    $payload = json_encode([['cui' => $cuiInt, 'data' => date('Y-m-d')]]);
    $raw     = httpPost('https://webservicesp.anaf.ro/PlatitorTvaRest/api/v8/ws/tva', $payload);

    if (!$raw) return null;
    $data = json_decode($raw, true);
    $found = $data['found'] ?? [];
    if (empty($found)) return null;

    $item   = $found[0];
    $adresa = $item['adresa'] ?? '';

    $result = [
        'name'              => trim($item['denumire'] ?? ''),
        'cui'               => (string)$cuiInt,
        'address'           => $adresa,
        'registration_date' => $item['data_inregistrare'] ?? null,
        'caen_code'         => $item['cod_CAEN'] ?? null,
        'vat_payer'         => !empty($item['scpTVA']),
        'status'            => !empty($item['statusInactivi']) ? 'Inactivă' : 'Activă',
        'source'            => 'ANAF',
        'county'            => '',
        'city'              => '',
    ];

    if (preg_match('/JUD\.\s*([^,]+)/i', $adresa, $m)) {
        $result['county'] = ucwords(strtolower(trim($m[1])));
    } elseif (stripos($adresa, 'bucure') !== false) {
        $result['county'] = 'București';
    }
    if (preg_match('/(?:LOC\.|MUN\.)\s*([^,]+)/i', $adresa, $m)) {
        $result['city'] = ucwords(strtolower(trim($m[1])));
    }

    return $result;
}

// ── totalfirme.ro (fallback scraping) ────────────────────────────────────────
function searchTotalfirme(string $query): array {
    $url  = 'https://www.totalfirme.ro/search/?q=' . urlencode($query);
    $html = httpGet($url);
    if (!$html) return [];

    $xp      = parseDom($html);
    $results = [];
    $seen    = [];

    $links = $xp->query('//a[@href]');
    foreach ($links as $a) {
        $href = $a->getAttribute('href');
        if (!preg_match('/-(\d{6,10})\/?$/', $href, $m)) continue;
        $cuiFound = $m[1];
        if (isset($seen[$cuiFound])) continue;

        $text = trim($a->textContent);
        if (strlen($text) < 5) continue;

        $seen[$cuiFound] = true;
        $results[] = [
            'name'    => $text,
            'cui'     => $cuiFound,
            'address' => '',
            'status'  => '',
            'source'  => 'totalfirme.ro',
        ];
        if (count($results) >= 15) break;
    }
    return $results;
}

function getAssociationDetails(string $cui): ?array {
    $searchHtml = httpGet('https://www.totalfirme.ro/search/?q=' . urlencode($cui));
    if (!$searchHtml) return null;

    $xp        = parseDom($searchHtml);
    $detailUrl = null;
    $links     = $xp->query('//a[@href]');
    foreach ($links as $a) {
        $href = $a->getAttribute('href');
        if (strpos($href, $cui) !== false) {
            $detailUrl = strpos($href, 'http') === 0 ? $href : 'https://www.totalfirme.ro' . $href;
            break;
        }
    }
    if (!$detailUrl) return null;

    $html = httpGet($detailUrl);
    if (!$html) return null;

    $xp   = parseDom($html);
    $data = ['source' => 'totalfirme.ro', 'representatives' => [], 'founders' => []];

    $h1 = xpathText($xp, '//h1');
    if ($h1) $data['name'] = $h1;

    $rows = $xp->query('//table//tr | //dl | //*[contains(@class,"detail")]');
    foreach ($rows as $row) {
        $cells = $xp->query('.//*[self::td or self::th or self::dt or self::dd]', $row);
        if ($cells->length < 2) continue;
        $label = strtolower(trim($cells->item(0)->textContent));
        $value = trim($cells->item(1)->textContent);
        if (!$value) continue;

        if (strpos($label, 'adres') !== false || strpos($label, 'sediu') !== false)         $data['address']           = isset($data['address'])           ? $data['address']           : $value;
        elseif (strpos($label, 'jude') !== false)                                           $data['county']            = isset($data['county'])            ? $data['county']            : $value;
        elseif (strpos($label, 'localit') !== false || strpos($label, 'ora') !== false)    $data['city']              = isset($data['city'])              ? $data['city']              : $value;
        elseif (strpos($label, 'caen') !== false) {
            $parts = explode(' ', $value, 2);
            $data['caen_code']        = isset($data['caen_code'])        ? $data['caen_code']        : $parts[0];
            $data['caen_description'] = isset($data['caen_description']) ? $data['caen_description'] : ($parts[1] ?? '');
        }
        elseif (strpos($label, 'stare') !== false || strpos($label, 'status') !== false)   $data['status']            = isset($data['status'])            ? $data['status']            : $value;
        elseif (strpos($label, 'registr') !== false)                                        $data['registration_date'] = isset($data['registration_date']) ? $data['registration_date'] : $value;
        elseif (strpos($label, 'telefon') !== false || strpos($label, 'tel') !== false)    $data['phone']             = isset($data['phone'])             ? $data['phone']             : $value;
        elseif (strpos($label, 'email') !== false)                                          $data['email']             = isset($data['email'])             ? $data['email']             : $value;
        elseif (strpos($label, 'web') !== false || strpos($label, 'site') !== false)       $data['website']           = isset($data['website'])           ? $data['website']           : $value;
        elseif (strpos($label, 'juridic') !== false || strpos($label, 'form') !== false)   $data['legal_form']        = isset($data['legal_form'])        ? $data['legal_form']        : $value;
    }

    $repNodes = $xp->query('//*[contains(translate(text(),"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz"),"administrator") or contains(translate(text(),"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz"),"reprezentant")]');
    if ($repNodes->length > 0) {
        $parent = $repNodes->item(0)->parentNode;
        $lis    = $xp->query('.//li', $parent);
        foreach ($lis as $li) {
            $t = trim($li->textContent);
            if ($t && !in_array($t, $data['representatives'])) $data['representatives'][] = $t;
        }
    }

    return count($data) > 3 ? $data : null;
}

// ── asociatii.net (fallback scop/fondatori) ───────────────────────────────────
function getAsociatiiData(string $cui, string $name = ''): ?array {
    $searchHtml = httpGet('https://www.asociatii.net/search?q=' . urlencode($cui));
    $detailUrl  = findDetailUrl($searchHtml, $cui, $name, 'https://www.asociatii.net');

    if (!$detailUrl && $name) {
        $searchHtml2 = httpGet('https://www.asociatii.net/search?q=' . urlencode(substr($name, 0, 50)));
        $detailUrl   = findDetailUrl($searchHtml2, $cui, $name, 'https://www.asociatii.net');
    }
    if (!$detailUrl) return null;

    $html = httpGet($detailUrl);
    if (!$html) return null;

    $xp   = parseDom($html);
    $data = [];

    $scopNodes = $xp->query('//*[contains(translate(text(),"SCOP","scop"),"scop") or contains(translate(text(),"OBIECT","obiect"),"obiect")]');
    if ($scopNodes->length > 0) {
        $sibling = $scopNodes->item(0)->parentNode->nextSibling;
        while ($sibling && $sibling->nodeType !== XML_ELEMENT_NODE) $sibling = $sibling->nextSibling;
        if ($sibling) {
            $txt = trim($sibling->textContent);
            if (strlen($txt) > 20) $data['purpose'] = substr($txt, 0, 1000);
        }
    }

    $founders   = [];
    $fondNodes  = $xp->query('//*[contains(translate(text(),"FONDATOR","fondator"),"fondator")]');
    if ($fondNodes->length > 0) {
        $lis = $xp->query('.//li', $fondNodes->item(0)->parentNode);
        foreach ($lis as $li) {
            $t = trim($li->textContent);
            if ($t && !in_array($t, $founders)) $founders[] = $t;
            if (count($founders) >= 15) break;
        }
    }
    if ($founders) $data['founders'] = $founders;

    return $data ?: null;
}

function findDetailUrl(string $html, string $cui, string $name, string $base): ?string {
    if (!$html) return null;
    $xp   = parseDom($html);
    $slug = preg_replace('/\W+/', '', strtolower(substr($name, 0, 20)));
    foreach ($xp->query('//a[@href]') as $a) {
        $href = $a->getAttribute('href');
        if (strpos($href, $cui) !== false || ($slug && strpos(strtolower($href), substr($slug, 0, 8)) !== false)) {
            return strpos($href, 'http') === 0 ? $href : $base . $href;
        }
    }
    return null;
}

// ── Găsire CUI după nume (pentru asociații din registrul MJ fără CUI) ─────────
/**
 * Caută CUI-ul unei asociații pe openapi.ro după denumire.
 * Returnează CUI-ul (string) dacă găsim o potrivire exactă sau unică, altfel null.
 */
function lookupCuiByName(string $name): ?string {
    if (strlen(trim($name)) < 5) return null;

    $results = searchOpenApi(substr(trim($name), 0, 80));
    if (empty($results)) return null;

    $nameNorm = strtolower(preg_replace('/\s+/', ' ', trim($name)));

    // 1. Potrivire exactă
    foreach ($results as $r) {
        $rNorm = strtolower(preg_replace('/\s+/', ' ', trim($r['name'])));
        if ($rNorm === $nameNorm) return $r['cui'];
    }

    // 2. Un singur rezultat → probabil e cel corect
    if (count($results) === 1) return $results[0]['cui'];

    // 3. Potrivire parțială puternică (>80% din cuvinte comune)
    $wordsA = array_filter(explode(' ', $nameNorm), function($w) { return strlen($w) > 3; });
    foreach ($results as $r) {
        $rNorm  = strtolower(preg_replace('/\s+/', ' ', trim($r['name'])));
        $wordsB = array_filter(explode(' ', $rNorm), function($w) { return strlen($w) > 3; });
        $common = array_intersect($wordsA, $wordsB);
        if (count($wordsA) > 0 && count($common) / count($wordsA) >= 0.8) {
            return $r['cui'];
        }
    }

    return null;
}

/**
 * Îmbogățește un record existent (fără CUI) cu date de la openapi.ro + ANAF.
 * Dacă găsește CUI-ul, îl salvează în DB și returnează datele complete.
 */
function enrichAssociationRecord(array $dbRecord): array {
    $name   = $dbRecord['name']       ?? '';
    $county = $dbRecord['county']     ?? '';

    // Încearcă să găsească CUI după nume
    $cui = lookupCuiByName($name);

    if (!$cui) {
        // Fără CUI – returnăm ce avem din DB (date MJ)
        return $dbRecord;
    }

    // Fetch complet cu CUI găsit
    $fresh = fetchAllData($cui);

    // Combinăm: datele MJ au prioritate pentru reg_number și status MJ
    $combined = array_merge($dbRecord, array_filter($fresh, function($v) { return $v !== null && $v !== '' && $v !== []; }));

    // Câmpurile MJ rămân neatinse (sunt sursa de adevăr pentru registru)
    $combined['reg_number'] = $dbRecord['reg_number'] ?? $combined['reg_number'] ?? null;

    // Statusul MJ se păstrează separat, statusul fiscal vine de la openapi/ANAF
    $combined['status_mj']  = $dbRecord['status'];   // din XLSX (radiata, in lichidare, etc.)
    $combined['status']     = $fresh['status'] ?? $dbRecord['status'];
    $combined['cui']        = $cui;

    return $combined;
}

// ── Căutare publică (primar: openapi.ro, fallback: totalfirme) ───────────────
function searchAssociations(string $query): array {
    // 1. openapi.ro – cel mai fiabil
    $results = searchOpenApi($query);
    if ($results) return $results;

    // 2. Fallback: totalfirme.ro scraping
    $results = searchTotalfirme($query);
    return $results;
}

// ── Detalii complete pentru un CUI ───────────────────────────────────────────
/**
 * Ordinea surselor:
 *  1. openapi.ro   → date de bază + fiscal (sursă primară)
 *  2. ANAF         → completează TVA/status fiscal dacă openapi nu le are
 *  3. totalfirme   → CAEN, reprezentanți (fallback)
 *  4. asociatii.net→ scop, fondatori (fallback)
 *  + openapi balances → CAEN code, date financiare
 */
function fetchAllData(string $cui): array {
    $data = ['cui' => $cui];

    // ── 1. openapi.ro – principală ────────────────────────────────────────────
    $openApi = getOpenApiDetails($cui);
    if ($openApi) {
        $data = array_merge($data, $openApi);
    }

    // ── 2. openapi.ro bilanțuri – CAEN + evoluție financiară ────────────────
    $balance = getOpenApiBalance($cui);
    if ($balance) {
        if (empty($data['caen_code']))         $data['caen_code']        = $balance['caen_code'];
        if (empty($data['caen_description']))  $data['caen_description'] = $balance['caen_description'];
        $data['balance_year']    = $balance['balance_year']  ?? null;
        $data['cifra_afaceri']   = $balance['cifra_afaceri'] ?? null;
        $data['nr_angajati']     = $balance['nr_angajati']   ?? null;
        $data['profit_net']      = $balance['profit_net']    ?? null;
        $data['active_totale']   = $balance['active_totale'] ?? null;
        $data['all_balances']    = $balance['all_balances']  ?? [];  // evoluție multi-an
    }

    // ── 3. ANAF – completează TVA/status dacă openapi n-a găsit ──────────────
    if (empty($openApi)) {
        $anaf = getAnafData($cui);
        if ($anaf) {
            foreach ($anaf as $k => $v) {
                if (empty($data[$k])) $data[$k] = $v;
            }
        }
    } else {
        // Chiar dacă avem openapi, forțăm vat_payer de la ANAF care e mai precis
        $anaf = getAnafData($cui);
        if ($anaf) {
            $data['vat_payer'] = $anaf['vat_payer'];
            if (empty($data['registration_date'])) $data['registration_date'] = $anaf['registration_date'];
            if (empty($data['caen_code']))          $data['caen_code']         = $anaf['caen_code'];
        }
    }

    // ── 4. totalfirme.ro – reprezentanți, formă juridică (fallback) ──────────
    $tf = getAssociationDetails($cui);
    if ($tf) {
        foreach ($tf as $k => $v) {
            if (empty($data[$k])) $data[$k] = $v;
        }
    }

    // ── 5. asociatii.net – scop, fondatori ───────────────────────────────────
    $an = getAsociatiiData($cui, $data['name'] ?? '');
    if ($an) {
        foreach ($an as $k => $v) {
            if (empty($data[$k])) $data[$k] = $v;
        }
    }

    // Sursa finală
    $data['source'] = $openApi ? 'openapi.ro' : ($data['source'] ?? 'mixed');

    return $data;
}
