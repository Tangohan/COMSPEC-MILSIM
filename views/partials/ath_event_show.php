<?php
declare(strict_types=1);

/**
 * Fiche créneau — rendu ATHENA (RSVP, postes, présence).
 *
 * @var array<string, mixed> $event
 * @var list<array<string, mixed>> $eventRsvps
 * @var list<array<string, mixed>> $eventMemberLookup
 * @var string $eventMemberLookupQuery
 * @var array<int, bool> $eventRsvpUserIds
 * @var bool $eventStaffActionsEnabled
 * @var list<array<string, mixed>> $eventSlots
 * @var array<int, list<array<string, mixed>>> $eventSlotAssignmentsBySlot
 * @var list<array<string, mixed>> $eventUnits
 */

$eventRsvps = is_array($eventRsvps ?? null) ? $eventRsvps : [];
$eventMemberLookup = is_array($eventMemberLookup ?? null) ? $eventMemberLookup : [];
$eventMemberLookupQuery = (string) ($eventMemberLookupQuery ?? '');
$eventRsvpUserIds = is_array($eventRsvpUserIds ?? null) ? $eventRsvpUserIds : [];
$eventStaffActionsEnabled = !empty($eventStaffActionsEnabled);
$eventSlots = is_array($eventSlots ?? null) ? $eventSlots : [];
$eventSlotAssignmentsBySlot = is_array($eventSlotAssignmentsBySlot ?? null) ? $eventSlotAssignmentsBySlot : [];
$eventUnits = is_array($eventUnits ?? null) ? $eventUnits : [];
$event = is_array($event ?? null) ? $event : [];

$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');

$cancelled = !empty($event['cancelled_at']);
$eid = (int) ($event['id'] ?? 0);
$addOpen = $eventMemberLookupQuery !== '' || $eventMemberLookup !== [];

$typeMeta = static function (string $t): array {
    return match ($t) {
        'operation' => ['label' => 'Opération', 'tone' => '#9f1239', 'bg' => '#fff1f2'],
        'formation' => ['label' => 'Formation', 'tone' => '#075985', 'bg' => '#f0f9ff'],
        'autre' => ['label' => 'Autre', 'tone' => '#5b21b6', 'bg' => '#f5f3ff'],
        default => ['label' => 'Événement', 'tone' => '#065f46', 'bg' => '#ecfdf5'],
    };
};

$statusLabel = static function (string $s): string {
    return match ($s) {
        'yes' => 'Présent',
        'maybe' => 'Peut-être',
        'no' => 'Absent',
        default => $s,
    };
};

$absenceLabel = static function (?string $reason): string {
    return match ((string) $reason) {
        'service' => 'Service',
        'sante' => 'Santé',
        'indisponibilite_planifiee' => 'Indisponibilité planifiée',
        'absence_non_justifiee' => 'Absence non justifiée',
        'autre' => 'Autre',
        default => '—',
    };
};

$formatCheckIn = static function (?string $raw): string {
    if ($raw === null || trim($raw) === '') {
        return '—';
    }
    $ts = strtotime($raw);

    return $ts !== false ? date('d/m/Y H:i', $ts) : $raw;
};

$nYes = 0;
$nMaybe = 0;
$nNo = 0;
$nChecked = 0;
foreach ($eventRsvps as $r) {
    $st = (string) ($r['status'] ?? '');
    if ($st === 'yes') {
        $nYes++;
    } elseif ($st === 'maybe') {
        $nMaybe++;
    } elseif ($st === 'no') {
        $nNo++;
    }
    if (!empty($r['checked_in_at'])) {
        $nChecked++;
    }
}
$nTotal = count($eventRsvps);
$type = $typeMeta((string) ($event['event_type'] ?? 'evenement'));
$ref = 'OP-' . date('Y') . '-' . str_pad((string) $eid, 3, '0', STR_PAD_LEFT);

