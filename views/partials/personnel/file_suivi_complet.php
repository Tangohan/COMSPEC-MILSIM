<?php
/**
 * Suivi complet d’un membre : étape d’immersion, échéances, parcours.
 *
 * @var array $memberFollowup
 * @var string $suiviCompletMode compact|full
 * @var array|null $phaseChecklist
 * @var list<array<string,mixed>> $phaseTransitions
 * @var array $roleplayEligibility
 * @var list<array<string,mixed>> $roleplayTimelineEvents
 * @var array $targetUser
 * @var bool $canStaffEdit
 * @var bool $canEditProfile
 * @var callable $rpTimelineStatusFr
 */
$fu = is_array($memberFollowup ?? null) ? $memberFollowup : [];
if (empty($fu['visible'])) {
    return;
}
$mode = (($suiviCompletMode ?? 'full') === 'compact') ? 'compact' : 'full';
$isFull = $mode === 'full';
$phase = is_array($fu['phase'] ?? null) ? $fu['phase'] : null;
$deadlines = is_array($fu['deadlines'] ?? null) ? $fu['deadlines'] : [];
$probation = is_array($fu['probation'] ?? null) ? $fu['probation'] : null;
$phaseCheck = is_array($phaseChecklist ?? null) ? $phaseChecklist : null;
$phaseJournal = is_array($phaseTransitions ?? null) ? $phaseTransitions : [];
$elig = is_array($roleplayEligibility ?? null) ? $roleplayEligibility : ['eligible' => false, 'checks' => []];
$timelineEvents = is_array($roleplayTimelineEvents ?? null) ? $roleplayTimelineEvents : [];
$uid = (int) ($targetUser['id'] ?? 0);
$ficheSuiviUrl = $uid > 0 ? url('personnel/' . $uid) . '?onglet=suivi' : url('personnel/me') . '?onglet=suivi';
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$statusFr = $rpTimelineStatusFr ?? static function (?string $s): string {
    return match (trim((string) $s)) {
        'planned' => 'Prévu',
        'completed' => 'Terminé',
        'blocked' => 'Bloqué',
        'cancelled' => 'Annulé',
        default => '—',
    };
};
$sectionId = $isFull ? 'suivi-complet' : 'suivi-complet-apercu';
$part = (string) ($suiviCompletPart ?? '');
if ($isFull && $part !== '') {
    $progress = $fu['progress'] !== null ? max(0, min(100, (int) $fu['progress'])) : null;
    if ($part === 'top'):
?>
<section id="<?= $h($sectionId) ?>" class="pf-card pf-parcours<?= !empty($fu['attention']) ? ' is-attention' : '' ?>"<?php if (!empty($fu['show_parcours'])): ?> data-parcours-anchor="1"<?php endif; ?> aria-labelledby="pf-parcours-title">
    <div class="pf-parcours__head">
        <div class="pf-parcours__intro">
            <p class="pf-eyebrow">Suivi du dossier</p>
            <h2 id="pf-parcours-title" class="pf-title">
                <?php if ($phase !== null): ?>
                    <?= $h((string) $phase['label']) ?>
                <?php else: ?>
                    Arrivée dans l’unité
                <?php endif; ?>
            </h2>
            <p class="pf-lead">
                <?php if ($phase === null): ?>
                    Étape d’arrivée, tuteur et dates importantes du dossier.
                <?php elseif (!empty($phase['is_last'])): ?>
                    Dernière étape du parcours atteinte.
                <?php elseif (!empty($phase['next_label'])): ?>
                    Prochaine étape : <strong><?= $h((string) $phase['next_label']) ?></strong>
                    <?= ($phase['effect'] ?? '') === 'automatic' ? '— le passage se fera tout seul une fois les conditions remplies.' : '— un responsable validera le passage une fois les conditions remplies.' ?>
                <?php endif; ?>
            </p>
        </div>
        <?php if ($progress !== null): ?>
        <div class="pf-ring" style="--pf-progress: <?= $progress ?>" role="img" aria-label="Progression du parcours : <?= $progress ?> %">
            <svg viewBox="0 0 120 120" aria-hidden="true">
                <circle class="pf-ring__track" cx="60" cy="60" r="52"></circle>
                <circle class="pf-ring__value" cx="60" cy="60" r="52" pathLength="100"></circle>
            </svg>
            <div class="pf-ring__label"><b><?= $progress ?><small>%</small></b><span>progression</span></div>
        </div>
        <?php endif; ?>
    </div>

    <?php if ($probation !== null): ?>
    <p class="pf-note"><strong><?= $h((string) $probation['label']) ?></strong> — jusqu’au <?= $h((string) $probation['ends_label']) ?>.</p>
    <?php endif; ?>

    <?php if ($phase !== null): ?>
    <div id="parcours-rh" class="pf-conditions">
        <?php $items = is_array($phase['items'] ?? null) ? $phase['items'] : []; ?>
        <?php
        $passedCount = 0;
        foreach ($items as $it) {
            if (!empty($it['passed'])) {
                $passedCount++;
            }
        }
        ?>
        <div class="pf-conditions__head">
            <h3 class="pf-subtitle">Conditions pour passer à l’étape suivante</h3>
            <?php if ($items !== []): ?>
            <span class="pf-count"><?= $passedCount ?> / <?= count($items) ?> remplies</span>
            <?php endif; ?>
        </div>
        <?php if (!empty($phase['is_last'])): ?>
        <p class="pf-muted">Vous êtes à la dernière étape prévue pour ce parcours.</p>
        <?php elseif ($items === []): ?>
        <p class="pf-muted">Aucune condition n’est encore définie pour cette étape. Le passage reste bloqué tant que le parcours n’est pas configuré.</p>
        <?php else: ?>
        <ul class="pf-checklist">
            <?php foreach ($items as $it): ?>
            <li class="<?= !empty($it['passed']) ? 'is-ok' : 'is-todo' ?>">
                <span class="pf-checklist__mark" aria-hidden="true"><?= !empty($it['passed']) ? '✓' : '' ?></span>
                <span><?= $h((string) ($it['reason'] ?? $it['label'] ?? '')) ?></span>
                <span class="pf-sr"><?= !empty($it['passed']) ? '(remplie)' : '(à remplir)' ?></span>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
        <?php if ($phaseCheck && !empty($phaseCheck['next']) && (!empty($canStaffEdit) || !empty($canEditProfile))): ?>
        <form method="post" action="<?= $h(url('personnel/' . $uid . '/phase')) ?>" class="pf-actions">
            <?= \App\Core\Csrf::field() ?>
            <button type="submit" name="phase_mode" value="manual" class="pf-btn pf-btn--primary" <?= empty($phaseCheck['evaluation']['eligible']) ? 'disabled title="Toutes les conditions ne sont pas encore remplies"' : '' ?>>
                Passer à <?= $h((string) ($phaseCheck['next']['label'] ?? 'l’étape suivante')) ?>
            </button>
            <?php if (function_exists('can') && (can('personnel.progression.override') || can('admin.organization') || can('admin.access'))): ?>
            <input type="text" name="override_reason" maxlength="500" placeholder="Motif du passage forcé" aria-label="Motif du passage forcé" class="pf-input">
            <button type="submit" name="phase_mode" value="override" class="pf-btn pf-btn--warn">Forcer le passage</button>
            <?php endif; ?>
        </form>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if ($deadlines !== []): ?>
    <div class="pf-deadlines">
        <h3 class="pf-subtitle">Prochaines échéances</h3>
        <div class="pf-deadlines__grid">
            <?php foreach ($deadlines as $card):
                $dateLabel = (string) (($card['date_label'] ?? null) ?: ($card['fallback'] ?? '—'));
                $overdue = !empty($card['overdue']);
                $done = empty($card['date_label']);
                ?>
            <article class="pf-deadline<?= $overdue ? ' is-overdue' : ($done ? ' is-done' : '') ?>">
                <p class="pf-deadline__title"><?= $h((string) ($card['title'] ?? '')) ?></p>
                <p class="pf-deadline__date"><?= $h($dateLabel) ?></p>
                <?php if ($overdue): ?>
                <p class="pf-deadline__state">Échéance dépassée</p>
                <?php elseif (!empty($card['note'])): ?>
                <p class="pf-deadline__note"><?= $h((string) $card['note']) ?></p>
                <?php endif; ?>
            </article>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</section>
<?php
    elseif ($part === 'side'):
        $facts = [
            'Étape' => (string) ($fu['stage'] ?? ''),
            'Statut' => (string) ($fu['status'] ?? ''),
            'Filière' => (string) ($fu['track'] ?? ''),
            'Fonction' => (string) ($fu['function'] ?? ''),
            'Profil de recrutement' => (string) ($fu['origin_label'] ?? ''),
            'Tuteur' => (string) ($fu['tutor_label'] ?? ''),
        ];
?>
<?php if (!empty($fu['show_immersion'])): ?>
<section class="pf-card" aria-labelledby="pf-facts-title">
    <h2 id="pf-facts-title" class="pf-card__title">Fiche de suivi</h2>
    <dl class="pf-facts">
        <?php foreach ($facts as $label => $value): ?>
        <div><dt><?= $h($label) ?></dt><dd><?= trim($value) !== '' ? $h($value) : '<span class="pf-muted">Non renseigné</span>' ?></dd></div>
        <?php endforeach; ?>
    </dl>
</section>
<?php endif; ?>
<?php if ($elig['checks'] !== []): ?>
<section class="pf-card pf-ready<?= !empty($elig['eligible']) ? ' is-ok' : ' is-todo' ?>" aria-labelledby="pf-ready-title">
    <h2 id="pf-ready-title" class="pf-card__title"><?= !empty($elig['eligible']) ? 'Dossier prêt pour le suivi' : 'Dossier à compléter' ?></h2>
    <ul class="pf-checklist pf-checklist--compact">
        <?php foreach ($elig['checks'] as $check): ?>
        <li class="<?= !empty($check['ok']) ? 'is-ok' : 'is-warn' ?>">
            <span class="pf-checklist__mark" aria-hidden="true"><?= !empty($check['ok']) ? '✓' : '!' ?></span>
            <span><?= $h((string) ($check['label'] ?? 'Critère')) ?></span>
        </li>
        <?php endforeach; ?>
    </ul>
</section>
<?php endif; ?>
<?php if (($fu['notes'] ?? '') !== ''): ?>
<section class="pf-card" aria-labelledby="pf-followup-notes-title">
    <h2 id="pf-followup-notes-title" class="pf-card__title">Notes de suivi</h2>
    <p class="pf-quote"><?= nl2br($h((string) $fu['notes'])) ?></p>
</section>
<?php endif; ?>
<?php
    elseif ($part === 'timeline'):
        if ($timelineEvents === [] && $phaseJournal === []) {
            return;
        }
?>
<section class="pf-card" aria-labelledby="pf-followup-log-title">
    <h2 id="pf-followup-log-title" class="pf-card__title">Journal du suivi</h2>
    <p class="pf-card__sub">Entretiens, objectifs et changements d’étape.</p>
    <ol class="pf-timeline">
        <?php foreach ($timelineEvents as $ev):
            $evDate = !empty($ev['event_date']) ? date('d/m/Y', strtotime((string) $ev['event_date'])) : (!empty($ev['created_at']) ? date('d/m/Y', strtotime((string) $ev['created_at'])) : '—');
            $dueDate = !empty($ev['due_date']) ? date('d/m/Y', strtotime((string) $ev['due_date'])) : null;
            $statusRaw = (string) ($ev['status'] ?? 'planned');
            $isOverdue = $dueDate !== null && !in_array($statusRaw, ['completed', 'cancelled'], true) && strtotime((string) $ev['due_date']) < strtotime(date('Y-m-d'));
            $tone = $isOverdue ? 'danger' : match ($statusRaw) {
                'completed' => 'ok',
                'blocked' => 'danger',
                'cancelled' => 'muted',
                default => 'warn',
            };
            $actor = trim((string) ($ev['actor_display_name'] ?? '')) ?: trim((string) ($ev['actor_callsign'] ?? ''));
            $delta = isset($ev['progress_delta']) && $ev['progress_delta'] !== null && $ev['progress_delta'] !== '' ? (int) $ev['progress_delta'] : null;
            ?>
        <li class="pf-timeline__item is-<?= $h($tone) ?>">
            <span class="pf-timeline__dot" aria-hidden="true"></span>
            <div class="pf-timeline__body">
                <div class="pf-timeline__meta">
                    <time><?= $h($evDate) ?></time>
                    <span class="pf-tag"><?= $h(ucfirst((string) ($ev['event_type'] ?? 'événement'))) ?></span>
                    <span class="pf-pill is-<?= $h($tone) ?>"><?= $isOverdue ? 'En retard' : $h($statusFr($statusRaw)) ?></span>
                </div>
                <p class="pf-timeline__title"><?= $h((string) ($ev['title'] ?? 'Événement')) ?></p>
                <?php if (!empty($ev['detail'])): ?><p class="pf-timeline__text"><?= nl2br($h((string) $ev['detail'])) ?></p><?php endif; ?>
                <p class="pf-timeline__foot">
                    <?php if ($dueDate !== null): ?><span>Échéance <b class="<?= $isOverdue ? 'is-danger' : '' ?>"><?= $h($dueDate) ?></b></span><?php endif; ?>
                    <?php if ($delta !== null): ?><span>Progression <b><?= $delta >= 0 ? '+' : '' ?><?= $delta ?></b></span><?php endif; ?>
                    <?php if ($actor !== ''): ?><span>Par <b><?= $h($actor) ?></b></span><?php endif; ?>
                </p>
            </div>
        </li>
        <?php endforeach; ?>
        <?php foreach ($phaseJournal as $tr): ?>
        <li class="pf-timeline__item is-ok">
            <span class="pf-timeline__dot" aria-hidden="true"></span>
            <div class="pf-timeline__body">
                <div class="pf-timeline__meta">
                    <time><?= $h(date('d/m/Y', strtotime((string) ($tr['created_at'] ?? 'now')))) ?></time>
                    <span class="pf-tag">Changement d’étape</span>
                </div>
                <p class="pf-timeline__title"><?= $h((string) ($tr['from_label'] ?? '—')) ?> → <?= $h((string) ($tr['to_label'] ?? '—')) ?></p>
                <?php if (!empty($tr['override_reason'])): ?><p class="pf-timeline__text">Passage forcé — motif : <?= $h((string) $tr['override_reason']) ?></p><?php endif; ?>
            </div>
        </li>
        <?php endforeach; ?>
    </ol>
</section>
<?php
    endif;
    return;
}
?>
<section id="<?= $h($sectionId) ?>" class="rounded-3xl border <?= !empty($fu['attention']) ? 'border-amber-200' : 'border-emerald-200' ?> bg-white p-6 shadow-sm md:p-8"<?php if ($isFull && !empty($fu['show_parcours'])): ?> data-parcours-anchor="1"<?php endif; ?>>
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h2 class="text-xs font-black uppercase tracking-[0.35em] <?= !empty($fu['attention']) ? 'text-amber-900' : 'text-emerald-900' ?>">
                <?= $isFull ? 'Suivi du dossier' : 'Votre parcours' ?>
            </h2>
            <p class="mt-2 max-w-2xl text-sm text-slate-600">
                <?php if ($phase !== null): ?>
                    Étape actuelle : <strong><?= $h((string) $phase['label']) ?></strong>
                    <?php if (!empty($phase['is_last'])): ?>
                        · dernière étape du parcours
                    <?php elseif (!empty($phase['next_label'])): ?>
                        · suivante : <strong><?= $h((string) $phase['next_label']) ?></strong>
                    <?php endif; ?>
                <?php else: ?>
                    Étape d’arrivée, tuteur et dates importantes du dossier.
                <?php endif; ?>
            </p>
        </div>
        <?php if ($fu['progress'] !== null): ?>
        <div class="min-w-[10rem] rounded-2xl border border-emerald-100 bg-emerald-50/60 px-4 py-3">
            <p class="text-[10px] font-black uppercase tracking-widest text-emerald-900">Progression</p>
            <p class="mt-1 text-xl font-black text-slate-900"><?= (int) $fu['progress'] ?>%</p>
        </div>
        <?php endif; ?>
    </div>

    <?php if ($probation !== null): ?>
    <p class="mt-4 rounded-xl border border-slate-200 bg-slate-50/80 px-4 py-3 text-sm text-slate-700">
        <span class="font-semibold text-slate-900"><?= $h((string) $probation['label']) ?></span>
        — jusqu’au <?= $h((string) $probation['ends_label']) ?>.
    </p>
    <?php endif; ?>

    <?php if ($deadlines !== []): ?>
    <div class="mt-5 grid gap-3 md:grid-cols-2 xl:grid-cols-4">
        <?php foreach ($deadlines as $card): ?>
        <article class="rounded-2xl border p-4 <?= $h((string) ($card['accent'] ?? 'border-slate-200 bg-slate-50/70')) ?>">
            <p class="text-[10px] font-black uppercase tracking-[0.22em] text-slate-600"><?= $h((string) ($card['title'] ?? '')) ?></p>
            <p class="mt-2 text-lg font-black text-slate-900"><?= $h((string) (($card['date_label'] ?? null) ?: ($card['fallback'] ?? '—'))) ?></p>
            <?php if (!empty($card['overdue'])): ?>
            <p class="mt-1 text-xs font-semibold text-rose-800">Échéance dépassée</p>
            <?php endif; ?>
            <?php if (!empty($card['note'])): ?>
            <p class="mt-1 text-xs text-slate-600"><?= $h((string) $card['note']) ?></p>
            <?php endif; ?>
        </article>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($fu['show_immersion'])): ?>
    <div class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
        <div class="rounded-xl border border-slate-100 bg-slate-50/70 px-4 py-3"><p class="text-[9px] font-black uppercase tracking-wider text-slate-500">Étape</p><p class="mt-1 text-sm font-semibold text-slate-900"><?= ($fu['stage'] ?? '') !== '' ? $h((string) $fu['stage']) : '—' ?></p></div>
        <div class="rounded-xl border border-slate-100 bg-slate-50/70 px-4 py-3"><p class="text-[9px] font-black uppercase tracking-wider text-slate-500">Statut</p><p class="mt-1 text-sm font-semibold text-slate-900"><?= ($fu['status'] ?? '') !== '' ? $h((string) $fu['status']) : '—' ?></p></div>
        <div class="rounded-xl border border-slate-100 bg-slate-50/70 px-4 py-3"><p class="text-[9px] font-black uppercase tracking-wider text-slate-500">Filière</p><p class="mt-1 text-sm font-semibold text-slate-900"><?= ($fu['track'] ?? '') !== '' ? $h((string) $fu['track']) : '—' ?></p></div>
        <div class="rounded-xl border border-slate-100 bg-slate-50/70 px-4 py-3"><p class="text-[9px] font-black uppercase tracking-wider text-slate-500">Fonction (dossier)</p><p class="mt-1 text-sm font-semibold text-slate-900"><?= ($fu['function'] ?? '') !== '' ? $h((string) $fu['function']) : '—' ?></p></div>
        <div class="rounded-xl border border-slate-100 bg-slate-50/70 px-4 py-3"><p class="text-[9px] font-black uppercase tracking-wider text-slate-500">Profil recrutement</p><p class="mt-1 text-sm font-semibold text-slate-900"><?= ($fu['origin_label'] ?? '') !== '' ? $h((string) $fu['origin_label']) : '—' ?></p></div>
        <div class="rounded-xl border border-slate-100 bg-slate-50/70 px-4 py-3"><p class="text-[9px] font-black uppercase tracking-wider text-slate-500">Tuteur</p><p class="mt-1 text-sm font-semibold text-slate-900"><?= ($fu['tutor_label'] ?? null) !== null && $fu['tutor_label'] !== '' ? $h((string) $fu['tutor_label']) : '—' ?></p></div>
    </div>
    <?php endif; ?>

    <?php if ($phase !== null): ?>
    <div id="<?= $isFull ? 'parcours-rh' : 'parcours-rh-apercu' ?>" class="mt-6 rounded-2xl border border-slate-200 bg-slate-50/50 p-4">
        <p class="text-[10px] font-black uppercase tracking-wider text-slate-500">Parcours dans l’unité</p>
        <?php if (!empty($phase['is_last'])): ?>
        <p class="mt-2 text-sm text-slate-700">Vous êtes à la dernière étape prévue pour ce parcours.</p>
        <?php elseif (($phase['effect'] ?? '') === 'automatic'): ?>
        <p class="mt-2 text-sm text-slate-700">Le passage à l’étape suivante se fera tout seul lorsque tout est rempli.</p>
        <?php else: ?>
        <p class="mt-2 text-sm text-slate-700">Un responsable validera le passage lorsque les conditions sont remplies.</p>
        <?php endif; ?>
        <?php $items = is_array($phase['items'] ?? null) ? $phase['items'] : []; ?>
        <?php if ($items === [] && empty($phase['is_last'])): ?>
        <p class="mt-3 text-sm text-slate-600">Aucune condition n’est encore définie pour cette étape. Le membre n’est pas éligible tant que le parcours n’est pas configuré.</p>
        <?php elseif ($items !== []): ?>
        <ul class="mt-3 space-y-2 text-sm">
            <?php foreach ($items as $it): ?>
            <li class="flex items-start gap-2">
                <span class="<?= !empty($it['passed']) ? 'text-emerald-700' : 'text-slate-600' ?>"><?= !empty($it['passed']) ? '✓' : '○' ?></span>
                <span><?= $h((string) ($it['reason'] ?? $it['label'] ?? '')) ?></span>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
        <?php if ($isFull && $phaseCheck && !empty($phaseCheck['next']) && (!empty($canStaffEdit) || !empty($canEditProfile))): ?>
        <form method="post" action="<?= $h(url('personnel/' . $uid . '/phase')) ?>" class="mt-4 flex flex-wrap gap-2">
            <?= \App\Core\Csrf::field() ?>
            <button type="submit" name="phase_mode" value="manual" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-semibold <?= !empty($phaseCheck['evaluation']['eligible']) ? 'text-slate-900' : 'text-slate-400' ?>" <?= empty($phaseCheck['evaluation']['eligible']) ? 'disabled' : '' ?>>
                Passer à <?= $h((string) ($phaseCheck['next']['label'] ?? 'l’étape suivante')) ?>
            </button>
            <?php if (function_exists('can') && (can('personnel.progression.override') || can('admin.organization') || can('admin.access'))): ?>
            <input type="text" name="override_reason" maxlength="500" placeholder="Motif du passage forcé" class="rounded-lg border border-slate-200 px-3 py-2 text-xs">
            <button type="submit" name="phase_mode" value="override" class="rounded-lg border border-amber-300 bg-amber-50 px-3 py-2 text-xs font-semibold text-amber-900">Forcer le passage</button>
            <?php endif; ?>
        </form>
        <?php endif; ?>
        <?php if ($isFull && $phaseJournal !== []): ?>
        <ol class="mt-4 space-y-2 text-xs text-slate-600">
            <?php foreach ($phaseJournal as $tr): ?>
            <li><?= $h(date('d/m/Y', strtotime((string) ($tr['created_at'] ?? 'now')))) ?>
                — <?= $h((string) ($tr['from_label'] ?? '—')) ?> → <?= $h((string) ($tr['to_label'] ?? '—')) ?>
                <?php if (!empty($tr['override_reason'])): ?> · Motif : <?= $h((string) $tr['override_reason']) ?><?php endif; ?>
            </li>
            <?php endforeach; ?>
        </ol>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if ($isFull && $elig['checks'] !== []): ?>
    <div class="mt-5 rounded-2xl border <?= !empty($elig['eligible']) ? 'border-emerald-200 bg-emerald-50/50' : 'border-amber-200 bg-amber-50/60' ?> p-4">
        <p class="text-[10px] font-black uppercase tracking-wider <?= !empty($elig['eligible']) ? 'text-emerald-900' : 'text-amber-900' ?>">Dossier prêt pour le suivi</p>
        <ul class="mt-2 space-y-1.5 text-xs text-slate-700">
            <?php foreach ($elig['checks'] as $check): ?>
            <li class="flex items-start gap-2"><span class="font-black <?= !empty($check['ok']) ? 'text-emerald-700' : 'text-amber-700' ?>"><?= !empty($check['ok']) ? '✓' : '!' ?></span><span><?= $h((string) ($check['label'] ?? 'Critère')) ?></span></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <?php if ($isFull && ($fu['notes'] ?? '') !== ''): ?>
    <div class="mt-5 rounded-2xl border border-slate-100 bg-slate-50/70 p-4">
        <p class="text-[10px] font-black uppercase tracking-wider text-slate-500">Notes de suivi</p>
        <p class="mt-2 text-sm leading-relaxed text-slate-800"><?= nl2br($h((string) $fu['notes'])) ?></p>
    </div>
    <?php endif; ?>

    <?php if ($isFull && $timelineEvents !== []): ?>
    <div class="mt-5 rounded-2xl border border-slate-200 bg-white p-4">
        <p class="text-[10px] font-black uppercase tracking-wider text-slate-500">Journal du suivi</p>
        <ol class="mt-3 space-y-3">
            <?php foreach ($timelineEvents as $ev):
                $evDate = !empty($ev['event_date']) ? date('d/m/Y', strtotime((string) $ev['event_date'])) : (!empty($ev['created_at']) ? date('d/m/Y', strtotime((string) $ev['created_at'])) : '—');
                $dueDate = !empty($ev['due_date']) ? date('d/m/Y', strtotime((string) $ev['due_date'])) : null;
                $statusRaw = (string) ($ev['status'] ?? 'planned');
                $isOverdue = $dueDate !== null && !in_array($statusRaw, ['completed', 'cancelled'], true) && strtotime((string) $ev['due_date']) < strtotime(date('Y-m-d'));
                $statusClass = match ($statusRaw) {
                    'completed' => 'bg-emerald-100 text-emerald-800',
                    'blocked' => 'bg-rose-100 text-rose-800',
                    'cancelled' => 'bg-slate-200 text-slate-700',
                    default => 'bg-amber-100 text-amber-800',
                };
                $actor = trim((string) ($ev['actor_display_name'] ?? '')) ?: trim((string) ($ev['actor_callsign'] ?? ''));
            ?>
            <li class="rounded-xl border border-slate-100 bg-slate-50/60 p-3">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="text-[10px] font-black uppercase tracking-wider text-slate-500"><?= $h((string) ($ev['event_type'] ?? 'événement')) ?></span>
                    <span class="rounded-full px-2 py-0.5 text-[10px] font-bold <?= $statusClass ?>"><?= $h($statusFr($statusRaw)) ?></span>
                    <?php if ($isOverdue): ?><span class="rounded-full bg-rose-100 px-2 py-0.5 text-[10px] font-bold text-rose-800">En retard</span><?php endif; ?>
                </div>
                <p class="mt-1 text-sm font-semibold text-slate-900"><?= $h((string) ($ev['title'] ?? 'Événement')) ?></p>
                <?php if (!empty($ev['detail'])): ?><p class="mt-1 text-sm text-slate-700 leading-relaxed"><?= nl2br($h((string) $ev['detail'])) ?></p><?php endif; ?>
                <p class="mt-2 text-[11px] text-slate-500">Date: <span class="font-semibold text-slate-700"><?= $h($evDate) ?></span><?php if ($dueDate !== null): ?> · Échéance: <span class="font-semibold <?= $isOverdue ? 'text-rose-700' : 'text-slate-700' ?>"><?= $h($dueDate) ?></span><?php endif; ?><?php if (!empty($ev['progress_delta']) || (string) ($ev['progress_delta'] ?? '') === '0'): ?> · Impact progression: <span class="font-semibold text-slate-700"><?= (int) $ev['progress_delta'] >= 0 ? '+' : '' ?><?= (int) $ev['progress_delta'] ?></span><?php endif; ?><?php if ($actor !== ''): ?> · Par: <span class="font-semibold text-slate-700"><?= $h($actor) ?></span><?php endif; ?></p>
            </li>
            <?php endforeach; ?>
        </ol>
    </div>
    <?php endif; ?>

    <?php if (!$isFull): ?>
    <a href="<?= $h($ficheSuiviUrl) ?>" class="mt-5 inline-flex text-sm font-bold text-emerald-700 hover:underline"<?php if (!empty($suiviCompletUseAlpineTab)): ?> @click.prevent="setTab('historique')"<?php endif; ?>>
        Ouvrir le suivi complet
    </a>
    <?php endif; ?>
</section>
