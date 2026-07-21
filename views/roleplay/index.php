<?php
declare(strict_types=1);

/** @var array<string,mixed> $rpUser */
/** @var array<string,mixed> $rpProfile */
/** @var string|null $rpTutorLabel */
/** @var string|null $rpNextDueAt */
/** @var string|null $rpLastReviewAt */
/** @var bool $rpOverdue */
/** @var list<array<string,mixed>> $rpTimeline */

$rpUser = is_array($rpUser ?? null) ? $rpUser : [];
$rpProfile = is_array($rpProfile ?? null) ? $rpProfile : [];
$rpTimeline = is_array($rpTimeline ?? null) ? $rpTimeline : [];
$rpOverdue = !empty($rpOverdue);

$baseUrl = url('');
$displayName = trim((string) ($rpUser['display_name'] ?? ''));
$callsign = trim((string) ($rpUser['callsign'] ?? ''));
$characterName = trim((string) ($rpProfile['character_name'] ?? ''));
$motto = trim((string) ($rpProfile['motto'] ?? ''));
$portraitUrl = null;
if (!empty($rpProfile['character_portrait_path'])) {
    $portraitUrl = $baseUrl . '/' . ltrim((string) $rpProfile['character_portrait_path'], '/');
}
$initialsSource = $characterName !== '' ? $characterName : ($displayName !== '' ? $displayName : $callsign);
$initials = function_exists('user_display_initials')
    ? user_display_initials($initialsSource, 2)
    : mb_strtoupper(mb_substr(preg_replace('/\s+/u', '', $initialsSource) ?: '?', 0, 2));

$identityFields = [
    'Sexe' => trim((string) ($rpProfile['sex'] ?? '')),
    'Groupe sanguin' => trim((string) ($rpProfile['blood_type'] ?? '')),
    'Nationalité (RP)' => trim((string) ($rpProfile['nationality'] ?? '')),
    'Langues (RP)' => trim((string) ($rpProfile['languages'] ?? '')),
    'Lieu de naissance' => trim((string) ($rpProfile['birth_place'] ?? '')),
    'Situation familiale' => trim((string) ($rpProfile['family_situation'] ?? '')),
    'Statut opérateur' => trim((string) ($rpProfile['operator_status'] ?? '')),
    'Spécialités / tags' => trim((string) ($rpProfile['operator_tags'] ?? '')),
];
$weight = (int) ($rpProfile['weight_kg'] ?? 0);
if ($weight > 0) {
    $identityFields['Poids'] = $weight . ' kg';
}

$stage = trim((string) ($rpProfile['rp_followup_stage'] ?? ''));
$progress = $rpProfile['rp_followup_progress'] ?? null;
$rpTutorLabel = trim((string) ($rpTutorLabel ?? ''));

$nextDueFmt = $rpNextDueAt ? date('d/m/Y', strtotime((string) $rpNextDueAt)) : null;
$lastReviewFmt = $rpLastReviewAt ? date('d/m/Y', strtotime((string) $rpLastReviewAt)) : null;