$athKpis = [
    ['label' => 'PRÉSENTS', 'value' => (string) $nYes, 'delta' => '', 'tone' => '#0b8a5c', 'pct' => $nTotal > 0 ? (int) round($nYes / $nTotal * 100) . '%' : '0%', 'note' => 'sur ' . $nTotal . ' réponse' . ($nTotal > 1 ? 's' : '')],
    ['label' => 'PEUT-ÊTRE', 'value' => (string) $nMaybe, 'delta' => '', 'tone' => '#c98a12', 'pct' => $nTotal > 0 ? (int) round($nMaybe / $nTotal * 100) . '%' : '0%', 'note' => 'en attente'],
    ['label' => 'ABSENTS', 'value' => (string) $nNo, 'delta' => '', 'tone' => '#b42318', 'pct' => $nTotal > 0 ? (int) round($nNo / $nTotal * 100) . '%' : '0%', 'note' => 'déclarés'],
    ['label' => 'POINTÉS', 'value' => (string) $nChecked, 'delta' => '', 'tone' => '#0b8a5c', 'pct' => $nYes > 0 ? (int) round($nChecked / max(1, $nYes) * 100) . '%' : '0%', 'note' => 'présence enregistrée'],
];

$s = \App\Core\Session::getFlash('success');
$errFlash = \App\Core\Session::getFlash('error');
?>

