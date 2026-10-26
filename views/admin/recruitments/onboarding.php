<?php
declare(strict_types=1);

/**
 * Parcours guidé post-acceptation — intégration du nouveau membre.
 * Listes alignées sur la fiche Effectifs : niveaux d’accès, grades de la communauté (systèmes sur mesure compris),
 * toutes les fonctions du référentiel et l’organigramme complet (EnlistmentOnboardingFormService).
 *
 * @var array<string,mixed> $enlistment
 * @var array<string,mixed> $onboardingPrefill
 * @var list<array<string,mixed>> $accessRoles
 * @var list<array{id:int,label:string,depth:int}> $units
 * @var list<array{label:string,grades:list<array<string,mixed>>}> $gradeGroups
 * @var list<array{label:string,hint:string,items:list<array{value:string,label:string,detail:string,checked:bool}>}> $jobRoleGroups
 * @var list<array{id:int,label:string}> $referentOptions
 * @var array<string,list<string>> $civilOptions
 * @var array<string,mixed>|null $linkedRecruitmentOpening
 * @var array<string,mixed>|null $linkedUser
 * @var bool $needsAcceptanceOnboarding
 */

$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');

$e = is_array($enlistment ?? null) ? $enlistment : [];
$id = (int) ($e['id'] ?? 0);
$prefill = is_array($onboardingPrefill ?? null) ? $onboardingPrefill : [];
$accessRoles = is_array($accessRoles ?? null) ? $accessRoles : [];
$units = is_array($units ?? null) ? $units : [];
$gradeGroups = is_array($gradeGroups ?? null) ? $gradeGroups : [];
$jobRoleGroups = is_array($jobRoleGroups ?? null) ? $jobRoleGroups : [];
$referentOptions = is_array($referentOptions ?? null) ? $referentOptions : [];
$civilOptions = is_array($civilOptions ?? null) ? $civilOptions : [];
$linkedOpening = is_array($linkedRecruitmentOpening ?? null) ? $linkedRecruitmentOpening : null;
$linkedUser = is_array($linkedUser ?? null) ? $linkedUser : null;
$needsOnboarding = !empty($needsAcceptanceOnboarding);

$pv = static fn (string $key): string => trim((string) ($prefill[$key] ?? ''));
$accessRoleId = (int) ($prefill['access_role_id'] ?? 0);
$gradeId = (int) ($prefill['grade_id'] ?? 0);
$unitId = (int) ($prefill['unit_id'] ?? 0);
$jobValue = (string) ((int) ($prefill['personnel_job_role_id'] ?? 0) ?: '');
$referentId = (int) ($prefill['referent_user_id'] ?? 0);
$duty = $pv('duty') === 'active' ? 'active' : 'training';

$candidateLabel = trim($pv('first_name') . ' ' . $pv('last_name'));
if ($candidateLabel === '') {
    $candidateLabel = 'Candidat #' . $id;
}
$initials = '';
foreach (preg_split('/\s+/u', $candidateLabel) ?: [] as $part) {
    if ($part !== '' && mb_strlen($initials, 'UTF-8') < 2) {
        $initials .= mb_strtoupper(mb_substr($part, 0, 1, 'UTF-8'), 'UTF-8');
    }
}

$fmtDate = static function (string $raw): string {
    $raw = trim($raw);
    if ($raw === '' || str_starts_with($raw, '0000')) {
        return '';
    }
    $ts = strtotime($raw);

    return $ts ? date('d/m/Y', $ts) : '';
};
$dossierFacts = [];
if (trim((string) ($e['email'] ?? '')) !== '' && !str_ends_with((string) $e['email'], '.invalid')) {
    $dossierFacts['E-mail'] = (string) $e['email'];
}
if (trim((string) ($e['discord_pseudo'] ?? '')) !== '') {
    $dossierFacts['Discord'] = (string) $e['discord_pseudo'];
}
if ((int) ($e['age'] ?? 0) > 0) {
    $dossierFacts['Âge'] = (int) $e['age'] . ' ans';
}
if (trim((string) ($e['timezone'] ?? '')) !== '') {
    $dossierFacts['Fuseau'] = (string) $e['timezone'];
}
if (($d = $fmtDate((string) ($e['created_at'] ?? ''))) !== '') {
    $dossierFacts['Candidature'] = $d;
}
if (($d = $fmtDate((string) ($e['reviewed_at'] ?? ''))) !== '') {
    $dossierFacts['Acceptée le'] = $d;
}

