<?php
/**
 * Référentiel des grades de la communauté.
 *
 * Une liste par système (France, États-Unis), groupée par catégorie et classée du grade le plus élevé au plus bas.
 * Les grades du catalogue partagé se masquent / se réaffichent (rien n'est supprimé) ; les grades créés par la
 * communauté appartiennent au système d'avancement et ont leur propre onglet (?tab=perso).
 * Les systèmes entièrement sur mesure (SCP, Star Wars…) ont l’onglet ?tab=custom (partial _custom.php).
 */

use App\Support\GradeSelectOptions;

$tab = in_array($tab ?? 'fr', ['us', 'perso', 'custom'], true) ? $tab : 'fr';
$customSystems = is_array($customSystems ?? null) ? $customSystems : [];
$customCurrent = is_array($customCurrent ?? null) ? $customCurrent : null;
$gradesFr = is_array($gradesFr ?? null) ? $gradesFr : [];
$gradesUs = is_array($gradesUs ?? null) ? $gradesUs : [];
$tenantExtras = is_array($tenantExtras ?? null) ? $tenantExtras : [];
$tenantSystemCode = strtoupper(trim((string) ($tenantSystemCode ?? 'FR_CLASSIC')));
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

$extrasActive = array_values(array_filter($tenantExtras, static fn (array $g): bool => empty($g['archived_at'])));
$extrasArchived = array_values(array_filter($tenantExtras, static fn (array $g): bool => !empty($g['archived_at'])));
$rows = $tab === 'us' ? $gradesUs : $gradesFr;
$visible = array_values(array_filter($rows, static fn (array $g): bool => !empty($g['is_enabled'])));
$hidden = GradeSelectOptions::sorted(array_values(array_filter($rows, static fn (array $g): bool => empty($g['is_enabled']))));
$groups = GradeSelectOptions::groups($visible, $tab === 'us' ? 'US' : 'FR');
$systemName = $tab === 'us' ? 'américains' : 'français';
$communitySystem = $customCurrent !== null ? 'custom' : (str_starts_with($tenantSystemCode, 'US') ? 'us' : 'fr');
$renamed = static function (array $g): bool {
    return trim((string) ($g['label_long'] ?? '')) !== trim((string) ($g['catalog_label_long'] ?? $g['label_long'] ?? ''))
        || trim((string) ($g['label_short'] ?? '')) !== trim((string) ($g['catalog_label_short'] ?? $g['label_short'] ?? ''));
};
$tabUrl = static fn (string $t): string => url('back-office/referentiels/grades') . '?tab=' . rawurlencode($t);
?>
<div class="bo-legacy bo-legacy--5xl grd-ref" data-grade-workspace>
    <div class="grd-ref__status" data-grade-status role="status" aria-live="polite"></div>
    <?php foreach (($gradeNotices ?? []) as $notice): ?><p class="grd-ref__notice<?= ($notice['level'] ?? '') === 'error' ? ' is-error' : '' ?>" role="<?= ($notice['level'] ?? '') === 'error' ? 'alert' : 'status' ?>"><?= $h($notice['message'] ?? '') ?></p><?php endforeach; ?>
    <header class="grd-ref__head">
        <p class="grd-ref__lead">Les grades proposés dans les fiches membres viennent d’un catalogue commun à toutes les communautés. Ici, vous choisissez lesquels afficher et vous pouvez renommer un grade : les autres communautés ne sont pas touchées. Pour un univers à part (SCP, Star Wars, Warhammer 40k…), créez vos propres grades dans l’onglet « Sur mesure ».</p>
        <nav class="grd-ref__links" aria-label="Pages liées">
            <a href="<?= $h(url('back-office/organisation/grades')) ?>">Règles d’avancement</a>
            <a href="<?= $h(url('back-office/referentiels/grades/catalogue')) ?>">Catalogue OTAN et audit</a>
        </nav>
    </header>

    <nav class="grd-ref__tabs" data-grade-tabs aria-label="Systèmes de grades">
        <?php foreach (['fr' => ['Grades français', count($gradesFr)], 'us' => ['Grades américains', count($gradesUs)], 'custom' => ['Sur mesure', count($customSystems)], 'perso' => ['Grades d’avancement', count($extrasActive)]] as $key => [$label, $count]): ?>
            <a href="<?= $h($tabUrl($key)) ?>" class="<?= $tab === $key ? 'is-active' : '' ?>" <?= $tab === $key ? 'aria-current="page"' : '' ?>>
                <?= $h($label) ?>
                <?php if ($key === 'perso' || ($key === 'custom' && $count > 0)): ?><span class="grd-ref__count"><?= (int) $count ?></span><?php endif; ?>
                <?php if ($communitySystem === $key): ?><span class="grd-ref__badge grd-ref__badge--accent">Système de la communauté</span><?php endif; ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <div class="grd-ref__toolbar">
        <label for="grade-filter"><span>Rechercher un grade</span><input id="grade-filter" type="search" data-grade-filter placeholder="Code, abrégé, nom complet…" autocomplete="off"></label>
        <span data-grade-filter-count aria-live="polite"></span>
        <p>Le code stable reste affiché, même si le grade a été renommé. Les modifications se font ici sans recharger la page.</p>
    </div>

    <?php if ($tab === 'custom'): ?>
    <?php require __DIR__ . '/_custom.php'; ?>
    <?php elseif ($tab === 'perso'): ?>
    <section class="grd-ref__panel grd-ref__panel--extras">
        <div class="grd-ref__panel-head grd-ref__panel-head--row">
            <div>
                <h2>Grades d’avancement créés par la communauté</h2>
                <p>Ces grades n’existent pas dans le catalogue. Ils servent aux règles d’avancement et aux campagnes (paliers, conditions, historique). Ils ne remplacent pas les grades affichés sur les fiches : pour renommer un grade, utilisez « Renommer » dans les onglets français ou américain ; pour une échelle entièrement à vous (SCP, Star Wars…), utilisez l’onglet « Sur mesure ».</p>
            </div>
            <a class="grd-ref__btn" data-grade-edit href="<?= $h(url('back-office/referentiels/grades/create') . '?tab=perso') ?>">Créer un grade d’avancement</a>
        </div>
        <?php if ($extrasActive === []): ?>
            <p class="grd-ref__empty">Aucun grade personnalisé pour l’instant. « Créer un grade d’avancement » en ajoute un.</p>
        <?php else: ?>
            <h3 class="grd-ref__group">Grades actifs <small><?= count($extrasActive) ?></small></h3>
            <ul class="grd-ref__list">
                <?php foreach ($extrasActive as $extra): ?>
                    <?php $eid = (int) ($extra['id'] ?? 0); ?>
                    <li class="grd-ref__row" data-grade-row>
                        <span class="grd-ref__short"><?= $h(($extra['short_label'] ?? '') !== '' ? $extra['short_label'] : ($extra['code'] ?? '')) ?></span>
                        <span class="grd-ref__name">
                            <strong translate="no"><?= $h($extra['label'] ?? '') ?></strong><code class="grd-ref__code">Code : <?= $h(trim((string) ($extra['code'] ?? '')) ?: 'Non renseigné') ?></code>
                            <span class="grd-ref__badge grd-ref__badge--community">Créé par la communauté</span>
                        </span>
                        <span class="grd-ref__otan"><?= $h(($extra['filiere_label'] ?? '') !== '' ? $extra['filiere_label'] : '—') ?></span>
                        <span class="grd-ref__actions">
                            <a href="<?= $h(url('back-office/organisation/grades/' . $eid . '/edit')) ?>">Modifier</a>
                            <form action="<?= $h(url('back-office/organisation/grades/' . $eid . '/archive')) ?>" method="post" onsubmit="return confirm('Archiver ce grade ? Il ne sera plus proposé dans l’avancement (l’historique des membres qui l’ont obtenu est conservé).');">
                                <?= \App\Core\Csrf::field() ?>
                                <input type="hidden" name="return_to" value="referentiel">
                                <button type="submit">Archiver</button>
                            </form>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <?php if ($extrasArchived !== []): ?>
            <details class="grd-ref__hidden">
                <summary>Grades archivés : <?= count($extrasArchived) ?></summary>
                <ul class="grd-ref__list">
                    <?php foreach ($extrasArchived as $extra): ?>
                        <li class="grd-ref__row grd-ref__row--hidden" data-grade-row>
                            <span class="grd-ref__short"><?= $h(($extra['short_label'] ?? '') !== '' ? $extra['short_label'] : ($extra['code'] ?? '')) ?></span>
                            <span class="grd-ref__name"><strong translate="no"><?= $h($extra['label'] ?? '') ?></strong><code class="grd-ref__code">Code : <?= $h(trim((string) ($extra['code'] ?? '')) ?: 'Non renseigné') ?></code> <span class="grd-ref__badge">Archivé</span></span>
                            <span class="grd-ref__otan"><?= $h(($extra['filiere_label'] ?? '') !== '' ? $extra['filiere_label'] : '—') ?></span>
                            <span class="grd-ref__actions">
                                <a href="<?= $h(url('back-office/organisation/grades/' . (int) ($extra['id'] ?? 0) . '/edit')) ?>">Modifier</a>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </details>
        <?php endif; ?>
        <p class="grd-ref__foot">Paliers, conditions et ordre de ces grades : <a href="<?= $h(url('back-office/organisation/grades')) ?>">Règles d’avancement</a>.</p>
    </section>
    <?php else: ?>

    <?php
    $systemLabels = ['fr' => 'grades français', 'us' => 'grades américains', 'custom' => 'grades sur mesure « ' . (string) ($customCurrent['label'] ?? '') . ' »'];
    $otherSystem = $communitySystem === 'fr' ? 'us' : 'fr';
    $targetSystem = $tab !== $communitySystem ? $tab : $otherSystem;
    ?>
    <?php if (!empty($gradesHidden)): ?>
    <div class="grd-ref__system is-none">
        <p>
            <strong>Votre communauté n’affiche aucun grade.</strong>
            Les grades déjà attribués restent enregistrés mais n’apparaissent ni dans l’en-tête, ni sur les fiches, ni sur le tableau de bord.
        </p>
        <form method="post" action="<?= $h(url('back-office/referentiels/grades/systeme')) ?>">
            <?= \App\Core\Csrf::field() ?>
            <input type="hidden" name="grade_system_code" value="<?= $tab === 'us' ? 'US_CLASSIC' : 'FR_CLASSIC' ?>">
            <button type="submit" class="grd-ref__switch">Afficher les <?= $h($systemLabels[$tab === 'us' ? 'us' : 'fr']) ?></button>
        </form>
    </div>
    <?php else: ?>
    <div class="grd-ref__system<?= $tab !== $communitySystem ? ' is-other' : '' ?>">
        <p>
            <?php if ($tab === $communitySystem): ?>
                <strong>Votre communauté utilise les <?= $h($systemLabels[$communitySystem]) ?>.</strong>
                Ce sont eux que proposent les fiches membres, l’ORBAT et l’avancement.
            <?php else: ?>
                <strong>Vous consultez les <?= $h($systemLabels[$tab]) ?>.</strong>
                Votre communauté utilise actuellement les <?= $h($systemLabels[$communitySystem]) ?>.
            <?php endif; ?>
        </p>
        <div class="grd-ref__system-actions">
            <form method="post" action="<?= $h(url('back-office/referentiels/grades/systeme')) ?>" onsubmit="return confirm('Passer la communauté aux <?= $h($systemLabels[$targetSystem]) ?> ? Les grades déjà attribués aux membres sont conservés ; les listes proposeront ensuite les <?= $h($systemLabels[$targetSystem]) ?>.');">
                <?= \App\Core\Csrf::field() ?>
                <input type="hidden" name="grade_system_code" value="<?= $targetSystem === 'us' ? 'US_CLASSIC' : 'FR_CLASSIC' ?>">
                <button type="submit" class="grd-ref__switch">Utiliser les <?= $h($systemLabels[$targetSystem]) ?> pour la communauté</button>
            </form>
            <form method="post" action="<?= $h(url('back-office/referentiels/grades/systeme')) ?>" onsubmit="return confirm('Ne plus afficher aucun grade dans la communauté ? Rien n’est supprimé : vous pourrez les réafficher ici.');">
                <?= \App\Core\Csrf::field() ?>
                <input type="hidden" name="grade_system_code" value="NONE">
                <button type="submit" class="grd-ref__switch grd-ref__switch--ghost">N’afficher aucun grade</button>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <section class="grd-ref__panel">
        <div class="grd-ref__panel-head">
            <h2><?= count($visible) ?> grades <?= $h($systemName) ?> affichés</h2>
            <p>Classés par catégorie, du plus élevé au plus bas. « Masquer » retire un grade des listes sans rien supprimer.</p>
        </div>
        <?php if ($groups === []): ?>
            <p class="grd-ref__empty">Aucun grade <?= $h($systemName) ?> affiché.</p>
        <?php endif; ?>
        <?php foreach ($groups as $group): ?>
            <h3 class="grd-ref__group"><?= $h($group['label']) ?> <small><?= count($group['grades']) ?></small></h3>
            <ul class="grd-ref__list">
                <?php foreach ($group['grades'] as $g): ?>
                    <?php $gid = (int) ($g['id'] ?? 0); ?>
                    <li class="grd-ref__row" data-grade-row>
                        <span class="grd-ref__short"><?= $h(trim((string) ($g['label_short'] ?? '')) ?: trim((string) ($g['code'] ?? ''))) ?></span>
                        <span class="grd-ref__name">
                            <strong><?= $h($g['label_long'] ?? '') ?></strong><code class="grd-ref__code">Code : <?= $h(trim((string) ($g['code'] ?? '')) ?: 'Non renseigné') ?></code>
                            <?php if ($renamed($g)): ?>
                                <span class="grd-ref__badge" title="Nom du catalogue : <?= $h($g['catalog_label_long'] ?? '') ?>">Renommé</span>
                            <?php endif; ?>
                        </span>
                        <span class="grd-ref__otan"><?= $h(($g['label_otan'] ?? '') !== '' ? $g['label_otan'] : '—') ?></span>
                        <span class="grd-ref__actions">
                            <a data-grade-edit href="<?= $h(url('back-office/referentiels/grades/' . $gid . '/edit')) ?>">Renommer</a>
                            <form action="<?= $h(url('back-office/referentiels/grades/' . $gid . '/deactivate')) ?>" method="post" onsubmit="return confirm('Masquer ce grade ? Il ne sera plus proposé dans les fiches membres (les membres qui l’ont déjà le gardent).');">
                                <?= \App\Core\Csrf::field() ?>
                                <button type="submit">Masquer</button>
                            </form>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endforeach; ?>

        <?php if ($hidden !== []): ?>
            <details class="grd-ref__hidden">
                <summary>Grades masqués : <?= count($hidden) ?></summary>
                <ul class="grd-ref__list">
                    <?php foreach ($hidden as $g): ?>
                        <?php $gid = (int) ($g['id'] ?? 0); ?>
                        <li class="grd-ref__row grd-ref__row--hidden" data-grade-row>
                            <span class="grd-ref__short"><?= $h(trim((string) ($g['label_short'] ?? '')) ?: trim((string) ($g['code'] ?? ''))) ?></span>
                            <span class="grd-ref__name"><strong><?= $h($g['label_long'] ?? '') ?></strong><code class="grd-ref__code">Code : <?= $h(trim((string) ($g['code'] ?? '')) ?: 'Non renseigné') ?></code> <span class="grd-ref__muted"><?= $h($g['category_label'] ?? '') ?></span></span>
                            <span class="grd-ref__otan"><?= $h(($g['label_otan'] ?? '') !== '' ? $g['label_otan'] : '—') ?></span>
                            <span class="grd-ref__actions">
                                <form action="<?= $h(url('back-office/referentiels/grades/' . $gid . '/reactivate')) ?>" method="post">
                                    <?= \App\Core\Csrf::field() ?>
                                    <button type="submit" class="grd-ref__restore">Réafficher</button>
                                </form>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </details>
        <?php endif; ?>
    </section>

    <?php endif; ?>
    <dialog class="grd-ref__dialog" data-grade-dialog aria-label="Modifier un grade"><button type="button" class="grd-ref__dialog-close" data-grade-close>Fermer ×</button><div data-grade-form-slot></div></dialog>
</div>
<script src="<?= $h(asset_url('assets/js/back-office-referentiel-grades.js')) ?>" defer></script>
