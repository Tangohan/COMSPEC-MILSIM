<?php
declare(strict_types=1);

/**
 * Exécute le schéma SQL et le seed par défaut (tenant, rôles, forum, panneaux).
 * Regroupe : schéma + ALTERs conditionnels + seed tenant/admin + seed forum.
 * CLI : php run-migrations.php
 * Web : public/run-migrations.php
 */

$root = dirname(__FILE__);

// ----- Vérifications préalables -----
$checks = [];
$checks['.env'] = is_file($root . '/.env');
$checks['schema.sql'] = is_file($root . '/migrations/schema.sql');

foreach ($checks as $label => $ok) {
    if (!$ok) {
        echo "[ERREUR] Fichier manquant : $label\n";
    }
}
if (!($checks['schema.sql'] ?? false)) {
    echo "Créez migrations/schema.sql ou placez-vous à la racine du projet.\n";
    exit(1);
}

// Charger .env
if ($checks['.env']) {
    $lines = file($root . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, '#') === 0 || strpos($line, '=') === false) {
            continue;
        }
        [$name, $value] = explode('=', $line, 2);
        $_ENV[trim($name)] = trim($value, " \t\"'");
        putenv(trim($name) . '=' . trim($value, " \t\"'"));
    }
    echo "[OK] .env chargé\n";
} else {
    echo "[ATTENTION] Pas de fichier .env — utilisation des variables d'environnement ou valeurs par défaut.\n";
}

// Connexion DB
$host = $_ENV['DB_HOST'] ?? getenv('DB_HOST') ?: '127.0.0.1';
$name = $_ENV['DB_NAME'] ?? getenv('DB_NAME') ?: 'u416380327_BDD_PROD';
$user = $_ENV['DB_USER'] ?? getenv('DB_USER') ?: 'u416380327_ADMIN_PROD';
$pass = $_ENV['DB_PASSWORD'] ?? getenv('DB_PASSWORD') ?: 'Tt05032001_TETARD_05032001';
$charset = $_ENV['DB_CHARSET'] ?? getenv('DB_CHARSET') ?: 'utf8mb4';

if ($user === 'root' && $pass === '' && !$checks['.env']) {
    echo "Erreur : aucun fichier .env trouvé. Créez .env avec DB_HOST, DB_NAME, DB_USER, DB_PASSWORD.\n";
    exit(1);
}

