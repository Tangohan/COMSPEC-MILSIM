<?php require base_path('views/admin/training/partials/command_shell_open.php'); ?>
<?php
/**
 * Pilotage — modules compétences de l’organisation, rangés par phase ALPHA → DELTA.
 * @var bool $competencySchemaReady
 * @var list<array<string, mixed>> $competencyModules
 * @var array<string, mixed>|null $competencyEditing
 * @var array<string, mixed>|null $competencyDraft
 * @var bool $competencyCanDesign
 */
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$schemaReady = !empty($competencySchemaReady);
$modules = is_array($competencyModules ?? null) ? $competencyModules : [];
$editing = is_array($competencyEditing ?? null) ? $competencyEditing : null;
$draft = is_array($competencyDraft ?? null) ? $competencyDraft : null;
$canDesign = !empty($competencyCanDesign);
$phases = \App\Services\Training\CompetencyModuleService::PHASE_LABELS;
$modes = \App\Services\Training\CompetencyModuleService::DELIVERY_LABELS;
$error = \App\Core\Session::getFlash('error');
$success = \App\Core\Session::getFlash('success');

$byPhase = array_fill_keys(array_keys($phases), []);
$names = [];
$active = $mandatory = $validated = $toRenew = 0;
foreach ($modules as $m) {
    $byPhase[$m['module_type']][] = $m;
    $names[$m['id']] = $m['name'];
    if ($m['is_active']) {
        $active++;
        $mandatory += $m['is_mandatory'] ? 1 : 0;
    }
    $validated += (int) ($m['progress']['COMPLETED'] ?? 0);
    $toRenew += (int) ($m['progress']['EXPIRED'] ?? 0);
}

// Valeurs du formulaire : brouillon après erreur, sinon module en cours d’édition, sinon vide.
$formId = $draft !== null ? (int) ($draft['id'] ?? 0) : (int) ($editing['id'] ?? 0);
$val = static function (string $key, mixed $default = '') use ($draft, $editing): mixed {
    if ($draft !== null && array_key_exists($key, $draft)) {
        return $draft[$key];
    }
    if ($editing !== null && array_key_exists($key, $editing)) {
        return $editing[$key];
    }

    return $default;
};
$formPhase = strtoupper((string) $val('module_type', 'ALPHA'));
$formPrereqs = array_map('intval', (array) $val('prereq_ids', []));
$formActive = $draft !== null ? !empty($draft['is_active']) : ($editing !== null ? (bool) $editing['is_active'] : true);
$formMandatory = $draft !== null ? !empty($draft['is_mandatory']) : ($editing !== null ? (bool) $editing['is_mandatory'] : false);
$formAction = $formId > 0
    ? training_lms_admin_url('competences/modules/' . $formId)
    : training_lms_admin_url('competences/modules');
?>
<link rel="stylesheet" href="<?= $h(asset_url('assets/css/competency.css')) ?>">

