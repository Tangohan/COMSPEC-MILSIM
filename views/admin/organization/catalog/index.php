<?php
declare(strict_types=1);

/** @var list<array<string, mixed>> $catalogItems */
/** @var list<array<string, mixed>> $catalogArchived */
/** @var list<array<string, mixed>> $catalogInstalls */
/** @var array<string, mixed> $catalogInventory */

$items = is_array($catalogItems ?? null) ? $catalogItems : [];
$archived = is_array($catalogArchived ?? null) ? $catalogArchived : [];
$installs = is_array($catalogInstalls ?? null) ? $catalogInstalls : [];
$inv = is_array($catalogInventory ?? null) ? $catalogInventory : [];
$flashOk = trim((string) ($flashOk ?? ''));
$flashErr = trim((string) ($flashErr ?? ''));
$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');

$officialCount = 0;
$privateCount = 0;
foreach ($items as $item) {
    if (!empty($item['official'])) {
        $officialCount++;
    } else {
        $privateCount++;
    }
}

$unitCount = (int) ($inv['unit_count'] ?? 0);
$functionCount = (int) ($inv['function_count'] ?? 0);
$roleCount = (int) ($inv['role_count'] ?? 0);
$gradeCount = (int) ($inv['grade_count'] ?? 0);
$installCount = (int) ($inv['install_count'] ?? count($installs));
$gradeLabel = trim((string) ($inv['grade_label'] ?? 'Système de grades'));
?>
<div class="bo-catalog">
    <header class="bo-catalog__hero">
        <p class="bo-catalog__kicker">Organisation</p>
        <h1 class="bo-catalog__title">Catalogue de l’organisation</h1>
        <p class="bo-catalog__lead">
            Administrez toute la structure de cette communauté, copiez un modèle officiel Athena,
            ou enregistrez l’état actuel. Rien n’est partagé avec une autre communauté,
            et rien de déjà en place n’est écrasé.
        </p>
        <div class="bo-catalog__hero-actions">
            <a class="ath-btn ath-btn--solid" href="#bo-catalog-snapshot">Enregistrer un modèle</a>
            <a class="ath-btn" href="<?= $h(url('back-office/organisation/catalogue/historique')) ?>">Ouvrir le journal complet</a>
            <a class="ath-btn" href="<?= $h(url('back-office/organisation/structure')) ?>">Ouvrir la structure</a>
        </div>
    </header>

    <?php if ($flashOk !== ''): ?>
        <p class="ath-flash ath-flash--ok" role="status"><?= $h($flashOk) ?></p>
    <?php endif; ?>
    <?php if ($flashErr !== ''): ?>
        <p class="ath-flash ath-flash--err" role="alert"><?= $h($flashErr) ?></p>
    <?php endif; ?>

    <section class="bo-catalog__section" aria-labelledby="bo-catalog-admin-title">
        <h2 id="bo-catalog-admin-title" class="bo-catalog__section-title">Administrer cette organisation</h2>
        <p class="bo-catalog__section-lead">Chaque écran d’édition s’ouvre avec le volume actuel. Les modèles ci-dessous copient ce qui manque ; ces raccourcis servent à tenir le réel.</p>
        <div class="bo-catalog__admin" aria-label="Administration">
            <a class="bo-catalog__admin-card" href="<?= $h(url('back-office/groups')) ?>">
                <span class="bo-catalog__tile-kicker">Organigramme</span>
                <strong class="bo-catalog__tile-value"><?= $unitCount ?></strong>
                <span class="bo-catalog__tile-label"><?= $unitCount > 1 ? 'unités à administrer' : 'unité à administrer' ?></span>
                <span class="bo-catalog__admin-go">Administrer les unités</span>
            </a>
            <a class="bo-catalog__admin-card" href="<?= $h(url('back-office/referentiels/grades')) ?>">
                <span class="bo-catalog__tile-kicker">Grades</span>
                <strong class="bo-catalog__tile-value"><?= $gradeCount ?></strong>
                <span class="bo-catalog__tile-label"><?= $h($gradeLabel) ?></span>
                <span class="bo-catalog__admin-go">Administrer les grades</span>
            </a>
            <a class="bo-catalog__admin-card" href="<?= $h(url('back-office/personnel-job-roles')) ?>">
                <span class="bo-catalog__tile-kicker">Fonctions</span>
                <strong class="bo-catalog__tile-value"><?= $functionCount ?></strong>
                <span class="bo-catalog__tile-label"><?= $functionCount > 1 ? 'emplois métier' : 'emploi métier' ?></span>
                <span class="bo-catalog__admin-go">Administrer les fonctions</span>
            </a>
            <a class="bo-catalog__admin-card" href="<?= $h(url('back-office/roles')) ?>">
                <span class="bo-catalog__tile-kicker">Rôles</span>
                <strong class="bo-catalog__tile-value"><?= $roleCount ?></strong>
                <span class="bo-catalog__tile-label"><?= $roleCount > 1 ? 'rôles de communauté' : 'rôle de communauté' ?></span>
                <span class="bo-catalog__admin-go">Administrer les rôles</span>
            </a>
            <a class="bo-catalog__admin-card" href="<?= $h(url('back-office/roles/presets')) ?>">
                <span class="bo-catalog__tile-kicker">Droits</span>
                <strong class="bo-catalog__tile-value">Profils</strong>
                <span class="bo-catalog__tile-label">Kits de droits prêts à l’emploi</span>
                <span class="bo-catalog__admin-go">Administrer les profils de droits</span>
            </a>
            <a class="bo-catalog__admin-card" href="<?= $h(url('back-office/organisation/catalogue/historique')) ?>">
                <span class="bo-catalog__tile-kicker">Journal</span>
                <strong class="bo-catalog__tile-value"><?= $installCount ?></strong>
                <span class="bo-catalog__tile-label"><?= $installCount > 1 ? 'applications enregistrées' : 'application enregistrée' ?></span>
                <span class="bo-catalog__admin-go">Ouvrir le journal complet</span>
            </a>
        </div>
        <nav class="bo-catalog__hubs" aria-label="Autres écrans d’administration">
            <a class="ath-btn" href="<?= $h(url('back-office/organisation/structure')) ?>">Structure et recrutement</a>
            <a class="ath-btn" href="<?= $h(url('back-office/personnel-job-roles/assignments')) ?>">Affecter les fonctions</a>
            <a class="ath-btn" href="<?= $h(url('back-office/roles-permissions')) ?>">Matrice des droits</a>
            <a class="ath-btn" href="<?= $h(url('back-office/ressources/effectifs')) ?>">Bureau effectifs</a>
        </nav>
    </section>

    <section class="bo-catalog__section" aria-labelledby="bo-catalog-models-title">
        <h2 id="bo-catalog-models-title" class="bo-catalog__section-title">Modèles à copier</h2>
        <p class="bo-catalog__section-lead">
            <?= $officialCount ?> modèle<?= $officialCount > 1 ? 's' : '' ?> officiel<?= $officialCount > 1 ? 's' : '' ?> Athena
            · <?= $privateCount ?> modèle<?= $privateCount > 1 ? 's' : '' ?> de cette organisation.
            Ouvrez le contenu, puis appliquez ce qui manque.
        </p>
        <?php if ($items === []): ?>
            <p class="bo-catalog__empty">Aucun modèle n’est encore disponible.</p>
        <?php else: ?>
            <div class="bo-catalog__grid">
                <?php foreach ($items as $item): ?>
                    <?php
                    $code = (string) ($item['code'] ?? '');
                    $official = !empty($item['official']);
                    $modeleUrl = url('back-office/organisation/catalogue/modele?modele=' . rawurlencode($code));
                    $apercuUrl = url('back-office/organisation/catalogue/apercu?modele=' . rawurlencode($code));
                    ?>
                    <article class="bo-catalog__card">
                        <p class="bo-catalog__card-origin<?= $official ? '' : ' bo-catalog__card-origin--local' ?>">
                            <?= $official ? 'Modèle officiel Athena' : 'Modèle de cette organisation' ?>
                        </p>
                        <h3 class="bo-catalog__card-title"><?= $h((string) ($item['title'] ?? '')) ?></h3>
                        <p class="bo-catalog__card-summary"><?= $h((string) ($item['summary'] ?? '')) ?></p>
                        <p class="bo-catalog__card-volume"><?= $h((string) ($item['volume'] ?? '')) ?></p>
                        <div class="bo-catalog__card-actions">
                            <a class="ath-btn ath-btn--solid" href="<?= $h($modeleUrl) ?>">Voir le contenu</a>
                            <a class="ath-btn" href="<?= $h($apercuUrl) ?>">Aperçu et application</a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <section class="bo-catalog__section" id="bo-catalog-snapshot" aria-labelledby="bo-catalog-snap-title">
        <div class="bo-catalog__snapshot">
            <h2 id="bo-catalog-snap-title" class="bo-catalog__section-title">Enregistrer un modèle de cette organisation</h2>
            <p class="bo-catalog__section-lead">Une copie privée de l’organigramme, des fonctions, des rôles et du système de grades actuels. Elle n’est pas liée en direct : réappliquer créera ce qui manque, sans modifier ce qui a déjà été personnalisé.</p>
            <form method="post" action="<?= $h(url('back-office/organisation/catalogue/instantane')) ?>">
                <?= \App\Core\Csrf::field() ?>
                <div class="bo-catalog__field">
                    <label for="catalog-snap-title">Nom du modèle</label>
                    <input id="catalog-snap-title" type="text" name="titre" maxlength="160" placeholder="Par exemple : Structure été 2026">
                </div>
                <button type="submit" class="ath-btn ath-btn--solid">Enregistrer</button>
            </form>
        </div>
    </section>

    <section class="bo-catalog__section" aria-labelledby="bo-catalog-hist-title">
        <div class="bo-catalog__section-head">
            <h2 id="bo-catalog-hist-title" class="bo-catalog__section-title">Dernières applications</h2>
            <a class="ath-btn" href="<?= $h(url('back-office/organisation/catalogue/historique')) ?>">Journal complet</a>
        </div>
        <?php if ($installs === []): ?>
            <p class="bo-catalog__empty">Aucune application n’a encore été enregistrée pour cette communauté.</p>
        <?php else: ?>
            <ul class="bo-catalog__history">
                <?php foreach ($installs as $row): ?>
                    <?php
                    $when = trim((string) ($row['applied_at_label'] ?? ''));
                    $summary = trim((string) ($row['summary'] ?? ''));
                    $actor = trim((string) ($row['actor'] ?? ''));
                    ?>
                    <li>
                        <strong><?= $h((string) ($row['title'] ?? 'Modèle')) ?></strong>
                        <?php if ($when !== ''): ?>
                            · <?= $h($when) ?>
                        <?php endif; ?>
                        <?php if ($actor !== ''): ?>
                            · <?= $h($actor) ?>
                        <?php endif; ?>
                        <?php if ($summary !== ''): ?>
                            <div><?= $h($summary) ?></div>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <?php if ($archived !== []): ?>
        <section class="bo-catalog__section" aria-labelledby="bo-catalog-arch-title">
            <h2 id="bo-catalog-arch-title" class="bo-catalog__section-title">Modèles retirés</h2>
            <p class="bo-catalog__section-lead">Ils ne s’appliquent plus, mais le journal reste. Restaurez-les pour les réutiliser.</p>
            <div class="bo-catalog__grid">
                <?php foreach ($archived as $item): ?>
                    <?php $code = (string) ($item['code'] ?? ''); ?>
                    <article class="bo-catalog__card bo-catalog__card--archived">
                        <p class="bo-catalog__card-origin bo-catalog__card-origin--local">Retiré</p>
                        <h3 class="bo-catalog__card-title"><?= $h((string) ($item['title'] ?? '')) ?></h3>
                        <p class="bo-catalog__card-volume"><?= $h((string) ($item['volume'] ?? '')) ?></p>
                        <div class="bo-catalog__card-actions">
                            <a class="ath-btn" href="<?= $h(url('back-office/organisation/catalogue/modele?modele=' . rawurlencode($code))) ?>">Voir le contenu</a>
                            <form method="post" action="<?= $h(url('back-office/organisation/catalogue/restaurer')) ?>">
                                <?= \App\Core\Csrf::field() ?>
                                <input type="hidden" name="modele" value="<?= $h($code) ?>">
                                <button type="submit" class="ath-btn ath-btn--solid">Restaurer</button>
                            </form>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>
</div>
