<?php
declare(strict_types=1);

/**
 * Tableau de bord : documents à signer (prise en compte des doctrines).
 * Section « Signatures » + fenêtre modale à l’arrivée tant qu’une signature est attendue.
 * La signature passe par le même point d’entrée que la page du document (/documents/doctrine/{id}/acknowledge).
 */

use App\Support\Doctrine\DoctrineComplianceStatus;

$doctrinePending = is_array($doctrine_pending ?? null) ? $doctrine_pending : [];
$docSummary = is_array($document_inbox_summary ?? null) ? $document_inbox_summary : [];
$mandatory = (int) ($docSummary['mandatory'] ?? 0);
$overdue = (int) ($docSummary['overdue'] ?? 0);
$toRead = (int) ($docSummary['to_read'] ?? count($doctrinePending));

if ($doctrinePending === [] && $toRead < 1 && $mandatory < 1 && $overdue < 1) {
    return;
}

$signStatuses = [
    DoctrineComplianceStatus::ACK_REQUIRED,
    DoctrineComplianceStatus::ACK_OUTDATED,
    DoctrineComplianceStatus::OVERDUE,
];
$toSign = array_values(array_filter(
    $doctrinePending,
    static fn (array $item): bool => !empty($item['needs_signature'])
        && in_array((string) ($item['badge']['badge'] ?? ''), $signStatuses, true)
        && (int) ($item['version_id'] ?? 0) > 0
));
$toSignCount = count($toSign);
$esc = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$csrf = \App\Core\Csrf::token();
$certifyText = 'Je certifie avoir pris connaissance de ce document, dans la version indiquée.';

$statusHint = static function (string $badge): string {
    return match ($badge) {
        DoctrineComplianceStatus::OVERDUE => 'Signature attendue sans délai.',
        DoctrineComplianceStatus::ACK_OUTDATED => 'Une nouvelle version remplace celle que vous aviez signée.',
        DoctrineComplianceStatus::ACK_REQUIRED => 'Lecture et signature obligatoires.',
        default => 'Document à consulter.',
    };
};

