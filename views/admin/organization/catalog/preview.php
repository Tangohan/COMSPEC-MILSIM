<?php
declare(strict_types=1);

/** @var array<string, mixed> $catalogPreview */
/** @var array{orbat?: bool, grades?: bool, functions?: bool, roles?: bool} $catalogParts */

$preview = is_array($catalogPreview ?? null) ? $catalogPreview : [];
$item = is_array($preview['item'] ?? null) ? $preview['item'] : [];
$report = is_array($preview['report'] ?? null) ? $preview['report'] : [];
$parts = is_array($catalogParts ?? null) ? $catalogParts : ['orbat' => true, 'grades' => true, 'functions' => true, 'roles' => true];
$code = (string) ($item['code'] ?? '');
$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$gradeLabel = (string) ($item['grade_label'] ?? 'Système de grades déjà en place, ou non précisé');
$showUrl = url('back-office/organisation/catalogue/modele?modele=' . rawurlencode($code));

$addedUnits = is_array($report['units_added_names'] ?? null) ? $report['units_added_names'] : [];
$keptUnits = is_array($report['units_kept_names'] ?? null) ? $report['units_kept_names'] : [];
$addedFn = is_array($report['functions_added_names'] ?? null) ? $report['functions_added_names'] : [];
$keptFn = is_array($report['functions_kept_names'] ?? null) ? $report['functions_kept_names'] : [];
$addedRoles = is_array($report['roles_added_names'] ?? null) ? $report['roles_added_names'] : [];
$keptRoles = is_array($report['roles_kept_names'] ?? null) ? $report['roles_kept_names'] : [];
?>
<div class="bo-catalog">
    <header class="bo-catalog__hero">
        <p class="bo-catalog__kicker">Aperçu</p>
        <h1 class="bo-catalog__title"><?= $h((string) ($item['title'] ?? 'Modèle')) ?></h1>
        <p class="bo-catalog__lead"><?= $h((string) ($item['summary'] ?? '')) ?></p>
        <div class="bo-catalog__hero-actions">
            <a class="ath-btn ath-btn--solid" href="<?= $h($showUrl) ?>">Voir le contenu complet</a>
            <a class="ath-btn" href="<?= $h(url('back-office/organisation/catalogue')) ?>">Retour au catalogue</a>
        </div>
    </header>

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
            <span class="bo-catalog__tile-label">rôle<?= (int) ($item['role_count'] ?? 0) > 1 ? 's' : '' ?> de communauté</span>
        </article>
        <article class="bo-catalog__tile">
            <span class="bo-catalog__tile-kicker">Grades</span>
            <strong class="bo-catalog__tile-value">1</strong>
            <span class="bo-catalog__tile-label"><?= $h($gradeLabel) ?></span>
        </article>
    </div>

    <p class="bo-catalog__report" role="status">
        <?= $h((string) ($report['summary'] ?? 'Rien ne sera modifié tant que vous n’appliquez pas.')) ?>
    </p>

    <div class="bo-catalog__diff">
        <?php if ($addedUnits !== [] || $keptUnits !== []): ?>
            <section class="bo-catalog__outline">
                <h2 class="bo-catalog__section-title">Organigramme</h2>
                <?php if ($addedUnits !== []): ?>
                    <p class="bo-catalog__group-title">Seront ajoutées</p>
                    <ul class="bo-catalog__plain"><?php foreach ($addedUnits as $n): ?><li><?= $h((string) $n) ?></li><?php endforeach; ?></ul>
                <?php endif; ?>
                <?php if ($keptUnits !== []): ?>
                    <p class="bo-catalog__group-title">Déjà en place, inchangées</p>
                    <ul class="bo-catalog__plain"><?php foreach ($keptUnits as $n): ?><li><?= $h((string) $n) ?></li><?php endforeach; ?></ul>
                <?php endif; ?>
            </section>
        <?php endif; ?>
        <?php if ($addedFn !== [] || $keptFn !== []): ?>
            <section class="bo-catalog__outline">
                <h2 class="bo-catalog__section-title">Fonctions</h2>
                <?php if ($addedFn !== []): ?>
                    <p class="bo-catalog__group-title">Seront ajoutées</p>
                    <ul class="bo-catalog__plain"><?php foreach ($addedFn as $n): ?><li><?= $h((string) $n) ?></li><?php endforeach; ?></ul>
                <?php endif; ?>
                <?php if ($keptFn !== []): ?>
                    <p class="bo-catalog__group-title">Déjà en place, inchangées</p>
                    <ul class="bo-catalog__plain"><?php foreach ($keptFn as $n): ?><li><?= $h((string) $n) ?></li><?php endforeach; ?></ul>
                <?php endif; ?>
            </section>
        <?php endif; ?>
        <?php if ($addedRoles !== [] || $keptRoles !== []): ?>
            <section class="bo-catalog__outline">
                <h2 class="bo-catalog__section-title">Rôles</h2>
                <?php if ($addedRoles !== []): ?>
                    <p class="bo-catalog__group-title">Seront ajoutés</p>
                    <ul class="bo-catalog__plain"><?php foreach ($addedRoles as $n): ?><li><?= $h((string) $n) ?></li><?php endforeach; ?></ul>
                <?php endif; ?>
                <?php if ($keptRoles !== []): ?>
                    <p class="bo-catalog__group-title">Déjà en place, inchangés</p>
                    <ul class="bo-catalog__plain"><?php foreach ($keptRoles as $n): ?><li><?= $h((string) $n) ?></li><?php endforeach; ?></ul>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    </div>

    <form method="post" action="<?= $h(url('back-office/organisation/catalogue/appliquer')) ?>">
        <?= \App\Core\Csrf::field() ?>
        <input type="hidden" name="modele" value="<?= $h($code) ?>">
        <fieldset class="bo-catalog__include">
            <legend class="bo-catalog__section-title">Éléments à copier</legend>
            <p class="bo-catalog__section-lead">Décochez ce que vous ne souhaitez pas ajouter. Les éléments déjà présents sous le même nom restent inchangés.</p>
            <label class="bo-catalog__check">
                <input type="checkbox" name="inclure[organigramme]" value="1" <?= !empty($parts['orbat']) ? 'checked' : '' ?>>
                <span>
                    <strong>Organigramme</strong>
                    <span>Unités et sous-unités du modèle.</span>
                </span>
            </label>
            <label class="bo-catalog__check">
                <input type="checkbox" name="inclure[grades]" value="1" <?= !empty($parts['grades']) ? 'checked' : '' ?>>
                <span>
                    <strong>Grades</strong>
                    <span>Retient le système de grades du modèle uniquement s’il n’est pas déjà choisi.</span>
                </span>
            </label>
            <label class="bo-catalog__check">
                <input type="checkbox" name="inclure[fonctions]" value="1" <?= !empty($parts['functions']) ? 'checked' : '' ?>>
                <span>
                    <strong>Fonctions</strong>
                    <span>Emplois métier (chef de groupe, recruteur, etc.).</span>
                </span>
            </label>
            <label class="bo-catalog__check">
                <input type="checkbox" name="inclure[roles]" value="1" <?= !empty($parts['roles']) ? 'checked' : '' ?>>
                <span>
                    <strong>Rôles et droits</strong>
                    <span>Rôles de communauté, avec un profil de droits cohérent. Les rôles fondateurs restent intacts.</span>
                </span>
            </label>
        </fieldset>
        <div class="bo-catalog__card-actions">
            <button type="submit" class="ath-btn ath-btn--solid">Appliquer à cette communauté</button>
            <a class="ath-btn" href="<?= $h(url('back-office/organisation/catalogue')) ?>">Annuler</a>
        </div>
    </form>

    <nav class="bo-catalog__hubs" aria-label="Écrans d’édition">
        <a class="ath-btn" href="<?= $h(url('back-office/groups')) ?>">Administrer les unités</a>
        <a class="ath-btn" href="<?= $h(url('back-office/referentiels/grades')) ?>">Administrer les grades</a>
        <a class="ath-btn" href="<?= $h(url('back-office/personnel-job-roles')) ?>">Administrer les fonctions</a>
        <a class="ath-btn" href="<?= $h(url('back-office/roles')) ?>">Administrer les rôles</a>
    </nav>
</div>
