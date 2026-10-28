<?php
declare(strict_types=1);

require base_path('views/admin/effectifs_workspace/partials/rh_ui_helpers.php');

$inbox = is_array($rhActionInbox ?? null) ? $rhActionInbox : ['total' => 0, 'buckets' => [], 'actions' => []];
$buckets = is_array($inbox['buckets'] ?? null) ? $inbox['buckets'] : [];
$actions = is_array($inbox['actions'] ?? null) ? $inbox['actions'] : [];
$total = (int) ($inbox['total'] ?? 0);
$csrfToken = (string) ($csrfToken ?? '');

$bucketLabels = [
    'corrections' => 'Correction',
    'elevations' => 'Élévation',
    'deadlines' => 'Échéance',
    'incomplete' => 'Dossier incomplet',
    'mobility' => 'Mobilité',
    'alerts' => 'Suivi',
];
$bucketTone = static fn (string $bucket): string => match ($bucket) {
    'corrections', 'elevations', 'deadlines' => 'warn',
    'alerts' => 'danger',
    default => 'info',
};

// Une carte par membre : toutes ses actions ensemble. Les actions collectives (mobilité, alertes) à part.
$members = [];
$collective = [];
foreach ($actions as $action) {
    if (!is_array($action)) {
        continue;
    }
    $memberId = (int) ($action['member_id'] ?? 0);
    if ($memberId < 1) {
        $collective[] = $action;
        continue;
    }
    if (!isset($members[$memberId])) {
        $members[$memberId] = [
            'id' => $memberId,
            'label' => (string) ($action['member_label'] ?? 'Membre'),
            'actions' => [],
            'buckets' => [],
        ];
    }
    $members[$memberId]['actions'][] = $action;
    $members[$memberId]['buckets'][(string) ($action['bucket'] ?? '')] = true;
}

