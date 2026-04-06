<?php
/** @var array<string, mixed> $event */
/** @var list<array<string, mixed>> $eventRsvps */

$eventRsvps = $eventRsvps ?? [];
$cancelled = !empty($event['cancelled_at']);

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
?>
<div class="max-w-4xl mx-auto px-6 py-12">
    <?php $s = \App\Core\Session::getFlash('success'); $errFlash = \App\Core\Session::getFlash('error'); ?>
    <?php if ($s): ?><p class="text-emerald-700 text-sm mb-4"><?= htmlspecialchars($s) ?></p><?php endif; ?>
    <?php if ($errFlash): ?><p class="text-red-600 text-sm mb-4"><?= htmlspecialchars($errFlash) ?></p><?php endif; ?>
    <div class="flex items-center justify-between mb-6 gap-4 flex-wrap">
        <div>
            <p class="text-xs font-semibold text-slate-500"><?= htmlspecialchars($typeLabel((string) ($event['event_type'] ?? 'evenement'))) ?></p>
            <h1 class="text-2xl font-black text-slate-900"><?= htmlspecialchars((string) ($event['title'] ?? '')) ?></h1>
            <p class="text-sm text-slate-600 mt-1"><?= htmlspecialchars((string) ($event['starts_at'] ?? '')) ?>
                <?php if (!empty($event['location'])): ?> · <?= htmlspecialchars((string) $event['location']) ?><?php endif; ?></p>
        </div>
        <a href="<?= url('back-office/events') ?>" class="text-sm text-slate-600 hover:underline shrink-0">← Liste</a>
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

    <h2 class="text-lg font-bold text-slate-900 mb-3">Participants</h2>
    <div class="border border-slate-200 rounded-lg overflow-hidden">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold text-slate-600">
                <tr>
                    <th class="px-4 py-2">Membre</th>
                    <th class="px-4 py-2">RSVP</th>
                    <th class="px-4 py-2">Pointage</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php foreach ($eventRsvps as $r): ?>
                    <tr>
                        <td class="px-4 py-2">
                            <?= htmlspecialchars((string) ($r['display_name'] ?? '')) ?>
                            <span class="text-slate-400 text-xs"><?= htmlspecialchars((string) ($r['email'] ?? '')) ?></span>
                        </td>
                        <td class="px-4 py-2"><?= htmlspecialchars($statusLabel((string) ($r['status'] ?? ''))) ?></td>
                        <td class="px-4 py-2"><?= !empty($r['checked_in_at']) ? htmlspecialchars((string) $r['checked_in_at']) : '—' ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php if ($eventRsvps === []): ?>
            <p class="px-4 py-6 text-sm text-slate-500">Aucune réponse pour l’instant.</p>
        <?php endif; ?>
    </div>

    <?php if (!$cancelled): ?>
        <form method="post" action="<?= url('back-office/events/' . (int) ($event['id'] ?? 0) . '/cancel') ?>" class="mt-10 border border-red-100 rounded-lg p-4 bg-red-50/50" onsubmit="return confirm('Annuler cet événement et notifier les inscrits ?');">
            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">
            <h3 class="text-sm font-bold text-red-900">Annuler l’événement</h3>
            <p class="text-xs text-red-800/80 mt-1">Un e-mail sera envoyé aux membres inscrits (présent / peut-être).</p>
            <label class="block mt-3 text-xs text-slate-600">Motif (optionnel)</label>
            <textarea name="cancel_reason" rows="2" class="mt-1 w-full max-w-lg border border-slate-300 rounded px-3 py-2 text-sm"></textarea>
            <button type="submit" class="mt-3 px-4 py-2 bg-red-700 text-white text-sm font-semibold rounded hover:bg-red-800">Annuler définitivement</button>
        </form>
    <?php endif; ?>
</div>
