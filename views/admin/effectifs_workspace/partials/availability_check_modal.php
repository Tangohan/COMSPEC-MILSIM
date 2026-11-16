<?php

declare(strict_types=1);

/**
 * Fenêtre « Vérifier la disponibilité » (Effectifs) : Aide, Recherche (étapes une à une), Validation.
 * Incluse par la fiche d'un membre et par le tableur des effectifs. Elle s'ouvre avec un bouton [data-avc-open]
 * (data-avc-ids : un identifiant ou une liste séparée par des virgules ; data-avc-name : nom affiché pour un membre seul ;
 * data-avc-from-selection : prend les cases cochées du tableur). Le script eff-availability-check.js la pilote.
 *
 * Variables : $avcRules (min_minutes, gap_minutes), $avcCanSave (étape de base appliquée), $avcSettingsUrl (réglage de la
 * durée qui compte, vide sans le droit), $csrfToken.
 */

use App\Services\Effectifs\Availability\AvailabilityCheck as Avc;
use App\Services\Effectifs\Availability\AvailabilityCheckRecorder as AvcRec;
use App\Services\Personnel\PlayerSessionService as AvcSessions;

$avcH = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$avcMin = max(1, (int) ($avcRules['min_minutes'] ?? AvcSessions::DEFAULT_MIN_MINUTES));
$avcGap = max(1, (int) ($avcRules['gap_minutes'] ?? AvcSessions::DEFAULT_GAP_MINUTES));
$avcSettings = trim((string) ($avcSettingsUrl ?? ''));
$avcSave = (bool) ($avcCanSave ?? false);
$avcRunTpl = effectifs_workspace_url('membres/__ID__/disponibilite/verification');
?>
<dialog class="eff-dialog eff-dialog--wide eff-avc" id="eff-dlg-avc" aria-labelledby="eff-avc-title" aria-describedby="eff-avc-sub"
    data-avc-run="<?= $avcH($avcRunTpl) ?>"
    data-avc-save="<?= $avcH($avcRunTpl) ?>"
    data-avc-bulk="<?= $avcH(effectifs_workspace_url('disponibilite/verifications')) ?>"
    data-avc-csrf="<?= $avcH((string) ($csrfToken ?? '')) ?>"
    data-avc-max="<?= (int) \App\Controllers\Admin\MemberAvailabilityCheckController::BULK_MAX ?>">
    <div class="eff-avc__frame">
        <header class="eff-avc__head">
            <div class="eff-avc__titles">
                <h2 id="eff-avc-title">Vérifier la disponibilité</h2>
                <p id="eff-avc-sub" class="eff-avc__sub" data-avc-subject>Un membre</p>
            </div>
            <button type="button" class="eff-avc__close" data-avc-close aria-label="Fermer la fenêtre">
                <svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
            </button>
        </header>

        <nav class="eff-avc__steps" aria-label="Parties de la vérification">
            <ol>
                <li><button type="button" data-avc-goto="aide" aria-current="step"><span class="eff-avc__num" aria-hidden="true">1</span>Aide</button></li>
                <li><button type="button" data-avc-goto="recherche" disabled><span class="eff-avc__num" aria-hidden="true">2</span>Recherche</button></li>
                <li><button type="button" data-avc-goto="validation" disabled><span class="eff-avc__num" aria-hidden="true">3</span>Validation</button></li>
            </ol>
        </nav>

        <div class="eff-avc__body">
            <section class="eff-avc__part" data-avc-part="aide" aria-labelledby="eff-avc-aide-t">
                <h3 id="eff-avc-aide-t" class="eff-avc__h">Avant de lancer</h3>
                <p class="eff-avc__lead">La vérification relit ce que le site enregistre déjà sur le membre, propose une conclusion, puis garde la trace de la vôtre.</p>
                <div class="eff-avc__help">
                    <article>
                        <h4>À quoi elle sert</h4>
                        <p>Savoir si un membre est réellement présent avant de compter sur lui, sans lui écrire et sans fouiller chaque onglet.</p>
                    </article>
                    <article>
                        <h4>Ce qui compte comme session</h4>
                        <p><?= $avcH(str_replace(['%1', '%2'], [(string) $avcGap, (string) $avcMin], 'Une présence continue sur le serveur de la communauté. Une reconnexion de moins de %1 minutes reste la même session. Elle compte quand elle dure %2 minutes ou plus.')) ?></p>
                        <?php if ($avcSettings !== ''): ?>
                            <p><a class="eff-avc__link" href="<?= $avcH($avcSettings) ?>" data-ath-modal="off">Régler la durée qui compte</a></p>
                        <?php endif; ?>
                    </article>
                    <article>
                        <h4>Quand l’utiliser</h4>
                        <p>Avant une affectation ou une mission, quand un membre ne donne plus de nouvelles, ou lors de la revue régulière des effectifs.</p>
                    </article>
                    <article>
                        <h4>Ce qui sera enregistré</h4>
                        <p>Votre nom, la date, la conclusion proposée et celle que vous retenez, votre note, la suite choisie et le résultat de chaque étape. La vérification apparaît dans les onglets Disponibilité, Suivi du dossier et Historique.</p>
                    </article>
                </div>
                <details class="eff-avc__rules">
                    <summary>Comment la conclusion est proposée</summary>
                    <ol>
                        <li><b><?= $avcH(Avc::conclusionLabel(Avc::ABSENT)) ?></b> : une absence déclarée couvre aujourd’hui, ou la position du membre ne l’engage pas en service.</li>
                        <li><b><?= $avcH(Avc::conclusionLabel(Avc::AVAILABLE)) ?></b> : au moins 1 session qui compte sur 7 jours ou 2 sur 30 jours.</li>
                        <li><b><?= $avcH(Avc::conclusionLabel(Avc::LOW)) ?></b> : moins de 2 sessions qui comptent sur 30 jours, mais au moins une sur 90 jours.</li>
                        <li><b><?= $avcH(Avc::conclusionLabel(Avc::INACTIVE)) ?></b> : aucune session qui compte sur 90 jours.</li>
                        <li>Sans sessions de jeu (Steam non relié), la présence aux convocations puis la connexion au site sur 30 jours prennent le relais.</li>
                    </ol>
                </details>
                <?php if (!$avcSave): ?>
                    <p class="eff-avc__notice" role="note"><?= $avcH(AvcRec::degradedMessage()) ?></p>
                <?php endif; ?>
            </section>

            <section class="eff-avc__part" data-avc-part="recherche" aria-labelledby="eff-avc-rech-t" hidden>
                <h3 id="eff-avc-rech-t" class="eff-avc__h">Étapes de recherche</h3>
                <p class="eff-avc__lead" data-avc-only="single">Chaque étape lit une source du site, puis donne son résultat, sa source et sa date.</p>
                <p class="eff-avc__lead" data-avc-only="bulk" hidden>Les étapes tournent pour chaque membre sélectionné, l’un après l’autre.</p>
                <p class="eff-avc__progress" data-avc-progress aria-hidden="true"></p>
                <ol class="eff-avc__list" data-avc-list data-avc-only="single"></ol>
                <div class="eff-avc__tablewrap" data-avc-only="bulk" hidden>
                    <table class="eff-avc__table">
                        <caption class="eff-fm-sr sr-only">Résultat de la vérification, membre par membre</caption>
                        <thead>
                            <tr>
                                <th scope="col">Membre</th>
                                <th scope="col">Étapes</th>
                                <th scope="col">Conclusion proposée</th>
                            </tr>
                        </thead>
                        <tbody data-avc-rows></tbody>
                    </table>
                </div>
                <div class="eff-avc__verdict" data-avc-verdict hidden>
                    <span class="eff-avc__dot" data-avc-verdict-dot aria-hidden="true"></span>
                    <div>
                        <p class="eff-avc__verdict-v">Conclusion proposée : <b data-avc-verdict-label></b></p>
                        <p class="eff-avc__verdict-r" data-avc-verdict-rule></p>
                    </div>
                </div>
            </section>

            <section class="eff-avc__part" data-avc-part="validation" aria-labelledby="eff-avc-val-t" hidden>
                <h3 id="eff-avc-val-t" class="eff-avc__h">Validation</h3>
                <p class="eff-avc__lead" data-avc-only="single">Confirmez la conclusion ou corrigez-la. Une correction demande une note.</p>
                <form class="eff-avc__form" data-avc-form novalidate>
                    <fieldset class="eff-avc__choices" data-avc-only="single">
                        <legend>Conclusion retenue</legend>
                        <?php foreach (Avc::conclusionLabels() as $avcKey => $avcLabel): ?>
                            <label class="eff-avc__choice">
                                <input type="radio" name="conclusion" value="<?= $avcH($avcKey) ?>">
                                <span class="eff-avc__choice-t"><span class="eff-avc__dot is-<?= $avcH($avcKey) ?>" aria-hidden="true"></span><?= $avcH($avcLabel) ?></span>
                                <span class="eff-avc__tag" data-avc-proposed-tag hidden>proposée</span>
                            </label>
                        <?php endforeach; ?>
                    </fieldset>
                    <div class="eff-avc__field" data-avc-only="single">
                        <label for="eff-avc-note">Note pour le dossier <span data-avc-note-req hidden>(obligatoire pour une correction)</span></label>
                        <textarea id="eff-avc-note" name="note" rows="3" maxlength="2000" aria-describedby="eff-avc-note-hint"></textarea>
                        <p id="eff-avc-note-hint" class="eff-avc__hint">Visible des responsables dans le suivi du dossier.</p>
                    </div>
                    <div class="eff-avc__bulkbar" data-avc-only="bulk" hidden>
                        <p>Chaque ligne garde la conclusion proposée, sauf si vous la changez. Une ligne corrigée demande une note.</p>
                    </div>
                    <div class="eff-avc__tablewrap" data-avc-only="bulk" hidden>
                        <table class="eff-avc__table eff-avc__table--val">
                            <caption class="sr-only">Validation, membre par membre</caption>
                            <thead>
                                <tr>
                                    <th scope="col">Membre</th>
                                    <th scope="col">Conclusion retenue</th>
                                    <th scope="col">Note</th>
                                    <th scope="col"><span class="sr-only">Enregistrer la ligne</span></th>
                                </tr>
                            </thead>
                            <tbody data-avc-val-rows></tbody>
                        </table>
                    </div>
                    <fieldset class="eff-avc__choices eff-avc__choices--follow">
                        <legend>Suite à donner</legend>
                        <?php foreach (AvcRec::followUpLabels() as $avcKey => $avcLabel): ?>
                            <label class="eff-avc__choice">
                                <input type="radio" name="follow_up" value="<?= $avcH($avcKey) ?>"<?= $avcKey === AvcRec::FOLLOW_NONE ? ' checked' : '' ?>>
                                <span class="eff-avc__choice-t"><?= $avcH($avcLabel) ?></span>
                                <?php if ($avcKey === AvcRec::FOLLOW_RECHECK): ?>
                                    <select name="recheck_days" aria-label="Délai de la relance">
                                        <?php foreach (AvcRec::RECHECK_DAYS as $avcDays): ?>
                                            <option value="<?= (int) $avcDays ?>"<?= $avcDays === 14 ? ' selected' : '' ?>><?= $avcH(str_replace('%1', (string) $avcDays, 'Dans %1 jours')) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                <?php endif; ?>
                                <span class="eff-avc__choice-h"><?= $avcH(match ($avcKey) {
                                    AvcRec::FOLLOW_RECHECK => 'Une étape « Revérifier la disponibilité » est ajoutée au suivi du dossier.',
                                    AvcRec::FOLLOW_RH => 'Une étape de suivi RH, à traiter sous 7 jours, est ajoutée au suivi du dossier.',
                                    AvcRec::FOLLOW_POSITION => 'Le formulaire Position et statut de la fiche s’ouvre après l’enregistrement.',
                                    default => 'La vérification est seulement enregistrée.',
                                }) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </fieldset>
                    <p class="eff-avc__error" data-avc-error role="alert" hidden></p>
                </form>
            </section>

            <section class="eff-avc__part" data-avc-part="fin" aria-labelledby="eff-avc-fin-t" hidden>
                <h3 id="eff-avc-fin-t" class="eff-avc__h">Vérification enregistrée</h3>
                <p class="eff-avc__lead" data-avc-done-msg></p>
                <p class="eff-avc__lead" data-avc-done-notice hidden></p>
                <p data-avc-position hidden><a class="eff-avc__btn is-primary" href="#" data-avc-position-link data-ath-modal="off">Ouvrir Position et statut</a></p>
            </section>
        </div>

        <footer class="eff-avc__foot">
            <p class="sr-only" aria-live="polite" aria-atomic="true" data-avc-live></p>
            <button type="button" class="eff-avc__btn is-ghost" data-avc-back hidden>Retour</button>
            <span class="eff-avc__spacer"></span>
            <button type="button" class="eff-avc__btn" data-avc-close>Fermer</button>
            <button type="button" class="eff-avc__btn is-primary" data-avc-start>Lancer la vérification</button>
            <button type="button" class="eff-avc__btn is-primary" data-avc-next hidden disabled>Passer à la validation</button>
            <button type="button" class="eff-avc__btn is-primary" data-avc-submit hidden<?= $avcSave ? '' : ' disabled' ?>>Enregistrer la vérification</button>
        </footer>
    </div>

    <div hidden data-avc-texts>
        <span data-t="running">En cours</span>
        <span data-t="pending">En attente</span>
        <span data-t="error">Erreur</span>
        <span data-t="progress">Étape %1 sur %2</span>
        <span data-t="live">Étape %1 sur %2 : %3, %4.</span>
        <span data-t="live_member">Membre %1 sur %2 : %3, conclusion proposée %4.</span>
        <span data-t="live_done">Recherche terminée. Conclusion proposée : %1.</span>
        <span data-t="source">Source : %1, au %2</span>
        <span data-t="one">Un membre</span>
        <span data-t="many">%1 membres sélectionnés</span>
        <span data-t="none">Cochez au moins un membre dans le tableau.</span>
        <span data-t="too_many">Vérifiez %1 membres au plus à la fois.</span>
        <span data-t="counts">%1 OK, %2 à surveiller, %3 sans donnée</span>
        <span data-t="proposed">proposée</span>
        <span data-t="save_row">Enregistrer</span>
        <span data-t="saved_row">Enregistrée</span>
        <span data-t="save_all">Enregistrer toutes les lignes</span>
        <span data-t="note_needed">Expliquez la correction dans la note (3 caractères au moins).</span>
        <span data-t="network">La vérification n’a pas abouti. Réessayez.</span>
        <span data-t="note_placeholder">Note (obligatoire si corrigée)</span>
    </div>
    <div hidden data-avc-labels>
        <?php foreach (Avc::conclusionLabels() as $avcKey => $avcLabel): ?>
            <span data-k="<?= $avcH($avcKey) ?>"><?= $avcH($avcLabel) ?></span>
        <?php endforeach; ?>
    </div>
</dialog>