$modalPayload = array_map(static fn (array $item): array => [
    'id' => (int) ($item['document_id'] ?? 0),
    'version_id' => (int) ($item['version_id'] ?? 0),
    'reference' => (string) ($item['reference'] ?? ''),
    'title' => (string) ($item['title'] ?? ''),
    'version' => (string) ($item['version_label'] ?? ''),
    'status' => (string) ($item['badge']['label'] ?? ''),
    'tone' => (string) ($item['badge']['tone'] ?? 'neutral'),
    'hint' => $statusHint((string) ($item['badge']['badge'] ?? '')),
    'deadline' => (string) ($item['deadline_label'] ?? ''),
    'href' => (string) ($item['href'] ?? ''),
    'ack_url' => (string) ($item['ack_url'] ?? ''),
], $toSign);
$modalJson = json_encode($modalPayload, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?: '[]';
?>
<section class="dash-zone dash-zone--signatures dash-reveal" id="documents-a-lire" aria-labelledby="dash-sign-title" data-dash-reveal
         data-dash-sign-root
         data-csrf="<?= $esc($csrf) ?>"
         data-certify="<?= $esc($certifyText) ?>"
         data-items="<?= $esc($modalJson) ?>">
    <div class="dash-zone__inner">
        <header class="dash-zone__head dash-sign__head">
            <div>
                <p class="dash-zone__kicker">Signatures</p>
                <h2 id="dash-sign-title" class="dash-zone__title">
                    <?= $toSignCount > 0 ? 'Documents à signer' : 'Documents à lire' ?>
                </h2>
                <p class="dash-zone__lead">
                    <?php if ($toSignCount > 0): ?>
                        Les directives ci-dessous exigent votre prise en compte signée. Chaque signature est horodatée et rattachée à la version lue.
                    <?php else: ?>
                        Des documents de votre communauté attendent votre lecture.
                    <?php endif; ?>
                </p>
            </div>
            <ul class="dash-sign__stats" aria-label="Synthèse">
                <li class="dash-sign__stat<?= $toSignCount > 0 ? ' is-alert' : '' ?>"><strong><?= $toSignCount ?></strong><span>à signer</span></li>
                <li class="dash-sign__stat<?= $overdue > 0 ? ' is-danger' : '' ?>"><strong><?= $overdue ?></strong><span>en retard</span></li>
                <li class="dash-sign__stat"><strong><?= $toRead ?></strong><span>à lire</span></li>
            </ul>
        </header>

        <?php if ($doctrinePending !== []): ?>
        <ul class="dash-sign__list">
            <?php foreach ($doctrinePending as $item): ?>
            <?php
            $badge = (string) ($item['badge']['badge'] ?? '');
            $tone = preg_replace('/[^a-z]/', '', (string) ($item['badge']['tone'] ?? 'neutral')) ?: 'neutral';
            $canSign = !empty($item['needs_signature']) && in_array($badge, $signStatuses, true) && (int) ($item['version_id'] ?? 0) > 0;
            $docId = (int) ($item['document_id'] ?? 0);
            ?>
            <li class="dash-sign__item dash-sign__item--<?= $esc($tone) ?>" data-dash-sign-item="<?= $docId ?>">
                <div class="dash-sign__main">
                    <p class="dash-sign__meta">
                        <?php if (($item['reference'] ?? '') !== ''): ?>
                        <code class="dash-sign__ref"><?= $esc($item['reference']) ?></code>
                        <?php endif; ?>
                        <?php if (($item['version_label'] ?? '') !== ''): ?>
                        <span class="dash-sign__version"><?= $esc($item['version_label']) ?></span>
                        <?php endif; ?>
                        <?php if (!empty($item['mandatory'])): ?>
                        <span class="dash-sign__flag">Obligatoire</span>
                        <?php endif; ?>
                    </p>
                    <a class="dash-sign__title" href="<?= $esc($item['href'] ?? '#') ?>"><?= $esc($item['title'] ?? '') ?></a>
                    <p class="dash-sign__hint"><?= $esc($statusHint($badge)) ?></p>
                </div>
                <div class="dash-sign__side">
                    <span class="dash-sign__chip dash-sign__chip--<?= $esc($tone) ?>" data-dash-sign-chip><?= $esc($item['badge']['label'] ?? '') ?></span>
                    <?php if (!empty($item['deadline_label'])): ?>
                    <span class="dash-sign__deadline"><?= $esc($item['deadline_label']) ?></span>
                    <?php endif; ?>
                    <div class="dash-sign__actions">
                        <a class="dash-sign__btn dash-sign__btn--ghost" href="<?= $esc($item['href'] ?? '#') ?>">Consulter</a>
                        <?php if ($canSign): ?>
                        <button type="button" class="dash-sign__btn dash-sign__btn--primary" data-dash-sign-open="<?= $docId ?>">Signer</button>
                        <?php endif; ?>
                    </div>
                </div>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>

        <p class="dash-sign__links">
            <a href="<?= url('documents/mes-documents') ?>">Ouvrir Mes documents</a>
            <a href="<?= url('documents') . '?category_slug=doctrine&doctrine_filter=action' ?>">Référentiel doctrinal</a>
        </p>
    </div>
</section>

<?php if ($toSignCount > 0): ?>
<div class="dash-sign-modal" data-dash-sign-modal hidden>
    <div class="dash-sign-modal__backdrop" data-dash-sign-close></div>
    <div class="dash-sign-modal__panel" role="dialog" aria-modal="true" aria-labelledby="dash-sign-modal-title" tabindex="-1">
        <header class="dash-sign-modal__head">
            <p class="dash-sign-modal__kicker">Signature obligatoire</p>
            <h2 id="dash-sign-modal-title" class="dash-sign-modal__title" data-dash-sign-modal-title>Documents à signer</h2>
            <p class="dash-sign-modal__lead" data-dash-sign-modal-lead>Prenez connaissance des directives suivantes et signez leur prise en compte.</p>
            <button type="button" class="dash-sign-modal__x" data-dash-sign-close aria-label="Fermer">×</button>
        </header>
        <ol class="dash-sign-modal__list" data-dash-sign-modal-list></ol>
        <footer class="dash-sign-modal__foot">
            <p class="dash-sign-modal__progress" data-dash-sign-progress></p>
            <button type="button" class="dash-sign__btn dash-sign__btn--ghost" data-dash-sign-close data-dash-sign-later>Plus tard</button>
        </footer>
    </div>
</div>
<script defer src="<?= $esc(asset_url('assets/js/dashboard-signatures.js')) ?>"></script>
<?php endif; ?>
