<?php
$courses = $courses ?? [];
require base_path('views/admin/training/partials/command_shell_open.php');
?>
                <header class="tc-panel p-6 md:p-8">
                    <p class="tc-kicker">Conformité</p>
                    <h1 class="tc-hero-title mb-3">Rapports</h1>
                    <p class="text-slate-600 text-sm max-w-2xl leading-relaxed">
                        Suivez la conformité et l’avancement des parcours : sélectionnez une formation ci-dessous pour ouvrir les inscriptions et la progression des participants.
                    </p>
                </header>

                <div class="tc-panel p-6 md:p-8">
                    <h2 class="text-xs font-black uppercase tracking-[0.2em] text-slate-500 mb-4">Raccourcis par formation</h2>
                    <ul class="space-y-3">
                        <?php foreach ($courses as $c): ?>
                        <li>
                            <a href="<?= url('admin/training/enrollments?course_id=' . (int) $c['id']) ?>" class="text-emerald-700 font-semibold hover:underline"><?= htmlspecialchars($c['title']) ?></a>
                            <span class="text-slate-500 text-sm"> — assignations &amp; progression</span>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <p class="text-sm text-slate-500">
                    <a href="<?= url('admin/training') ?>" class="font-semibold text-slate-700 underline decoration-slate-300 hover:text-emerald-800">← Vue d’ensemble</a>
                </p>
<?php require base_path('views/admin/training/partials/command_shell_close.php'); ?>
