<?php
declare(strict_types=1);
$rows = is_array($platformTenants ?? null) ? $platformTenants : [];
$plans = is_array($platformSubscriptionPlans ?? null) ? $platformSubscriptionPlans : [];
$plansError = isset($platformSubscriptionPlansError) ? (string) $platformSubscriptionPlansError : '';
$planNameBySlug = is_array($platformPlanNameBySlug ?? null) ? $platformPlanNameBySlug : [];
$statusLabels = is_array($platformSubscriptionStatusLabels ?? null) ? $platformSubscriptionStatusLabels : [];
?>
<div class="min-h-0 flex-1 bg-slate-50">
    <div class="mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:px-8 space-y-10">
        <nav class="text-sm text-slate-500">
            <a href="<?= htmlspecialchars(url('admin'), ENT_QUOTES, 'UTF-8') ?>" class="font-semibold text-emerald-800 hover:text-emerald-950">Administration plateforme</a>
            <span class="mx-2" aria-hidden="true">/</span>
            <span class="text-slate-800">Annuaire des communautés</span>
        </nav>

        <?php $ok = \App\Core\Session::getFlash('success'); ?>
        <?php if ($ok): ?>
            <p class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-900"><?= htmlspecialchars((string) $ok, ENT_QUOTES, 'UTF-8') ?></p>
        <?php endif; ?>
        <?php $err = \App\Core\Session::getFlash('error'); ?>
        <?php if ($err): ?>
            <p class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-900"><?= htmlspecialchars((string) $err, ENT_QUOTES, 'UTF-8') ?></p>
        <?php endif; ?>

        <header>
            <h1 class="text-2xl font-black text-slate-900">Annuaire des communautés</h1>
            <p class="mt-2 max-w-3xl text-sm text-slate-600 leading-relaxed">
                Vue transverse de toutes les communautés actives sur le site, avec l’effectif des comptes rattachés et la formule d’accès.
                La configuration détaillée (membres, recrutement, unités) reste dans le back-office de chaque communauté.
            </p>
        </header>

        <div class="flex flex-wrap gap-3">
            <a href="<?= htmlspecialchars(url('communities'), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-800 hover:bg-slate-50">Annuaire public</a>
            <a href="<?= htmlspecialchars(url('communities/create'), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm font-semibold text-emerald-950 hover:bg-emerald-100">Créer une communauté</a>
            <a href="<?= htmlspecialchars(url('admin/system/subscription-plans'), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-800 hover:bg-slate-50">Catalogue des formules</a>
            <a href="<?= htmlspecialchars(url('admin/system/deployment'), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex rounded-lg border border-amber-200 bg-amber-50 px-4 py-2 text-sm font-semibold text-amber-950 hover:bg-amber-100">Publications et préqualification</a>
        </div>

        <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold text-slate-700">Communauté</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-600">Formule</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-600">Abonnement</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-600">Création</th>
                        <th class="px-4 py-3 text-right font-semibold text-slate-600">Comptes</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-600">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if ($rows === []): ?>
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-slate-500">Aucune communauté enregistrée (hors espace technique).</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($rows as $t): ?>
                            <?php
                            $id = (int) ($t['id'] ?? 0);
                            $name = (string) ($t['name'] ?? '');
                            $slug = (string) ($t['slug'] ?? '');
                            $created = $t['created_at'] ?? null;
                            $uc = (int) ($t['user_count'] ?? 0);
                            $planSlug = (string) ($t['plan_slug'] ?? 'free');
                            $planLabel = (string) ($planNameBySlug[$planSlug] ?? $planSlug);
                            $status = (string) ($t['subscription_status'] ?? 'none');
                            $statusLabel = (string) ($statusLabels[$status] ?? 'Statut inconnu');
                            $publicUrl = $slug !== '' ? url('c/' . rawurlencode($slug)) : '';
                            $editUrl = $id > 0 ? url('admin/tenants/' . $id . '/edit') : '';
                            ?>
                            <tr>
                                <td class="px-4 py-3">
                                    <p class="font-semibold text-slate-900"><?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?></p>
                                    <?php if ($publicUrl !== ''): ?>
                                        <a href="<?= htmlspecialchars($publicUrl, ENT_QUOTES, 'UTF-8') ?>" class="mt-1 inline-block text-xs font-semibold text-emerald-800 hover:text-emerald-950" target="_blank" rel="noopener">Page publique</a>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex rounded-md bg-emerald-50 px-2 py-1 text-xs font-semibold text-emerald-950"><?= htmlspecialchars($planLabel, ENT_QUOTES, 'UTF-8') ?></span>
                                </td>
                                <td class="px-4 py-3 text-slate-700 whitespace-nowrap"><?= htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="px-4 py-3 text-slate-600 whitespace-nowrap">
                                    <?= $created !== null && $created !== '' ? htmlspecialchars($created, ENT_QUOTES, 'UTF-8') : '—' ?>
                                </td>
                                <td class="px-4 py-3 text-right font-medium text-slate-800"><?= $uc ?></td>
                                <td class="px-4 py-3">
                                    <?php if ($editUrl !== ''): ?>
                                        <a href="<?= htmlspecialchars($editUrl, ENT_QUOTES, 'UTF-8') ?>" class="inline-flex rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-800 hover:bg-slate-50">Changer la formule</a>
                                    <?php else: ?>
                                        <span class="text-slate-400">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <section aria-labelledby="plans-heading" class="space-y-4">
            <div>
                <h2 id="plans-heading" class="text-lg font-bold text-slate-900">Formules d’accès au service</h2>
                <p class="mt-1 max-w-3xl text-sm text-slate-600 leading-relaxed">
                    Référentiel des paliers proposés aux communautés. Pour attribuer une formule à une communauté, utilisez le bouton « Changer la formule » dans le tableau ci-dessus.
                </p>
            </div>
            <?php if ($plansError !== ''): ?>
                <p class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900"><?= htmlspecialchars($plansError, ENT_QUOTES, 'UTF-8') ?></p>
            <?php elseif ($plans === []): ?>
                <p class="text-sm text-slate-500">Aucune formule enregistrée.</p>
            <?php else: ?>
                <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-4 py-3 text-left font-semibold text-slate-700">Intitulé</th>
                                <th class="px-4 py-3 text-left font-semibold text-slate-600">Ordre d’affichage</th>
                                <th class="px-4 py-3 text-left font-semibold text-slate-600">Paiement récurrent</th>
                                <th class="px-4 py-3 text-right font-semibold text-slate-600">Catalogue</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php foreach ($plans as $p): ?>
                                <?php
                                $pid = (int) ($p['id'] ?? 0);
                                $pname = (string) ($p['name'] ?? '');
                                $sort = (int) ($p['sort_order'] ?? 0);
                                $m = trim((string) ($p['stripe_price_id_monthly'] ?? ''));
                                $y = trim((string) ($p['stripe_price_id_yearly'] ?? ''));
                                $billing = ($m !== '' || $y !== '') ? 'Réglages présents (mensuel et/ou annuel)' : 'Non renseigné sur cette instance';
                                ?>
                                <tr>
                                    <td class="px-4 py-3 font-medium text-slate-900"><?= htmlspecialchars($pname, ENT_QUOTES, 'UTF-8') ?></td>
                                    <td class="px-4 py-3 text-slate-600"><?= $sort ?></td>
                                    <td class="px-4 py-3 text-slate-600"><?= htmlspecialchars($billing, ENT_QUOTES, 'UTF-8') ?></td>
                                    <td class="px-4 py-3 text-right">
                                        <?php if ($pid > 0): ?>
                                            <a href="<?= htmlspecialchars(url('admin/system/subscription-plans/' . $pid . '/edit'), ENT_QUOTES, 'UTF-8') ?>" class="text-xs font-semibold text-emerald-800 hover:text-emerald-950">Modifier le palier</a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </div>
</div>
