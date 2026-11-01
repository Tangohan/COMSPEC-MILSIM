<?php
declare(strict_types=1);

/**
 * Référentiel des compétences (back-office) : à quoi sert chaque compétence, comment elle est acquise et validée,
 * et où elle est utilisée (Academy, modules de compétence, qualifications, emplois et postes ORBAT, dotation).
 *
 * @var array<string, list<array<string, mixed>>> $competencyByPalier
 * @var list<array<string, mixed>> $competencyGrades
 * @var list<string> $competencyAcquisitionLevels
 * @var array<string, list<array<string, mixed>>> $competencyCatalog
 * @var array<string, mixed> $competencyUsage
 * @var bool $competencyLinksReady
 */

use App\Services\Training\CompetencyPathwayGenerator as Gen;

$competencyByPalier = $competencyByPalier ?? [];
$competencyGrades = $competencyGrades ?? [];
$competencyAcquisitionLevels = $competencyAcquisitionLevels ?? [];
$competencyCatalog = $competencyCatalog ?? [];
$competencyUsage = $competencyUsage ?? ['billets_by_job_role' => [], 'members_by_job_role' => [], 'pathways_by_competency' => []];
$linksReady = !empty($competencyLinksReady);
$flashSuccess = \App\Core\Session::getFlash('success');
$flashError = \App\Core\Session::getFlash('error');
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$csrf = \App\Core\Csrf::token();

$catalogLists = [
    'academy_course' => $competencyCatalog['academy_courses'] ?? [],
    'training_course' => $competencyCatalog['training_courses'] ?? [],
    'module' => $competencyCatalog['modules'] ?? [],
    'qualification' => $competencyCatalog['qualifications'] ?? [],
    'job_role' => $competencyCatalog['job_roles'] ?? [],
    'equipment' => $competencyCatalog['equipment'] ?? [],
];
$linkHelp = [
    'academy_course' => 'Cours suivi dans l’Academy pour acquérir la compétence (ex. licence JTAC).',
    'training_course' => 'Formation du catalogue qui prépare à la compétence.',
    'module' => 'Module ALPHA → DELTA du parcours compétences des membres.',
    'qualification' => 'Qualification ou brevet qui atteste la compétence.',
    'job_role' => 'Emploi qui exige la compétence ; les postes ORBAT de cet emploi en héritent.',
    'equipment' => 'Matériel de la dotation dont l’usage demande la compétence.',
];

$total = 0;
$linkedCount = 0;
$explainedCount = 0;
foreach ($competencyByPalier as $rows) {
    foreach ($rows as $c) {
        $total++;
        $linkedCount += ($c['resolved_links'] ?? []) !== [] ? 1 : 0;
        $explainedCount += trim((string) ($c['description'] ?? '')) !== '' ? 1 : 0;
    }
}

$levelClass = static fn (string $lvl): string => match ($lvl) {
    'Basique' => 'cp-ref-level--1',
    'Intermédiaire' => 'cp-ref-level--2',
    'Avancé' => 'cp-ref-level--3',
    'Spécifique' => 'cp-ref-level--4',
    default => '',
};

