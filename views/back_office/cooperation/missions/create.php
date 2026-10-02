<?php
declare(strict_types=1);

/**
 * Assistant de création en trois étapes (intitulé & cadrage → unités à inviter → récapitulatif).
 * Un seul formulaire POST /back-office/cooperation/missions : sans JavaScript, les trois étapes sont
 * affichées l’une sous l’autre et le formulaire reste complet. « Enregistrer en brouillon » est
 * disponible à chaque étape (send_invitations absent ⇒ aucune invitation envoyée).
 */

$csrf = $csrfToken ?? \App\Core\Csrf::token();
$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$typoChoices = is_array($cooperationTypologyChoices ?? null) ? $cooperationTypologyChoices : [];
$prioChoices = is_array($cooperationPriorityChoices ?? null) ? $cooperationPriorityChoices : [];
$tenantChoices = is_array($cooperationTenantChoices ?? null) ? $cooperationTenantChoices : [];
$fieldsOn = !empty($interteamProposalFieldsEnabled);
$wizardSteps = ['Intitulé & cadrage', 'Unités à inviter', 'Récapitulatif & envoi'];
?>
<div class="max-w-3xl mx-auto px-6 py-10">
    <a href="<?= $h(cooperation_mission_index_url()) ?>" class="text-sm font-medium text-slate-600 hover:text-slate-900 underline">← Retour à la liste</a>
    <h1 class="mt-4 text-2xl font-black text-slate-900">Nouvelle coopération inter-unités</h1>
    <p class="mt-2 text-sm text-slate-600 leading-relaxed">Décrivez la démarche, choisissez les unités à solliciter, puis envoyez les invitations. Vous pouvez enregistrer un brouillon à tout moment.</p>

    <form method="post" action="<?= $h(cooperation_mission_index_url()) ?>" class="coop-wizard mt-6" data-coop-wizard novalidate>
        <input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>">

        <div class="coop-progress" data-wizard-progress>
            <p class="coop-progress__title" data-wizard-heading>Étape 1 sur 3 — <?= $h($wizardSteps[0]) ?></p>
            <p class="coop-progress__next" data-wizard-next>Étape suivante : <?= $h($wizardSteps[1]) ?></p>
            <div class="coop-progress__steps">
                <?php
                $steps = [];
                foreach ($wizardSteps as $i => $lab) {
                    $steps[] = ['label' => $lab, 'active' => $i === 0, 'done' => false];
                }
                require base_path('views/partials/ui/stepper.php');
                ?>
            </div>
        </div>

        <fieldset class="coop-wizard__step" data-wizard-step="1">
            <legend class="coop-wizard__legend">1. Intitulé & cadrage</legend>
            <div>
                <label for="itm-title" class="block text-sm font-semibold text-slate-900">Intitulé <span class="text-rose-700" aria-hidden="true">*</span></label>
                <input id="itm-title" name="title" type="text" required minlength="3" maxlength="255" aria-describedby="itm-title-msg" class="mt-2 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" placeholder="Ex. Exercice conjoint — coordination entre unités" data-summary="title">
                <p id="itm-title-msg" class="fr-message">Entre 3 et 255 caractères. Un intitulé explicite aide les unités invitées à décider.</p>
            </div>
            <?php if ($fieldsOn): ?>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="itm-typology" class="block text-sm font-semibold text-slate-900">Typologie</label>
                    <select id="itm-typology" name="cooperation_typology" class="mt-2 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" data-summary="typology">
                        <option value="">— Non précisée —</option>
                        <?php foreach ($typoChoices as $val => $label): ?>
                        <option value="<?= $h((string) $val) ?>"><?= $h((string) $label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="itm-priority" class="block text-sm font-semibold text-slate-900">Priorité</label>
                    <select id="itm-priority" name="cooperation_priority" class="mt-2 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" data-summary="priority">
                        <?php foreach ($prioChoices as $val => $label): ?>
                        <option value="<?= $h((string) $val) ?>"<?= $val === 'routine' ? ' selected' : '' ?>><?= $h((string) $label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="sm:col-span-2">
                    <label for="itm-deadline" class="block text-sm font-semibold text-slate-900">Date limite de réponse <span class="font-normal text-slate-500">(facultatif)</span></label>
                    <input id="itm-deadline" type="datetime-local" name="proposal_deadline_at" class="mt-2 w-full max-w-xs rounded-lg border border-slate-200 px-3 py-2 text-sm" data-summary="deadline">
                    <p class="fr-message">Les unités qui n’ont pas répondu reçoivent un rappel deux jours avant.</p>
                </div>
            </div>
            <?php endif; ?>
        </fieldset>

        <fieldset class="coop-wizard__step" data-wizard-step="2">
            <legend class="coop-wizard__legend">2. Unités à inviter</legend>
            <p class="text-sm text-slate-600">Cochez une ou plusieurs unités. Elles recevront l’invitation à l’envoi (étape 3) et pourront accepter ou refuser.</p>
            <?php if ($tenantChoices === []): ?>
            <p class="text-sm text-slate-500">Aucune autre unité n’est disponible pour le moment. Enregistrez un brouillon et invitez plus tard.</p>
            <?php else: ?>
            <label class="block">
                <span class="sr-only">Filtrer la liste des unités</span>
                <input type="search" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" placeholder="Filtrer les unités…" autocomplete="off" data-wizard-unit-filter>
            </label>
            <ul class="coop-wizard__units" data-wizard-units>
                <?php foreach ($tenantChoices as $t): ?>
                <li data-unit-name="<?= $h(mb_strtolower((string) $t['name'])) ?>">
                    <label class="coop-wizard__unit">
                        <input type="checkbox" name="partner_tenant_ids[]" value="<?= (int) $t['id'] ?>" data-unit-label="<?= $h((string) $t['name']) ?>">
                        <span><?= $h((string) $t['name']) ?></span>
                    </label>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </fieldset>

        <fieldset class="coop-wizard__step" data-wizard-step="3">
            <legend class="coop-wizard__legend">3. Récapitulatif & envoi</legend>
            <dl class="coop-wizard__recap" data-wizard-recap>
                <div><dt>Intitulé</dt><dd data-recap="title">—</dd></div>
                <?php if ($fieldsOn): ?>
                <div><dt>Typologie</dt><dd data-recap="typology">Non précisée</dd></div>
                <div><dt>Priorité</dt><dd data-recap="priority">Routine</dd></div>
                <div><dt>Réponse attendue avant</dt><dd data-recap="deadline">Pas de date limite</dd></div>
                <?php endif; ?>
                <div><dt>Unités invitées</dt><dd data-recap="units">Aucune (brouillon)</dd></div>
            </dl>
            <p class="text-sm text-slate-600">À l’envoi, chaque unité sélectionnée reçoit l’invitation (portail et courriel à ses responsables habilités). Le lancement interviendra quand toutes auront répondu.</p>
        </fieldset>

        <div class="coop-wizard__actions">
            <button type="button" class="coop-wizard__btn coop-wizard__btn--ghost" data-wizard-prev hidden>Précédent</button>
            <span class="flex-1"></span>
            <button type="submit" class="coop-wizard__btn coop-wizard__btn--ghost" name="save_draft" value="1">Enregistrer en brouillon</button>
            <button type="button" class="coop-wizard__btn coop-wizard__btn--primary" data-wizard-next-btn hidden>Suivant</button>
            <button type="submit" class="coop-wizard__btn coop-wizard__btn--primary" name="send_invitations" value="1" data-wizard-send>Créer et envoyer les invitations</button>
        </div>
    </form>
</div>
<script defer src="<?= $h(asset_url('assets/js/cooperation/create-wizard.js')) ?>"></script>