<div class="ath-event-show ath-rise" x-data="{ addOpen: <?= $addOpen ? 'true' : 'false' ?>, dangerOpen: false }">
    <?php if ($s): ?>
    <div class="ath-banner-warn ath-rise" style="background:#e6f8f0;border-color:#bfe9d8;margin-bottom:16px;" role="status">
        <div class="ath-banner-warn__text" style="color:#0b6b47;"><?= $h((string) $s) ?></div>
    </div>
    <?php endif; ?>
    <?php if ($errFlash): ?>
    <div class="ath-banner-warn ath-rise" style="margin-bottom:16px;" role="alert">
        <div class="ath-banner-warn__text"><?= $h((string) $errFlash) ?></div>
    </div>
    <?php endif; ?>

    <div class="ath-event-show__meta ath-card ath-rise" style="padding:14px 18px;margin-bottom:16px;display:flex;flex-wrap:wrap;align-items:center;gap:10px 16px;">
        <span class="ath-cell ath-cell--mono" style="font-size:10px;font-weight:800;letter-spacing:0.12em;color:#8c979b;"><?= $h($ref) ?></span>
        <span class="ath-cell ath-cell--badge" style="color:<?= $h($type['tone']) ?>;background:<?= $h($type['bg']) ?>;border-color:transparent;"><?= $h($type['label']) ?></span>
        <?php if ($cancelled): ?>
        <span class="ath-cell ath-cell--badge" style="color:#9a3412;background:#fff7ed;border-color:transparent;">Annulé</span>
        <?php endif; ?>
        <a href="<?= $h(url('back-office/events/' . $eid . '/reponses-nominatives')) ?>" class="ath-btn ath-btn--solid">Réponses nominatives</a>
        <a href="<?= $h(url('back-office/events')) ?>" class="ath-btn" style="margin-left:auto;">← Registre des opérations</a>
    </div>

    <?php if ($cancelled): ?>
    <div class="ath-banner-warn ath-rise" style="margin-bottom:16px;" role="status">
        <div class="ath-banner-warn__text">
            <strong>Créneau annulé</strong> le <?= $h($formatCheckIn(isset($event['cancelled_at']) ? (string) $event['cancelled_at'] : null)) ?>
            <?php if (!empty($event['cancelled_reason'])): ?>
                — <?= nl2br($h((string) $event['cancelled_reason'])) ?>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($event['description']) && !$eventStaffActionsEnabled): ?>
    <section class="ath-card ath-rise ath-event-show__section">
        <div class="ath-event-show__section-head">
            <h2>Consignes</h2>
        </div>
        <div class="ath-event-show__section-body"><?= nl2br($h((string) $event['description'])) ?></div>
    </section>
    <?php endif; ?>

    <?php if ($eventStaffActionsEnabled): ?>
    <section class="ath-card ath-rise ath-event-show__section">
        <div class="ath-event-show__section-head">
            <h2>Détails affichés aux membres</h2>
            <p>Image, conditions, déroulement et étiquettes — visibles sur la page Événements.</p>
        </div>
        <form method="post" action="<?= $h(url('back-office/events/' . $eid . '/details')) ?>" enctype="multipart/form-data" class="ath-event-show__form">
            <input type="hidden" name="_csrf_token" value="<?= $h(\App\Core\Csrf::token()) ?>">
            <div class="ath-event-show__form-grid">
                <div class="ath-event-show__field--full">
                    <label class="ath-users-filters__label" for="ev-edit-desc">Description</label>
                    <textarea id="ev-edit-desc" name="description" rows="3" class="bo-select" style="width:100%;min-height:88px;padding:10px 12px;"><?= $h((string) ($event['description'] ?? '')) ?></textarea>
                </div>
                <div>
                    <label class="ath-users-filters__label" for="ev-edit-loc">Lieu</label>
                    <input id="ev-edit-loc" type="text" name="location" value="<?= $h((string) ($event['location'] ?? '')) ?>" class="bo-select" style="height:40px;width:100%;">
                </div>
                <?php
                $eventDetailsSource = $event;
                $eventDetailsAthForm = true;
                require base_path('views/admin/organization/partials/event_details_fields.php');
                ?>
            </div>
            <div class="ath-event-show__form-actions">
                <button type="submit" class="ath-btn ath-btn--solid">Enregistrer les détails</button>
            </div>
        </form>
    </section>
    <?php endif; ?>

    <?php require base_path('views/partials/ath_kpis.php'); ?>

    <?php if ($eventStaffActionsEnabled): ?>
    <p class="ath-event-show__hint">
        Les membres peuvent pointer eux-mêmes depuis « Pointage &amp; présence » lorsque la fenêtre est ouverte.
        Ici vous pouvez corriger une participation, ajouter quelqu’un ou enregistrer une présence à leur place.
    </p>

    <section class="ath-card ath-rise ath-event-show__section">
        <button type="button" class="ath-event-show__collapse-head" @click="addOpen = !addOpen" :aria-expanded="addOpen.toString()">
            <div>
                <h2>Ajouter un membre</h2>
                <p>Recherchez par nom affiché ou indicatif (au moins deux lettres).</p>
            </div>
            <svg class="ath-event-show__chevron" :style="addOpen ? 'transform: rotate(180deg)' : ''" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" /></svg>
        </button>
        <div class="ath-event-show__section-body" x-show="addOpen" x-cloak>
            <form method="get" action="<?= $h(url('back-office/events/' . $eid)) ?>" class="ath-event-show__search-row">
                <div class="ath-event-show__field--grow">
                    <label class="ath-users-filters__label" for="ev-member-q">Recherche</label>
                    <input id="ev-member-q" type="search" name="q" value="<?= $h($eventMemberLookupQuery) ?>" minlength="2" autocomplete="off" class="bo-select" style="height:40px;width:100%;" placeholder="Ex. Martin ou Foxtrot">
                </div>
                <button type="submit" class="ath-btn ath-btn--solid">Chercher</button>
            </form>
            <?php if ($eventMemberLookup !== []): ?>
            <ul class="ath-event-show__hit-list">
                <?php foreach ($eventMemberLookup as $hit):
                    $hid = (int) ($hit['id'] ?? 0);
                    $already = $hid > 0 && !empty($eventRsvpUserIds[$hid]);
                    $dn = (string) ($hit['display_name'] ?? '');
                    $cs = trim((string) ($hit['callsign'] ?? ''));
                    ?>
                <li class="ath-event-show__hit">
                    <div>
                        <span class="ath-event-show__hit-name"><?= $h($dn) ?></span>
                        <?php if ($cs !== ''): ?>
                        <span class="ath-event-show__hit-call"><?= $h($cs) ?></span>
                        <?php endif; ?>
                    </div>
                    <?php if ($already): ?>
                    <span class="ath-event-show__hit-note">Déjà sur la feuille — modifiez la ligne ci-dessous.</span>
                    <?php else: ?>
                    <form method="post" action="<?= $h(url('back-office/events/' . $eid . '/participant/add')) ?>" class="ath-event-show__inline-form">
                        <input type="hidden" name="_csrf_token" value="<?= $h(\App\Core\Csrf::token()) ?>">
                        <input type="hidden" name="user_id" value="<?= $hid ?>">
                        <label class="sr-only" for="add-part-<?= $hid ?>">Participation</label>
                        <select id="add-part-<?= $hid ?>" name="participation" class="bo-select">
                            <option value="yes">Présent</option>
                            <option value="maybe">Peut-être</option>
                            <option value="no">Absent</option>
                        </select>
                        <label class="sr-only" for="add-abs-<?= $hid ?>">Motif d’absence</label>
                        <select id="add-abs-<?= $hid ?>" name="absence_reason" class="bo-select">
                            <option value="">Motif d’absence</option>
                            <option value="service">Service</option>
                            <option value="sante">Santé</option>
                            <option value="indisponibilite_planifiee">Indisponibilité planifiée</option>
                            <option value="absence_non_justifiee">Absence non justifiée</option>
                            <option value="autre">Autre</option>
                        </select>
                        <button type="submit" class="ath-btn ath-btn--solid">Ajouter</button>
                    </form>
                    <?php endif; ?>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php elseif (strlen($eventMemberLookupQuery) >= 2): ?>
            <p class="ath-event-show__empty-inline">Aucun résultat pour cette recherche.</p>
            <?php endif; ?>
        </div>
    </section>
    <?php endif; ?>

    <div class="ath-table-panel ath-rise" style="margin-bottom:16px;">
        <div class="ath-table-toolbar">
            <span class="ath-table-toolbar__title">Postes de mission</span>
            <span class="ath-table-toolbar__count"><?= count($eventSlots) ?> poste<?= count($eventSlots) > 1 ? 's' : '' ?></span>
            <span class="ath-table-toolbar__spacer" aria-hidden="true"></span>
        </div>
        <?php if ($eventStaffActionsEnabled): ?>
        <form method="post" action="<?= $h(url('back-office/events/' . $eid . '/slots')) ?>" class="ath-event-show__section-body ath-event-show__form">
            <input type="hidden" name="_csrf_token" value="<?= $h(\App\Core\Csrf::token()) ?>">
            <div class="ath-event-show__form-grid">
                <div>
                    <label class="ath-users-filters__label" for="slot-label">Nom du poste</label>
                    <input id="slot-label" type="text" name="label" required maxlength="160" class="bo-select" style="height:40px;width:100%;" placeholder="Ex. Pilote, Tireur AT, Chef d’équipe">
                </div>
                <div>
                    <label class="ath-users-filters__label" for="slot-capacity">Places</label>
                    <input id="slot-capacity" type="number" name="capacity" min="1" max="200" value="1" class="bo-select" style="height:40px;width:100%;">
                </div>
                <div>
                    <label class="ath-users-filters__label" for="slot-unit">Unité <span class="ath-event-show__opt">(optionnel)</span></label>
                    <select id="slot-unit" name="unit_id" class="bo-select">
                        <option value="0">—</option>
                        <?php foreach ($eventUnits as $u): ?>
                        <option value="<?= (int) $u['id'] ?>"><?= $h((string) ($u['name'] ?? '')) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="ath-event-show__field--full">
                    <label class="ath-users-filters__label" for="slot-notes">Notes d’équipement <span class="ath-event-show__opt">(optionnel)</span></label>
                    <textarea id="slot-notes" name="loadout_notes" rows="2" class="bo-select" style="width:100%;min-height:72px;padding:10px 12px;" placeholder="Ex. Tenue standard + AT4, munitions fournies au dépôt"></textarea>
                </div>
            </div>
            <div class="ath-event-show__form-actions">
                <button type="submit" class="ath-btn ath-btn--solid">Ajouter le poste</button>
            </div>
        </form>
        <?php endif; ?>

        <?php if ($eventSlots === []): ?>
        <div class="ath-event-show__empty">Aucun poste défini — ajoutez des rôles pour que les membres s’inscrivent sur un poste précis.</div>
        <?php else: ?>
        <div class="ath-table-wrap">
            <table class="ath-table" style="min-width:900px;">
                <thead>
                    <tr>
                        <th scope="col">Poste</th>
                        <th scope="col">Unité</th>
                        <th scope="col">Places</th>
                        <th scope="col">Inscrits</th>
                        <th scope="col">Équipement</th>
                        <?php if ($eventStaffActionsEnabled): ?>
                        <th scope="col">Actions</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($eventSlots as $slot):
                        $sid = (int) ($slot['id'] ?? 0);
                        $capacity = (int) ($slot['capacity'] ?? 1);
                        $confirmedN = (int) ($slot['confirmed_count'] ?? 0);
                        $waitlistedN = (int) ($slot['waitlisted_count'] ?? 0);
                        $roster = $eventSlotAssignmentsBySlot[$sid] ?? [];
                        $unitName = trim((string) ($slot['unit_name'] ?? ''));
                        ?>
                    <tr>
                        <td><strong><?= $h((string) ($slot['label'] ?? '')) ?></strong></td>
                        <td><?= $unitName !== '' ? $h($unitName) : '—' ?></td>
                        <td>
                            <?= $confirmedN ?> / <?= $capacity ?>
                            <?php if ($waitlistedN > 0): ?>
                            <span class="ath-cell ath-cell--badge" style="margin-left:6px;color:#64748b;background:#f1f5f9;border-color:transparent;"><?= $waitlistedN ?> en attente</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($roster === []): ?>
                            —
                            <?php else: ?>
                                <?php foreach ($roster as $a): ?>
                                <div><?= $h((string) ($a['display_name'] ?? '')) ?><?= (string) ($a['status'] ?? '') === 'waitlisted' ? ' (attente)' : '' ?></div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </td>
                        <td><?= !empty($slot['loadout_notes']) ? nl2br($h((string) $slot['loadout_notes'])) : '—' ?></td>
                        <?php if ($eventStaffActionsEnabled): ?>
                        <td>
                            <details class="ath-event-show__details">
                                <summary class="ath-btn">Modifier</summary>
                                <form method="post" action="<?= $h(url('back-office/events/' . $eid . '/slots/' . $sid)) ?>" class="ath-event-show__form" style="margin-top:10px;">
                                    <input type="hidden" name="_csrf_token" value="<?= $h(\App\Core\Csrf::token()) ?>">
                                    <div class="ath-event-show__form-grid">
                                        <div>
                                            <label class="ath-users-filters__label" for="slot-label-<?= $sid ?>">Nom du poste</label>
                                            <input id="slot-label-<?= $sid ?>" type="text" name="label" required maxlength="160" value="<?= $h((string) ($slot['label'] ?? '')) ?>" class="bo-select" style="height:40px;width:100%;">
                                        </div>
                                        <div>
                                            <label class="ath-users-filters__label" for="slot-capacity-<?= $sid ?>">Places</label>
                                            <input id="slot-capacity-<?= $sid ?>" type="number" name="capacity" min="1" max="200" value="<?= $capacity ?>" class="bo-select" style="height:40px;width:100%;">
                                        </div>
                                        <div>
                                            <label class="ath-users-filters__label" for="slot-unit-<?= $sid ?>">Unité</label>
                                            <select id="slot-unit-<?= $sid ?>" name="unit_id" class="bo-select">
                                                <option value="0">—</option>
                                                <?php foreach ($eventUnits as $u): ?>
                                                <option value="<?= (int) $u['id'] ?>" <?= (int) ($slot['unit_id'] ?? 0) === (int) $u['id'] ? 'selected' : '' ?>><?= $h((string) ($u['name'] ?? '')) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="ath-event-show__field--full">
                                            <label class="ath-users-filters__label" for="slot-notes-<?= $sid ?>">Notes d’équipement</label>
                                            <textarea id="slot-notes-<?= $sid ?>" name="loadout_notes" rows="2" class="bo-select" style="width:100%;min-height:72px;padding:10px 12px;"><?= $h((string) ($slot['loadout_notes'] ?? '')) ?></textarea>
                                        </div>
                                    </div>
                                    <div class="ath-event-show__form-actions">
                                        <button type="submit" class="ath-btn ath-btn--solid">Enregistrer</button>
                                    </div>
                                </form>
                            </details>
                            <form method="post" action="<?= $h(url('back-office/events/' . $eid . '/slots/' . $sid . '/supprimer')) ?>" onsubmit="return confirm('Supprimer ce poste ? Les inscriptions associées seront retirées.');" style="margin-top:8px;">
                                <input type="hidden" name="_csrf_token" value="<?= $h(\App\Core\Csrf::token()) ?>">
                                <button type="submit" class="ath-btn" style="color:#b42318;border-color:#fecaca;">Supprimer</button>
                            </form>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <div class="ath-table-panel ath-rise" style="margin-bottom:16px;">
        <div class="ath-table-toolbar">
            <span class="ath-table-toolbar__title">Feuille de présence</span>
            <span class="ath-table-toolbar__count"><?= $nTotal ?> membre<?= $nTotal > 1 ? 's' : '' ?></span>
            <span class="ath-table-toolbar__spacer" aria-hidden="true"></span>
            <a href="<?= $h(url('back-office/events/' . $eid . '/export-presences')) ?>" class="ath-table-toolbar__export ath-btn">Exporter CSV</a>
        </div>

        <?php if ($eventRsvps === []): ?>
        <div class="ath-event-show__empty">Aucune réponse pour l’instant — les membres apparaîtront ici dès qu’ils auront confirmé leur participation.</div>
        <?php else: ?>
        <div class="ath-table-wrap">
            <table class="ath-table ath-event-show__rsvp-table" style="min-width:960px;">
                <thead>
                    <tr>
                        <th scope="col">Membre</th>
                        <th scope="col">Participation</th>
                        <th scope="col">Motif d’absence</th>
                        <th scope="col">Rappel</th>
                        <th scope="col">Pointage</th>
                        <?php if ($eventStaffActionsEnabled): ?>
                        <th scope="col">Actions</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($eventRsvps as $r):
                        $uid = (int) ($r['user_id'] ?? 0);
                        $st = (string) ($r['status'] ?? '');
                        $canPoint = $eventStaffActionsEnabled && in_array($st, ['yes', 'maybe'], true);
                        $hasCheck = !empty($r['checked_in_at']);
                        $chipStyle = match ($st) {
                            'yes' => 'color:#065f46;background:#ecfdf5;',
                            'maybe' => 'color:#92400e;background:#fffbeb;',
                            'no' => 'color:#991b1b;background:#fef2f2;',
                            default => 'color:#64748b;background:#f1f5f9;',
                        };
                        ?>
                    <tr>
                        <td>
                            <strong><?= $h((string) ($r['display_name'] ?? '')) ?></strong>
                            <?php if (trim((string) ($r['callsign'] ?? '')) !== ''): ?>
                            <div class="ath-event-show__sub"><?= $h(trim((string) $r['callsign'])) ?></div>
                            <?php endif; ?>
                            <div class="ath-event-show__sub"><?= $h((string) ($r['email'] ?? '')) ?></div>
                        </td>
                        <td>
                            <?php if ($eventStaffActionsEnabled): ?>
                            <form method="post" action="<?= $h(url('back-office/events/' . $eid . '/participant/rsvp')) ?>" class="ath-event-show__inline-form ath-event-show__inline-form--stack">
                                <input type="hidden" name="_csrf_token" value="<?= $h(\App\Core\Csrf::token()) ?>">
                                <input type="hidden" name="user_id" value="<?= $uid ?>">
                                <select name="participation" class="bo-select" aria-label="Participation">
                                    <option value="yes" <?= $st === 'yes' ? ' selected' : '' ?>>Présent</option>
                                    <option value="maybe" <?= $st === 'maybe' ? ' selected' : '' ?>>Peut-être</option>
                                    <option value="no" <?= $st === 'no' ? ' selected' : '' ?>>Absent</option>
                                    <option value="remove">Retirer de la liste</option>
                                </select>
                                <select name="absence_reason" class="bo-select" aria-label="Motif d’absence">
                                    <option value="">Motif d’absence</option>
                                    <option value="service" <?= ($r['absence_reason'] ?? '') === 'service' ? ' selected' : '' ?>>Service</option>
                                    <option value="sante" <?= ($r['absence_reason'] ?? '') === 'sante' ? ' selected' : '' ?>>Santé</option>
                                    <option value="indisponibilite_planifiee" <?= ($r['absence_reason'] ?? '') === 'indisponibilite_planifiee' ? ' selected' : '' ?>>Indisponibilité planifiée</option>
                                    <option value="absence_non_justifiee" <?= ($r['absence_reason'] ?? '') === 'absence_non_justifiee' ? ' selected' : '' ?>>Absence non justifiée</option>
                                    <option value="autre" <?= ($r['absence_reason'] ?? '') === 'autre' ? ' selected' : '' ?>>Autre</option>
                                </select>
                                <button type="submit" class="ath-btn">Enregistrer</button>
                            </form>
                            <?php else: ?>
                            <span class="ath-cell ath-cell--badge" style="<?= $chipStyle ?>border-color:transparent;"><?= $h($statusLabel($st)) ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?= $h($absenceLabel(is_string($r['absence_reason'] ?? null) ? (string) $r['absence_reason'] : null)) ?>
                            <?php if (!empty($r['absence_note'])): ?>
                            <div class="ath-event-show__sub"><?= $h((string) $r['absence_note']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($r['reminder_sent_at'])): ?>
                            <span class="ath-cell ath-cell--badge" style="color:#065f46;background:#ecfdf5;border-color:transparent;">Envoyé</span>
                            <?php else: ?>
                            <span class="ath-cell ath-cell--badge" style="color:#64748b;background:#f1f5f9;border-color:transparent;">Non</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($hasCheck): ?>
                            <span class="ath-cell ath-cell--badge" style="color:#065f46;background:#ecfdf5;border-color:transparent;"><?= $h($formatCheckIn((string) $r['checked_in_at'])) ?></span>
                            <?php else: ?>
                            —
                            <?php endif; ?>
                        </td>
                        <?php if ($eventStaffActionsEnabled): ?>
                        <td>
                            <?php if ($canPoint && !$hasCheck): ?>
                            <form method="post" action="<?= $h(url('back-office/events/' . $eid . '/participant/presence')) ?>">
                                <input type="hidden" name="_csrf_token" value="<?= $h(\App\Core\Csrf::token()) ?>">
                                <input type="hidden" name="user_id" value="<?= $uid ?>">
                                <button type="submit" class="ath-btn ath-btn--solid">Pointer présence</button>
                            </form>
                            <?php elseif ($canPoint && $hasCheck): ?>
                            <form method="post" action="<?= $h(url('back-office/events/' . $eid . '/participant/presence/clear')) ?>" onsubmit="return confirm('Effacer l’heure de pointage pour ce membre ?');">
                                <input type="hidden" name="_csrf_token" value="<?= $h(\App\Core\Csrf::token()) ?>">
                                <input type="hidden" name="user_id" value="<?= $uid ?>">
                                <button type="submit" class="ath-btn" style="color:#b42318;border-color:#fecaca;">Effacer le pointage</button>
                            </form>
                            <?php else: ?>
                            —
                            <?php endif; ?>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <?php if (!$cancelled): ?>
    <section class="ath-card ath-rise ath-event-show__section ath-event-show__section--danger">
        <button type="button" class="ath-event-show__collapse-head ath-event-show__collapse-head--danger" @click="dangerOpen = !dangerOpen" :aria-expanded="dangerOpen.toString()">
            <div>
                <h2>Annuler le créneau</h2>
                <p>Les membres indiqués comme présents ou « peut-être » seront prévenus.</p>
            </div>
            <svg class="ath-event-show__chevron" :style="dangerOpen ? 'transform: rotate(180deg)' : ''" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" /></svg>
        </button>
        <form method="post" action="<?= $h(url('back-office/events/' . $eid . '/cancel')) ?>" class="ath-event-show__section-body ath-event-show__form" x-show="dangerOpen" x-cloak onsubmit="return confirm('Annuler ce créneau et prévenir les membres inscrits ?');">
            <input type="hidden" name="_csrf_token" value="<?= $h(\App\Core\Csrf::token()) ?>">
            <label class="ath-users-filters__label" for="cancel-reason">Motif affiché aux membres <span class="ath-event-show__opt">(optionnel)</span></label>
            <textarea id="cancel-reason" name="cancel_reason" rows="2" class="bo-select" style="width:100%;min-height:72px;padding:10px 12px;" placeholder="Ex. Report pour conditions météo / indisponibilité serveur…"></textarea>
            <div class="ath-event-show__form-actions">
                <button type="submit" class="ath-btn" style="background:#b42318;color:#fff;border-color:#b42318;">Annuler définitivement</button>
            </div>
        </form>
    </section>
    <?php endif; ?>
</div>