/** Formulaire d’ajout ou de modification d’une compétence. */
$renderForm = static function (?array $c) use ($h, $csrf, $competencyGrades, $competencyAcquisitionLevels, $catalogLists, $linkHelp, $linksReady): void {
    $id = $c !== null ? (int) $c['id'] : 0;
    $prefix = $id > 0 ? 'c' . $id . '-' : 'new-';
    $linked = [];
    foreach ($c['links'] ?? [] as $l) {
        $linked[$l['type'] . ':' . $l['ref']] = true;
    }
    $gradeId = (int) ($c['grade_id'] ?? 0);
    $level = (string) ($c['acquisition_level'] ?? '');
    $mode = (string) ($c['validation_mode'] ?? '');
    ?>
    <form method="post" action="<?= $h(url('back-office/referentiels/competences' . ($id > 0 ? '/' . $id : ''))) ?>" class="cp-form<?= $id > 0 ? ' is-editing' : '' ?>">
        <input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>">
        <div class="cp-field cp-field--wide">
            <label for="<?= $prefix ?>label">Nom de la compétence</label>
            <input class="cp-input" type="text" name="label" id="<?= $prefix ?>label" required maxlength="255" value="<?= $h($c['label'] ?? '') ?>" placeholder="Ex. Guidage d’un appui aérien rapproché">
        </div>
        <div class="cp-field">
            <label for="<?= $prefix ?>palier">Palier de formation</label>
            <input class="cp-input" type="text" name="palier" id="<?= $prefix ?>palier" required maxlength="120" value="<?= $h($c['palier'] ?? '') ?>" placeholder="Ex. Formation initiale" list="cp-ref-paliers">
        </div>
        <div class="cp-field cp-field--full">
            <label for="<?= $prefix ?>description">À quoi sert cette compétence ?</label>
            <textarea class="cp-input" name="description" id="<?= $prefix ?>description" rows="3" maxlength="2000" placeholder="Ce que le membre sait faire une fois la compétence acquise, et dans quelles situations elle est attendue."<?= $linksReady ? '' : ' disabled' ?>><?= $h($c['description'] ?? '') ?></textarea>
        </div>
        <div class="cp-field">
            <label for="<?= $prefix ?>grade">Grade attendu</label>
            <select class="cp-input" name="grade_id" id="<?= $prefix ?>grade">
                <option value="0">Aucun grade imposé</option>
                <?php foreach ($competencyGrades as $g): ?>
                <option value="<?= (int) $g['id'] ?>" <?= $gradeId === (int) $g['id'] ? 'selected' : '' ?>><?= $h($g['label_long'] ?? $g['label_short'] ?? '') ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="cp-field">
            <label for="<?= $prefix ?>level">Niveau visé</label>
            <select class="cp-input" name="acquisition_level" id="<?= $prefix ?>level">
                <option value="">Non précisé</option>
                <?php foreach ($competencyAcquisitionLevels as $lvl): ?>
                <option value="<?= $h($lvl) ?>" <?= $level === $lvl ? 'selected' : '' ?>><?= $h($lvl) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="cp-field">
            <label for="<?= $prefix ?>mode">Comment elle est validée</label>
            <select class="cp-input" name="validation_mode" id="<?= $prefix ?>mode"<?= $linksReady ? '' : ' disabled' ?>>
                <option value="">Non précisé</option>
                <?php foreach (Gen::VALIDATION_MODES as $key => $vm): ?>
                <option value="<?= $h($key) ?>" <?= $mode === $key ? 'selected' : '' ?>><?= $h($vm['label']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="cp-field">
            <label for="<?= $prefix ?>order">Ordre du palier</label>
            <input class="cp-input" type="number" name="palier_order" id="<?= $prefix ?>order" min="0" value="<?= (int) ($c['palier_order'] ?? 0) ?>">
            <small>Les paliers s’affichent du plus petit au plus grand.</small>
        </div>
        <?php if ($linksReady): ?>
        <fieldset class="cp-field cp-field--full cp-ref-links-edit">
            <legend>Où cette compétence est utilisée</legend>
            <small>Cochez les éléments liés : ils s’affichent sur la fiche de la compétence et servent à générer les parcours.</small>
            <div class="cp-ref-pickers">
                <?php foreach (Gen::LINK_TYPES as $type => $typeLabel):
                    $items = $catalogLists[$type];
                    $checkedCount = 0;
                    foreach ($items as $it) {
                        $checkedCount += isset($linked[$type . ':' . $it['ref']]) ? 1 : 0;
                    }
                    ?>
                <details class="cp-ref-picker"<?= $checkedCount > 0 ? ' open' : '' ?>>
                    <summary><?= $h($typeLabel) ?> <span class="cp-count"><?= $checkedCount ?>/<?= count($items) ?></span></summary>
                    <p class="cp-help"><?= $h($linkHelp[$type]) ?></p>
                    <?php if ($items === []): ?>
                    <p class="cp-help">Rien à lier pour l’instant dans ce module.</p>
                    <?php else: ?>
                    <div class="cp-ref-picker__list">
                        <?php foreach ($items as $it): ?>
                        <label class="cp-check"><input type="checkbox" name="links[<?= $h($type) ?>][]" value="<?= $h($it['ref']) ?>" <?= isset($linked[$type . ':' . $it['ref']]) ? 'checked' : '' ?>> <?= $h($it['title']) ?><?php if (!empty($it['code'])): ?> <small><?= $h($it['code']) ?></small><?php endif; ?></label>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </details>
                <?php endforeach; ?>
            </div>
        </fieldset>
        <?php endif; ?>
        <div class="cp-form__actions">
            <?php if (!$linksReady): ?><span class="cp-help">L’explication et les liens arrivent avec la prochaine mise à jour de la plateforme.</span><?php endif; ?>
            <button type="submit" class="cp-btn cp-btn--primary"><?= $id > 0 ? 'Enregistrer les modifications' : 'Ajouter la compétence' ?></button>
        </div>
    </form>
    <?php
};
?>
<link rel="stylesheet" href="<?= $h(asset_url('assets/css/competency.css')) ?>">
<div class="bo-legacy bo-legacy--6xl">
<div class="cp cp-ref">
    <header class="cp-head">
        <div style="flex: 1 1 380px">
            <p class="cp-kicker">Référentiels</p>
            <h1 class="cp-title">Référentiel des compétences</h1>
            <p class="cp-lead">
                Une compétence est un savoir-faire attendu d’un membre : guider un appui aérien, commander une équipe, soigner un blessé.
                Ce référentiel dit, pour chaque palier de formation, ce qui est attendu, à quel grade, comment on l’acquiert et qui la valide.
            </p>
        </div>
        <dl class="cp-figures">
            <div><dt>Compétences</dt><dd><?= $total ?></dd></div>
            <div><dt>Paliers</dt><dd><?= count($competencyByPalier) ?></dd></div>
            <div><dt>Reliées</dt><dd><?= $linkedCount ?><small>/<?= $total ?></small></dd></div>
            <div><dt>Expliquées</dt><dd><?= $explainedCount ?><small>/<?= $total ?></small></dd></div>
        </dl>
    </header>

    <nav class="cp-ref-nav" aria-label="Modules liés">
        <a class="cp-btn cp-btn--small" href="<?= $h(url('academy/formations/competences') . '#generateur') ?>">Générer un parcours</a>
        <a class="cp-btn cp-btn--small" href="<?= $h(url('academy/studio')) ?>">Cours Academy</a>
        <a class="cp-btn cp-btn--small" href="<?= $h(training_lms_admin_url('competences/modules')) ?>">Modules de compétence</a>
        <a class="cp-btn cp-btn--small" href="<?= $h(url('back-office/referentiels/qualifications')) ?>">Qualifications et brevets</a>
        <a class="cp-btn cp-btn--small" href="<?= $h(url('back-office/personnel-job-roles')) ?>">Emplois</a>
        <a class="cp-btn cp-btn--small" href="<?= $h(url('orbat')) ?>">ORBAT</a>
        <a class="cp-btn cp-btn--small" href="<?= $h(url('back-office/referentiels/grades')) ?>">Grades</a>
        <a class="cp-btn cp-btn--small" href="<?= $h(url('back-office/referentiels/dotation')) ?>">Dotation</a>
    </nav>

    <details class="cp-guide"<?= $total === 0 ? ' open' : '' ?>>
        <summary><span class="cp-guide__icon" aria-hidden="true">?</span>Comment une compétence est acquise et validée</summary>
        <ol class="cp-ref-guide">
            <li><span><strong>Définir.</strong> Nommez la compétence, rangez-la dans un palier (formation initiale, spécialisation, encadrement…), indiquez le grade auquel elle est attendue et le niveau visé.</span></li>
            <li><span><strong>Relier.</strong> Liez-la aux cours Academy, aux modules de compétence et aux qualifications qui la font acquérir, aux emplois qui l’exigent et à la dotation qui la demande.</span></li>
            <li><span><strong>Valider.</strong> Choisissez qui la constate : un instructeur, une licence Academy, l’attribution d’un brevet, une évaluation en exercice ou un module validé.</span></li>
            <li><span><strong>Utiliser.</strong> Les formateurs en tirent des parcours dans l’Academy ; les emplois et postes ORBAT liés montrent qui doit la détenir.</span></li>
        </ol>
        <p class="cp-help cp-ref-guide__note">Ce référentiel décrit ce qui est attendu. Le suivi de chaque membre se fait dans les modules de compétence, l’Academy et l’attribution des qualifications (visibles sur sa fiche).</p>
    </details>

    <?php if ($flashSuccess): ?><p class="cp-flash" role="status"><?= $h($flashSuccess) ?></p><?php endif; ?>
    <?php if ($flashError): ?><p class="cp-flash cp-flash--err" role="alert"><?= $h($flashError) ?></p><?php endif; ?>
    <?php if (!$linksReady): ?>
    <p class="cp-flash cp-flash--err">Les explications, les liens vers les autres modules et les parcours arrivent avec la prochaine mise à jour de la plateforme. Les compétences existantes restent modifiables.</p>
    <?php endif; ?>

    <datalist id="cp-ref-paliers">
        <?php foreach (array_keys($competencyByPalier) as $p): ?><option value="<?= $h($p) ?>"></option><?php endforeach; ?>
    </datalist>

    <details class="cp-ref-add" id="ajouter"<?= $total === 0 ? ' open' : '' ?>>
        <summary class="cp-btn cp-btn--primary">Ajouter une compétence</summary>
        <?php $renderForm(null); ?>
    </details>

    <?php if ($competencyByPalier === []): ?>
    <div class="cp-empty">
        <p><strong>Aucune compétence dans le référentiel pour l’instant.</strong></p>
        <p>Commencez par les savoir-faire de la formation initiale, puis ajoutez les spécialités (JTAC, pilote, médic…) et l’encadrement.</p>
    </div>
    <?php else: ?>
    <div class="cp-ref-filter">
        <label class="sr-only" for="cp-ref-search">Rechercher une compétence</label>
        <input class="cp-input" type="search" id="cp-ref-search" placeholder="Rechercher une compétence, un cours, un emploi…" autocomplete="off">
        <span class="cp-help" id="cp-ref-search-count" aria-live="polite"></span>
    </div>

    <?php foreach ($competencyByPalier as $palierName => $rows):
        $palierId = 'palier-' . substr(md5((string) $palierName), 0, 10);
        ?>
    <section class="cp-ref-palier" aria-labelledby="<?= $h($palierId) ?>">
        <div class="cp-phase-block__head">
            <h2 id="<?= $h($palierId) ?>"><?= $h($palierName) ?></h2>
            <p>Palier n° <?= (int) ($rows[0]['palier_order'] ?? 0) ?></p>
            <span class="cp-count"><?= count($rows) ?></span>
        </div>
        <ul class="cp-ref-list">
            <?php foreach ($rows as $c):
                $cid = (int) $c['id'];
                $level = (string) ($c['acquisition_level'] ?? '');
                $mode = (string) ($c['validation_mode'] ?? '');
                $resolved = $c['resolved_links'] ?? [];
                $pathwayCount = (int) ($competencyUsage['pathways_by_competency'][(string) $cid] ?? 0);
                $search = [$c['label'], $c['palier'], $c['description'] ?? '', $c['grade_label'] ?? ''];
                foreach ($resolved as $items) {
                    foreach ($items as $it) {
                        $search[] = $it['title'];
                    }
                }
                ?>
            <li>
                <article class="cp-ref-card" id="competence-<?= $cid ?>" data-search="<?= $h(Gen::normalize(implode(' ', $search))) ?>">
                    <div class="cp-ref-card__top">
                        <h3 class="cp-item__name"><?= $h($c['label']) ?></h3>
                        <div class="cp-tags">
                            <?php if ($level !== ''): ?><span class="cp-tag cp-ref-level <?= $levelClass($level) ?>"><?= $h($level) ?></span><?php endif; ?>
                            <?php if (($c['grade_label'] ?? '') !== ''): ?><span class="cp-tag">Grade : <?= $h($c['grade_label']) ?></span><?php endif; ?>
                        </div>
                    </div>
                    <?php if (trim((string) ($c['description'] ?? '')) !== ''): ?>
                    <p class="cp-item__desc"><?= nl2br($h($c['description'])) ?></p>
                    <?php else: ?>
                    <p class="cp-help">Pas encore d’explication : précisez à quoi sert cette compétence pour les membres et les formateurs.</p>
                    <?php endif; ?>
                    <p class="cp-ref-validation">
                        <b>Validation :</b>
                        <?php if (isset(Gen::VALIDATION_MODES[$mode])): ?>
                            <?= $h(Gen::VALIDATION_MODES[$mode]['label']) ?> — <span><?= $h(Gen::VALIDATION_MODES[$mode]['help']) ?></span>
                        <?php else: ?>
                            <span>non précisée (par défaut, un instructeur la constate).</span>
                        <?php endif; ?>
                    </p>

                    <div class="cp-ref-usage">
                        <p class="cp-ref-usage__title">Utilisée dans</p>
                        <?php if ($resolved === [] && $pathwayCount === 0): ?>
                        <p class="cp-help">Aucun module lié pour l’instant.</p>
                        <?php endif; ?>
                        <dl class="cp-ref-usage__list">
                            <?php foreach (Gen::LINK_TYPES as $type => $typeLabel):
                                if (empty($resolved[$type])) {
                                    continue;
                                }
                                ?>
                            <div class="cp-ref-usage__row">
                                <dt><?= $h($typeLabel) ?></dt>
                                <dd>
                                    <?php foreach ($resolved[$type] as $it): ?>
                                    <span class="cp-ref-chip">
                                        <?php if (!empty($it['url'])): ?><a href="<?= $h($it['url']) ?>"><?= $h($it['title']) ?></a><?php else: ?><?= $h($it['title']) ?><?php endif; ?>
                                        <?php if ($type === 'job_role'):
                                            $billets = $competencyUsage['billets_by_job_role'][(string) $it['ref']] ?? [];
                                            $members = (int) ($competencyUsage['members_by_job_role'][(string) $it['ref']] ?? 0);
                                            ?>
                                        <small><?= $members ?> membre<?= $members > 1 ? 's' : '' ?><?= $billets !== [] ? ' · postes ORBAT : ' . $h(implode(', ', array_slice($billets, 0, 4))) . (count($billets) > 4 ? '…' : '') : '' ?></small>
                                        <?php endif; ?>
                                    </span>
                                    <?php endforeach; ?>
                                </dd>
                            </div>
                            <?php endforeach; ?>
                            <?php if ($pathwayCount > 0): ?>
                            <div class="cp-ref-usage__row">
                                <dt>Parcours</dt>
                                <dd><a class="cp-ref-chip" href="<?= $h(url('academy/formations/competences') . '#parcours-enregistres') ?>"><?= $pathwayCount ?> parcours enregistré<?= $pathwayCount > 1 ? 's' : '' ?></a></dd>
                            </div>
                            <?php endif; ?>
                        </dl>
                        <?php if ($linksReady && ($c['suggested_links'] ?? []) !== []): ?>
                        <p class="cp-ref-suggest">À lier ? D’après son nom :
                            <?php foreach ($c['suggested_links'] as $sug): ?>
                            <span class="cp-tag"><?= $h(Gen::LINK_TYPES[$sug['type']] ?? '') ?> · <?= $h($sug['title']) ?></span>
                            <?php endforeach; ?>
                        </p>
                        <?php endif; ?>
                    </div>

                    <div class="cp-module__actions">
                        <a class="cp-btn cp-btn--small" href="<?= $h(url('academy/formations/competences') . '?cible=' . rawurlencode('competency:' . $cid) . '#generateur') ?>">Générer un parcours</a>
                        <details class="cp-ref-edit">
                            <summary class="cp-btn cp-btn--small">Modifier</summary>
                            <?php $renderForm($c); ?>
                        </details>
                        <form method="post" action="<?= $h(url('back-office/referentiels/competences/' . $cid . '/supprimer')) ?>" onsubmit="return confirm('Retirer cette compétence du référentiel ? Ses liens seront aussi retirés.');">
                            <input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>">
                            <button type="submit" class="cp-btn cp-btn--small cp-btn--ghost cp-ref-danger">Supprimer</button>
                        </form>
                    </div>
                </article>
            </li>
            <?php endforeach; ?>
        </ul>
    </section>
    <?php endforeach; ?>
    <?php endif; ?>
</div>
</div>
<script>
(function () {
    var input = document.getElementById('cp-ref-search');
    if (!input) { return; }
    var count = document.getElementById('cp-ref-search-count');
    var norm = function (s) { return s.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/[^a-z0-9]+/g, ' ').trim(); };
    input.addEventListener('input', function () {
        var q = norm(input.value);
        var shown = 0;
        document.querySelectorAll('.cp-ref-palier').forEach(function (section) {
            var visible = 0;
            section.querySelectorAll('.cp-ref-card').forEach(function (card) {
                var ok = q === '' || (card.getAttribute('data-search') || '').indexOf(q) !== -1;
                card.parentElement.hidden = !ok;
                visible += ok ? 1 : 0;
            });
            section.hidden = visible === 0;
            shown += visible;
        });
        count.textContent = q === '' ? '' : shown + ' compétence' + (shown > 1 ? 's' : '');
    });
})();
</script>
