<?php
declare(strict_types=1);

$reports = is_array($aarReports ?? null) ? $aarReports : [];
$statusFilter = (string) ($aarStatusFilter ?? '');
$openActionsFilter = !empty($aarOpenActionsFilter);
$kpis = is_array($aarKpis ?? null) ? $aarKpis : [];
$csrfToken = (string) ($csrfToken ?? \App\Core\Csrf::token());
$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');

$fmtDate = static function (?string $raw): string {
    if ($raw === null || trim($raw) === '') {
        return '—';
    }
    $ts = strtotime($raw);

    return $ts ? date('d/m/Y', $ts) : $raw;
};

$baseListUrl = url('back-office/atak/comptes-rendus');
$filterLink = static function (string $status = '', bool $openActions = false) use ($baseListUrl): string {
    $q = [];
    if ($status !== '') {
        $q['status'] = $status;
    }
    if ($openActions) {
        $q['open_actions'] = '1';
    }

    return $baseListUrl . ($q !== [] ? '?' . http_build_query($q) : '');
};

$athKpis = $kpis;
require base_path('views/partials/ath_kpis.php');

$s = \App\Core\Session::getFlash('success');
$e = \App\Core\Session::getFlash('error');
?>
<?php if ($s): ?><div class="ath-banner-warn ath-rise" style="background:#e6f8f0;border-color:#bfe9d8;margin-bottom:16px;" role="status"><div class="ath-banner-warn__text" style="color:#0b6b47;"><?= $h((string) $s) ?></div></div><?php endif; ?>
<?php if ($e): ?><div class="ath-banner-warn ath-rise" style="margin-bottom:16px;" role="alert"><div class="ath-banner-warn__text"><?= $h((string) $e) ?></div></div><?php endif; ?>

<div class="ath-users-filters ath-rise ath-aar-filters">
    <a href="<?= $h($filterLink()) ?>" class="ath-btn<?= $statusFilter === '' && !$openActionsFilter ? ' ath-btn--solid' : '' ?>">Tous</a>
    <a href="<?= $h($filterLink('pending')) ?>" class="ath-btn<?= $statusFilter === 'pending' ? ' ath-btn--solid' : '' ?>">En attente</a>
    <a href="<?= $h($filterLink('in_review')) ?>" class="ath-btn<?= $statusFilter === 'in_review' ? ' ath-btn--solid' : '' ?>">En relecture</a>
    <a href="<?= $h($filterLink('validated')) ?>" class="ath-btn<?= $statusFilter === 'validated' ? ' ath-btn--solid' : '' ?>">Validés</a>
    <a href="<?= $h($filterLink('missing')) ?>" class="ath-btn<?= $statusFilter === 'missing' ? ' ath-btn--solid' : '' ?>">Manquants</a>
    <a href="<?= $h($filterLink('', true)) ?>" class="ath-btn<?= $openActionsFilter ? ' ath-btn--solid' : '' ?>">Actions ouvertes</a>
</div>

<div class="ath-aar-list-tools ath-rise" x-data="{ formOpen: false }">
    <div class="ath-card ath-aar-form-card">
        <button type="button" class="ath-aar-form-card__toggle" @click="formOpen = !formOpen" :aria-expanded="formOpen.toString()">
            <span>
                <h2>Nouveau compte rendu</h2>
                <p class="ath-aar-form-card__hint">Déposer un retour d’expérience post-opération.</p>
            </span>
            <svg class="ath-aar-form-card__chevron" :style="formOpen ? 'transform:rotate(180deg)' : ''" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path d="m6 9 6 6 6-6"></path></svg>
        </button>
        <form method="post" action="<?= $h(url('back-office/atak/comptes-rendus')) ?>" class="ath-aar-form-card__body" id="nouveau" x-show="formOpen" x-cloak>
            <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
            <?php
            $report = [];
            $isEdit = false;
            require base_path('views/admin/aar_reports/partials/form_fields.php');
            ?>
            <button type="submit" class="ath-btn ath-btn--solid">Créer le compte rendu</button>
        </form>
    </div>
</div>

<?php
$athTableRows = [];
$athTableRowHrefs = [];
foreach ($reports as $report) {
    $rid = (int) ($report['id'] ?? 0);
    $ref = (string) ($report['reference_code'] ?? ('AAR-' . str_pad((string) $rid, 4, '0', STR_PAD_LEFT)));
    $operation = trim((string) ($report['operation_label'] ?? $report['mission_title'] ?? '—'));
    $strengths = count(is_array($report['strengths'] ?? null) ? $report['strengths'] : []);
    $weaknesses = count(is_array($report['weaknesses'] ?? null) ? $report['weaknesses'] : []);
    $open = (int) (($report['totals']['open_actions'] ?? 0));
    $closed = (int) (($report['totals']['closed_actions'] ?? 0));
    $pages = (int) ($report['page_count'] ?? 0);
    $validator = trim((string) ($report['validator_name'] ?? '—'));
    $hasLessons = trim((string) ($report['lessons_learned'] ?? '')) !== '';
    $athTableRows[] = [
        $ref,
        $operation !== '' ? $operation : '—',
        $fmtDate(isset($report['reported_at']) ? (string) $report['reported_at'] : null),
        trim((string) ($report['author_name'] ?? '—')) !== '' ? (string) $report['author_name'] : '—',
        $pages > 0 ? (string) $pages : '—',
        $strengths > 0 ? (string) $strengths : '—',
        $weaknesses > 0 ? (string) $weaknesses : '—',
        $hasLessons ? 'Oui' : '—',
        $open > 0 ? (string) $open : '—',
        $closed > 0 ? (string) $closed : '—',
        $validator !== '' ? $validator : '—',
        $fmtDate(isset($report['validated_at']) ? (string) $report['validated_at'] : null),
        (string) ($report['status_label'] ?? 'En attente'),
    ];
    $athTableRowHrefs[] = $rid > 0 ? url('back-office/atak/comptes-rendus/' . $rid) : null;
}

$athTableTitle = 'Rapports post-opération';
$athTableCount = count($reports);
$athTableCols = [
    'RÉF.|m', 'OPÉRATION', 'DÉPOSÉ LE|m', 'AUTEUR', 'PAGES|r', 'POINTS FORTS|r',
    'POINTS FAIBLES|r', 'ENSEIGNEMENTS', 'ACTIONS|r', 'ACTIONS CLOSES|r', 'RELECTEUR', 'VALIDÉ LE|m', 'STATUT|b',
];
$athTableFilters = ['Opération', 'Auteur', 'Statut'];
$athTableMinWidth = '1680px';
$athTableFoot = count($reports) > 0
    ? 'Affichage 1 – ' . count($reports) . ' sur ' . count($reports) . ' · ' . date('d/m/Y H:i')
    : 'Aucun compte rendu · ' . date('d/m/Y H:i');
$athTableExportUrl = url('api/atak/aar-reports/export');
require base_path('views/partials/ath_table.php');
