<?php
$terrain_modpack = $terrain_modpack ?? null;
$terrain_atak_url = $terrain_atak_url ?? null;
$terrain_pin_links = $terrain_pin_links ?? [];
?>
<h1 class="text-xl font-black uppercase italic tracking-tight text-white">Avant l’opération</h1>
<p class="mt-2 text-sm text-slate-400">Raccourcis et rappels. Pour l’administration complète, utilisez le portail depuis un ordinateur.</p>

<section class="mt-8 rounded-2xl border border-white/10 bg-white/5 p-4">
    <h2 class="text-[10px] font-black uppercase tracking-widest text-emerald-400">Checklist rapide</h2>
    <ul class="mt-3 space-y-2 text-sm text-slate-200">
        <li class="flex gap-2"><span class="text-emerald-500">✓</span> Modpack à jour et testé</li>
        <li class="flex gap-2"><span class="text-emerald-500">✓</span> Briefing / consignes lus</li>
        <li class="flex gap-2"><span class="text-emerald-500">✓</span> Radio & fréquences validées</li>
        <li class="flex gap-2"><span class="text-emerald-500">✓</span> Formations obligatoires à jour</li>
    </ul>
</section>

<section class="mt-6 space-y-3">
    <h2 class="text-[10px] font-black uppercase tracking-widest text-slate-500">Liens</h2>
    <a href="<?= htmlspecialchars(url('evenements'), ENT_QUOTES, 'UTF-8') ?>" class="block rounded-xl border border-white/10 bg-slate-900 px-4 py-3 text-sm font-bold text-white hover:border-emerald-500/40">Calendrier des opérations</a>
    <a href="<?= htmlspecialchars(url('formations/mes-formations'), ENT_QUOTES, 'UTF-8') ?>" class="block rounded-xl border border-white/10 bg-slate-900 px-4 py-3 text-sm font-bold text-white hover:border-emerald-500/40">Mes formations</a>
    <a href="<?= htmlspecialchars(url('forum'), ENT_QUOTES, 'UTF-8') ?>" class="block rounded-xl border border-white/10 bg-slate-900 px-4 py-3 text-sm font-bold text-white hover:border-emerald-500/40">Forum — briefings</a>
    <a href="<?= htmlspecialchars(url('modpacks'), ENT_QUOTES, 'UTF-8') ?>" class="block rounded-xl border border-white/10 bg-slate-900 px-4 py-3 text-sm font-bold text-white hover:border-emerald-500/40">Modpacks</a>
    <?php if (!empty($terrain_atak_url)): ?>
    <a href="<?= htmlspecialchars((string) $terrain_atak_url, ENT_QUOTES, 'UTF-8') ?>" class="block rounded-xl border border-emerald-500/30 bg-emerald-950/40 px-4 py-3 text-sm font-bold text-emerald-200">Module ATAK</a>
    <?php endif; ?>
</section>

<?php if ($terrain_modpack && !empty($terrain_modpack['slug'])): ?>
<section class="mt-6 rounded-2xl border border-white/10 bg-white/5 p-4">
    <h2 class="text-[10px] font-black uppercase tracking-widest text-slate-500">Modpack principal</h2>
    <p class="mt-2 text-sm font-bold text-white"><?= htmlspecialchars((string) ($terrain_modpack['name'] ?? 'Modpack'), ENT_QUOTES, 'UTF-8') ?></p>
    <a href="<?= htmlspecialchars(url('modpacks/' . rawurlencode((string) $terrain_modpack['slug'])), ENT_QUOTES, 'UTF-8') ?>" class="mt-3 inline-block text-xs font-bold uppercase tracking-wider text-emerald-400">Ouvrir la fiche</a>
</section>
<?php endif; ?>

<?php if ($terrain_pin_links !== []): ?>
<section class="mt-6">
    <h2 class="text-[10px] font-black uppercase tracking-widest text-slate-500 mb-3">Raccourcis communauté</h2>
    <div class="space-y-2">
        <?php foreach ($terrain_pin_links as $p): ?>
            <?php if (!empty($p['href'])): ?>
            <a href="<?= htmlspecialchars((string) $p['href'], ENT_QUOTES, 'UTF-8') ?>" class="block truncate rounded-lg border border-white/10 bg-slate-900/80 px-3 py-2 text-sm text-slate-200 hover:border-emerald-500/30"><?= htmlspecialchars((string) ($p['label'] ?? 'Lien'), ENT_QUOTES, 'UTF-8') ?></a>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>
