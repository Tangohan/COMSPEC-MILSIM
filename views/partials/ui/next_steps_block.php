<?php
declare(strict_types=1);
/** @var list<array{label: string, description: string, href: string, accent?: string}> $next_steps */
$next_steps = $next_steps ?? [];
$next_steps_title = isset($next_steps_title) ? trim((string) $next_steps_title) : 'Prochaine étape suggérée';
$next_steps_intro = isset($next_steps_intro) ? trim((string) $next_steps_intro) : '';
if ($next_steps === []) {
    return;
}
$accents = [
    'emerald' => 'border-emerald-200/80 bg-emerald-50/60',
    'sky' => 'border-sky-200/80 bg-sky-50/60',
    'teal' => 'border-teal-200/80 bg-teal-50/50',
    'amber' => 'border-amber-200/80 bg-amber-50/50',
    'rose' => 'border-rose-200/80 bg-rose-50/50',
    'violet' => 'border-violet-200/80 bg-violet-50/50',
    'slate' => 'border-slate-200/80 bg-slate-50/80',
];
?>
<section class="mt-10 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8" aria-labelledby="next-steps-heading">
    <h2 id="next-steps-heading" class="text-sm font-black uppercase tracking-[0.18em] text-slate-500"><?= htmlspecialchars($next_steps_title, ENT_QUOTES, 'UTF-8') ?></h2>
    <?php if ($next_steps_intro !== ''): ?>
    <p class="mt-2 max-w-2xl text-sm text-slate-600"><?= htmlspecialchars($next_steps_intro, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>
    <ul class="mt-6 space-y-3">
        <?php foreach ($next_steps as $step): ?>
        <?php
        $ac = (string) ($step['accent'] ?? 'slate');
        $wrap = $accents[$ac] ?? $accents['slate'];
        ?>
        <li>
            <a href="<?= htmlspecialchars((string) $step['href'], ENT_QUOTES, 'UTF-8') ?>" class="flex flex-col gap-1 rounded-xl border px-4 py-4 transition hover:shadow-md sm:flex-row sm:items-center sm:justify-between <?= htmlspecialchars($wrap, ENT_QUOTES, 'UTF-8') ?>">
                <div class="min-w-0">
                    <p class="text-sm font-bold text-slate-900"><?= htmlspecialchars((string) $step['label'], ENT_QUOTES, 'UTF-8') ?></p>
                    <p class="mt-1 text-xs leading-relaxed text-slate-600"><?= htmlspecialchars((string) $step['description'], ENT_QUOTES, 'UTF-8') ?></p>
                </div>
                <span class="shrink-0 text-xs font-semibold text-emerald-800">Ouvrir →</span>
            </a>
        </li>
        <?php endforeach; ?>
    </ul>
</section>