$initials = static function (string $label): string {
    $parts = preg_split('/\s+/u', trim($label)) ?: [];
    $out = '';
    foreach (array_slice($parts, 0, 2) as $part) {
        $out .= mb_strtoupper(mb_substr($part, 0, 1));
    }

    return $out !== '' ? $out : '?';
};
$chipCounts = [];
foreach ($buckets as $bucket) {
    $chipCounts[(string) ($bucket['id'] ?? '')] = (int) ($bucket['count'] ?? 0);
}
$dialogs = [];
?>
<div class="eff-inbox" data-ws>
    <section class="eff-inbox-head">
        <div class="eff-inbox-head__copy">
            <p class="eff-page-kicker">Bureau effectifs</p>
            <h2 class="eff-page-title">À traiter</h2>
            <p class="eff-page-lead">Les décisions qui vous attendent, regroupées par membre. Le système propose ; vous décidez.</p>
        </div>
        <div class="eff-inbox-head__total <?= $total > 0 ? 'is-warn' : 'is-ok' ?>">
            <strong><?= $total ?></strong>
            <span><?= $total > 0 ? 'action' . ($total > 1 ? 's' : '') . ' RH' : 'Tout est à jour' ?></span>
        </div>
    </section>

    <div class="eff-ws-toolbar" role="search">
        <div class="eff-ws-chips" role="group" aria-label="Filtrer par type">
            <button type="button" class="eff-ws-chip is-active" data-ws-chip="">Tout <span><?= count($members) + count($collective) ?></span></button>
            <?php foreach ($buckets as $bucket): ?>
                <?php $bid = (string) ($bucket['id'] ?? ''); $count = (int) ($bucket['count'] ?? 0); ?>
                <button type="button" class="eff-ws-chip eff-ws-chip--<?= $h($bucketTone($bid)) ?>" data-ws-chip="<?= $h($bid) ?>" <?= $count === 0 ? 'disabled' : '' ?>>
                    <?= $h((string) ($bucket['label'] ?? '')) ?> <span><?= $count ?></span>
                </button>
            <?php endforeach; ?>
        </div>
        <label class="eff-ws-search">
            <span class="sr-only">Rechercher un membre</span>
            <input type="search" placeholder="Rechercher un membre ou un sujet…" data-ws-search autocomplete="off">
        </label>
    </div>

    <section class="eff-inbox-list" id="actions" aria-labelledby="eff-inbox-list-title">
        <h3 class="eff-ws-section-title" id="eff-inbox-list-title">File d’actions</h3>
        <?php if ($members === [] && $collective === []): ?>
            <div class="eff-ws-empty">
                <p class="eff-ws-empty__title">Rien à traiter</p>
                <p>Aucune correction, élévation ni échéance en attente. Les dossiers sont à jour pour l’instant.</p>
            </div>
        <?php else: ?>
            <ul class="eff-inbox-members">
                <?php foreach ($members as $member): ?>
                    <?php
                    $memberUrl = effectifs_workspace_url('membres/' . $member['id']);
                    $searchText = $member['label'];
                    foreach ($member['actions'] as $a) {
                        $searchText .= ' ' . ($a['title'] ?? '') . ' ' . ($a['detail'] ?? '');
                    }
                    ?>
                    <li class="eff-inbox-member" data-ws-item data-ws-tags="<?= $h(implode(' ', array_keys($member['buckets']))) ?>" data-ws-text="<?= $h($searchText) ?>" id="membre-<?= (int) $member['id'] ?>">
                        <a class="eff-inbox-member__who" href="<?= $h($memberUrl) ?>">
                            <span class="eff-avatar" aria-hidden="true"><?= $h($initials($member['label'])) ?></span>
                            <span>
                                <strong><?= $h($member['label']) ?></strong>
                                <small><?= count($member['actions']) ?> point<?= count($member['actions']) > 1 ? 's' : '' ?> à traiter</small>
                            </span>
                        </a>
                        <ul class="eff-inbox-member__tasks">
                            <?php foreach ($member['actions'] as $action): ?>
                                <?php
                                $bucket = (string) ($action['bucket'] ?? '');
                                $actionId = preg_replace('/[^a-z0-9-]/i', '', (string) ($action['id'] ?? '')) ?: ('a' . count($dialogs));
                                $issues = is_array($action['issues'] ?? null) ? $action['issues'] : [];
                                $decideUrl = (string) ($action['decide_url'] ?? '');
                                ?>
                                <li class="eff-inbox-task">
                                    <span class="eff-pill eff-pill--<?= $h($bucketTone($bucket)) ?>"><?= $h($bucketLabels[$bucket] ?? 'Action') ?></span>
                                    <div class="eff-inbox-task__body">
                                        <p class="eff-inbox-task__title"><?= $h((string) ($action['title'] ?? '')) ?></p>
                                        <?php if ($issues !== []): ?>
                                            <p class="eff-inbox-task__fix">
                                                <span>À compléter :</span>
                                                <?php foreach ($issues as $issue): ?>
                                                    <a class="eff-fix-link" href="<?= $h((string) $issue['href']) ?>"><?= $h((string) $issue['label']) ?></a>
                                                <?php endforeach; ?>
                                            </p>
                                        <?php elseif (trim((string) ($action['detail'] ?? '')) !== ''): ?>
                                            <p class="eff-inbox-task__detail"><?= $h((string) $action['detail']) ?></p>
                                        <?php endif; ?>
                                    </div>
                                    <div class="eff-inbox-task__cta">
                                        <?php if ($decideUrl !== ''): ?>
                                            <?php $dialogs[] = ['id' => 'eff-dlg-' . $actionId, 'action' => $action, 'member' => $member['label']]; ?>
                                            <button type="button" class="ath-btn ath-btn--solid ath-btn--sm" data-dialog-open="eff-dlg-<?= $h($actionId) ?>">Décider</button>
                                        <?php elseif ($bucket !== 'incomplete'): ?>
                                            <a class="ath-btn ath-btn--solid ath-btn--sm" href="<?= $h((string) ($action['href'] ?? $memberUrl)) ?>"><?= $h((string) ($action['cta_label'] ?? 'Traiter')) ?></a>
                                        <?php endif; ?>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        <a class="ath-btn ath-btn--sm eff-inbox-member__open" href="<?= $h($memberUrl) ?>">Ouvrir la fiche</a>
                    </li>
                <?php endforeach; ?>
            </ul>

            <?php if ($collective !== []): ?>
                <h3 class="eff-ws-section-title">Suivi collectif</h3>
                <ul class="eff-inbox-collective">
                    <?php foreach ($collective as $action): ?>
                        <?php $bucket = (string) ($action['bucket'] ?? ''); ?>
                        <li class="eff-inbox-collective__item" data-ws-item data-ws-tags="<?= $h($bucket) ?>" data-ws-text="<?= $h(($action['member_label'] ?? '') . ' ' . ($action['title'] ?? '') . ' ' . ($action['detail'] ?? '')) ?>">
                            <span class="eff-pill eff-pill--<?= $h($bucketTone($bucket)) ?>"><?= $h((string) ($action['member_label'] ?? 'Suivi')) ?></span>
                            <div class="eff-inbox-task__body">
                                <p class="eff-inbox-task__title"><?= $h((string) ($action['title'] ?? '')) ?></p>
                                <p class="eff-inbox-task__detail"><?= $h((string) ($action['detail'] ?? '')) ?></p>
                            </div>
                            <a class="ath-btn ath-btn--sm" href="<?= $h((string) ($action['href'] ?? '#')) ?>"><?= $h((string) ($action['cta_label'] ?? 'Voir')) ?></a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <div class="eff-ws-empty" data-ws-empty hidden>
                <p class="eff-ws-empty__title">Aucun résultat</p>
                <p>Rien ne correspond à ce filtre. Essayez un autre type ou effacez la recherche.</p>
            </div>
        <?php endif; ?>
    </section>
