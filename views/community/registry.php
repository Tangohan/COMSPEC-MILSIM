<?php
/** @var list<array<string, mixed>> $registryTenants */
$registryTenants = $registryTenants ?? [];
$registryCount = count($registryTenants);

/** Couverture optionnelle : public/assets/img/communities/{slug}-cover.jpg */
$registryCoverUrl = static function (string $slug): ?string {
    $rel = 'assets/img/communities/' . $slug . '-cover.jpg';
    $path = base_path('public/' . $rel);
    return is_file($path) ? url($rel) : null;
};

/** Dégradé distinct par slug (fallback sans image). */
$registryCoverGradient = static function (string $slug): string {
    $h = crc32($slug);
    $h1 = $h % 360;
    $h2 = (($h >> 9) & 0xffff) % 360;
    $h3 = (($h >> 17) & 0xffff) % 360;

    return sprintf(
        'background: radial-gradient(circle at 80%% 20%%, hsla(%d,65%%,42%%,0.35), transparent 45%%), '
        . 'radial-gradient(circle at 10%% 90%%, hsla(%d,55%%,38%%,0.3), transparent 40%%), '
        . 'linear-gradient(155deg, hsl(%d,32%%,12%%) 0%%, hsl(%d,28%%,8%%) 55%%, hsl(%d,35%%,6%%) 100%%);',
        $h1,
        $h2,
        $h1,
        $h2,
        $h3
    );
};