$dossierUrl = url('back-office/recruitments/' . $id . '?dossier=1');
$formAction = url('back-office/recruitments/' . $id . '/onboarding');
$steps = [
    1 => ['Identité', 'Personnage et état civil'],
    2 => ['Steam & Arma', 'Reconnaissance en jeu'],
    3 => ['Accès', 'Niveau et position'],
    4 => ['Affectation', 'Unité, grade, emploi'],
];
?>
<div class="rec-onb" data-rec-onb>
    <header class="rec-onb__hero">
        <div class="rec-onb__avatar" aria-hidden="true" data-rec-onb-initials><?= $h($initials !== '' ? $initials : '?') ?></div>
        <div class="rec-onb__hero-text">
            <p class="rec-onb__kicker">Intégration · dossier #<?= (int) $id ?></p>
            <h1 class="rec-onb__title">Intégrer <span data-rec-onb-name><?= $h($candidateLabel) ?></span></h1>
            <p class="rec-onb__lead">
                Quatre étapes pour transformer la candidature acceptée en fiche membre complète.
                Rien n’est enregistré avant « Finaliser l’intégration ».
            </p>
            <div class="rec-onb__chips">
                <?php if ($linkedOpening): ?>
                <span class="rec-onb__chip rec-onb__chip--accent">
                    Offre : <?= $h((string) ($linkedOpening['title'] ?? 'Avis de vacance')) ?>
                    <?php if (trim((string) ($linkedOpening['reference_public'] ?? '')) !== ''): ?>
                        · <?= $h((string) $linkedOpening['reference_public']) ?>
                    <?php endif; ?>
                </span>
                <?php endif; ?>
                <?php if ($linkedUser): ?>
                <span class="rec-onb__chip">Compte existant rattaché</span>
                <?php else: ?>
                <span class="rec-onb__chip">Compte créé à la validation</span>
                <?php endif; ?>
            </div>
        </div>
        <a class="rec-onb__btn rec-onb__btn--ghost rec-onb__hero-back" href="<?= $h($dossierUrl) ?>">Retour au dossier</a>
    </header>

    <?php if (!$needsOnboarding): ?>
    <p class="rec-onb__note" role="status">
        L’intégration a déjà été finalisée. Vous pouvez encore corriger les informations ci-dessous puis réenregistrer.
    </p>
    <?php endif; ?>

    <nav class="rec-onb__steps" aria-label="Étapes d’intégration">
        <div class="rec-onb__progress" aria-hidden="true"><span data-rec-onb-progress></span></div>
        <?php foreach ($steps as $n => [$label, $sub]): ?>
        <button type="button" class="rec-onb__step<?= $n === 1 ? ' is-active' : '' ?>" data-rec-onb-tab="<?= (int) $n ?>">
            <span class="rec-onb__step-num"><?= (int) $n ?></span>
            <span class="rec-onb__step-text">
                <span class="rec-onb__step-label"><?= $h($label) ?></span>
                <span class="rec-onb__step-sub"><?= $h($sub) ?></span>
            </span>
        </button>
        <?php endforeach; ?>
    </nav>

    <div hidden aria-hidden="true">
        <span data-rec-onb-t="ok">Format reconnu</span>
        <span data-rec-onb-t="warn">Adresse inhabituelle : vérifiez qu’il s’agit bien d’un profil Steam.</span>
        <span data-rec-onb-t="empty">Sans Steam, le membre ne sera pas reconnu automatiquement en jeu.</span>
        <span data-rec-onb-t="linked">Lié</span>
        <span data-rec-onb-t="tolink">À lier</span>
        <span data-rec-onb-t="new">nouveau</span>
    </div>

    <div class="rec-onb__layout">
    <form method="post" action="<?= $h($formAction) ?>" class="rec-onb__form" id="rec-onb-form" novalidate>
        <?= \App\Core\Csrf::field() ?>

        <section class="rec-onb__panel is-active" data-rec-onb-panel="1">
            <div class="rec-onb__panel-head">
                <p class="rec-onb__eyebrow">Étape 1 sur 4</p>
                <h2 class="rec-onb__panel-title">Identité du personnage</h2>
                <p class="rec-onb__panel-text">
                    Le prénom et le nom forment le nom du personnage, utilisé partout (dossier, annuaire, ATAK).
                    L’état civil reprend ce que le candidat a déclaré quand il existe.
                </p>
            </div>
            <div class="rec-onb__grid">
                <label class="rec-onb__field">
                    <span class="rec-onb__label">Prénom <abbr title="obligatoire">*</abbr></span>
                    <input type="text" name="first_name" required maxlength="100" value="<?= $h($pv('first_name')) ?>" placeholder="Jean" autocomplete="off" data-rec-onb-watch="first">
                </label>
                <label class="rec-onb__field">
                    <span class="rec-onb__label">Nom <abbr title="obligatoire">*</abbr></span>
                    <input type="text" name="last_name" required maxlength="100" value="<?= $h($pv('last_name')) ?>" placeholder="Dupont" autocomplete="off" data-rec-onb-watch="last">
                </label>
                <label class="rec-onb__field">
                    <span class="rec-onb__label">Indicatif radio <span class="rec-onb__opt">(optionnel)</span></span>
                    <input type="text" name="callsign" maxlength="64" value="<?= $h($pv('callsign')) ?>" placeholder="Ex. Ghost-1" autocomplete="off" data-rec-onb-watch="callsign">
                </label>
                <label class="rec-onb__field">
                    <span class="rec-onb__label">Surnom <span class="rec-onb__opt">(optionnel)</span></span>
                    <input type="text" name="nickname_primary" maxlength="100" value="<?= $h($pv('nickname_primary')) ?>" placeholder="Ex. Doc" autocomplete="off">
                </label>
            </div>

            <h3 class="rec-onb__section">État civil du personnage</h3>
            <div class="rec-onb__grid">
                <label class="rec-onb__field">
                    <span class="rec-onb__label">Nationalité</span>
                    <input type="text" name="nationality" maxlength="100" value="<?= $h($pv('nationality')) ?>" placeholder="Ex. Française" autocomplete="off">
                </label>
                <label class="rec-onb__field">
                    <span class="rec-onb__label">Lieu de naissance</span>
                    <input type="text" name="birth_place" maxlength="150" value="<?= $h($pv('birth_place')) ?>" placeholder="Ex. Lyon" autocomplete="off">
                </label>
                <label class="rec-onb__field">
                    <span class="rec-onb__label">Sexe</span>
                    <select name="sex">
                        <option value="">Non renseigné</option>
                        <?php foreach ($civilOptions['sex'] ?? [] as $opt): ?>
                        <option value="<?= $h($opt) ?>"<?= $pv('sex') === $opt ? ' selected' : '' ?>><?= $h($opt) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="rec-onb__field">
                    <span class="rec-onb__label">Groupe sanguin</span>
                    <select name="blood_type">
                        <option value="">Non renseigné</option>
                        <?php foreach ($civilOptions['blood_type'] ?? [] as $opt): ?>
                        <option value="<?= $h($opt) ?>"<?= $pv('blood_type') === $opt ? ' selected' : '' ?>><?= $h($opt) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <span class="rec-onb__hint">Repris par l’app médicale de l’ATAK.</span>
                </label>
                <label class="rec-onb__field">
                    <span class="rec-onb__label">Date d’engagement</span>
                    <input type="date" name="enlistment_date" value="<?= $h($pv('enlistment_date')) ?>">
                    <span class="rec-onb__hint">Point de départ de l’ancienneté dans la communauté.</span>
                </label>
            </div>
            <div class="rec-onb__nav">
                <a class="rec-onb__btn rec-onb__btn--ghost" href="<?= $h($dossierUrl) ?>">Retour au dossier</a>
                <button type="button" class="rec-onb__btn rec-onb__btn--solid" data-rec-onb-next>Continuer</button>
            </div>
        </section>

        <section class="rec-onb__panel" data-rec-onb-panel="2" hidden>
            <div class="rec-onb__panel-head">
                <p class="rec-onb__eyebrow">Étape 2 sur 4</p>
                <h2 class="rec-onb__panel-title">Lien Steam & données Arma</h2>
                <p class="rec-onb__panel-text">
                    Avec le profil Steam, Athena reconnaît le joueur dans Arma et l’ATAK : temps de jeu,
                    présence en mission et position remontent dès sa première connexion.
                </p>
            </div>
            <ol class="rec-onb__howto">
                <li><strong>Ouvrir</strong> le profil Steam du candidat (navigateur ou client Steam).</li>
                <li><strong>Copier</strong> l’adresse <code>steamcommunity.com/profiles/…</code> ou <code>/id/pseudo</code>.</li>
                <li><strong>Coller</strong> ci-dessous. Le SteamID64 seul est aussi accepté.</li>
            </ol>
            <label class="rec-onb__field rec-onb__field--full">
                <span class="rec-onb__label">Profil Steam <span class="rec-onb__opt">(fortement recommandé)</span></span>
                <input type="text" name="steam_profile" maxlength="255" value="<?= $h($pv('steam_profile')) ?>" placeholder="https://steamcommunity.com/profiles/76561198…" inputmode="url" autocomplete="off" data-rec-onb-steam>
                <span class="rec-onb__status" data-rec-onb-steam-status data-state="<?= $pv('steam_profile') !== '' ? 'ok' : 'empty' ?>">
                    <?= $pv('steam_profile') !== '' ? 'Format reconnu' : 'Sans Steam, le membre ne sera pas reconnu automatiquement en jeu.' ?>
                </span>
            </label>
            <?php if (is_array($linkedUser) && trim((string) ($linkedUser['steam_id'] ?? '')) !== ''): ?>
            <p class="rec-onb__info">Steam déjà enregistré sur le compte : <code><?= $h((string) $linkedUser['steam_id']) ?></code></p>
            <?php endif; ?>
            <div class="rec-onb__nav">
                <button type="button" class="rec-onb__btn rec-onb__btn--ghost" data-rec-onb-prev>Retour</button>
                <button type="button" class="rec-onb__btn rec-onb__btn--solid" data-rec-onb-next>Continuer</button>
            </div>
        </section>

        <section class="rec-onb__panel" data-rec-onb-panel="3" hidden>
            <div class="rec-onb__panel-head">
                <p class="rec-onb__eyebrow">Étape 3 sur 4</p>
                <h2 class="rec-onb__panel-title">Niveau d’accès et position de service</h2>
                <p class="rec-onb__panel-text">
                    Le niveau d’accès décide de ce que le membre peut voir et faire sur le site.
                    Ce sont les mêmes niveaux que sur la fiche Effectifs ; « Membre » suffit pour un engagement classique.
                </p>
            </div>
            <?php if ($accessRoles === []): ?>
            <p class="rec-onb__info">Aucun niveau d’accès n’est encore configuré : le niveau Membre sera appliqué.</p>
            <?php else: ?>
            <fieldset class="rec-onb__choices">
                <legend class="sr-only">Niveau d’accès</legend>
                <?php foreach ($accessRoles as $role): ?>
                    <?php
                    $rid = (int) ($role['id'] ?? 0);
                    $rname = trim((string) ($role['name'] ?? '')) ?: ('Niveau #' . $rid);
                    $rdesc = trim((string) ($role['description'] ?? ''));
                    ?>
                <label class="rec-onb__choice">
                    <input type="radio" name="access_role_id" value="<?= $rid ?>"<?= $accessRoleId === $rid ? ' checked' : '' ?> data-rec-onb-watch="access">
                    <span class="rec-onb__choice-body">
                        <span class="rec-onb__choice-name"><?= $h($rname) ?></span>
                        <?php if ($rdesc !== ''): ?>
                        <span class="rec-onb__choice-desc"><?= $h($rdesc) ?></span>
                        <?php endif; ?>
                    </span>
                </label>
                <?php endforeach; ?>
            </fieldset>
            <?php endif; ?>

            <h3 class="rec-onb__section">Position de service</h3>
            <fieldset class="rec-onb__choices rec-onb__choices--two">
                <legend class="sr-only">Position de service</legend>
                <label class="rec-onb__choice">
                    <input type="radio" name="duty" value="training"<?= $duty === 'training' ? ' checked' : '' ?> data-rec-onb-watch="duty">
                    <span class="rec-onb__choice-body">
                        <span class="rec-onb__choice-name">En formation <span class="rec-onb__tag">Recommandé</span></span>
                        <span class="rec-onb__choice-desc">Nouvelle recrue : le suivi d’intégration s’ouvre, passage en service actif à la fin du parcours.</span>
                    </span>
                </label>
                <label class="rec-onb__choice">
                    <input type="radio" name="duty" value="active"<?= $duty === 'active' ? ' checked' : '' ?> data-rec-onb-watch="duty">
                    <span class="rec-onb__choice-body">
                        <span class="rec-onb__choice-name">En service actif</span>
                        <span class="rec-onb__choice-desc">Transfert ou joueur déjà formé : il est opérationnel tout de suite.</span>
                    </span>
                </label>
            </fieldset>

            <div class="rec-onb__nav">
                <button type="button" class="rec-onb__btn rec-onb__btn--ghost" data-rec-onb-prev>Retour</button>
                <button type="button" class="rec-onb__btn rec-onb__btn--solid" data-rec-onb-next>Continuer</button>
            </div>
        </section>

        <section class="rec-onb__panel" data-rec-onb-panel="4" hidden>
            <div class="rec-onb__panel-head">
                <p class="rec-onb__eyebrow">Étape 4 sur 4</p>
                <h2 class="rec-onb__panel-title">Affectation : unité, grade et emploi</h2>
                <p class="rec-onb__panel-text">
                    Placez le membre dans l’organigramme, donnez-lui son grade et sa fonction.
                    L’emploi décrit le poste (radio, médic, chef d’équipe…) : il n’ouvre aucun droit d’accès.
                    Les valeurs de l’offre sont pré-remplies.
                </p>
            </div>
            <div class="rec-onb__grid">
                <label class="rec-onb__field">
                    <span class="rec-onb__label">Unité</span>
                    <select name="unit_id" data-rec-onb-watch="unit">
                        <option value="0">Choisir plus tard</option>
                        <?php foreach ($units as $u): ?>
                        <option value="<?= (int) $u['id'] ?>"<?= $unitId === (int) $u['id'] ? ' selected' : '' ?>><?= str_repeat('&nbsp;&nbsp;&nbsp;', min(6, (int) $u['depth'])) ?><?= (int) $u['depth'] > 0 ? '↳ ' : '' ?><?= $h((string) $u['label']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <span class="rec-onb__hint"><?= count($units) ?> unité<?= count($units) > 1 ? 's' : '' ?> de l’organigramme.</span>
                </label>
                <label class="rec-onb__field">
                    <span class="rec-onb__label">Grade</span>
                    <select name="grade_id" data-rec-onb-watch="grade">
                        <option value="0">Aucun grade</option>
                        <?php foreach ($gradeGroups as $group): ?>
                        <optgroup label="<?= $h((string) $group['label']) ?>">
                            <?php foreach ($group['grades'] as $g): ?>
                                <?php $gid = (int) ($g['id'] ?? 0); ?>
                            <option value="<?= $gid ?>"<?= $gradeId === $gid ? ' selected' : '' ?>><?= $h(\App\Support\GradeSelectOptions::label($g)) ?></option>
                            <?php endforeach; ?>
                        </optgroup>
                        <?php endforeach; ?>
                    </select>
                    <span class="rec-onb__hint">Grades de votre communauté, du plus élevé au plus bas. <a href="<?= $h(url('back-office/referentiels/grades')) ?>">Gérer les grades</a></span>
                </label>
                <div class="rec-onb__field rec-onb__field--full">
                    <label class="rec-onb__label" for="rec-onb-job">Emploi</label>
                    <div class="rec-onb__combo">
                        <input type="search" class="rec-onb__filter" placeholder="Filtrer : médic, pilote, JTAC…" aria-label="Filtrer les emplois" data-rec-onb-filter="rec-onb-job" autocomplete="off">
                        <select name="personnel_job_role_id" id="rec-onb-job" data-rec-onb-watch="job">
                            <option value="">Choisir, ou nommer ci-dessous</option>
                            <?php foreach ($jobRoleGroups as $group): ?>
                            <optgroup label="<?= $h((string) $group['label']) ?>">
                                <?php foreach ($group['items'] as $item): ?>
                                <option value="<?= $h((string) $item['value']) ?>"<?= $jobValue !== '' && $jobValue === (string) $item['value'] ? ' selected' : '' ?>><?= $h((string) $item['label']) ?><?= trim((string) $item['detail']) !== '' ? ' · ' . $h((string) $item['detail']) : '' ?></option>
                                <?php endforeach; ?>
                            </optgroup>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <span class="rec-onb__hint">Tout le référentiel militaire, classé par domaine, plus les emplois créés par votre communauté.</span>
                </div>
                <label class="rec-onb__field">
                    <span class="rec-onb__label">Nouvel emploi <span class="rec-onb__opt">(si absent de la liste)</span></span>
                    <input type="text" name="assignment_label" maxlength="120" value="<?= $h($pv('assignment_label')) ?>" placeholder="Ex. Technicien cyber" data-rec-onb-watch="jobname">
                    <span class="rec-onb__hint">Ajouté au référentiel de la communauté.</span>
                </label>
                <label class="rec-onb__field">
                    <span class="rec-onb__label">Référent d’intégration</span>
                    <select name="referent_user_id" data-rec-onb-watch="referent">
                        <option value="0">Aucun pour l’instant</option>
                        <?php foreach ($referentOptions as $ref): ?>
                        <option value="<?= (int) $ref['id'] ?>"<?= $referentId === (int) $ref['id'] ? ' selected' : '' ?>><?= $h((string) $ref['label']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <span class="rec-onb__hint">Le parrain qui accompagne la recrue. Par défaut : le recruteur du dossier.</span>
                </label>
            </div>

            <div class="rec-onb__summary">
                <h3 class="rec-onb__summary-title">Prêt à finaliser</h3>
                <p class="rec-onb__summary-text">
                    Athena crée ou rattache le compte, applique le niveau d’accès, le grade et l’affectation,
                    lie le Steam, ouvre le suivi d’intégration puis envoie les e-mails d’accueil si nécessaire.
                </p>
            </div>

            <div class="rec-onb__nav">
                <button type="button" class="rec-onb__btn rec-onb__btn--ghost" data-rec-onb-prev>Retour</button>
                <button type="submit" class="rec-onb__btn rec-onb__btn--accent">Finaliser l’intégration</button>
            </div>
        </section>
    </form>

    <aside class="rec-onb__aside" aria-label="Récapitulatif">
        <div class="rec-onb__card">
            <p class="rec-onb__card-kicker">Future fiche membre</p>
            <dl class="rec-onb__recap">
                <div><dt>Personnage</dt><dd data-rec-onb-out="name"><?= $h($candidateLabel) ?></dd></div>
                <div><dt>Indicatif</dt><dd data-rec-onb-out="callsign"><?= $h($pv('callsign') !== '' ? $pv('callsign') : '—') ?></dd></div>
                <div><dt>Steam</dt><dd data-rec-onb-out="steam"><?= $pv('steam_profile') !== '' ? 'Lié' : 'À lier' ?></dd></div>
                <div><dt>Accès</dt><dd data-rec-onb-out="access">—</dd></div>
                <div><dt>Position</dt><dd data-rec-onb-out="duty">—</dd></div>
                <div><dt>Unité</dt><dd data-rec-onb-out="unit">—</dd></div>
                <div><dt>Grade</dt><dd data-rec-onb-out="grade">—</dd></div>
                <div><dt>Emploi</dt><dd data-rec-onb-out="job">—</dd></div>
                <div><dt>Référent</dt><dd data-rec-onb-out="referent">—</dd></div>
            </dl>
        </div>
        <?php if ($dossierFacts !== []): ?>
        <div class="rec-onb__card rec-onb__card--quiet">
            <p class="rec-onb__card-kicker">Rappel du dossier</p>
            <dl class="rec-onb__recap">
                <?php foreach ($dossierFacts as $k => $v): ?>
                <div><dt><?= $h($k) ?></dt><dd><?= $h($v) ?></dd></div>
                <?php endforeach; ?>
            </dl>
        </div>
        <?php endif; ?>
    </aside>
    </div>
</div>
<script>
(function () {
  var root = document.querySelector('[data-rec-onb]');
  if (!root) return;
  var form = root.querySelector('#rec-onb-form');
  var tabs = Array.prototype.slice.call(root.querySelectorAll('[data-rec-onb-tab]'));
  var panels = Array.prototype.slice.call(root.querySelectorAll('[data-rec-onb-panel]'));
  var bar = root.querySelector('[data-rec-onb-progress]');
  var current = 1;

  function show(step) {
    current = Math.max(1, Math.min(4, step));
    tabs.forEach(function (t) {
      var n = parseInt(t.getAttribute('data-rec-onb-tab'), 10);
      t.classList.toggle('is-active', n === current);
      t.classList.toggle('is-done', n < current);
      if (n === current) t.setAttribute('aria-current', 'step'); else t.removeAttribute('aria-current');
    });
    panels.forEach(function (p) {
      var on = parseInt(p.getAttribute('data-rec-onb-panel'), 10) === current;
      p.hidden = !on;
      p.classList.toggle('is-active', on);
    });
    if (bar) bar.style.width = ((current - 1) / 3 * 100) + '%';
  }

  function validateStep(step) {
    if (step !== 1 || !form) return true;
    var fields = [form.querySelector('[name="first_name"]'), form.querySelector('[name="last_name"]')];
    for (var i = 0; i < fields.length; i++) {
      var f = fields[i];
      if (f && !String(f.value || '').trim()) {
        f.focus();
        if (f.reportValidity) f.reportValidity();
        return false;
      }
    }
    return true;
  }

  function selectedText(sel) {
    if (!sel || sel.selectedIndex < 0) return '';
    var o = sel.options[sel.selectedIndex];
    if (!o || !o.value || o.value === '0') return '';
    return String(o.textContent || '').replace(/^[\s ↳]+/, '').trim();
  }
  function out(key, text) {
    var el = root.querySelector('[data-rec-onb-out="' + key + '"]');
    if (el) el.textContent = text || '—';
  }
  function checkedLabel(name) {
    var c = form && form.querySelector('[name="' + name + '"]:checked');
    var n = c && c.closest('label') ? c.closest('label').querySelector('.rec-onb__choice-name') : null;
    if (!n) return '';
    var copy = n.cloneNode(true);
    copy.querySelectorAll('.rec-onb__tag').forEach(function (t) { t.remove(); });
    return String(copy.textContent || '').trim();
  }
  // Libellés rendus côté serveur (traduits avec la page).
  function t(key) {
    var el = root.querySelector('[data-rec-onb-t="' + key + '"]');
    return el ? String(el.textContent || '').trim() : key;
  }
  var steamRe = /^(?:https?:\/\/)?(?:www\.)?steamcommunity\.com\/(?:profiles\/\d{17}|id\/[A-Za-z0-9_-]{2,64})\/?$|^\d{17}$/;
  function refresh() {
    if (!form) return;
    var first = String(form.first_name.value || '').trim();
    var last = String(form.last_name.value || '').trim();
    var name = (first + ' ' + last).trim();
    out('name', name);
    var title = root.querySelector('[data-rec-onb-name]');
    if (title && name) title.textContent = name;
    var ini = root.querySelector('[data-rec-onb-initials]');
    if (ini && name) ini.textContent = name.split(/\s+/).slice(0, 2).map(function (p) { return p.charAt(0).toUpperCase(); }).join('');
    out('callsign', String(form.callsign.value || '').trim());
    var steam = String(form.steam_profile.value || '').trim();
    var st = root.querySelector('[data-rec-onb-steam-status]');
    if (st) {
      var state = steam === '' ? 'empty' : (steamRe.test(steam) ? 'ok' : 'warn');
      st.setAttribute('data-state', state);
      st.textContent = t(state);
    }
    out('steam', steam === '' ? t('tolink') : t('linked'));
    out('access', checkedLabel('access_role_id'));
    out('duty', checkedLabel('duty'));
    out('unit', selectedText(form.unit_id));
    out('grade', selectedText(form.grade_id));
    var job = selectedText(form.personnel_job_role_id);
    var named = String(form.assignment_label.value || '').trim();
    out('job', named !== '' ? named + ' (' + t('new') + ')' : job.split(' · ')[0]);
    out('referent', selectedText(form.referent_user_id));
  }

  root.querySelectorAll('[data-rec-onb-filter]').forEach(function (input) {
    var sel = document.getElementById(input.getAttribute('data-rec-onb-filter'));
    if (!sel) return;
    input.addEventListener('input', function () {
      var q = String(input.value || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').trim();
      var firstHit = null;
      sel.querySelectorAll('optgroup').forEach(function (g) {
        var any = false;
        g.querySelectorAll('option').forEach(function (o) {
          var txt = (g.label + ' ' + o.textContent).toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
          var hit = q === '' || txt.indexOf(q) !== -1;
          o.hidden = !hit;
          o.disabled = !hit && !o.selected;
          if (hit) { any = true; if (!firstHit) firstHit = o; }
        });
        g.hidden = !any;
      });
      if (q !== '' && firstHit && (sel.selectedIndex < 1 || sel.options[sel.selectedIndex].hidden)) {
        firstHit.selected = true;
        refresh();
      }
    });
  });

  tabs.forEach(function (t) {
    t.addEventListener('click', function () {
      var n = parseInt(t.getAttribute('data-rec-onb-tab'), 10);
      if (n > current && !validateStep(current)) return;
      show(n);
    });
  });
  root.querySelectorAll('[data-rec-onb-next]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      if (!validateStep(current)) return;
      show(current + 1);
      root.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
  });
  root.querySelectorAll('[data-rec-onb-prev]').forEach(function (btn) {
    btn.addEventListener('click', function () { show(current - 1); });
  });
  if (form) {
    form.addEventListener('input', refresh);
    form.addEventListener('change', refresh);
    form.addEventListener('submit', function (ev) {
      if (!validateStep(1)) { ev.preventDefault(); show(1); }
    });
  }
  show(1);
  refresh();
})();
</script>
