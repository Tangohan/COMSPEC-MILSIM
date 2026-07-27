<?php
declare(strict_types=1);

$report = is_array($aarReport ?? null) ? $aarReport : [];
$missions = is_array($aarMissions ?? null) ? $aarMissions : (is_array($missions ?? null) ? $missions : []);
$csrfToken = (string) ($csrfToken ?? \App\Core\Csrf::token());
$id = (int) ($report['id'] ?? 0);
$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$operation = trim((string) ($report['operation_label'] ?? $report['title'] ?? ''));
?>

<div class="ath-aar-edit ath-rise">
    <p class="ath-aar-edit__lead">
        Modification du compte rendu<?= $operation !== '' ? ' — ' . $h($operation) : '' ?>.
        Les sections vides ne s’afficheront pas à la lecture.
    </p>
    <form method="post" action="<?= $h(url('back-office/atak/comptes-rendus/' . $id)) ?>" class="ath-card ath-aar-form-card">
        <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
        <?php
        $isEdit = true;
        require base_path('views/admin/aar_reports/partials/form_fields.php');
        ?>
        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:4px;">
            <button type="submit" class="ath-btn ath-btn--solid">Enregistrer les modifications</button>
            <a href="<?= $h(url('back-office/atak/comptes-rendus/' . $id)) ?>" class="ath-btn">Annuler</a>
        </div>
    </form>
</div>
