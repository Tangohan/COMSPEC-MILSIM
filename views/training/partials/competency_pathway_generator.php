<?php
declare(strict_types=1);
/**
 * Générateur de parcours de compétence (Academy > Formations > Compétences).
 * Consultation pour tous les membres ; ajustement et enregistrement réservés au pilotage des formations.
 *
 * @var array<string, mixed> $pathwayGenerator
 */

use App\Services\Training\CompetencyPathwayGenerator as Gen;

$g = $pathwayGenerator ?? [];
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$templates = $g['templates'] ?? Gen::templates();
$competencies = $g['competencies'] ?? [];
$jobRoles = $g['job_roles'] ?? [];
$target = (string) ($g['target'] ?? '');
$generated = $g['generated'] ?? null;
$saved = $g['saved'] ?? [];
$canDesign = !empty($g['can_design']);
$saveReady = !empty($g['save_ready']);
$here = url('academy/formations/competences');
$sourceLabels = [
    'lien' => 'Lié',
    'suggestion' => 'Proposé',
    'prerequis' => 'Prérequis',
    'modele' => 'Modèle',
    'referentiel' => 'Référentiel',
    'generique' => 'À préciser',
];
$byPalier = [];
foreach ($competencies as $c) {
    $byPalier[(string) $c['palier']][] = $c;
}
$formatDuration = static function (int $min): string {
    if ($min <= 0) {
        return '—';
    }

    return $min >= 60 ? intdiv($min, 60) . ' h' . ($min % 60 > 0 ? ' ' . str_pad((string) ($min % 60), 2, '0', STR_PAD_LEFT) : '') : $min . ' min';
};
$renderReadonlySteps = static function (array $steps) use ($h, $sourceLabels): void {
    ?>
    <ol class="cp-gen-steps">
        <?php foreach ($steps as $s):
            $kind = (string) ($s['kind'] ?? 'custom');
            ?>
        <li class="cp-gen-step cp-gen-step--<?= $h($kind) ?>">
            <span class="cp-gen-step__kind"><?= $h(Gen::STEP_KINDS[$kind] ?? 'Étape') ?></span>
            <div class="cp-gen-step__body">
                <p class="cp-gen-step__title">
                    <?php if (!empty($s['url'])): ?><a href="<?= $h($s['url']) ?>"><?= $h($s['title']) ?></a><?php else: ?><?= $h($s['title']) ?><?php endif; ?>
                </p>
                <?php if (trim((string) ($s['detail'] ?? '')) !== ''): ?><p class="cp-gen-step__detail"><?= $h($s['detail']) ?></p><?php endif; ?>
            </div>
            <div class="cp-tags">
                <?php if (!empty($s['duration_min'])): ?><span class="cp-tag"><?= (int) $s['duration_min'] ?> min</span><?php endif; ?>
                <?php if (isset($s['source'], $sourceLabels[$s['source']])): ?><span class="cp-tag cp-gen-src--<?= $h($s['source']) ?>"><?= $h($sourceLabels[$s['source']]) ?></span><?php endif; ?>
            </div>
        </li>
        <?php endforeach; ?>
    </ol>
    <?php
};
?>
<section class="cp-section cp-gen" id="generateur" aria-labelledby="cp-gen-title">
    <div class="cp-gen__intro">
        <p class="cp-kicker">Parcours de compétence</p>
        <h2 id="cp-gen-title" class="cp-gen__title">Générer un parcours</h2>
        <p class="cp-lead">
            Choisissez un objectif — une compétence, un emploi ou un modèle — et la plateforme propose un parcours ordonné :
            prérequis, cours Academy, modules, exercices puis validation. Il est construit uniquement à partir de ce que la
            communauté a déjà saisi (référentiel des compétences, cours, modules, qualifications) : le même objectif donne toujours le même parcours.
            <?= $canDesign ? 'Ajustez-le puis enregistrez-le pour le proposer aux membres.' : 'L’encadrement formation peut l’ajuster et l’enregistrer pour tous.' ?>
        </p>
    </div>

    <div class="cp-gen-templates" role="list" aria-label="Modèles de parcours">
        <?php foreach ($templates as $key => $t):
            $active = $target === 'template:' . $key;
            ?>
        <a role="listitem" class="cp-gen-template<?= $active ? ' is-active' : '' ?>" href="<?= $h($here . '?cible=' . rawurlencode('template:' . $key) . '#generateur') ?>"<?= $active ? ' aria-current="true"' : '' ?>>
            <span class="cp-gen-template__name"><?= $h($t['label']) ?></span>
            <span class="cp-gen-template__summary"><?= $h($t['summary']) ?></span>
        </a>
        <?php endforeach; ?>
    </div>

    <form method="get" action="<?= $h($here) ?>#generateur" class="cp-gen-pick">
        <label for="cp-gen-target" class="cp-gen-pick__label">Ou partir d’un objectif précis</label>
        <select class="cp-input" name="cible" id="cp-gen-target" required>
            <option value="">Choisir une compétence ou un emploi…</option>
            <?php foreach ($byPalier as $palier => $rows): ?>
            <optgroup label="<?= $h('Compétences — ' . $palier) ?>">
                <?php foreach ($rows as $c): $v = 'competency:' . (int) $c['id']; ?>
                <option value="<?= $h($v) ?>" <?= $target === $v ? 'selected' : '' ?>><?= $h($c['label']) ?></option>
                <?php endforeach; ?>
            </optgroup>
            <?php endforeach; ?>
            <?php if ($jobRoles !== []): ?>
            <optgroup label="Emplois et postes">
                <?php foreach ($jobRoles as $r): $v = 'job_role:' . $r['ref']; ?>
                <option value="<?= $h($v) ?>" <?= $target === $v ? 'selected' : '' ?>><?= $h($r['title']) ?></option>
                <?php endforeach; ?>
            </optgroup>
            <?php endif; ?>
        </select>
        <button type="submit" class="cp-btn cp-btn--primary">Générer</button>
    </form>
    <?php if ($competencies === [] && $jobRoles === []): ?>
    <p class="cp-help">Le référentiel des compétences de votre communauté est encore vide : seuls les modèles sont proposés pour l’instant.</p>
    <?php endif; ?>

    <?php if (is_array($generated)): ?>
        <?php if (empty($generated['ok'])): ?>
        <p class="cp-flash cp-flash--err" role="alert"><?= $h($generated['error'] ?? 'Impossible de générer ce parcours.') ?></p>
        <?php else:
            $stats = $generated['stats'];
            $steps = $generated['steps'];
            ?>
        <article class="cp-gen-result" aria-labelledby="cp-gen-result-title">
            <header class="cp-gen-result__head">
                <div>
                    <h3 id="cp-gen-result-title"><?= $h($generated['title']) ?></h3>
                    <p class="cp-help"><?= $h($generated['summary']) ?></p>
                </div>
                <dl class="cp-figures">
                    <div><dt>Étapes</dt><dd><?= (int) $stats['steps'] ?></dd></div>
                    <div><dt>Durée des cours</dt><dd><?= $h($formatDuration((int) $stats['duration_min'])) ?></dd></div>
                    <div><dt>Liés au référentiel</dt><dd><?= (int) $stats['linked'] ?></dd></div>
                    <div><dt>Proposés</dt><dd><?= (int) $stats['suggested'] ?></dd></div>
                </dl>
            </header>
            <p class="cp-help cp-gen-legend">
                <span class="cp-tag cp-gen-src--lien">Lié</span> relié à la compétence dans le référentiel ·
                <span class="cp-tag cp-gen-src--suggestion">Proposé</span> retrouvé d’après son intitulé, à vérifier ·
                <span class="cp-tag cp-gen-src--modele">Modèle</span> étape type du modèle ·
                <span class="cp-tag cp-gen-src--generique">À préciser</span> étape générique à compléter.
            </p>

            <?php if ($canDesign && $saveReady): ?>
            <form method="post" action="<?= $h(url('academy/formations/competences/parcours')) ?>" class="cp-gen-edit" data-cp-gen-edit>
                <input type="hidden" name="_csrf_token" value="<?= $h(\App\Core\Csrf::token()) ?>">
                <input type="hidden" name="target_kind" value="<?= $h($generated['target']['kind']) ?>">
                <input type="hidden" name="target_ref" value="<?= $h($generated['target']['ref']) ?>">
                <div class="cp-gen-edit__meta">
                    <div class="cp-field">
                        <label for="cp-gen-name">Nom du parcours</label>
                        <input class="cp-input" type="text" id="cp-gen-name" name="name" required maxlength="150" value="<?= $h($generated['title']) ?>">
                    </div>
                    <div class="cp-field">
                        <label for="cp-gen-summary">Présentation pour les membres</label>
                        <input class="cp-input" type="text" id="cp-gen-summary" name="summary" maxlength="500" value="<?= $h($generated['summary']) ?>">
                    </div>
                </div>
                <p class="cp-help">Décochez une étape pour la retirer, changez l’ordre avec les flèches, modifiez les textes ou complétez les lignes libres en bas.</p>
                <ol class="cp-gen-rows" data-cp-gen-rows>
                    <?php foreach ($steps as $i => $s):
                        $kind = (string) $s['kind'];
                        ?>
                    <li class="cp-gen-row cp-gen-step--<?= $h($kind) ?>" data-cp-gen-row>
                        <input type="hidden" name="steps[<?= $i ?>][position]" value="<?= $i + 1 ?>" data-cp-gen-pos>
                        <input type="hidden" name="steps[<?= $i ?>][ref_type]" value="<?= $h($s['ref_type']) ?>">
                        <input type="hidden" name="steps[<?= $i ?>][ref]" value="<?= $h($s['ref']) ?>">
                        <label class="cp-gen-row__keep"><input type="checkbox" name="steps[<?= $i ?>][keep]" value="1" checked><span class="sr-only">Garder l’étape</span></label>
                        <select class="cp-input cp-gen-row__kind" name="steps[<?= $i ?>][kind]" aria-label="Type d’étape">
                            <?php foreach (Gen::STEP_KINDS as $k => $kl): ?>
                            <option value="<?= $h($k) ?>" <?= $k === $kind ? 'selected' : '' ?>><?= $h($kl) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="cp-gen-row__text">
                            <input class="cp-input" type="text" name="steps[<?= $i ?>][title]" value="<?= $h($s['title']) ?>" maxlength="160" aria-label="Intitulé de l’étape">
                            <input class="cp-input cp-gen-row__detail" type="text" name="steps[<?= $i ?>][detail]" value="<?= $h($s['detail']) ?>" maxlength="500" aria-label="Précision">
                        </div>
                        <div class="cp-gen-row__meta">
                            <?php if (isset($sourceLabels[$s['source']])): ?><span class="cp-tag cp-gen-src--<?= $h($s['source']) ?>"><?= $h($sourceLabels[$s['source']]) ?></span><?php endif; ?>
                            <?php if (!empty($s['url'])): ?><a class="cp-link" href="<?= $h($s['url']) ?>" target="_blank" rel="noopener">Ouvrir</a><?php endif; ?>
                        </div>
                        <div class="cp-gen-row__move">
                            <button type="button" class="cp-btn cp-btn--small cp-btn--ghost" data-cp-gen-up aria-label="Monter l’étape">↑</button>
                            <button type="button" class="cp-btn cp-btn--small cp-btn--ghost" data-cp-gen-down aria-label="Descendre l’étape">↓</button>
                        </div>
                    </li>
                    <?php endforeach; ?>
                    <?php for ($j = count($steps); $j < count($steps) + 3; $j++): ?>
                    <li class="cp-gen-row cp-gen-row--blank" data-cp-gen-row>
                        <input type="hidden" name="steps[<?= $j ?>][position]" value="<?= $j + 1 ?>" data-cp-gen-pos>
                        <label class="cp-gen-row__keep"><input type="checkbox" name="steps[<?= $j ?>][keep]" value="1" checked><span class="sr-only">Garder l’étape</span></label>
                        <select class="cp-input cp-gen-row__kind" name="steps[<?= $j ?>][kind]" aria-label="Type d’étape">
                            <?php foreach (Gen::STEP_KINDS as $k => $kl): ?>
                            <option value="<?= $h($k) ?>" <?= $k === 'custom' ? 'selected' : '' ?>><?= $h($kl) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="cp-gen-row__text">
                            <input class="cp-input" type="text" name="steps[<?= $j ?>][title]" value="" maxlength="160" placeholder="Étape à ajouter (laisser vide pour ignorer)" aria-label="Intitulé de l’étape">
                            <input class="cp-input cp-gen-row__detail" type="text" name="steps[<?= $j ?>][detail]" value="" maxlength="500" placeholder="Précision" aria-label="Précision">
                        </div>
                        <div class="cp-gen-row__meta"></div>
                        <div class="cp-gen-row__move">
                            <button type="button" class="cp-btn cp-btn--small cp-btn--ghost" data-cp-gen-up aria-label="Monter l’étape">↑</button>
                            <button type="button" class="cp-btn cp-btn--small cp-btn--ghost" data-cp-gen-down aria-label="Descendre l’étape">↓</button>
                        </div>
                    </li>
                    <?php endfor; ?>
                </ol>
                <div class="cp-form__actions">
                    <a class="cp-btn" href="<?= $h($here . '?cible=' . rawurlencode($target) . '#generateur') ?>">Régénérer</a>
                    <button type="submit" class="cp-btn cp-btn--primary">Enregistrer le parcours</button>
                </div>
            </form>
            <?php else: ?>
                <?php $renderReadonlySteps($steps); ?>
                <?php if ($canDesign && !$saveReady): ?>
                <p class="cp-help">L’enregistrement des parcours arrive avec la prochaine mise à jour de la plateforme.</p>
                <?php elseif (!$canDesign): ?>
                <p class="cp-help">Parcours indicatif : demandez à l’encadrement formation de l’enregistrer pour qu’il apparaisse dans la liste ci-dessous.</p>
                <?php endif; ?>
            <?php endif; ?>
        </article>
        <?php endif; ?>
    <?php endif; ?>

    <div class="cp-gen-saved" id="parcours-enregistres">
        <h3 class="cp-gen-saved__title">Parcours enregistrés <span class="cp-count"><?= count($saved) ?></span></h3>
        <?php if ($saved === []): ?>
        <p class="cp-help">Aucun parcours enregistré pour l’instant<?= $canDesign ? ' : générez-en un ci-dessus puis enregistrez-le.' : '.' ?></p>
        <?php else: ?>
        <?php foreach ($saved as $p): ?>
        <details class="cp-gen-pathway" id="parcours-<?= (int) $p['id'] ?>">
            <summary>
                <span class="cp-gen-pathway__name"><?= $h($p['name']) ?></span>
                <span class="cp-help"><?= count($p['steps']) ?> étape<?= count($p['steps']) > 1 ? 's' : '' ?></span>
            </summary>
            <?php if (trim((string) ($p['summary'] ?? '')) !== ''): ?><p class="cp-help"><?= $h($p['summary']) ?></p><?php endif; ?>
            <?php $renderReadonlySteps($p['steps']); ?>
            <?php if ($canDesign): ?>
            <div class="cp-module__actions">
                <form method="post" action="<?= $h(url('academy/formations/competences/parcours/' . (int) $p['id'] . '/supprimer')) ?>" onsubmit="return confirm('Supprimer ce parcours ?');">
                    <input type="hidden" name="_csrf_token" value="<?= $h(\App\Core\Csrf::token()) ?>">
                    <button type="submit" class="cp-btn cp-btn--small cp-btn--ghost cp-ref-danger">Supprimer</button>
                </form>
            </div>
            <?php endif; ?>
        </details>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
</section>
<script>
(function () {
    var list = document.querySelector('[data-cp-gen-rows]');
    if (!list) { return; }
    var renumber = function () {
        list.querySelectorAll('[data-cp-gen-row]').forEach(function (row, i) {
            var pos = row.querySelector('[data-cp-gen-pos]');
            if (pos) { pos.value = String(i + 1); }
        });
    };
    list.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-cp-gen-up],[data-cp-gen-down]');
        if (!btn) { return; }
        var row = btn.closest('[data-cp-gen-row]');
        if (btn.hasAttribute('data-cp-gen-up') && row.previousElementSibling) {
            list.insertBefore(row, row.previousElementSibling);
        } else if (btn.hasAttribute('data-cp-gen-down') && row.nextElementSibling) {
            list.insertBefore(row.nextElementSibling, row);
        }
        renumber();
        btn.focus();
    });
    list.addEventListener('change', function (e) {
        if (e.target.matches('.cp-gen-row__keep input')) {
            e.target.closest('[data-cp-gen-row]').classList.toggle('is-off', !e.target.checked);
        }
    });
})();
</script>
