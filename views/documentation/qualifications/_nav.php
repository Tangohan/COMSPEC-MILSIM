<?php
/**
 * Navigation des guides des qualifications.
 *
 * @var array<string, array{file: string, title: string, hint: string}> $qualificationGuides
 * @var string $guideSlug guide ouvert (vide : sommaire)
 */
$guideSlug = (string) ($guideSlug ?? '');
?>
<nav class="site-docs__refs-nav mb-4 text-sm" aria-label="Guides des qualifications">
    <a href="<?= htmlspecialchars(url('documentation'), ENT_QUOTES, 'UTF-8') ?>" class="site-docs__refs-back">← Guide du portail</a>
    <?php if ($guideSlug !== ''): ?>
    <a href="<?= htmlspecialchars(url('documentation/qualifications'), ENT_QUOTES, 'UTF-8') ?>" class="font-semibold text-slate-600 underline decoration-slate-300 underline-offset-2 hover:text-sky-800">Guides des qualifications</a>
    <?php endif; ?>
    <?php foreach ($qualificationGuides as $navSlug => $navGuide): ?>
    <a href="<?= htmlspecialchars(url('documentation/qualifications/' . $navSlug), ENT_QUOTES, 'UTF-8') ?>" class="font-semibold text-slate-600 underline decoration-slate-300 underline-offset-2 hover:text-sky-800"<?= $navSlug === $guideSlug ? ' aria-current="page"' : '' ?>><?= htmlspecialchars($navGuide['title'], ENT_QUOTES, 'UTF-8') ?></a>
    <?php endforeach; ?>
    <a href="<?= htmlspecialchars(url('back-office/referentiels/qualifications'), ENT_QUOTES, 'UTF-8') ?>" class="font-semibold text-slate-600 underline decoration-slate-300 underline-offset-2 hover:text-sky-800">Référentiel des qualifications</a>
</nav>
