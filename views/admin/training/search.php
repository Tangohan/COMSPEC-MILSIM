<?php
declare(strict_types=1);
$q = trim((string) ($searchQuery ?? ''));
$courses = is_array($searchCourses ?? null) ? $searchCourses : [];
$docs = is_array($searchDocs ?? null) ? $searchDocs : [];
$certificates = is_array($searchCertificates ?? null) ? $searchCertificates : [];
$hasAny = $courses !== [] || $docs !== [] || $certificates !== [];
?>
<section class="tc-panel p-6 md:p-8 space-y-4">
    <p class="tc-kicker">Centre des opérations LMS</p>
    <h1 class="tc-hero-title">Recherche transverse</h1>
    <form method="get" action="<?= htmlspecialchars(training_lms_admin_url('recherche'), ENT_QUOTES, 'UTF-8') ?>" class="flex max-w-lg gap-1.5">
        <input type="search" name="q" value="<?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8') ?>" placeholder="Formations, documentations, certificats…" autofocus class="h-10 flex-1 rounded-lg border border-slate-300 px-3 text-sm">
        <button type="submit" class="h-10 rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">Rechercher</button>
    </form>
</section>

<?php if ($q === ''): ?>
<div class="tc-panel p-10 text-center text-slate-600">Tapez une recherche pour interroger formations, Documentations HTML et certificats en une fois.</div>
<?php elseif (!$hasAny): ?>
<div class="tc-panel p-10 text-center text-slate-600">Aucun résultat pour « <?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8') ?> ».</div>
<?php else: ?>

<?php if ($courses !== []): ?>
<section class="tc-panel p-6 md:p-8 space-y-3">
    <h2 class="text-sm font-black uppercase tracking-wide text-slate-500">Formations (<?= count($courses) ?>)</h2>
    <ul class="space-y-2">
        <?php foreach ($courses as $c): ?>
        <li class="rounded-lg border border-slate-200 px-3 py-2 flex items-center justify-between gap-3">
            <span class="text-sm font-semibold text-slate-800"><?= htmlspecialchars((string) $c['title']) ?></span>
            <a href="<?= htmlspecialchars(training_studio_url((string) $c['id']), ENT_QUOTES, 'UTF-8') ?>" class="text-xs font-semibold text-sky-700">Ouvrir dans le studio</a>
        </li>
        <?php endforeach; ?>
    </ul>
</section>
<?php endif; ?>

<?php if ($docs !== []): ?>
<section class="tc-panel p-6 md:p-8 space-y-3">
    <h2 class="text-sm font-black uppercase tracking-wide text-slate-500">Documentations HTML (<?= count($docs) ?>)</h2>
    <ul class="space-y-2">
        <?php foreach ($docs as $d): ?>
        <li class="rounded-lg border border-slate-200 px-3 py-2 flex items-center justify-between gap-3">
            <span class="text-sm font-semibold text-slate-800"><?= htmlspecialchars((string) $d['title']) ?></span>
            <a href="<?= htmlspecialchars(training_lms_admin_url('pages-html/' . (int) $d['id'] . '/modifier'), ENT_QUOTES, 'UTF-8') ?>" class="text-xs font-semibold text-sky-700">Ouvrir dans le studio</a>
        </li>
        <?php endforeach; ?>
    </ul>
</section>
<?php endif; ?>

<?php if ($certificates !== []): ?>
<section class="tc-panel p-6 md:p-8 space-y-3">
    <h2 class="text-sm font-black uppercase tracking-wide text-slate-500">Certificats (<?= count($certificates) ?>)</h2>
    <ul class="space-y-2">
        <?php foreach ($certificates as $cert): ?>
        <li class="rounded-lg border border-slate-200 px-3 py-2">
            <p class="text-sm font-semibold text-slate-800"><?= htmlspecialchars((string) ($cert['course_title'] ?? '')) ?> — <?= htmlspecialchars((string) ($cert['learner_display_name'] ?? $cert['learner_email'] ?? '')) ?></p>
            <p class="text-xs text-slate-500"><?= htmlspecialchars((string) ($cert['certificate_number'] ?? '')) ?> <?php if (!empty($cert['issued_at'])): ?>· délivré le <?= htmlspecialchars((string) $cert['issued_at']) ?><?php endif; ?></p>
        </li>
        <?php endforeach; ?>
    </ul>
</section>
<?php endif; ?>

<?php endif; ?>
