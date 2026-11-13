<?php

declare(strict_types=1);

use App\Support\Migrations\SchemaInspector;

/**
 * Identités unifiées : la « couverture » n'existe plus sur le site, il ne reste que des identités (celle du personnage et
 * les identités d'emprunt), chacune avec ses papiers et, au besoin, une histoire.
 *
 *  1. sse_identity_stories : histoire / notes du personnage écrites par le membre pour une identité (texte libre
 *     facultatif) et date du dernier envoi de cette identité au téléphone du jeu.
 *  2. sse_cover_export_merges : pour chaque ancienne couverture exportée (sse_cover_exports), l'identité dans laquelle
 *     elle a été fondue, comment (linked : lien de l'étape 4440 ; matched : même nom et même date de naissance qu'une
 *     identité du membre ; created : identité d'emprunt créée ; revoked / invalid : rien à fondre), et si elle était la
 *     couverture active. Sa présence dit que la fusion a eu lieu : le téléphone est alors servi depuis les identités.
 *  3. Fusion : chaque couverture non encore traitée est rattachée à une identité du membre ; sinon une identité d'emprunt
 *     est créée avec son état civil. Le texte de la légende (histoire, métier, employeur, ville, adresse, téléphone,
 *     motif, famille, contacts) est ajouté à l'histoire de l'identité.
 *
 * Aucune perte : sse_cover_exports, sse_cover_export_links et les lignes item_kind = 'cover' de operator_identity_states
 * ne sont ni modifiées ni supprimées (tables dépréciées, à retirer dans une étape ultérieure). L'identité envoyée en jeu
 * (kit par défaut) n'est pas changée : « was_active » garde la trace de l'ancienne couverture active.
 *
 * Idempotente (seules les couvertures absentes de sse_cover_export_merges sont lues) ; MySQL, MariaDB et sqlite (tests).
 * Mode à blanc ($dryRun) : rien n'est créé ni écrit, le rapport dit ce qui serait fondu et les doublons trouvés.
 *
 * @return callable(PDO, ?callable=, bool=): array{dry_run: bool, covers: int, already: int, linked: int, matched: int, created: int, revoked: int, invalid: int, lines: list<string>, duplicates: list<string>}
 */
