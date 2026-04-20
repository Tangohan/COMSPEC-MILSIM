<?php
$roles = $roles ?? [];
$permissions = $permissions ?? [];
$rules = $rules ?? [];
$logs = $logs ?? [];
$users = $users ?? [];
$activeTab = $activeTab ?? 'roles';
?>
<div class="mx-auto max-w-7xl space-y-6">
    <header class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <h1 class="text-2xl font-black text-slate-900">Gestion des accès (RBAC + ABAC)</h1>
        <p class="mt-2 text-sm text-slate-600">Pilotage multi-tenant des rôles, permissions, règles conditionnelles et simulation d’accès.</p>
        <div class="mt-4 flex flex-wrap gap-2 text-xs font-semibold">
            <?php foreach (['roles' => 'Rôles', 'rules' => 'Règles d’accès', 'matrix' => 'Matrice visuelle', 'simulation' => 'Simulation'] as $key => $label): ?>
                <a href="<?= url('back-office/access-management?tab=' . $key) ?>" class="rounded-full px-3 py-1 <?= $activeTab === $key ? 'bg-emerald-600 text-white' : 'bg-slate-100 text-slate-700' ?>"><?= htmlspecialchars($label) ?></a>
            <?php endforeach; ?>
        </div>
    </header>

    <?php if ($activeTab === 'roles'): ?>
    <section class="grid gap-4 lg:grid-cols-2">
        <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-lg font-bold text-slate-900">Créer / éditer rôle</h2>
            <form method="post" action="<?= url('back-office/access-management/roles/save') ?>" class="mt-4 space-y-3">
                <?= csrf_field() ?>
                <input name="name" class="w-full rounded border border-slate-300 px-3 py-2" placeholder="Nom" required>
                <input name="slug" class="w-full rounded border border-slate-300 px-3 py-2" placeholder="Slug" required>
                <input name="level" type="number" class="w-full rounded border border-slate-300 px-3 py-2" placeholder="Niveau hiérarchique">
                <button class="rounded bg-emerald-700 px-4 py-2 text-sm font-bold text-white">Enregistrer</button>
            </form>
        </article>
        <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-lg font-bold text-slate-900">Matrice permissions</h2>
            <div class="mt-3 max-h-96 overflow-auto text-sm">
                <?php foreach ($roles as $role): ?>
                    <div class="mb-3 rounded border border-slate-100 p-3">
                        <div class="font-semibold text-slate-900"><?= htmlspecialchars((string) $role['name']) ?> <span class="rounded bg-slate-100 px-2 py-0.5 text-xs text-slate-600"><?= htmlspecialchars((string) $role['slug']) ?></span></div>
                        <div class="mt-2 flex flex-wrap gap-1">
                            <?php foreach ($permissions as $p): ?>
                                <span class="rounded bg-slate-50 px-2 py-0.5 text-xs text-slate-600"><?= htmlspecialchars((string) $p['code']) ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </article>
    </section>
    <?php endif; ?>

    <?php if ($activeTab === 'rules'): ?>
    <section class="grid gap-4 lg:grid-cols-2">
        <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-lg font-bold text-slate-900">Builder “Si → Alors”</h2>
            <form method="post" action="<?= url('back-office/access-management/rules/save') ?>" class="mt-4 grid gap-3">
                <?= csrf_field() ?>
                <input name="name" class="rounded border border-slate-300 px-3 py-2" placeholder="Nom de règle" required>
                <textarea name="description" class="rounded border border-slate-300 px-3 py-2" placeholder="Description"></textarea>
                <div class="grid grid-cols-2 gap-2">
                    <select name="target_type" class="rounded border border-slate-300 px-3 py-2"><option>ROLE</option><option>USER</option></select>
                    <input name="target_id" type="number" class="rounded border border-slate-300 px-3 py-2" placeholder="ID cible" required>
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <select name="condition_type" class="rounded border border-slate-300 px-3 py-2"><option>DAYS_SINCE_CREATION</option><option>MODULE_VALIDATED</option><option>UNIT</option><option>MANUAL_APPROVAL</option><option>STATUS</option></select>
                    <select name="effect" class="rounded border border-slate-300 px-3 py-2"><option>ALLOW</option><option>DENY</option></select>
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <input name="scope_identifier" class="rounded border border-slate-300 px-3 py-2" placeholder="Ressource (module/page/doc)">
                    <select name="action" class="rounded border border-slate-300 px-3 py-2"><option>READ</option><option>CREATE</option><option>UPDATE</option><option>DELETE</option><option>EXPORT</option></select>
                </div>
                <input name="priority" type="number" class="rounded border border-slate-300 px-3 py-2" value="100">
                <label class="inline-flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" checked> Active</label>
                <button class="rounded bg-emerald-700 px-4 py-2 text-sm font-bold text-white">Créer la règle</button>
            </form>
        </article>
        <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-lg font-bold text-slate-900">Règles actives</h2>
            <ul class="mt-3 space-y-2 text-sm">
                <?php foreach ($rules as $r): ?>
                    <li class="rounded border border-slate-100 p-3"><span class="font-semibold"><?= htmlspecialchars((string) $r['name']) ?></span> — <span class="rounded bg-slate-100 px-2 py-0.5 text-xs"><?= htmlspecialchars((string) $r['effect']) ?></span> <span class="text-xs text-slate-500">prio <?= (int) $r['priority'] ?></span></li>
                <?php endforeach; ?>
            </ul>
        </article>
    </section>
    <?php endif; ?>

    <?php if ($activeTab === 'matrix'): ?>
    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 class="text-lg font-bold text-slate-900">Matrice ressource × action</h2>
        <table class="mt-4 min-w-full text-sm">
            <thead><tr class="text-left text-slate-500"><th class="py-2">Ressource</th><th>Lire</th><th>Créer</th><th>Modifier</th><th>Supprimer</th><th>Exporter</th></tr></thead>
            <tbody>
            <?php foreach (['documents','courrier','training','admin'] as $resource): ?>
            <tr class="border-t border-slate-100"><td class="py-2 font-semibold"><?= htmlspecialchars($resource) ?></td><td><span class="rounded bg-emerald-100 px-2 py-0.5 text-emerald-700">ALLOW</span></td><td><span class="rounded bg-amber-100 px-2 py-0.5 text-amber-700">RULE</span></td><td><span class="rounded bg-amber-100 px-2 py-0.5 text-amber-700">RULE</span></td><td><span class="rounded bg-rose-100 px-2 py-0.5 text-rose-700">DENY</span></td><td><span class="rounded bg-slate-100 px-2 py-0.5 text-slate-700">N/A</span></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </section>
    <?php endif; ?>

    <?php if ($activeTab === 'simulation'): ?>
    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 class="text-lg font-bold text-slate-900">Simulation d’accès</h2>
        <form id="sim-form" class="mt-4 grid gap-3 md:grid-cols-4">
            <select name="user_id" class="rounded border border-slate-300 px-3 py-2"><?php foreach ($users as $u): ?>
                <?php
                $dn = trim((string) ($u['display_name'] ?? ''));
                $cs = trim((string) ($u['callsign'] ?? ''));
                $em = trim((string) ($u['email'] ?? ''));
                $uLabel = $dn !== '' ? $dn : ($cs !== '' ? $cs : $em);
                ?>
                <option value="<?= (int) $u['id'] ?>"><?= htmlspecialchars($uLabel !== '' ? $uLabel : ('#' . (int) $u['id']), ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?></select>
            <input name="resource" value="documents" class="rounded border border-slate-300 px-3 py-2">
            <select name="action" class="rounded border border-slate-300 px-3 py-2"><option>READ</option><option>CREATE</option><option>UPDATE</option><option>DELETE</option><option>EXPORT</option></select>
            <button class="rounded bg-slate-900 px-4 py-2 text-sm font-bold text-white">Simuler</button>
        </form>
        <pre id="sim-result" class="mt-4 rounded bg-slate-900 p-3 text-xs text-emerald-200">Sélectionnez un utilisateur et lancez la simulation.</pre>
    </section>
    <script>
    document.getElementById('sim-form')?.addEventListener('submit', async function (e) {
      e.preventDefault();
      const fd = new FormData(e.currentTarget);
      const q = new URLSearchParams(fd).toString();
      const res = await fetch('<?= url('back-office/access-management/simulate') ?>?' + q, {credentials:'same-origin'});
      document.getElementById('sim-result').textContent = JSON.stringify(await res.json(), null, 2);
    });
    </script>
    <?php endif; ?>

    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 class="text-lg font-bold text-slate-900">Journal sécurité (50 derniers)</h2>
        <ul class="mt-3 space-y-2 text-xs">
            <?php foreach ($logs as $log): ?>
                <li class="rounded border border-slate-100 p-2"><span class="font-semibold"><?= htmlspecialchars((string) $log['decision']) ?></span> <?= htmlspecialchars((string) $log['resource']) ?>::<?= htmlspecialchars((string) $log['action']) ?> — <?= htmlspecialchars((string) $log['reason']) ?></li>
            <?php endforeach; ?>
        </ul>
    </section>
</div>
