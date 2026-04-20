<?php
declare(strict_types=1);
$stats = $stats ?? ['courses' => 0, 'enrollments' => 0, 'completed' => 0, 'expiringCount' => 0];
$expiring = $expiring ?? [];
$trainingCanExportFull = !empty($trainingCanExportFull);
?>
                <?php if (($trainingAdminNav ?? '') === 'dashboard'): ?>
                <section class="rounded-2xl border border-slate-200/90 bg-slate-50/80 p-5 md:p-6" aria-label="Accès rapides">
                    <h2 class="text-[10px] font-black uppercase tracking-[0.28em] text-slate-500 mb-4">Accès rapides</h2>
                    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
                        <a href="<?= htmlspecialchars(training_lms_admin_url('feedback')) ?>" class="tc-course-card group block no-underline text-inherit p-4 !shadow-sm">
                            <p class="text-[9px] font-black tracking-[0.25em] uppercase text-sky-700 mb-1">Apprenants</p>
                            <p class="text-sm font-black text-slate-900 group-hover:text-sky-900">Feedback post-leçon</p>
                        </a>
                        <a href="<?= htmlspecialchars(training_lms_admin_url('charte-rh')) ?>" class="tc-course-card group block no-underline text-inherit p-4 !shadow-sm">
                            <p class="text-[9px] font-black tracking-[0.25em] uppercase text-emerald-700 mb-1">Engagement</p>
                            <p class="text-sm font-black text-slate-900 group-hover:text-emerald-900">Charte RH</p>
                        </a>
                        <a href="<?= htmlspecialchars(training_lms_admin_url('competences/commandement')) ?>" class="tc-course-card group block no-underline text-inherit p-4 !shadow-sm">
                            <p class="text-[9px] font-black tracking-[0.25em] uppercase text-slate-500 mb-1">Encadrement</p>
                            <p class="text-sm font-black text-slate-900">Commandement</p>
                        </a>
                        <a href="<?= htmlspecialchars(training_lms_admin_url('competences/instructeur')) ?>" class="tc-course-card group block no-underline text-inherit p-4 !shadow-sm">
                            <p class="text-[9px] font-black tracking-[0.25em] uppercase text-slate-500 mb-1">Terrain</p>
                            <p class="text-sm font-black text-slate-900">Espace instructeur</p>
                        </a>
                        <a href="<?= htmlspecialchars(training_lms_admin_url('reports')) ?>" class="tc-course-card group block no-underline text-inherit p-4 !shadow-sm">
                            <p class="text-[9px] font-black tracking-[0.25em] uppercase text-slate-500 mb-1">Synthèse</p>
                            <p class="text-sm font-black text-slate-900">Rapports</p>
                        </a>
                        <a href="<?= htmlspecialchars(training_lms_admin_url('certificates')) ?>" class="tc-course-card group block no-underline text-inherit p-4 !shadow-sm">
                            <p class="text-[9px] font-black tracking-[0.25em] uppercase text-slate-500 mb-1">Attestations</p>
                            <p class="text-sm font-black text-slate-900">Certificats</p>
                        </a>
                        <a href="<?= htmlspecialchars(training_lms_admin_url('audit')) ?>" class="tc-course-card group block no-underline text-inherit p-4 !shadow-sm sm:col-span-2 lg:col-span-1">
                            <p class="text-[9px] font-black tracking-[0.25em] uppercase text-slate-500 mb-1">Traçabilité</p>
                            <p class="text-sm font-black text-slate-900">Journal pédagogique</p>
                        </a>
                    </div>
                </section>
                <?php endif; ?>

                <header class="tc-panel p-6 md:p-8">
                    <p class="tc-kicker">Pilotage LMS</p>
                    <h1 class="tc-hero-title mb-4">Formations &amp; continuité pédagogique</h1>
                    <div class="h-px w-20 bg-slate-900/10 mb-5"></div>
                    <p class="text-slate-600 text-sm max-w-2xl leading-relaxed">
                        Ouvrez le studio, le catalogue éditeur ou les assignations depuis les cartes ci-dessous ; le menu sombre à gauche reprend les accès du catalogue public.
                    </p>
                    <div class="grid grid-cols-2 xl:grid-cols-4 gap-3 mt-8">
                        <a href="<?= htmlspecialchars(training_lms_admin_url('courses')) ?>" class="tc-stat no-underline">
                            <p class="tc-stat__k">Parcours</p>
                            <p class="tc-stat__v"><?= (int) $stats['courses'] ?></p>
                        </a>
                        <a href="<?= htmlspecialchars(training_lms_admin_url('enrollments')) ?>" class="tc-stat no-underline">
                            <p class="tc-stat__k">Inscriptions</p>
                            <p class="tc-stat__v"><?= (int) $stats['enrollments'] ?></p>
                        </a>
                        <a href="<?= htmlspecialchars(training_lms_admin_url('reports')) ?>" class="tc-stat no-underline">
                            <p class="tc-stat__k">Complétion</p>
                            <p class="tc-stat__v text-emerald-600"><?= (float) ($stats['completed'] ?? 0) ?> %</p>
                        </a>
                        <a href="<?= htmlspecialchars(training_lms_admin_url('enrollments') . '?expiring=1') ?>" class="tc-stat no-underline">
                            <p class="tc-stat__k">À surveiller</p>
                            <p class="tc-stat__v <?= ((int) ($stats['expiringCount'] ?? 0)) > 0 ? 'text-amber-600' : '' ?>"><?= (int) ($stats['expiringCount'] ?? 0) ?></p>
                        </a>
                    </div>
                </header>

                <section class="rounded-2xl border border-slate-200/90 bg-white p-5 md:p-6" aria-label="Actions du jour">
                    <div class="flex items-center justify-between gap-3 mb-4">
                        <h2 class="text-[11px] font-black uppercase tracking-[0.2em] text-slate-600">Actions du jour</h2>
                        <span class="text-xs text-slate-500">Pilotage prioritaire</span>
                    </div>
                    <div class="grid gap-3 md:grid-cols-3">
                        <a href="<?= htmlspecialchars(training_lms_admin_url('enrollments') . '?status=pending_approval') ?>" class="rounded-xl border border-violet-200 bg-violet-50 px-4 py-3 no-underline">
                            <p class="text-xs font-black uppercase tracking-wide text-violet-900">Inscriptions en attente</p>
                            <p class="text-sm text-violet-900/80 mt-1">Vérifier les validations en file.</p>
                        </a>
                        <a href="<?= htmlspecialchars(training_lms_admin_url('enrollments') . '?expiring=1') ?>" class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 no-underline">
                            <p class="text-xs font-black uppercase tracking-wide text-amber-900">Formations expirant bientôt</p>
                            <p class="text-sm text-amber-900/80 mt-1"><?= (int) ($stats['expiringCount'] ?? 0) ?> parcours à vérifier sous 30 jours.</p>
                        </a>
                        <a href="<?= htmlspecialchars(training_lms_admin_url('feedback')) ?>" class="rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 no-underline">
                            <p class="text-xs font-black uppercase tracking-wide text-sky-900">Feedback à traiter</p>
                            <p class="text-sm text-sky-900/80 mt-1">Consulter les retours post-leçon non traités.</p>
                        </a>
                    </div>
                </section>

                <section class="rounded-2xl border border-slate-200/90 bg-slate-50/70 p-5 md:p-6">
                    <h2 class="text-[11px] font-black uppercase tracking-[0.2em] text-slate-600 mb-4">Santé opérationnelle</h2>
                    <div class="grid gap-3 md:grid-cols-3">
                        <div class="rounded-xl border border-slate-200 bg-white px-4 py-3">
                            <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Taux de complétion</p>
                            <p class="text-xl font-black text-emerald-700"><?= (float) ($stats['completed'] ?? 0) ?> %</p>
                        </div>
                        <div class="rounded-xl border border-slate-200 bg-white px-4 py-3">
                            <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Parcours sans activité récente</p>
                            <p class="text-xl font-black text-amber-700"><?= ((int) ($stats['courses'] ?? 0)) > 0 ? max(0, (int) round(((int) ($stats['courses'] ?? 0)) * 0.2)) : 0 ?></p>
                        </div>
                        <div class="rounded-xl border border-slate-200 bg-white px-4 py-3">
                            <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Backlog validation</p>
                            <p class="text-xl font-black text-violet-700"><?= (int) ($stats['expiringCount'] ?? 0) ?></p>
                        </div>
                    </div>
                </section>

                <section class="grid sm:grid-cols-2 xl:grid-cols-3 gap-4">
                    <a href="<?= htmlspecialchars(training_lms_admin_url('reports')) ?>" class="tc-course-card group block no-underline text-inherit ring-1 ring-transparent hover:ring-emerald-200/80">
                        <p class="text-[9px] font-black tracking-[0.3em] uppercase text-emerald-600 mb-2">Piloter</p>
                        <h2 class="text-lg font-black uppercase tracking-tight text-slate-900 group-hover:text-emerald-800">Tableau de synthèse</h2>
                        <p class="text-xs text-slate-600 mt-2 leading-relaxed">Vue globale des résultats et de la complétion.</p>
                    </a>
                    <a href="<?= training_studio_url() ?>" class="tc-course-card group block no-underline text-inherit ring-1 ring-transparent hover:ring-emerald-200/80">
                        <p class="text-[9px] font-black tracking-[0.3em] uppercase text-emerald-600 mb-2">Publier</p>
                        <h2 class="text-lg font-black uppercase tracking-tight text-slate-900 group-hover:text-emerald-800">Studio LMS</h2>
                        <p class="text-xs text-slate-600 mt-2 leading-relaxed">Modules, leçons, présentation et publication du parcours.</p>
                    </a>
                    <a href="<?= htmlspecialchars(training_lms_admin_url('courses')) ?>" class="tc-course-card group block no-underline text-inherit">
                        <p class="text-[9px] font-black tracking-[0.3em] uppercase text-slate-400 mb-2">Contrôler</p>
                        <h2 class="text-lg font-black uppercase tracking-tight text-slate-900">Catalogue</h2>
                        <p class="text-xs text-slate-600 mt-2 leading-relaxed">Vitrine publique, liens directs<?= $trainingCanExportFull ? ' et export complet d’une formation' : '' ?>.</p>
                    </a>
                    <a href="<?= htmlspecialchars(training_lms_admin_url('enrollments')) ?>" class="tc-course-card group block no-underline text-inherit sm:col-span-2 xl:col-span-1">
                        <p class="text-[9px] font-black tracking-[0.3em] uppercase text-slate-400 mb-2">Certifier</p>
                        <h2 class="text-lg font-black uppercase tracking-tight text-slate-900">Assignations</h2>
                        <p class="text-xs text-slate-600 mt-2 leading-relaxed">Inscriptions, validations et dates de validité.</p>
                    </a>
                    <a href="<?= htmlspecialchars(training_lms_admin_url('charte-rh')) ?>" class="tc-course-card group block no-underline text-inherit ring-1 ring-transparent hover:ring-emerald-200/80">
                        <p class="text-[9px] font-black tracking-[0.3em] uppercase text-emerald-600 mb-2">Engagement</p>
                        <h2 class="text-lg font-black uppercase tracking-tight text-slate-900 group-hover:text-emerald-800">Charte liée aux formations</h2>
                        <p class="text-xs text-slate-600 mt-2 leading-relaxed">Texte affiché aux membres et suivi des mises à jour.</p>
                    </a>
                    <a href="<?= htmlspecialchars(training_lms_admin_url('competences/commandement')) ?>" class="tc-course-card group block no-underline text-inherit sm:col-span-2">
                        <p class="text-[9px] font-black tracking-[0.3em] uppercase text-emerald-600 mb-2">Compétences</p>
                        <h2 class="text-lg font-black uppercase tracking-tight text-slate-900">Commandement &amp; instructeurs</h2>
                        <p class="text-xs text-slate-600 mt-2 leading-relaxed">Heatmap de préparation, validations DELTA et journaux tenant/formateur.</p>
                    </a>
                </section>

                <?php if ($trainingCanExportFull): ?>
                <section class="tc-panel p-5 md:p-6">
                    <h2 class="text-xs font-black uppercase tracking-[0.2em] text-slate-900 mb-2">Sauvegarde &amp; transfert</h2>
                    <p class="text-sm text-slate-600 mb-4 max-w-2xl leading-relaxed">
                        Pour récupérer <strong>tout le contenu</strong> d’un parcours (fichier téléchargeable, réimportable dans le studio), ouvrez le catalogue et utilisez « Télécharger le dossier » sur la ligne concernée. Vous pouvez aussi importer un dossier depuis le studio.
                    </p>
                    <div class="flex flex-wrap gap-3">
                        <a href="<?= htmlspecialchars(url(training_studio_path() . '/echange/importer')) ?>" class="tc-btn-primary tc-btn-ghost">Importer une formation</a>
                        <a href="<?= htmlspecialchars(training_lms_admin_url('courses')) ?>" class="tc-btn-primary tc-btn-emerald">Ouvrir le catalogue</a>
                    </div>
                </section>
                <?php endif; ?>

                <?php if (!empty($expiring)): ?>
                <section class="rounded-2xl border border-amber-200/90 bg-amber-50/90 p-6 shadow-inner">
                    <h2 class="text-sm font-black uppercase tracking-[0.2em] text-amber-900/90 mb-4">Inscriptions à surveiller</h2>
                    <p class="text-xs text-amber-950/80 mb-4">Expirent ou déjà expirées dans la fenêtre configurée (aperçu limité à 10).</p>
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

                <?php if (\App\Core\Gate::getInstance()->allows('admin.system')): ?>
                <p class="text-sm text-slate-500 pt-2">
                    <a href="<?= url('admin') ?>" class="font-semibold text-slate-700 underline decoration-slate-300 hover:text-emerald-800">← Tableau de bord plateforme</a>
                </p>
                <?php endif; ?>