return static function (PDO $pdo, ?callable $log = null, bool $dryRun = false): array {
    $log ??= static function (string $msg): void {
        // Silence web.
    };
    $schema = new SchemaInspector($pdo);
    $sqlite = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';
    $report = ['dry_run' => $dryRun, 'covers' => 0, 'already' => 0, 'linked' => 0, 'matched' => 0, 'created' => 0, 'revoked' => 0, 'invalid' => 0, 'lines' => [], 'duplicates' => []];

    // ---- 1 et 2 : tables
    $tables = [
        'sse_identity_stories' => [
            'cols' => [
                'tenant_id INT UNSIGNED NOT NULL',
                'identity_id INT UNSIGNED NOT NULL',
                'story TEXT NOT NULL',
                'game_synced_at DATETIME DEFAULT NULL',
                'updated_by_user_id INT UNSIGNED DEFAULT NULL',
                'updated_at DATETIME NOT NULL',
            ],
            'unique' => ['uq_sse_identity_stories', 'tenant_id, identity_id'],
        ],
        'sse_cover_export_merges' => [
            'cols' => [
                'tenant_id INT UNSIGNED NOT NULL',
                'export_id INT UNSIGNED NOT NULL',
                'user_id INT UNSIGNED NOT NULL',
                'identity_id INT UNSIGNED DEFAULT NULL',
                'action VARCHAR(16) NOT NULL',
                'was_active TINYINT NOT NULL DEFAULT 0',
                'merged_at DATETIME NOT NULL',
            ],
            'unique' => ['uq_sse_cover_export_merges', 'tenant_id, export_id'],
        ],
    ];
    $mergesExist = $schema->tableExists('sse_cover_export_merges');
    $storiesExist = $schema->tableExists('sse_identity_stories');
    if (!$dryRun) {
        foreach ($tables as $name => $def) {
            if ($schema->tableExists($name)) {
                $log("  [OK] {$name} (déjà présente)\n");
                continue;
            }
            $lines = array_merge([$sqlite ? 'id INTEGER PRIMARY KEY AUTOINCREMENT' : 'id INT UNSIGNED NOT NULL AUTO_INCREMENT'], $def['cols']);
            if (!$sqlite) {
                $lines[] = 'PRIMARY KEY (id)';
                $lines[] = 'UNIQUE KEY ' . $def['unique'][0] . ' (' . $def['unique'][1] . ')';
            }
            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS {$name} (\n    " . implode(",\n    ", $lines) . "\n)"
                . ($sqlite ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci')
            );
            if ($sqlite) {
                $pdo->exec('CREATE UNIQUE INDEX IF NOT EXISTS ' . $def['unique'][0] . " ON {$name} (" . $def['unique'][1] . ')');
            }
            $log("  [OK] {$name}\n");
        }
        $mergesExist = $storiesExist = true;
    }

    // ---- 3 : fusion des couvertures exportées
    if (!$schema->tableExists('sse_cover_exports') || !$schema->tableExists('sse_identities')) {
        $log("  [OK] aucune couverture exportée à fondre (tables absentes)\n");

        return $report;
    }
    $norm = static fn (string $v): string => mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $v)));
    $key = static fn (string $first, string $last, string $dob): string => $norm($first . ' ' . $last) . '|' . substr($dob, 0, 10);
    $now = gmdate('Y-m-d H:i:s');
    $done = [];
    if ($mergesExist) {
        foreach ($pdo->query('SELECT tenant_id, export_id FROM sse_cover_export_merges')->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $done[(int) $r['tenant_id'] . ':' . (int) $r['export_id']] = true;
        }
    }
    $links = [];
    if ($schema->tableExists('sse_cover_export_links')) {
        foreach ($pdo->query('SELECT tenant_id, export_id, identity_id FROM sse_cover_export_links')->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $links[(int) $r['tenant_id'] . ':' . (int) $r['export_id']] = (int) $r['identity_id'];
        }
    }
    $states = [];
    if ($schema->tableExists('operator_identity_states')) {
        foreach ($pdo->query("SELECT tenant_id, item_id, is_active, revoked_at FROM operator_identity_states WHERE item_kind = 'cover'")->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $states[(int) $r['tenant_id'] . ':' . (int) $r['item_id']] = ['active' => (int) $r['is_active'] === 1, 'revoked' => $r['revoked_at'] !== null];
        }
    }
    // Identités de chaque membre, par « nom|date de naissance » ; doublons déjà présents entre identités signalés.
    $identities = [];
    $owners = [];
    foreach ($pdo->query('SELECT id, tenant_id, user_id, first_name, last_name, birth_date FROM sse_identities WHERE user_id IS NOT NULL AND user_id > 0 ORDER BY id ASC')->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $member = (int) $r['tenant_id'] . ':' . (int) $r['user_id'];
        $owners[(int) $r['tenant_id'] . ':' . (int) $r['id']] = (int) $r['user_id'];
        $identities[$member][$key((string) $r['first_name'], (string) $r['last_name'], (string) ($r['birth_date'] ?? ''))][] = (int) $r['id'];
    }
    foreach ($identities as $member => $byKey) {
        foreach ($byKey as $k => $ids) {
            if (count($ids) > 1) {
                $report['duplicates'][] = sprintf('membre %s : identités %s portent le même nom et la même date de naissance (%s)', $member, implode(', ', $ids), $k);
            }
        }
    }
    $storyOf = [];
    if ($storiesExist) {
        foreach ($pdo->query('SELECT tenant_id, identity_id, story FROM sse_identity_stories')->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $storyOf[(int) $r['tenant_id'] . ':' . (int) $r['identity_id']] = (string) $r['story'];
        }
    }
    $legendText = static function (array $cover): string {
        $lines = [];
        foreach ((array) ($cover['history'] ?? []) as $l) {
            if (is_string($l) && trim($l) !== '') {
                $lines[] = trim($l);
            }
        }
        foreach (['profession' => 'Métier', 'employer' => 'Employeur', 'city' => 'Ville', 'address' => 'Adresse', 'phone' => 'Téléphone', 'travel' => 'Motif du déplacement', 'family' => 'Famille'] as $k => $label) {
            if (is_string($cover[$k] ?? null) && trim($cover[$k]) !== '') {
                $lines[] = $label . ' : ' . trim($cover[$k]);
            }
        }
        foreach ((array) ($cover['contacts'] ?? []) as $c) {
            if (is_array($c) && is_string($c[0] ?? null) && trim($c[0]) !== '') {
                $lines[] = 'Contact : ' . implode(', ', array_filter(array_map(static fn ($v): string => trim((string) $v), array_slice($c, 0, 3)), static fn (string $v): bool => $v !== ''));
            }
        }

        return implode("\n", $lines);
    };
    $seenCover = [];
    $tempId = 0;
    $rows = $pdo->query('SELECT id, tenant_id, user_id, ref, cover_name, payload_json FROM sse_cover_exports ORDER BY id ASC')->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $row) {
        $report['covers']++;
        $tenantId = (int) $row['tenant_id'];
        $userId = (int) $row['user_id'];
        $exportId = (int) $row['id'];
        $rk = $tenantId . ':' . $exportId;
        if (isset($done[$rk])) {
            $report['already']++;
            continue;
        }
        $state = $states[$rk] ?? ['active' => false, 'revoked' => false];
        $file = json_decode((string) $row['payload_json'], true);
        $cover = is_array($file) && is_array($file['cover'] ?? null) ? $file['cover'] : null;
        $first = is_array($cover) ? trim((string) ($cover['first_name'] ?? '')) : '';
        $last = is_array($cover) ? trim((string) ($cover['last_name'] ?? '')) : '';
        $identityId = null;
        if ($state['revoked']) {
            $action = 'revoked';
        } elseif ($cover === null || $first === '' || $last === '') {
            $action = 'invalid';
        } else {
            $member = $tenantId . ':' . $userId;
            $dob = preg_match('/^\d{4}-\d{2}-\d{2}/', (string) ($cover['dob'] ?? '')) === 1 ? substr((string) $cover['dob'], 0, 10) : '';
            $k = $key($first, $last, $dob);
            if (isset($seenCover[$member][$k])) {
                $report['duplicates'][] = sprintf('membre %s : couvertures %s et %s décrivent la même personne (%s), fondues dans une seule identité', $member, $seenCover[$member][$k], (string) $row['ref'], $k);
            }
            $seenCover[$member][$k] = (string) $row['ref'];
            $linked = $links[$rk] ?? 0;
            if ($linked > 0 && ($owners[$tenantId . ':' . $linked] ?? 0) === $userId) {
                [$action, $identityId] = ['linked', $linked];
            } elseif (isset($identities[$member][$k])) {
                [$action, $identityId] = ['matched', $identities[$member][$k][0]];
            } else {
                $action = 'created';
                if ($dryRun) {
                    $identityId = --$tempId;
                } else {
                    $sex = in_array($cover['sex'] ?? '', ['M', 'F'], true) ? (string) $cover['sex'] : 'X';
                    $pdo->prepare(
                        "INSERT INTO sse_identities (tenant_id, kind, last_name, first_name, sex, birth_date, birth_place, nationality, profession, user_id, created_by_user_id, created_at)
                         VALUES (?, 'alias', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
                    )->execute([
                        $tenantId, mb_substr($last, 0, 80), mb_substr($first, 0, 80), $sex, $dob !== '' ? $dob : null,
                        trim((string) ($cover['pob'] ?? '')) !== '' ? mb_substr(trim((string) $cover['pob']), 0, 120) : null,
                        trim((string) ($cover['country_code'] ?? '')) !== '' ? mb_substr(strtoupper(trim((string) $cover['country_code'])), 0, 16) : null,
                        trim((string) ($cover['profession'] ?? '')) !== '' ? mb_substr(trim((string) $cover['profession']), 0, 120) : null,
                        $userId, $userId, $now,
                    ]);
                    $identityId = (int) $pdo->lastInsertId();
                }
                $identities[$member][$k][] = $identityId;
                $owners[$tenantId . ':' . $identityId] = $userId;
            }
            // Histoire : le texte de la légende est ajouté une seule fois à celle de l'identité.
            $text = $legendText($cover);
            $sk = $tenantId . ':' . $identityId;
            $current = $storyOf[$sk] ?? null;
            if ($text !== '' && ($current === null || !str_contains($current, $text))) {
                $story = mb_substr($current === null || trim($current) === '' ? $text : rtrim($current) . "\n\n" . $text, 0, 6000);
                if (!$dryRun) {
                    if ($current === null) {
                        $pdo->prepare('INSERT INTO sse_identity_stories (tenant_id, identity_id, story, updated_by_user_id, updated_at) VALUES (?, ?, ?, ?, ?)')
                            ->execute([$tenantId, $identityId, $story, $userId, $now]);
                    } else {
                        $pdo->prepare('UPDATE sse_identity_stories SET story = ?, updated_at = ? WHERE tenant_id = ? AND identity_id = ?')
                            ->execute([$story, $now, $tenantId, $identityId]);
                    }
                }
                $storyOf[$sk] = $story;
            }
        }
        $report[$action]++;
        $report['lines'][] = sprintf(
            '%s (%s, membre %d:%d) : %s%s',
            (string) $row['ref'],
            (string) $row['cover_name'],
            $tenantId,
            $userId,
            match ($action) {
                'linked' => 'fondue dans l’identité ' . $identityId . ' (déjà liée)',
                'matched' => 'fondue dans l’identité ' . $identityId . ' (même nom et même date de naissance)',
                'created' => $dryRun ? 'identité d’emprunt à créer' : 'identité d’emprunt ' . $identityId . ' créée',
                'revoked' => 'révoquée par le membre, non fondue',
                default => 'fichier illisible, non fondue',
            },
            $state['active'] && $action !== 'revoked' ? ' ; était la couverture active' : ''
        );
        if (!$dryRun) {
            $pdo->prepare('INSERT INTO sse_cover_export_merges (tenant_id, export_id, user_id, identity_id, action, was_active, merged_at) VALUES (?, ?, ?, ?, ?, ?, ?)')
                ->execute([$tenantId, $exportId, $userId, $identityId, $action, $state['active'] ? 1 : 0, $now]);
        }
    }
    $log(sprintf(
        "  [%s] couvertures exportées : %d lues, %d déjà fondues, %d liées, %d rapprochées (nom + date de naissance), %d identités d’emprunt %s, %d révoquées, %d illisibles\n",
        $dryRun ? 'À BLANC' : 'OK',
        $report['covers'], $report['already'], $report['linked'], $report['matched'], $report['created'], $dryRun ? 'à créer' : 'créées', $report['revoked'], $report['invalid']
    ));
    foreach ($report['lines'] as $line) {
        $log('    - ' . $line . "\n");
    }
    foreach ($report['duplicates'] as $line) {
        $log('    [DOUBLON] ' . $line . "\n");
    }

    return $report;
};
