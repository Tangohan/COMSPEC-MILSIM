<?php
declare(strict_types=1);

$definition = $definition ?? null;
$categories = is_array($categories ?? null) ? $categories : [];
$types = is_array($types ?? null) ? $types : [];
$templates = is_array($templates ?? null) ? $templates : [];
$levels = is_array($levels ?? null) ? $levels : [];
$prerequisites = is_array($prerequisites ?? null) ? $prerequisites : [];
$customFields = is_array($customFields ?? null) ? $customFields : [];
$permissionGrants = is_array($permissionGrants ?? null) ? $permissionGrants : [];
$holders = is_array($holders ?? null) ? $holders : [];
$allDefinitions = is_array($allDefinitions ?? null) ? $allDefinitions : [];
$qualificationExamples = is_array($qualificationExamples ?? null) ? $qualificationExamples : \App\Support\QualificationExamples::all();
$isEdit = is_array($definition);
$badgeUrl = (string) ($badgeUrl ?? '');
$hasCustomBadge = $isEdit && trim((string) ($definition['badge_media_path'] ?? '')) !== '';
$defId = $isEdit ? (int) $definition['id'] : 0;
$action = $isEdit
    ? url('back-office/referentiels/qualifications/' . $defId . '/update')
    : url('back-office/referentiels/qualifications/store');
