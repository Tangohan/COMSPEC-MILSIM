<?php
declare(strict_types=1);

/**
 * Page publique de C.V. du joueur (sans connexion).
 * Reçoit uniquement $cv, déjà filtré par App\Support\PublicCv\PublicCvPresenter (liste blanche + sections choisies).
 */
$cv = is_array($cv ?? null) ? $cv : [];
$h = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

$callsign = (string) ($cv['callsign'] ?? 'Opérateur');
$grade = $cv['grade'] ?? null;
$gradeShort = $cv['grade_short'] ?? null;
$unit = $cv['unit'] ?? null;
$community = $cv['community'] ?? null;
$communityUrl = $cv['community_url'] ?? null;
$avatar = $cv['avatar_url'] ?? null;
$bio = $cv['bio'] ?? null;
$counters = is_array($cv['counters'] ?? null) ? $cv['counters'] : [];
$pageUrl = (string) ($cv['page_url'] ?? '');
$og = is_array($cv['og'] ?? null) ? $cv['og'] : [];

$blocks = [
    'decorations' => ['Décorations', is_array($cv['decorations'] ?? null) ? $cv['decorations'] : []],
    'qualifications' => ['Qualifications', is_array($cv['qualifications'] ?? null) ? $cv['qualifications'] : []],
    'trainings' => ['Formations', is_array($cv['trainings'] ?? null) ? $cv['trainings'] : []],
    'operations' => ['Opérations', is_array($cv['operations'] ?? null) ? $cv['operations'] : []],
    'photos' => ['Photos', is_array($cv['photos'] ?? null) ? $cv['photos'] : []],
];
$blocks = array_filter($blocks, static fn (array $b): bool => $b[1] !== []);

$icons = [
    'decorations' => '<circle cx="12" cy="15" r="5"/><path d="M8.5 11.5L6 3h4l2 5 2-5h4l-2.5 8.5"/><path d="M12 13l.9 1.8 2 .3-1.45 1.4.35 2-1.8-.95-1.8.95.35-2L9.1 15.1l2-.3z"/>',
    'qualifications' => '<path d="M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6z"/><path d="M9 12l2 2 4-4"/>',
    'trainings' => '<path d="M2 9l10-5 10 5-10 5z"/><path d="M6 11v5c2 2 10 2 12 0v-5"/>',
    'operations' => '<circle cx="12" cy="12" r="8"/><circle cx="12" cy="12" r="3"/><path d="M12 2v4M12 18v4M2 12h4M18 12h4"/>',
    'photos' => '<rect x="3" y="5" width="18" height="14" rx="2"/><circle cx="12" cy="12" r="3.5"/><path d="M8 5l1.5-2h5L16 5"/>',
];
$icon = static fn (string $k, string $cls = 'h-5 w-5'): string => '<svg class="' . $cls . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ($icons[$k] ?? '') . '</svg>';

