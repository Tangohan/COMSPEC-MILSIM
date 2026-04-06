<?php
declare(strict_types=1);
$base = url('');
$course = $course ?? null;
$enrollment = $enrollment ?? null;
$bilan = $bilan ?? null;
$certificate = $certificate ?? null;
$canNotifyStaff = $canNotifyStaff ?? true;
$notifyCooldownHours = $notifyCooldownHours ?? null;
if (!$course || !$enrollment || !$bilan) {
    echo '<p>Données indisponibles.</p>';
    return;
}
$mod = $bilan['module'] ?? [];
$moduleTitle = (string) ($mod['title'] ?? 'Module');
$slug = trim((string) ($course['slug'] ?? ($enrollment['course_slug'] ?? '')));
$courseUrl = $slug !== '' ? url('formations/' . rawurlencode($slug)) : url('formations/mes-formations');
$enrId = (int) $enrollment['id'];
$moduleId = (int) ($mod['id'] ?? 0);
$validated = !empty($bilan['module_validated']);
$courseDone = !empty($bilan['course_completed']);
$gaps = $bilan['gaps'] ?? [];
$theme = function_exists('training_lms_parse_theme') ? training_lms_parse_theme((string) ($course['theme_json'] ?? '')) : [];
$lmsTitle = 'Synthèse — ' . $moduleTitle;
$lmsBase = $base;
$lmsThemeVars = function_exists('training_lms_theme_css_vars') ? training_lms_theme_css_vars($theme) : '';
$lmsExtraHead = '';
ob_start();
require base_path('views/training/partials/lms_head.php');
$headHtml = ob_get_clean();
$flashOk = \App\Core\Session::getFlash('success');
$flashErr = \App\Core\Session::getFlash('error');
?>
<!DOCTYPE html>
<html lang="fr" class="scroll-smooth">
<head>
<?= $headHtml ?>
</head>
<body class="bg-slate-100 text-slate-900 overflow-x-hidden">
<div class="min-h-screen">
    <header class="border-b border-slate-200/80 bg-white/95 backdrop-blur-sm sticky top-0 z-30">
        <div class="max-w-3xl mx-auto px-4 py-4 flex flex-wrap items-center justify-between gap-3">
            <a href="<?= htmlspecialchars($courseUrl) ?>" class="text-sm font-bold text-emerald-800 hover:underline">← Formation</a>
            <span class="text-xs font-black uppercase tracking-wider text-slate-500">Fin de module</span>
        </div>
    </header>

    <main class="max-w-3xl mx-auto px-4 py-10 space-y-8">
        <?php if ($flashOk): ?>
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-950 font-medium"><?= htmlspecialchars((string) $flashOk) ?></div>
        <?php endif; ?>
        <?php if ($flashErr): ?>
        <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-950 font-medium"><?= htmlspecialchars((string) $flashErr) ?></div>
        <?php endif; ?>

        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm p-6 md:p-8">
            <h1 class="text-2xl font-black text-slate-900 tracking-tight"><?= htmlspecialchars($moduleTitle) ?></h1>
            <p class="mt-2 text-sm text-slate-600"><?= htmlspecialchars((string) ($course['title'] ?? '')) ?></p>

            <?php if ($validated): ?>
            <div class="mt-6 rounded-xl border border-emerald-200 bg-emerald-50/80 p-5">
                <p class="font-bold text-emerald-950">Module validé</p>
                <p class="text-sm text-emerald-900/90 mt-1">Toutes les leçons requises et les évaluations de ce module sont au vert. Vous pouvez poursuivre la suite du parcours.</p>
            </div>
            <?php else: ?>
            <div class="mt-6 rounded-xl border border-rose-200 bg-rose-50/80 p-5">
                <p class="font-bold text-rose-950">Module non terminé</p>
                <p class="text-sm text-rose-900/90 mt-2">Il reste des étapes avant de valider ce bloc. Détail ci-dessous.</p>
                <?php if ($gaps !== []): ?>
                <ul class="mt-4 space-y-2 text-sm text-rose-950 list-disc pl-5">
                    <?php foreach ($gaps as $line): ?>
                    <li><?= htmlspecialchars((string) $line) ?></li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
                <?php if ($canNotifyStaff): ?>
                <form method="post" action="<?= url('formations/bilan-module/notifier') ?>" class="mt-5">
                    <?= \App\Core\Csrf::field() ?>
                    <input type="hidden" name="enrollment_id" value="<?= $enrId ?>">
                    <input type="hidden" name="module_id" value="<?= $moduleId ?>">
                    <button type="submit" class="rounded-xl bg-slate-900 px-5 py-2.5 text-sm font-bold text-white hover:bg-slate-800">Prévenir le personnel pédagogique</button>
                    <p class="text-xs text-rose-800/80 mt-2">Un message est envoyé aux référents de la formation (et une alerte apparaît dans le tableau de bord de la communauté). Une nouvelle alerte pour ce module n’est possible qu’après un délai.</p>
                </form>
                <?php elseif ($notifyCooldownHours !== null): ?>
                <p class="mt-4 text-xs text-rose-800/90">Vous avez déjà récemment demandé de l’aide pour ce module. Réessayez dans environ <?= (int) $notifyCooldownHours ?> h si besoin.</p>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>

        <section class="rounded-2xl border border-slate-200 bg-white shadow-sm p-6 md:p-8">
            <h2 class="text-sm font-black uppercase tracking-wider text-slate-500 mb-4">Leçons</h2>
            <ul class="divide-y divide-slate-100">
                <?php foreach ($bilan['lessons'] ?? [] as $row): ?>
                <li class="py-3 flex flex-wrap justify-between gap-2 text-sm">
                    <span class="font-medium text-slate-800"><?= htmlspecialchars((string) ($row['title'] ?? '')) ?></span>
                    <span class="text-xs font-semibold <?= ($row['status'] ?? '') === 'completed' ? 'text-emerald-700' : 'text-amber-800' ?>"><?= htmlspecialchars((string) ($row['status_label'] ?? '')) ?></span>
                </li>
                <?php endforeach; ?>
            </ul>
        </section>

        <?php if (($bilan['quizzes'] ?? []) !== []): ?>
        <section class="rounded-2xl border border-slate-200 bg-white shadow-sm p-6 md:p-8">
            <h2 class="text-sm font-black uppercase tracking-wider text-slate-500 mb-4">Évaluations du module</h2>
            <ul class="space-y-4">
                <?php foreach ($bilan['quizzes'] as $qz): ?>
                <li class="rounded-xl border border-slate-100 bg-slate-50/50 p-4">
                    <p class="font-bold text-slate-900"><?= htmlspecialchars((string) ($qz['title'] ?? 'Évaluation')) ?></p>
                    <p class="text-xs text-slate-600 mt-1">Seuil de réussite : <?= htmlspecialchars((string) round((float) ($qz['passing_score'] ?? 80), 1)) ?> %</p>
                    <p class="text-sm mt-2 <?= !empty($qz['passed']) ? 'text-emerald-800 font-semibold' : 'text-amber-900' ?>">
                        <?php if (!empty($qz['passed'])): ?>
                        Réussite enregistrée<?php if ($qz['best_score'] !== null): ?> (meilleur score : <?= htmlspecialchars((string) round((float) $qz['best_score'], 1)) ?> %)<?php endif; ?>.
                        <?php elseif ($qz['attempts_count'] === 0): ?>
                        Aucune tentative terminée pour l’instant.
                        <?php else: ?>
                        Dernières tentatives sans réussite<?php if ($qz['best_score'] !== null): ?> — meilleur score : <?= htmlspecialchars((string) round((float) $qz['best_score'], 1)) ?> %<?php endif; ?>.
                        <?php endif; ?>
                    </p>
                </li>
                <?php endforeach; ?>
            </ul>
        </section>
        <?php endif; ?>

        <section class="rounded-2xl border border-slate-200 bg-white shadow-sm p-6 md:p-8">
            <h2 class="text-sm font-black uppercase tracking-wider text-slate-500 mb-3">Attestation</h2>
            <?php if ($courseDone && $certificate): ?>
            <p class="text-sm text-slate-700">Votre parcours complet est validé. Vous pouvez consulter votre attestation.</p>
            <a href="<?= url('formations/certificate/' . (int) ($certificate['id'] ?? 0)) ?>" class="mt-4 inline-flex rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-bold text-white hover:bg-emerald-700">Ouvrir l’attestation</a>
            <?php elseif ($courseDone): ?>
            <p class="text-sm text-slate-700">Parcours terminé. Si cette formation délivre une attestation, elle apparaîtra sous peu dans « Mes formations ».</p>
            <a href="<?= url('formations/mes-formations') ?>" class="mt-3 text-sm font-semibold text-emerald-800 underline">Mes formations</a>
            <?php else: ?>
            <p class="text-sm text-slate-600">L’attestation n’est disponible qu’une fois l’ensemble du parcours validé (y compris les éventuelles évaluations finales).</p>
            <?php endif; ?>
        </section>

        <div class="text-center pb-8">
            <a href="<?= htmlspecialchars($courseUrl) ?>" class="text-sm font-bold text-slate-700 underline">Retour à la formation</a>
        </div>
    </main>
</div>
<?php require base_path('views/partials/cookie_banner.php'); ?>
</body>
</html>
