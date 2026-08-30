<?php
/** @var array<string, mixed> $action_center_digest */
$action_center_digest = $action_center_digest ?? [];
$sections = $action_center_digest['sections'] ?? [];
$totalAttention = max(0, (int) ($action_center_digest['total_attention'] ?? 0));
$todayLabel = (new DateTimeImmutable('now'))->format('d/m/Y');
?>
<div class="mx-auto max-w-5xl px-4 py-8 sm:px-6 lg:px-8">
    <header class="mb-10">
        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-emerald-700">Synthèse personnelle · <?= htmlspecialchars($todayLabel, ENT_QUOTES, 'UTF-8') ?></p>
        <div class="mt-2 flex flex-wrap items-end justify-between gap-4">
            <h1 class="text-3xl font-black tracking-tight text-slate-900 sm:text-4xl">Aujourd’hui</h1>
            <div class="rounded-2xl border <?= $totalAttention > 0 ? 'border-amber-200 bg-amber-50 text-amber-950' : 'border-emerald-200 bg-emerald-50 text-emerald-950' ?> px-4 py-3">
                <strong class="text-2xl"><?= $totalAttention ?></strong>
                <span class="ml-1 text-xs font-bold uppercase tracking-[0.12em]">élément<?= $totalAttention > 1 ? 's' : '' ?> à traiter</span>
            </div>
        </div>
        <p class="mt-3 text-sm leading-relaxed text-slate-600">
            Votre briefing transversal : messages, courriers et dossiers prioritaires réunis sans déplacer les actions hors de leur module métier.
        </p>
    </header>

    <div class="space-y-10">
        <?php foreach ($sections as $secIdx => $sec): ?>
        <?php
        $st = (string) ($sec['title'] ?? '');
        $items = $sec['items'] ?? [];
        if (! is_array($items) || $items === [] || $st === '') {
            continue;
        }
        $secDomId = $st === 'Agenda et échéances' ? 'agenda-et-echeances' : 'action-center-sec-' . (int) $secIdx;
        ?>
        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm" aria-labelledby="<?= htmlspecialchars($secDomId, ENT_QUOTES, 'UTF-8') ?>">
            <h2 id="<?= htmlspecialchars($secDomId, ENT_QUOTES, 'UTF-8') ?>" class="text-sm font-bold text-slate-900"><?= htmlspecialchars($st, ENT_QUOTES, 'UTF-8') ?></h2>
            <ul class="mt-4 grid gap-3 sm:grid-cols-2">
                <?php foreach ($items as $it): ?>
                <?php
                if (! is_array($it)) {
                    continue;
                }
                $label = (string) ($it['label'] ?? '');
                $href = (string) ($it['href'] ?? '');
                $hint = (string) ($it['hint'] ?? '');
                $count = isset($it['count']) ? (int) $it['count'] : null;
                if ($label === '' || $href === '') {
                    continue;
                }
                $meta = $count !== null && $count > 0 ? (string) $count : '';
                $priority = (string) ($it['priority'] ?? 'low');
                $action = (string) ($it['action'] ?? 'Ouvrir');
                $eventId = max(0, (int) ($it['event_id'] ?? 0));
                $rsvpStatus = (string) ($it['rsvp_status'] ?? '');
                ?>
                <li>
                    <article class="group flex h-full items-start gap-4 rounded-xl border border-slate-200 bg-slate-50 p-4 transition hover:-translate-y-0.5 hover:border-emerald-300 hover:bg-white hover:shadow-sm">
                        <span class="mt-1 h-2.5 w-2.5 shrink-0 rounded-full <?= $priority === 'high' ? 'bg-amber-500' : ($priority === 'normal' ? 'bg-sky-500' : 'bg-slate-300') ?>" aria-hidden="true"></span>
                        <div class="min-w-0 flex-1">
                            <span class="flex items-center justify-between gap-3">
                                <strong class="text-sm text-slate-950"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></strong>
                                <?php if ($meta !== ''): ?><span class="rounded-full bg-slate-900 px-2 py-0.5 text-xs font-bold text-white"><?= htmlspecialchars($meta, ENT_QUOTES, 'UTF-8') ?></span><?php endif; ?>
                            </span>
                            <span class="mt-1 block text-xs leading-relaxed text-slate-600"><?= htmlspecialchars($hint, ENT_QUOTES, 'UTF-8') ?></span>
                            <?php if ($eventId > 0): ?>
                                <div class="mt-3">
                                    <?php
                                    $rsvpEventId = $eventId;
                                    $rsvpCurrentStatus = $rsvpStatus;
                                    $rsvpCompact = true;
                                    $rsvpShowAbsenceReason = false;
                                    require base_path('views/partials/dashboard_rsvp_buttons.php');
                                    ?>
                                </div>
                            <?php else: ?>
                                <a href="<?= htmlspecialchars($href, ENT_QUOTES, 'UTF-8') ?>" class="mt-3 inline-block text-xs font-bold text-emerald-700 group-hover:text-emerald-800"><?= htmlspecialchars($action, ENT_QUOTES, 'UTF-8') ?> →</a>
                            <?php endif; ?>
                        </div>
                    </article>
                </li>
                <?php endforeach; ?>
            </ul>
        </section>
        <?php endforeach; ?>
    </div>

    <p class="mt-12 text-center text-sm text-slate-500">
        <a href="<?= htmlspecialchars(url('hub'), ENT_QUOTES, 'UTF-8') ?>" class="font-semibold text-emerald-700 underline decoration-emerald-200 underline-offset-2 hover:text-emerald-800">Retour au centre de commandement</a>
    </p>
</div>