/* Métadonnées de partage (Open Graph / Twitter) via le partial commun. */
$seo_og_title = (string) ($og['title'] ?? $callsign);
$meta_description = (string) ($og['description'] ?? '');
$og_image = (string) ($og['image'] ?? '');
$og_image_alt = 'Portrait de ' . $callsign;
$seo_og_type = 'profile';
$seo_robots = 'noindex,follow';
if ($pageUrl !== '') {
    $seo_canonical = $pageUrl;
}
$seo_json_ld = [
    '@type' => 'ProfilePage',
    'mainEntity' => array_filter([
        '@type' => 'Person',
        'name' => $callsign,
        'jobTitle' => $grade,
        'memberOf' => $community !== null ? ['@type' => 'Organization', 'name' => $community] : null,
    ], static fn ($v): bool => $v !== null),
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $h($seo_og_title) ?></title>
<?php require base_path('views/partials/seo_meta.php'); ?>
    <?php require base_path('views/partials/tailwind_cdn_or_build.php'); ?>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', system-ui, sans-serif; }
        .pcv-ring { background: conic-gradient(from 210deg, #34d399, #38bdf8, #a3e635, #34d399); }
        .pcv-grid-bg {
            background-image:
                linear-gradient(rgba(255,255,255,.035) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,255,255,.035) 1px, transparent 1px);
            background-size: 36px 36px;
        }
        .pcv-tile { container-type: inline-size; }
        .pcv-tile-title { font-size: clamp(.62rem, 8cqi, .9rem); }
    </style>
</head>
<body class="min-h-screen bg-slate-950 text-slate-100 antialiased">
<div class="pcv-grid-bg min-h-screen">
    <header class="sticky top-0 z-20 border-b border-white/5 bg-slate-950/85 backdrop-blur">
        <div class="mx-auto flex max-w-4xl items-center justify-between px-4 py-3">
            <a href="<?= $h(url('/')) ?>" class="text-[0.7rem] font-black uppercase tracking-[0.4em] text-slate-300 hover:text-white">Athena</a>
            <button type="button" data-pcv-share class="inline-flex items-center gap-2 rounded-full border border-white/10 px-3.5 py-1.5 text-xs font-semibold text-slate-200 hover:border-emerald-400/60 hover:text-white">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 12v7a1 1 0 001 1h14a1 1 0 001-1v-7"/><path d="M16 6l-4-4-4 4"/><path d="M12 2v14"/></svg>
                <span data-pcv-share-label>Partager</span>
            </button>
        </div>
    </header>

    <main id="main" class="mx-auto max-w-4xl px-4 pb-16">
        <section class="flex flex-col items-center gap-6 pt-10 sm:flex-row sm:items-start sm:gap-12 sm:pt-14" aria-labelledby="pcv-callsign">
            <div class="pcv-ring shrink-0 rounded-full p-[3px] shadow-[0_0_60px_-12px_rgba(52,211,153,.55)]">
                <div class="rounded-full bg-slate-950 p-[3px]">
                    <?php if (is_string($avatar) && $avatar !== ''): ?>
                    <img src="<?= $h($avatar) ?>" alt="Portrait de <?= $h($callsign) ?>" class="h-28 w-28 rounded-full object-cover sm:h-36 sm:w-36" width="144" height="144">
                    <?php else: ?>
                    <span class="flex h-28 w-28 items-center justify-center rounded-full bg-slate-800 text-3xl font-black tracking-tight text-slate-300 sm:h-36 sm:w-36" aria-hidden="true"><?= $h($cv['initials'] ?? '·') ?></span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="w-full min-w-0 text-center sm:text-left">
                <div class="flex flex-col items-center gap-2 sm:flex-row sm:items-center sm:gap-3">
                    <h1 id="pcv-callsign" class="break-words text-3xl font-black tracking-tight text-white sm:text-4xl"><?= $h($callsign) ?></h1>
                    <?php if ($gradeShort !== null || $grade !== null): ?>
                    <span class="rounded-full border border-emerald-400/40 bg-emerald-400/10 px-2.5 py-0.5 text-[0.7rem] font-bold uppercase tracking-[0.18em] text-emerald-300" title="<?= $h($grade ?? $gradeShort) ?>"><?= $h($gradeShort ?? $grade) ?></span>
                    <?php endif; ?>
                </div>
                <?php if ($grade !== null || $unit !== null || $community !== null): ?>
                <p class="mt-2 text-sm text-slate-400">
                    <?php $parts = array_values(array_filter([$grade, $unit], static fn ($v): bool => $v !== null)); ?>
                    <?= $h(implode(' · ', $parts)) ?>
                    <?php if ($community !== null): ?>
                        <?= $parts !== [] ? ' · ' : '' ?>
                        <?php if (is_string($communityUrl) && $communityUrl !== ''): ?>
                        <a href="<?= $h($communityUrl) ?>" class="font-semibold text-slate-200 underline decoration-emerald-400/40 underline-offset-4 hover:text-white"><?= $h($community) ?></a>
                        <?php else: ?>
                        <span class="font-semibold text-slate-200"><?= $h($community) ?></span>
                        <?php endif; ?>
                    <?php endif; ?>
                </p>
                <?php endif; ?>

                <?php if ($counters !== []): ?>
                <ul class="mt-6 grid grid-cols-4 gap-2 border-y border-white/10 py-4 sm:max-w-lg sm:border-0 sm:py-0" aria-label="Compteurs">
                    <?php foreach ($counters as $c): ?>
                    <li class="text-center sm:text-left">
                        <span class="block text-xl font-black tabular-nums text-white sm:text-2xl"><?= $h($c['value'] ?? '0') ?></span>
                        <span class="mt-0.5 block text-[0.65rem] font-semibold uppercase tracking-[0.14em] text-slate-400"><?= $h($c['label'] ?? '') ?></span>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>

                <?php if (is_string($bio) && $bio !== ''): ?>
                <p class="mt-5 whitespace-pre-line text-sm leading-relaxed text-slate-300 sm:max-w-xl"><?= $h($bio) ?></p>
                <?php endif; ?>
            </div>
        </section>

        <?php if ($blocks !== []): ?>
        <nav class="mt-10 flex justify-center gap-1 overflow-x-auto border-t border-white/10 sm:justify-start" aria-label="Sections du parcours">
            <?php foreach ($blocks as $key => [$label, $items]): ?>
            <a href="#pcv-<?= $h($key) ?>" class="-mt-px inline-flex shrink-0 items-center gap-2 border-t border-transparent px-3 py-3 text-[0.68rem] font-bold uppercase tracking-[0.2em] text-slate-400 hover:border-white hover:text-white">
                <?= $icon($key, 'h-4 w-4') ?><?= $h($label) ?>
                <span class="text-slate-500"><?= count($items) ?></span>
            </a>
            <?php endforeach; ?>
        </nav>

        <?php foreach ($blocks as $key => [$label, $items]): ?>
        <section id="pcv-<?= $h($key) ?>" class="scroll-mt-20 pt-8" aria-labelledby="pcv-h-<?= $h($key) ?>">
            <h2 id="pcv-h-<?= $h($key) ?>" class="mb-3 flex items-center gap-2 text-xs font-black uppercase tracking-[0.3em] text-slate-300"><?= $icon($key, 'h-4 w-4 text-emerald-400') ?><?= $h($label) ?></h2>
            <ul class="grid grid-cols-3 gap-1 sm:gap-3">
                <?php foreach ($items as $item): ?>
                <?php if ($key === 'photos'): ?>
                <li class="aspect-square overflow-hidden rounded-sm bg-slate-900 sm:rounded-xl">
                    <img src="<?= $h($item['url'] ?? '') ?>" alt="<?= $h(($item['title'] ?? '') !== '' ? $item['title'] : 'Photo de ' . $callsign) ?>" loading="lazy" class="h-full w-full object-cover transition duration-300 hover:scale-105">
                </li>
                <?php else: ?>
                <li class="pcv-tile group relative flex aspect-square flex-col justify-between overflow-hidden rounded-sm border border-white/5 bg-gradient-to-br from-slate-800 to-slate-900 p-2.5 sm:rounded-xl sm:p-4">
                    <?php $img = $item['image'] ?? null; ?>
                    <div class="flex flex-1 items-center justify-center">
                        <?php if (is_string($img) && $img !== ''): ?>
                        <img src="<?= $h($img) ?>" alt="" loading="lazy" class="max-h-[58%] max-w-[58%] object-contain drop-shadow-[0_6px_18px_rgba(0,0,0,.5)]">
                        <?php elseif ($key === 'operations' && !empty($item['date'])): ?>
                        <span class="text-center text-[0.7rem] font-black uppercase leading-tight tracking-[0.16em] text-emerald-300 sm:text-sm"><?= $h($item['date']) ?></span>
                        <?php else: ?>
                        <span class="flex h-10 w-10 items-center justify-center rounded-full bg-emerald-400/10 text-emerald-300 sm:h-14 sm:w-14"><?= $icon($key, 'h-5 w-5 sm:h-7 sm:w-7') ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="min-w-0">
                        <p class="pcv-tile-title line-clamp-2 font-bold leading-snug text-white"><?= $h($item['title'] ?? '') ?></p>
                        <?php $meta = array_values(array_filter([$item['subtitle'] ?? null, $key !== 'operations' ? ($item['date'] ?? null) : null])); ?>
                        <?php if ($meta !== []): ?>
                        <p class="mt-0.5 hidden truncate text-[0.68rem] text-slate-400 sm:block"><?= $h(implode(' · ', $meta)) ?></p>
                        <?php endif; ?>
                    </div>
                </li>
                <?php endif; ?>
                <?php endforeach; ?>
            </ul>
        </section>
        <?php endforeach; ?>
        <?php else: ?>
        <div class="mt-12 rounded-2xl border border-dashed border-white/10 px-6 py-12 text-center">
            <p class="text-sm font-semibold text-slate-200">Le parcours de <?= $h($callsign) ?> commence.</p>
            <p class="mt-1 text-xs text-slate-400">Décorations, qualifications et opérations apparaîtront ici au fil des parties.</p>
        </div>
        <?php endif; ?>
    </main>

    <footer class="border-t border-white/5 py-6 text-center text-[0.68rem] text-slate-500">
        Page publiée par <?= $h($callsign) ?> sur <a href="<?= $h(url('/')) ?>" class="font-semibold text-slate-400 hover:text-white">Athena</a>.
    </footer>
</div>
<script>
(function () {
    var btn = document.querySelector('[data-pcv-share]');
    if (!btn) return;
    var label = btn.querySelector('[data-pcv-share-label]');
    var url = <?= json_encode($pageUrl !== '' ? $pageUrl : null, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?> || window.location.href;
    var title = <?= json_encode($seo_og_title, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
    btn.addEventListener('click', function () {
        if (navigator.share) {
            navigator.share({ title: title, url: url }).catch(function () {});
            return;
        }
        if (navigator.clipboard) {
            navigator.clipboard.writeText(url).then(function () {
                label.textContent = 'Lien copié';
                setTimeout(function () { label.textContent = 'Partager'; }, 2000);
            });
        }
    });
})();
</script>
</body>
</html>
