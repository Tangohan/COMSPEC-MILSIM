<?php
/** @var array $tenant */
/** @var array $settings */
$tz = $settings['timezone'] ?? 'Europe/Paris';
$zones = \DateTimeZone::listIdentifiers();
if (!in_array($tz, $zones, true)) {
    $zones[] = $tz;
    sort($zones);
}
?>
<div class="max-w-lg mx-auto px-4 py-10">
    <h1 class="text-2xl font-black text-white mb-2">Bienvenue sur <?= htmlspecialchars($tenant['name'] ?? '') ?></h1>
    <p class="text-neutral-400 text-sm mb-6">Dernière étape : fuseau horaire de la communauté (affichage des événements et outils).</p>
    <form method="post" action="<?= url('c/' . rawurlencode((string) ($tenant['slug'] ?? '')) . '/setup') ?>" class="space-y-4">
        <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">
        <div>
            <label class="block text-xs font-bold uppercase tracking-widest text-neutral-500 mb-1">Fuseau</label>
            <select name="timezone" class="w-full bg-neutral-900 border border-white/10 rounded px-3 py-2 text-sm text-white">
                <?php foreach ($zones as $z): ?>
                    <option value="<?= htmlspecialchars($z) ?>" <?= $z === $tz ? 'selected' : '' ?>><?= htmlspecialchars($z) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="w-full py-2.5 rounded bg-emerald-600 hover:bg-emerald-500 text-sm font-bold text-white">Terminer et ouvrir le tableau de bord</button>
    </form>
</div>
