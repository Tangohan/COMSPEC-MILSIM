<?php

declare(strict_types=1);

/**
 * Sujets de référence du canal forum ATAK / COMSPEC (slug catégorie atak-comspec).
 * Idempotent — appelée depuis run-migrations.php et scripts/seed-atak-forum-topics.php.
 *
 * @return callable(PDO): void
 */

require_once dirname(__DIR__) . '/app/Support/SqlText.php';

return static function (PDO $pdo): void {
    $tableExists = static function (PDO $pdo, string $table): bool {
        $st = $pdo->prepare(
            'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
        );
        $st->execute([$table]);

        return (bool) $st->fetchColumn();
    };

    $columnExists = static function (PDO $pdo, string $table, string $column): bool {
        $st = $pdo->prepare(
            'SELECT 1 FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
        );
        $st->execute([$table, $column]);

        return (bool) $st->fetchColumn();
    };

    if (!$tableExists($pdo, 'forum_categories') || !$tableExists($pdo, 'forum_topics') || !$tableExists($pdo, 'forum_posts')) {
        echo "  [ATTENTION] tables forum absentes — seed ATAK forum reporté\n";

        return;
    }

    $hasOfficial = $columnExists($pdo, 'forum_topics', 'is_official');
    $hasSuppress = $columnExists($pdo, 'forum_topics', 'suppress_auto_lock');
    $hasBodyFormat = $columnExists($pdo, 'forum_posts', 'body_format');
    $hasServiceAccount = $columnExists($pdo, 'users', 'is_service_account');

    /** @var list<array{slug: string, title: string, pinned: bool, official: bool, locked: bool, body: string}> $topics */
    $topics = [
        [
            'slug' => 'atak-annonces',
            'title' => 'Annonces ATAK',
            'pinned' => true,
            'official' => true,
            'locked' => false,
            'body' => <<<'MD'
## Annonces du module ATAK

Espace réservé aux communications importantes concernant la carte tactique, la liaison jeu ↔ portail, et les outils associés.

Les nouveautés détaillées sont publiées dans le sujet **Changelog & nouveautés**.
Utilisez **Bugs & retours** pour signaler un problème, et **FAQ & aide** pour les questions courantes.
MD
        ],
        [
            'slug' => 'atak-changelog',
            'title' => 'Changelog & nouveautés',
            'pinned' => true,
            'official' => true,
            'locked' => false,
            'body' => <<<'MD'
## Changelog ATAK / COMSPEC

Ce fil regroupe les notes de version et les évolutions du module ATAK (carte, liaison, assistances, configuration).

### Comment lire ce fil
- Chaque mise à jour notable sera ajoutée **en réponse** (ou en tête de message) avec une date.
- Les correctifs mineurs peuvent être regroupés.

### Dernière entrée
*Aucune publication pour le moment — les prochaines versions seront annoncées ici.*

Merci de ne pas transformer ce sujet en discussion générale : pour les questions, utilisez **FAQ & aide** ; pour les incidents, **Bugs & retours**.
MD
        ],
        [
            'slug' => 'atak-roadmap',
            'title' => 'Feuille de route',
            'pinned' => true,
            'official' => true,
            'locked' => false,
            'body' => <<<'MD'
## Feuille de route ATAK

Vue d’ensemble des chantiers prévus ou en cours pour le module ATAK.

Les éléments listés ici sont **indicatifs** : priorités et délais peuvent évoluer selon les retours terrain et la charge de développement.

### En cours / prochainement
- À compléter par l’équipe Athena.

### Idées / à étudier
- Proposez des suggestions dans **Bugs & retours** (avec le préfixe « Suggestion » dans le titre de votre message).
MD
        ],
        [
            'slug' => 'atak-faq',
            'title' => 'FAQ & aide',
            'pinned' => true,
            'official' => true,
            'locked' => false,
            'body' => <<<'MD'
## FAQ & aide ATAK

Questions fréquentes et conseils d’utilisation.

### Première liaison
1. Ouvrez ATAK depuis le portail.
2. Suivez l’assistant de configuration / le guide Mod Arma si besoin.
3. Vérifiez l’état de la liaison dans le panneau dédié.

### La carte ne se charge pas
- Vérifiez que votre communauté n’a pas activé une maintenance ATAK.
- Rechargez la page, puis contrôlez l’état de la liaison.

### Où télécharger le pack ?
Voir le sujet **Liens utiles & téléchargements**.

Posez vos questions en réponse à ce fil. Pour un bug reproductible, préférez **Bugs & retours**.
MD
        ],
        [
            'slug' => 'atak-bugs',
            'title' => 'Bugs & retours',
            'pinned' => true,
            'official' => false,
            'locked' => false,
            'body' => <<<'MD'
## Bugs & retours

Signalez ici un dysfonctionnement ou une suggestion d’amélioration liée à ATAK.

### Pour un bug, indiquez
- **Ce que vous faisiez** (écran, action)
- **Ce qui s’est passé** (comportement observé)
- **Ce qui était attendu**
- Navigateur / appareil si pertinent
- Capture d’écran si possible

### Pour une suggestion
Commencez votre message par **Suggestion :** puis décrivez le besoin métier (pas seulement la solution technique).

L’équipe Athena lit ce fil pour prioriser les correctifs et les évolutions.
MD
        ],
        [
            'slug' => 'atak-liens',
            'title' => 'Liens utiles & téléchargements',
            'pinned' => true,
            'official' => true,
            'locked' => false,
            'body' => <<<'MD'
## Liens utiles ATAK

Raccourcis vers les ressources du module.

- [Ouvrir la carte ATAK](https://athena.ttrd.fr/public/atak)
- [Soutenir le financement ATAK](https://athena.ttrd.fr/public/soutenir-atak)
- Configuration et guides : panneau « Configuration pour le jeu » dans ATAK, ou pages d’aide du portail.

Les adresses de téléchargement du pack dépendent de votre communauté : elles apparaissent dans ATAK lorsque le pack est publié par l’administration.
MD
        ],
    ];

    $cats = $pdo->query(
        'SELECT id, tenant_id, name FROM forum_categories WHERE ' . \App\Support\SqlText::equalsLiteral($pdo, 'slug', 'atak-comspec') . ' ORDER BY id ASC'
    );
    $categoryRows = $cats ? ($cats->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];

    if ($categoryRows === []) {
        // Créer sous Support & Technique du tenant ATHENA (slug athena-sys) si possible
        $tenantSt = $pdo->prepare('SELECT id FROM tenants WHERE ' . \App\Support\SqlText::equals($pdo, 'slug') . ' LIMIT 1');
        $tenantSt->execute(['athena-sys']);
        $tenantId = (int) ($tenantSt->fetchColumn() ?: 0);
        if ($tenantId < 1) {
            echo "  [SKIP] aucune catégorie atak-comspec et tenant athena-sys introuvable\n";

            return;
        }
        $parentSt = $pdo->prepare(
            'SELECT id FROM forum_categories WHERE tenant_id = ? AND ' . \App\Support\SqlText::equals($pdo, 'slug') . ' AND parent_id IS NULL LIMIT 1'
        );
        $parentSt->execute([$tenantId, 'support']);
        $parentId = (int) ($parentSt->fetchColumn() ?: 0);
        if ($parentId < 1) {
            echo "  [SKIP] catégorie support absente pour athena-sys — créez atak-comspec manuellement\n";

            return;
        }
        $insCat = $pdo->prepare(
            'INSERT INTO forum_categories (tenant_id, parent_id, name, slug, description, color_theme, display_order, is_locked, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, 0, NOW(), NOW())'
        );
        $desc = 'Carte tactique ATAK, liaison jeu, changelogs et support module.';
        try {
            if ($columnExists($pdo, 'forum_categories', 'scope')) {
                $insCat = $pdo->prepare(
                    'INSERT INTO forum_categories (tenant_id, scope, parent_id, name, slug, description, color_theme, display_order, is_locked, created_at, updated_at)
                     VALUES (?, \'general\', ?, ?, ?, ?, ?, ?, 0, NOW(), NOW())'
                );
                $insCat->execute([$tenantId, $parentId, 'ATAK / COMSPEC', 'atak-comspec', $desc, 'slate', 0]);
            } else {
                $insCat->execute([$tenantId, $parentId, 'ATAK / COMSPEC', 'atak-comspec', $desc, 'slate', 0]);
            }
            $newId = (int) $pdo->lastInsertId();
            $categoryRows[] = ['id' => $newId, 'tenant_id' => $tenantId, 'name' => 'ATAK / COMSPEC'];
            echo "  [OK] catégorie atak-comspec créée (id {$newId}, tenant {$tenantId})\n";
        } catch (Throwable $e) {
            echo '  [ATTENTION] création catégorie atak-comspec : ' . $e->getMessage() . "\n";

            return;
        }
    }

    $resolveAuthor = static function (PDO $pdo, int $tenantId, bool $hasServiceAccount): int {
        $sql = 'SELECT id FROM users WHERE tenant_id = ? AND ' . \App\Support\SqlText::equalsLiteral($pdo, 'status', 'active');
        if ($hasServiceAccount) {
            $sql .= ' AND COALESCE(is_service_account, 0) = 0';
        }
        $sql .= ' AND ' . \App\Support\SqlText::notEquals($pdo, 'email') . ' ORDER BY id ASC LIMIT 1';
        $st = $pdo->prepare($sql);
        $st->execute([$tenantId, 'system.moderation@internal.local']);
        $id = (int) ($st->fetchColumn() ?: 0);
        if ($id > 0) {
            return $id;
        }
        $fallback = $pdo->prepare('SELECT id FROM users WHERE tenant_id = ? ORDER BY id ASC LIMIT 1');
        $fallback->execute([$tenantId]);

        return (int) ($fallback->fetchColumn() ?: 0);
    };

    $findTopic = $pdo->prepare(
        'SELECT id FROM forum_topics WHERE tenant_id = ? AND category_id = ? AND ' . \App\Support\SqlText::equals($pdo, 'slug') . ' LIMIT 1'
    );
    $insertTopic = $pdo->prepare(
        'INSERT INTO forum_topics (tenant_id, category_id, user_id, title, slug, is_pinned, is_locked, is_archived, is_hidden, view_count, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, 0, 0, 0, NOW(), NOW())'
    );
    $insertPost = $pdo->prepare(
        'INSERT INTO forum_posts (tenant_id, topic_id, user_id, body, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())'
    );

    $created = 0;
    $skipped = 0;

    foreach ($categoryRows as $cat) {
        $tenantId = (int) ($cat['tenant_id'] ?? 0);
        $categoryId = (int) ($cat['id'] ?? 0);
        if ($tenantId < 1 || $categoryId < 1) {
            continue;
        }
        $authorId = $resolveAuthor($pdo, $tenantId, $hasServiceAccount);
        if ($authorId < 1) {
            echo "  [ATTENTION] aucun utilisateur pour tenant {$tenantId} — sujets non créés\n";
            continue;
        }

        // Enrichir la description de catégorie si vide
        try {
            $pdo->prepare(
                'UPDATE forum_categories
                 SET description = COALESCE(NULLIF(TRIM(description), \'\'), ?), updated_at = NOW()
                 WHERE id = ? AND tenant_id = ?'
            )->execute([
                'Carte tactique ATAK, liaison jeu, changelogs et support module.',
                $categoryId,
                $tenantId,
            ]);
        } catch (Throwable) {
        }

        foreach ($topics as $spec) {
            $findTopic->execute([$tenantId, $categoryId, $spec['slug']]);
            $existingId = (int) ($findTopic->fetchColumn() ?: 0);
            if ($existingId > 0) {
                $skipped++;
                // Réappliquer pin / officiel / lock
                try {
                    $sets = ['is_pinned = ?', 'is_locked = ?', 'updated_at = NOW()'];
                    $params = [$spec['pinned'] ? 1 : 0, $spec['locked'] ? 1 : 0];
                    if ($hasOfficial) {
                        $sets[] = 'is_official = ?';
                        $params[] = $spec['official'] ? 1 : 0;
                    }
                    if ($hasSuppress) {
                        $sets[] = 'suppress_auto_lock = 1';
                    }
                    $params[] = $existingId;
                    $params[] = $tenantId;
                    $pdo->prepare(
                        'UPDATE forum_topics SET ' . implode(', ', $sets) . ' WHERE id = ? AND tenant_id = ?'
                    )->execute($params);
                } catch (Throwable) {
                }
                continue;
            }

            try {
                $insertTopic->execute([
                    $tenantId,
                    $categoryId,
                    $authorId,
                    $spec['title'],
                    $spec['slug'],
                    $spec['pinned'] ? 1 : 0,
                    $spec['locked'] ? 1 : 0,
                ]);
                $topicId = (int) $pdo->lastInsertId();
                if ($topicId < 1) {
                    echo "  [ATTENTION] échec insert topic {$spec['slug']}\n";
                    continue;
                }
                if ($hasOfficial || $hasSuppress) {
                    $sets = [];
                    $params = [];
                    if ($hasOfficial) {
                        $sets[] = 'is_official = ?';
                        $params[] = $spec['official'] ? 1 : 0;
                    }
                    if ($hasSuppress) {
                        $sets[] = 'suppress_auto_lock = 1';
                    }
                    $params[] = $topicId;
                    $params[] = $tenantId;
                    $pdo->prepare(
                        'UPDATE forum_topics SET ' . implode(', ', $sets) . ', updated_at = NOW() WHERE id = ? AND tenant_id = ?'
                    )->execute($params);
                }
                $insertPost->execute([$tenantId, $topicId, $authorId, $spec['body']]);
                $postId = (int) $pdo->lastInsertId();
                if ($postId > 0 && $hasBodyFormat) {
                    try {
                        $pdo->prepare('UPDATE forum_posts SET body_format = ? WHERE id = ?')->execute(['markdown', $postId]);
                    } catch (Throwable) {
                    }
                }
                $created++;
                echo "  [OK] sujet « {$spec['title']} » (slug {$spec['slug']}, topic #{$topicId})\n";
            } catch (Throwable $e) {
                echo '  [ATTENTION] topic ' . $spec['slug'] . ' : ' . $e->getMessage() . "\n";
            }
        }
    }

    echo "  Résumé seed forum ATAK : {$created} créé(s), {$skipped} déjà présent(s)\n";
};