$dsn = "mysql:host=$host;dbname=$name;charset=$charset";
try {
    $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (PDOException $e) {
    echo "Connexion impossible : " . $e->getMessage() . "\n";
    echo "Vérifiez DB_HOST, DB_NAME, DB_USER et DB_PASSWORD.\n";
    exit(1);
}
echo "[OK] Connexion base : $name\n";
@flush();
@ob_flush();

// ----- Schéma (exécution statement par statement : PDO::exec ne gère qu'une requête) -----
set_time_limit(300);
$schemaPath = $root . '/migrations/schema.sql';
echo "Exécution du schéma...\n";
@flush();
@ob_flush();

$sql = @file_get_contents($schemaPath);
if ($sql === false || $sql === '') {
    echo "[ERREUR] Impossible de lire le fichier schema.sql\n";
    exit(1);
}
echo "  Fichier lu (" . strlen($sql) . " octets)\n";
@flush();
@ob_flush();

$sql = preg_replace('/--[^\r\n]*/s', '', $sql);
$chunks = preg_split('/;\s*[\r\n]+/', $sql);
$statements = array_filter(array_map('trim', $chunks), function ($s) { return $s !== ''; });
echo "  " . count($statements) . " instructions à exécuter\n";
@flush();
@ob_flush();

$done = 0;
$errors = [];
foreach ($statements as $stmt) {
    $stmt = trim($stmt);
    if ($stmt === '') continue;
    try {
        $pdo->exec($stmt . (str_ends_with($stmt, ';') ? '' : ';'));
        $done++;
        if ($done % 10 === 0) {
            echo "  … {$done}\n";
            @flush();
            @ob_flush();
        }
    } catch (PDOException $e) {
        $errors[] = $e->getMessage() . ' (extrait: ' . substr($stmt, 0, 80) . '…)';
    }
}
if (!empty($errors)) {
    echo "[ATTENTION] " . count($errors) . " erreur(s) :\n";
    foreach (array_slice($errors, 0, 5) as $err) echo "  - $err\n";
}
echo "Schéma OK. ({$done} instructions exécutées)\n";
@flush();
@ob_flush();

// Enlistments : colonnes formulaire Olympus (si table existante sans ces colonnes)
$stmt = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'enlistments' AND COLUMN_NAME = 'age'");
if ($stmt && !$stmt->fetch()) {
    echo "Ajout des colonnes formulaire Olympus à enlistments...\n";
    $alters = [
        "ALTER TABLE enlistments ADD COLUMN age smallint unsigned DEFAULT NULL AFTER notes",
        "ALTER TABLE enlistments ADD COLUMN timezone varchar(100) DEFAULT NULL AFTER age",
        "ALTER TABLE enlistments ADD COLUMN weekly_availability varchar(255) DEFAULT NULL AFTER timezone",
        "ALTER TABLE enlistments ADD COLUMN system_config varchar(500) DEFAULT NULL AFTER weekly_availability",
        "ALTER TABLE enlistments ADD COLUMN microphone_quality varchar(20) DEFAULT NULL AFTER system_config",
        "ALTER TABLE enlistments ADD COLUMN past_milsim_experience text AFTER microphone_quality",
        "ALTER TABLE enlistments ADD COLUMN ace_acre_level varchar(50) DEFAULT NULL AFTER past_milsim_experience",
        "ALTER TABLE enlistments ADD COLUMN motivation_why_join text AFTER ace_acre_level",
        "ALTER TABLE enlistments ADD COLUMN motivation_accountability text AFTER motivation_why_join",
        "ALTER TABLE enlistments ADD COLUMN commitment_effort varchar(20) DEFAULT NULL AFTER commitment_effort",
        "ALTER TABLE enlistments ADD COLUMN availability_wed_sat varchar(20) DEFAULT NULL AFTER availability_wed_sat",
        "ALTER TABLE enlistments ADD COLUMN no_ai_confirmed tinyint(1) DEFAULT 0 AFTER availability_wed_sat",
    ];
    foreach ($alters as $alter) {
        $pdo->exec($alter);
    }
    echo "Colonnes Olympus OK.\n";
}

// Colonne nato_code sur grades (si absente)
$stmt = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'grades' AND COLUMN_NAME = 'nato_code'");
if ($stmt && !$stmt->fetch()) {
    echo "Ajout colonne grades.nato_code...\n";
    $pdo->exec("ALTER TABLE grades ADD COLUMN nato_code varchar(10) DEFAULT NULL AFTER short_name");
}

// ----- Seed forum (permissions, rôles, catégories) — idempotent -----
$run_forum_seed = function (PDO $pdo, int $tenantId): void {
    $stmt = $pdo->prepare("SELECT id FROM permissions WHERE tenant_id = ? AND slug = 'forum.view' LIMIT 1");
    $stmt->execute([$tenantId]);
    if ($stmt->fetch()) {
        echo "Forum seed déjà appliqué (permissions existantes).\n";
        return;
    }

    echo "Seed forum : permissions et rôles...\n";

    $permissions = [
        ['forum.view', 'Voir le forum', 'forum'],
        ['forum.create_topic', 'Créer un sujet', 'forum'],
        ['forum.reply', 'Répondre', 'forum'],
        ['forum.edit_own', 'Modifier son message', 'forum'],
        ['forum.delete_own', 'Supprimer son message', 'forum'],
        ['forum.moderate', 'Modérer le forum', 'forum'],
        ['forum.manage_categories', 'Gérer les catégories', 'forum'],
    ];

    $permIds = [];
    $insertPerm = $pdo->prepare("INSERT INTO permissions (tenant_id, name, slug, module, created_at) VALUES (?, ?, ?, ?, NOW())");
    foreach ($permissions as $p) {
        $insertPerm->execute([$tenantId, $p[1], $p[0], $p[2]]);
        $permIds[$p[0]] = (int) $pdo->lastInsertId();
    }

    $adminRole = $pdo->prepare("SELECT id FROM roles WHERE tenant_id = ? AND slug = 'tenant_admin' LIMIT 1");
    $adminRole->execute([$tenantId]);
    $adminRoleId = (int) ($adminRole->fetch(PDO::FETCH_ASSOC)['id'] ?? 0);
    if ($adminRoleId) {
        $link = $pdo->prepare("INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (?, ?)");
        foreach ($permIds as $pid) {
            $link->execute([$adminRoleId, $pid]);
        }
    }

    foreach (['forum_moderator' => 'Modérateur forum', 'member' => 'Membre'] as $slug => $roleName) {
        $stmt = $pdo->prepare("SELECT id FROM roles WHERE tenant_id = ? AND slug = ? LIMIT 1");
        $stmt->execute([$tenantId, $slug]);
        if (!$stmt->fetch()) {
            $pdo->prepare("INSERT INTO roles (tenant_id, name, slug, description, is_system, created_at) VALUES (?, ?, ?, ?, 1, NOW())")
                ->execute([$tenantId, $roleName, $slug, '']);
        }
    }

    $modRole = $pdo->prepare("SELECT id FROM roles WHERE tenant_id = ? AND slug = 'forum_moderator' LIMIT 1");
    $modRole->execute([$tenantId]);
    $modRoleId = (int) ($modRole->fetch(PDO::FETCH_ASSOC)['id'] ?? 0);
    if ($modRoleId) {
        $link = $pdo->prepare("INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (?, ?)");
        foreach (['forum.view', 'forum.create_topic', 'forum.reply', 'forum.edit_own', 'forum.moderate'] as $slug) {
            if (isset($permIds[$slug])) {
                $link->execute([$modRoleId, $permIds[$slug]]);
            }
        }
    }

    $memberRole = $pdo->prepare("SELECT id FROM roles WHERE tenant_id = ? AND slug = 'member' LIMIT 1");
    $memberRole->execute([$tenantId]);
    $memberRoleId = (int) ($memberRole->fetch(PDO::FETCH_ASSOC)['id'] ?? 0);
    if ($memberRoleId) {
        $link = $pdo->prepare("INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (?, ?)");
        foreach (['forum.view', 'forum.create_topic', 'forum.reply', 'forum.edit_own'] as $slug) {
            if (isset($permIds[$slug])) {
                $link->execute([$memberRoleId, $permIds[$slug]]);
            }
        }
    }

    $stmt = $pdo->prepare("SELECT 1 FROM forum_categories WHERE tenant_id = ? LIMIT 1");
    $stmt->execute([$tenantId]);
    if ($stmt->fetch()) {
        echo "Catégories forum déjà présentes.\n";
        return;
    }

    echo "Création des catégories forum par défaut...\n";
    $categories = [
        ['Communiqués officiels', 'annonces', 'Annonces et communiqués de l\'équipe.', 'orange', 10],
        ['Général', 'general', 'Discussions générales et présentation.', 'indigo', 20],
        ['Missions & Opérations', 'missions', 'Briefs et retours d\'opérations.', 'violet', 30],
        ['Support & Technique', 'support', 'Aide, ATAK, équipement, technique.', 'rose', 40],
        ['Hors sujet', 'hors-sujet', 'Échanges informels.', 'emerald', 50],
    ];
    $insCat = $pdo->prepare("INSERT INTO forum_categories (tenant_id, parent_id, name, slug, description, color_theme, display_order, is_locked, created_at, updated_at) VALUES (?, NULL, ?, ?, ?, ?, ?, 0, NOW(), NOW())");
    foreach ($categories as $c) {
        $insCat->execute([$tenantId, $c[0], $c[1], $c[2], $c[3], $c[4]]);
    }
    echo "Forum seed OK.\n";
};

// ----- Seed : tenant + role + grade + admin (ou mise à jour) -----
$stmt = $pdo->query("SELECT id FROM tenants WHERE slug = 'default' LIMIT 1");
if ($stmt && $stmt->fetch()) {
    echo "Seed déjà appliqué (tenant default existe).\n";
    $row = $pdo->query("SELECT id FROM tenants WHERE slug = 'default' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $tenantId = (int) $row['id'];

    $stmt = $pdo->query("SELECT 1 FROM personnel_admin_panels WHERE tenant_id = $tenantId LIMIT 1");
    if ($stmt && !$stmt->fetch()) {
        echo "Création des panneaux admin par défaut...\n";
        $panels = [
            ['État civil', 'etat-civil', 'Identité et état civil', 10],
            ['Affectation', 'affectation', 'Unité, poste, affectation', 20],
            ['Formation', 'formation', 'Parcours et qualifications', 30],
            ['Sécurité / Clearance', 'securite', 'Niveaux de sécurité et habilitations', 40],
            ['Santé / Aptitude', 'sante', 'Aptitude médicale et restrictions', 50],
            ['Références / Notes', 'references-notes', 'Références et notes administratives', 60],
        ];
        foreach ($panels as $p) {
            $pdo->prepare("INSERT INTO personnel_admin_panels (tenant_id, name, slug, description, display_order) VALUES (?, ?, ?, ?, ?)")
                ->execute([$tenantId, $p[0], $p[1], $p[2], $p[3]]);
        }
        $pdo->exec("INSERT IGNORE INTO tenant_matricule_config (tenant_id, prefix, format_pattern, next_number, updated_at) VALUES ($tenantId, 'ATH', '{prefix}-{seq:5}', 1, NOW())");
    }

    $run_forum_seed($pdo, $tenantId);
    echo "Migrations terminées.\n";
    exit(0);
}

echo "Insertion du tenant et admin par défaut...\n";
$pdo->exec("INSERT INTO tenants (name, slug, created_at, updated_at) VALUES ('Default Organisation', 'default', NOW(), NOW())");
$tenantId = (int) $pdo->lastInsertId();

$pdo->exec("INSERT INTO roles (tenant_id, name, slug, description, is_system, created_at) VALUES ($tenantId, 'Administrator', 'tenant_admin', 'Full access', 1, NOW())");
$roleId = (int) $pdo->lastInsertId();

$pdo->exec("INSERT INTO grades (tenant_id, name, short_name, nato_code, rank_order, created_at) VALUES ($tenantId, 'Officer', 'OFR', 'OF-1', 10, NOW())");
$gradeId = (int) $pdo->lastInsertId();

$hash = password_hash('admin', PASSWORD_ARGON2ID);
$pdo->prepare("INSERT INTO users (tenant_id, email, password_hash, display_name, callsign, role_id, grade_id, status, created_at, updated_at) VALUES (?, 'admin@athena.local', ?, 'Admin', 'ADMIN', ?, ?, 'active', NOW(), NOW())")
    ->execute([$tenantId, $hash, $roleId, $gradeId]);

$panels = [
    ['État civil', 'etat-civil', 'Identité et état civil', 10],
    ['Affectation', 'affectation', 'Unité, poste, affectation', 20],
    ['Formation', 'formation', 'Parcours et qualifications', 30],
    ['Sécurité / Clearance', 'securite', 'Niveaux de sécurité et habilitations', 40],
    ['Santé / Aptitude', 'sante', 'Aptitude médicale et restrictions', 50],
    ['Références / Notes', 'references-notes', 'Références et notes administratives', 60],
];
foreach ($panels as $p) {
    $pdo->prepare("INSERT INTO personnel_admin_panels (tenant_id, name, slug, description, display_order) VALUES (?, ?, ?, ?, ?)")
        ->execute([$tenantId, $p[0], $p[1], $p[2], $p[3]]);
}
$pdo->exec("INSERT INTO tenant_matricule_config (tenant_id, prefix, format_pattern, next_number, updated_at) VALUES ($tenantId, 'ATH', '{prefix}-{seq:5}', 1, NOW())");

$run_forum_seed($pdo, $tenantId);

echo "Seed OK. Compte : admin@athena.local / admin\n";
echo "Migrations terminées.\n";
