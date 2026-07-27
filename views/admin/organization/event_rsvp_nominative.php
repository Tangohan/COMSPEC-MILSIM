<?php
declare(strict_types=1);

if (!empty($isBackOfficeShell)) {
    require base_path('views/partials/ath_event_rsvp_nominative.php');
    return;
}

use App\Services\Attendance\EventRsvpNominativeService;

$event = is_array($event ?? null) ? $event : [];
$rows = is_array($nominativeRows ?? null) ? $nominativeRows : [];
$stats = is_array($nominativeStats ?? null) ? $nominativeStats : [];
$sections = is_array($nominativeSections ?? null) ? $nominativeSections : [];
$filters = is_array($nominativeFilters ?? null) ? $nominativeFilters : [];
$responseFilterLabels = is_array($responseFilterLabels ?? null) ? $responseFilterLabels : EventRsvpNominativeService::responseFilterLabelsFr();
$atakFilterLabels = is_array($atakFilterLabels ?? null) ? $atakFilterLabels : EventRsvpNominativeService::atakFilterLabelsFr();
$csrfToken = (string) ($csrfToken ?? \App\Core\Csrf::token());
$eventId = (int) ($event['id'] ?? 0);
$successFlash = \App\Core\Session::getFlash('success');
$errorFlash = \App\Core\Session::getFlash('error');
?>
<div class="max-w-[1400px] mx-auto px-4 sm:px-6 py-8 space-y-6">
    <header class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <p class="text-xs font-bold uppercase tracking-widest text-slate-500">Présences</p>
        <div class="mt-2 flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="text-2xl font-black text-slate-900">Réponses nominatives</h1>
                <p class="mt-1 text-sm text-slate-600">
                    <?= htmlspecialchars((string) ($event['title'] ?? 'Créneau'), ENT_QUOTES, 'UTF-8') ?>
                    · <?= (int) ($stats['total'] ?? count($rows)) ?> ligne<?= ((int) ($stats['total'] ?? count($rows)) > 1) ? 's' : '' ?>
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="<?= htmlspecialchars(url('back-office/events/' . $eventId), ENT_QUOTES, 'UTF-8') ?>" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-800">RSVP &amp; pointage</a>
                <a href="<?= htmlspecialchars(url('back-office/events'), ENT_QUOTES, 'UTF-8') ?>" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-800">Liste des créneaux</a>
            </div>
        </div>
    </header>

    <?php if ($successFlash): ?>
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900"><?= htmlspecialchars((string) $successFlash, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>
    <?php if ($errorFlash): ?>
        <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900"><?= htmlspecialchars((string) $errorFlash, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
        <article class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-xs font-bold uppercase tracking-widest text-emerald-600">Confirmés</p>
            <p class="mt-1 text-2xl font-black text-slate-900"><?= (int) ($stats['confirmed'] ?? 0) ?></p>
        </article>
        <article class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-xs font-bold uppercase tracking-widest text-amber-600">Peut-être</p>
            <p class="mt-1 text-2xl font-black text-slate-900"><?= (int) ($stats['maybe'] ?? 0) ?></p>
        </article>
        <article class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-xs font-bold uppercase tracking-widest text-sky-600">Sans réponse</p>
            <p class="mt-1 text-2xl font-black text-slate-900"><?= (int) ($stats['no_response'] ?? 0) ?></p>
        </article>
        <article class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-xs font-bold uppercase tracking-widest text-slate-500">Déclinés</p>
            <p class="mt-1 text-2xl font-black text-slate-900"><?= (int) ($stats['declined'] ?? 0) ?></p>
        </article>
        <article class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-xs font-bold uppercase tracking-widest text-emerald-600">ATAK actifs</p>
            <p class="mt-1 text-2xl font-black text-slate-900"><?= (int) ($stats['atak_active'] ?? 0) ?></p>
        </article>
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
        <form method="get" action="<?= htmlspecialchars(url('back-office/events/' . $eventId . '/reponses-nominatives'), ENT_QUOTES, 'UTF-8') ?>" class="flex flex-wrap items-end gap-3">
            <div class="min-w-[160px] flex-1">
                <label class="block text-xs font-bold text-slate-700 mb-1">Filtrer</label>
                <input type="search" name="q" value="<?= htmlspecialchars((string) ($filters['q'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" placeholder="Nom, indicatif, section…" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Réponse</label>
                <select name="response" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="">Toutes</option>
                    <?php foreach ($responseFilterLabels as $key => $label): ?>
                        <option value="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>" <?= ($filters['response'] ?? '') === $key ? 'selected' : '' ?>><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Section</label>
                <select name="section" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="">Toutes</option>
                    <?php foreach ($sections as $section): ?>
                        <option value="<?= htmlspecialchars($section, ENT_QUOTES, 'UTF-8') ?>" <?= ($filters['section'] ?? '') === $section ? 'selected' : '' ?>><?= htmlspecialchars($section, ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">ATAK</label>
                <select name="atak" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="">Tous</option>
                    <?php foreach ($atakFilterLabels as $key => $label): ?>
                        <option value="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>" <?= ($filters['atak'] ?? '') === $key ? 'selected' : '' ?>><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-bold text-white">Appliquer</button>
            <a href="<?= htmlspecialchars(url('back-office/events/' . $eventId . '/reponses-nominatives/export') . '?' . http_build_query(array_filter($filters)), ENT_QUOTES, 'UTF-8') ?>" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-800">Exporter CSV</a>
        </form>
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
        <div class="border-b border-slate-100 px-6 py-3 flex items-center justify-between gap-4">
            <p class="text-xs text-slate-500">
                Affichage 1 – <?= count($rows) ?> sur <?= (int) ($stats['total'] ?? count($rows)) ?>
                · mis à jour <?= htmlspecialchars((string) ($stats['updated_at_label'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
            </p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[1200px] text-sm">
                <thead class="bg-slate-50 text-slate-600 text-xs uppercase tracking-wide">
                    <tr>
                        <th class="px-3 py-3 text-left">Matricule</th>
                        <th class="px-3 py-3 text-left">Indicatif</th>
                        <th class="px-3 py-3 text-left">Nom</th>
                        <th class="px-3 py-3 text-left">Section</th>
                        <th class="px-3 py-3 text-left">Rôle prévu</th>
                        <th class="px-3 py-3 text-left">Réponse</th>
                        <th class="px-3 py-3 text-left">Répondu le</th>
                        <th class="px-3 py-3 text-left">Dispo. horaire</th>
                        <th class="px-3 py-3 text-left">ATAK</th>
                        <th class="px-3 py-3 text-left">Terminal</th>
                        <th class="px-3 py-3 text-left">Relances</th>
                        <th class="px-3 py-3 text-left">Présence hist.</th>
                        <th class="px-3 py-3 text-left">Commentaires</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr class="border-t border-slate-100 align-top">
                        <td class="px-3 py-3 font-mono text-xs text-slate-700"><?= htmlspecialchars((string) ($row['matricule'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                        <td class="px-3 py-3 font-semibold text-slate-900"><?= htmlspecialchars((string) ($row['callsign'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                        <td class="px-3 py-3"><?= htmlspecialchars((string) ($row['display_name'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                        <td class="px-3 py-3"><?= htmlspecialchars((string) ($row['section'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                        <td class="px-3 py-3"><?= htmlspecialchars((string) ($row['planned_role'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                        <td class="px-3 py-3">
                            <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold <?= htmlspecialchars((string) ($row['response_badge_class'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                <?= htmlspecialchars((string) ($row['response_label'] ?? '—'), ENT_QUOTES, 'UTF-8') ?>
                            </span>
                        </td>
                        <td class="px-3 py-3 text-slate-600"><?= htmlspecialchars((string) ($row['responded_label'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                        <td class="px-3 py-3 text-slate-600"><?= htmlspecialchars((string) ($row['availability_label'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                        <td class="px-3 py-3">
                            <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold <?= htmlspecialchars((string) ($row['atak_badge_class'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                <?= htmlspecialchars((string) ($row['atak_label'] ?? '—'), ENT_QUOTES, 'UTF-8') ?>
                            </span>
                        </td>
                        <td class="px-3 py-3 font-mono text-xs text-slate-700"><?= htmlspecialchars((string) ($row['atak_terminal_label'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                        <td class="px-3 py-3 text-center"><?= (int) ($row['reminder_count'] ?? 0) ?></td>
                        <td class="px-3 py-3"><?= (int) ($row['historical_presence_pct'] ?? 0) ?> %</td>
                        <td class="px-3 py-3 text-slate-600 max-w-[180px] truncate" title="<?= htmlspecialchars((string) ($row['admin_comment'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                            <?= htmlspecialchars((string) (($row['admin_comment'] ?? '') !== '' ? $row['admin_comment'] : '—'), ENT_QUOTES, 'UTF-8') ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($rows === []): ?>
                    <tr><td colspan="13" class="px-4 py-10 text-center text-slate-500">Aucun membre ne correspond aux filtres.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>
