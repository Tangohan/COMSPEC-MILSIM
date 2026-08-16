<?php
declare(strict_types=1);
ob_start();
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
/** @var list<array<string,mixed>> $suggestions */
$suggestions = is_array($suggestions ?? null) ? $suggestions : [];
/** @var list<array<string,mixed>> $history */
$history = is_array($history ?? null) ? $history : [];
/** @var list<array<string,mixed>> $signals */
$signals = is_array($signals ?? null) ? $signals : [];
$pendingCount = (int) ($pendingCount ?? 0);
$historyCount = (int) ($historyCount ?? count($history));
$canManage = (bool) ($canManage ?? false);
$filterCaseId = (int) ($filterCaseId ?? 0);
$searchQuery = trim((string) ($searchQuery ?? ''));

$fmtWhen = static function (mixed $raw): string {
    $s = trim((string) ($raw ?? ''));
    if ($s === '') {
        return '—';
    }
    $ts = strtotime($s);

    return $ts ? date('d/m/Y H:i', $ts) : $s;
};
?>
<div class="page-heading">
    <div>
        <div class="page-heading-overline">Analyse // Moteur</div>
        <h1>Rapprochements moteur</h1>
        <p>
            Propositions seulement — possible, probable, candidat à confirmation.
            Aucune fusion ni relation « confirmée » sans validation humaine.
            Un passage moteur (manuel ou nocturne) peut aussi envoyer le point e-mail aux analystes.
        </p>
    </div>
    <?php if ($canManage): ?>
        <form method="post" action="<?= $h(url('atak/sse/moteur/executer')) ?>">
            <?= \App\Core\Csrf::field() ?>
            <button class="btn" type="submit">Lancer un passage maintenant</button>
        </form>
    <?php endif; ?>
</div>

<form class="sse-toolbar-search" method="get" action="<?= $h(url('atak/sse/rapprochements')) ?>" role="search">
    <?php if ($filterCaseId > 0): ?>
        <input type="hidden" name="case_id" value="<?= $filterCaseId ?>">
    <?php endif; ?>
    <label for="sugg-q">Rechercher</label>
    <div class="case-search-control">
        <input
            id="sugg-q"
            name="q"
            type="search"
            value="<?= $h($searchQuery) ?>"
            placeholder="Titre, motif, type, détail de signal…"
        >
        <button type="submit" aria-label="Lancer la recherche">→</button>
    </div>
    <?php if ($searchQuery !== ''): ?>
        <a class="link" href="<?= $h(url('atak/sse/rapprochements' . ($filterCaseId > 0 ? '?case_id=' . $filterCaseId : ''))) ?>">Effacer</a>
    <?php endif; ?>
</form>

<section class="panel">
    <div class="panel-header">
        <div class="panel-title"><span class="panel-index">25.01</span> File à traiter</div>
        <div class="panel-meta"><?= $pendingCount ?> en attente<?= $searchQuery !== '' ? ' · filtrée' : '' ?></div>
    </div>
    <div class="panel-body">
        <?php if ($suggestions === []): ?>
            <div class="empty-state">
                <div class="empty-state-inner">
                    <div class="empty-symbol"><?= $searchQuery !== '' ? '—' : 'OK' ?></div>
                    <strong><?= $searchQuery !== '' ? 'Aucun résultat' : 'Aucune proposition en attente' ?></strong>
                    <p><?= $searchQuery !== ''
                        ? 'Aucune proposition ne correspond à cette recherche.'
                        : 'Le prochain passage nocturne ou un lancement manuel pourra en produire.' ?></p>
                </div>
            </div>
        <?php else: ?>
            <ul class="sse-sugg-list">
                <?php foreach ($suggestions as $s): ?>
                    <li class="sse-sugg-item sse-sugg-item--<?= $h($s['confidence'] ?? 'possible') ?>">
                        <div>
                            <strong><?= $h($s['title'] ?? '') ?></strong>
                            <span class="sse-ana-tag"><?= $h($s['confidence_label'] ?? '') ?></span>
                            <span class="sse-ana-tag"><?= $h($s['kind_label'] ?? '') ?></span>
                            <span class="muted">score <?= (int) ($s['score'] ?? 0) ?></span>
                            <p><?= $h($s['reason'] ?? '') ?></p>
                            <?php if (!empty($s['case_id'])): ?>
                                <a class="link" href="<?= $h(url('atak/sse/dossiers/' . (int) $s['case_id'])) ?>">Ouvrir le dossier</a>
                            <?php endif; ?>
                        </div>
                        <?php if ($canManage): ?>
                            <div class="sse-sugg-actions">
                                <form method="post" action="<?= $h(url('atak/sse/rapprochements/' . (int) $s['id'] . '/valider')) ?>">
                                    <?= \App\Core\Csrf::field() ?>
                                    <input type="hidden" name="case_id" value="<?= (int) ($s['case_id'] ?? $filterCaseId) ?>">
                                    <button class="btn btn--sm" type="submit">Valider</button>
                                </form>
                                <form method="post" action="<?= $h(url('atak/sse/rapprochements/' . (int) $s['id'] . '/rejeter')) ?>">
                                    <?= \App\Core\Csrf::field() ?>
                                    <input type="hidden" name="case_id" value="<?= (int) ($s['case_id'] ?? $filterCaseId) ?>">
                                    <button class="btn btn--ghost btn--sm" type="submit">Rejeter</button>
                                </form>
                            </div>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</section>

