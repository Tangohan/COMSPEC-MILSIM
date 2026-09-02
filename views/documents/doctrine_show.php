<?php
declare(strict_types=1);

$document = $document ?? [];
$doctrine = $doctrine ?? [];
$versions = $versions ?? [];
$currentVersion = $currentVersion ?? null;
$compliance = $compliance ?? [];
$needsAckModal = !empty($needsAckModal);
$deadlineLabel = $deadlineLabel ?? null;
$csrf_token = (string) ($csrf_token ?? '');
$docId = (int) ($document['id'] ?? 0);
$versionId = (int) ($currentVersion['id'] ?? 0);
$versionLabel = '';
if (is_array($currentVersion)) {
    $versionLabel = trim((string) ($currentVersion['version_label'] ?? ''));
    if ($versionLabel === '' && isset($currentVersion['version_major'], $currentVersion['version_minor'])) {
        $versionLabel = 'v' . (int) $currentVersion['version_major'] . '.' . (int) $currentVersion['version_minor'];
    }
}
?>
<link rel="stylesheet" href="<?= htmlspecialchars(asset_url('assets/css/doctrine-referential.css'), ENT_QUOTES, 'UTF-8') ?>">

<div class="doctrine-show" data-doctrine-show data-document-id="<?= $docId ?>" data-version-id="<?= $versionId ?>">
    <div class="mx-auto max-w-5xl px-4 py-8 sm:px-6">
        <p class="doctrine-show__kicker">Référentiel doctrinal</p>
        <div class="doctrine-show__header">
            <div>
                <p class="doctrine-show__ref-label">Référence</p>
                <code class="doctrine-show__ref"><?= htmlspecialchars((string) ($doctrine['reference_code'] ?? ''), ENT_QUOTES, 'UTF-8') ?></code>
                <h1 class="doctrine-show__title"><?= htmlspecialchars((string) ($document['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?></h1>
            </div>
            <span class="doctrine-ref__badge doctrine-ref__badge--<?= htmlspecialchars((string) ($compliance['tone'] ?? 'neutral'), ENT_QUOTES, 'UTF-8') ?>">
                <?= htmlspecialchars((string) ($compliance['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
            </span>
        </div>

        <dl class="doctrine-show__meta">
            <div><dt>Version</dt><dd><?= htmlspecialchars($versionLabel !== '' ? $versionLabel : '—', ENT_QUOTES, 'UTF-8') ?></dd></div>
            <div><dt>Autorité</dt><dd><?= htmlspecialchars((string) ($doctrine['issuing_label'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></dd></div>
            <div><dt>Diffusion</dt><dd><?= htmlspecialchars((string) ($doctrine['diffusion_label'] ?? 'Interne'), ENT_QUOTES, 'UTF-8') ?></dd></div>
            <div><dt>Statut</dt><dd><?= htmlspecialchars(strtoupper((string) ($doctrine['doctrine_status'] ?? '')), ENT_QUOTES, 'UTF-8') ?></dd></div>
            <?php if ($deadlineLabel): ?>
            <div class="doctrine-show__deadline"><dt>Échéance</dt><dd><?= htmlspecialchars($deadlineLabel, ENT_QUOTES, 'UTF-8') ?></dd></div>
            <?php endif; ?>
        </dl>

        <?php if (!empty($doctrine['summary'])): ?>
        <section class="doctrine-show__summary">
            <h2>Résumé</h2>
            <p><?= nl2br(htmlspecialchars((string) $doctrine['summary'], ENT_QUOTES, 'UTF-8')) ?></p>
        </section>
        <?php endif; ?>

        <section class="doctrine-show__file">
            <h2>Document</h2>
            <?php if (!empty($document['file_path']) || !empty($document['version_id'])): ?>
            <div class="doctrine-show__file-actions">
                <a href="<?= url('documents/' . $docId . '/file') ?>" target="_blank" rel="noopener" class="doctrine-ref__btn doctrine-ref__btn--primary">Ouvrir le fichier</a>
                <a href="<?= url('documents/' . $docId . '/download') ?>" class="doctrine-ref__btn">Télécharger</a>
            </div>
            <?php else: ?>
            <p class="text-sm text-slate-500">Aucun fichier attaché à cette version.</p>
            <?php endif; ?>
        </section>

        <?php if ($versions !== []): ?>
        <section class="doctrine-show__history">
            <h2>Historique des versions</h2>
            <ul class="doctrine-show__version-list">
                <?php foreach ($versions as $v): ?>
                <?php
                $vl = trim((string) ($v['version_label'] ?? ''));
                if ($vl === '' && isset($v['version_major'], $v['version_minor'])) {
                    $vl = 'v' . (int) $v['version_major'] . '.' . (int) $v['version_minor'];
                }
                $when = (string) ($v['published_at'] ?? $v['created_at'] ?? '');
                ?>
                <li>
                    <strong><?= htmlspecialchars($vl !== '' ? $vl : 'Version ' . (int) ($v['version_number'] ?? 0), ENT_QUOTES, 'UTF-8') ?></strong>
                    <?php if ($when !== ''): ?> — <?= htmlspecialchars(date('d/m/Y', strtotime($when)), ENT_QUOTES, 'UTF-8') ?><?php endif; ?>
                    <?php if (!empty($v['change_summary']) || !empty($v['change_notes'])): ?>
                    <em><?= htmlspecialchars((string) ($v['change_summary'] ?? $v['change_notes'] ?? ''), ENT_QUOTES, 'UTF-8') ?></em>
                    <?php endif; ?>
                </li>
                <?php endforeach; ?>
            </ul>
        </section>
        <?php endif; ?>

        <p class="mt-8"><a href="<?= url('documents') . '?category_slug=doctrine' ?>" class="doctrine-ref__link">← Retour au référentiel</a></p>
    </div>
</div>

<?php if ($needsAckModal): ?>
<div class="doctrine-ack-modal" data-doctrine-ack-modal hidden>
    <div class="doctrine-ack-modal__backdrop" data-doctrine-ack-close></div>
    <div class="doctrine-ack-modal__panel" role="dialog" aria-modal="true" aria-labelledby="doctrine-ack-title">
        <h2 id="doctrine-ack-title" class="doctrine-ack-modal__title">Prise en compte d'une directive</h2>
        <dl class="doctrine-ack-modal__meta">
            <div><dt>Référence</dt><dd><code><?= htmlspecialchars((string) ($doctrine['reference_code'] ?? ''), ENT_QUOTES, 'UTF-8') ?></code></dd></div>
            <div><dt>Version</dt><dd><?= htmlspecialchars($versionLabel, ENT_QUOTES, 'UTF-8') ?></dd></div>
            <div><dt>Titre</dt><dd><?= htmlspecialchars((string) ($document['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?></dd></div>
        </dl>
        <p class="doctrine-ack-modal__text">« Je reconnais avoir pris connaissance du présent document dans sa version indiquée ci-dessus. »</p>
        <form data-doctrine-ack-form data-endpoint="<?= htmlspecialchars(url('documents/doctrine/' . $docId . '/acknowledge'), ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="version_id" value="<?= $versionId ?>">
            <label class="doctrine-ack-modal__check">
                <input type="checkbox" name="certify" value="1" required>
                Je certifie avoir pris connaissance de ce document.
            </label>
            <p class="doctrine-ack-modal__status" data-doctrine-ack-status hidden></p>
            <button type="submit" class="doctrine-ref__btn doctrine-ref__btn--primary">Signer et prendre en compte</button>
        </form>
    </div>
</div>
<?php endif; ?>

<script defer src="<?= htmlspecialchars(asset_url('assets/js/doctrine-referential.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
