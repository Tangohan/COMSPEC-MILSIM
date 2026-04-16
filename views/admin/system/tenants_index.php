<?php
declare(strict_types=1);
$rows = is_array($platformTenants ?? null) ? $platformTenants : [];
$plans = is_array($platformSubscriptionPlans ?? null) ? $platformSubscriptionPlans : [];
$plansError = isset($platformSubscriptionPlansError) ? (string) $platformSubscriptionPlansError : '';
?>
<div class="min-h-0 flex-1 bg-slate-50">
    <div class="mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:px-8">
        <nav class="mb-6 text-sm text-slate-500">
            <a href="<?= htmlspecialchars(url('admin'), ENT_QUOTES, 'UTF-8') ?>" class="font-semibold text-emerald-800 hover:text-emerald-950">Administration plateforme</a>
            <span class="mx-2" aria-hidden="true">/</span>
            <span class="text-slate-800">Annuaire des communautés</span>
        </nav>
        <h1 class="text-2xl font-black text-slate-900">Annuaire des communautés</h1>
        <p class="mt-2 max-w-3xl text-sm text-slate-600">
            Vue transverse de toutes les communautés actives sur le site, avec l’effectif des comptes rattachés.
            La configuration détaillée (membres, recrutement, unités) reste dans le back-office de chaque communauté.
        </p>

        <div class="mt-6 flex flex-wrap gap-3">
            <a href="<?= htmlspecialchars(url('communities'), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-800 hover:bg-slate-50">Annuaire public</a>
            <a href="<?= htmlspecialchars(url('communities/create'), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm font-semibold text-emerald-950 hover:bg-emerald-100">Créer une communauté</a>
            <a href="<?= htmlspecialchars(url('admin/system/deployment'), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex rounded-lg border border-amber-200 bg-amber-50 px-4 py-2 text-sm font-semibold text-amber-950 hover:bg-amber-100">Publications et préqualification</a>
        </div>

        <div class="mt-10 overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold text-slate-700">Communauté</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-600">Création</th>
                        <th class="px-4 py-3 text-right font-semibold text-slate-600">Comptes</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-600">Vitrine publique</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if ($rows === []): ?>
                        <tr>
                            <td colspan="4" class="px-4 py-8 text-center text-slate-500">Aucune communauté enregistrée (hors espace technique).</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($rows as $t): ?>
                            <?php
                            $name = (string) ($t['name'] ?? '');
                            $slug = (string) ($t['slug'] ?? '');
                            $created = $t['created_at'] ?? null;
                            $uc = (int) ($t['user_count'] ?? 0);
                            $publicUrl = $slug !== '' ? url('c/' . rawurlencode($slug)) : '';
                            ?>
                            <tr>
                                <td class="px-4 py-3">
                                    <p class="font-semibold text-slate-900"><?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?></p>
                                </td>
                                <td class="px-4 py-3 text-slate-600 whitespace-nowrap">
                                    <?= $created !== null && $created !== '' ? htmlspecialchars($created, ENT_QUOTES, 'UTF-8') : '—' ?>
                                </td>
                                <td class="px-4 py-3 text-right font-medium text-slate-800"><?= $uc ?></td>
                                <td class="px-4 py-3">
                                    <?php if ($publicUrl !== ''): ?>
                                        <a href="<?= htmlspecialchars($publicUrl, ENT_QUOTES, 'UTF-8') ?>" class="font-semibold text-emerald-800 hover:text-emerald-950" target="_blank" rel="noopener">Voir la page publique</a>
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

        <section class="mt-12" aria-labelledby="plans-heading">
            <h2 id="plans-heading" class="text-lg font-bold text-slate-900">Formules d’accès au service</h2>
            <p class="mt-1 max-w-3xl text-sm text-slate-600">
                Référentiel des paliers proposés aux communautés (affichage informatif). La modification des barèmes et du raccordement au prestataire de paiement se fait côté configuration applicative et base de données, pas depuis cette page.
            </p>
            <?php if ($plansError !== ''): ?>
                <p class="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900"><?= htmlspecialchars($plansError, ENT_QUOTES, 'UTF-8') ?></p>
            <?php elseif ($plans === []): ?>
                <p class="mt-4 text-sm text-slate-500">Aucune formule enregistrée.</p>
            <?php else: ?>
                <div class="mt-6 overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-4 py-3 text-left font-semibold text-slate-700">Intitulé</th>
                                <th class="px-4 py-3 text-left font-semibold text-slate-600">Ordre d’affichage</th>
                                <th class="px-4 py-3 text-left font-semibold text-slate-600">Paiement récurrent</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php foreach ($plans as $p): ?>
                                <?php
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
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <p class="mt-4 text-xs text-slate-500">
                    Pour faire évoluer une communauté vers une autre formule ou pour les parrainages, utilisez les écrans prévus dans le portail (mise à niveau, invitations structurantes) ou contactez l’équipe technique.
                </p>
            <?php endif; ?>
        </section>
    </div>
</div>
