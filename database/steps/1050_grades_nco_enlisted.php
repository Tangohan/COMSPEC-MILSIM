<?php

declare(strict_types=1);

/**
 * Étape « Grades sous-officiers / militaires du rang FR & US (référentiel global, idempotent) » (Référentiels).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

// Grades sous-officiers / militaires du rang FR & US (référentiel global, idempotent)
{
    $gradeTable = null;
    foreach (['grades', 'grades_referentiel'] as $t) {
        $chk = $pdo->query("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = " . $pdo->quote($t) . " AND COLUMN_NAME = 'grade_system_id' LIMIT 1");
        if ($chk && $chk->fetchColumn()) {
            $gradeTable = $t;
            break;
        }
    }
    if ($gradeTable !== null) {
        $cat = [];
        foreach ($pdo->query('SELECT id, code FROM grade_categories')->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $cat[$r['code']] = (int) $r['id'];
        }
        $sys = [];
        foreach ($pdo->query('SELECT id, code FROM grade_systems')->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $sys[$r['code']] = (int) $r['id'];
        }
        if (isset($cat['SOUS_OFFICIER'], $cat['MDR'], $sys['FR_CLASSIC'], $sys['US_CLASSIC'])) {
            $exists = $pdo->prepare("SELECT 1 FROM `{$gradeTable}` WHERE grade_system_id = ? AND code = ? LIMIT 1");
            $ins = $pdo->prepare("INSERT INTO `{$gradeTable}` (grade_system_id, grade_category_id, code, label_short, label_long, label_otan, sort_order, is_commissioned, is_active, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, 0, 1, NOW(), NOW())");
            $rows = [
                ['FR_CLASSIC', 'SOUS_OFFICIER', 'MAJ', 'Major', 'Major', 'OR-9', 21],
                ['FR_CLASSIC', 'SOUS_OFFICIER', 'ADC', 'Adc', 'Adjudant-chef', 'OR-8', 22],
                ['FR_CLASSIC', 'SOUS_OFFICIER', 'ADJ', 'Adj', 'Adjudant', 'OR-7', 23],
                ['FR_CLASSIC', 'SOUS_OFFICIER', 'SCH', 'Sch', 'Sergent-chef', 'OR-6', 24],
                ['FR_CLASSIC', 'SOUS_OFFICIER', 'SGT', 'Sgt', 'Sergent', 'OR-5', 25],
                ['FR_CLASSIC', 'MDR', 'CCH', 'Cch', 'Caporal-chef', 'OR-4', 31],
                ['FR_CLASSIC', 'MDR', 'CPL', 'Cpl', 'Caporal', 'OR-3', 32],
                ['FR_CLASSIC', 'MDR', 'SD1', 'Sdt 1', 'Soldat de 1re classe', 'OR-2', 33],
                ['FR_CLASSIC', 'MDR', 'SD2', 'Sdt 2', 'Soldat de 2e classe', 'OR-1', 34],
                ['US_CLASSIC', 'SOUS_OFFICIER', 'SGM', 'SGM', 'Sergeant Major', 'E-9', 21],
                ['US_CLASSIC', 'SOUS_OFFICIER', 'MSG', 'MSG', 'Master Sergeant', 'E-8', 22],
                ['US_CLASSIC', 'SOUS_OFFICIER', 'SFC', 'SFC', 'Sergeant First Class', 'E-7', 23],
                ['US_CLASSIC', 'SOUS_OFFICIER', 'SSG', 'SSG', 'Staff Sergeant', 'E-6', 24],
                ['US_CLASSIC', 'SOUS_OFFICIER', 'SGT', 'SGT', 'Sergeant', 'E-5', 25],
                ['US_CLASSIC', 'MDR', 'CPL', 'CPL', 'Corporal', 'E-4', 31],
                ['US_CLASSIC', 'MDR', 'PFC', 'PFC', 'Private First Class', 'E-3', 32],
                ['US_CLASSIC', 'MDR', 'PV2', 'PV2', 'Private Second Class', 'E-2', 33],
                ['US_CLASSIC', 'MDR', 'PVT', 'PVT', 'Private', 'E-1', 34],
            ];
            $added = 0;
            foreach ($rows as $row) {
                [$sc, $cc, $code, $ls, $ll, $lo, $so] = $row;
                $exists->execute([$sys[$sc], $code]);
                if ($exists->fetchColumn()) {
                    continue;
                }
                $ins->execute([$sys[$sc], $cat[$cc], $code, $ls, $ll, $lo, $so]);
                $added++;
            }
            if ($added > 0) {
                echo "Référentiel grades : ajout SO / MdR ({$added} lignes) dans {$gradeTable}.\n";
            }
        }

        // Officiers généraux, entrées civiles et hors grade (idempotent, même table cible)
        if (isset($cat['OFFICIER'], $sys['FR_CLASSIC'], $sys['US_CLASSIC'])) {
            $existsGen = $pdo->prepare("SELECT 1 FROM `{$gradeTable}` WHERE grade_system_id = ? AND code = ? LIMIT 1");
            $insOff = $pdo->prepare("INSERT INTO `{$gradeTable}` (grade_system_id, grade_category_id, code, label_short, label_long, label_otan, sort_order, is_commissioned, is_active, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, 1, 1, NOW(), NOW())");
            $rowsOff = [
                ['FR_CLASSIC', 'GBR', 'Gén. bde', 'Général de brigade', 'OF-6', 17],
                ['FR_CLASSIC', 'GDV', 'Gén. div.', 'Général de division', 'OF-7', 18],
                ['FR_CLASSIC', 'GCA', 'Gén. c. a.', 'Général de corps d’armée', 'OF-8', 19],
                ['FR_CLASSIC', 'GAR', 'Gén. armée', 'Général d’armée', 'OF-9', 20],
                ['US_CLASSIC', 'BG', 'Brig. Gen.', 'Brigadier General', 'O-7', 17],
                ['US_CLASSIC', 'MG', 'Maj. Gen.', 'Major General', 'O-8', 18],
                ['US_CLASSIC', 'LTG', 'Lt Gen.', 'Lieutenant General', 'O-9', 19],
                ['US_CLASSIC', 'GEN', 'Gen.', 'General', 'O-10', 20],
            ];
            $addedOff = 0;
            foreach ($rowsOff as $ro) {
                [$sc, $code, $ls, $ll, $lo, $so] = $ro;
                $existsGen->execute([$sys[$sc], $code]);
                if ($existsGen->fetchColumn()) {
                    continue;
                }
                $insOff->execute([$sys[$sc], $cat['OFFICIER'], $code, $ls, $ll, $lo, $so]);
                $addedOff++;
            }
            if ($addedOff > 0) {
                echo "Référentiel grades : ajout officiers généraux ({$addedOff} lignes) dans {$gradeTable}.\n";
            }
        }
        if (isset($cat['CIVIL'], $sys['FR_CLASSIC'], $sys['US_CLASSIC'])) {
            $existsCiv = $pdo->prepare("SELECT 1 FROM `{$gradeTable}` WHERE grade_system_id = ? AND code = ? LIMIT 1");
            $insCiv = $pdo->prepare("INSERT INTO `{$gradeTable}` (grade_system_id, grade_category_id, code, label_short, label_long, label_otan, sort_order, is_commissioned, is_active, created_at, updated_at) VALUES (?, ?, ?, ?, ?, NULL, ?, 0, 1, NOW(), NOW())");
            $rowsCiv = [
                ['FR_CLASSIC', 'CIV', 'Civil', 'Personnel civil', 80],
                ['US_CLASSIC', 'CIV', 'Civilian', 'Civilian (non-military)', 80],
            ];
            $addedCiv = 0;
            foreach ($rowsCiv as $rc) {
                [$sc, $code, $ls, $ll, $so] = $rc;
                $existsCiv->execute([$sys[$sc], $code]);
                if ($existsCiv->fetchColumn()) {
                    continue;
                }
                $insCiv->execute([$sys[$sc], $cat['CIVIL'], $code, $ls, $ll, $so]);
                $addedCiv++;
            }
            if ($addedCiv > 0) {
                echo "Référentiel grades : ajout entrées civiles ({$addedCiv} lignes) dans {$gradeTable}.\n";
            }
        }
        if (isset($cat['HORS_GRADE'], $sys['FR_CLASSIC'], $sys['US_CLASSIC'])) {
            $existsHg = $pdo->prepare("SELECT 1 FROM `{$gradeTable}` WHERE grade_system_id = ? AND code = ? LIMIT 1");
            $insHg = $pdo->prepare("INSERT INTO `{$gradeTable}` (grade_system_id, grade_category_id, code, label_short, label_long, label_otan, sort_order, is_commissioned, is_active, created_at, updated_at) VALUES (?, ?, ?, ?, ?, NULL, ?, 0, 1, NOW(), NOW())");
            $rowsHg = [
                ['FR_CLASSIC', 'HG', 'Hors grade', 'Sans grade militaire', 90],
                ['US_CLASSIC', 'HG', 'No grade', 'No military grade', 90],
            ];
            $addedHg = 0;
            foreach ($rowsHg as $rh) {
                [$sc, $code, $ls, $ll, $so] = $rh;
                $existsHg->execute([$sys[$sc], $code]);
                if ($existsHg->fetchColumn()) {
                    continue;
                }
                $insHg->execute([$sys[$sc], $cat['HORS_GRADE'], $code, $ls, $ll, $so]);
                $addedHg++;
            }
            if ($addedHg > 0) {
                echo "Référentiel grades : ajout hors grade ({$addedHg} lignes) dans {$gradeTable}.\n";
            }
        }
    }
}
