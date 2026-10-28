<?php

declare(strict_types=1);

/**
 * Corrections du catalogue partagé des grades américains (US_CLASSIC), signalées par les contributeurs :
 * - faute de frappe « Fist Sergeant » → « First Sergeant » ;
 * - MSG libellé « Senior Master Sergeant » (c’est SMSgt) → « Master Sergeant » ;
 * - codes en double (ex. SFC deux fois) : on garde la ligne la plus complète, les membres sont reportés dessus,
 *   le doublon est désactivé (rien n’est supprimé) ;
 * - échelle de solde (E-x / W-x / O-x) manquante complétée pour les codes connus.
 *
 * Idempotent — appelée depuis run-migrations.php.
 */
return static function (PDO $pdo): void {
    $gradeTable = null;
    foreach (['grades', 'grades_referentiel'] as $t) {
        $chk = $pdo->prepare('SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1');
        $chk->execute([$t, 'grade_system_id']);
        if ($chk->fetchColumn()) {
            $gradeTable = $t;
            break;
        }
    }
    if ($gradeTable === null) {
        return;
    }
    $sysId = $pdo->query("SELECT id FROM grade_systems WHERE code = 'US_CLASSIC' LIMIT 1")->fetchColumn();
    if (!$sysId) {
        return;
    }
    $sysId = (int) $sysId;
    $changed = 0;

    // Fautes de frappe dans les libellés.
    foreach (['label_long', 'label_short'] as $col) {
        $st = $pdo->prepare("UPDATE `{$gradeTable}` SET `{$col}` = REPLACE(`{$col}`, 'Fist Sergeant', 'First Sergeant'), updated_at = NOW() WHERE grade_system_id = ? AND `{$col}` LIKE '%Fist Sergeant%'");
        $st->execute([$sysId]);
        $changed += $st->rowCount();
    }
    $st = $pdo->prepare("UPDATE `{$gradeTable}` SET label_long = 'Master Sergeant', updated_at = NOW() WHERE grade_system_id = ? AND UPPER(TRIM(code)) = 'MSG' AND label_long <> 'Master Sergeant'");
    $st->execute([$sysId]);
    $changed += $st->rowCount();

    // Doublons de code : on garde la ligne qui a une échelle de solde, sinon la plus ancienne.
    $rows = $pdo->prepare("SELECT id, UPPER(TRIM(code)) AS c, COALESCE(label_otan, '') AS otan FROM `{$gradeTable}` WHERE grade_system_id = ? AND is_active = 1 ORDER BY id ASC");
    $rows->execute([$sysId]);
    $byCode = [];
    foreach ($rows->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $byCode[(string) $r['c']][] = $r;
    }
    $moveUsers = $pdo->prepare('UPDATE users SET grade_id = ? WHERE grade_id = ?');
    $deactivate = $pdo->prepare("UPDATE `{$gradeTable}` SET is_active = 0, updated_at = NOW() WHERE id = ?");
    foreach ($byCode as $list) {
        if (count($list) < 2) {
            continue;
        }
        $keep = $list[0];
        foreach ($list as $r) {
            if (trim((string) $r['otan']) !== '') {
                $keep = $r;
                break;
            }
        }
        foreach ($list as $r) {
            if ((int) $r['id'] === (int) $keep['id']) {
                continue;
            }
            try {
                $moveUsers->execute([(int) $keep['id'], (int) $r['id']]);
            } catch (Throwable) {
                // colonne absente sur une base ancienne : le doublon reste lisible, seulement masqué des listes
            }
            $deactivate->execute([(int) $r['id']]);
            $changed++;
        }
    }

    // Échelle de solde américaine (US Army, Air Force, Marines, Navy) pour les codes connus.
    $payGrades = [
        'PVT' => 'E-1', 'PV1' => 'E-1', 'PV2' => 'E-2', 'PFC' => 'E-3', 'SPC' => 'E-4', 'CPL' => 'E-4',
        'SGT' => 'E-5', 'SSG' => 'E-6', 'SFC' => 'E-7', 'MSG' => 'E-8', '1SG' => 'E-8',
        'SGM' => 'E-9', 'CSM' => 'E-9', 'SMA' => 'E-9',
        'AB' => 'E-1', 'AMN' => 'E-2', 'A1C' => 'E-3', 'SRA' => 'E-4', 'SSGT' => 'E-5', 'TSGT' => 'E-6',
        'MSGT' => 'E-7', 'SMSGT' => 'E-8', 'CMSGT' => 'E-9', 'CMSAF' => 'E-9',
        'LCPL' => 'E-3', 'GYSGT' => 'E-7', '1STSGT' => 'E-8', 'MGYSGT' => 'E-9', 'SGTMAJ' => 'E-9',
        'WO1' => 'W-1', 'WO' => 'W-1', 'CW2' => 'W-2', 'CW3' => 'W-3', 'CW4' => 'W-4', 'CW5' => 'W-5',
        '2LT' => 'O-1', '1LT' => 'O-2', 'CPT' => 'O-3', 'CAPT' => 'O-3', 'MAJ' => 'O-4', 'LTC' => 'O-5', 'LTCOL' => 'O-5',
        'COL' => 'O-6', 'BG' => 'O-7', 'BGEN' => 'O-7', 'MG' => 'O-8', 'MAJGEN' => 'O-8',
        'LTG' => 'O-9', 'LTGEN' => 'O-9', 'GEN' => 'O-10', 'GA' => 'O-11',
    ];
    $fill = $pdo->prepare("UPDATE `{$gradeTable}` SET label_otan = ?, updated_at = NOW() WHERE grade_system_id = ? AND UPPER(REPLACE(REPLACE(TRIM(code), '.', ''), ' ', '')) = ? AND (label_otan IS NULL OR TRIM(label_otan) = '' OR label_otan = '—')");
    foreach ($payGrades as $code => $pay) {
        $fill->execute([$pay, $sysId, $code]);
        $changed += $fill->rowCount();
    }

    if ($changed > 0) {
        echo "Catalogue des grades américains corrigé ({$changed} modification(s)).\n";
    }
};
