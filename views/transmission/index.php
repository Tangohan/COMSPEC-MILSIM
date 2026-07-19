<?php
declare(strict_types=1);

/**
 * @var list<array<string,mixed>> $transmissionSessions
 * @var string|null $transmissionStatusFilter
 * @var bool $transmissionCanManage
 * @var list<array<string,mixed>> $transmissionUpcomingEvents
 */

$sessions = is_array($transmissionSessions ?? null) ? $transmissionSessions : [];
$statusFilter = $transmissionStatusFilter ?? null;
$canManage = (bool) ($transmissionCanManage ?? false);
$upcomingEvents = is_array($transmissionUpcomingEvents ?? null) ? $transmissionUpcomingEvents : [];
$flashSuccess = \App\Core\Session::getFlash('success');
$flashError = \App\Core\Session::getFlash('error');

$statusLabel = static function (string $st): array {
    return $st === 'closed'
        ? ['label' => 'Fermée', 'class' => 'bg-slate-100 text-slate-700 ring-slate-200']
        : ['label' => 'Ouverte', 'class' => 'bg-emerald-50 text-emerald-800 ring-emerald-200'];
};
?>
<div class="mx-auto max-w-5xl space-y-6 px-4 py-8 sm:px-6">
    <div>
        <p class="text-[10px] font-black uppercase tracking-[0.28em] text-slate-500">Renseignement</p>
        <h1 class="mt-1 text-2xl font-black tracking-tight text-slate-900">Transmission de reconnaissance</h1>
        <p class="mt-2 max-w-2xl text-sm text-slate-600">
            Une session par mission : la reconnaissance publie ses comptes-rendus (mini-PV, captures d'écran)
            dans un fil chronologique, que le Mission Maker synthétise ensuite en Plan d'Exécution (PoE).
        </p>
    </div>

    <?php if ($flashSuccess): ?>
    <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900"><?= htmlspecialchars((string) $flashSuccess, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>
    <?php if ($flashError): ?>
    <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900"><?= htmlspecialchars((string) $flashError, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <?php if ($canManage): ?>
    <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="text-sm font-black uppercase tracking-wide text-slate-900">Ouvrir une session de transmission</h2>
        <form method="post" action="<?= htmlspecialchars(url('transmission'), ENT_QUOTES, 'UTF-8') ?>" class="mt-4 grid gap-4 sm:grid-cols-3">
            <?= \App\Core\Csrf::field() ?>
            <div class="sm:col-span-2">
                <label class="block text-xs font-bold uppercase tracking-wide text-slate-600" for="ts-title">Titre (ex. nom de la mission)</label>
                <input type="text" id="ts-title" name="title" maxlength="200" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="Op. Faucon Noir — 22/07">
            </div>
            <div>
                <label class="block text-xs font-bold uppercase tracking-wide text-slate-600" for="ts-event">Événement lié (optionnel)</label>
                <select id="ts-event" name="community_event_id" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="">— Aucun —</option>
                    <?php foreach ($upcomingEvents as $ev): ?>
                        <?php
                        $evId = (int) ($ev['id'] ?? 0);
                        $evTitle = trim((string) ($ev['title'] ?? 'Événement'));
                        $evStarts = trim((string) ($ev['starts_at'] ?? ''));
                        $evLabel = $evTitle . ($evStarts !== '' ? ' — ' . date('d/m/Y', strtotime($evStarts)) : '');
                        ?>
                    <option value="<?= $evId ?>"><?= htmlspecialchars($evLabel, ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="sm:col-span-3">
                <button type="submit" class="rounded-lg bg-slate-900 px-4 py-2 text-xs font-bold uppercase tracking-wide text-white hover:bg-slate-800">Ouvrir la session</button>
            </div>
        </form>
    </section>
    <?php endif; ?>

    <div class="flex gap-2">
        <a href="<?= htmlspecialchars(url('transmission') . '?status=open', ENT_QUOTES, 'UTF-8') ?>" class="rounded-lg border px-3 py-1.5 text-[11px] font-bold uppercase tracking-wide <?= $statusFilter === 'open' ? 'border-slate-900 bg-slate-900 text-white' : 'border-slate-300 bg-white text-slate-700' ?>">Ouvertes</a>
        <a href="<?= htmlspecialchars(url('transmission') . '?status=closed', ENT_QUOTES, 'UTF-8') ?>" class="rounded-lg border px-3 py-1.5 text-[11px] font-bold uppercase tracking-wide <?= $statusFilter === 'closed' ? 'border-slate-900 bg-slate-900 text-white' : 'border-slate-300 bg-white text-slate-700' ?>">Fermées</a>
        <a href="<?= htmlspecialchars(url('transmission') . '?status=', ENT_QUOTES, 'UTF-8') ?>" class="rounded-lg border px-3 py-1.5 text-[11px] font-bold uppercase tracking-wide <?= $statusFilter === null ? 'border-slate-900 bg-slate-900 text-white' : 'border-slate-300 bg-white text-slate-700' ?>">Toutes</a>
    </div>

    <?php if ($sessions === []): ?>
    <div class="rounded-2xl border-2 border-dashed border-slate-300 bg-slate-50 px-6 py-12 text-center">
        <p class="text-sm text-slate-600">Aucune session de transmission<?= $statusFilter === 'open' ? ' ouverte' : ($statusFilter === 'closed' ? ' fermée' : '') ?> pour le moment.</p>
    </div>
    <?php else: ?>
    <div class="space-y-3">
        <?php foreach ($sessions as $s): ?>
            <?php
            $sid = (int) ($s['id'] ?? 0);
            $sTitle = trim((string) ($s['title'] ?? ''));
            $st = $statusLabel((string) ($s['status'] ?? 'open'));
            $entryCount = (int) ($s['entry_count'] ?? 0);
            $eventTitle = trim((string) ($s['event_title'] ?? ''));
            $openedByName = trim((string) ($s['opened_by_name'] ?? '')) ?: trim((string) ($s['opened_by_email'] ?? ''));
            $createdAt = trim((string) ($s['created_at'] ?? ''));
            ?>
        <a href="<?= htmlspecialchars(url('transmission/' . $sid), ENT_QUOTES, 'UTF-8') ?>" class="flex items-center justify-between gap-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-emerald-300 hover:bg-emerald-50/30">
            <div class="min-w-0">
                <div class="flex items-center gap-2">
                    <p class="truncate text-sm font-black text-slate-900"><?= htmlspecialchars($sTitle, ENT_QUOTES, 'UTF-8') ?></p>
                    <span class="inline-flex shrink-0 items-center rounded-full px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide ring-1 <?= $st['class'] ?>"><?= $st['label'] ?></span>
                </div>
                <p class="mt-1 text-xs text-slate-500">
                    <?php if ($eventTitle !== ''): ?>Mission : <?= htmlspecialchars($eventTitle, ENT_QUOTES, 'UTF-8') ?> · <?php endif; ?>
                    Ouverte par <?= htmlspecialchars($openedByName !== '' ? $openedByName : 'Membre', ENT_QUOTES, 'UTF-8') ?>
                    <?= $createdAt !== '' ? ' le ' . date('d/m/Y', strtotime($createdAt)) : '' ?>
                </p>
            </div>
            <div class="shrink-0 text-right">
                <p class="text-lg font-black text-slate-900"><?= $entryCount ?></p>
                <p class="text-[10px] font-bold uppercase tracking-wide text-slate-500">PV</p>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
