<?php
declare(strict_types=1);

require base_path('views/admin/effectifs_workspace/partials/rh_ui_helpers.php');

$inbox = is_array($rhActionInbox ?? null) ? $rhActionInbox : ['total' => 0, 'buckets' => [], 'actions' => []];
$buckets = is_array($inbox['buckets'] ?? null) ? $inbox['buckets'] : [];
$actions = is_array($inbox['actions'] ?? null) ? $inbox['actions'] : [];
$total = (int) ($inbox['total'] ?? 0);

$toneClass = static function (string $tone): string {
    return match ($tone) {
        'warn' => 'eff-rh-tile--warn',
        'info' => 'eff-rh-tile--info',
        default => 'eff-rh-tile--ok',
    };
};
?>
<section class="eff-rh-hero">
    <p class="eff-page-kicker">Bureau effectifs</p>
    <h2 class="eff-page-title">À traiter</h2>
    <p class="eff-page-lead">
        Les décisions qui vous attendent : corrections demandées, élévations à examiner, échéances et dossiers incomplets.
        Le système propose ; vous décidez.
    </p>
    <div class="eff-rh-tiles" aria-label="Synthèse des actions RH">
        <article class="eff-rh-tile <?= $total > 0 ? 'eff-rh-tile--warn' : 'eff-rh-tile--ok' ?>">
            <span class="eff-rh-tile__kicker">Priorité</span>
            <strong class="eff-rh-tile__value"><?= $total ?></strong>
            <span class="eff-rh-tile__label">action<?= $total > 1 ? 's' : '' ?> RH requise<?= $total > 1 ? 's' : '' ?></span>
        </article>
        <?php foreach ($buckets as $bucket): ?>
            <?php
            $count = (int) ($bucket['count'] ?? 0);
            $tone = (string) ($bucket['tone'] ?? 'ok');
            $href = (string) ($bucket['href'] ?? effectifs_workspace_url('a-traiter'));
            ?>
            <article class="eff-rh-tile <?= $h($toneClass($tone)) ?>">
                <span class="eff-rh-tile__kicker"><?= $h((string) ($bucket['label'] ?? '')) ?></span>
                <a class="eff-rh-tile__hit" href="<?= $h($href) ?>">
                    <strong class="eff-rh-tile__value"><?= $count ?></strong>
                    <span class="eff-rh-tile__label"><?= $count > 0 ? 'À examiner' : 'Rien à signaler' ?></span>
                </a>
            </article>
        <?php endforeach; ?>
    </div>
</section>

<section class="eff-rh-list-card" id="actions">
    <div class="eff-rh-list-card__head">
        <h2 class="eff-rh-list-card__title">File d’actions</h2>
    </div>
    <p class="eff-rh-list-card__lead">Chaque ligne ouvre le dossier ou l’écran où prendre la décision.</p>
    <?php if ($actions === []): ?>
        <p class="eff-rh-list-card__empty">Aucune action en attente. Les dossiers sont à jour pour l’instant.</p>
    <?php else: ?>
        <ul class="eff-rh-inbox">
            <?php foreach ($actions as $action): ?>
                <?php
                if (!is_array($action)) {
                    continue;
                }
                $memberId = (int) ($action['member_id'] ?? 0);
                $cta = trim((string) ($action['cta_label'] ?? 'Traiter'));
                $href = (string) ($action['href'] ?? '#');
                ?>
                <li class="eff-rh-inbox__item" id="<?= $h((string) ($action['bucket'] ?? '')) ?>">
                    <div class="eff-rh-inbox__body">
                        <p class="eff-rh-inbox__member"><?= $h((string) ($action['member_label'] ?? 'Membre')) ?></p>
                        <p class="eff-rh-inbox__title"><?= $h((string) ($action['title'] ?? '')) ?></p>
                        <?php if (trim((string) ($action['detail'] ?? '')) !== ''): ?>
                        <p class="eff-rh-inbox__detail"><?= $h((string) $action['detail']) ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="eff-rh-inbox__actions">
                        <?php if ($memberId > 0): ?>
                        <a class="eff-btn eff-btn--ghost" href="<?= $h(effectifs_workspace_url('membres/' . $memberId)) ?>">Fiche</a>
                        <?php endif; ?>
                        <a class="eff-btn" href="<?= $h($href) ?>"><?= $h($cta) ?></a>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>

<?php $rhShortcutCurrent = 'a-traiter'; require base_path('views/admin/effectifs_workspace/partials/rh_shortcuts.php'); ?>

<style>
.eff-rh-inbox { list-style: none; margin: 0; padding: 0; display: grid; gap: 0.75rem; }
.eff-rh-inbox__item {
  display: flex; flex-wrap: wrap; gap: 0.85rem; justify-content: space-between; align-items: flex-start;
  padding: 0.95rem 1rem; border: 1px solid #dbeafe; border-radius: 14px; background: #f8fbff;
}
.eff-rh-inbox__member { margin: 0; font-size: 0.7rem; font-weight: 800; letter-spacing: 0.04em; text-transform: uppercase; color: #0369a1; }
.eff-rh-inbox__title { margin: 0.2rem 0 0; font-size: 0.95rem; font-weight: 700; color: #0f172a; }
.eff-rh-inbox__detail { margin: 0.35rem 0 0; font-size: 0.8125rem; color: #475569; line-height: 1.45; }
.eff-rh-inbox__actions { display: flex; flex-wrap: wrap; gap: 0.4rem; align-items: center; }
</style>
