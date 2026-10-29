<?php
/**
 * Onglet « Sur mesure » du référentiel des grades : systèmes de grades créés par la communauté
 * (SCP, Star Wars, Warhammer 40k, univers maison…), leurs catégories et leurs grades.
 * Actions : CustomGradeSystemController. Formulaires natifs, enrichis sans rechargement si JavaScript est disponible.
 *
 * @var list<array<string, mixed>> $customSystems
 * @var array<string, mixed>|null $customSelected
 * @var array<string, mixed>|null $customCurrent
 * @var list<array<string, mixed>> $customTree
 * @var array<string, array<string, mixed>> $customTemplates
 */
$customSystems = is_array($customSystems ?? null) ? $customSystems : [];
$customTree = is_array($customTree ?? null) ? $customTree : [];
$customTemplates = is_array($customTemplates ?? null) ? $customTemplates : [];
$customSelected = is_array($customSelected ?? null) ? $customSelected : null;
$customCurrent = is_array($customCurrent ?? null) ? $customCurrent : null;
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$base = url('back-office/referentiels/grades/sur-mesure');
$tabUrl = static fn (int $id): string => url('back-office/referentiels/grades') . '?tab=custom&systeme=' . $id;
$isCurrent = $customSelected !== null && $customCurrent !== null && (int) $customCurrent['id'] === (int) $customSelected['id'];
$gradeTotal = 0;
foreach ($customTree as $c) {
    $gradeTotal += count($c['grades'] ?? []);
}
$catCount = count($customTree);
?>
<section class="grd-ref__panel grd-cus">
    <div class="grd-ref__panel-head">
        <h2>Grades sur mesure</h2>
        <p>Votre propre échelle de grades, entièrement libre : univers SCP, Star Wars, Warhammer 40k ou création maison. Vous nommez les catégories et les grades et vous les classez du plus haut au plus bas. Une fois le système « utilisé pour la communauté », les fiches membres, l’ORBAT, l’ATAK et l’avancement proposent ces grades.</p>
    </div>

    <?php if (empty($customReady)): ?>
        <p class="grd-cus__notice">Les grades sur mesure ne sont pas encore installés sur ce serveur : l’administrateur doit lancer les migrations (étape <code>custom_grade_systems</code>).</p>
    <?php else: ?>

    <?php if ($customSystems !== []): ?>
    <nav class="grd-cus__systems" aria-label="Systèmes sur mesure">
        <?php foreach ($customSystems as $cs): ?>
            <?php $on = $customSelected !== null && (int) $cs['id'] === (int) $customSelected['id']; ?>
            <a href="<?= $h($tabUrl((int) $cs['id'])) ?>" class="<?= $on ? 'is-active' : '' ?>" <?= $on ? 'aria-current="true"' : '' ?>>
                <span translate="no"><?= $h($cs['label']) ?></span>
                <small><?= (int) $cs['grade_count'] ?> grade<?= (int) $cs['grade_count'] > 1 ? 's' : '' ?></small>
                <?php if ($customCurrent !== null && (int) $customCurrent['id'] === (int) $cs['id']): ?><span class="grd-ref__badge grd-ref__badge--accent">Utilisé</span><?php endif; ?>
            </a>
        <?php endforeach; ?>
        <a href="#grd-cus-new" class="grd-cus__new-link">+ Nouveau système</a>
    </nav>
    <?php endif; ?>

    <?php if ($customSelected !== null): ?>
    <?php $sid = (int) $customSelected['id']; ?>
    <div class="grd-ref__system<?= $isCurrent ? '' : ' is-other' ?>">
        <p>
            <?php if ($isCurrent): ?>
                <strong>Votre communauté utilise « <span translate="no"><?= $h($customSelected['label']) ?></span> ».</strong>
                Ce sont ces grades que proposent les fiches membres, l’ORBAT et l’avancement.
            <?php else: ?>
                <strong>« <span translate="no"><?= $h($customSelected['label']) ?></span> » n’est pas encore utilisé.</strong>
                Préparez les catégories et les grades, puis utilisez-le pour la communauté : les grades déjà attribués sont conservés.
            <?php endif; ?>
        </p>
        <div class="grd-ref__system-actions">
            <?php if (!$isCurrent): ?>
            <form method="post" action="<?= $h($base . '/systeme/' . $sid . '/utiliser') ?>" onsubmit="return confirm('Utiliser ce système pour la communauté ? Les grades déjà attribués aux membres sont conservés ; les listes proposeront ensuite ces grades.');">
                <?= \App\Core\Csrf::field() ?>
                <button type="submit" class="grd-ref__switch">Utiliser pour la communauté</button>
            </form>
            <?php endif; ?>
            <details class="grd-cus__inline">
                <summary class="grd-ref__switch grd-ref__switch--ghost">Renommer</summary>
                <form method="post" action="<?= $h($base . '/systeme/' . $sid . '/update') ?>" class="grd-cus__form">
                    <?= \App\Core\Csrf::field() ?>
                    <label class="grd-cus__field"><span>Nom du système</span><input type="text" name="label" value="<?= $h($customSelected['label']) ?>" maxlength="100" required></label>
                    <button type="submit" class="grd-ref__btn">Enregistrer</button>
                </form>
            </details>
            <?php if (!$isCurrent): ?>
            <form method="post" action="<?= $h($base . '/systeme/' . $sid . '/delete') ?>" onsubmit="return confirm('Supprimer ce système, ses catégories et ses grades ? Impossible si des membres portent encore l’un de ces grades.');">
                <?= \App\Core\Csrf::field() ?>
                <button type="submit" class="grd-ref__switch grd-ref__switch--ghost grd-cus__danger">Supprimer</button>
            </form>
            <?php endif; ?>
        </div>
    </div>

    <p class="grd-cus__summary">Catégories : <?= $catCount ?> · grades : <?= $gradeTotal ?>. Classés du plus haut au plus bas : les flèches changent l’ordre.</p>

    <?php foreach ($customTree as $ci => $cat): ?>
        <?php $cid = (int) $cat['id']; $grades = $cat['grades'] ?? []; $retired = $cat['retired'] ?? []; ?>
        <div class="grd-cus__cat">
            <div class="grd-cus__cat-head">
                <h3 class="grd-ref__group" translate="no"><?= $h($cat['label']) ?> <small><?= count($grades) ?></small></h3>
                <div class="grd-cus__tools">
                    <?php if ($ci > 0): ?>
                    <form method="post" action="<?= $h($base . '/categorie/' . $cid . '/move') ?>"><?= \App\Core\Csrf::field() ?><input type="hidden" name="dir" value="up"><button type="submit" class="grd-cus__arrow" aria-label="Monter la catégorie" title="Monter la catégorie">↑</button></form>
                    <?php else: ?><span class="grd-cus__arrow-gap" aria-hidden="true"></span>
                    <?php endif; ?>
                    <?php if ($ci < $catCount - 1): ?>
                    <form method="post" action="<?= $h($base . '/categorie/' . $cid . '/move') ?>"><?= \App\Core\Csrf::field() ?><input type="hidden" name="dir" value="down"><button type="submit" class="grd-cus__arrow" aria-label="Descendre la catégorie" title="Descendre la catégorie">↓</button></form>
                    <?php else: ?><span class="grd-cus__arrow-gap" aria-hidden="true"></span>
                    <?php endif; ?>
                    <details class="grd-cus__inline">
                        <summary>Renommer</summary>
                        <form method="post" action="<?= $h($base . '/categorie/' . $cid . '/update') ?>" class="grd-cus__form">
                            <?= \App\Core\Csrf::field() ?>
                            <label class="grd-cus__field"><span>Nom de la catégorie</span><input type="text" name="label" value="<?= $h($cat['label']) ?>" maxlength="100" required></label>
                            <button type="submit" class="grd-ref__btn">Enregistrer</button>
                        </form>
                    </details>
                    <form method="post" action="<?= $h($base . '/categorie/' . $cid . '/delete') ?>" onsubmit="return confirm('Supprimer cette catégorie et ses grades ? Impossible si des membres portent l’un de ces grades.');">
                        <?= \App\Core\Csrf::field() ?>
                        <button type="submit" class="grd-cus__danger">Supprimer</button>
                    </form>
                </div>
            </div>

            <?php if ($grades === []): ?>
                <p class="grd-ref__empty">Aucun grade dans cette catégorie.</p>
            <?php else: ?>
            <ul class="grd-ref__list">
                <?php foreach ($grades as $gi => $g): ?>
                    <?php $gid = (int) $g['id']; $n = (int) ($g['holders'] ?? 0); ?>
                    <li class="grd-ref__row grd-cus__row" data-grade-row>
                        <span class="grd-ref__short" translate="no"><?= $h(trim((string) ($g['label_short'] ?? '')) ?: trim((string) ($g['code'] ?? ''))) ?></span>
                        <span class="grd-ref__name"><strong translate="no"><?= $h($g['label_long'] ?? '') ?></strong><code class="grd-ref__code">Code : <?= $h(trim((string) ($g['code'] ?? '')) ?: 'Non renseigné') ?></code></span>
                        <span class="grd-ref__otan"><?= $n > 0 ? $h($n . ' membre' . ($n > 1 ? 's' : '')) : '—' ?></span>
                        <span class="grd-ref__actions">
                            <?php if ($gi > 0): ?>
                            <form method="post" action="<?= $h($base . '/grade/' . $gid . '/move') ?>"><?= \App\Core\Csrf::field() ?><input type="hidden" name="dir" value="up"><button type="submit" class="grd-cus__arrow" aria-label="Monter le grade" title="Monter le grade">↑</button></form>
                            <?php else: ?><span class="grd-cus__arrow-gap" aria-hidden="true"></span>
                            <?php endif; ?>
                            <?php if ($gi < count($grades) - 1): ?>
                            <form method="post" action="<?= $h($base . '/grade/' . $gid . '/move') ?>"><?= \App\Core\Csrf::field() ?><input type="hidden" name="dir" value="down"><button type="submit" class="grd-cus__arrow" aria-label="Descendre le grade" title="Descendre le grade">↓</button></form>
                            <?php else: ?><span class="grd-cus__arrow-gap" aria-hidden="true"></span>
                            <?php endif; ?>
                            <details class="grd-cus__inline">
                                <summary>Modifier</summary>
                                <form method="post" action="<?= $h($base . '/grade/' . $gid . '/update') ?>" class="grd-cus__form">
                                    <?= \App\Core\Csrf::field() ?>
                                    <label class="grd-cus__field"><span>Nom complet</span><input type="text" name="label_long" value="<?= $h($g['label_long'] ?? '') ?>" maxlength="150" required></label>
                                    <label class="grd-cus__field grd-cus__field--short"><span>Abrégé</span><input type="text" name="label_short" value="<?= $h(trim((string) ($g['label_short'] ?? '')) ?: trim((string) ($g['code'] ?? ''))) ?>" maxlength="100"></label>
                                    <?php if ($catCount > 1): ?>
                                    <label class="grd-cus__field"><span>Catégorie</span>
                                        <select name="grade_category_id">
                                            <?php foreach ($customTree as $opt): ?>
                                                <option value="<?= (int) $opt['id'] ?>" <?= (int) $opt['id'] === $cid ? 'selected' : '' ?>><?= $h($opt['label']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </label>
                                    <?php endif; ?>
                                    <button type="submit" class="grd-ref__btn">Enregistrer</button>
                                </form>
                            </details>
                            <form method="post" action="<?= $h($base . '/grade/' . $gid . '/delete') ?>" onsubmit="return confirm('<?= $n > 0 ? 'Des membres portent ce grade : il sera retiré des listes mais ils le garderont. Continuer ?' : 'Supprimer ce grade ?' ?>');">
                                <?= \App\Core\Csrf::field() ?>
                                <button type="submit"><?= $n > 0 ? 'Retirer' : 'Supprimer' ?></button>
                            </form>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>

            <?php if ($retired !== []): ?>
            <details class="grd-ref__hidden">
                <summary>Grades retirés des listes : <?= count($retired) ?></summary>
                <ul class="grd-ref__list">
                    <?php foreach ($retired as $g): ?>
                        <?php $n = (int) ($g['holders'] ?? 0); ?>
                        <li class="grd-ref__row grd-ref__row--hidden" data-grade-row>
                            <span class="grd-ref__short" translate="no"><?= $h(trim((string) ($g['label_short'] ?? '')) ?: trim((string) ($g['code'] ?? ''))) ?></span>
                            <span class="grd-ref__name"><strong translate="no"><?= $h($g['label_long'] ?? '') ?></strong><code class="grd-ref__code">Code : <?= $h(trim((string) ($g['code'] ?? '')) ?: 'Non renseigné') ?></code></span>
                            <span class="grd-ref__otan"><?= $n > 0 ? $h($n . ' membre' . ($n > 1 ? 's' : '')) : '—' ?></span>
                            <span class="grd-ref__actions">
                                <form method="post" action="<?= $h($base . '/grade/' . (int) $g['id'] . '/restore') ?>">
                                    <?= \App\Core\Csrf::field() ?>
                                    <button type="submit" class="grd-ref__restore">Remettre</button>
                                </form>
                                <form method="post" action="<?= $h($base . '/grade/' . (int) $g['id'] . '/delete') ?>" onsubmit="return confirm('Supprimer ce grade ? Impossible tant que des membres le portent.');">
                                    <?= \App\Core\Csrf::field() ?>
                                    <button type="submit">Supprimer</button>
                                </form>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </details>
            <?php endif; ?>

            <details class="grd-cus__add-wrap"<?= $grades === [] ? ' open' : '' ?>>
            <summary>+ Ajouter un grade</summary>
            <form method="post" action="<?= $h($base . '/grade') ?>" class="grd-cus__add">
                <?= \App\Core\Csrf::field() ?>
                <input type="hidden" name="grade_category_id" value="<?= $cid ?>">
                <label class="grd-cus__field grd-cus__field--short"><span>Abrégé</span><input type="text" name="label_short" maxlength="100" placeholder="SGT"></label>
                <label class="grd-cus__field"><span>Nouveau grade</span><input type="text" name="label_long" maxlength="150" placeholder="Nom complet du grade" required></label>
                <button type="submit" class="grd-ref__btn">Ajouter le grade</button>
            </form>
            </details>
        </div>
    <?php endforeach; ?>

    <form method="post" action="<?= $h($base . '/categorie') ?>" class="grd-cus__add grd-cus__add--cat">
        <?= \App\Core\Csrf::field() ?>
        <input type="hidden" name="grade_system_id" value="<?= $sid ?>">
        <label class="grd-cus__field"><span>Nouvelle catégorie (ajoutée en bas de l’échelle)</span><input type="text" name="label" maxlength="100" placeholder="Ex. Research Department, Clone Troopers" required></label>
        <button type="submit" class="grd-ref__btn">Ajouter la catégorie</button>
    </form>
    <?php endif; ?>

    <div class="grd-cus__create" id="grd-cus-new">
        <h3><?= $customSystems === [] ? 'Créer votre système de grades' : 'Nouveau système' ?></h3>
        <p>Partez d’un modèle ou d’une page vierge : tout reste modifiable ensuite (noms, catégories, ordre).</p>
        <form method="post" action="<?= $h($base . '/systeme') ?>">
            <?= \App\Core\Csrf::field() ?>
            <fieldset class="grd-cus__templates">
                <legend>Modèle de départ</legend>
                <?php $first = true; foreach ($customTemplates as $key => $tpl): ?>
                    <?php
                    $tplGrades = 0;
                    $tplCats = [];
                    foreach ($tpl['categories'] as $tc) {
                        $tplGrades += count($tc['grades']);
                        $tplCats[] = $tc['label'];
                    }
                    ?>
                    <label class="grd-cus__tpl">
                        <input type="radio" name="template" value="<?= $h($key) ?>" <?= $first ? 'checked' : '' ?>>
                        <span class="grd-cus__tpl-body">
                            <strong><?= $h($tpl['label']) ?></strong>
                            <small><?= $key === 'vide' ? 'Une catégorie vide, à remplir' : $h(count($tplCats) . ' catégories · ' . $tplGrades . ' grades') ?></small>
                            <?php if ($key !== 'vide'): ?><em translate="no"><?= $h(implode(' · ', $tplCats)) ?></em><?php endif; ?>
                        </span>
                    </label>
                <?php $first = false; endforeach; ?>
            </fieldset>
            <div class="grd-cus__create-row">
                <label class="grd-cus__field"><span>Nom du système (facultatif, sinon celui du modèle)</span><input type="text" name="label" maxlength="100" placeholder="Ex. 501st Legion, Site-19, 88th Cadian"></label>
                <label class="grd-cus__check"><input type="checkbox" name="use_now" value="1"> L’utiliser tout de suite pour la communauté</label>
                <button type="submit" class="grd-ref__btn">Créer le système</button>
            </div>
        </form>
    </div>
    <?php endif; ?>
</section>
