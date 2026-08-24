<?php
declare(strict_types=1);

$templates = is_array($aarTemplates ?? null) ? $aarTemplates : [];
$csrfToken = (string) ($csrfToken ?? \App\Core\Csrf::token());
$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');

$s = \App\Core\Session::getFlash('success');
$e = \App\Core\Session::getFlash('error');
?>
<?php if ($s): ?><div class="ath-banner-warn ath-rise" style="background:#e6f8f0;border-color:#bfe9d8;margin-bottom:16px;" role="status"><div class="ath-banner-warn__text" style="color:#0b6b47;"><?= $h((string) $s) ?></div></div><?php endif; ?>
<?php if ($e): ?><div class="ath-banner-warn ath-rise" style="margin-bottom:16px;" role="alert"><div class="ath-banner-warn__text"><?= $h((string) $e) ?></div></div><?php endif; ?>

<p class="ath-aar-edit__lead">
    Ces modèles servent de questionnaire pour les comptes rendus de debriefing.
    Les rapports déjà déposés conservent les questions en vigueur au moment de la saisie.
</p>

<?php if ($templates === []): ?>
<div class="ath-card ath-aar-form-card ath-rise">
    <h2>Aucun modèle pour le moment</h2>
    <p class="ath-aar-form-card__hint">Créez un premier questionnaire (questions courtes, listes, cases à cocher ou texte libre).</p>
    <a class="ath-btn ath-btn--solid" href="<?= $h(url('back-office/atak/comptes-rendus/modeles/nouveau')) ?>">Créer un modèle</a>
</div>
<?php else: ?>
<div class="ath-aar-template-list ath-rise">
    <?php foreach ($templates as $tpl): ?>
        <?php
        $tid = (int) ($tpl['id'] ?? 0);
        $status = (string) ($tpl['status'] ?? 'active');
        $archived = $status === 'archived';
        $count = (int) ($tpl['field_count'] ?? 0);
        ?>
        <article class="ath-card ath-aar-template-card">
            <div class="ath-aar-template-card__head">
                <h2><?= $h((string) ($tpl['title'] ?? 'Modèle')) ?></h2>
                <span class="ath-tag <?= $archived ? 'ath-tag--muted' : '' ?>"><?= $h((string) ($tpl['status_label'] ?? ($archived ? 'Archivé' : 'Actif'))) ?></span>
            </div>
            <?php if (trim((string) ($tpl['description'] ?? '')) !== ''): ?>
            <p class="ath-aar-template-card__desc"><?= $h((string) $tpl['description']) ?></p>
            <?php endif; ?>
            <p class="ath-aar-template-card__meta">
                <?php
                $typeLabels = [];
                foreach (is_array($tpl['fields'] ?? null) ? $tpl['fields'] : [] as $field) {
                    if (!is_array($field)) {
                        continue;
                    }
                    $typeLabels[] = \App\Support\AarCustomForm::typeLabel((string) ($field['type'] ?? 'text'));
                }
                $typeLabels = array_values(array_unique($typeLabels));
                ?>
                <?= $count ?> question<?= $count > 1 ? 's' : '' ?>
                <?php if ($typeLabels !== []): ?>
                    · <?= $h(implode(', ', $typeLabels)) ?>
                <?php endif; ?>
                <?php if (trim((string) ($tpl['author_name'] ?? '')) !== ''): ?>
                    · <?= $h((string) $tpl['author_name']) ?>
                <?php endif; ?>
            </p>
            <div class="ath-aar-template-card__actions">
                <a class="ath-btn" href="<?= $h(url('back-office/atak/comptes-rendus/modeles/' . $tid)) ?>">Modifier</a>
                <?php if ($archived): ?>
                <form method="post" action="<?= $h(url('back-office/atak/comptes-rendus/modeles/' . $tid . '/reactiver')) ?>">
                    <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
                    <button type="submit" class="ath-btn">Réactiver</button>
                </form>
                <?php else: ?>
                <form method="post" action="<?= $h(url('back-office/atak/comptes-rendus/modeles/' . $tid . '/archiver')) ?>" onsubmit="return confirm('Archiver ce modèle ? Les comptes rendus déjà déposés restent lisibles.');">
                    <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
                    <button type="submit" class="ath-btn">Archiver</button>
                </form>
                <?php endif; ?>
            </div>
        </article>
    <?php endforeach; ?>
</div>
<?php endif; ?>
