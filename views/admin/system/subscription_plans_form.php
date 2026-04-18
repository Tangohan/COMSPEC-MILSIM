<?php
declare(strict_types=1);
$row = is_array($subscriptionPlanRow ?? null) ? $subscriptionPlanRow : null;
$action = (string) ($subscriptionPlanFormAction ?? '');
$csrf = \App\Core\Csrf::token();
if ($row === null) {
    echo '<p class="p-8 text-slate-600">Données manquantes.</p>';

    return;
}
$id = (int) ($row['id'] ?? 0);
$name = (string) ($row['name'] ?? '');
$slug = (string) ($row['slug'] ?? '');
$sort = (int) ($row['sort_order'] ?? 0);
$fj = $row['features_json'] ?? null;
$lj = $row['limits_json'] ?? null;
$sm = (string) ($row['stripe_price_id_monthly'] ?? '');
$sy = (string) ($row['stripe_price_id_yearly'] ?? '');

$prettyJson = static function (?string $raw): string {
    if ($raw === null || trim($raw) === '') {
        return '';
    }
    $d = json_decode($raw, true);
    if (!is_array($d)) {
        return (string) $raw;
    }
    $enc = json_encode($d, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

    return $enc !== false ? $enc : (string) $raw;
};
$featuresVal = $prettyJson(is_string($fj) ? $fj : null);
$limitsVal = $prettyJson(is_string($lj) ? $lj : null);
?>
<div class="min-h-0 flex-1 bg-slate-50">
    <div class="mx-auto max-w-3xl px-4 py-8 sm:px-6 lg:px-8">
        <nav class="mb-6 text-sm text-slate-500">
            <a href="<?= htmlspecialchars(url('admin'), ENT_QUOTES, 'UTF-8') ?>" class="font-semibold text-emerald-800 hover:text-emerald-950">Administration plateforme</a>
            <span class="mx-2" aria-hidden="true">/</span>
            <a href="<?= htmlspecialchars(url('admin/system/subscription-plans'), ENT_QUOTES, 'UTF-8') ?>" class="font-semibold text-emerald-800 hover:text-emerald-950">Formules d’accès</a>
            <span class="mx-2" aria-hidden="true">/</span>
            <span class="text-slate-800">Modifier</span>
        </nav>

        <?php $err = \App\Core\Session::getFlash('error'); ?>
        <?php if ($err): ?>
            <p class="mb-4 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-900"><?= htmlspecialchars((string) $err, ENT_QUOTES, 'UTF-8') ?></p>
        <?php endif; ?>

        <h1 class="text-2xl font-black text-slate-900">Modifier la formule</h1>
        <p class="mt-2 text-sm text-slate-600">
            Slug technique (non modifiable) : <span class="font-mono text-xs font-semibold text-slate-800"><?= htmlspecialchars($slug, ENT_QUOTES, 'UTF-8') ?></span>
        </p>

        <form method="post" action="<?= htmlspecialchars($action, ENT_QUOTES, 'UTF-8') ?>" class="mt-8 space-y-6 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">

            <div>
                <label for="sp_name" class="block text-sm font-semibold text-slate-800">Libellé</label>
                <input id="sp_name" name="name" type="text" required maxlength="100" value="<?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500" />
            </div>

            <div>
                <label for="sp_sort" class="block text-sm font-semibold text-slate-800">Ordre d’affichage</label>
                <input id="sp_sort" name="sort_order" type="number" value="<?= (int) $sort ?>" class="mt-1 w-32 rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500" />
            </div>

            <div>
                <label for="sp_features" class="block text-sm font-semibold text-slate-800">Fonctionnalités (JSON objet)</label>
                <p class="mt-0.5 text-xs text-slate-500">Laissez vide pour effacer. Les clés correspondent aux drapeaux lus par le portail (voir documentation interne des paliers).</p>
                <textarea id="sp_features" name="features_json" rows="12" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 font-mono text-xs shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500"><?= htmlspecialchars($featuresVal, ENT_QUOTES, 'UTF-8') ?></textarea>
            </div>

            <div>
                <label for="sp_limits" class="block text-sm font-semibold text-slate-800">Quotas (JSON objet)</label>
                <p class="mt-0.5 text-xs text-slate-500">Optionnel — plafonds ou compteurs pour l’offre gratuite ou limitée.</p>
                <textarea id="sp_limits" name="limits_json" rows="8" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 font-mono text-xs shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500"><?= htmlspecialchars($limitsVal, ENT_QUOTES, 'UTF-8') ?></textarea>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label for="sp_stripe_m" class="block text-sm font-semibold text-slate-800">Stripe — prix mensuel</label>
                    <input id="sp_stripe_m" name="stripe_price_id_monthly" type="text" maxlength="100" value="<?= htmlspecialchars($sm, ENT_QUOTES, 'UTF-8') ?>" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 font-mono text-xs shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500" placeholder="price_…" />
                </div>
                <div>
                    <label for="sp_stripe_y" class="block text-sm font-semibold text-slate-800">Stripe — prix annuel</label>
                    <input id="sp_stripe_y" name="stripe_price_id_yearly" type="text" maxlength="100" value="<?= htmlspecialchars($sy, ENT_QUOTES, 'UTF-8') ?>" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 font-mono text-xs shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500" placeholder="price_…" />
                </div>
            </div>

            <div class="flex flex-wrap gap-3 pt-2">
                <button type="submit" class="inline-flex rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-bold text-white hover:bg-slate-800">Enregistrer</button>
                <a href="<?= htmlspecialchars(url('admin/system/subscription-plans'), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-800 hover:bg-slate-50">Annuler</a>
            </div>
        </form>
    </div>
</div>
