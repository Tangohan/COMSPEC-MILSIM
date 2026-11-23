<?php
declare(strict_types=1);
ob_start();
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
/** @var list<array<string,mixed>> $models */
$models = is_array($models ?? null) ? $models : [];
/** @var array<string,string> $groups */
$groups = is_array($groups ?? null) ? $groups : [];
/** @var array<string,string> $typeLabels */
$typeLabels = is_array($typeLabels ?? null) ? $typeLabels : [];
$caseId = (int) ($caseId ?? 0);
$caseRef = (string) ($caseRef ?? '');
$preferredType = (string) ($preferredType ?? '');
$groups = $groups + ['perso' => 'Mes modèles', 'communaute' => 'Modèles partagés par votre communauté'];
$byGroup = [];
foreach ($models as $m) {
    $byGroup[(string) $m['group']][] = $m;
}
$classAbbr = static function (string $code): string {
    return (string) (\App\Support\SseUsClassification::SCALE[$code]['abbr'] ?? '');
};
$classLabel = static function (string $code): string {
    return \App\Repositories\SseCaseRepository::CLASSIFICATION_LABELS[$code] ?? '';
};
$link = static function (string $code) use ($caseId): string {
    return url('atak/sse/documents/nouveau') . '?' . http_build_query(['modele' => $code] + ($caseId > 0 ? ['case_id' => $caseId] : []));
};
?>
<link rel="stylesheet" href="<?= $h(asset_url('assets/css/sse-doc-editor.css')) ?>">
<link rel="stylesheet" href="<?= $h(asset_url('assets/css/sse-doc-templates.css')) ?>">

<section class="sse-desk-hero sse-desk-hero--compact" aria-labelledby="sse-tpl-title">
    <div class="sse-desk-hero__main">
        <h1 id="sse-tpl-title">Nouveau document</h1>
        <p class="sse-desk-hero__lead">
            Choisissez le modèle qui ressemble le plus à ce que vous voulez écrire. Il s’ouvre déjà mis en page,
            avec votre nom, la date et la référence du dossier, et des conseils grisés à chaque étape.
            <?php if ($caseRef !== ''): ?>
                Le document sera rattaché au dossier <strong><?= $h($caseRef) ?></strong>.
            <?php endif; ?>
        </p>
        <ol class="sse-tpl-steps" aria-label="Les trois étapes">
            <li class="is-current"><span aria-hidden="true">1</span> Choisir un modèle</li>
            <li><span aria-hidden="true">2</span> Remplacer le texte grisé</li>
            <li><span aria-hidden="true">3</span> Enregistrer et faire relire</li>
        </ol>
    </div>
</section>

<?php foreach ($groups as $gKey => $gLabel): ?>
    <?php if (empty($byGroup[$gKey])) { continue; } ?>
    <section class="sse-tpl-group" aria-labelledby="sse-tpl-g-<?= $h($gKey) ?>">
        <h2 id="sse-tpl-g-<?= $h($gKey) ?>"><?= $h($gLabel) ?></h2>
        <ul class="sse-tpl-grid">
            <?php foreach ($byGroup[$gKey] as $m): ?>
                <?php $cls = (string) $m['classification']; $recommended = $preferredType !== '' && (string) $m['document_type'] === $preferredType && empty($m['own']); ?>
                <li class="sse-tpl-card<?= $recommended ? ' is-recommended' : '' ?>">
                    <a class="sse-tpl-card__link" href="<?= $h($link((string) $m['code'])) ?>" data-model="<?= $h($m['code']) ?>">
                        <div class="sse-tpl-thumb" data-level="<?= $h($cls) ?>" aria-hidden="true">
                            <div class="sse-tpl-thumb__band"><?= $h($classAbbr($cls)) ?></div>
                            <div class="sse-tpl-thumb__page"><?= $m['preview'] /* HTML assaini par SseRichText */ ?></div>
                            <div class="sse-tpl-thumb__band sse-tpl-thumb__band--bottom"><?= $h($classAbbr($cls)) ?></div>
                        </div>
                        <span class="sse-tpl-card__text">
                            <?php if ($recommended): ?><span class="sse-tpl-card__badge">Conseillé pour ce type</span><?php endif; ?>
                            <?php if (!empty($m['shared']) && !empty($m['own'])): ?><span class="sse-tpl-card__badge sse-tpl-card__badge--soft">Partagé</span><?php endif; ?>
                            <strong class="sse-tpl-card__title"><svg viewBox="0 0 16 16" width="16" height="16" aria-hidden="true" focusable="false"><path d="<?= $h($m['icon']) ?>" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg> <?= $h($m['label']) ?></strong>
                            <span class="sse-tpl-card__desc"><?= $h($m['description']) ?></span>
                            <span class="sse-tpl-card__meta"><?= $h($typeLabels[(string) $m['document_type']] ?? '') ?> · <?= $h($classLabel($cls)) ?></span>
                        </span>
                        <span class="sse-tpl-card__go">Utiliser ce modèle</span>
                    </a>
                    <?php if (!empty($m['deletable'])): ?>
                        <form method="post" action="<?= $h(url('atak/sse/documents/modeles/' . (int) $m['id'] . '/supprimer')) ?>" class="sse-tpl-card__del" data-confirm="Supprimer ce modèle ? Les documents déjà créés ne changent pas.">
                            <?= \App\Core\Csrf::field() ?>
                            <button type="submit" class="btn btn--ghost btn--sm">Supprimer</button>
                        </form>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
<?php endforeach; ?>

<p class="muted sse-desk-footnote">
    Vous avez déjà un document dont la mise en page vous convient ? Ouvrez-le, puis utilisez « Enregistrer ce document comme modèle »
    dans le panneau Modèles de l’éditeur : il apparaîtra ici, pour vous seul ou pour toute votre communauté.
</p>
<div class="toolbar-actions sse-desk-actions">
    <a class="btn btn--ghost" href="<?= $h(url('atak/sse/documents')) ?>">Retour à l’atelier</a>
</div>
<script>
(function () {
    var forms = document.querySelectorAll('.sse-tpl-card__del');
    Array.prototype.forEach.call(forms, function (f) {
        f.addEventListener('submit', function (e) { if (!window.confirm(f.getAttribute('data-confirm'))) { e.preventDefault(); } });
    });
})();
</script>
<?php
$sseContent = ob_get_clean();
require __DIR__ . '/_layout.php';
