<?php
declare(strict_types=1);

$presetMeta = $presetMeta ?? [];
$roles = $roles ?? [];

$err = \App\Core\Session::getFlash('error');
$ok = \App\Core\Session::getFlash('success');
?>
<div class="max-w-4xl mx-auto px-4 sm:px-6 py-10 lg:py-12">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between mb-8">
        <div>
            <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-500 mb-2">Rôles communauté</p>
            <h1 class="text-2xl font-black text-slate-900 tracking-tight">Profils de permissions</h1>
            <p class="mt-2 text-slate-600 text-sm leading-relaxed max-w-2xl">
                Appliquez <strong>en une fois</strong> un jeu complet de droits à un rôle (gouvernance ou opérationnel).
                Les profils ne contiennent jamais les droits réservés à la <strong>plateforme</strong> (administration système, modération forum « site » étendue).
            </p>
        </div>
        <a href="<?= url('back-office/roles') ?>" class="shrink-0 text-sm font-semibold text-slate-600 hover:text-slate-900">← Liste des rôles</a>
    </div>

    <?php if ($err): ?>
        <p class="mb-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900"><?= htmlspecialchars($err, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>
    <?php if ($ok): ?>
        <p class="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900"><?= htmlspecialchars($ok, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>

    <div class="rounded-2xl border border-amber-200 bg-amber-50/80 p-5 mb-8">
        <h2 class="text-sm font-bold text-amber-950">Toujours exclus des profils automatiques</h2>
        <p class="mt-1 text-sm text-amber-950/90">Les profils ci-dessous ne donnent jamais les habilitations réservées à l’administration de l’ensemble du site, par exemple :</p>
        <ul class="mt-2 text-sm text-amber-950 list-disc list-inside space-y-1">
            <li>Paramètres et maintenance de la plateforme pour tous les espaces communautaires.</li>
            <li>Modération forum au niveau global (au-delà de votre communauté).</li>
        </ul>
    </div>

    <?php if (empty($roles)): ?>
        <p class="text-slate-500">Aucun rôle communauté ou opérationnel. Créez d’abord des rôles (ou exécutez les migrations).</p>
    <?php else: ?>
        <form method="post" action="<?= url('back-office/roles/presets/apply') ?>" class="space-y-8">
            <?= \App\Core\Csrf::field() ?>

            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <label for="role_id" class="block text-sm font-bold text-slate-800 mb-2">Rôle à configurer</label>
                <select name="role_id" id="role_id" required class="w-full max-w-lg rounded-xl border border-slate-300 px-4 py-2.5 text-sm text-slate-900 focus:border-blue-500 focus:ring-2 focus:ring-blue-100">
                    <option value="">— Choisir —</option>
                    <?php foreach ($roles as $r):
                        $rid = (int) $r['id'];
                        $layer = (string) ($r['role_layer'] ?? '');
                        $layerFr = $layer === 'intra' ? 'Opérationnel' : 'Communauté';
                        $locked = !empty($r['is_locked']);
                        ?>
                        <option value="<?= $rid ?>" <?= $locked ? 'disabled' : '' ?>><?= htmlspecialchars($r['name'] ?? '', ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars($layerFr, ENT_QUOTES, 'UTF-8') ?>)<?= $locked ? ' — verrouillé' : '' ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="mt-2 text-xs text-slate-500">Les rôles verrouillés ne peuvent pas être modifiés ici.</p>
            </div>

            <div class="space-y-4">
                <h2 class="text-sm font-bold uppercase tracking-wider text-slate-500">Choisir un profil</h2>
                <?php foreach ($presetMeta as $meta):
                    $pid = (string) ($meta['id'] ?? '');
                    if ($pid === '') {
                        continue;
                    }
                    ?>
                    <label class="flex cursor-pointer gap-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-blue-300 hover:bg-blue-50/20 has-[:checked]:border-blue-500 has-[:checked]:ring-2 has-[:checked]:ring-blue-100">
                        <input type="radio" name="preset_id" value="<?= htmlspecialchars($pid, ENT_QUOTES, 'UTF-8') ?>" required class="mt-1 h-4 w-4 text-blue-600 border-slate-300 focus:ring-blue-500">
                        <span class="min-w-0 flex-1">
                            <span class="block font-bold text-slate-900"><?= htmlspecialchars((string) ($meta['label'] ?? $pid), ENT_QUOTES, 'UTF-8') ?></span>
                            <span class="mt-1 block text-sm text-slate-600 leading-relaxed"><?= htmlspecialchars((string) ($meta['description'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                        </span>
                    </label>
                <?php endforeach; ?>
            </div>

            <div class="rounded-2xl border border-rose-200 bg-rose-50/60 p-5">
                <p class="text-sm font-semibold text-rose-950">Attention</p>
                <p class="mt-1 text-sm text-rose-900/90">L’application d’un profil <strong>remplace toutes les permissions</strong> actuelles du rôle par celles du profil (aucune fusion). Vérifiez ensuite la fiche du rôle.</p>
            </div>

            <div class="flex flex-wrap gap-3">
                <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-slate-900 px-6 py-3 text-sm font-bold text-white shadow-md hover:bg-slate-800">
                    Appliquer le profil au rôle
                </button>
                <a href="<?= url('back-office/roles') ?>" class="inline-flex items-center justify-center rounded-xl border border-slate-300 bg-white px-6 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50">Annuler</a>
            </div>
        </form>
    <?php endif; ?>
</div>
