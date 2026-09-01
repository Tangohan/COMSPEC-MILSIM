<?php
declare(strict_types=1);

/** @var array<string, mixed>|null $dashboard_rh_parcours */
$rh = is_array($dashboard_rh_parcours ?? null) ? $dashboard_rh_parcours : [];
$showPersonnel = !empty($rh['show_personnel']);
$showOffers = !empty($rh['show_offers']);
if (!$showPersonnel && !$showOffers) {
    return;
}

$csrf = htmlspecialchars((string) ($rh['csrf'] ?? ''), ENT_QUOTES, 'UTF-8');
$workspaceUrl = (string) ($rh['workspace_url'] ?? url('personnel/mon-espace-rh'));
$offers = is_array($rh['offers'] ?? null) ? $rh['offers'] : [];
$absenceReady = !empty($rh['absence_ready']);
$activeAbsences = is_array($rh['active_absences'] ?? null) ? $rh['active_absences'] : [];
$absenceReasonLabels = is_array($rh['absence_reason_labels'] ?? null) ? $rh['absence_reason_labels'] : [];
$mobilityReady = !empty($rh['mobility_ready']);
$mobilityTypeLabels = is_array($rh['mobility_type_labels'] ?? null) ? $rh['mobility_type_labels'] : [];
$myMobility = is_array($rh['my_mobility'] ?? null) ? $rh['my_mobility'] : [];
$elevationCatalog = is_array($rh['elevation_catalog'] ?? null) ? $rh['elevation_catalog'] : [];
$elevationCooldown = isset($rh['elevation_cooldown_seconds']) ? $rh['elevation_cooldown_seconds'] : null;
$elevationHasRecipients = !empty($rh['elevation_has_recipients']);

$mobilityStatusLabels = [
    'pending' => 'En attente',
    'approved' => 'Acceptée',
    'rejected' => 'Refusée',
    'cancelled' => 'Annulée',
    'applied' => 'Prise en compte',
];

$formatAbsenceDate = static function (?string $ymd): string {
    if ($ymd === null || $ymd === '') {
        return '';
    }
    $ts = strtotime($ymd);
    if ($ts === false) {
        return (string) $ymd;
    }

    return date('d/m/Y', $ts);
};