<section class="panel">
    <div class="panel-header">
        <div class="panel-title"><span class="panel-index">25.02</span> Historique des décisions</div>
        <div class="panel-meta"><?= $historyCount ?> décision<?= $historyCount > 1 ? 's' : '' ?><?= $searchQuery !== '' ? ' · filtrée' : '' ?></div>
    </div>
    <div class="panel-body">
        <?php if ($history === []): ?>
            <p class="muted"><?= $searchQuery !== ''
                ? 'Aucune décision ne correspond à cette recherche.'
                : 'Aucune validation ni rejet enregistré pour l’instant.' ?></p>
        <?php else: ?>
            <ul class="sse-sugg-list">
                <?php foreach ($history as $s):
                    $status = (string) ($s['status'] ?? '');
                    $statusClass = match ($status) {
                        'accepted' => 'accepted',
                        'rejected' => 'rejected',
                        default => 'deferred',
                    };
                    ?>
                    <li class="sse-sugg-item sse-sugg-item--history sse-sugg-item--<?= $h($statusClass) ?>">
                        <div>
                            <strong><?= $h($s['title'] ?? '') ?></strong>
                            <span class="badge"><?= $h($s['status_label'] ?? $status) ?></span>
                            <span class="sse-ana-tag"><?= $h($s['confidence_label'] ?? '') ?></span>
                            <span class="sse-ana-tag"><?= $h($s['kind_label'] ?? '') ?></span>
                            <p><?= $h($s['reason'] ?? '') ?></p>
                            <p class="muted sse-sugg-meta">
                                Décidé le <?= $h($fmtWhen($s['decided_at'] ?? $s['updated_at'] ?? null)) ?>
                                <?php if (!empty($s['author_label'])): ?>
                                    · par <?= $h($s['author_label']) ?>
                                <?php endif; ?>
                            </p>
                            <?php if (!empty($s['case_id'])): ?>
                                <a class="link" href="<?= $h(url('atak/sse/dossiers/' . (int) $s['case_id'])) ?>">Ouvrir le dossier</a>
                            <?php endif; ?>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</section>

<section class="panel">
    <div class="panel-header">
        <div class="panel-title"><span class="panel-index">25.03</span> Signaux analytiques</div>
        <div class="panel-meta"><?= count($signals) ?><?= $searchQuery !== '' ? ' · filtrés' : '' ?></div>
    </div>
    <div class="panel-body">
        <?php if ($signals === []): ?>
            <p class="muted"><?= $searchQuery !== '' ? 'Aucun signal pour cette recherche.' : 'Aucun signal ouvert.' ?></p>
        <?php else: ?>
            <ul class="sse-ana-suggest">
                <?php foreach ($signals as $sig): ?>
                    <li class="sse-ana-suggest__item sse-ana-suggest__item--<?= $h(($sig['severity'] ?? '') === 'high' || ($sig['severity'] ?? '') === 'critical' ? 'high' : 'medium') ?>">
                        <strong><?= $h($sig['title'] ?? '') ?></strong>
                        <span class="muted"><?= $h($sig['signal_type_label'] ?? '') ?> · <?= $h($sig['severity'] ?? '') ?></span>
                        <?php if (!empty($sig['detail'])): ?><em><?= $h($sig['detail']) ?></em><?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</section>
<?php
$sseContent = ob_get_clean();
require __DIR__ . '/_layout.php';
