<?php
/** @var array<string, mixed> $event */
/** @var list<array<string, mixed>> $eventRsvps */
/** @var list<array<string, mixed>> $eventMemberLookup */
/** @var string $eventMemberLookupQuery */
/** @var array<int, bool> $eventRsvpUserIds */
/** @var bool $eventStaffActionsEnabled */
/** @var list<array<string, mixed>> $eventSlots */
/** @var array<int, list<array<string, mixed>>> $eventSlotAssignmentsBySlot */
/** @var list<array<string, mixed>> $eventUnits */

$eventRsvps = $eventRsvps ?? [];
$eventMemberLookup = $eventMemberLookup ?? [];
$eventMemberLookupQuery = $eventMemberLookupQuery ?? '';
$eventRsvpUserIds = $eventRsvpUserIds ?? [];
$eventStaffActionsEnabled = $eventStaffActionsEnabled ?? false;
$eventSlots = $eventSlots ?? [];
$eventSlotAssignmentsBySlot = $eventSlotAssignmentsBySlot ?? [];
$eventUnits = $eventUnits ?? [];
$cancelled = !empty($event['cancelled_at']);
$eid = (int) ($event['id'] ?? 0);

$typeMeta = static function (string $t): array {
    return match ($t) {
        'operation' => ['label' => 'Opération', 'class' => 'bo-events__badge--op'],
        'formation' => ['label' => 'Formation', 'class' => 'bo-events__badge--form'],
        'autre' => ['label' => 'Autre', 'class' => 'bo-events__badge--autre'],
        default => ['label' => 'Événement', 'class' => 'bo-events__badge--evt'],
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

$formatWhen = static function (?string $raw): array {
    if ($raw === null || trim($raw) === '') {
        return ['day' => '—', 'mon' => '', 'time' => '', 'full' => '—', 'date' => '—'];
    }
    $ts = strtotime($raw);
    if ($ts === false) {
        return ['day' => '—', 'mon' => '', 'time' => '', 'full' => $raw, 'date' => $raw];
    }
    $months = [1 => 'jan', 2 => 'fév', 3 => 'mar', 4 => 'avr', 5 => 'mai', 6 => 'juin', 7 => 'juil', 8 => 'aoû', 9 => 'sep', 10 => 'oct', 11 => 'nov', 12 => 'déc'];
    $monthsLong = [1 => 'janvier', 2 => 'février', 3 => 'mars', 4 => 'avril', 5 => 'mai', 6 => 'juin', 7 => 'juillet', 8 => 'août', 9 => 'septembre', 10 => 'octobre', 11 => 'novembre', 12 => 'décembre'];
    $m = (int) date('n', $ts);

    return [
        'day' => date('d', $ts),
        'mon' => $months[$m] ?? date('M', $ts),
        'time' => date('H:i', $ts),
        'full' => date('d/m/Y H:i', $ts),
        'date' => date('j', $ts) . ' ' . ($monthsLong[$m] ?? '') . ' ' . date('Y', $ts),
    ];
};

$formatCheckIn = static function (?string $raw): string {
    if ($raw === null || trim($raw) === '') {
        return '—';
    }
    $ts = strtotime($raw);
    if ($ts === false) {
        return $raw;
    }

    return date('d/m/Y H:i', $ts);
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
$when = $formatWhen(isset($event['starts_at']) ? (string) $event['starts_at'] : null);
$ends = !empty($event['ends_at']) ? $formatWhen((string) $event['ends_at']) : null;
$addOpen = $eventMemberLookupQuery !== '' || $eventMemberLookup !== [];
?>
<link href="<?= htmlspecialchars(asset_url('assets/css/back-office-events.css'), ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">

<div class="bo-events" x-data="{ addOpen: <?= $addOpen ? 'true' : 'false' ?>, dangerOpen: false }">
    <header class="bo-events__hero">
        <div class="bo-events__hero-inner bo-events__hero-inner--detail">
            <div class="bo-events__hero-main">
                <a href="<?= url('back-office/events') ?>" class="bo-events__back">← Liste des créneaux</a>
                <div class="bo-events__hero-title-row">
                    <div class="bo-events__dateblock bo-events__dateblock--hero" aria-hidden="true">
                        <span class="bo-events__dateblock-day"><?= htmlspecialchars($when['day']) ?></span>
                        <span class="bo-events__dateblock-mon"><?= htmlspecialchars($when['mon']) ?></span>
                        <span class="bo-events__dateblock-time"><?= htmlspecialchars($when['time']) ?></span>
                    </div>
                    <div>
                        <div class="bo-events__badges">
                            <span class="bo-events__badge <?= htmlspecialchars($type['class']) ?>"><?= htmlspecialchars($type['label']) ?></span>
                            <?php if ($cancelled): ?>
                                <span class="bo-events__badge bo-events__badge--cancelled">Annulé</span>
                            <?php endif; ?>
                        </div>
                        <h1 class="bo-events__title"><?= htmlspecialchars((string) ($event['title'] ?? '')) ?></h1>
                        <p class="bo-events__lead bo-events__lead--tight">
                            <?= htmlspecialchars($when['date']) ?> · <?= htmlspecialchars($when['time']) ?>
                            <?php if ($ends !== null): ?>
                                → <?= htmlspecialchars($ends['time']) ?>
                            <?php endif; ?>
                            <?php if (!empty($event['location'])): ?>
                                · <?= htmlspecialchars((string) $event['location']) ?>
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
            </div>
            <div class="bo-events__hero-actions">
                <a href="<?= url('back-office/events/' . $eid . '/export-presences') ?>" class="bo-events__btn bo-events__btn--solid">Télécharger la feuille</a>
                <a href="<?= url('back-office/events') ?>" class="bo-events__btn bo-events__btn--ghost">Retour</a>
            </div>
        </div>
    </header>

    <div class="bo-events__deck">
        <?php $s = \App\Core\Session::getFlash('success'); $errFlash = \App\Core\Session::getFlash('error'); ?>
        <?php if ($s): ?>
            <div class="bo-events__flash bo-events__flash--ok" role="status"><?= htmlspecialchars($s) ?></div>
        <?php endif; ?>
        <?php if ($errFlash): ?>
            <div class="bo-events__flash bo-events__flash--err" role="alert"><?= htmlspecialchars($errFlash) ?></div>
        <?php endif; ?>

        <?php if ($cancelled): ?>
            <div class="bo-events__notice bo-events__notice--warn" role="status">
                <strong>Créneau annulé</strong>
                <span>le <?= htmlspecialchars($formatCheckIn(isset($event['cancelled_at']) ? (string) $event['cancelled_at'] : null)) ?></span>
                <?php if (!empty($event['cancelled_reason'])): ?>
                    <p><?= nl2br(htmlspecialchars((string) $event['cancelled_reason'])) ?></p>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($event['description'])): ?>
            <section class="bo-events__panel bo-events__panel--static">
                <div class="bo-events__panel-static-head">
                    <h2>Consignes</h2>
                </div>
                <div class="bo-events__desc"><?= nl2br(htmlspecialchars((string) $event['description'])) ?></div>
            </section>
        <?php endif; ?>

        <?php if ($eventStaffActionsEnabled): ?>
            <section class="bo-events__panel bo-events__panel--static">
                <div class="bo-events__panel-static-head">
                    <h2>Détails affichés aux membres</h2>
                    <p>Image, conditions, déroulement et étiquettes — visibles sur la page Événements.</p>
                </div>
                <form method="post" action="<?= url('back-office/events/' . $eid . '/details') ?>" enctype="multipart/form-data" class="bo-events__form">
                    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">
                    <div class="bo-events__form-grid">
                        <div class="bo-events__field--full">
                            <label class="bo-events__label" for="ev-edit-desc">Description</label>
                            <textarea id="ev-edit-desc" name="description" rows="3" class="bo-events__textarea"><?= htmlspecialchars((string) ($event['description'] ?? '')) ?></textarea>
                        </div>
                        <div>
                            <label class="bo-events__label" for="ev-edit-loc">Lieu</label>
                            <input id="ev-edit-loc" type="text" name="location" value="<?= htmlspecialchars((string) ($event['location'] ?? '')) ?>" class="bo-events__input">
                        </div>
                        <?php
                        $eventDetailsSource = $event;
                        require __DIR__ . '/partials/event_details_fields.php';
                        ?>
                    </div>
                    <div class="bo-events__form-actions">
                        <button type="submit" class="bo-events__submit">Enregistrer les détails</button>
                    </div>
                </form>
            </section>
        <?php endif; ?>

        <div class="bo-events__kpi-grid bo-events__kpi-grid--4">
            <div class="bo-events__kpi">
                <p class="bo-events__kpi-label">Présents</p>
                <p class="bo-events__kpi-value"><?= (int) $nYes ?></p>
                <p class="bo-events__kpi-meta">sur <?= (int) $nTotal ?> réponse<?= $nTotal > 1 ? 's' : '' ?></p>
            </div>
            <div class="bo-events__kpi">
                <p class="bo-events__kpi-label">Peut-être</p>
                <p class="bo-events__kpi-value"><?= (int) $nMaybe ?></p>
            </div>
            <div class="bo-events__kpi">
                <p class="bo-events__kpi-label">Absents</p>
                <p class="bo-events__kpi-value"><?= (int) $nNo ?></p>
            </div>
            <div class="bo-events__kpi bo-events__kpi--ok">
                <p class="bo-events__kpi-label">Pointés</p>
                <p class="bo-events__kpi-value"><?= (int) $nChecked ?></p>
                <p class="bo-events__kpi-meta">présence enregistrée</p>
            </div>
        </div>

        <?php if ($eventStaffActionsEnabled): ?>
            <p class="bo-events__staff-hint">
                Les membres peuvent pointer eux-mêmes depuis « Pointage &amp; présence » lorsque la fenêtre est ouverte.
                Ici vous pouvez corriger une participation, ajouter quelqu’un ou enregistrer une présence à leur place.
            </p>

            <section class="bo-events__panel">
                <button type="button" class="bo-events__panel-head" @click="addOpen = !addOpen" :aria-expanded="addOpen.toString()">
                    <div>
                        <h2>Ajouter un membre</h2>
                        <p>Recherchez par nom affiché ou indicatif (au moins deux lettres).</p>
                    </div>
                    <svg class="bo-events__chevron" :style="addOpen ? 'transform: rotate(180deg)' : ''" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" /></svg>
                </button>
                <div class="bo-events__form" x-show="addOpen" x-cloak>
                    <form method="get" action="<?= url('back-office/events/' . $eid) ?>" class="bo-events__search-row">
                        <div class="bo-events__field--grow">
                            <label class="bo-events__label" for="ev-member-q">Recherche</label>
                            <input id="ev-member-q" type="search" name="q" value="<?= htmlspecialchars($eventMemberLookupQuery) ?>" minlength="2" autocomplete="off" class="bo-events__input" placeholder="Ex. Martin ou Foxtrot">
                        </div>
                        <button type="submit" class="bo-events__submit">Chercher</button>
                    </form>
                    <?php if ($eventMemberLookup !== []): ?>
                        <ul class="bo-events__hit-list">
                            <?php foreach ($eventMemberLookup as $hit):
                                $hid = (int) ($hit['id'] ?? 0);
                                $already = $hid > 0 && !empty($eventRsvpUserIds[$hid]);
                                $dn = (string) ($hit['display_name'] ?? '');
                                $cs = trim((string) ($hit['callsign'] ?? ''));
                                ?>
                                <li class="bo-events__hit">
                                    <div class="bo-events__hit-id">
                                        <span class="bo-events__hit-name"><?= htmlspecialchars($dn) ?></span>
                                        <?php if ($cs !== ''): ?>
                                            <span class="bo-events__hit-call"><?= htmlspecialchars($cs) ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($already): ?>
                                        <span class="bo-events__hit-note">Déjà sur la feuille — modifiez la ligne ci-dessous.</span>
                                    <?php else: ?>
                                        <form method="post" action="<?= url('back-office/events/' . $eid . '/participant/add') ?>" class="bo-events__inline-form">
                                            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">
                                            <input type="hidden" name="user_id" value="<?= $hid ?>">
                                            <label class="sr-only" for="add-part-<?= $hid ?>">Participation</label>
                                            <select id="add-part-<?= $hid ?>" name="participation" class="bo-events__select bo-events__select--sm">
                                                <option value="yes">Présent</option>
                                                <option value="maybe">Peut-être</option>
                                                <option value="no">Absent</option>
                                            </select>
                                            <label class="sr-only" for="add-abs-<?= $hid ?>">Motif d’absence</label>
                                            <select id="add-abs-<?= $hid ?>" name="absence_reason" class="bo-events__select bo-events__select--sm">
                                                <option value="">Motif d’absence</option>
                                                <option value="service">Service</option>
                                                <option value="sante">Santé</option>
                                                <option value="indisponibilite_planifiee">Indisponibilité planifiée</option>
                                                <option value="absence_non_justifiee">Absence non justifiée</option>
                                                <option value="autre">Autre</option>
                                            </select>
                                            <button type="submit" class="bo-events__action bo-events__action--primary">Ajouter</button>
                                        </form>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php elseif (strlen($eventMemberLookupQuery) >= 2): ?>
                        <p class="bo-events__empty-inline">Aucun résultat pour cette recherche.</p>
                    <?php endif; ?>
                </div>
            </section>
        <?php endif; ?>

        <section class="bo-events__panel bo-events__panel--static">
            <div class="bo-events__panel-static-head bo-events__panel-static-head--row">
                <div>
                    <h2>Postes de mission</h2>
                    <p><?= count($eventSlots) ?> poste<?= count($eventSlots) > 1 ? 's' : '' ?> défini<?= count($eventSlots) > 1 ? 's' : '' ?> — les membres s’inscrivent sur un rôle précis depuis la page Événements plutôt qu’un simple RSVP.</p>
                </div>
            </div>

            <?php if ($eventStaffActionsEnabled): ?>
            <form method="post" action="<?= url('back-office/events/' . $eid . '/slots') ?>" class="bo-events__form">
                <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">
                <div class="bo-events__form-grid">
                    <div>
                        <label class="bo-events__label" for="slot-label">Nom du poste</label>
                        <input id="slot-label" type="text" name="label" required maxlength="160" class="bo-events__input" placeholder="Ex. Pilote, Tireur AT, Chef d’équipe">
                    </div>
                    <div>
                        <label class="bo-events__label" for="slot-capacity">Places</label>
                        <input id="slot-capacity" type="number" name="capacity" min="1" max="200" value="1" class="bo-events__input">
                    </div>
                    <div>
                        <label class="bo-events__label" for="slot-unit">Unité <span>(optionnel)</span></label>
                        <select id="slot-unit" name="unit_id" class="bo-events__select">
                            <option value="0">—</option>
                            <?php foreach ($eventUnits as $u): ?>
                            <option value="<?= (int) $u['id'] ?>"><?= htmlspecialchars((string) ($u['name'] ?? '')) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="bo-events__field--full">
                        <label class="bo-events__label" for="slot-notes">Notes de loadout <span>(optionnel)</span></label>
                        <textarea id="slot-notes" name="loadout_notes" rows="2" class="bo-events__textarea" placeholder="Ex. Tenue standard + AT4, munitions fournies au dépôt"></textarea>
                    </div>
                </div>
                <div class="bo-events__form-actions">
                    <button type="submit" class="bo-events__submit">Ajouter le poste</button>
                </div>
            </form>
            <?php endif; ?>

            <?php if ($eventSlots === []): ?>
                <div class="bo-events__empty bo-events__empty--inset">
                    <p>Aucun poste défini pour l’instant</p>
                    <span>Ajoutez des postes pour que les membres s’inscrivent sur un rôle précis (pilote, tireur, chef d’équipe…) plutôt qu’un simple oui/non.</span>
                </div>
            <?php else: ?>
                <div class="bo-events__table-wrap">
                    <table class="bo-events__table">
                        <thead>
                            <tr>
                                <th>Poste</th>
                                <th>Unité</th>
                                <th>Places</th>
                                <th>Inscrits</th>
                                <th>Notes loadout</th>
                                <?php if ($eventStaffActionsEnabled): ?>
                                    <th>Actions</th>
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
                                    <td data-label="Poste"><span class="bo-events__member-name"><?= htmlspecialchars((string) ($slot['label'] ?? '')) ?></span></td>
                                    <td data-label="Unité"><?= $unitName !== '' ? htmlspecialchars($unitName) : '—' ?></td>
                                    <td data-label="Places">
                                        <?= $confirmedN ?> / <?= $capacity ?>
                                        <?php if ($waitlistedN > 0): ?>
                                            <span class="bo-events__chip bo-events__chip--muted"><?= $waitlistedN ?> en attente</span>
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="Inscrits">
                                        <?php if ($roster === []): ?>
                                            <span class="bo-events__chip bo-events__chip--muted">—</span>
                                        <?php else: ?>
                                            <?php foreach ($roster as $a): ?>
                                                <div class="bo-events__cell-main"><?= htmlspecialchars((string) ($a['display_name'] ?? '')) ?><?= (string) ($a['status'] ?? '') === 'waitlisted' ? ' (attente)' : '' ?></div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="Notes loadout"><?= !empty($slot['loadout_notes']) ? nl2br(htmlspecialchars((string) $slot['loadout_notes'])) : '—' ?></td>
                                    <?php if ($eventStaffActionsEnabled): ?>
                                        <td data-label="Actions">
                                            <details>
                                                <summary class="bo-events__link-btn">Modifier</summary>
                                                <form method="post" action="<?= url('back-office/events/' . $eid . '/slots/' . $sid) ?>" class="bo-events__form">
                                                    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">
                                                    <div class="bo-events__form-grid">
                                                        <div>
                                                            <label class="bo-events__label" for="slot-label-<?= $sid ?>">Nom du poste</label>
                                                            <input id="slot-label-<?= $sid ?>" type="text" name="label" required maxlength="160" value="<?= htmlspecialchars((string) ($slot['label'] ?? '')) ?>" class="bo-events__input">
                                                        </div>
                                                        <div>
                                                            <label class="bo-events__label" for="slot-capacity-<?= $sid ?>">Places</label>
                                                            <input id="slot-capacity-<?= $sid ?>" type="number" name="capacity" min="1" max="200" value="<?= $capacity ?>" class="bo-events__input">
                                                        </div>
                                                        <div>
                                                            <label class="bo-events__label" for="slot-unit-<?= $sid ?>">Unité</label>
                                                            <select id="slot-unit-<?= $sid ?>" name="unit_id" class="bo-events__select">
                                                                <option value="0">—</option>
                                                                <?php foreach ($eventUnits as $u): ?>
                                                                <option value="<?= (int) $u['id'] ?>" <?= (int) ($slot['unit_id'] ?? 0) === (int) $u['id'] ? 'selected' : '' ?>><?= htmlspecialchars((string) ($u['name'] ?? '')) ?></option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </div>
                                                        <div class="bo-events__field--full">
                                                            <label class="bo-events__label" for="slot-notes-<?= $sid ?>">Notes de loadout</label>
                                                            <textarea id="slot-notes-<?= $sid ?>" name="loadout_notes" rows="2" class="bo-events__textarea"><?= htmlspecialchars((string) ($slot['loadout_notes'] ?? '')) ?></textarea>
                                                        </div>
                                                    </div>
                                                    <div class="bo-events__form-actions">
                                                        <button type="submit" class="bo-events__submit">Enregistrer</button>
                                                    </div>
                                                </form>
                                            </details>
                                            <form method="post" action="<?= url('back-office/events/' . $eid . '/slots/' . $sid . '/supprimer') ?>" onsubmit="return confirm('Supprimer ce poste ? Les inscriptions associées seront retirées.');">
                                                <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">
                                                <button type="submit" class="bo-events__link-btn bo-events__link-btn--danger">Supprimer</button>
                                            </form>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>

        <section class="bo-events__panel bo-events__panel--static">
            <div class="bo-events__panel-static-head bo-events__panel-static-head--row">
                <div>
                    <h2>Feuille de présence</h2>
                    <p><?= (int) $nTotal ?> membre<?= $nTotal > 1 ? 's' : '' ?> sur la liste</p>
                </div>
            </div>

            <?php if ($eventRsvps === []): ?>
                <div class="bo-events__empty bo-events__empty--inset">
                    <div class="bo-events__empty-icon" aria-hidden="true">
                        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z" /></svg>
                    </div>
                    <p>Aucune réponse pour l’instant</p>
                    <span>Les membres apparaîtront ici dès qu’ils auront confirmé leur participation.</span>
                </div>
            <?php else: ?>
                <div class="bo-events__table-wrap">
                    <table class="bo-events__table">
                        <thead>
                            <tr>
                                <th>Membre</th>
                                <th>Participation</th>
                                <th>Motif d’absence</th>
                                <th>Rappel</th>
                                <th>Pointage</th>
                                <?php if ($eventStaffActionsEnabled): ?>
                                    <th>Actions</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($eventRsvps as $r):
                                $uid = (int) ($r['user_id'] ?? 0);
                                $st = (string) ($r['status'] ?? '');
                                $canPoint = $eventStaffActionsEnabled && in_array($st, ['yes', 'maybe'], true);
                                $hasCheck = !empty($r['checked_in_at']);
                                $statusClass = match ($st) {
                                    'yes' => 'bo-events__chip--yes',
                                    'maybe' => 'bo-events__chip--maybe',
                                    'no' => 'bo-events__chip--no',
                                    default => '',
                                };
                                ?>
                                <tr>
                                    <td data-label="Membre">
                                        <div class="bo-events__member">
                                            <span class="bo-events__member-name"><?= htmlspecialchars((string) ($r['display_name'] ?? '')) ?></span>
                                            <?php if (trim((string) ($r['callsign'] ?? '')) !== ''): ?>
                                                <span class="bo-events__member-call">Indicatif <?= htmlspecialchars(trim((string) $r['callsign'])) ?></span>
                                            <?php endif; ?>
                                            <span class="bo-events__member-mail"><?= htmlspecialchars((string) ($r['email'] ?? '')) ?></span>
                                        </div>
                                    </td>
                                    <td data-label="Participation">
                                        <?php if ($eventStaffActionsEnabled): ?>
                                            <form method="post" action="<?= url('back-office/events/' . $eid . '/participant/rsvp') ?>" class="bo-events__inline-form bo-events__inline-form--stack">
                                                <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">
                                                <input type="hidden" name="user_id" value="<?= $uid ?>">
                                                <select name="participation" class="bo-events__select bo-events__select--sm" aria-label="Participation">
                                                    <option value="yes" <?= $st === 'yes' ? ' selected' : '' ?>>Présent</option>
                                                    <option value="maybe" <?= $st === 'maybe' ? ' selected' : '' ?>>Peut-être</option>
                                                    <option value="no" <?= $st === 'no' ? ' selected' : '' ?>>Absent</option>
                                                    <option value="remove">Retirer de la liste</option>
                                                </select>
                                                <select name="absence_reason" class="bo-events__select bo-events__select--sm" aria-label="Motif d’absence">
                                                    <option value="">Motif d’absence</option>
                                                    <option value="service" <?= ($r['absence_reason'] ?? '') === 'service' ? ' selected' : '' ?>>Service</option>
                                                    <option value="sante" <?= ($r['absence_reason'] ?? '') === 'sante' ? ' selected' : '' ?>>Santé</option>
                                                    <option value="indisponibilite_planifiee" <?= ($r['absence_reason'] ?? '') === 'indisponibilite_planifiee' ? ' selected' : '' ?>>Indisponibilité planifiée</option>
                                                    <option value="absence_non_justifiee" <?= ($r['absence_reason'] ?? '') === 'absence_non_justifiee' ? ' selected' : '' ?>>Absence non justifiée</option>
                                                    <option value="autre" <?= ($r['absence_reason'] ?? '') === 'autre' ? ' selected' : '' ?>>Autre</option>
                                                </select>
                                                <button type="submit" class="bo-events__link-btn">Enregistrer</button>
                                            </form>
                                        <?php else: ?>
                                            <span class="bo-events__chip <?= htmlspecialchars($statusClass) ?>"><?= htmlspecialchars($statusLabel($st)) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="Motif d’absence">
                                        <span class="bo-events__cell-main"><?= htmlspecialchars($absenceLabel(is_string($r['absence_reason'] ?? null) ? (string) $r['absence_reason'] : null)) ?></span>
                                        <?php if (!empty($r['absence_note'])): ?>
                                            <span class="bo-events__cell-note"><?= htmlspecialchars((string) $r['absence_note']) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="Rappel">
                                        <?php if (!empty($r['reminder_sent_at'])): ?>
                                            <span class="bo-events__chip bo-events__chip--yes">Envoyé</span>
                                        <?php else: ?>
                                            <span class="bo-events__chip bo-events__chip--muted">Non</span>
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="Pointage">
                                        <?php if ($hasCheck): ?>
                                            <span class="bo-events__chip bo-events__chip--yes"><?= htmlspecialchars($formatCheckIn((string) $r['checked_in_at'])) ?></span>
                                        <?php else: ?>
                                            <span class="bo-events__chip bo-events__chip--muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <?php if ($eventStaffActionsEnabled): ?>
                                        <td data-label="Actions">
                                            <?php if ($canPoint && !$hasCheck): ?>
                                                <form method="post" action="<?= url('back-office/events/' . $eid . '/participant/presence') ?>">
                                                    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">
                                                    <input type="hidden" name="user_id" value="<?= $uid ?>">
                                                    <button type="submit" class="bo-events__action bo-events__action--dark">Pointer présence</button>
                                                </form>
                                            <?php elseif ($canPoint && $hasCheck): ?>
                                                <form method="post" action="<?= url('back-office/events/' . $eid . '/participant/presence/clear') ?>" onsubmit="return confirm('Effacer l’heure de pointage pour ce membre ?');">
                                                    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">
                                                    <input type="hidden" name="user_id" value="<?= $uid ?>">
                                                    <button type="submit" class="bo-events__link-btn bo-events__link-btn--danger">Effacer le pointage</button>
                                                </form>
                                            <?php else: ?>
                                                <span class="bo-events__chip bo-events__chip--muted">—</span>
                                            <?php endif; ?>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>

        <?php if (!$cancelled): ?>
            <section class="bo-events__panel bo-events__panel--danger">
                <button type="button" class="bo-events__panel-head bo-events__panel-head--danger" @click="dangerOpen = !dangerOpen" :aria-expanded="dangerOpen.toString()">
                    <div>
                        <h2>Annuler le créneau</h2>
                        <p>Les membres indiqués comme présents ou « peut-être » seront prévenus.</p>
                    </div>
                    <svg class="bo-events__chevron" :style="dangerOpen ? 'transform: rotate(180deg)' : ''" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" /></svg>
                </button>
                <form method="post" action="<?= url('back-office/events/' . $eid . '/cancel') ?>" class="bo-events__form" x-show="dangerOpen" x-cloak onsubmit="return confirm('Annuler ce créneau et prévenir les membres inscrits ?');">
                    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">
                    <label class="bo-events__label" for="cancel-reason">Motif affiché aux membres <span>(optionnel)</span></label>
                    <textarea id="cancel-reason" name="cancel_reason" rows="2" class="bo-events__textarea" placeholder="Ex. Report pour conditions météo / indisponibilité serveur…"></textarea>
                    <div class="bo-events__form-actions">
                        <button type="submit" class="bo-events__submit bo-events__submit--danger">Annuler définitivement</button>
                    </div>
                </form>
            </section>
        <?php endif; ?>
    </div>
</div>
