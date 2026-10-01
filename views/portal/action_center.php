<?php
/** @var array<string, mixed> $action_center_digest */
/** @var array<string, mixed> $mon_service */
$action_center_digest = $action_center_digest ?? [];
$mon_service = is_array($mon_service ?? null) ? $mon_service : [];
$sections = $action_center_digest['sections'] ?? [];
$totalAttention = max(0, (int) ($action_center_digest['total_attention'] ?? 0));
$todayLabel = (new DateTimeImmutable('now'))->format('d/m/Y');
$tasks = is_array($mon_service['tasks'] ?? null) ? $mon_service['tasks'] : [];
$waiting = is_array($mon_service['waiting'] ?? null) ? $mon_service['waiting'] : [];
$missionDuty = is_array($mon_service['mission_duty'] ?? null) ? $mon_service['mission_duty'] : null;
$csrf = \App\Core\Csrf::token();
?>
<div class="mx-auto max-w-5xl px-4 py-8 sm:px-6 lg:px-8">
    <header class="mb-8">
        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-emerald-700">Mon service · <?= htmlspecialchars($todayLabel, ENT_QUOTES, 'UTF-8') ?></p>
        <div class="mt-2 flex flex-wrap items-end justify-between gap-4">
            <h1 class="text-3xl font-black tracking-tight text-slate-900 sm:text-4xl"><?= htmlspecialchars((string) ($mon_service['display_name'] ?? 'Mon service'), ENT_QUOTES, 'UTF-8') ?></h1>
            <div class="rounded-2xl border <?= $totalAttention > 0 ? 'border-amber-200 bg-amber-50 text-amber-950' : 'border-emerald-200 bg-emerald-50 text-emerald-950' ?> px-4 py-3">
                <strong class="text-2xl"><?= $totalAttention ?></strong>
                <span class="ml-1 text-xs font-bold uppercase tracking-[0.12em]">élément<?= $totalAttention > 1 ? 's' : '' ?> à traiter</span>
            </div>
        </div>
        <p class="mt-3 text-sm leading-relaxed text-slate-600">
            Ce que votre poste attend de vous : tâches affectées, validations, puis le reste du briefing personnel.
        </p>
    </header>

    <section class="mb-10 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm" aria-label="Identité et affectation">
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <p class="text-[10px] font-bold uppercase tracking-[0.16em] text-slate-400">Grade</p>
                <p class="mt-1 text-sm font-semibold text-slate-900"><?= htmlspecialchars((string) ($mon_service['grade_label'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></p>
            </div>
            <div>
                <p class="text-[10px] font-bold uppercase tracking-[0.16em] text-slate-400">Unité</p>
                <p class="mt-1 text-sm font-semibold text-slate-900"><?= htmlspecialchars((string) ($mon_service['unit_label'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></p>
            </div>
            <div>
                <p class="text-[10px] font-bold uppercase tracking-[0.16em] text-slate-400">Poste org.</p>
                <p class="mt-1 text-sm font-semibold text-slate-900"><?= htmlspecialchars((string) ($mon_service['organic_post_label'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></p>
            </div>
            <div>
                <p class="text-[10px] font-bold uppercase tracking-[0.16em] text-slate-400">Rôle Athena</p>
                <p class="mt-1 text-sm font-semibold text-slate-900"><?= htmlspecialchars((string) ($mon_service['technical_role_label'] ?? 'Membre'), ENT_QUOTES, 'UTF-8') ?></p>
            </div>
        </div>

        <?php if ($missionDuty): ?>
        <div class="mt-6 border-t border-slate-100 pt-5">
            <p class="text-[10px] font-bold uppercase tracking-[0.16em] text-emerald-700">Affectation mission</p>
            <div class="mt-3 grid gap-3 sm:grid-cols-3">
                <div>
                    <p class="text-[10px] uppercase tracking-wide text-slate-400">Poste</p>
                    <p class="text-sm font-semibold text-slate-900"><?= htmlspecialchars((string) ($missionDuty['duty_title'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                </div>
                <div>
                    <p class="text-[10px] uppercase tracking-wide text-slate-400">Indicatif</p>
                    <p class="text-sm font-semibold text-slate-900"><?= htmlspecialchars((string) (($missionDuty['duty_callsign'] ?? '') ?: '—'), ENT_QUOTES, 'UTF-8') ?></p>
                </div>
                <div>
                    <p class="text-[10px] uppercase tracking-wide text-slate-400">Mission</p>
                    <p class="text-sm font-semibold text-slate-900"><?= htmlspecialchars((string) (($missionDuty['mission_label'] ?? '') ?: '—'), ENT_QUOTES, 'UTF-8') ?></p>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </section>

    <section class="mb-10 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm" aria-labelledby="mon-service-tasks">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h2 id="mon-service-tasks" class="text-sm font-bold text-slate-900">Tâches de mon poste</h2>
                <p class="mt-1 text-xs text-slate-500"><?= count($tasks) ?> tâche<?= count($tasks) > 1 ? 's' : '' ?> ouverte<?= count($tasks) > 1 ? 's' : '' ?></p>
            </div>
            <div class="flex flex-wrap gap-2 text-[11px] font-semibold uppercase tracking-wide text-slate-500">
                <span class="rounded-full bg-slate-100 px-2.5 py-1"><?= (int) ($waiting['validations'] ?? 0) ?> validation<?= (int) ($waiting['validations'] ?? 0) > 1 ? 's' : '' ?></span>
                <span class="rounded-full bg-slate-100 px-2.5 py-1"><?= (int) ($waiting['reports'] ?? 0) ?> rapport<?= (int) ($waiting['reports'] ?? 0) > 1 ? 's' : '' ?></span>
            </div>
        </div>

        <?php if ($tasks === []): ?>
            <p class="mt-5 text-sm text-slate-500">Aucune tâche ouverte pour votre poste. Les demandes adressées à votre permanence apparaîtront ici.</p>
        <?php else: ?>
            <ul class="mt-5 space-y-3">
                <?php foreach ($tasks as $task): ?>
                    <?php
                    if (!is_array($task)) {
                        continue;
                    }
                    $taskId = (int) ($task['id'] ?? 0);
                    $title = trim((string) ($task['title'] ?? ''));
                    if ($taskId < 1 || $title === '') {
                        continue;
                    }
                    $prio = (string) ($task['priority'] ?? 'normal');
                    $status = (string) ($task['status'] ?? 'open');
                    $due = trim((string) ($task['due_at'] ?? ''));
                    $desc = trim((string) ($task['description'] ?? ''));
                    ?>
                    <li class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-semibold text-slate-950"><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></p>
                                <?php if ($desc !== ''): ?>
                                    <p class="mt-1 text-xs leading-relaxed text-slate-600"><?= htmlspecialchars($desc, ENT_QUOTES, 'UTF-8') ?></p>
                                <?php endif; ?>
                                <p class="mt-2 text-[11px] uppercase tracking-wide text-slate-400">
                                    <?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?>
                                    · <?= htmlspecialchars($prio, ENT_QUOTES, 'UTF-8') ?>
                                    <?php if ($due !== ''): ?> · échéance <?= htmlspecialchars($due, ENT_QUOTES, 'UTF-8') ?><?php endif; ?>
                                </p>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <?php if ($status === 'open'): ?>
                                <form method="post" action="<?= htmlspecialchars(url('mon-service/tache/' . $taskId . '/accuser'), ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                                    <button type="submit" class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-bold text-slate-800 hover:border-emerald-400">Accuser</button>
                                </form>
                                <?php endif; ?>
                                <?php if (in_array($status, ['open', 'acknowledged'], true)): ?>
                                <form method="post" action="<?= htmlspecialchars(url('mon-service/tache/' . $taskId . '/demarrer'), ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                                    <button type="submit" class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-bold text-slate-800 hover:border-emerald-400">Démarrer</button>
                                </form>
                                <?php endif; ?>
                                <?php if ($status !== 'completed'): ?>
                                <form method="post" action="<?= htmlspecialchars(url('mon-service/tache/' . $taskId . '/terminer'), ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                                    <button type="submit" class="rounded-lg bg-emerald-700 px-3 py-1.5 text-xs font-bold text-white hover:bg-emerald-800">Terminer</button>
                                </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

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
