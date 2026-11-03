<?php
declare(strict_types=1);

/**
 * Bloc « Comment l'obtenir » et « Prérequis et affichage » du formulaire d'une qualification.
 *
 * @var array<string, mixed>|null $obtention
 */
$ob = is_array($obtention ?? null) ? $obtention : null;
if ($ob === null) {
    return;
}
$e = static fn (mixed $s): string => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$st = is_array($ob['settings'] ?? null) ? $ob['settings'] : \App\Services\Personnel\QualificationAwardRuleService::defaults();
$rule = is_array($st['rule'] ?? null) ? $st['rule'] : [];
$ruleJson = json_encode($rule !== [] ? $rule : new stdClass(), JSON_UNESCAPED_UNICODE) ?: '{}';
$on = static fn (string $k): string => !empty($st[$k]) ? ' checked' : '';
$cat = \App\Support\QualificationCriteriaCatalog::class;
$grouped = static function (array $opts): array {
    $out = [];
    foreach ($opts as [$value, $label, $group]) {
        $out[$group][] = [$value, $label];
    }

    return $out;
};
$aircraft = $grouped(is_array($ob['aircraft'] ?? null) ? $ob['aircraft'] : []);
$items = $grouped(is_array($ob['items'] ?? null) ? $ob['items'] : []);
$courses = is_array($ob['courses'] ?? null) ? $ob['courses'] : [];
$builtInCourses = is_array($ob['builtInCourses'] ?? null) ? $ob['builtInCourses'] : [];
$studioCourses = is_array($ob['studioCourses'] ?? null) ? $ob['studioCourses'] : [];
$otherDefs = is_array($ob['definitions'] ?? null) ? $ob['definitions'] : [];
$grades = is_array($ob['grades'] ?? null) ? $ob['grades'] : [];
$jobs = is_array($ob['jobs'] ?? null) ? $ob['jobs'] : [];
$sessionCats = is_array($ob['sessionCategories'] ?? null) ? $ob['sessionCategories'] : [];
?>
<section class="rq-sec" id="obtention" aria-labelledby="rq-s6">
    <header class="rq-sec__head">
        <span class="rq-sec__num" aria-hidden="true">6</span>
        <div>
            <h2 id="rq-s6">Comment l’obtenir</h2>
            <p>Un ou plusieurs modes, qui se cumulent : une qualification automatique peut aussi être délivrée à la main.</p>
        </div>
    </header>
    <input type="hidden" name="obtention_form" value="1">
    <?php if (empty($ob['ready'])): ?>
        <p class="rq-flash rq-flash--err" role="status">Les modes d’obtention arrivent avec la prochaine mise à jour du site. En attendant, la qualification se délivre à la main.</p>
    <?php endif; ?>

    <ul class="rq-modes" aria-label="Les modes d’obtention">
        <li><b>À la main</b><span>Un instructeur ou un gestionnaire la délivre depuis la matrice des effectifs ou la fiche de la qualification, avec la date et un commentaire (preuve, contexte).</span></li>
        <li><b>Formation Academy</b><span>Ajoutez la condition « Formation Academy réussie » : l’attestation délivrée par l’Academy donne la qualification, et son échéance suit celle de l’attestation.</span></li>
        <li><b>Automatique</b><span>Seuils mesurés en jeu (heures de jeu, heures de vol par appareil, missions, présences, sauts…) : le site vérifie toutes les heures et délivre dès qu’ils sont atteints.</span></li>
    </ul>

    <div class="rq-toggles">
        <label class="rq-toggle">
            <input type="checkbox" name="allow_manual" value="1"<?= $on('allow_manual') ?>>
            <span class="rq-toggle__ui" aria-hidden="true"></span>
            <span class="rq-toggle__text"><strong>Délivrée par un responsable</strong><small>Délivrance, mise en formation et retrait à la main, avec un commentaire. Décochée : seule la règle automatique la donne.</small></span>
        </label>
        <label class="rq-toggle">
            <input type="checkbox" name="auto_enabled" value="1"<?= $on('auto_enabled') ?> data-qr-auto>
            <span class="rq-toggle__ui" aria-hidden="true"></span>
            <span class="rq-toggle__text"><strong>Obtention automatique ou par l’Academy</strong><small>Délivrée dès que les conditions ci-dessous sont remplies. Une qualification retirée à la main n’est jamais redonnée toute seule.</small></span>
        </label>
    </div>

    <div class="rq-rule" data-qr-builder>
        <div class="rq-grid rq-grid--2">
            <div class="rq-field">
                <label for="rq-auto-mode">Quand les conditions sont remplies</label>
                <select id="rq-auto-mode" name="auto_mode">
                    <option value="direct"<?= ($st['auto_mode'] ?? '') !== 'propose' ? ' selected' : '' ?>>La qualification est délivrée</option>
                    <option value="propose"<?= ($st['auto_mode'] ?? '') === 'propose' ? ' selected' : '' ?>>Elle est proposée, un responsable valide</option>
                </select>
                <p class="rq-help">« Proposée » : la fiche de la qualification liste les membres à valider.</p>
            </div>
            <div class="rq-field">
                <label for="rq-root-op">Il faut remplir</label>
                <select id="rq-root-op" data-qr-root-op>
                    <option value="all">tous les blocs (ET)</option>
                    <option value="any">au moins un bloc (OU)</option>
                </select>
                <p class="rq-help">Chaque bloc regroupe des conditions liées par ET ou par OU. <span class="rq-ex">Ex. bloc 1 : 20 h de vol sur UH-60 ; bloc 2 : formation Academy OU 10 missions</span></p>
            </div>
        </div>
        <div class="rq-rule__groups" data-qr-groups></div>
        <p class="rq-rule__empty" data-qr-empty>Aucune condition : ajoutez un bloc pour définir ce qui donne la qualification.</p>
        <button type="button" class="rq-btn" data-qr-add-group>Ajouter un bloc de conditions</button>
        <input type="hidden" name="rule_json" value="<?= $e($ruleJson) ?>" data-qr-json>

        <template data-qr-tpl-group>
            <div class="rq-rule__group" data-qr-group>
                <div class="rq-rule__ghead">
                    <span class="rq-rule__gname" data-qr-gname>Bloc</span>
                    <label class="rq-rule__gop">
                        <span class="sr-only">Lien entre les conditions du bloc</span>
                        <select data-qr-group-op>
                            <option value="all">toutes ces conditions (ET)</option>
                            <option value="any">au moins une (OU)</option>
                        </select>
                    </label>
                    <button type="button" class="rq-link rq-link--danger" data-qr-del-group>Retirer le bloc</button>
                </div>
                <ul class="rq-rule__items" data-qr-items></ul>
                <button type="button" class="rq-link" data-qr-add-item>+ Ajouter une condition</button>
            </div>
        </template>
        <template data-qr-tpl-item>
            <li class="rq-rule__item" data-qr-item>
                <div class="rq-rule__row">
                    <label><span class="sr-only">Type de condition</span>
                        <select data-qr-type>
                            <option value="metric">Seuil mesuré en jeu</option>
                            <option value="academy">Formation Academy réussie</option>
                            <option value="qualification">Autre qualification détenue</option>
                            <option value="attestation">Attestée par un instructeur</option>
                        </select>
                    </label>
                    <span class="rq-rule__metric" data-qr-when="attestation">
                        <label><span class="sr-only">Condition à attester</span>
                            <select data-qr-attest-key>
                                <?php foreach ($cat::ATTESTATIONS as $key => $a): ?>
                                    <option value="<?= $e($key) ?>" data-label="<?= $e($a['help']) ?>" data-count="<?= $a['count'] ? '1' : '0' ?>" data-unit="<?= $e($a['unit']) ?>"><?= $e($a['label']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label class="rq-rule__min" data-qr-attest-min-wrap>au moins <input type="number" min="0" step="1" inputmode="decimal" data-qr-attest-min aria-label="Nombre attesté"> <span data-qr-attest-unit></span></label>
                        <label><span class="sr-only">Intitulé de l’épreuve</span><input type="text" maxlength="160" data-qr-attest-label placeholder="Intitulé (ex. tir de qualification à 300 m)"></label>
                    </span>
                    <span class="rq-rule__metric" data-qr-when="metric">
                        <label><span class="sr-only">Donnée mesurée</span>
                            <select data-qr-metric>
                                <?php foreach ($cat::METRICS as $key => $m): ?>
                                    <option value="<?= $e($key) ?>" data-label="<?= $e($m['help']) ?>" data-scope="<?= $e($m['scope']) ?>" data-window="<?= $m['window'] ? '1' : '0' ?>" data-step="<?= $e($m['step']) ?>" data-unit="<?= $e($m['unit']) ?>"><?= $e($m['label']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label data-qr-scope-wrap><span class="sr-only">Périmètre</span><select data-qr-scope></select></label>
                        <label class="rq-rule__min">au moins <input type="number" min="0" step="0.5" inputmode="decimal" data-qr-min aria-label="Seuil"> <span data-qr-unit></span></label>
                        <label data-qr-window-wrap><span class="sr-only">Période</span>
                            <select data-qr-window>
                                <option value="0">depuis toujours</option>
                                <option value="30">sur les 30 derniers jours</option>
                                <option value="90">sur les 90 derniers jours</option>
                                <option value="180">sur les 180 derniers jours</option>
                                <option value="365">sur les 365 derniers jours</option>
                            </select>
                        </label>
                    </span>
                    <label data-qr-when="academy"><span class="sr-only">Formation</span>
                        <select data-qr-course>
                            <?php if ($courses === [] && $builtInCourses === [] && $studioCourses === []): ?><option value="">Aucune formation dans votre Academy</option><?php endif; ?>
                            <?php if ($courses !== []): ?><optgroup label="Formations de la communauté">
                            <?php foreach ($courses as $cid => $title): ?>
                                <option value="<?= (int) $cid ?>"><?= $e($title) ?></option>
                            <?php endforeach; ?>
                            </optgroup><?php endif; ?>
                            <?php if ($studioCourses !== []): ?><optgroup label="Cours du Studio de l’Academy">
                            <?php foreach ($studioCourses as $courseKey => $title): ?>
                                <option value="key:<?= $e((string) $courseKey) ?>"><?= $e($title) ?></option>
                            <?php endforeach; ?>
                            </optgroup><?php endif; ?>
                            <?php if ($builtInCourses !== []): ?><optgroup label="Cours intégrés de l’Academy">
                            <?php foreach ($builtInCourses as $courseKey => $title): ?>
                                <option value="key:<?= $e((string) $courseKey) ?>"><?= $e($title) ?></option>
                            <?php endforeach; ?>
                            </optgroup><?php endif; ?>
                        </select>
                    </label>
                    <label data-qr-when="qualification"><span class="sr-only">Qualification</span>
                        <select data-qr-def>
                            <?php if ($otherDefs === []): ?><option value="">Aucune autre qualification</option><?php endif; ?>
                            <?php foreach ($otherDefs as $did => $dname): ?>
                                <option value="<?= (int) $did ?>"><?= $e($dname) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <button type="button" class="rq-rule__del" data-qr-del-item aria-label="Retirer la condition" title="Retirer la condition">×</button>
                </div>
                <p class="rq-help" data-qr-help></p>
            </li>
        </template>

        <div hidden>
            <?php foreach (['h', 'sessions', 'missions', 'présences', 'sauts', 'km', 'comptes rendus', 'jours'] as $u): ?>
                <span data-qr-unit-label="<?= $e($u) ?>"><?= $e($u) ?></span>
            <?php endforeach; ?>
            <select data-qr-scopes="playtime">
                <?php foreach ($cat::PLAYTIME_SCOPES as $v => $l): ?><option value="<?= $e($v) ?>"><?= $e($l) ?></option><?php endforeach; ?>
            </select>
            <select data-qr-scopes="flight_condition">
                <?php foreach ($cat::FLIGHT_CONDITIONS as $v => $l): ?><option value="<?= $e($v) ?>"><?= $e($l) ?></option><?php endforeach; ?>
            </select>
            <select data-qr-scopes="event_type">
                <?php foreach ($cat::EVENT_TYPES as $v => $l): ?><option value="<?= $e($v) ?>"><?= $e($l) ?></option><?php endforeach; ?>
            </select>
            <select data-qr-scopes="session_category">
                <option value="">Toutes les catégories</option>
                <?php foreach ($sessionCats as $c): ?><option value="<?= $e($c) ?>"><?= $e($c) ?></option><?php endforeach; ?>
            </select>
            <select data-qr-scopes="aircraft">
                <?php foreach ($aircraft[''] ?? [] as [$v, $l]): ?><option value="<?= $e($v) ?>"><?= $e($l) ?></option><?php endforeach; ?>
                <?php if (!empty($aircraft['family'])): ?>
                <optgroup label="Famille d’appareils">
                    <?php foreach ($aircraft['family'] as [$v, $l]): ?><option value="<?= $e($v) ?>"><?= $e($l) ?></option><?php endforeach; ?>
                </optgroup>
                <?php endif; ?>
                <?php if (!empty($aircraft['group'])): ?>
                <optgroup label="Famille d’emploi (transport, attaque…)">
                    <?php foreach ($aircraft['group'] as [$v, $l]): ?><option value="<?= $e($v) ?>"><?= $e($l) ?></option><?php endforeach; ?>
                </optgroup>
                <?php endif; ?>
                <?php if (!empty($aircraft['class'])): ?>
                <optgroup label="Appareil précis vu en jeu">
                    <?php foreach ($aircraft['class'] as [$v, $l]): ?><option value="<?= $e($v) ?>"><?= $e($l) ?></option><?php endforeach; ?>
                </optgroup>
                <?php endif; ?>
            </select>
            <select data-qr-scopes="item">
                <?php if (!empty($items['kind'])): ?>
                <optgroup label="Famille d’objets">
                    <?php foreach ($items['kind'] as [$v, $l]): ?><option value="<?= $e($v) ?>"><?= $e($l) ?></option><?php endforeach; ?>
                </optgroup>
                <?php endif; ?>
                <?php if (!empty($items['class'])): ?>
                <optgroup label="Objet précis vu en jeu">
                    <?php foreach ($items['class'] as [$v, $l]): ?><option value="<?= $e($v) ?>"><?= $e($l) ?></option><?php endforeach; ?>
                </optgroup>
                <?php endif; ?>
            </select>
        </div>
        <p class="rq-help">Seuls les critères que le site reçoit vraiment sont proposés. Les heures de vol comptent celles relevées par le mod et les vols du carnet de vol contresignés ; les périodes glissantes ne s’appliquent qu’aux données datées.</p>
    </div>
</section>

<section class="rq-sec" aria-labelledby="rq-s7">
    <header class="rq-sec__head">
        <span class="rq-sec__num" aria-hidden="true">7</span>
        <div>
            <h2 id="rq-s7">Prérequis et affichage</h2>
            <p>Ce qu’il faut avant de l’obtenir, et où elle se voit.</p>
        </div>
    </header>
    <div class="rq-grid rq-grid--3">
        <div class="rq-field">
            <label for="rq-pre-grade">Grade minimum</label>
            <select id="rq-pre-grade" name="prereq_grade_id">
                <option value="">Aucun</option>
                <?php foreach ($grades as $g): ?>
                    <option value="<?= (int) $g['id'] ?>"<?= (int) ($st['prereq_grade_id'] ?? 0) === (int) $g['id'] ? ' selected' : '' ?>><?= $e(trim((string) ($g['label_short'] ?? '')) . ' — ' . trim((string) ($g['label_long'] ?? ''))) ?></option>
                <?php endforeach; ?>
            </select>
            <p class="rq-help">Ce grade ou un grade plus élevé.</p>
        </div>
        <div class="rq-field">
            <label for="rq-pre-job">Fonction demandée</label>
            <select id="rq-pre-job" name="prereq_job_role_id">
                <option value="">Aucune</option>
                <?php foreach ($jobs as $j): ?>
                    <option value="<?= (int) $j['id'] ?>"<?= (int) ($st['prereq_job_role_id'] ?? 0) === (int) $j['id'] ? ' selected' : '' ?>><?= $e($j['label'] ?? '') ?></option>
                <?php endforeach; ?>
            </select>
            <p class="rq-help">Emploi tenu sur le dossier du membre.</p>
        </div>
        <div class="rq-field">
            <label for="rq-min-unit">Besoin minimum par unité</label>
            <input id="rq-min-unit" type="number" min="0" max="999" inputmode="numeric" name="min_holders_per_unit" value="<?= $e($st['min_holders_per_unit'] ?? '') ?>" placeholder="2">
            <p class="rq-help">Titulaires souhaités dans chaque unité : la matrice signale les unités en dessous.</p>
        </div>
    </div>
    <p class="rq-help">Sans ces prérequis, la qualification n’est jamais délivrée automatiquement ; à la main, le responsable est prévenu et peut passer outre. Les qualifications à détenir avant celle-ci se règlent dans la section « Prérequis » de la fiche.</p>
    <div class="rq-toggles">
        <label class="rq-toggle">
            <input type="checkbox" name="show_on_profile" value="1"<?= $on('show_on_profile') ?>>
            <span class="rq-toggle__ui" aria-hidden="true"></span>
            <span class="rq-toggle__text"><strong>Visible sur la page publique du joueur</strong><small>Si le membre a ouvert sa page publique et y affiche ses qualifications.</small></span>
        </label>
        <label class="rq-toggle">
            <input type="checkbox" name="show_on_orbat" value="1"<?= $on('show_on_orbat') ?>>
            <span class="rq-toggle__ui" aria-hidden="true"></span>
            <span class="rq-toggle__text"><strong>Visible sur la fiche opérateur ATAK</strong><small>La fiche affichée en jeu quand on consulte un opérateur sur l’ORBAT tactique.</small></span>
        </label>
    </div>
</section>
<script src="<?= $e(asset_url('assets/js/qualification-rule-builder.js')) ?>" defer></script>
