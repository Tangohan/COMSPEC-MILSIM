<?php
$stats = $stats ?? ['courses' => 0, 'enrollments' => 0, 'completed' => 0, 'expiringCount' => 0];
$expiring = $expiring ?? [];
require base_path('views/admin/training/partials/command_shell_open.php');
?>
                <header class="tc-panel p-6 md:p-8">
                    <p class="tc-kicker">Training overview</p>
                    <h1 class="tc-hero-title mb-4">Instruction structurée &amp; continuité opérationnelle</h1>
                    <div class="h-px w-20 bg-slate-900/10 mb-5"></div>
                    <p class="text-slate-500 text-[11px] font-bold tracking-[0.2em] uppercase leading-relaxed max-w-2xl">
                        Pilotage du catalogue, des inscriptions et des alertes de validité au sein de votre communauté.
                    </p>
                    <div class="grid grid-cols-2 xl:grid-cols-4 gap-3 mt-8">
                        <div class="tc-stat">
                            <p class="tc-stat__k">Formations</p>
                            <p class="tc-stat__v"><?= (int) $stats['courses'] ?></p>
                        </div>
                        <div class="tc-stat">
                            <p class="tc-stat__k">Inscriptions</p>
                            <p class="tc-stat__v"><?= (int) $stats['enrollments'] ?></p>
                        </div>
                        <div class="tc-stat">
                            <p class="tc-stat__k">Complétion</p>
                            <p class="tc-stat__v text-emerald-600"><?= (float) ($stats['completed'] ?? 0) ?> %</p>
                        </div>
                        <div class="tc-stat">
                            <p class="tc-stat__k">Alertes validité</p>
                            <p class="tc-stat__v text-amber-500"><?= (int) ($stats['expiringCount'] ?? 0) ?></p>
                        </div>
                    </div>
                </header>

                <section class="grid md:grid-cols-2 xl:grid-cols-3 gap-4">
                    <a href="<?= url('admin/training/studio') ?>" class="tc-course-card group block no-underline text-inherit">
                        <p class="text-[9px] font-black tracking-[0.3em] uppercase text-emerald-600 mb-2">Création</p>
                        <h2 class="text-lg font-black uppercase tracking-tight text-slate-900 group-hover:text-emerald-800">Studio LMS</h2>
                        <p class="text-xs text-slate-600 mt-2 leading-relaxed">Parcours, slides canvas, modules et publication.</p>
                    </a>
                    <a href="<?= url('admin/training/courses') ?>" class="tc-course-card group block no-underline text-inherit">
                        <p class="text-[9px] font-black tracking-[0.3em] uppercase text-slate-400 mb-2">Catalogue</p>
                        <h2 class="text-lg font-black uppercase tracking-tight text-slate-900">Formations</h2>
                        <p class="text-xs text-slate-600 mt-2 leading-relaxed">Liste, vitrine publique et liens directs.</p>
                    </a>
                    <a href="<?= url('admin/training/enrollments') ?>" class="tc-course-card group block no-underline text-inherit">
                        <p class="text-[9px] font-black tracking-[0.3em] uppercase text-slate-400 mb-2">Effectifs</p>
                        <h2 class="text-lg font-black uppercase tracking-tight text-slate-900">Assignations</h2>
                        <p class="text-xs text-slate-600 mt-2 leading-relaxed">Inscriptions, statuts et échéances.</p>
                    </a>
                </section>

                <div class="flex flex-wrap gap-3">
                    <a href="<?= url('admin/training/reports') ?>" class="tc-btn-primary tc-btn-ghost">Rapports</a>
                    <a href="<?= url('admin/training/certificates') ?>" class="tc-btn-primary tc-btn-ghost">Certificats</a>
                    <a href="<?= url('admin/training/audit') ?>" class="tc-btn-primary tc-btn-ghost">Audit</a>
                    <a href="<?= url('formations') ?>" target="_blank" rel="noopener" class="tc-btn-primary tc-btn-emerald">Catalogue public ↗</a>
                </div>

                <?php if (!empty($expiring)): ?>
                <section class="rounded-2xl border border-amber-200/90 bg-amber-50/90 p-6 shadow-inner">
                    <h2 class="text-sm font-black uppercase tracking-[0.2em] text-amber-900/90 mb-4">Inscriptions à surveiller</h2>
                    <p class="text-xs text-amber-950/80 mb-4">Expirent ou expirées dans la fenêtre configurée (aperçu limité à 10).</p>
                    <ul class="space-y-3">
                        <?php foreach (array_slice($expiring, 0, 10) as $e): ?>
                        <li class="flex flex-wrap gap-x-2 gap-y-1 text-sm text-slate-800 border-b border-amber-200/50 pb-2 last:border-0">
                            <span class="font-semibold"><?= htmlspecialchars($e['course_title'] ?? '') ?></span>
                            <span class="text-slate-500">—</span>
                            <span><?= htmlspecialchars($e['display_name'] ?? $e['email'] ?? '') ?></span>
                            <span class="text-amber-800 font-mono text-xs"><?= date('d/m/Y', strtotime($e['expires_at'] ?? '')) ?></span>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </section>
                <?php endif; ?>

                <p class="text-sm text-slate-500">
                    <a href="<?= url('admin') ?>" class="font-semibold text-slate-700 underline decoration-slate-300 hover:text-emerald-800">← Administration</a>
                </p>
<?php require base_path('views/admin/training/partials/command_shell_close.php'); ?>