if ($registryCount === 0) {
    $countLabel = 'Aucune communauté listée';
} elseif ($registryCount === 1) {
    $countLabel = '1 communauté visible';
} else {
    $countLabel = $registryCount . ' communautés visibles';
}
?>
<div class="min-h-screen bg-slate-100">
    <div class="mx-auto max-w-7xl px-6 py-10 md:py-14">

        <!-- Hero -->
        <section class="relative overflow-hidden rounded-[2rem] border border-slate-200 bg-white shadow-[0_24px_80px_-28px_rgba(15,23,42,0.18)]">
            <div class="absolute inset-0 bg-[radial-gradient(circle_at_top_right,rgba(16,185,129,0.10),transparent_28%),radial-gradient(circle_at_left,rgba(59,130,246,0.08),transparent_24%)]"></div>

            <div class="relative grid gap-8 px-6 py-8 md:grid-cols-[1.15fr_0.85fr] md:px-8 md:py-10">
                <div>
                    <p class="text-[11px] font-black uppercase tracking-[0.35em] text-slate-400 mb-3">Registre</p>
                    <h1 class="text-3xl md:text-5xl font-black uppercase tracking-tight text-slate-950">
                        Unités &amp; communautés
                    </h1>
                    <p class="mt-4 max-w-2xl text-sm leading-6 text-slate-600">
                        Parcourez les organisations présentes sur la plateforme, consultez leur fiche publique, découvrez leur structure et utilisez un
                        <a href="<?= htmlspecialchars(url('join')) ?>" class="font-semibold text-emerald-700 underline decoration-emerald-300 underline-offset-4">code d’accès</a>
                        pour rejoindre directement une communauté.
                    </p>

                    <div class="mt-6 flex flex-wrap gap-3">
                        <a href="<?= htmlspecialchars(url('join')) ?>" class="inline-flex items-center rounded-2xl bg-slate-950 px-5 py-3 text-[11px] font-black uppercase tracking-[0.2em] text-white transition hover:bg-emerald-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 focus-visible:ring-offset-2">
                            Rejoindre par code
                        </a>
                        <a href="<?= htmlspecialchars(url('pointage')) ?>" class="inline-flex items-center rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-3 text-[11px] font-black uppercase tracking-[0.2em] text-emerald-900 transition hover:border-emerald-400 hover:bg-emerald-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 focus-visible:ring-offset-2">
                            Pointage
                        </a>
                        <a href="<?= htmlspecialchars(url('dashboard')) ?>" class="inline-flex items-center rounded-2xl border border-slate-200 bg-white px-5 py-3 text-[11px] font-black uppercase tracking-[0.2em] text-slate-700 transition hover:border-slate-300 hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-400 focus-visible:ring-offset-2">
                            Tableau de bord
                        </a>
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-2 md:grid-cols-1">
                    <div class="rounded-[1.5rem] border border-slate-200 bg-slate-50 p-5">
                        <p class="text-[11px] font-black uppercase tracking-[0.22em] text-slate-400">Navigation</p>
                        <p class="mt-2 text-lg font-black tracking-tight text-slate-950">Catalogue public</p>
                        <p class="mt-2 text-sm leading-6 text-slate-600">
                            Consultez les espaces visibles, leurs identifiants, leurs accès et leur positionnement sur la plateforme.
                        </p>
                    </div>

                    <div class="rounded-[1.5rem] border border-emerald-200 bg-emerald-50 p-5">
                        <p class="text-[11px] font-black uppercase tracking-[0.22em] text-emerald-700">Accès rapide</p>
                        <p class="mt-2 text-lg font-black tracking-tight text-slate-950">Code d’invitation</p>
                        <p class="mt-2 text-sm leading-6 text-slate-700">
                            Si vous disposez déjà d’un code communauté, utilisez-le pour être redirigé immédiatement vers l’espace cible.
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Toolbar -->
        <section class="mt-8 flex flex-col gap-4 rounded-[1.75rem] border border-slate-200 bg-white px-5 py-4 shadow-sm md:flex-row md:items-center md:justify-between">
            <div>
                <p class="text-[11px] font-black uppercase tracking-[0.22em] text-slate-400">Catalogue</p>
                <h2 class="mt-1 text-xl font-black tracking-tight text-slate-950">Communautés disponibles</h2>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <div class="inline-flex items-center rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-600">
                    <span class="mr-2 h-2 w-2 rounded-full <?= $registryCount > 0 ? 'bg-emerald-500' : 'bg-amber-400' ?>" aria-hidden="true"></span>
                    <?= htmlspecialchars($countLabel) ?>
                </div>
                <a href="<?= htmlspecialchars(url('pointage')) ?>" class="inline-flex items-center rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm font-semibold text-emerald-900 transition hover:border-emerald-400 hover:bg-emerald-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 focus-visible:ring-offset-2">
                    Pointage
                </a>
                <a href="<?= htmlspecialchars(url('join')) ?>" class="inline-flex items-center rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:border-slate-300 hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-400 focus-visible:ring-offset-2">
                    Saisir un code
                </a>
                <a href="<?= htmlspecialchars(url('hub')) ?>" class="inline-flex items-center rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:border-slate-300 hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-400 focus-visible:ring-offset-2">
                    Hub
                </a>
            </div>
        </section>

        <?php if ($registryTenants === []): ?>
            <div class="mt-8 rounded-[2rem] border border-dashed border-slate-300 bg-white px-8 py-16 text-center shadow-sm">
                <p class="text-[11px] font-black uppercase tracking-[0.22em] text-slate-400">Registre vide</p>
                <p class="mt-3 text-lg font-black text-slate-950">Aucune communauté listée pour l’instant</p>
                <p class="mt-2 max-w-md mx-auto text-sm text-slate-600">
                    Toutes les communautés ne choisissent pas d’être visibles ici. Vous pouvez créer la vôtre ou revenir plus tard.
                </p>
                <a href="<?= htmlspecialchars(url('communities/create')) ?>" class="mt-8 inline-flex items-center rounded-2xl bg-slate-950 px-6 py-3 text-[11px] font-black uppercase tracking-[0.2em] text-white transition hover:bg-emerald-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 focus-visible:ring-offset-2">
                    Créer une communauté
                </a>
            </div>
        <?php else: ?>
        <!-- Cards -->
        <ul class="mt-8 grid gap-6 lg:grid-cols-2">
            <?php foreach ($registryTenants as $t): ?>
                <?php
                $slug = (string) ($t['slug'] ?? '');
                $name = (string) ($t['name'] ?? $slug);
                $code = trim((string) ($t['community_code'] ?? ''));
                $logoUrl = trim((string) ($t['logo_url'] ?? ''));
                $locked = !empty($t['registry_locked']);
                $simpleReg = !empty($t['registry_simple_reg']);
                $excerpt = trim((string) ($t['registry_excerpt'] ?? ''));
                $styleBadgeLabels = is_array($t['registry_style_badge_labels'] ?? null) ? $t['registry_style_badge_labels'] : [];
                $registryTagLabels = is_array($t['registry_tag_labels'] ?? null) ? $t['registry_tag_labels'] : [];
                $gameLabel = trim((string) ($t['game_label'] ?? ''));
                $coverUrl = $registryCoverUrl($slug);
                $gradientStyle = $registryCoverGradient($slug);
                $publicUrl = url('c/' . rawurlencode($slug));
                $joinDirect = $code !== '' ? url('join') . '?code=' . rawurlencode($code) : null;
                ?>
            <li class="group overflow-hidden rounded-[2rem] border border-slate-200 bg-white shadow-[0_20px_70px_-30px_rgba(15,23,42,0.22)] transition-all hover:-translate-y-1 hover:border-emerald-300 hover:shadow-[0_30px_90px_-28px_rgba(16,185,129,0.18)]">
                <!-- cover -->
                <div class="relative h-48 overflow-hidden border-b border-slate-200 bg-slate-950">
                    <?php if ($coverUrl !== null): ?>
                    <img
                        src="<?= htmlspecialchars($coverUrl) ?>"
                        alt=""
                        class="h-full w-full object-cover opacity-80 transition duration-500 group-hover:scale-[1.03]"
                    >
                    <?php else: ?>
                    <div class="absolute inset-0" style="<?= htmlspecialchars($gradientStyle) ?>" role="img" aria-hidden="true"></div>
                    <?php endif; ?>
                    <div class="absolute inset-0 bg-gradient-to-t from-slate-950 via-slate-950/50 to-transparent"></div>

                    <div class="absolute left-5 top-5 flex flex-wrap gap-2">
                        <span class="inline-flex items-center rounded-full bg-white/10 px-3 py-1 text-[10px] font-black uppercase tracking-[0.18em] text-white backdrop-blur">
                            Communauté
                        </span>
                        <span class="inline-flex items-center rounded-full bg-emerald-400/90 px-3 py-1 text-[10px] font-black uppercase tracking-[0.18em] text-slate-950">
                            Fiche publique
                        </span>
                        <?php if ($locked): ?>
                        <span class="inline-flex items-center rounded-full bg-amber-400/95 px-3 py-1 text-[10px] font-black uppercase tracking-[0.18em] text-slate-950">
                            Recrutement fermé
                        </span>
                        <?php endif; ?>
                    </div>

                    <div class="absolute bottom-5 left-5 right-5 flex items-end justify-between gap-4">
                        <div class="min-w-0">
                            <h2 class="text-2xl font-black tracking-tight text-white"><?= htmlspecialchars($name) ?></h2>
                            <?php if ($gameLabel !== ''): ?>
                            <p class="mt-1 text-sm font-semibold leading-snug text-white/95"><?= htmlspecialchars($gameLabel) ?></p>
                            <?php endif; ?>
                        </div>

                        <?php if ($logoUrl !== '' && filter_var($logoUrl, FILTER_VALIDATE_URL)): ?>
                        <div class="hidden h-14 w-14 shrink-0 overflow-hidden rounded-2xl border border-white/20 bg-white/10 p-1 backdrop-blur sm:flex">
                            <img src="<?= htmlspecialchars($logoUrl) ?>" alt="" class="h-full w-full object-contain">
                        </div>
                        <?php else: ?>
                        <div class="hidden h-14 w-14 shrink-0 items-center justify-center rounded-2xl border border-white/15 bg-white/10 backdrop-blur sm:flex" aria-hidden="true">
                            <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 21h18M5 21V7l8-4 6 4v14M9 9h.01M9 12h.01M9 15h.01M15 12h.01M15 15h.01"></path>
                            </svg>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- body -->
                <div class="p-6">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="inline-flex items-center rounded-full bg-slate-100 px-3 py-1 text-[10px] font-black uppercase tracking-[0.18em] text-slate-700">
                            <?= $simpleReg ? 'Inscription simple' : 'Parcours MilSim' ?>
                        </span>
                        <?php foreach ($styleBadgeLabels as $bl): ?>
                            <?php if (is_string($bl) && $bl !== ''): ?>
                            <span class="inline-flex items-center rounded-full bg-sky-50 px-3 py-1 text-[10px] font-black uppercase tracking-[0.18em] text-sky-800"><?= htmlspecialchars($bl) ?></span>
                            <?php endif; ?>
                        <?php endforeach; ?>
                        <?php foreach ($registryTagLabels as $tl): ?>
                            <?php if (is_string($tl) && $tl !== ''): ?>
                            <span class="inline-flex items-center rounded-full bg-indigo-50 px-3 py-1 text-[10px] font-black uppercase tracking-[0.18em] text-indigo-900"><?= htmlspecialchars($tl) ?></span>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>

                    <?php if ($excerpt !== ''): ?>
                    <p class="mt-4 text-sm leading-6 text-slate-600">
                        <?= nl2br(htmlspecialchars($excerpt)) ?>
                    </p>
                    <?php else: ?>
                    <p class="mt-4 text-sm leading-6 text-slate-600">
                        La communauté peut encore enrichir son texte d’accroche depuis l’espace d’administration (menu <strong class="font-semibold text-slate-800">Fiche registre &amp; contact</strong>). En attendant, ouvrez la fiche publique pour le recrutement et le forum.
                    </p>
                    <?php endif; ?>

                    <div class="mt-5 grid gap-3 sm:grid-cols-3">
                        <div class="rounded-2xl bg-slate-50 px-4 py-3">
                            <p class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-400">Accès</p>
                            <p class="mt-1 text-sm font-semibold text-slate-900"><?= $locked ? 'Sur invitation / admin' : 'Public + code' ?></p>
                        </div>
                        <div class="rounded-2xl bg-slate-50 px-4 py-3">
                            <p class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-400">Recrutement</p>
                            <p class="mt-1 text-sm font-semibold text-slate-900"><?= $locked ? 'Fermé' : 'Ouvert' ?></p>
                        </div>
                        <div class="rounded-2xl bg-slate-50 px-4 py-3 min-w-0">
                            <p class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-400"><?= $code !== '' ? 'Code d’accès' : 'Lien' ?></p>
                            <p class="mt-1 text-sm font-semibold text-slate-900 break-words"><?= $code !== '' ? htmlspecialchars($code) : 'Fiche publique ci-dessous' ?></p>
                        </div>
                    </div>

                    <?php if ($code !== ''): ?>
                    <div class="mt-5 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-4">
                        <p class="text-[10px] font-black uppercase tracking-[0.18em] text-emerald-700">Code communauté</p>
                        <div class="mt-2 flex flex-wrap items-center gap-3">
                            <code class="rounded-xl bg-white px-3 py-2 font-mono text-sm font-bold text-emerald-900 ring-1 ring-emerald-200">
                                <?= htmlspecialchars($code) ?>
                            </code>
                            <button type="button" data-registry-copy="<?= htmlspecialchars($code, ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center rounded-xl border border-emerald-300 bg-white px-3 py-2 text-xs font-bold uppercase tracking-wider text-emerald-800 transition hover:bg-emerald-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500" aria-label="Copier le code communauté">
                                <span data-registry-copy-label>Copier</span>
                            </button>
                            <span class="text-xs text-slate-600 max-w-md">
                                Saisissez ce code sur la page rejoindre pour être orienté vers cette communauté.
                            </span>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="mt-6 flex flex-wrap gap-3">
                        <a href="<?= htmlspecialchars($publicUrl) ?>"
                           class="inline-flex items-center rounded-2xl bg-slate-950 px-5 py-3 text-[11px] font-black uppercase tracking-[0.18em] text-white transition hover:bg-emerald-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 focus-visible:ring-offset-2">
                            Ouvrir la fiche publique
                            <span class="ml-2 transition-transform group-hover:translate-x-0.5" aria-hidden="true">→</span>
                        </a>

                        <?php if ($joinDirect !== null): ?>
                        <a href="<?= htmlspecialchars($joinDirect) ?>"
                           class="inline-flex items-center rounded-2xl border border-slate-200 bg-white px-5 py-3 text-[11px] font-black uppercase tracking-[0.18em] text-slate-700 transition hover:border-slate-300 hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-400 focus-visible:ring-offset-2">
                            Rejoindre directement
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>

        <!-- Aide responsables (sans jargon technique) -->
        <section class="mt-10 rounded-[1.75rem] border border-dashed border-slate-300 bg-white/70 px-6 py-8 text-center">
            <p class="text-[11px] font-black uppercase tracking-[0.22em] text-slate-400">Pour les équipes qui gèrent une communauté</p>
            <h3 class="mt-2 text-xl font-black tracking-tight text-slate-950">Rendre votre carte plus claire et plus accueillante</h3>
            <p class="mt-3 max-w-2xl mx-auto text-sm leading-6 text-slate-600">
                Connectez-vous au <strong class="font-semibold text-slate-800">back-office de votre communauté</strong>, puis ouvrez <strong class="font-semibold text-slate-800">Fiche registre &amp; contact</strong>.
                Vous pouvez y rédiger un court texte de présentation, indiquer le <strong class="font-semibold text-slate-800">jeu</strong> auquel vous jouez,
                choisir des <strong class="font-semibold text-slate-800">pastilles</strong> qui décrivent votre style et vos thèmes,
                et décider si votre unité doit <strong class="font-semibold text-slate-800">apparaître dans cette liste</strong>.
            </p>
            <p class="mt-4 max-w-2xl mx-auto text-sm leading-6 text-slate-600">
                Une <strong class="font-semibold text-slate-800">grande image d’en-tête</strong> (paysage) peut remplacer le fond coloré&nbsp;: transmettez votre visuel à la personne qui gère l’hébergement du site, ou suivez l’indication affichée dans la même fiche registre — le portail saura alors l’afficher automatiquement sur votre carte.
            </p>
        </section>
    </div>
</div>
