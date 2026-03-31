<?php
require_once __DIR__ . '/config.php';

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
    return $pdo;
}

function dbOne(string $sql, array $p = []): ?array {
    $st = db()->prepare($sql);
    $st->execute($p);
    $r = $st->fetch();
    return $r ?: null;
}

function dbAll(string $sql, array $p = []): array {
    $st = db()->prepare($sql);
    $st->execute($p);
    return $st->fetchAll();
}

function dbExec(string $sql, array $p = []): int {
    $st = db()->prepare($sql);
    $st->execute($p);
    return (int) db()->lastInsertId() ?: $st->rowCount();
}

// ── Creare tabele la prima rulare ─────────────────────────────────────────────
function initDb(): void {
    $flag  = __DIR__ . '/../.db_initialized';
    $migV2 = __DIR__ . '/../.db_migrated_v2';

    if (file_exists($flag)) {
        // Migrare v2 (o singură dată): adaugă reg_number și face cui nullable
        if (!file_exists($migV2)) {
            try { db()->exec("ALTER TABLE `" . T_ASSOC . "` ADD COLUMN reg_number VARCHAR(60) NULL AFTER cui"); } catch (\Exception $e) {}
            try { db()->exec("ALTER TABLE `" . T_ASSOC . "` ADD UNIQUE INDEX idx_reg_number (reg_number)"); } catch (\Exception $e) {}
            try { db()->exec("ALTER TABLE `" . T_ASSOC . "` MODIFY COLUMN cui VARCHAR(20) NULL"); } catch (\Exception $e) {}
            // Lărgim city și county ca să nu mai dea truncate
            try { db()->exec("ALTER TABLE `" . T_ASSOC . "` MODIFY COLUMN city VARCHAR(255)"); } catch (\Exception $e) {}
            try { db()->exec("ALTER TABLE `" . T_ASSOC . "` MODIFY COLUMN county VARCHAR(150)"); } catch (\Exception $e) {}
            file_put_contents($migV2, date('Y-m-d H:i:s'));
        }
        return;
    }

    $db = db();
    $db->exec("
        CREATE TABLE IF NOT EXISTS `" . T_ASSOC . "` (
            id                INT AUTO_INCREMENT PRIMARY KEY,
            name              VARCHAR(500),
            cui               VARCHAR(20) NULL,
            reg_number        VARCHAR(60) NULL,
            address           TEXT,
            county            VARCHAR(100),
            city              VARCHAR(100),
            status            VARCHAR(50),
            registration_date VARCHAR(30),
            legal_form        VARCHAR(200),
            caen_code         VARCHAR(10),
            caen_description  VARCHAR(500),
            vat_payer         TINYINT(1) DEFAULT 0,
            representatives   TEXT,
            purpose           TEXT,
            founders          TEXT,
            phone             VARCHAR(50),
            email             VARCHAR(200),
            website           VARCHAR(500),
            raw_data          LONGTEXT,
            source            VARCHAR(50),
            last_updated      DATETIME,
            created_at        DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE INDEX idx_cui (cui),
            UNIQUE INDEX idx_reg_number (reg_number),
            INDEX idx_name (name(100))
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS `" . T_SEARCH . "` (
            id               INT AUTO_INCREMENT PRIMARY KEY,
            query            VARCHAR(500),
            cui_found        VARCHAR(20),
            association_name VARCHAR(500),
            ip_address       VARCHAR(50),
            created_at       DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS `" . T_ADMIN . "` (
            id            INT AUTO_INCREMENT PRIMARY KEY,
            username      VARCHAR(100) UNIQUE,
            password_hash VARCHAR(255),
            created_at    DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // Admin implicit: admin / admin123
    $cnt = dbOne("SELECT COUNT(*) AS cnt FROM `" . T_ADMIN . "`")['cnt'];
    if ($cnt == 0) {
        $hash = password_hash('admin123', PASSWORD_DEFAULT);
        dbExec("INSERT INTO `" . T_ADMIN . "` (username, password_hash) VALUES (?, ?)",
               ['admin', $hash]);
    }

    // Flag ca să nu mai rulăm la fiecare request
    file_put_contents($flag, date('Y-m-d H:i:s'));
}

function saveAssociation(array $d): void {
    $cui = !empty($d['cui']) ? $d['cui'] : null;
    $reg = !empty($d['reg_number']) ? $d['reg_number'] : null;

    // Decide cheia de unicitate: cui sau reg_number
    if ($cui !== null) {
        $sql = "INSERT INTO `" . T_ASSOC . "`
            (name, cui, reg_number, address, county, city, status, registration_date,
             legal_form, caen_code, caen_description, vat_payer,
             representatives, purpose, founders, phone, email, website,
             raw_data, source, last_updated)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
            ON DUPLICATE KEY UPDATE
              name=VALUES(name), reg_number=VALUES(reg_number),
              address=VALUES(address), county=VALUES(county),
              city=VALUES(city), status=VALUES(status),
              registration_date=VALUES(registration_date),
              legal_form=VALUES(legal_form), caen_code=VALUES(caen_code),
              caen_description=VALUES(caen_description), vat_payer=VALUES(vat_payer),
              representatives=VALUES(representatives), purpose=VALUES(purpose),
              founders=VALUES(founders), phone=VALUES(phone), email=VALUES(email),
              website=VALUES(website), raw_data=VALUES(raw_data),
              source=VALUES(source), last_updated=VALUES(last_updated)";
    } else {
        $sql = "INSERT INTO `" . T_ASSOC . "`
            (name, cui, reg_number, address, county, city, status, registration_date,
             legal_form, caen_code, caen_description, vat_payer,
             representatives, purpose, founders, phone, email, website,
             raw_data, source, last_updated)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
            ON DUPLICATE KEY UPDATE
              name=VALUES(name), cui=VALUES(cui),
              address=VALUES(address), county=VALUES(county),
              city=VALUES(city), status=VALUES(status),
              registration_date=VALUES(registration_date),
              legal_form=VALUES(legal_form), caen_code=VALUES(caen_code),
              caen_description=VALUES(caen_description), vat_payer=VALUES(vat_payer),
              representatives=VALUES(representatives), purpose=VALUES(purpose),
              founders=VALUES(founders), phone=VALUES(phone), email=VALUES(email),
              website=VALUES(website), raw_data=VALUES(raw_data),
              source=VALUES(source), last_updated=VALUES(last_updated)";
    }

    dbExec($sql, [
        $d['name'] ?? null,
        $cui,
        $reg,
        $d['address'] ?? null,
        $d['county']  ?? null,
        $d['city']    ?? null,
        $d['status']  ?? null,
        $d['registration_date'] ?? null,
        $d['legal_form']  ?? null,
        $d['caen_code']   ?? null,
        $d['caen_description'] ?? null,
        empty($d['vat_payer']) ? 0 : 1,
        json_encode($d['representatives'] ?? [], JSON_UNESCAPED_UNICODE),
        $d['purpose']  ?? null,
        json_encode($d['founders'] ?? [], JSON_UNESCAPED_UNICODE),
        $d['phone']   ?? null,
        $d['email']   ?? null,
        $d['website'] ?? null,
        json_encode($d, JSON_UNESCAPED_UNICODE),
        $d['source'] ?? 'mixed',
        date('Y-m-d H:i:s'),
    ]);
}