</div>

<?php foreach ($dialogs as $dialog): ?>
    <?php $action = $dialog['action']; $changes = is_array($action['changes'] ?? null) ? $action['changes'] : []; ?>
    <?php
    $memberNote = trim((string) ($action['member_note'] ?? ''));
    $requestedBy = trim((string) ($action['requested_by'] ?? ''));
    ?>
    <dialog class="eff-dialog eff-dialog--decision" id="<?= $h($dialog['id']) ?>" aria-labelledby="<?= $h($dialog['id']) ?>-t">
        <form method="post" action="<?= $h((string) $action['decide_url']) ?>" class="eff-dialog__body">
            <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
            <input type="hidden" name="return_to" value="a-traiter">
            <header class="eff-dialog__head">
                <div>
                    <p class="eff-page-kicker">Demande de correction</p>
                    <h2 id="<?= $h($dialog['id']) ?>-t"><?= $h($dialog['member']) ?></h2>
                </div>
                <button type="button" class="eff-dialog__close" data-dialog-close aria-label="Fermer">×</button>
            </header>
            <?php if ($requestedBy !== ''): ?>
                <p class="eff-dialog__meta">Demandé par <?= $h($requestedBy) ?></p>
            <?php endif; ?>
            <p class="eff-dialog__lead">Confirmer applique ces changements au dossier. Refuser le laisse tel qu’il est.</p>
            <?php if ($memberNote !== ''): ?>
                <blockquote class="eff-dialog__quote"><span class="eff-dialog__quote-label">Message du membre</span><?= $h($memberNote) ?></blockquote>
            <?php endif; ?>
            <?php if ($changes !== []): ?>
                <ul class="eff-dialog__changes">
                    <?php foreach ($changes as $change): ?>
                        <?php
                        $beforeLines = is_array($change['before'] ?? null) ? $change['before'] : [];
                        $afterLines = is_array($change['after'] ?? null) ? $change['after'] : [];
                        $blank = trim((string) ($change['blank'] ?? 'Non renseigné'));
                        if ($blank === '') {
                            $blank = 'Non renseigné';
                        }
                        ?>
                        <li class="eff-dialog__change">
                            <p class="eff-dialog__change-label"><?= $h((string) ($change['label'] ?? 'Information du dossier')) ?></p>
                            <p class="eff-dialog__change-row">
                                <span>Actuellement</span>
                                <strong>
                                    <?php if ($beforeLines === []): ?>
                                        <?= $h($blank) ?>
                                    <?php else: ?>
                                        <?php foreach ($beforeLines as $line): ?><span class="eff-dialog__change-line"><?= $h((string) $line) ?></span><?php endforeach; ?>
                                    <?php endif; ?>
                                </strong>
                            </p>
                            <p class="eff-dialog__change-row eff-dialog__change-row--next">
                                <span>Proposé</span>
                                <strong>
                                    <?php if ($afterLines === []): ?>
                                        <?= $h($blank) ?>
                                    <?php else: ?>
                                        <?php foreach ($afterLines as $line): ?><span class="eff-dialog__change-line"><?= $h((string) $line) ?></span><?php endforeach; ?>
                                    <?php endif; ?>
                                </strong>
                            </p>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <label class="eff-dialog__field">
                Message au membre (facultatif)
                <input type="text" name="resolution_note" maxlength="1000" placeholder="Ce message lui sera transmis avec votre décision">
            </label>
            <footer class="eff-dialog__foot">
                <a class="eff-dialog__link" href="<?= $h((string) ($action['href'] ?? '#')) ?>">Ouvrir la page des corrections</a>
                <button type="submit" name="decision" value="rejected" class="ath-btn">Refuser</button>
                <button type="submit" name="decision" value="approved" class="ath-btn ath-btn--solid">Confirmer</button>
            </footer>
        </form>
    </dialog>
<?php endforeach; ?>

<?php $rhShortcutCurrent = 'a-traiter'; require base_path('views/admin/effectifs_workspace/partials/rh_shortcuts.php'); ?>
<script src="<?= $h(asset_url('assets/js/eff-member.js')) ?>" defer></script>
<script src="<?= $h(asset_url('assets/js/eff-workspace.js')) ?>" defer></script>