$absencePeriodLabel = static function (array $row) use ($formatAbsenceDate): string {
    $start = $formatAbsenceDate(isset($row['starts_on']) ? (string) $row['starts_on'] : null);
    $endRaw = $row['ends_on'] ?? null;
    $end = is_string($endRaw) && $endRaw !== '' ? $formatAbsenceDate($endRaw) : '';
    if ($end === '') {
        return $start !== '' ? ('Depuis le ' . $start . ', sans date de retour') : 'Absence en cours';
    }

    return 'Du ' . $start . ' au ' . $end;
};
?>
<div class="dash-rh-foot" id="mon-dossier-rh">
    <?php if ($showOffers): ?>
    <section class="cc-card dash-rh-offers" id="dashboard-org-offers" aria-labelledby="dash-rh-offers-title">
        <p class="cc-kicker cc-kicker--primary">Recrutement</p>
        <h2 id="dash-rh-offers-title" class="dash-rh-foot__title">Offres de l’organisation</h2>
        <p class="dash-rh-foot__lead">Les postes ouverts dans la communauté apparaissent ici. Vous pouvez consulter une offre et, le cas échéant, postuler.</p>
        <?php if ($offers === []): ?>
            <p class="dash-rh-foot__empty">Aucune offre n’est publiée pour le moment.</p>
        <?php else: ?>
            <ul class="dash-rh-offers__list">
                <?php foreach ($offers as $offer): ?>
                    <?php
                    $ohref = (string) ($offer['href'] ?? '');
                    $otitle = (string) ($offer['title'] ?? 'Offre');
                    $ounit = trim((string) ($offer['unit_name'] ?? ''));
                    if ($ohref === '') {
                        continue;
                    }
                    ?>
                    <li>
                        <a class="dash-rh-offers__item" href="<?= htmlspecialchars($ohref, ENT_QUOTES, 'UTF-8') ?>">
                            <strong><?= htmlspecialchars($otitle, ENT_QUOTES, 'UTF-8') ?></strong>
                            <?php if ($ounit !== ''): ?>
                                <span><?= htmlspecialchars($ounit, ENT_QUOTES, 'UTF-8') ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
    <?php endif; ?>

    <?php if ($showPersonnel): ?>
    <section class="cc-card dash-rh-parcours" id="dashboard-member-rh" aria-labelledby="dash-rh-parcours-title">
        <div class="dash-rh-parcours__head">
            <div>
                <p class="cc-kicker cc-kicker--primary">Personnel</p>
                <h2 id="dash-rh-parcours-title" class="dash-rh-foot__title">Mon dossier RH</h2>
                <p class="dash-rh-foot__lead">Choisissez d’abord le type de demande, puis complétez le formulaire. Une seule démarche à la fois.</p>
            </div>
            <a class="dash-rh-parcours__full" href="<?= htmlspecialchars($workspaceUrl, ENT_QUOTES, 'UTF-8') ?>">Espace RH complet</a>
        </div>

        <p class="dash-rh-parcours__step" x-text="rhStep === 'choice' ? 'Étape 1 sur 2 — Choisissez une démarche' : 'Étape 2 sur 2 — Complétez la demande'">Étape 1 sur 2 — Choisissez une démarche</p>

        <div class="dash-rh-parcours__choice" x-show="rhStep === 'choice'">
            <button type="button" class="dash-rh-choice" @click="rhStep = 'absence'; history.replaceState(null, '', '#absence')">
                <span class="dash-rh-choice__kicker">Indisponibilité</span>
                <strong>Absence</strong>
                <span class="dash-rh-choice__hint">Prévenir l’encadrement d’une période d’indisponibilité, datée ou jusqu’à votre retour.</span>
            </button>
            <button type="button" class="dash-rh-choice" @click="rhStep = 'elevation'; history.replaceState(null, '', '#elevation')">
                <span class="dash-rh-choice__kicker">Situation</span>
                <strong>Élévation</strong>
                <span class="dash-rh-choice__hint">Proposer un changement de grade, de rôle, de fonction, d’affectation ou d’habilitation.</span>
            </button>
            <button type="button" class="dash-rh-choice" @click="rhStep = 'avancement'; history.replaceState(null, '', '#avancement')">
                <span class="dash-rh-choice__kicker">Mobilité</span>
                <strong>Avancement</strong>
                <span class="dash-rh-choice__hint">Exprimer un souhait d’évolution, un poste visé ou un changement d’unité.</span>
            </button>
        </div>

        <div class="dash-rh-parcours__panel" id="absence" x-show="rhStep === 'absence'" x-cloak>
            <button type="button" class="dash-rh-back" @click="rhStep = 'choice'; history.replaceState(null, '', '#mon-dossier-rh')">← Retour au choix</button>
            <h3 class="dash-rh-parcours__panel-title">Déclarer une absence</h3>
            <?php if (!$absenceReady): ?>
                <p class="dash-rh-foot__empty">Les déclarations d’absence ne sont pas encore ouvertes. Contactez l’encadrement si vous devez vous signaler dès maintenant.</p>
            <?php else: ?>
                <?php if ($activeAbsences !== []): ?>
                    <div class="dash-rh-active">
                        <?php foreach ($activeAbsences as $active): ?>
                            <?php
                            $aReason = (string) ($active['reason'] ?? 'autre');
                            $aReasonLabel = (string) ($absenceReasonLabels[$aReason] ?? 'Autre');
                            $aNote = trim((string) ($active['note'] ?? ''));
                            ?>
                            <div class="dash-rh-active__row">
                                <div>
                                    <p class="dash-rh-active__period"><?= htmlspecialchars($absencePeriodLabel($active), ENT_QUOTES, 'UTF-8') ?></p>
                                    <p class="dash-rh-active__meta"><?= htmlspecialchars($aReasonLabel, ENT_QUOTES, 'UTF-8') ?><?php if ($aNote !== ''): ?> — <?= htmlspecialchars($aNote, ENT_QUOTES, 'UTF-8') ?><?php endif; ?></p>
                                </div>
                                <form method="post" action="<?= htmlspecialchars(url('personnel/mon-espace-rh/absences/annuler'), ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="hidden" name="_csrf_token" value="<?= $csrf ?>">
                                    <input type="hidden" name="return_to" value="dashboard">
                                    <input type="hidden" name="return_step" value="absence">
                                    <input type="hidden" name="absence_id" value="<?= (int) ($active['id'] ?? 0) ?>">
                                    <button type="submit" class="dash-rh-active__stop">Interrompre</button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <form method="post" action="<?= htmlspecialchars(url('personnel/mon-espace-rh/absences'), ENT_QUOTES, 'UTF-8') ?>" class="dash-rh-form" id="dash-rh-absence-form">
                    <input type="hidden" name="_csrf_token" value="<?= $csrf ?>">
                    <input type="hidden" name="return_to" value="dashboard">
                    <input type="hidden" name="return_step" value="absence">
                    <label for="dash-rh-absence-starts">Date de début</label>
                    <input type="date" id="dash-rh-absence-starts" name="starts_on" required value="<?= htmlspecialchars(date('Y-m-d'), ENT_QUOTES, 'UTF-8') ?>">
                    <label for="dash-rh-absence-reason">Motif</label>
                    <select id="dash-rh-absence-reason" name="reason">
                        <?php foreach ($absenceReasonLabels as $reasonValue => $reasonLabel): ?>
                            <option value="<?= htmlspecialchars((string) $reasonValue, ENT_QUOTES, 'UTF-8') ?>"<?= (string) $reasonValue === 'personnel' ? ' selected' : '' ?>><?= htmlspecialchars((string) $reasonLabel, ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="dash-rh-form__legend">Durée</p>
                    <div class="dash-rh-duration">
                        <label class="dash-rh-duration__opt">
                            <input type="radio" name="has_duration" value="1" checked data-dash-rh-duration="dated">
                            <span>Période datée — vous connaissez la date de retour.</span>
                        </label>
                        <label class="dash-rh-duration__opt">
                            <input type="radio" name="has_duration" value="0" data-dash-rh-duration="open">
                            <span>Sans durée précisée — jusqu’à ce que vous interrompiez l’absence.</span>
                        </label>
                    </div>
                    <div id="dash-rh-absence-ends-wrap">
                        <label for="dash-rh-absence-ends">Date de fin</label>
                        <input type="date" id="dash-rh-absence-ends" name="ends_on">
                    </div>
                    <label for="dash-rh-absence-note">Précision (facultatif)</label>
                    <textarea id="dash-rh-absence-note" name="note" rows="2" maxlength="500" placeholder="Ex. : indisponible les soirs de semaine jusqu’à nouvel ordre"></textarea>
                    <button type="submit" class="dash-rh-submit">Déclarer l’absence</button>
                </form>
            <?php endif; ?>
        </div>

        <div class="dash-rh-parcours__panel" id="elevation" x-show="rhStep === 'elevation'" x-cloak>
            <button type="button" class="dash-rh-back" @click="rhStep = 'choice'; history.replaceState(null, '', '#mon-dossier-rh')">← Retour au choix</button>
            <h3 class="dash-rh-parcours__panel-title">Demande d’élévation</h3>
            <?php if (is_int($elevationCooldown) && $elevationCooldown > 0): ?>
                <?php $hours = max(1, (int) ceil($elevationCooldown / 3600)); ?>
                <p class="dash-rh-foot__empty">Une demande est déjà en cours. Vous pourrez en renvoyer une dans environ <?= $hours ?> heure<?= $hours > 1 ? 's' : '' ?>.</p>
            <?php elseif (!$elevationHasRecipients): ?>
                <p class="dash-rh-foot__empty">Aucune personne habilitée n’est joignable pour traiter une élévation. Contactez l’encadrement autrement.</p>
            <?php else: ?>
                <form method="post" action="<?= htmlspecialchars(url('personnel/mon-espace-rh/elevation'), ENT_QUOTES, 'UTF-8') ?>" class="dash-rh-form">
                    <input type="hidden" name="_csrf_token" value="<?= $csrf ?>">
                    <input type="hidden" name="return_to" value="dashboard">
                    <input type="hidden" name="return_step" value="elevation">
                    <?php
                    $fieldIdPrefix = 'dash-rh-elev';
                    $selectedKind = 'grade';
                    $includeUnit = true;
                    require base_path('views/admin/effectifs_workspace/partials/elevation_request_fields.php');
                    ?>
                    <button type="submit" class="dash-rh-submit">Transmettre la demande</button>
                </form>
            <?php endif; ?>
        </div>

        <div class="dash-rh-parcours__panel" id="avancement" x-show="rhStep === 'avancement'" x-cloak>
            <button type="button" class="dash-rh-back" @click="rhStep = 'choice'; history.replaceState(null, '', '#mon-dossier-rh')">← Retour au choix</button>
            <h3 class="dash-rh-parcours__panel-title">Demande d’avancement</h3>
            <?php if (!$mobilityReady): ?>
                <p class="dash-rh-foot__empty">Les souhaits d’évolution ne sont pas encore ouverts. Contactez l’encadrement si vous devez signaler un mouvement dès maintenant.</p>
            <?php else: ?>
                <form method="post" action="<?= htmlspecialchars(url('personnel/mon-espace-rh/mobilite'), ENT_QUOTES, 'UTF-8') ?>" class="dash-rh-form">
                    <input type="hidden" name="_csrf_token" value="<?= $csrf ?>">
                    <input type="hidden" name="return_to" value="dashboard">
                    <input type="hidden" name="return_step" value="avancement">
                    <label for="dash-rh-mob-type">Type de demande</label>
                    <select id="dash-rh-mob-type" name="request_type">
                        <?php foreach ($mobilityTypeLabels as $k => $lab): ?>
                            <option value="<?= htmlspecialchars((string) $k, ENT_QUOTES, 'UTF-8') ?>" <?= $k === 'career_wish' ? 'selected' : '' ?>><?= htmlspecialchars((string) $lab, ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                    <label for="dash-rh-mob-target">Poste ou unité visée</label>
                    <input type="text" id="dash-rh-mob-target" name="target_label" maxlength="200" placeholder="Ex. Chef d’équipe, radio…">
                    <label for="dash-rh-mob-motivation">Motivation</label>
                    <textarea id="dash-rh-mob-motivation" name="motivation" rows="3" maxlength="2000" placeholder="Pourquoi ce mouvement ?"></textarea>
                    <button type="submit" class="dash-rh-submit">Envoyer à l’encadrement</button>
                </form>
                <?php if ($myMobility !== []): ?>
                    <ul class="dash-rh-history">
                        <?php foreach ($myMobility as $m): ?>
                            <?php
                            $typeLab = (string) ($mobilityTypeLabels[$m['request_type'] ?? ''] ?? 'Demande');
                            $st = (string) ($m['status'] ?? 'pending');
                            $stLab = (string) ($mobilityStatusLabels[$st] ?? 'En attente');
                            $tl = trim((string) ($m['target_label'] ?? ''));
                            ?>
                            <li>
                                <?= htmlspecialchars($typeLab, ENT_QUOTES, 'UTF-8') ?>
                                — <?= htmlspecialchars($stLab, ENT_QUOTES, 'UTF-8') ?>
                                <?php if ($tl !== ''): ?>
                                    · <?= htmlspecialchars($tl, ENT_QUOTES, 'UTF-8') ?>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </section>
    <script>
    (function () {
        var form = document.getElementById('dash-rh-absence-form');
        if (!form) return;
        var wrap = document.getElementById('dash-rh-absence-ends-wrap');
        var ends = document.getElementById('dash-rh-absence-ends');
        function sync() {
            var dated = form.querySelector('input[name="has_duration"][value="1"]');
            var on = dated && dated.checked;
            if (wrap) wrap.hidden = !on;
            if (ends) {
                ends.required = !!on;
                if (!on) ends.value = '';
            }
        }
        form.querySelectorAll('input[name="has_duration"]').forEach(function (el) {
            el.addEventListener('change', sync);
        });
        sync();
    })();
    </script>
    <?php endif; ?>
</div>