$flashSuccess = \App\Core\Session::getFlash('success');
$flashError = \App\Core\Session::getFlash('error');
$e = static fn (mixed $s): string => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$v = static function (string $key, mixed $default = '') use ($definition): string {
    if (!is_array($definition)) {
        return (string) $default;
    }
    $val = $definition[$key] ?? null;

    return $val === null ? (string) $default : (string) $val;
};
$checked = static fn (string $key): string => (is_array($definition) && !empty($definition[$key])) ? ' checked' : '';
$base = 'back-office/referentiels/qualifications/' . $defId;
$year = (int) date('Y');
$examplePreview = \App\Support\QualificationExamples::numberPreview($v('certificate_number_format'), $v('code', 'SC1'), $year, 42);
?>
<div class="rq" data-rq data-rq-year="<?= $year ?>">
    <header class="rq-top">
        <a href="<?= $e(url('back-office/referentiels/qualifications')) ?>" class="rq-back">
            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M15 6l-6 6 6 6"/></svg>
            Référentiel des qualifications
        </a>
        <div class="rq-top__row">
            <div>
                <p class="rq-kicker">Personnel · Qualifications</p>
                <h1><?= $isEdit ? $e($v('name', 'Qualification')) : 'Nouvelle qualification' ?></h1>
                <p class="rq-lead">Une qualification décrit ce qu’un membre <strong>sait faire</strong> : sa durée de validité, la façon dont elle se valide et le brevet qui l’atteste. Chaque champ est expliqué ; rien n’est obligatoire hormis le code et le nom.</p>
            </div>
            <?php if ($isEdit && empty($definition['archived_at'])): ?>
                <form method="post" action="<?= $e(url($base . '/archive')) ?>" onsubmit="return confirm('Archiver cette qualification ? Les attributions existantes sont conservées.');">
                    <?= \App\Core\Csrf::field() ?>
                    <button class="rq-btn rq-btn--warn" type="submit">Archiver</button>
                </form>
            <?php elseif ($isEdit): ?>
                <span class="rq-pill rq-pill--muted">Archivée</span>
            <?php endif; ?>
        </div>
    </header>

    <?php if ($flashSuccess): ?><p class="rq-flash rq-flash--ok" role="status"><?= $e($flashSuccess) ?></p><?php endif; ?>
    <?php if ($flashError): ?><p class="rq-flash rq-flash--err" role="alert"><?= $e($flashError) ?></p><?php endif; ?>

    <?php if (!$isEdit): ?>
        <section class="rq-examples" aria-labelledby="rq-ex-title">
            <div>
                <h2 id="rq-ex-title">Partir d’un exemple</h2>
                <p>Un clic pré-remplit le formulaire avec une qualification MILSIM courante. Adaptez ensuite ce qui doit l’être : rien n’est enregistré avant « Créer la qualification ».</p>
            </div>
            <div class="rq-examples__list" role="list">
                <?php foreach ($qualificationExamples as $ex): ?>
                    <button type="button" class="rq-chip" role="listitem" data-rq-example="<?= $e($ex['key']) ?>">
                        <b><?= $e($ex['code']) ?></b> <?= $e($ex['label'] !== $ex['code'] ? preg_replace('/^' . preg_quote((string) $ex['code'], '/') . ' — /u', '', (string) $ex['label']) : '') ?>
                    </button>
                <?php endforeach; ?>
            </div>
            <p class="rq-examples__applied" data-rq-example-note hidden></p>
            <script type="application/json" data-rq-examples><?= json_encode($qualificationExamples, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
        </section>
    <?php endif; ?>

    <div class="rq-layout">
        <form method="post" action="<?= $e($action) ?>" class="rq-form" data-rq-form>
            <?= \App\Core\Csrf::field() ?>

            <section class="rq-sec" aria-labelledby="rq-s1">
                <header class="rq-sec__head">
                    <span class="rq-sec__num" aria-hidden="true">1</span>
                    <div>
                        <h2 id="rq-s1">Identité</h2>
                        <p>Comment la qualification s’appelle et comment on la reconnaît dans les listes.</p>
                    </div>
                </header>
                <div class="rq-grid rq-grid--3">
                    <div class="rq-field">
                        <label for="rq-code">Code <span class="rq-req">obligatoire</span></label>
                        <input id="rq-code" name="code" required maxlength="64" autocomplete="off" class="rq-mono" <?= $isEdit ? 'readonly aria-readonly="true"' : '' ?> value="<?= $e($v('code')) ?>" placeholder="SC1" data-rq-code>
                        <p class="rq-help"><?= $isEdit ? 'Figé après la création : il sert de référence stable (brevets, imports, historique).' : 'Identifiant court et stable, en majuscules. Il ne pourra plus changer.' ?> <span class="rq-ex">Ex. SC1, JTAC, PIL-H</span></p>
                    </div>
                    <div class="rq-field rq-span-2">
                        <label for="rq-name">Nom <span class="rq-req">obligatoire</span></label>
                        <input id="rq-name" name="name" required maxlength="150" value="<?= $e($v('name')) ?>" placeholder="Sauvetage au combat niveau 1" data-rq-name>
                        <p class="rq-help">Libellé complet affiché sur la fiche du membre et sur le brevet. <span class="rq-ex">Ex. Sauvetage au combat niveau 1</span></p>
                    </div>
                    <div class="rq-field">
                        <label for="rq-short">Nom court</label>
                        <input id="rq-short" name="short_name" maxlength="40" value="<?= $e($v('short_name')) ?>" placeholder="SC1" data-rq-short>
                        <p class="rq-help">Version abrégée pour les badges, l’ORBAT et les tableaux. <span class="rq-ex">Ex. SC1</span></p>
                    </div>
                    <div class="rq-field rq-span-2">
                        <label for="rq-desc">Description</label>
                        <textarea id="rq-desc" name="description" rows="3" placeholder="Gestes réflexes de tout combattant : garrot, pansement compressif, compte rendu MARCHE." data-rq-desc><?= $e($v('description')) ?></textarea>
                        <p class="rq-help">Ce que la qualification permet de faire et, le cas échéant, ses prérequis. Visible par les membres.</p>
                    </div>
                </div>
            </section>

            <section class="rq-sec" aria-labelledby="rq-s2">
                <header class="rq-sec__head">
                    <span class="rq-sec__num" aria-hidden="true">2</span>
                    <div>
                        <h2 id="rq-s2">Classement</h2>
                        <p>Où ranger la qualification : sert aux filtres, aux statistiques et à la matrice de compétences.</p>
                    </div>
                </header>
                <div class="rq-grid rq-grid--3">
                    <div class="rq-field">
                        <label for="rq-scope">Portée</label>
                        <select id="rq-scope" name="qualification_scope" data-rq-scope>
                            <option value="global"<?= $v('qualification_scope', 'global') === 'global' ? ' selected' : '' ?>>Globale — toute la communauté</option>
                            <option value="unit"<?= $v('qualification_scope') === 'unit' ? ' selected' : '' ?>>Unité — propre à une unité</option>
                        </select>
                        <p class="rq-help">« Globale » est reconnue partout ; « Unité » ne vaut que dans l’unité qui la délivre. <span class="rq-ex">Ex. Chef de groupe : Unité</span></p>
                    </div>
                    <div class="rq-field">
                        <label for="rq-cat">Catégorie</label>
                        <select id="rq-cat" name="category_id" data-rq-category>
                            <option value="">Aucune</option>
                            <?php foreach ($categories as $c): ?>
                                <option value="<?= (int) $c['id'] ?>"<?= (int) $v('category_id') === (int) $c['id'] ? ' selected' : '' ?>><?= $e($c['name'] ?? '') ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p class="rq-help">Domaine : santé, combat, transmissions… <?php if ($categories === []): ?><a href="<?= $e(url('back-office/referentiels/qualifications')) ?>#bo-qual-cat-title">Créer une catégorie</a><?php endif; ?></p>
                    </div>
                    <div class="rq-field">
                        <label for="rq-type">Type</label>
                        <select id="rq-type" name="type_id" data-rq-type>
                            <option value="">Aucun</option>
                            <?php foreach ($types as $t): ?>
                                <option value="<?= (int) $t['id'] ?>"<?= (int) $v('type_id') === (int) $t['id'] ? ' selected' : '' ?>><?= $e($t['name'] ?? '') ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p class="rq-help">Nature : brevet, spécialité, aptitude technique… <?php if ($types === []): ?><a href="<?= $e(url('back-office/referentiels/qualifications')) ?>#bo-qual-type-title">Créer un type</a><?php endif; ?></p>
                    </div>
                </div>
            </section>

            <section class="rq-sec" aria-labelledby="rq-s3">
                <header class="rq-sec__head">
                    <span class="rq-sec__num" aria-hidden="true">3</span>
                    <div>
                        <h2 id="rq-s3">Validité et renouvellement</h2>
                        <p>Combien de temps la qualification reste valable et quand prévenir le membre.</p>
                    </div>
                </header>
                <label class="rq-toggle">
                    <input type="checkbox" name="is_permanent" value="1"<?= $checked('is_permanent') ?> data-rq-permanent>
                    <span class="rq-toggle__ui" aria-hidden="true"></span>
                    <span class="rq-toggle__text"><strong>Permanente</strong><small>Acquise à vie, n’expire jamais (ex. brevet parachutiste, chef de groupe). Les champs de durée ci-dessous sont alors désactivés.</small></span>
                </label>
                <div class="rq-validity" data-rq-validity>
                    <div class="rq-grid rq-grid--3">
                        <div class="rq-field">
                            <label for="rq-validity">Validité (mois)</label>
                            <input id="rq-validity" type="number" min="0" max="600" inputmode="numeric" name="default_validity_months" value="<?= $e($v('default_validity_months')) ?>" placeholder="24" data-rq-months>
                            <p class="rq-help">Durée proposée à chaque attribution pour calculer la date d’expiration. Vide : à saisir au cas par cas. <span class="rq-ex">Ex. 24</span></p>
                        </div>
                        <div class="rq-field">
                            <label for="rq-alert">Alerte avant expiration (jours)</label>
                            <input id="rq-alert" type="number" min="0" max="365" inputmode="numeric" name="alert_before_expiry_days" value="<?= $e($v('alert_before_expiry_days', '30')) ?>" placeholder="30" data-rq-alert>
                            <p class="rq-help">À partir de ce délai, la qualification passe « Expire bientôt ». <span class="rq-ex">Ex. 30</span></p>
                        </div>
                        <div class="rq-field">
                            <label for="rq-grace">Période de grâce (jours)</label>
                            <input id="rq-grace" type="number" min="0" max="365" inputmode="numeric" name="grace_period_days" value="<?= $e($v('grace_period_days')) ?>" placeholder="15" data-rq-grace>
                            <p class="rq-help">Tolérance après l’échéance avant « Expirée », le temps de recycler. <span class="rq-ex">Ex. 15</span></p>
                        </div>
                    </div>
                    <ol class="rq-timeline" aria-label="Cycle de vie d’une attribution" data-rq-timeline>
                        <li><b>Obtention</b><span>jour J</span></li>
                        <li class="is-ok"><b>Valide</b><span data-rq-t-valid>24 mois</span></li>
                        <li class="is-warn"><b>Expire bientôt</b><span data-rq-t-alert>30 j avant</span></li>
                        <li class="is-grace"><b>Grâce</b><span data-rq-t-grace>15 j après</span></li>
                        <li class="is-off"><b>Expirée</b><span>à recycler</span></li>
                    </ol>
                    <label class="rq-toggle">
                        <input type="checkbox" name="renewal_required" value="1"<?= $checked('renewal_required') ?> data-rq-renewal>
                        <span class="rq-toggle__ui" aria-hidden="true"></span>
                        <span class="rq-toggle__text"><strong>Renouvellement requis</strong><small>Un recyclage (stage ou contrôle) est nécessaire pour prolonger la qualification à l’échéance.</small></span>
                    </label>
                </div>
                <div class="rq-field rq-field--inline">
                    <div>
                        <label for="rq-currency">Pratique récente exigée (jours)</label>
                        <p class="rq-help">Facultatif, indépendant de la validité : au-delà de ce délai sans pratique enregistrée, le membre est signalé « non entraîné ». <span class="rq-ex">Ex. JTAC : 90 ; pilote : 60</span></p>
                    </div>
                    <input id="rq-currency" type="number" min="0" max="3650" inputmode="numeric" name="currency_days" value="<?= $e($v('currency_days')) ?>" placeholder="90" data-rq-currency>
                </div>
            </section>

            <section class="rq-sec" aria-labelledby="rq-s4">
                <header class="rq-sec__head">
                    <span class="rq-sec__num" aria-hidden="true">4</span>
                    <div>
                        <h2 id="rq-s4">Validation</h2>
                        <p>Comment un membre obtient la qualification, et si elle comporte plusieurs niveaux.</p>
                    </div>
                </header>
                <div class="rq-toggles">
                    <label class="rq-toggle">
                        <input type="checkbox" name="requires_exam" value="1"<?= $checked('requires_exam') ?> data-rq-exam>
                        <span class="rq-toggle__ui" aria-hidden="true"></span>
                        <span class="rq-toggle__text"><strong>Examen requis</strong><small>Une épreuve (théorique ou pratique) doit être réussie avant l’attribution.</small></span>
                    </label>
                    <label class="rq-toggle">
                        <input type="checkbox" name="requires_panel" value="1"<?= $checked('requires_panel') ?> data-rq-panel>
                        <span class="rq-toggle__ui" aria-hidden="true"></span>
                        <span class="rq-toggle__text"><strong>Jury de validation</strong><small>L’attribution passe par un jury (instructeurs, cadres) plutôt que par un seul validateur.</small></span>
                    </label>
                    <label class="rq-toggle">
                        <input type="checkbox" name="uses_levels" value="1"<?= $checked('uses_levels') ?> data-rq-levels>
                        <span class="rq-toggle__ui" aria-hidden="true"></span>
                        <span class="rq-toggle__text"><strong>Utilise des niveaux</strong><small>La qualification se décline en paliers (ex. Élève pilote → Pilote → Chef de bord). <?= $isEdit ? 'Gérez-les dans la section « Niveaux » plus bas.' : 'Vous les ajouterez juste après la création.' ?></small></span>
                    </label>
                    <label class="rq-toggle" data-rq-progression-wrap>
                        <input type="checkbox" name="enforce_level_progression" value="1"<?= $checked('enforce_level_progression') ?> data-rq-progression>
                        <span class="rq-toggle__ui" aria-hidden="true"></span>
                        <span class="rq-toggle__text"><strong>Progression des niveaux obligatoire</strong><small>Impossible de sauter un palier : il faut détenir le niveau précédent. N’a de sens qu’avec des niveaux.</small></span>
                    </label>
                </div>
            </section>

            <section class="rq-sec" aria-labelledby="rq-s5">
                <header class="rq-sec__head">
                    <span class="rq-sec__num" aria-hidden="true">5</span>
                    <div>
                        <h2 id="rq-s5">Brevet</h2>
                        <p>Le PDF remis au membre à l’attribution, et la façon dont il est numéroté.</p>
                    </div>
                </header>
                <div class="rq-grid rq-grid--2">
                    <div class="rq-field">
                        <label for="rq-tpl">Gabarit de brevet</label>
                        <select id="rq-tpl" name="certificate_template_id">
                            <option value="">Par défaut (Classique)</option>
                            <?php foreach ($templates as $tpl): ?>
                                <option value="<?= (int) $tpl['id'] ?>"<?= (int) $v('certificate_template_id') === (int) $tpl['id'] ? ' selected' : '' ?>><?= $e($tpl['name'] ?? '') ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p class="rq-help">Mise en page du PDF. Voir les modèles vierges :
                            <a href="<?= $e(asset_url('docs/qualification-certificate-templates/template_classique_vierge.pdf')) ?>" target="_blank" rel="noopener noreferrer">Classique</a> ·
                            <a href="<?= $e(asset_url('docs/qualification-certificate-templates/template_moderne_vierge.pdf')) ?>" target="_blank" rel="noopener noreferrer">Moderne</a>
                        </p>
                    </div>
                    <div class="rq-field">
                        <label for="rq-fmt">Format du numéro de brevet</label>
                        <input id="rq-fmt" name="certificate_number_format" maxlength="120" class="rq-mono" placeholder="<?= $e(\App\Support\QualificationExamples::DEFAULT_NUMBER_FORMAT) ?>" value="<?= $e($v('certificate_number_format')) ?>" data-rq-format>
                        <div class="rq-tokens" role="group" aria-label="Insérer une variable">
                            <button type="button" class="rq-token" data-rq-token="{code}">{code}</button>
                            <button type="button" class="rq-token" data-rq-token="{year}">{year}</button>
                            <button type="button" class="rq-token" data-rq-token="{seq}">{seq}</button>
                        </div>
                        <p class="rq-help"><b>{code}</b> le code de la qualification · <b>{year}</b> l’année d’attribution · <b>{seq}</b> un compteur sur 4 chiffres, remis à zéro chaque année. Vide : <span class="rq-mono"><?= $e(\App\Support\QualificationExamples::DEFAULT_NUMBER_FORMAT) ?></span>.</p>
                        <p class="rq-preview">Exemple de numéro : <output class="rq-mono" data-rq-number><?= $e($examplePreview) ?></output></p>
                    </div>
                </div>
            </section>

            <div class="rq-submit">
                <p data-rq-dirty-note><?= $isEdit ? 'Les attributions existantes gardent leurs dates ; les nouvelles règles s’appliquent aux prochaines.' : 'Après la création : insigne, niveaux, prérequis et droits associés.' ?></p>
                <div class="rq-submit__actions">
                    <a class="rq-btn" href="<?= $e(url('back-office/referentiels/qualifications')) ?>">Annuler</a>
                    <button class="rq-btn rq-btn--primary" type="submit"><?= $isEdit ? 'Enregistrer les modifications' : 'Créer la qualification' ?></button>
                </div>
            </div>
        </form>

        <aside class="rq-aside" aria-label="Aperçu">
            <div class="rq-card rq-summary" aria-live="polite">
                <p class="rq-summary__kicker">Aperçu de la fiche</p>
                <div class="rq-summary__id">
                    <?php if ($hasCustomBadge): ?>
                        <img src="<?= $e($badgeUrl) ?>" alt="" class="rq-summary__badge">
                    <?php else: ?>
                        <span class="rq-summary__mono" data-rq-s-mono><?= $e(mb_substr($v('short_name') !== '' ? $v('short_name') : ($v('code') !== '' ? $v('code') : '?'), 0, 5)) ?></span>
                    <?php endif; ?>
                    <div>
                        <strong data-rq-s-name><?= $e($v('name') !== '' ? $v('name') : 'Nom de la qualification') ?></strong>
                        <span class="rq-mono" data-rq-s-code><?= $e($v('code') !== '' ? $v('code') : 'CODE') ?></span>
                    </div>
                </div>
                <p class="rq-summary__class" data-rq-s-class></p>
                <p class="rq-summary__validity" data-rq-s-validity></p>
                <ul class="rq-summary__flags" data-rq-s-flags></ul>
                <p class="rq-summary__number">Brevet n° <span class="rq-mono" data-rq-s-number><?= $e($examplePreview) ?></span></p>
            </div>
            <div class="rq-card rq-tips">
                <p class="rq-summary__kicker">Bon à savoir</p>
                <ul>
                    <li><b>Validité ≠ pratique récente.</b> Un JTAC peut être valide jusqu’en 2028 mais non entraîné après 90 jours sans mission.</li>
                    <li><b>Archiver</b> retire la qualification des nouvelles attributions sans effacer l’historique.</li>
                    <li>Les statuts « Expire bientôt » et « Expirée » sont recalculés chaque jour à partir des dates de chaque attribution.</li>
                </ul>
            </div>
        </aside>
    </div>

    <?php if ($isEdit): ?>
    <div class="rq-more">
        <section id="badge" class="rq-card" aria-labelledby="rq-badge-t">
            <h2 id="rq-badge-t">Insigne</h2>
            <p class="rq-help">Image affichée sur la fiche du membre et sur le brevet. PNG ou WebP à fond transparent de préférence.</p>
            <div class="rq-badge">
                <img src="<?= $e($badgeUrl !== '' ? $badgeUrl : url('assets/img/qualification-badge-default.svg')) ?>" alt="Insigne actuel" class="rq-badge__img">
                <form method="post" enctype="multipart/form-data" action="<?= $e(url($base . '/badge')) ?>" class="rq-inline">
                    <?= \App\Core\Csrf::field() ?>
                    <input type="file" name="badge" accept=".png,.webp,.svg,image/png,image/webp,image/svg+xml" required>
                    <button class="rq-btn" type="submit">Téléverser</button>
                    <p class="rq-help">PNG, WebP ou SVG — 2 Mo max.</p>
                </form>
            </div>
        </section>

        <section id="niveaux" class="rq-card" aria-labelledby="rq-lvl-t">
            <h2 id="rq-lvl-t">Niveaux <span class="rq-count"><?= count($levels) ?></span></h2>
            <p class="rq-help">Paliers successifs, du plus simple au plus avancé. <span class="rq-ex">Ex. Élève pilote → Pilote → Chef de bord</span><?= empty($definition['uses_levels']) ? ' — activez « Utilise des niveaux » pour qu’ils soient proposés à l’attribution.' : '' ?></p>
            <ul class="rq-list">
                <?php foreach ($levels as $lvl): ?>
                    <li>
                        <span><strong><?= $e($lvl['name'] ?? '') ?></strong><?php if (!empty($lvl['short_name'])): ?> <span class="rq-muted">(<?= $e($lvl['short_name']) ?>)</span><?php endif; ?></span>
                        <form method="post" action="<?= $e(url($base . '/niveaux/' . (int) $lvl['id'] . '/supprimer')) ?>">
                            <?= \App\Core\Csrf::field() ?>
                            <button class="rq-link rq-link--danger" type="submit">Retirer</button>
                        </form>
                    </li>
                <?php endforeach; ?>
                <?php if ($levels === []): ?><li class="rq-muted">Aucun niveau.</li><?php endif; ?>
            </ul>
            <form method="post" action="<?= $e(url($base . '/niveaux')) ?>" class="rq-inline">
                <?= \App\Core\Csrf::field() ?>
                <input name="name" required placeholder="Nom (ex. Pilote)">
                <input name="short_name" placeholder="Court (ex. P2)" class="rq-w-sm">
                <select name="previous_level_id">
                    <option value="">Niveau précédent</option>
                    <?php foreach ($levels as $lvl): ?>
                        <option value="<?= (int) $lvl['id'] ?>"><?= $e($lvl['name'] ?? '') ?></option>
                    <?php endforeach; ?>
                </select>
                <button class="rq-btn" type="submit">Ajouter</button>
            </form>
        </section>

        <section id="prerequis" class="rq-card" aria-labelledby="rq-pre-t">
            <h2 id="rq-pre-t">Prérequis <span class="rq-count"><?= count($prerequisites) ?></span></h2>
            <p class="rq-help">Qualifications à détenir avant celle-ci. <span class="rq-ex">Ex. SC1 pour obtenir SC2</span> « Recyclage » : exigé seulement pour le renouvellement.</p>
            <ul class="rq-list">
                <?php foreach ($prerequisites as $p): ?>
                    <li>
                        <span><?= $e($p['required_name'] ?? '') ?> <span class="rq-pill"><?= $e(($p['requirement_type'] ?? '') === 'recyclage' ? 'Recyclage' : 'Obtention') ?></span></span>
                        <form method="post" action="<?= $e(url($base . '/prerequis/' . (int) $p['id'] . '/supprimer')) ?>">
                            <?= \App\Core\Csrf::field() ?>
                            <button class="rq-link rq-link--danger" type="submit">Retirer</button>
                        </form>
                    </li>
                <?php endforeach; ?>
                <?php if ($prerequisites === []): ?><li class="rq-muted">Aucun prérequis.</li><?php endif; ?>
            </ul>
            <form method="post" action="<?= $e(url($base . '/prerequis')) ?>" class="rq-inline">
                <?= \App\Core\Csrf::field() ?>
                <select name="required_qualification_id" required>
                    <option value="">Qualification requise</option>
                    <?php foreach ($allDefinitions as $d): if ((int) $d['id'] === $defId) { continue; } ?>
                        <option value="<?= (int) $d['id'] ?>"><?= $e($d['name'] ?? '') ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="requirement_type">
                    <option value="obtention">Obtention</option>
                    <option value="recyclage">Recyclage</option>
                </select>
                <button class="rq-btn" type="submit">Ajouter</button>
            </form>
        </section>

        <section id="champs" class="rq-card" aria-labelledby="rq-cf-t">
            <h2 id="rq-cf-t">Champs personnalisés <span class="rq-count"><?= count($customFields) ?></span></h2>
            <p class="rq-help">Informations supplémentaires saisies à l’attribution. <span class="rq-ex">Ex. « Heures de vol » (Nombre), « Arme de dotation » (Texte court)</span></p>
            <ul class="rq-list">
                <?php foreach ($customFields as $cf): ?>
                    <li>
                        <span><?= $e($cf['name'] ?? '') ?> <span class="rq-mono rq-muted"><?= $e($cf['code'] ?? '') ?></span></span>
                        <form method="post" action="<?= $e(url($base . '/champs/' . (int) $cf['id'] . '/supprimer')) ?>">
                            <?= \App\Core\Csrf::field() ?>
                            <button class="rq-link rq-link--danger" type="submit">Retirer</button>
                        </form>
                    </li>
                <?php endforeach; ?>
                <?php if ($customFields === []): ?><li class="rq-muted">Aucun champ.</li><?php endif; ?>
            </ul>
            <form method="post" action="<?= $e(url($base . '/champs')) ?>" class="rq-inline">
                <?= \App\Core\Csrf::field() ?>
                <input name="name" required placeholder="Libellé">
                <input name="code" required placeholder="CODE" class="rq-mono rq-w-sm">
                <select name="field_type">
                    <option value="text_short">Texte court</option>
                    <option value="text_long">Texte long</option>
                    <option value="number">Nombre</option>
                    <option value="date">Date</option>
                    <option value="boolean">Oui / Non</option>
                    <option value="url">Lien / document</option>
                </select>
                <button class="rq-btn" type="submit">Ajouter</button>
            </form>
        </section>

        <section id="droits" class="rq-card" aria-labelledby="rq-dr-t">
            <h2 id="rq-dr-t">Droits accordés à l’obtention <span class="rq-count"><?= count($permissionGrants) ?></span></h2>
            <p class="rq-help">Permissions données automatiquement aux détenteurs. <span class="rq-ex">Ex. trainings.create pour un instructeur</span> Les accès d’administration de la plateforme sont exclus.</p>
            <ul class="rq-list">
                <?php foreach ($permissionGrants as $g): ?>
                    <li>
                        <span class="rq-mono"><?= $e($g['permission_code'] ?? '') ?></span>
                        <form method="post" action="<?= $e(url($base . '/droits/' . (int) $g['id'] . '/supprimer')) ?>">
                            <?= \App\Core\Csrf::field() ?>
                            <button class="rq-link rq-link--danger" type="submit">Retirer</button>
                        </form>
                    </li>
                <?php endforeach; ?>
                <?php if ($permissionGrants === []): ?><li class="rq-muted">Aucun droit lié.</li><?php endif; ?>
            </ul>
            <form method="post" action="<?= $e(url($base . '/droits')) ?>" class="rq-inline">
                <?= \App\Core\Csrf::field() ?>
                <input name="permission_code" required placeholder="ex. trainings.create" class="rq-mono">
                <button class="rq-btn" type="submit">Lier</button>
            </form>
        </section>

        <section class="rq-card rq-card--wide" aria-labelledby="rq-hold-t">
            <div class="rq-card__head">
                <h2 id="rq-hold-t">Détenteurs <span class="rq-count"><?= count($holders) ?></span></h2>
                <a href="<?= $e(url('back-office/referentiels/qualifications/attribuer')) ?>?definition_id=<?= $defId ?>" class="rq-btn rq-btn--primary">Attribuer cette qualification</a>
            </div>
            <div class="rq-table-wrap">
                <table class="rq-table">
                    <thead>
                        <tr><th>Personnel</th><th>Niveau</th><th>Obtenue</th><th>Expire</th><th></th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($holders as $hd): ?>
                        <tr>
                            <td><?= $e(($hd['display_name'] ?? '') ?: ($hd['username'] ?? '')) ?></td>
                            <td><?= $e($hd['level_name'] ?? $hd['level'] ?? '—') ?></td>
                            <td><?= $e($hd['obtained_at'] ?? '—') ?></td>
                            <td><?= $e($hd['expires_at'] ?? '—') ?></td>
                            <td><a href="<?= $e(url('back-office/ressources/effectifs/membres/' . (int) ($hd['user_id'] ?? 0))) ?>#qualifications">Fiche</a></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($holders === []): ?>
                        <tr><td colspan="5" class="rq-muted">Aucun détenteur pour le moment.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
    <?php endif; ?>
</div>
<script src="<?= $e(asset_url('assets/js/referentiel-qualifications.js')) ?>" defer></script>
