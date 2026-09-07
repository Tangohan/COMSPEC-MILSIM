<?php
declare(strict_types=1);

/** @var array{enabled: bool, optional: bool, stages: list<string>, recruitment_tracks: list<string>, eligibility: array<string,mixed>} $rpConfig */
/** @var array<string, mixed> $rpEligibility */
/** @var string $immersionFormAction */

$cfg = is_array($rpConfig ?? null) ? $rpConfig : [];
$eligibility = is_array($rpEligibility ?? null) ? $rpEligibility : [];
$formAction = (string) ($immersionFormAction ?? url('back-office/roleplay/immersion'));
$enabled = !empty($cfg['enabled']);
$optional = !empty($cfg['optional']);
$stages = is_array($cfg['stages'] ?? null) ? $cfg['stages'] : [];
$tracks = is_array($cfg['recruitment_tracks'] ?? null) ? $cfg['recruitment_tracks'] : [];
if ($stages === []) {
    $stages = ['Pré-qualification', 'Tutorat', 'Validation', 'Intégration active'];
}
if ($tracks === []) {
    $tracks = ['Infanterie', 'Support', 'Commandement'];
}

$minCompleteness = max(0, min(100, (int) ($eligibility['min_completeness'] ?? 50)));
$minReadiness = max(0, min(100, (int) ($eligibility['min_readiness'] ?? 30)));
$requireUnit = !empty($eligibility['require_unit']);
$requireCallsign = !empty($eligibility['require_callsign']);
$requireTutor = !empty($eligibility['require_tutor']);

$readyBits = [
    'la complétude du dossier atteint ' . $minCompleteness . ' %',
    'la disponibilité atteint ' . $minReadiness . ' %',
];
if ($requireUnit) {
    $readyBits[] = 'une unité est renseignée';
}
if ($requireCallsign) {
    $readyBits[] = 'un indicatif radio est renseigné';
}
if ($requireTutor) {
    $readyBits[] = 'un tuteur est désigné';
}
$readySentence = 'Un dossier est marqué prêt lorsque ' . implode(', ', $readyBits) . '.';

$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$followupUrl = url('back-office/roleplay-followup');
$deadlinesUrl = url('back-office/roleplay-followup/echeances');
$personnelUrl = url('personnel');
$atakRoleplayUrl = url('admin/atak/roleplay');
$phaseRulesUrl = url('back-office/roleplay/regles-phases');
$sessionsUrl = url('back-office/roleplay/sessions');