<div class="cp">
    <header class="cp-head">
        <div>
            <p class="cp-kicker">Compétences</p>
            <h1 class="cp-title">Modules du parcours</h1>
            <p class="cp-lead">
                Les modules forment le parcours compétences de vos membres, de la doctrine (ALPHA) à la validation par un instructeur (DELTA).
                Chaque membre voit sa progression sur « Mon parcours compétences ».
            </p>
        </div>
        <dl class="cp-figures">
            <div><dt>Modules actifs</dt><dd><?= $active ?><small> / <?= count($modules) ?></small></dd></div>
            <div><dt>Obligatoires</dt><dd><?= $mandatory ?></dd></div>
            <div><dt>Validations</dt><dd><?= $validated ?></dd></div>
            <div><dt>À renouveler</dt><dd><?= $toRenew ?></dd></div>
        </dl>
    </header>

    <?php if ($error): ?><div class="cp-flash cp-flash--err" role="alert"><?= $h((string) $error) ?></div><?php endif; ?>
    <?php if ($success): ?><div class="cp-flash" role="status"><?= $h((string) $success) ?></div><?php endif; ?>

    <?php if (!$schemaReady): ?>
        <div class="cp-empty">
            <p><strong>Le cadre compétences n’est pas encore installé sur cet environnement.</strong></p>
            <p>Un administrateur technique doit appliquer les migrations, puis cette page permettra de créer les modules.</p>
        </div>
    <?php else: ?>

    <details class="cp-guide"<?= $modules === [] ? ' open' : '' ?>>
        <summary><span class="cp-guide__icon" aria-hidden="true">?</span>Comment construire le parcours</summary>
        <ol>
            <li><span><strong>Créez les modules par phase.</strong> ALPHA pour la doctrine, BRAVO pour la pratique, CHARLIE pour la simulation, DELTA pour la validation instructeur.</span></li>
            <li><span><strong>Fixez les règles.</strong> Un prérequis bloque le module tant que le précédent n’est pas validé ; un renouvellement fait expirer la validation après X jours.</span></li>
            <li><span><strong>Suivez vos membres.</strong> Ouvrez « Suivi » sur un module pour passer des membres en cours, validés ou à renouveler.</span></li>
        </ol>
    </details>

    <section class="cp-section" aria-labelledby="cp-board-title">
        <div class="cp-section__head">
            <div>
                <h2 id="cp-board-title">Parcours ALPHA → DELTA</h2>
                <p class="cp-section__lead">Un module retiré du parcours n’apparaît plus aux membres, mais leur historique est conservé.</p>
            </div>
            <?php if ($canDesign): ?>
                <a class="cp-btn cp-btn--primary" href="<?= $h(training_lms_admin_url('competences/modules') . '#module-form') ?>">+ Nouveau module</a>
            <?php endif; ?>
        </div>

        <div class="cp-board">
            <?php foreach ($phases as $code => $meta): ?>
                <section class="cp-col cp-phase--<?= $h($code) ?>" aria-labelledby="cp-col-<?= $h($code) ?>">
                    <div class="cp-col__head">
                        <div>
                            <h2 id="cp-col-<?= $h($code) ?>"><span><?= $h($meta['label']) ?></span></h2>
                            <p><?= $h($meta['subtitle']) ?></p>
                        </div>
                        <span class="cp-count"><?= count($byPhase[$code]) ?></span>
                    </div>
                    <?php if ($byPhase[$code] === []): ?>
                        <p class="cp-col__empty">Aucun module <?= $h($meta['label']) ?>.</p>
                    <?php endif; ?>
                    <?php foreach ($byPhase[$code] as $m): ?>
                        <?php
                        $pr = (array) $m['progress'];
                        $prereqNames = array_values(array_filter(array_map(static fn (int $id): string => $names[$id] ?? '', $m['prereq_ids'])));
                        ?>
                        <article class="cp-module<?= $m['is_active'] ? '' : ' is-off' ?>" id="module-<?= (int) $m['id'] ?>">
                            <div class="cp-module__top">
                                <h3 class="cp-module__name"><?= $h($m['name']) ?></h3>
                                <span class="cp-module__code"><?= $h($m['code']) ?></span>
                            </div>
                            <div class="cp-tags">
                                <span class="cp-tag"><?= $h($modes[$m['delivery_mode']] ?? $m['delivery_mode']) ?></span>
                                <?php if ($m['is_mandatory']): ?><span class="cp-tag cp-tag--must">Obligatoire</span><?php endif; ?>
                                <?php if ($m['recurrence_days'] !== null): ?><span class="cp-tag cp-tag--renew">Tous les <?= (int) $m['recurrence_days'] ?> j</span><?php endif; ?>
                                <?php if ($m['duration_min'] !== null): ?><span class="cp-tag"><?= (int) $m['duration_min'] ?> min</span><?php endif; ?>
                                <?php if (!$m['is_active']): ?><span class="cp-tag cp-tag--off">Retiré du parcours</span><?php endif; ?>
                            </div>
                            <?php if ($prereqNames !== []): ?>
                                <p class="cp-module__prereq">Après : <b><?= $h(implode(', ', $prereqNames)) ?></b></p>
                            <?php endif; ?>
                            <p class="cp-module__stats">
                                <span><b><?= (int) ($pr['COMPLETED'] ?? 0) ?></b> validés</span>
                                <span><b><?= (int) ($pr['IN_PROGRESS'] ?? 0) ?></b> en cours</span>
                                <?php if ((int) ($pr['EXPIRED'] ?? 0) > 0): ?><span><b><?= (int) $pr['EXPIRED'] ?></b> à renouveler</span><?php endif; ?>
                            </p>
                            <div class="cp-module__actions">
                                <a class="cp-btn cp-btn--small" href="<?= $h(training_lms_admin_url('competences/modules/' . (int) $m['id'] . '/suivi')) ?>">Suivi des membres</a>
                                <?php if ($canDesign): ?>
                                    <a class="cp-btn cp-btn--small" href="<?= $h(training_lms_admin_url('competences/modules') . '?modifier=' . (int) $m['id'] . '#module-form') ?>">Modifier</a>
                                    <form method="post" action="<?= $h(training_lms_admin_url('competences/modules/' . (int) $m['id'] . '/activation')) ?>">
                                        <?= \App\Core\Csrf::field() ?>
                                        <input type="hidden" name="active" value="<?= $m['is_active'] ? '0' : '1' ?>">
                                        <button type="submit" class="cp-btn cp-btn--small cp-btn--ghost"><?= $m['is_active'] ? 'Retirer' : 'Réactiver' ?></button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </section>
            <?php endforeach; ?>
        </div>
    </section>

    <?php if ($canDesign): ?>
        <section class="cp-section" aria-labelledby="cp-form-title">
            <div class="cp-section__head">
                <div>
                    <h2 id="cp-form-title"><?= $formId > 0 ? 'Modifier « ' . $h((string) $val('name')) . ' »' : 'Nouveau module' ?></h2>
                    <p class="cp-section__lead">Les champs marqués d’un astérisque sont obligatoires.</p>
                </div>
                <?php if ($formId > 0): ?>
                    <a class="cp-link" href="<?= $h(training_lms_admin_url('competences/modules') . '#module-form') ?>">Annuler la modification</a>
                <?php endif; ?>
            </div>
            <form id="module-form" class="cp-form<?= $formId > 0 ? ' is-editing' : '' ?>" method="post" action="<?= $h($formAction) ?>">
                <?= \App\Core\Csrf::field() ?>

                <fieldset class="cp-field cp-field--full" style="border:0;margin:0;padding:0">
                    <legend>Phase *</legend>
                    <div class="cp-phase-pick">
                        <?php foreach ($phases as $code => $meta): ?>
                            <label class="cp-phase--<?= $h($code) ?>">
                                <input type="radio" name="module_type" value="<?= $h($code) ?>"<?= $formPhase === $code ? ' checked' : '' ?> required>
                                <?= $h($meta['label']) ?>
                                <small><?= $h($meta['subtitle']) ?></small>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </fieldset>

                <div class="cp-field cp-field--wide">
                    <label for="cp-name">Nom du module *</label>
                    <input id="cp-name" name="name" class="cp-input" maxlength="180" required value="<?= $h((string) $val('name')) ?>" placeholder="Ex. Règles d’engagement et cadre légal">
                </div>
                <div class="cp-field">
                    <label for="cp-code">Code *</label>
                    <input id="cp-code" name="code" class="cp-input" maxlength="80" required value="<?= $h((string) $val('code')) ?>" placeholder="Ex. ROE-01">
                    <small>Court et unique : lettres, chiffres, tirets.</small>
                </div>

                <div class="cp-field cp-field--full">
                    <label for="cp-desc">Description <small>(facultatif)</small></label>
                    <textarea id="cp-desc" name="description" class="cp-input" rows="3" maxlength="5000" placeholder="Ce que le membre doit savoir faire à l’issue du module."><?= $h((string) $val('description')) ?></textarea>
                </div>

                <div class="cp-field">
                    <label for="cp-mode">Type de formation</label>
                    <select id="cp-mode" name="delivery_mode" class="cp-input">
                        <?php foreach ($modes as $modeKey => $modeLabel): ?>
                            <option value="<?= $h($modeKey) ?>"<?= strtoupper((string) $val('delivery_mode', 'INITIAL')) === $modeKey ? ' selected' : '' ?>><?= $h($modeLabel) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="cp-field">
                    <label for="cp-duration">Durée en minutes <small>(facultatif)</small></label>
                    <input id="cp-duration" name="duration_min" type="number" min="1" max="100000" class="cp-input" value="<?= $h((string) ($val('duration_min') ?? '')) ?>">
                </div>
                <div class="cp-field">
                    <label for="cp-renew">Renouveler tous les… jours <small>(facultatif)</small></label>
                    <input id="cp-renew" name="recurrence_days" type="number" min="1" max="3650" class="cp-input" value="<?= $h((string) ($val('recurrence_days') ?? '')) ?>" placeholder="Ex. 365">
                    <small>Vide : la validation n’expire jamais.</small>
                </div>

                <fieldset class="cp-field cp-field--full" style="border:0;margin:0;padding:0">
                    <legend>Prérequis <small>(modules à valider avant celui-ci)</small></legend>
                    <?php
                    $candidates = array_values(array_filter($modules, static fn (array $m): bool => $m['id'] !== $formId));
                    ?>
                    <?php if ($candidates === []): ?>
                        <p class="cp-help">Aucun autre module pour l’instant.</p>
                    <?php else: ?>
                        <div class="cp-checks">
                            <?php foreach ($candidates as $c): ?>
                                <label class="cp-check">
                                    <input type="checkbox" name="prereq_ids[]" value="<?= (int) $c['id'] ?>"<?= in_array((int) $c['id'], $formPrereqs, true) ? ' checked' : '' ?>>
                                    <span><?= $h($c['name']) ?> <small><?= $h($c['module_type']) ?></small></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </fieldset>

                <div class="cp-field cp-field--full">
                    <div class="cp-checks">
                        <label class="cp-check"><input type="checkbox" name="is_active" value="1"<?= $formActive ? ' checked' : '' ?>> <span>Visible dans le parcours des membres</span></label>
                        <label class="cp-check"><input type="checkbox" name="is_mandatory" value="1"<?= $formMandatory ? ' checked' : '' ?>> <span>Obligatoire</span></label>
                    </div>
                </div>

                <div class="cp-form__actions">
                    <p class="cp-help">Après l’enregistrement, ouvrez « Suivi des membres » pour enregistrer les validations.</p>
                    <button type="submit" class="cp-btn cp-btn--primary"><?= $formId > 0 ? 'Enregistrer les modifications' : 'Créer le module' ?></button>
                </div>
            </form>
        </section>
    <?php else: ?>
        <p class="cp-help">Vous pouvez suivre et valider les membres. La création des modules est réservée à l’encadrement formation.</p>
    <?php endif; ?>

    <?php endif; ?>
</div>
<?php require base_path('views/admin/training/partials/command_shell_close.php'); ?>
