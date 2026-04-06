<?php

declare(strict_types=1);

/**
 * Assignation en masse de la formation « parcours-portail » à tous les utilisateurs actifs
 * qui n’ont pas encore d’inscription, puis initialisation des lignes de progression (leçons).
 *
 * Activé uniquement si la variable d’environnement TRAINING_ONBOARDING_ASSIGN_ALL vaut 1, true ou on.
 * Peut être long sur une très grosse base — préférer l’exécution en CLI (php setup-database.php).
 */
function run_training_onboarding_bulk_assign(PDO $pdo): void
{
    $raw = getenv('TRAINING_ONBOARDING_ASSIGN_ALL');
    if ($raw === false || $raw === '') {
        $raw = $_ENV['TRAINING_ONBOARDING_ASSIGN_ALL'] ?? '';
    }
    $raw = strtolower(trim((string) $raw));
    if ($raw !== '1' && $raw !== 'true' && $raw !== 'on') {
        return;
    }

    $chk = $pdo->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'training_enrollments' LIMIT 1");
    if (!$chk || !$chk->fetch()) {
        return;
    }

    echo "training_onboarding_bulk_assign (TRAINING_ONBOARDING_ASSIGN_ALL activé)...\n";

    $slug = 'parcours-portail';
    $totalNew = 0;
    $tenants = $pdo->query('SELECT id FROM tenants ORDER BY id ASC');
    if (!$tenants) {
        return;
    }

    $selCourse = $pdo->prepare('SELECT id FROM training_courses WHERE tenant_id = ? AND slug = ? LIMIT 1');
    $selUsers = $pdo->prepare('SELECT id FROM users WHERE tenant_id = ? AND status = ?');
    $existsEnr = $pdo->prepare('SELECT id FROM training_enrollments WHERE course_id = ? AND user_id = ? LIMIT 1');
    $insEnr = $pdo->prepare(
        'INSERT INTO training_enrollments (tenant_id, course_id, user_id, assigned_by, assignment_type, status, expires_at, motivation_text)
         VALUES (?, ?, ?, NULL, ?, ?, NULL, NULL)'
    );
    $selLessons = $pdo->prepare(
        'SELECT l.id FROM training_lessons l
         INNER JOIN training_modules m ON m.id = l.module_id
         WHERE m.course_id = ? ORDER BY m.position ASC, l.position ASC, l.id ASC'
    );
    $insProg = $pdo->prepare(
        'INSERT IGNORE INTO training_progress (enrollment_id, lesson_id, status) VALUES (?, ?, ?)'
    );

    while ($tRow = $tenants->fetch(PDO::FETCH_ASSOC)) {
        $tenantId = (int) ($tRow['id'] ?? 0);
        if ($tenantId < 1) {
            continue;
        }
        $selCourse->execute([$tenantId, $slug]);
        $cid = (int) ($selCourse->fetchColumn() ?: 0);
        if ($cid < 1) {
            continue;
        }

        $selLessons->execute([$cid]);
        $lessonIds = $selLessons->fetchAll(PDO::FETCH_COLUMN);
        if (!is_array($lessonIds) || $lessonIds === []) {
            continue;
        }
        $lessonIds = array_map('intval', $lessonIds);

        $selUsers->execute([$tenantId, 'active']);
        while ($uRow = $selUsers->fetch(PDO::FETCH_ASSOC)) {
            $uid = (int) ($uRow['id'] ?? 0);
            if ($uid < 1) {
                continue;
            }
            $existsEnr->execute([$cid, $uid]);
            if ($existsEnr->fetchColumn()) {
                continue;
            }
            $insEnr->execute([$tenantId, $cid, $uid, 'campaign', 'assigned']);
            $eid = (int) $pdo->lastInsertId();
            if ($eid < 1) {
                continue;
            }
            foreach ($lessonIds as $lid) {
                if ($lid < 1) {
                    continue;
                }
                $insProg->execute([$eid, $lid, 'not_started']);
            }
            $totalNew++;
        }
    }

    echo "  Inscriptions créées : {$totalNew}.\n";
}