$bilans = is_array($cfg['bilans'] ?? null) ? $cfg['bilans'] : [];
$interview = is_array($cfg['interview'] ?? null) ? $cfg['interview'] : [];
$medical = is_array($cfg['medical'] ?? null) ? $cfg['medical'] : [];
$rotation = is_array($cfg['rotation'] ?? null) ? $cfg['rotation'] : [];
$probation = is_array($cfg['probation'] ?? null) ? $cfg['probation'] : [];
$notifications = is_array($cfg['notifications'] ?? null) ? $cfg['notifications'] : [];
$stageBilanTypes = is_array($cfg['stage_bilan_types'] ?? null) ? $cfg['stage_bilan_types'] : [];
$stageBilanLabels = [];
foreach ($stageBilanTypes as $row) {
    if (is_array($row)) {
        $lab = trim((string) ($row['label'] ?? ''));
        if ($lab !== '') {
            $stageBilanLabels[] = $lab;
        }
    } elseif (is_string($row) && trim($row) !== '') {
        $stageBilanLabels[] = trim($row);
    }
}
if ($stageBilanLabels === []) {
    $stageBilanLabels = ['Suivi périodique', 'Fin de période d’essai', 'Bilan annuel', 'Autre'];
}
$bilansEnabled = array_key_exists('enabled', $bilans) ? !empty($bilans['enabled']) : true;
$interviewVisible = array_key_exists('visible', $interview) ? !empty($interview['visible']) : true;
$medicalVisible = array_key_exists('visible', $medical) ? !empty($medical['visible']) : true;
$rotationVisible = array_key_exists('visible', $rotation) ? !empty($rotation['visible']) : true;
$rotationRequireInterview = array_key_exists('require_interview', $rotation) ? !empty($rotation['require_interview']) : true;
$probationAlert = array_key_exists('alert_enabled', $probation) ? !empty($probation['alert_enabled']) : true;
$emailReminders = array_key_exists('email_reminders', $notifications) ? !empty($notifications['email_reminders']) : true;
?>
<div class="bo-imm bo-community-settings">

    <section class="bo-imm__hero ath-rise" aria-labelledby="bo-imm-hero-title">
        <div class="bo-imm__hero-copy">
            <span class="bo-imm__eyebrow">Roleplay · Arrivée dans l’unité</span>
            <h2 id="bo-imm-hero-title">Suivre l’arrivée d’un membre, pas le jeu</h2>
            <p>
                Cette page décide <strong>comment votre communauté suit un nouveau membre</strong> :
                étapes du parcours, filière choisie, tuteur, et ce qui rend un dossier « prêt ».
                Une fois enregistré, le staff voit ces choix sur chaque fiche et dans le bureau de suivi.
            </p>
            <p>
                Ce n’est pas ici que l’on règle l’immersion en session : les pannes de liaison et les aides de jeu
                se configurent ailleurs, pour ATAK et Overwatch.
            </p>
            <div class="bo-imm__hero-actions">
                <a href="<?= $h($followupUrl) ?>" class="ath-btn ath-btn--solid">Ouvrir le bureau de suivi</a>
                <a href="<?= $h($deadlinesUrl) ?>" class="ath-btn">Voir les échéances</a>
            </div>
        </div>
        <aside class="bo-imm__orient" aria-label="Ce que vous pouvez faire ici">
            <span class="bo-imm__orient-title">Après enregistrement, vous verrez</span>
            <ul>
                <li><strong>Sur la fiche du membre</strong><span>Étape, filière, tuteur et indicateur « dossier prêt »</span></li>
                <li><strong>Dans le bureau de suivi</strong><span>Tous les dossiers, triés par prochaine échéance</span></li>
                <li><strong>Dans les menus du staff</strong><span>Vos étapes et filières, dans l’ordre saisi</span></li>
            </ul>
        </aside>
    </section>

    <section class="bo-imm__caps" aria-label="Où se trouve chaque capacité">
        <article class="bo-imm__cap bo-imm__cap--here">
            <span class="bo-imm__cap-kicker">Cette page</span>
            <h3>Parcours d’immersion</h3>
            <p>Activer le suivi, nommer les étapes, choisir les filières et fixer ce qui rend un dossier prêt.</p>
        </article>
        <article class="bo-imm__cap">
            <span class="bo-imm__cap-kicker">Suivi quotidien</span>
            <h3>Bureau de suivi</h3>
            <p>Attribuer un tuteur, avancer une étape, noter un bilan et voir qui est déjà suivi.</p>
            <a href="<?= $h($followupUrl) ?>">Ouvrir le bureau</a>
        </article>
        <article class="bo-imm__cap">
            <span class="bo-imm__cap-kicker">Dates</span>
            <h3>Échéances</h3>
            <p>Entretiens, visites médicales et rotations : ce qui est en retard, ce qui arrive.</p>
            <a href="<?= $h($deadlinesUrl) ?>">Voir le calendrier</a>
        </article>
        <article class="bo-imm__cap">
            <span class="bo-imm__cap-kicker">Parcours RH</span>
            <h3>Règles de passage</h3>
            <p>Décidez ce qu’un membre doit avoir fait pour passer à l’étape suivante, et si un responsable valide ou si le passage se fait tout seul.</p>
            <a href="<?= $h($phaseRulesUrl) ?>">Configurer le parcours</a>
        </article>
        <article class="bo-imm__cap">
            <span class="bo-imm__cap-kicker">En session</span>
            <h3>Sessions Arma</h3>
            <p>Types de sessions, catégories d’heures et pointage. Distinct du suivi d’arrivée ci-dessus.</p>
            <a href="<?= $h($sessionsUrl) ?>">Ouvrir les sessions</a>
        </article>
        <article class="bo-imm__cap">
            <span class="bo-imm__cap-kicker">En session</span>
            <h3>ATAK et Overwatch</h3>
            <p>Liaisons dégradées, capteurs et aides de jeu se règlent dans le mode roleplay ATAK, pas sur cette page.</p>
            <a href="<?= $h($atakRoleplayUrl) ?>">Ouvrir le mode roleplay ATAK</a>
        </article>
    </section>

    <div class="bo-imm__status" aria-label="État actuel du suivi">
        <article class="bo-imm__tile <?= $enabled ? 'bo-imm__tile--ok' : 'bo-imm__tile--warn' ?>">
            <span class="bo-imm__tile-kicker">Suivi</span>
            <strong><?= $enabled ? 'Visible' : 'Masqué' ?></strong>
            <span><?= $enabled ? 'Présent sur les fiches et dans le bureau' : 'Invisible tant que vous ne l’affichez pas' ?></span>
        </article>
        <article class="bo-imm__tile">
            <span class="bo-imm__tile-kicker">Étapes</span>
            <strong><?= count($stages) ?></strong>
            <span>proposées au staff, de la plus tôt à la plus avancée</span>
        </article>
        <article class="bo-imm__tile">
            <span class="bo-imm__tile-kicker">Filières</span>
            <strong><?= count($tracks) ?></strong>
            <span>choix de recrutement affichés sur le dossier</span>
        </article>
    </div>

    <?php if (!$enabled): ?>
    <div class="bo-imm__empty" role="status">
        <strong>Le suivi n’apparaît pas encore.</strong>
        Cochez « Afficher le suivi d’immersion » ci-dessous, puis enregistrez. Tant que ce n’est pas fait, les fiches et le bureau restent sans cette section.
    </div>
    <?php endif; ?>

    <form method="post" action="<?= $h($formAction) ?>" class="bo-settings-grid" id="bo-imm-form">
        <?= \App\Core\Csrf::field() ?>

        <section class="ath-card ath-rise bo-setting-group" id="activation-options">
            <p class="bo-setting-group__kicker">Activation</p>
            <h2 class="bo-setting-group__title">Afficher le suivi sur les fiches</h2>
            <p class="bo-setting-row__help" style="margin-top:8px;max-width:720px;">
                Ces deux choix concernent uniquement votre communauté. Ils ne changent rien en jeu, ni sur ATAK.
            </p>
            <div class="bo-setting-group__rows" style="margin-top:13px;">
                <label class="bo-setting-row" style="align-items:flex-start;cursor:pointer;">
                    <input type="checkbox" name="rp_followup_enabled" value="1" style="margin-top:3px;min-height:auto;" <?= $enabled ? 'checked' : '' ?>>
                    <span class="bo-setting-row__copy">
                        <span class="bo-setting-row__label">Afficher le suivi d’immersion</span>
                        <span class="bo-setting-row__help">La section apparaît sur la fiche, à l’édition du dossier, et dans le bureau de suivi.</span>
                    </span>
                </label>
                <label class="bo-setting-row" style="align-items:flex-start;cursor:pointer;">
                    <input type="checkbox" name="rp_followup_optional" value="1" style="margin-top:3px;min-height:auto;" <?= $optional ? 'checked' : '' ?>>
                    <span class="bo-setting-row__copy">
                        <span class="bo-setting-row__label">Ne pas bloquer la validation du dossier</span>
                        <span class="bo-setting-row__help">Le staff peut enregistrer une fiche même si l’étape, la filière ou le tuteur sont encore vides.</span>
                    </span>
                </label>
            </div>
        </section>

        <section class="ath-card ath-rise bo-setting-group bo-setting-group--wide" id="listes">
            <p class="bo-setting-group__kicker">Listes</p>
            <h2 class="bo-setting-group__title">Étapes et filières proposées au staff</h2>
            <p class="bo-setting-row__help" style="margin-top:8px;max-width:720px;">
                Chaque ligne devient un choix dans les menus. Renommez, réordonnez ou ajoutez : l’ordre affiché ici est celui des listes.
            </p>
            <div class="bo-setting-group__rows" style="margin-top:13px;">
                <div class="bo-setting-row bo-setting-row--stack">
                    <div class="bo-setting-row__copy">
                        <div class="bo-setting-row__label">Étapes d’avancement</div>
                        <div class="bo-setting-row__help">De la plus tôt à la plus avancée. Exemple : pré-qualification, puis tutorat, puis validation.</div>
                    </div>
                    <div class="bo-setting-row__control">
                        <div class="bo-imm-list" data-imm-list>
                            <div data-imm-rows></div>
                            <button type="button" class="bo-imm-list__add" data-imm-add>Ajouter une étape</button>
                            <textarea id="rp_followup_stages" name="rp_followup_stages" rows="6" class="bo-imm-list__fallback" data-imm-source data-placeholder="Ex. Tutorat"><?= $h(implode("\n", array_map(static fn ($v) => trim((string) $v), $stages))) ?></textarea>
                            <p class="bo-imm-list__hint">Si les boutons n’apparaissent pas, écrivez une étape par ligne.</p>
                        </div>
                    </div>
                </div>
                <div class="bo-setting-row bo-setting-row--stack">
                    <div class="bo-setting-row__copy">
                        <div class="bo-setting-row__label">Filières de recrutement</div>
                        <div class="bo-setting-row__help">Spécialités proposées au staff, par exemple infanterie, soutien ou commandement.</div>
                    </div>
                    <div class="bo-setting-row__control">
                        <div class="bo-imm-list" data-imm-list>
                            <div data-imm-rows></div>
                            <button type="button" class="bo-imm-list__add" data-imm-add>Ajouter une filière</button>
                            <textarea id="rp_followup_tracks" name="rp_followup_tracks" rows="6" class="bo-imm-list__fallback" data-imm-source data-placeholder="Ex. Infanterie"><?= $h(implode("\n", array_map(static fn ($v) => trim((string) $v), $tracks))) ?></textarea>
                            <p class="bo-imm-list__hint">Si les boutons n’apparaissent pas, écrivez une filière par ligne.</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="ath-card ath-rise bo-setting-group" id="indicateur">
            <p class="bo-setting-group__kicker">Indicateur</p>
            <h2 class="bo-setting-group__title">Quand un dossier est « prêt »</h2>
            <p class="bo-setting-row__help" style="margin-top:8px;max-width:720px;">
                Sur la fiche, un encadré vert ou ambre indique si le dossier atteint ces minimums.
                Les pourcentages reprennent la complétude et la disponibilité déjà saisies ailleurs dans le portail.
            </p>
            <div class="bo-setting-group__rows" style="margin-top:13px;">
                <div class="bo-setting-row bo-setting-row--stack">
                    <div class="bo-setting-row__copy">
                        <div class="bo-setting-row__label">Complétude du dossier — minimum</div>
                        <div class="bo-setting-row__help">Part des informations déjà renseignées sur la fiche, de 0 à 100.</div>
                    </div>
                    <div class="bo-setting-row__control">
                        <input type="number" min="0" max="100" id="rp_eligibility_min_completeness" name="rp_eligibility_min_completeness" class="bo-setting-row__field--wide" value="<?= $minCompleteness ?>" aria-describedby="rp-elig-completeness-help">
                    </div>
                </div>
                <div class="bo-setting-row bo-setting-row--stack">
                    <div class="bo-setting-row__copy">
                        <div class="bo-setting-row__label">Disponibilité — minimum</div>
                        <div class="bo-setting-row__help">Niveau d’engagement déjà saisi sur le dossier, de 0 à 100.</div>
                    </div>
                    <div class="bo-setting-row__control">
                        <input type="number" min="0" max="100" id="rp_eligibility_min_readiness" name="rp_eligibility_min_readiness" class="bo-setting-row__field--wide" value="<?= $minReadiness ?>">
                    </div>
                </div>
            </div>
            <div class="bo-imm__preview">
                <span class="bo-imm__preview-label">Résultat sur la fiche</span>
                <p><?= $h($readySentence) ?></p>
            </div>
        </section>

        <section class="ath-card ath-rise bo-setting-group" id="exigences">
            <p class="bo-setting-group__kicker">Exigences</p>
            <h2 class="bo-setting-group__title">Informations obligatoires pour « prêt »</h2>
            <p class="bo-setting-row__help" style="margin-top:8px;max-width:720px;">
                Cochez ce qui doit être présent en plus des deux minimums. Décocher une case ne l’exige plus.
            </p>
            <div class="bo-setting-group__rows" style="margin-top:13px;">
                <label class="bo-setting-row" style="align-items:flex-start;cursor:pointer;">
                    <input type="checkbox" name="rp_eligibility_require_unit" value="1" style="margin-top:3px;min-height:auto;" <?= $requireUnit ? 'checked' : '' ?>>
                    <span class="bo-setting-row__copy">
                        <span class="bo-setting-row__label">Affectation à une unité renseignée</span>
                        <span class="bo-setting-row__help">Le membre doit déjà être placé dans une unité de l’organigramme.</span>
                    </span>
                </label>
                <label class="bo-setting-row" style="align-items:flex-start;cursor:pointer;">
                    <input type="checkbox" name="rp_eligibility_require_callsign" value="1" style="margin-top:3px;min-height:auto;" <?= $requireCallsign ? 'checked' : '' ?>>
                    <span class="bo-setting-row__copy">
                        <span class="bo-setting-row__label">Indicatif radio renseigné</span>
                        <span class="bo-setting-row__help">Le nom d’appel doit figurer sur le dossier.</span>
                    </span>
                </label>
                <label class="bo-setting-row" style="align-items:flex-start;cursor:pointer;">
                    <input type="checkbox" name="rp_eligibility_require_tutor" value="1" style="margin-top:3px;min-height:auto;" <?= $requireTutor ? 'checked' : '' ?>>
                    <span class="bo-setting-row__copy">
                        <span class="bo-setting-row__label">Tuteur désigné sur le dossier</span>
                        <span class="bo-setting-row__help">Un référent d’arrivée doit être choisi dans le bureau de suivi ou sur la fiche.</span>
                    </span>
                </label>
            </div>
        </section>

        <section class="ath-card ath-rise bo-setting-group" id="cadences">
            <p class="bo-setting-group__kicker">Cadences</p>
            <h2 class="bo-setting-group__title">Bilans périodiques</h2>
            <p class="bo-setting-row__help" style="margin-top:8px;max-width:720px;">
                Ces délais décident quand un bilan d’étape apparaît sur le bureau et dans les rappels.
                Ils ne changent pas le parcours de grades.
            </p>
            <div class="bo-setting-group__rows" style="margin-top:13px;">
                <label class="bo-setting-row" style="align-items:flex-start;cursor:pointer;">
                    <input type="checkbox" name="rp_bilans_enabled" value="1" style="margin-top:3px;min-height:auto;" <?= $bilansEnabled ? 'checked' : '' ?>>
                    <span class="bo-setting-row__copy">
                        <span class="bo-setting-row__label">Suivre les bilans périodiques</span>
                        <span class="bo-setting-row__help">Si décoché, les rappels et le calendrier n’affichent plus ces bilans.</span>
                    </span>
                </label>
                <div class="bo-setting-row bo-setting-row--stack">
                    <div class="bo-setting-row__copy">
                        <div class="bo-setting-row__label">Pendant la première année — tous les (jours)</div>
                        <div class="bo-setting-row__help">Par défaut 180 jours (environ six mois).</div>
                    </div>
                    <div class="bo-setting-row__control">
                        <input type="number" min="30" max="730" name="rp_bilans_first_year_days" class="bo-setting-row__field--wide" value="<?= (int) ($bilans['first_year_days'] ?? 180) ?>">
                    </div>
                </div>
                <div class="bo-setting-row bo-setting-row--stack">
                    <div class="bo-setting-row__copy">
                        <div class="bo-setting-row__label">Entre un et deux ans — tous les (jours)</div>
                        <div class="bo-setting-row__help">Par défaut 240 jours (environ huit mois).</div>
                    </div>
                    <div class="bo-setting-row__control">
                        <input type="number" min="30" max="730" name="rp_bilans_second_year_days" class="bo-setting-row__field--wide" value="<?= (int) ($bilans['second_year_days'] ?? 240) ?>">
                    </div>
                </div>
                <div class="bo-setting-row bo-setting-row--stack">
                    <div class="bo-setting-row__copy">
                        <div class="bo-setting-row__label">Ensuite — tous les (jours)</div>
                        <div class="bo-setting-row__help">Par défaut 365 jours (une fois par an).</div>
                    </div>
                    <div class="bo-setting-row__control">
                        <input type="number" min="30" max="730" name="rp_bilans_ongoing_days" class="bo-setting-row__field--wide" value="<?= (int) ($bilans['ongoing_days'] ?? 365) ?>">
                    </div>
                </div>
                <div class="bo-setting-row bo-setting-row--stack">
                    <div class="bo-setting-row__copy">
                        <div class="bo-setting-row__label">Marge avant « en retard » (jours)</div>
                        <div class="bo-setting-row__help">Un bilan dû reste « à faire » pendant cette marge, puis passe en retard.</div>
                    </div>
                    <div class="bo-setting-row__control">
                        <input type="number" min="0" max="60" name="rp_bilans_grace_days" class="bo-setting-row__field--wide" value="<?= (int) ($bilans['grace_days'] ?? 14) ?>">
                    </div>
                </div>
            </div>
        </section>

        <section class="ath-card ath-rise bo-setting-group" id="echeances-cadence">
            <p class="bo-setting-group__kicker">Échéances</p>
            <h2 class="bo-setting-group__title">Entretien, médical et rotation</h2>
            <p class="bo-setting-row__help" style="margin-top:8px;max-width:720px;">
                Choisissez ce qui apparaît dans le calendrier. Si vous indiquez un délai après « réalisé », la prochaine date est proposée automatiquement.
            </p>
            <div class="bo-setting-group__rows" style="margin-top:13px;">
                <label class="bo-setting-row" style="align-items:flex-start;cursor:pointer;">
                    <input type="checkbox" name="rp_interview_visible" value="1" style="margin-top:3px;min-height:auto;" <?= $interviewVisible ? 'checked' : '' ?>>
                    <span class="bo-setting-row__copy">
                        <span class="bo-setting-row__label">Afficher les entretiens individuels</span>
                    </span>
                </label>
                <div class="bo-setting-row bo-setting-row--stack">
                    <div class="bo-setting-row__copy">
                        <div class="bo-setting-row__label">Prochain entretien — jours après réalisation</div>
                        <div class="bo-setting-row__help">0 = ne pas proposer de date suivante.</div>
                    </div>
                    <div class="bo-setting-row__control">
                        <input type="number" min="0" max="730" name="rp_interview_next_after_days" class="bo-setting-row__field--wide" value="<?= (int) ($interview['next_after_days'] ?? 0) ?>">
                    </div>
                </div>
                <label class="bo-setting-row" style="align-items:flex-start;cursor:pointer;">
                    <input type="checkbox" name="rp_medical_visible" value="1" style="margin-top:3px;min-height:auto;" <?= $medicalVisible ? 'checked' : '' ?>>
                    <span class="bo-setting-row__copy">
                        <span class="bo-setting-row__label">Afficher les visites médicales</span>
                    </span>
                </label>
                <div class="bo-setting-row bo-setting-row--stack">
                    <div class="bo-setting-row__copy">
                        <div class="bo-setting-row__label">Prochaine visite — jours après réalisation</div>
                    </div>
                    <div class="bo-setting-row__control">
                        <input type="number" min="0" max="730" name="rp_medical_next_after_days" class="bo-setting-row__field--wide" value="<?= (int) ($medical['next_after_days'] ?? 0) ?>">
                    </div>
                </div>
                <label class="bo-setting-row" style="align-items:flex-start;cursor:pointer;">
                    <input type="checkbox" name="rp_rotation_visible" value="1" style="margin-top:3px;min-height:auto;" <?= $rotationVisible ? 'checked' : '' ?>>
                    <span class="bo-setting-row__copy">
                        <span class="bo-setting-row__label">Afficher les rotations de service</span>
                    </span>
                </label>
                <div class="bo-setting-row bo-setting-row--stack">
                    <div class="bo-setting-row__copy">
                        <div class="bo-setting-row__label">Prochaine rotation — jours après réalisation</div>
                    </div>
                    <div class="bo-setting-row__control">
                        <input type="number" min="0" max="730" name="rp_rotation_next_after_days" class="bo-setting-row__field--wide" value="<?= (int) ($rotation['next_after_days'] ?? 0) ?>">
                    </div>
                </div>
                <label class="bo-setting-row" style="align-items:flex-start;cursor:pointer;">
                    <input type="checkbox" name="rp_rotation_require_interview" value="1" style="margin-top:3px;min-height:auto;" <?= $rotationRequireInterview ? 'checked' : '' ?>>
                    <span class="bo-setting-row__copy">
                        <span class="bo-setting-row__label">Exiger un entretien avant une rotation</span>
                        <span class="bo-setting-row__help">Le staff ne peut planifier ou valider une rotation tant qu’un entretien n’a pas été réalisé.</span>
                    </span>
                </label>
            </div>
        </section>

        <section class="ath-card ath-rise bo-setting-group" id="essai">
            <p class="bo-setting-group__kicker">Période d’essai</p>
            <h2 class="bo-setting-group__title">Durée et alerte</h2>
            <p class="bo-setting-row__help" style="margin-top:8px;max-width:720px;">
                L’alerte rappelle au staff les dossiers encore en essai au-delà du délai. Le libellé sert à ouvrir le bon type de bilan.
            </p>
            <div class="bo-setting-group__rows" style="margin-top:13px;">
                <div class="bo-setting-row bo-setting-row--stack">
                    <div class="bo-setting-row__copy">
                        <div class="bo-setting-row__label">Durée de référence (jours)</div>
                    </div>
                    <div class="bo-setting-row__control">
                        <input type="number" min="14" max="365" name="rp_probation_duration_days" class="bo-setting-row__field--wide" value="<?= (int) ($probation['duration_days'] ?? 60) ?>">
                    </div>
                </div>
                <label class="bo-setting-row" style="align-items:flex-start;cursor:pointer;">
                    <input type="checkbox" name="rp_probation_alert_enabled" value="1" style="margin-top:3px;min-height:auto;" <?= $probationAlert ? 'checked' : '' ?>>
                    <span class="bo-setting-row__copy">
                        <span class="bo-setting-row__label">Alerter le staff au-delà de ce délai</span>
                    </span>
                </label>
                <div class="bo-setting-row bo-setting-row--stack">
                    <div class="bo-setting-row__copy">
                        <div class="bo-setting-row__label">Alerte après (jours)</div>
                    </div>
                    <div class="bo-setting-row__control">
                        <input type="number" min="14" max="365" name="rp_probation_alert_after_days" class="bo-setting-row__field--wide" value="<?= (int) ($probation['alert_after_days'] ?? 60) ?>">
                    </div>
                </div>
                <div class="bo-setting-row bo-setting-row--stack">
                    <div class="bo-setting-row__copy">
                        <div class="bo-setting-row__label">Libellé du bilan d’essai</div>
                    </div>
                    <div class="bo-setting-row__control">
                        <input type="text" maxlength="80" name="rp_probation_bilan_label" class="bo-setting-row__field--wide" value="<?= $h((string) ($probation['bilan_label'] ?? 'Fin de période d’essai')) ?>">
                    </div>
                </div>
            </div>
        </section>

        <section class="ath-card ath-rise bo-setting-group" id="notifications">
            <p class="bo-setting-group__kicker">Rappels</p>
            <h2 class="bo-setting-group__title">Notifications et calendrier</h2>
            <div class="bo-setting-group__rows" style="margin-top:13px;">
                <label class="bo-setting-row" style="align-items:flex-start;cursor:pointer;">
                    <input type="checkbox" name="rp_notif_email_reminders" value="1" style="margin-top:3px;min-height:auto;" <?= $emailReminders ? 'checked' : '' ?>>
                    <span class="bo-setting-row__copy">
                        <span class="bo-setting-row__label">Envoyer les rappels par e-mail</span>
                        <span class="bo-setting-row__help">Tuteurs et responsables reçoivent un message le lundi si un bilan est dû.</span>
                    </span>
                </label>
                <div class="bo-setting-row bo-setting-row--stack">
                    <div class="bo-setting-row__copy">
                        <div class="bo-setting-row__label">Calendrier — jours à venir</div>
                        <div class="bo-setting-row__help">Ce qui apparaît dans le bureau effectifs et les échéances (1 à 90).</div>
                    </div>
                    <div class="bo-setting-row__control">
                        <input type="number" min="1" max="90" name="rp_notif_calendar_horizon_days" class="bo-setting-row__field--wide" value="<?= (int) ($notifications['calendar_horizon_days'] ?? 14) ?>">
                    </div>
                </div>
                <div class="bo-setting-row bo-setting-row--stack">
                    <div class="bo-setting-row__copy">
                        <div class="bo-setting-row__label">Rappel avant l’échéance (jours)</div>
                        <div class="bo-setting-row__help">0 = pas de rappel anticipé. Réservé aux prochains envois.</div>
                    </div>
                    <div class="bo-setting-row__control">
                        <input type="number" min="0" max="30" name="rp_notif_remind_before_days" class="bo-setting-row__field--wide" value="<?= (int) ($notifications['remind_before_days'] ?? 0) ?>">
                    </div>
                </div>
            </div>
        </section>

        <section class="ath-card ath-rise bo-setting-group bo-setting-group--wide" id="types-bilans">
            <p class="bo-setting-group__kicker">Types</p>
            <h2 class="bo-setting-group__title">Types de bilans d’étape</h2>
            <p class="bo-setting-row__help" style="margin-top:8px;max-width:720px;">
                Ces libellés apparaissent dans le menu du bilan sur la fiche. Une ligne = un type.
            </p>
            <div class="bo-setting-group__rows" style="margin-top:13px;">
                <div class="bo-setting-row bo-setting-row--stack">
                    <div class="bo-setting-row__control">
                        <div class="bo-imm-list" data-imm-list>
                            <div data-imm-rows></div>
                            <button type="button" class="bo-imm-list__add" data-imm-add>Ajouter un type</button>
                            <textarea id="rp_stage_bilan_types" name="rp_stage_bilan_types" rows="6" class="bo-imm-list__fallback" data-imm-source data-placeholder="Ex. Suivi périodique"><?= $h(implode("\n", $stageBilanLabels)) ?></textarea>
                            <p class="bo-imm-list__hint">Si les boutons n’apparaissent pas, écrivez un type par ligne.</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <div class="bo-settings-save">
            <button type="submit" class="ath-btn ath-btn--solid">Enregistrer le suivi d’immersion</button>
        </div>
    </form>

    <p class="bo-settings-note">
        <a href="<?= $h($followupUrl) ?>">Bureau de suivi</a>
        · <a href="<?= $h($deadlinesUrl) ?>">Échéances</a>
        · <a href="<?= $h($personnelUrl) ?>">Dossiers personnel</a>
        · <a href="<?= $h($phaseRulesUrl) ?>">Parcours RH</a>
        · <a href="<?= $h($sessionsUrl) ?>">Sessions Arma</a>
        · <a href="<?= $h($atakRoleplayUrl) ?>">Mode roleplay ATAK</a>
    </p>
</div>
<script src="<?= $h(asset_url('assets/js/roleplay-immersion-settings.js')) ?>" defer></script>
