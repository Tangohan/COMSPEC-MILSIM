<?php
declare(strict_types=1);

$orphanIds = is_array($orphanIds ?? null) ? $orphanIds : [];
$orphanReports = is_array($orphanReports ?? null) ? $orphanReports : [];
$selectedTenantId = (int) ($selectedTenantId ?? 0);
$inspect = is_array($inspect ?? null) ? $inspect : null;
$form = is_array($form ?? null) ? $form : [];
$tenantTypes = is_array($tenantTypes ?? null) ? $tenantTypes : [];
$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$ok = \App\Core\Session::getFlash('success');
$err = \App\Core\Session::getFlash('error');
$recoveryUrl = url('admin/system/tenant-recovery');
?>
<div class="pa">
    <div class="pa__frame">
        <header class="pa-hero">
            <p class="pa-crumb">
                <a href="<?= $h(url('admin')) ?>">Administration du site</a>
                <span aria-hidden="true"> / </span>
                <a href="<?= $h(url('admin/tenants')) ?>">Communautés</a>
                <span aria-hidden="true"> / </span>
                Récupération
            </p>
            <h1 class="pa-hero__title">Récupération de communauté orpheline</h1>
            <p class="pa-hero__lead">
                Quand la ligne <code>tenants</code> a disparu mais que les données (<code>users</code>, <code>roles</code>, etc.)
                existent encore, recréez la fiche communauté pour rebrancher l’organisation sans restore complet.
            </p>
            <div class="pa-hero__actions">
                <a class="pa-btn pa-btn--ghost" href="<?= $h(url('admin/tenants')) ?>">Annuaire des communautés</a>
            </div>
        </header>

        <div class="pa-panel">
            <?php if ($ok): ?><p class="pa-flash pa-flash--ok"><?= $h((string) $ok) ?></p><?php endif; ?>
            <?php if ($err): ?><p class="pa-flash pa-flash--err"><?= $h((string) $err) ?></p><?php endif; ?>

            <?php if ($orphanIds === []): ?>
                <p class="text-sm text-slate-700">
                    Aucune communauté orpheline détectée pour l’instant. Les identifiants référencés dans les tables métier
                    correspondent tous à une ligne <code>tenants</code> existante.
                </p>
            <?php else: ?>
                <section class="mb-8">
                    <h2 class="text-lg font-black text-slate-900">Communautés orphelines détectées</h2>
                    <p class="mt-1 text-sm text-slate-600">Données présentes sans fiche <code>tenants</code>.</p>
                    <div class="pa-table-wrap mt-4">
                        <table class="pa-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Users</th>
                                    <th>Rôles</th>
                                    <th>Appartenances</th>
                                    <th>Documents</th>
                                    <th>Total lignes sondées</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($orphanReports as $report): ?>
                                    <?php
                                    $id = (int) ($report['tenant_id'] ?? 0);
                                    $counts = is_array($report['table_counts'] ?? null) ? $report['table_counts'] : [];
                                    ?>
                                    <tr>
                                        <td class="font-mono font-bold">#<?= $id ?></td>
                                        <td><?= (int) ($counts['users'] ?? 0) ?></td>
                                        <td><?= (int) ($counts['roles'] ?? 0) ?></td>
                                        <td><?= (int) ($counts['user_community_memberships'] ?? 0) ?></td>
                                        <td><?= (int) ($counts['documents'] ?? 0) ?></td>
                                        <td><?= (int) ($report['total_rows'] ?? 0) ?></td>
                                        <td style="text-align:right;">
                                            <a class="pa-btn pa-btn--ghost pa-btn--sm" href="<?= $h($recoveryUrl . '?tenant_id=' . $id) ?>">Récupérer</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            <?php endif; ?>

            <?php if ($selectedTenantId > 1): ?>
                <section class="mb-8 rounded-xl border border-amber-200 bg-amber-50 p-5">
                    <h2 class="text-lg font-black text-amber-950">Diagnostic — communauté #<?= $selectedTenantId ?></h2>
                    <?php if ($inspect !== null): ?>
                        <?php $hints = is_array($inspect['identity_hints'] ?? null) ? $inspect['identity_hints'] : []; ?>
                        <?php if ($hints !== []): ?>
                            <p class="mt-2 text-sm text-amber-900">
                                Indices trouvés dans le journal d’audit
                                <?php if (!empty($hints['audit_identity_at'])): ?>
                                    (identité : <?= $h((string) $hints['audit_identity_at']) ?>)
                                <?php endif; ?>.
                            </p>
                        <?php else: ?>
                            <p class="mt-2 text-sm text-amber-900">Aucun indice d’identité dans l’audit — utilisez un dump SQL ou saisissez manuellement.</p>
                        <?php endif; ?>
                    <?php endif; ?>
                </section>

                <section class="mb-8 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h2 class="text-lg font-black text-slate-900">1. Importer depuis un dump SQL</h2>
                    <p class="mt-1 text-sm text-slate-600">
                        Téléversez votre ancienne base (<code>.sql</code>) ou collez la ligne
                        <code>INSERT INTO tenants ...</code>. Seule la ligne correspondant à l’identifiant #<?= $selectedTenantId ?> est extraite.
                    </p>
                    <form class="mt-4 space-y-4" action="<?= $h(url('admin/system/tenant-recovery/parse-dump')) ?>" method="post" enctype="multipart/form-data">
                        <?= \App\Core\Csrf::field() ?>
                        <input type="hidden" name="tenant_id" value="<?= $selectedTenantId ?>">
                        <div>
                            <label class="block text-sm font-semibold text-slate-700" for="sql_file">Fichier dump (.sql, max 8 Mo)</label>
                            <input id="sql_file" type="file" name="sql_file" accept=".sql,.txt,text/plain" class="mt-1 block w-full text-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-slate-700" for="sql_paste">Ou coller un extrait SQL</label>
                            <textarea id="sql_paste" name="sql_paste" rows="5" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 font-mono text-xs" placeholder="INSERT INTO `tenants` ..."></textarea>
                        </div>
                        <button type="submit" class="pa-btn pa-btn--solid">Extraire la ligne du dump</button>
                    </form>
                </section>

                <section class="rounded-xl border border-emerald-200 bg-emerald-50/40 p-5 shadow-sm">
                    <h2 class="text-lg font-black text-slate-900">2. Recréer la fiche communauté</h2>
                    <p class="mt-1 text-sm text-slate-600">
                        Vérifiez chaque champ. L’identifiant doit rester <strong>#<?= $selectedTenantId ?></strong> pour rebrancher les données existantes.
                    </p>
                    <form class="mt-4 grid gap-4 md:grid-cols-2" action="<?= $h(url('admin/system/tenant-recovery/restore')) ?>" method="post">
                        <?= \App\Core\Csrf::field() ?>
                        <input type="hidden" name="tenant_id" value="<?= $selectedTenantId ?>">

                        <div>
                            <label class="block text-sm font-semibold" for="name">Nom</label>
                            <input id="name" name="name" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2" value="<?= $h((string) ($form['name'] ?? '')) ?>">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold" for="slug">Slug (URL publique)</label>
                            <input id="slug" name="slug" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 font-mono" value="<?= $h((string) ($form['slug'] ?? '')) ?>">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold" for="tenant_type">Profil</label>
                            <select id="tenant_type" name="tenant_type" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">
                                <?php foreach ($tenantTypes as $value => $label): ?>
                                    <option value="<?= $h((string) $value) ?>" <?= ((string) ($form['tenant_type'] ?? 'full') === (string) $value) ? 'selected' : '' ?>><?= $h((string) $label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold" for="plan_slug">Formule</label>
                            <input id="plan_slug" name="plan_slug" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2" value="<?= $h((string) ($form['plan_slug'] ?? 'free')) ?>">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold" for="community_code">Code communauté</label>
                            <input id="community_code" name="community_code" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 font-mono uppercase" value="<?= $h((string) ($form['community_code'] ?? '')) ?>">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold" for="owner_user_id">Propriétaire (user id)</label>
                            <input id="owner_user_id" name="owner_user_id" type="number" min="1" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2" value="<?= $h((string) ($form['owner_user_id'] ?? '')) ?>">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold" for="subscription_status">Statut abonnement</label>
                            <input id="subscription_status" name="subscription_status" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2" value="<?= $h((string) ($form['subscription_status'] ?? 'none')) ?>">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold" for="logo_url">Logo URL</label>
                            <input id="logo_url" name="logo_url" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2" value="<?= $h((string) ($form['logo_url'] ?? '')) ?>">
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-semibold" for="settings">Settings (JSON)</label>
                            <textarea id="settings" name="settings" rows="4" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 font-mono text-xs"><?= $h((string) ($form['settings'] ?? '')) ?></textarea>
                        </div>

                        <div class="md:col-span-2 rounded-lg border border-red-200 bg-red-50 p-4">
                            <p class="text-sm font-semibold text-red-900">Confirmation obligatoire</p>
                            <label class="mt-2 flex items-start gap-2 text-sm text-red-900">
                                <input type="checkbox" name="confirm_understand" value="1" class="mt-1">
                                <span>Je comprends que cette opération recrée la fiche <code>tenants</code> pour reconnecter les données orphelines existantes.</span>
                            </label>
                            <label class="mt-3 block text-sm font-semibold text-red-900" for="confirm_tenant_id">
                                Retapez l’identifiant <?= $selectedTenantId ?> pour confirmer
                            </label>
                            <input id="confirm_tenant_id" name="confirm_tenant_id" type="number" min="2" required class="mt-1 w-40 rounded-lg border border-red-300 px-3 py-2 font-mono">
                        </div>

                        <div class="md:col-span-2">
                            <button type="submit" class="pa-btn pa-btn--solid">Recréer la communauté #<?= $selectedTenantId ?></button>
                        </div>
                    </form>
                </section>
            <?php elseif ($orphanIds !== []): ?>
                <p class="text-sm text-slate-600">Sélectionnez une communauté orpheline dans le tableau ci-dessus.</p>
            <?php endif; ?>
        </div>
    </div>
</div>
