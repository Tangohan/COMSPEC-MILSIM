<?php
declare(strict_types=1);

/** @var array<string, mixed> $catalogItem */

$item = is_array($catalogItem ?? null) ? $catalogItem : [];
$code = (string) ($item['code'] ?? '');
$official = !empty($item['official']);
$archived = !empty($item['archived']);
$flashOk = trim((string) ($flashOk ?? ''));
$flashErr = trim((string) ($flashErr ?? ''));
$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$unitOutline = is_array($item['unit_outline'] ?? null) ? $item['unit_outline'] : [];
$functionGroups = is_array($item['function_groups'] ?? null) ? $item['function_groups'] : [];
$roleNames = is_array($item['role_names'] ?? null) ? $item['role_names'] : [];
$gradeLabel = (string) ($item['grade_label'] ?? '');
$apercuUrl = url('back-office/organisation/catalogue/apercu?modele=' . rawurlencode($code));
?>
<div class="bo-catalog">
    <header class="bo-catalog__hero">
        <p class="bo-catalog__kicker"><?= $official ? 'Modèle officiel Athena' : ($archived ? 'Modèle retiré' : 'Modèle de cette organisation') ?></p>
        <h1 class="bo-catalog__title"><?= $h((string) ($item['title'] ?? 'Modèle')) ?></h1>
        <p class="bo-catalog__lead"><?= $h((string) ($item['summary'] ?? '')) ?></p>
        <div class="bo-catalog__hero-actions">
            <?php if (!$archived): ?>
                <a class="ath-btn ath-btn--solid" href="<?= $h($apercuUrl) ?>">Aperçu et application</a>
            <?php endif; ?>
            <a class="ath-btn" href="<?= $h(url('back-office/organisation/catalogue')) ?>">Retour au catalogue</a>
            <a class="ath-btn" href="<?= $h(url('back-office/organisation/catalogue/historique')) ?>">Journal</a>
        </div>
    </header>

    <?php if ($flashOk !== ''): ?>
        <p class="ath-flash ath-flash--ok" role="status"><?= $h($flashOk) ?></p>
    <?php endif; ?>
    <?php if ($flashErr !== ''): ?>
        <p class="ath-flash ath-flash--err" role="alert"><?= $h($flashErr) ?></p>
    <?php endif; ?>

    <div class="bo-catalog__tiles" aria-label="Volume du modèle">
        <article class="bo-catalog__tile">
            <span class="bo-catalog__tile-kicker">Organigramme</span>
            <strong class="bo-catalog__tile-value"><?= (int) ($item['unit_count'] ?? 0) ?></strong>
            <span class="bo-catalog__tile-label">unité<?= (int) ($item['unit_count'] ?? 0) > 1 ? 's' : '' ?></span>
        </article>
        <article class="bo-catalog__tile">
            <span class="bo-catalog__tile-kicker">Fonctions</span>
            <strong class="bo-catalog__tile-value"><?= (int) ($item['function_count'] ?? 0) ?></strong>
            <span class="bo-catalog__tile-label">emploi<?= (int) ($item['function_count'] ?? 0) > 1 ? 's' : '' ?> métier</span>
        </article>
        <article class="bo-catalog__tile">
            <span class="bo-catalog__tile-kicker">Rôles</span>
            <strong class="bo-catalog__tile-value"><?= (int) ($item['role_count'] ?? 0) ?></strong>
            <span class="bo-catalog__tile-label">rôle<?= (int) ($item['role_count'] ?? 0) > 1 ? 's' : '' ?></span>
        </article>
        <article class="bo-catalog__tile">
            <span class="bo-catalog__tile-kicker">Grades</span>
            <strong class="bo-catalog__tile-value">1</strong>
            <span class="bo-catalog__tile-label"><?= $h($gradeLabel) ?></span>
        </article>
    </div>

    <div class="bo-catalog__outline-grid">
        <section class="bo-catalog__outline" aria-labelledby="bo-cat-units">
            <h2 id="bo-cat-units" class="bo-catalog__section-title">Organigramme</h2>
            <?php if ($unitOutline === []): ?>
                <p class="bo-catalog__empty">Aucune unité dans ce modèle.</p>
            <?php else: ?>
                <ul class="bo-catalog__tree">
                    <?php foreach ($unitOutline as $node): ?>
                        <?php $depth = max(0, min(8, (int) ($node['depth'] ?? 0))); ?>
                        <li style="--d: <?= $depth ?>"><?= $h((string) ($node['name'] ?? '')) ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <a class="ath-btn" href="<?= $h(url('back-office/groups')) ?>">Administrer les unités</a>
        </section>
        <section class="bo-catalog__outline" aria-labelledby="bo-cat-fn">
            <h2 id="bo-cat-fn" class="bo-catalog__section-title">Fonctions</h2>
            <?php if ($functionGroups === []): ?>
                <p class="bo-catalog__empty">Aucune fonction dans ce modèle.</p>
            <?php else: ?>
                <?php foreach ($functionGroups as $group): ?>
                    <p class="bo-catalog__group-title"><?= $h((string) ($group['category'] ?? '')) ?></p>
                    <ul class="bo-catalog__plain">
                        <?php foreach (is_array($group['names'] ?? null) ? $group['names'] : [] as $name): ?>
                            <li><?= $h((string) $name) ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endforeach; ?>
            <?php endif; ?>
            <a class="ath-btn" href="<?= $h(url('back-office/personnel-job-roles')) ?>">Administrer les fonctions</a>
        </section>
        <section class="bo-catalog__outline" aria-labelledby="bo-cat-roles">
            <h2 id="bo-cat-roles" class="bo-catalog__section-title">Rôles</h2>
            <?php if ($roleNames === []): ?>
                <p class="bo-catalog__empty">Aucun rôle dans ce modèle.</p>
            <?php else: ?>
                <ul class="bo-catalog__plain">
                    <?php foreach ($roleNames as $name): ?>
                        <li><?= $h((string) $name) ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <a class="ath-btn" href="<?= $h(url('back-office/roles')) ?>">Administrer les rôles</a>
        </section>
        <section class="bo-catalog__outline" aria-labelledby="bo-cat-grades">
            <h2 id="bo-cat-grades" class="bo-catalog__section-title">Grades</h2>
            <p class="bo-catalog__card-summary"><?= $h($gradeLabel) ?></p>
            <a class="ath-btn" href="<?= $h(url('back-office/referentiels/grades')) ?>">Administrer les grades</a>
        </section>
    </div>

    <?php if (!$official): ?>
        <section class="bo-catalog__section" aria-labelledby="bo-cat-manage">
            <h2 id="bo-cat-manage" class="bo-catalog__section-title">Administrer ce modèle</h2>
            <?php if ($archived): ?>
                <form method="post" action="<?= $h(url('back-office/organisation/catalogue/restaurer')) ?>">
                    <?= \App\Core\Csrf::field() ?>
                    <input type="hidden" name="modele" value="<?= $h($code) ?>">
                    <button type="submit" class="ath-btn ath-btn--solid">Restaurer ce modèle</button>
                </form>
            <?php else: ?>
                <div class="bo-catalog__manage">
                    <form method="post" action="<?= $h(url('back-office/organisation/catalogue/renommer')) ?>" class="bo-catalog__rename">
                        <?= \App\Core\Csrf::field() ?>
                        <input type="hidden" name="modele" value="<?= $h($code) ?>">
                        <div class="bo-catalog__field">
                            <label for="catalog-rename-title">Nom du modèle</label>
                            <input id="catalog-rename-title" type="text" name="titre" maxlength="160" required value="<?= $h((string) ($item['title'] ?? '')) ?>">
                        </div>
                        <button type="submit" class="ath-btn ath-btn--solid">Renommer</button>
                    </form>
                    <form method="post" action="<?= $h(url('back-office/organisation/catalogue/actualiser')) ?>" onsubmit="return confirm('Remplacer le contenu de ce modèle par l’organisation actuelle ? Le journal des applications reste intact.');">
                        <?= \App\Core\Csrf::field() ?>
                        <input type="hidden" name="modele" value="<?= $h($code) ?>">
                        <button type="submit" class="ath-btn">Actualiser depuis l’organisation actuelle</button>
                    </form>
                    <form method="post" action="<?= $h(url('back-office/organisation/catalogue/retirer')) ?>" onsubmit="return confirm('Retirer ce modèle du catalogue ? Vous pourrez le restaurer plus tard. Le journal des applications est conservé.');">
                        <?= \App\Core\Csrf::field() ?>
                        <input type="hidden" name="modele" value="<?= $h($code) ?>">
                        <button type="submit" class="ath-btn">Retirer du catalogue</button>
                    </form>
                </div>
            <?php endif; ?>
        </section>
    <?php endif; ?>
</div>
