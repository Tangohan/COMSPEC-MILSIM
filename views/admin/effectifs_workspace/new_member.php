<?php
declare(strict_types=1);

/**
 * Effectifs › Ajouter un membre (EffectifsWorkspaceController::newMember → UserAdminController::store).
 * Cinq étapes ; sans JavaScript elles s'affichent toutes, à la suite. Comportements : eff-new-member.js.
 */
require_once __DIR__ . '/partials/unit_code.php';

$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$old = is_array($nmOld ?? null) ? $nmOld : [];
$o = static fn (string $k, string $d = ''): string => isset($old[$k]) && is_string($old[$k]) ? $old[$k] : $d;
$oList = static fn (string $k): array => isset($old[$k]) && is_array($old[$k]) ? array_map('strval', $old[$k]) : [];
$rosterOnly = !empty($nmRosterOnly);
$units = is_array($nmUnits ?? null) ? $nmUnits : [];
$gradeGroups = is_array($nmGradeGroups ?? null) ? $nmGradeGroups : [];
$accessRoles = is_array($nmAccessRoles ?? null) ? $nmAccessRoles : [];
$jobGroups = is_array($nmJobGroups ?? null) ? $nmJobGroups : [];
$jobMax = max(1, (int) ($nmJobMax ?? 5));
$languages = is_array($nmLanguages ?? null) && $nmLanguages !== [] ? $nmLanguages : ['fr'];
$defaultLang = $o('primary_lang', (string) ($nmDefaultLanguage ?? $languages[0]));
$canGrades = !empty($nmCanGrades);
$canAssign = !empty($nmCanAssign);
$canRoles = !empty($nmCanRoles);
$canProfiles = !empty($nmCanProfiles);
$invitesReady = !empty($nmInvitesReady);
$destinations = is_array($nmDestinations ?? null) ? $nmDestinations : [];
$flashErr = \App\Core\Session::getFlash('error');
$oldJobs = $oList('job_role_ids');
$oldOthers = $oList('other_langs');
$memberSlug = \App\Services\Rbac\CommunityAccessProfiles::SLUG_MEMBER;
$selectedAccess = (int) $o('access_role_id', '0');
if ($selectedAccess < 1) {
    foreach ($accessRoles as $r) {
        if ((string) ($r['slug'] ?? '') === $memberSlug) {
            $selectedAccess = (int) $r['id'];
        }
    }
}
$accountDefault = $o('roster_only', $rosterOnly ? '1' : '0');
$steps = [
    ['identite', 'Identité de jeu'],
    ['poste', 'Place dans l’unité'],
    ['dossier', 'Dossier'],
    ['invitation', 'Lien d’invitation'],
    ['apercu', 'Aperçu'],
];
$unitRoleSuggestions = ['Chef de groupe', 'Adjoint', 'Chef d’équipe', 'Tireur', 'Grenadier', 'Tireur d’élite', 'Infirmier', 'Opérateur radio', 'Pilote', 'Membre'];
?>
<div class="eff-nm" data-eff-new-member
     data-check-url="<?= $h(effectifs_workspace_url('nouveau/verifier')) ?>"
     data-job-max="<?= $jobMax ?>">
    <?php if ($flashErr): ?>
        <p class="eff-nm-flash" role="alert"><?= $h((string) $flashErr) ?></p>
    <?php endif; ?>

    <ol class="eff-nm-rail" aria-label="Étapes" data-nm-rail hidden>
        <?php foreach ($steps as $i => [$sid, $slabel]): ?>
            <li><button type="button" data-nm-goto="<?= $h($sid) ?>"><span class="eff-nm-rail__n"><?= $i + 1 ?></span><span><?= $h($slabel) ?></span></button></li>
        <?php endforeach; ?>
    </ol>

    <div class="eff-nm-layout">
    <form method="post" action="<?= $h(effectifs_workspace_url('nouveau')) ?>" class="eff-nm-form" id="eff-nm-form" novalidate>
        <input type="hidden" name="_csrf_token" value="<?= $h((string) ($csrfToken ?? '')) ?>">

        <!-- 1. Identité de jeu -->
        <section class="eff-nm-step" id="nm-identite" data-nm-step="identite" aria-labelledby="nm-identite-t">
            <header class="eff-nm-step__head">
                <span class="eff-nm-step__n" aria-hidden="true">1</span>
                <div>
                    <h2 id="nm-identite-t">Identité de jeu</h2>
                    <p>Ce que l’on voit sur la carte, à la radio et dans le tableur. Le reste pourra être complété par la personne elle-même.</p>
                </div>
            </header>
            <?php if ($rosterOnly): ?>
            <fieldset class="eff-nm-choice" data-nm-kind>
                <legend>Type de fiche</legend>
                <label class="eff-nm-option"><input type="radio" name="roster_only" value="1" <?= $accountDefault === '1' ? 'checked' : '' ?>>
                    <span><b>Fiche sans compte</b> Le membre n’a rien à faire : vous tenez sa fiche ici.</span></label>
                <label class="eff-nm-option"><input type="radio" name="roster_only" value="0" <?= $accountDefault !== '1' ? 'checked' : '' ?>>
                    <span><b>Compte avec connexion</b> Pour quelqu’un de l’état-major qui tiendra les fiches avec vous.</span></label>
            </fieldset>
            <?php endif; ?>
            <div class="eff-nm-grid">
                <label class="eff-nm-field">
                    <span>Indicatif</span>
                    <input type="text" name="callsign" maxlength="50" autocomplete="off" value="<?= $h($o('callsign')) ?>" data-nm-check="callsign" data-nm-preview="callsign" placeholder="ex. VIPER 2-1">
                    <small class="eff-nm-msg" data-nm-msg="callsign" aria-live="polite"></small>
                </label>
                <label class="eff-nm-field">
                    <span>Surnom <em>facultatif</em></span>
                    <input type="text" name="nickname" maxlength="60" autocomplete="off" value="<?= $h($o('nickname')) ?>" data-nm-preview="nickname"<?= $canProfiles ? '' : ' disabled' ?>>
                    <small>Le pseudo par lequel on l’appelle entre joueurs.</small>
                </label>
                <label class="eff-nm-field">
                    <span>Prénom du personnage</span>
                    <input type="text" name="first_name" maxlength="100" autocomplete="off" value="<?= $h($o('first_name')) ?>" data-nm-preview="first_name">
                </label>
                <label class="eff-nm-field">
                    <span>Nom du personnage</span>
                    <input type="text" name="last_name" maxlength="100" autocomplete="off" value="<?= $h($o('last_name')) ?>" data-nm-preview="last_name">
                </label>
            </div>
            <div class="eff-nm-account" data-nm-account>
                <div class="eff-nm-grid">
                    <label class="eff-nm-field">
                        <span>E-mail</span>
                        <input type="email" name="email" maxlength="190" autocomplete="off" inputmode="email" value="<?= $h($o('email')) ?>" data-nm-check="email" data-nm-email>
                        <small class="eff-nm-msg" data-nm-msg="email" aria-live="polite">Sert à se connecter. Aucun e-mail ne part sans votre accord.</small>
                    </label>
                    <label class="eff-nm-check eff-nm-check--inline">
                        <input type="checkbox" name="email_unknown" value="1" data-nm-email-unknown <?= $o('email_unknown') === '1' ? 'checked' : '' ?>>
                        <span>Je ne connais pas son e-mail : la personne le saisira en ouvrant son lien.</span>
                    </label>
                </div>
            </div>
            <div class="eff-nm-grid">
                <label class="eff-nm-field">
                    <span>Discord <em>si connu</em></span>
                    <input type="text" name="discord_handle" maxlength="64" autocomplete="off" value="<?= $h($o('discord_handle')) ?>" data-nm-preview="discord" placeholder="pseudo ou identifiant à 18 chiffres"<?= $canProfiles ? '' : ' disabled' ?>>
                    <small>Avec l’identifiant (clic droit sur la personne › Copier l’identifiant), le message Discord la mentionnera.</small>
                </label>
                <label class="eff-nm-field">
                    <span>Steam <em>si connu</em></span>
                    <input type="text" name="steam_id" maxlength="200" autocomplete="off" value="<?= $h($o('steam_id')) ?>" data-nm-check="steam_id" data-nm-preview="steam" placeholder="numéro à 17 chiffres ou adresse du profil">
                    <small class="eff-nm-msg" data-nm-msg="steam_id" aria-live="polite">Relie le joueur au serveur de jeu et à la carte ATAK.</small>
                </label>
            </div>
        </section>

        <!-- 2. Place dans l'unité -->
        <section class="eff-nm-step" id="nm-poste" data-nm-step="poste" aria-labelledby="nm-poste-t">
            <header class="eff-nm-step__head">
                <span class="eff-nm-step__n" aria-hidden="true">2</span>
                <div>
                    <h2 id="nm-poste-t">Place dans l’unité</h2>
                    <p>Unité, rôle tenu, emplois, grade et ce qu’il pourra faire sur le site.</p>
                </div>
            </header>
            <?php if ($canAssign): ?>
            <div class="eff-nm-grid">
                <label class="eff-nm-field">
                    <span>Unité</span>
                    <select name="unit_id" data-nm-unit>
                        <option value="">Sans unité pour l’instant</option>
                        <?php foreach ($units as $u): ?>
                            <option value="<?= (int) $u['id'] ?>" data-code="<?= $h($u['code']) ?>" data-admin="<?= $h($u['admin_id']) ?>" data-name="<?= $h($u['name']) ?>" <?= (int) $o('unit_id') === (int) $u['id'] ? 'selected' : '' ?>><?= $h($u['path']) ?><?= $u['code'] !== '' ? ' — ' . $h($u['code']) : '' ?></option>
                        <?php endforeach; ?>
                    </select>
                    <small class="eff-nm-unitcode" data-nm-unitcode aria-live="polite"></small>
                </label>
                <label class="eff-nm-field">
                    <span>Rôle dans l’unité</span>
                    <input type="text" name="unit_role" maxlength="120" list="nm-unit-roles" autocomplete="off" value="<?= $h($o('unit_role')) ?>" data-nm-preview="unit_role" placeholder="Membre">
                    <datalist id="nm-unit-roles"><?php foreach ($unitRoleSuggestions as $sug): ?><option value="<?= $h($sug) ?>"><?php endforeach; ?></datalist>
                    <small>Chef de groupe, tireur, adjoint… « Membre » si rien n’est indiqué.</small>
                </label>
            </div>
            <?php if ($jobGroups !== []): ?>
            <fieldset class="eff-nm-jobs" data-nm-jobs>
                <legend>Emplois <em><?= $jobMax ?> au plus, le premier coché est l’emploi principal</em></legend>
                <input type="search" class="eff-nm-jobs__search" placeholder="Chercher un emploi (fusilier, médecin, pilote…)" data-nm-job-search aria-label="Chercher un emploi">
                <div class="eff-nm-jobs__list">
                <?php foreach ($jobGroups as $group): ?>
                    <?php $items = is_array($group['items'] ?? null) ? $group['items'] : []; if ($items === []) { continue; } ?>
                    <details class="eff-nm-jobs__group" data-nm-job-group<?= array_intersect(array_column($items, 'value'), $oldJobs) !== [] ? ' open' : '' ?>>
                        <summary><?= $h((string) ($group['label'] ?? '')) ?> <span><?= count($items) ?></span></summary>
                        <?php foreach ($items as $item): ?>
                            <label class="eff-nm-job" data-nm-job data-search="<?= $h(mb_strtolower((string) ($item['label'] ?? '') . ' ' . (string) ($item['detail'] ?? '') . ' ' . (string) ($item['abbreviation'] ?? '') . ' ' . implode(' ', array_column($item['tags'] ?? [], 'name')))) ?>"<?= ($item['description'] ?? '') !== '' ? ' title="' . $h((string) $item['description']) . '"' : '' ?>>
                                <input type="checkbox" name="job_role_ids[]" value="<?= $h((string) $item['value']) ?>" data-label="<?= $h((string) $item['label']) ?>" <?= in_array((string) $item['value'], $oldJobs, true) ? 'checked' : '' ?>>
                                <span><?= \App\Support\JobRoleBadge::html($item, false) ?><?= $h((string) $item['label']) ?> <?= \App\Support\JobRoleBadge::origin((string) ($item['origin'] ?? '')) ?><?php if (trim((string) ($item['detail'] ?? '')) !== ''): ?><small><?= $h((string) $item['detail']) ?></small><?php endif; ?></span>
                            </label>
                        <?php endforeach; ?>
                    </details>
                <?php endforeach; ?>
                </div>
                <input type="hidden" name="primary_job_role_id" value="" data-nm-primary-job>
                <small class="eff-nm-msg" data-nm-job-msg aria-live="polite"></small>
            </fieldset>
            <?php endif; ?>
            <?php else: ?>
            <p class="eff-nm-locked">Unité, rôle et emplois : réservés à qui gère les affectations. Le membre sera créé sans unité.</p>
            <?php endif; ?>

            <div class="eff-nm-grid">
                <?php if ($canGrades): ?>
                <label class="eff-nm-field">
                    <span>Grade</span>
                    <select name="grade_id" data-nm-grade>
                        <option value="">Sans grade pour l’instant</option>
                        <?php foreach ($gradeGroups as $gg): ?>
                            <optgroup label="<?= $h((string) ($gg['label'] ?? '')) ?>">
                                <?php foreach (($gg['grades'] ?? []) as $g): ?>
                                    <option value="<?= (int) $g['id'] ?>" <?= (int) $o('grade_id') === (int) $g['id'] ? 'selected' : '' ?>><?= $h((string) ($g['label_long'] ?? $g['name'] ?? '')) ?></option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endforeach; ?>
                    </select>
                </label>
                <?php endif; ?>
                <?php if ($canProfiles): ?>
                <label class="eff-nm-field">
                    <span>Date d’engagement</span>
                    <input type="date" name="enlistment_date" value="<?= $h($o('enlistment_date', date('Y-m-d'))) ?>" max="<?= $h(date('Y-m-d', strtotime('+1 year'))) ?>" data-nm-preview="enlist">
                    <small>Point de départ de l’ancienneté dans la communauté.</small>
                </label>
                <?php endif; ?>
            </div>

            <?php if ($canRoles && $accessRoles !== []): ?>
            <fieldset class="eff-nm-choice eff-nm-choice--cards" data-nm-access>
                <legend>Niveau d’accès sur le site</legend>
                <?php foreach ($accessRoles as $r): ?>
                    <label class="eff-nm-option">
                        <input type="radio" name="access_role_id" value="<?= (int) $r['id'] ?>" data-label="<?= $h((string) ($r['name'] ?? '')) ?>" <?= (int) $r['id'] === $selectedAccess ? 'checked' : '' ?>>
                        <span><b><?= $h((string) ($r['name'] ?? '')) ?></b><?= $h(trim((string) ($r['description'] ?? ''))) ?></span>
                    </label>
                <?php endforeach; ?>
            </fieldset>
            <?php else: ?>
            <p class="eff-nm-locked">Niveau d’accès : Membre. Seul qui gère les niveaux d’accès peut en donner un autre.</p>
            <?php endif; ?>
        </section>

        <!-- 3. Dossier -->
        <section class="eff-nm-step" id="nm-dossier" data-nm-step="dossier" aria-labelledby="nm-dossier-t">
            <header class="eff-nm-step__head">
                <span class="eff-nm-step__n" aria-hidden="true">3</span>
                <div>
                    <h2 id="nm-dossier-t">Dossier</h2>
                    <p>Langues parlées en opération et notes pour l’encadrement.</p>
                </div>
            </header>
            <?php if ($canProfiles): ?>
            <fieldset class="eff-nm-langs">
                <legend>Langue principale en jeu</legend>
                <?php foreach ($languages as $code): ?>
                    <label class="eff-nm-lang"><input type="radio" name="primary_lang" value="<?= $h($code) ?>" data-flag="<?= $h(\App\Support\CommunityLanguages::flag($code)) ?>" <?= $code === $defaultLang ? 'checked' : '' ?>>
                        <span aria-hidden="true"><?= $h(\App\Support\CommunityLanguages::flag($code)) ?></span><?= $h(\App\Support\CommunityLanguages::fullLabel($code)) ?></label>
                <?php endforeach; ?>
            </fieldset>
            <?php if (count($languages) > 1): ?>
            <fieldset class="eff-nm-langs">
                <legend>Comprend aussi</legend>
                <?php foreach ($languages as $code): ?>
                    <label class="eff-nm-lang"><input type="checkbox" name="other_langs[]" value="<?= $h($code) ?>" data-flag="<?= $h(\App\Support\CommunityLanguages::flag($code)) ?>" <?= in_array($code, $oldOthers, true) ? 'checked' : '' ?>>
                        <span aria-hidden="true"><?= $h(\App\Support\CommunityLanguages::flag($code)) ?></span><?= $h(\App\Support\CommunityLanguages::fullLabel($code)) ?></label>
                <?php endforeach; ?>
            </fieldset>
            <?php endif; ?>
            <label class="eff-nm-field">
                <span>Notes de l’encadrement <em>facultatif</em></span>
                <textarea name="notes" rows="4" maxlength="4000" placeholder="Recruté via Discord, disponible le week-end, ancien de…"><?= $h($o('notes')) ?></textarea>
                <small>Visibles par l’encadrement sur la fiche, jamais par le membre.</small>
            </label>
            <?php else: ?>
            <p class="eff-nm-locked">Langues et notes : réservées à qui modifie les dossiers.</p>
            <?php endif; ?>
        </section>

        <!-- 4. Lien d'invitation -->
        <section class="eff-nm-step" id="nm-invitation" data-nm-step="invitation" aria-labelledby="nm-invitation-t" data-nm-invite-step>
            <header class="eff-nm-step__head">
                <span class="eff-nm-step__n" aria-hidden="true">4</span>
                <div>
                    <h2 id="nm-invitation-t">Lien d’invitation</h2>
                    <p>Un lien personnel pour que la personne crée son mot de passe, puis relie Discord et Steam, ajoute son portrait et protège son compte.</p>
                </div>
            </header>
            <?php if (!$invitesReady): ?>
                <p class="eff-nm-locked">Les liens d’invitation seront disponibles après la prochaine mise à jour de la base. La personne pourra utiliser « Mot de passe oublié ».</p>
                <input type="hidden" name="invite" value="0">
            <?php else: ?>
            <label class="eff-nm-check">
                <input type="checkbox" name="invite" value="1" data-nm-invite <?= $o('invite', '1') === '1' ? 'checked' : '' ?>>
                <span><b>Créer son lien maintenant</b> Vous pourrez le copier sur la fiche juste après, ou plus tard.</span>
            </label>
            <div class="eff-nm-invite" data-nm-invite-opts>
                <fieldset class="eff-nm-seg">
                    <legend>Valable</legend>
                    <?php foreach ([24, 48] as $hours): ?>
                        <label><input type="radio" name="duration_hours" value="<?= $hours ?>" <?= (int) $o('duration_hours', '48') === $hours ? 'checked' : '' ?>><span><?= $hours ?> heures</span></label>
                    <?php endforeach; ?>
                </fieldset>
                <label class="eff-nm-check">
                    <input type="checkbox" name="single_use" value="1" <?= $o('single_use') === '1' ? 'checked' : '' ?>>
                    <span><b>Usage unique</b> Le lien se ferme dès que le compte est activé.</span>
                </label>
                <fieldset class="eff-nm-send">
                    <legend>L’envoyer tout de suite <em>facultatif</em></legend>
                    <?php if ($destinations !== []): ?>
                    <label class="eff-nm-check">
                        <input type="checkbox" name="send_discord" value="1" data-nm-send-discord>
                        <span><b>Publier sur Discord</b> Message avec son lien, son unité et l’expiration ; mention si son identifiant est connu.</span>
                    </label>
                    <label class="eff-nm-field eff-nm-field--inset" data-nm-destination hidden>
                        <span>Salon</span>
                        <select name="destination">
                            <?php foreach ($destinations as $d): ?>
                                <option value="<?= $h($d['id']) ?>" <?= $d['preferred'] ? 'selected' : '' ?>><?= $h($d['label']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small>Le lien ouvre le compte de cette personne : choisissez un salon où elle est seule avec l’encadrement, ou cochez « Usage unique ».</small>
                    </label>
                    <?php else: ?>
                    <p class="eff-nm-hint">Pour publier le lien sur Discord en un clic, reliez un salon dans <a href="<?= $h(url('back-office/integrations') . '#relais-discord') ?>">Intégrations › Discord</a>.</p>
                    <?php endif; ?>
                    <label class="eff-nm-check" data-nm-send-email-row>
                        <input type="checkbox" name="send_email" value="1" data-nm-send-email>
                        <span><b>Envoyer aussi par e-mail</b> Un seul e-mail, à l’adresse saisie. Rien ne part si vous ne cochez pas.</span>
                    </label>
                </fieldset>
            </div>
            <?php endif; ?>
        </section>

        <!-- 5. Aperçu -->
        <section class="eff-nm-step" id="nm-apercu" data-nm-step="apercu" aria-labelledby="nm-apercu-t">
            <header class="eff-nm-step__head">
                <span class="eff-nm-step__n" aria-hidden="true">5</span>
                <div>
                    <h2 id="nm-apercu-t">Aperçu avant création</h2>
                    <p>Relisez la fiche : tout reste modifiable ensuite depuis le tableur ou la fiche.</p>
                </div>
            </header>
            <dl class="eff-nm-summary" data-nm-summary></dl>
            <div class="eff-nm-actions">
                <button type="submit" class="eff-nm-btn eff-nm-btn--solid" data-nm-submit>Créer la fiche</button>
                <a class="eff-nm-btn" href="<?= $h(effectifs_workspace_url()) ?>">Annuler</a>
            </div>
        </section>

        <nav class="eff-nm-pager" data-nm-pager hidden>
            <button type="button" class="eff-nm-btn" data-nm-prev>Étape précédente</button>
            <button type="button" class="eff-nm-btn eff-nm-btn--solid" data-nm-next>Étape suivante</button>
        </nav>
    </form>

    <aside class="eff-nm-card" aria-label="Aperçu de la fiche" aria-live="polite">
        <p class="eff-nm-card__kicker">Aperçu de la fiche</p>
        <p class="eff-nm-card__grade" data-pv="grade">Sans grade</p>
        <p class="eff-nm-card__callsign" data-pv="callsign">Indicatif</p>
        <p class="eff-nm-card__name" data-pv="name">Nom du personnage</p>
        <p class="eff-nm-card__unit"><span data-pv="unitcode"></span><span data-pv="unit">Sans unité</span></p>
        <dl class="eff-nm-card__facts">
            <div><dt>Rôle</dt><dd data-pv="unit_role">Membre</dd></div>
            <div><dt>Emplois</dt><dd data-pv="jobs">Aucun</dd></div>
            <div><dt>Accès</dt><dd data-pv="access">Membre</dd></div>
            <div><dt>Langues</dt><dd data-pv="langs">—</dd></div>
            <div><dt>Discord</dt><dd data-pv="discord">À relier par la personne</dd></div>
            <div><dt>Steam</dt><dd data-pv="steam">À relier par la personne</dd></div>
        </dl>
        <p class="eff-nm-card__invite" data-pv="invite"></p>
    </aside>
    </div>
</div>
<script src="<?= $h(asset_url('assets/js/eff-new-member.js')) ?>" defer></script>