$timelineStatusFr = static function (?string $raw): string {
    return match (trim((string) $raw)) {
        'planned' => 'Prévu',
        'completed' => 'Terminé',
        'blocked' => 'Bloqué',
        'cancelled' => 'Annulé',
        default => '—',
    };
};
?>
<div class="min-h-screen bg-slate-100">
    <div class="mx-auto max-w-4xl px-6 py-10 md:py-14">
        <p class="mb-3 text-[11px] font-black uppercase tracking-[0.35em] text-slate-400">Roleplay</p>

        <section class="mb-8 overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
            <div class="flex flex-col gap-5 border-b border-slate-100 bg-gradient-to-br from-slate-950 via-slate-900 to-emerald-950 px-6 py-8 sm:flex-row sm:items-center sm:px-8">
                <div class="flex h-20 w-20 shrink-0 items-center justify-center overflow-hidden rounded-2xl bg-emerald-500/20 text-xl font-black text-white ring-2 ring-white/15">
                    <?php if ($portraitUrl): ?>
                    <img src="<?= htmlspecialchars($portraitUrl, ENT_QUOTES, 'UTF-8') ?>" alt="Portrait du personnage" class="h-full w-full object-cover">
                    <?php else: ?>
                    <?= htmlspecialchars($initials, ENT_QUOTES, 'UTF-8') ?>
                    <?php endif; ?>
                </div>
                <div class="min-w-0">
                    <h1 class="text-2xl font-black tracking-tight text-white sm:text-3xl"><?= htmlspecialchars($characterName !== '' ? $characterName : ($displayName !== '' ? $displayName : 'Mon personnage'), ENT_QUOTES, 'UTF-8') ?></h1>
                    <p class="mt-1 text-sm text-slate-300"><?= $callsign !== '' ? htmlspecialchars($callsign, ENT_QUOTES, 'UTF-8') : '—' ?></p>
                    <?php if ($motto !== ''): ?>
                    <p class="mt-2 max-w-xl text-sm italic leading-relaxed text-emerald-200/90">« <?= htmlspecialchars($motto, ENT_QUOTES, 'UTF-8') ?> »</p>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <div class="grid gap-6 md:grid-cols-2">
            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="mb-4 text-xs font-black uppercase tracking-[0.2em] text-slate-500">Identité RP</h2>
                <?php
                $anyIdentity = false;
                foreach ($identityFields as $v) {
                    if ($v !== '') {
                        $anyIdentity = true;
                        break;
                    }
                }
                ?>
                <?php if (!$anyIdentity): ?>
                <p class="text-sm text-slate-500">Aucun détail de personnage renseigné pour l’instant. Complétez votre dossier depuis la fiche personnel.</p>
                <?php else: ?>
                <dl class="grid grid-cols-2 gap-4 text-sm">
                    <?php foreach ($identityFields as $label => $value): if ($value === '') { continue; } ?>
                    <div>
                        <dt class="text-[10px] font-black uppercase tracking-widest text-slate-400"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></dt>
                        <dd class="mt-0.5 font-semibold text-slate-900"><?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?></dd>
                    </div>
                    <?php endforeach; ?>
                </dl>
                <?php endif; ?>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="mb-4 text-xs font-black uppercase tracking-[0.2em] text-slate-500">Suivi &amp; tutorat</h2>
                <dl class="space-y-3 text-sm">
                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-slate-500">Étape</dt>
                        <dd class="font-semibold text-slate-900"><?= $stage !== '' ? htmlspecialchars($stage, ENT_QUOTES, 'UTF-8') : '—' ?></dd>
                    </div>
                    <?php if ($progress !== null): ?>
                    <div>
                        <div class="mb-1 flex items-center justify-between text-xs text-slate-500"><span>Avancement</span><span><?= (int) $progress ?>&nbsp;%</span></div>
                        <div class="h-1.5 overflow-hidden rounded-full bg-slate-100"><div class="h-full bg-emerald-500" style="width: <?= max(0, min(100, (int) $progress)) ?>%"></div></div>
                    </div>
                    <?php endif; ?>
                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-slate-500">Tuteur</dt>
                        <dd class="font-semibold text-slate-900"><?= $rpTutorLabel !== '' ? htmlspecialchars($rpTutorLabel, ENT_QUOTES, 'UTF-8') : '—' ?></dd>
                    </div>
                </dl>
                <div class="mt-5 rounded-xl border <?= $rpOverdue ? 'border-rose-200 bg-rose-50' : 'border-slate-100 bg-slate-50' ?> px-4 py-3">
                    <p class="text-[10px] font-black uppercase tracking-widest <?= $rpOverdue ? 'text-rose-700' : 'text-slate-500' ?>">Prochain bilan roleplay</p>
                    <p class="mt-1 text-sm font-bold <?= $rpOverdue ? 'text-rose-900' : 'text-slate-900' ?>"><?= $nextDueFmt !== null ? htmlspecialchars($nextDueFmt, ENT_QUOTES, 'UTF-8') : 'À planifier' ?><?= $rpOverdue ? ' — en retard' : '' ?></p>
                    <?php if ($lastReviewFmt !== null): ?>
                    <p class="mt-1 text-xs text-slate-500">Dernier bilan : <?= htmlspecialchars($lastReviewFmt, ENT_QUOTES, 'UTF-8') ?></p>
                    <?php endif; ?>
                </div>
            </section>
        </div>

        <section class="mt-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="mb-4 text-xs font-black uppercase tracking-[0.2em] text-slate-500">Historique roleplay</h2>
            <?php if ($rpTimeline === []): ?>
            <p class="text-sm text-slate-500">Aucun événement enregistré pour l’instant.</p>
            <?php else: ?>
            <ol class="space-y-4">
                <?php foreach ($rpTimeline as $ev):
                    $evTitle = trim((string) ($ev['title'] ?? ''));
                    $evDetail = trim((string) ($ev['detail'] ?? ''));
                    $evDateRaw = trim((string) ($ev['event_date'] ?? '')) ?: trim((string) ($ev['created_at'] ?? ''));
                    $evDateFmt = $evDateRaw !== '' && strtotime($evDateRaw) ? date('d/m/Y', strtotime($evDateRaw)) : '—';
                    $evStatus = $timelineStatusFr((string) ($ev['status'] ?? ''));
                ?>
                <li class="border-l-2 border-slate-200 pl-4">
                    <p class="text-xs font-semibold text-slate-400"><?= htmlspecialchars($evDateFmt, ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars($evStatus, ENT_QUOTES, 'UTF-8') ?></p>
                    <p class="mt-0.5 text-sm font-bold text-slate-900"><?= htmlspecialchars($evTitle !== '' ? $evTitle : '—', ENT_QUOTES, 'UTF-8') ?></p>
                    <?php if ($evDetail !== ''): ?>
                    <p class="mt-0.5 text-sm text-slate-600"><?= htmlspecialchars($evDetail, ENT_QUOTES, 'UTF-8') ?></p>
                    <?php endif; ?>
                </li>
                <?php endforeach; ?>
            </ol>
            <?php endif; ?>
        </section>
    </div>
</div>
