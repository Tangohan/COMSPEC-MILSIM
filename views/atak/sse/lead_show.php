<?php
declare(strict_types=1);
ob_start();
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
/**
 * Une piste : son état, sa description, son responsable, et ce qui y est rattaché.
 *
 * @var array<string, mixed> $lead ligne de sse_leads (déjà contrôlée : lisible par ce lecteur)
 * @var string $owner nom du responsable
 * @var bool $canWrite
 * @var list<array{value: string, abbr: string, label: string}> $levels niveaux que le lecteur peut poser
 */
$lid = (int) ($lead['id'] ?? 0);
$states = \App\Support\SseAttachCatalog::LEAD_STATUSES;
$status = (string) ($lead['status'] ?? 'ouverte');
$class = (string) ($lead['classification'] ?? '');
$tone = ['ouverte' => 'open', 'suivie' => 'progress', 'close' => 'closed'][$status] ?? 'none';
$when = static function (?string $d): string {
    $t = $d !== null && $d !== '' ? strtotime($d) : false;

    return $t !== false ? date('d/m/Y', $t) : '';
};
?>
<link rel="stylesheet" href="<?= $h(asset_url('assets/css/sse-rattacher.css')) ?>">
<div class="page-heading">
    <div>
        <h1 translate="no"><?= $h($lead['title'] ?? '') ?></h1>
        <p class="ras">
            <span class="ra-mono" translate="no"><?= $h($lead['reference_code'] ?? '') ?></span>
            · <span class="ra-state"><span class="ra-dot ra-dot--<?= $h($tone) ?>"></span><?= $h($states[$status] ?? $status) ?></span>
            <?php if (\App\Support\SseAttachCatalog::isLevel($class)): ?>· <span class="ra-level ra-level--<?= $h($class) ?>" title="<?= $h(\App\Support\SseAttachCatalog::levelLabel($class)) ?>"><?= $h(\App\Support\SseAttachCatalog::levelAbbr($class)) ?></span><?php endif; ?>
            <?php if ((string) ($owner ?? '') !== ''): ?>· Responsable : <span translate="no"><?= $h($owner) ?></span><?php endif; ?>
            · Ouverte le <?= $h($when((string) ($lead['created_at'] ?? ''))) ?>
        </p>
    </div>
    <div class="page-heading__actions">
        <a class="btn btn--ghost" href="<?= $h(url('atak/sse/pistes')) ?>">Toutes les pistes</a>
        <?php if (!empty($canWrite)): ?>
            <button type="button" class="btn" data-ras-show="lead-edit" aria-haspopup="dialog">Modifier la piste</button>
        <?php endif; ?>
    </div>
</div>

<?php if (trim((string) ($lead['description'] ?? '')) !== ''): ?>
    <section class="panel" aria-labelledby="lead-desc">
        <div class="panel-header"><div class="panel-title" id="lead-desc">Hypothèse</div></div>
        <div class="panel-body"><p class="ras-text" translate="no"><?= $h($lead['description']) ?></p></div>
    </section>
<?php endif; ?>

<?php \App\Services\Sse\Links\SseLinksWidget::render('lead', $lid); ?>
<?php \App\Services\Sse\Attach\SseAttachWindow::section('lead', $lid); ?>

<?php if (!empty($canWrite)): ?>
    <dialog class="ras ras-dialog" id="lead-edit" aria-labelledby="lead-edit-t">
        <form method="post" action="<?= $h(url('atak/sse/pistes/' . $lid)) ?>">
            <?= \App\Core\Csrf::field() ?>
            <h2 id="lead-edit-t">Modifier la piste</h2>
            <p class="ra-lead">Relever la classification est possible jusqu’à votre habilitation ; l’abaisser demande le droit de gérer les dossiers et un motif.</p>
            <label class="ra-field">Titre<input type="text" name="title" maxlength="200" minlength="3" required autocomplete="off" value="<?= $h($lead['title'] ?? '') ?>"></label>
            <label class="ra-field">Description<textarea name="description" rows="3" maxlength="2000"><?= $h($lead['description'] ?? '') ?></textarea></label>
            <label class="ra-field">État
                <select name="status"><?php foreach ($states as $k => $label): ?><option value="<?= $h($k) ?>"<?= $status === $k ? ' selected' : '' ?>><?= $h($label) ?></option><?php endforeach; ?></select>
            </label>
            <label class="ra-field">Classification
                <select name="classification"><?php foreach ((array) ($levels ?? []) as $lv): ?><option value="<?= $h($lv['value']) ?>"<?= $class === $lv['value'] ? ' selected' : '' ?>><?= $h($lv['abbr'] . ' · ' . $lv['label']) ?></option><?php endforeach; ?></select>
            </label>
            <label class="ra-field"><span>Motif <span class="ra-opt">obligatoire pour abaisser la classification</span></span><input type="text" name="reason" maxlength="255" autocomplete="off"></label>
            <div class="ras-dialog__actions">
                <button type="button" class="ra-btn ra-btn--ghost" data-ras-close>Annuler</button>
                <button type="submit" class="ra-btn ra-btn--primary">Enregistrer</button>
            </div>
        </form>
    </dialog>
<?php endif; ?>
<?php
$sseContent = ob_get_clean();
require __DIR__ . '/_layout.php';
