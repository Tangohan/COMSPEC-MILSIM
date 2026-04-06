<?php
/** @var array<string, mixed> $event */
/** @var list<array<string, mixed>> $eventRsvps */
/** @var list<array<string, mixed>> $eventMemberLookup */
/** @var string $eventMemberLookupQuery */
/** @var array<int, bool> $eventRsvpUserIds */
/** @var bool $eventStaffActionsEnabled */

$eventRsvps = $eventRsvps ?? [];
$eventMemberLookup = $eventMemberLookup ?? [];
$eventMemberLookupQuery = $eventMemberLookupQuery ?? '';
$eventRsvpUserIds = $eventRsvpUserIds ?? [];
$eventStaffActionsEnabled = $eventStaffActionsEnabled ?? false;
$cancelled = !empty($event['cancelled_at']);
$eid = (int) ($event['id'] ?? 0);

$typeLabel = static function (string $t): string {
    return match ($t) {
        'operation' => 'Opération',
        'formation' => 'Formation',
        'autre' => 'Autre',
        default => 'Événement',
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
?>
<div class="max-w-5xl mx-auto px-6 py-12">
    <?php $s = \App\Core\Session::getFlash('success'); $errFlash = \App\Core\Session::getFlash('error'); ?>
    <?php if ($s): ?><p class="text-emerald-700 text-sm mb-4"><?= htmlspecialchars($s) ?></p><?php endif; ?>
    <?php if ($errFlash): ?><p class="text-red-600 text-sm mb-4"><?= htmlspecialchars($errFlash) ?></p><?php endif; ?>
    <div class="flex items-start justify-between mb-6 gap-4 flex-wrap">
        <div>
            <p class="text-xs font-semibold text-slate-500"><?= htmlspecialchars($typeLabel((string) ($event['event_type'] ?? 'evenement'))) ?></p>
            <h1 class="text-2xl font-black text-slate-900"><?= htmlspecialchars((string) ($event['title'] ?? '')) ?></h1>
            <p class="text-sm text-slate-600 mt-1"><?= htmlspecialchars((string) ($event['starts_at'] ?? '')) ?>
                <?php if (!empty($event['location'])): ?> · <?= htmlspecialchars((string) $event['location']) ?><?php endif; ?></p>
        </div>
        <div class="flex flex-col items-end gap-2 shrink-0">
            <a href="<?= url('back-office/events/' . $eid . '/export-presences') ?>" class="text-sm font-semibold text-emerald-700 hover:underline">Télécharger la feuille (tableur)</a>
            <a href="<?= url('back-office/events') ?>" class="text-sm text-slate-600 hover:underline">← Liste des créneaux</a>
        </div>
    </div>

    <?php if ($cancelled): ?>
        <p class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 mb-6">
            Annulé le <?= htmlspecialchars((string) ($event['cancelled_at'] ?? '')) ?>
            <?php if (!empty($event['cancelled_reason'])): ?><br>Motif : <?= nl2br(htmlspecialchars((string) $event['cancelled_reason'])) ?><?php endif; ?>
        </p>
    <?php endif; ?>

    <?php if (!empty($event['description'])): ?>
        <p class="text-sm text-slate-700 mb-8"><?= nl2br(htmlspecialchars((string) $event['description'])) ?></p>
    <?php endif; ?>

    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-8">
        <div class="rounded-lg border border-slate-200 bg-white px-4 py-3 text-center">
            <p class="text-2xl font-black text-slate-900"><?= (int) $nYes ?></p>
            <p class="text-xs font-semibold text-slate-500">Présents</p>
        </div>
        <div class="rounded-lg border border-slate-200 bg-white px-4 py-3 text-center">
            <p class="text-2xl font-black text-slate-900"><?= (int) $nMaybe ?></p>
            <p class="text-xs font-semibold text-slate-500">Peut-être</p>
        </div>
        <div class="rounded-lg border border-slate-200 bg-white px-4 py-3 text-center">
            <p class="text-2xl font-black text-slate-900"><?= (int) $nNo ?></p>
            <p class="text-xs font-semibold text-slate-500">Absents</p>
        </div>
        <div class="rounded-lg border border-emerald-100 bg-emerald-50/80 px-4 py-3 text-center">
            <p class="text-2xl font-black text-emerald-900"><?= (int) $nChecked ?></p>
            <p class="text-xs font-semibold text-emerald-800">Pointés</p>
        </div>
    </div>

    <?php if ($eventStaffActionsEnabled): ?>
        <p class="text-xs text-slate-500 mb-6 max-w-2xl">
            Les membres peuvent pointer eux-mêmes depuis l’espace « Pointage & présence » lorsque la fenêtre prévue par le portail est ouverte.
            Ici vous pouvez corriger une participation, ajouter quelqu’un ou enregistrer une présence pour son compte si besoin.
        </p>
    <?php endif; ?>

    <?php if ($eventStaffActionsEnabled): ?>
        <section class="mb-10 border border-slate-200 rounded-lg p-4 bg-slate-50/50">
            <h2 class="text-sm font-bold text-slate-900 mb-2">Ajouter un membre à la feuille</h2>
            <p class="text-xs text-slate-600 mb-3">Saisissez au moins deux lettres du nom affiché ou de l’indicatif.</p>
            <form method="get" action="<?= url('back-office/events/' . $eid) ?>" class="flex flex-wrap gap-2 items-end mb-4">
                <div class="flex-1 min-w-[200px]">
                    <label class="block text-xs text-slate-500 mb-1">Recherche</label>
                    <input type="search" name="q" value="<?= htmlspecialchars($eventMemberLookupQuery) ?>" minlength="2" autocomplete="off" class="w-full border border-slate-300 rounded px-3 py-2 text-sm" placeholder="Ex. Martin ou Foxtrot">
                </div>
                <button type="submit" class="px-4 py-2 bg-slate-800 text-white text-sm font-semibold rounded hover:bg-slate-900">Chercher</button>
            </form>
            <?php if ($eventMemberLookup !== []): ?>
                <ul class="divide-y divide-slate-200 border border-slate-200 rounded-lg bg-white text-sm">
                    <?php foreach ($eventMemberLookup as $hit):
                        $hid = (int) ($hit['id'] ?? 0);
                        $already = $hid > 0 && !empty($eventRsvpUserIds[$hid]);
                        $dn = (string) ($hit['display_name'] ?? '');
                        $cs = trim((string) ($hit['callsign'] ?? ''));
                        ?>
                        <li class="px-4 py-3 flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <span class="font-semibold text-slate-900"><?= htmlspecialchars($dn) ?></span>
                                <?php if ($cs !== ''): ?>
                                    <span class="text-slate-500 text-xs ml-2"><?= htmlspecialchars($cs) ?></span>
                                <?php endif; ?>
                            </div>
                            <?php if ($already): ?>
                                <span class="text-xs text-slate-500">Déjà sur la feuille — modifiez la ligne dans le tableau ci-dessous.</span>
                            <?php else: ?>
                                <form method="post" action="<?= url('back-office/events/' . $eid . '/participant/add') ?>" class="flex flex-wrap items-center gap-2">
                                    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">
                                    <input type="hidden" name="user_id" value="<?= $hid ?>">
                                    <label class="text-xs text-slate-500 sr-only">Participation</label>
                                    <select name="participation" class="border border-slate-300 rounded px-2 py-1.5 text-xs">
                                        <option value="yes">Présent</option>
                                        <option value="maybe">Peut-être</option>
                                        <option value="no">Absent</option>
                                    </select>
                                    <button type="submit" class="px-3 py-1.5 bg-emerald-700 text-white text-xs font-semibold rounded hover:bg-emerald-800">Ajouter</button>
                                </form>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php elseif (strlen($eventMemberLookupQuery) >= 2): ?>
                <p class="text-sm text-slate-500">Aucun résultat pour cette recherche.</p>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <h2 class="text-lg font-bold text-slate-900 mb-3">Feuille de présence</h2>
    <div class="border border-slate-200 rounded-lg overflow-hidden overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold text-slate-600">
                <tr>
                    <th class="px-4 py-2">Membre</th>
                    <th class="px-4 py-2">Participation</th>
                    <th class="px-4 py-2">Rappel</th>
                    <th class="px-4 py-2">Pointage</th>
                    <th class="px-4 py-2">Actions staff</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php foreach ($eventRsvps as $r):
                    $uid = (int) ($r['user_id'] ?? 0);
                    $st = (string) ($r['status'] ?? '');
                    $canPoint = $eventStaffActionsEnabled && in_array($st, ['yes', 'maybe'], true);
                    $hasCheck = !empty($r['checked_in_at']);
                    ?>
                    <tr class="align-top">
                        <td class="px-4 py-3">
                            <div class="font-medium text-slate-900"><?= htmlspecialchars((string) ($r['display_name'] ?? '')) ?></div>
                            <div class="text-xs text-slate-500"><?= htmlspecialchars((string) ($r['email'] ?? '')) ?></div>
                            <?php if (trim((string) ($r['callsign'] ?? '')) !== ''): ?>
                                <div class="text-xs text-slate-400 mt-0.5">Indicatif <?= htmlspecialchars(trim((string) $r['callsign'])) ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3">
                            <?php if ($eventStaffActionsEnabled): ?>
                                <form method="post" action="<?= url('back-office/events/' . $eid . '/participant/rsvp') ?>" class="flex flex-col gap-2 sm:flex-row sm:items-center">
                                    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">
                                    <input type="hidden" name="user_id" value="<?= $uid ?>">
                                    <select name="participation" class="border border-slate-300 rounded px-2 py-1.5 text-xs max-w-[11rem]">
                                        <option value="yes" <?= $st === 'yes' ? ' selected' : '' ?>>Présent</option>
                                        <option value="maybe" <?= $st === 'maybe' ? ' selected' : '' ?>>Peut-être</option>
                                        <option value="no" <?= $st === 'no' ? ' selected' : '' ?>>Absent</option>
                                        <option value="remove">Retirer de la liste</option>
                                    </select>
                                    <button type="submit" class="text-xs font-semibold text-emerald-700 hover:underline whitespace-nowrap">Enregistrer</button>
                                </form>
                            <?php else: ?>
                                <?= htmlspecialchars($statusLabel($st)) ?>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3 text-xs text-slate-600">
                            <?php if (!empty($r['reminder_sent_at'])): ?>
                                <span class="text-emerald-800 font-medium">Oui</span>
                            <?php else: ?>
                                <span class="text-slate-400">Non</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3 text-xs text-slate-700">
                            <?= !empty($r['checked_in_at']) ? htmlspecialchars((string) $r['checked_in_at']) : '—' ?>
                        </td>
                        <td class="px-4 py-3">
                            <?php if ($eventStaffActionsEnabled && $canPoint && !$hasCheck): ?>
                                <form method="post" action="<?= url('back-office/events/' . $eid . '/participant/presence') ?>" class="inline">
                                    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">
                                    <input type="hidden" name="user_id" value="<?= $uid ?>">
                                    <button type="submit" class="text-xs font-semibold text-white bg-slate-800 rounded px-2 py-1 hover:bg-slate-900">Pointer présence</button>
                                </form>
                            <?php elseif ($eventStaffActionsEnabled && $canPoint && $hasCheck): ?>
                                <form method="post" action="<?= url('back-office/events/' . $eid . '/participant/presence/clear') ?>" class="inline" onsubmit="return confirm('Effacer l’heure de pointage pour ce membre ?');">
                                    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">
                                    <input type="hidden" name="user_id" value="<?= $uid ?>">
                                    <button type="submit" class="text-xs font-semibold text-red-700 hover:underline">Effacer le pointage</button>
                                </form>
                            <?php elseif ($eventStaffActionsEnabled && !$canPoint): ?>
                                <span class="text-xs text-slate-400">—</span>
                            <?php else: ?>
                                <span class="text-xs text-slate-400">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php if ($eventRsvps === []): ?>
            <p class="px-4 py-6 text-sm text-slate-500">Aucune réponse pour l’instant.</p>
        <?php endif; ?>
    </div>

    <?php if (!$cancelled): ?>
        <form method="post" action="<?= url('back-office/events/' . $eid . '/cancel') ?>" class="mt-10 border border-red-100 rounded-lg p-4 bg-red-50/50" onsubmit="return confirm('Annuler ce créneau et prévenir les membres inscrits ?');">
            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">
            <h3 class="text-sm font-bold text-red-900">Annuler le créneau</h3>
            <p class="text-xs text-red-800/80 mt-1">Un message sera envoyé aux membres indiqués comme présents ou « peut-être ».</p>
            <label class="block mt-3 text-xs text-slate-600">Motif affiché aux membres (optionnel)</label>
            <textarea name="cancel_reason" rows="2" class="mt-1 w-full max-w-lg border border-slate-300 rounded px-3 py-2 text-sm"></textarea>
            <button type="submit" class="mt-3 px-4 py-2 bg-red-700 text-white text-sm font-semibold rounded hover:bg-red-800">Annuler définitivement</button>
        </form>
    <?php endif; ?>
</div>
