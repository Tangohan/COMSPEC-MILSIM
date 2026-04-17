<?php
declare(strict_types=1);

/** @var string $query */
/** @var list<array<string,mixed>> $results */

$query = trim((string) ($query ?? ''));
$results = is_array($results ?? null) ? $results : [];
?>
<section class="mx-auto max-w-7xl px-6 py-8 md:px-8 md:py-10">
    <header class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm md:p-8">
        <p class="text-[11px] font-black uppercase tracking-[0.3em] text-slate-500">Athena</p>
        <h1 class="mt-2 text-3xl font-black tracking-tight text-slate-900">Annuaire du site</h1>
        <p class="mt-3 max-w-3xl text-sm text-slate-600">Recherchez un profil par nom, callsign, slug ou identifiant système Athena (9 caractères).</p>

        <form method="get" action="<?= htmlspecialchars(url('personnel'), ENT_QUOTES, 'UTF-8') ?>" class="mt-5 flex flex-col gap-3 sm:flex-row">
            <label class="sr-only" for="personnel-directory-q">Recherche annuaire</label>
            <input id="personnel-directory-q" name="q" type="search" value="<?= htmlspecialchars($query, ENT_QUOTES, 'UTF-8') ?>" placeholder="Nom, callsign, ATHENA ID…" class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-900 outline-none focus:border-emerald-400 focus:bg-white focus:ring-4 focus:ring-emerald-100">
            <button type="submit" class="inline-flex items-center justify-center rounded-2xl bg-slate-900 px-5 py-3 text-sm font-bold text-white hover:bg-emerald-700">Rechercher</button>
            <?php if ($query !== ''): ?>
            <a href="<?= htmlspecialchars(url('personnel'), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center justify-center rounded-2xl border border-slate-200 bg-white px-5 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50">Réinitialiser</a>
            <?php endif; ?>
        </form>
    </header>

    <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        <?php foreach ($results as $row):
            $uid = (int) ($row['id'] ?? 0);
            if ($uid < 1) {
                continue;
            }
            $displayName = trim((string) ($row['display_name'] ?? 'Profil sans nom'));
            $callsign = trim((string) ($row['callsign'] ?? ''));
            $athenaId = trim((string) ($row['athena_identifier'] ?? ''));
            $slug = trim((string) ($row['profile_slug'] ?? ''));
            $character = trim((string) ($row['character_name'] ?? ''));
            $avatar = trim((string) ($row['avatar_url'] ?? ''));
            $target = $slug !== '' ? $slug : (string) $uid;
        ?>
        <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="flex items-start gap-3">
                <div class="h-12 w-12 shrink-0 overflow-hidden rounded-xl border border-slate-200 bg-slate-100">
                    <?php if ($avatar !== ''): ?>
                    <img src="<?= htmlspecialchars($avatar, ENT_QUOTES, 'UTF-8') ?>" alt="Avatar" class="h-full w-full object-cover" loading="lazy" decoding="async">
                    <?php else: ?>
                    <div class="flex h-full w-full items-center justify-center text-slate-400">•</div>
                    <?php endif; ?>
                </div>
                <div class="min-w-0">
                    <h2 class="truncate text-base font-black text-slate-900"><?= htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') ?></h2>
                    <?php if ($character !== ''): ?>
                    <p class="truncate text-xs text-slate-500">RP: <?= htmlspecialchars($character, ENT_QUOTES, 'UTF-8') ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <dl class="mt-4 space-y-1 text-xs text-slate-600">
                <?php if ($callsign !== ''): ?><div><dt class="inline font-semibold text-slate-700">Callsign:</dt> <dd class="inline"><?= htmlspecialchars($callsign, ENT_QUOTES, 'UTF-8') ?></dd></div><?php endif; ?>
                <div><dt class="inline font-semibold text-slate-700">Athena ID:</dt> <dd class="inline"><?= $athenaId !== '' ? htmlspecialchars($athenaId, ENT_QUOTES, 'UTF-8') : '—' ?></dd></div>
                <?php if ($slug !== ''): ?><div><dt class="inline font-semibold text-slate-700">Slug:</dt> <dd class="inline"><?= htmlspecialchars($slug, ENT_QUOTES, 'UTF-8') ?></dd></div><?php endif; ?>
            </dl>

            <a href="<?= htmlspecialchars(url('personnel/' . rawurlencode($target)), ENT_QUOTES, 'UTF-8') ?>" class="mt-4 inline-flex rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs font-bold text-emerald-900 hover:bg-emerald-100">Ouvrir le profil</a>
        </article>
        <?php endforeach; ?>
    </div>

    <?php if ($results === []): ?>
    <p class="mt-6 rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-4 py-5 text-sm text-slate-500">Aucun profil trouvé pour cette recherche.</p>
    <?php endif; ?>
</section>
