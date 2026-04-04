<?php
$error = \App\Core\Session::getFlash('error');
$success = \App\Core\Session::getFlash('success');
/** @var list<array<string,mixed>> $paidPlans */
$paidPlans = $paidPlans ?? [];
$stripeConfigured = $stripeConfigured ?? false;

/** Libellés lisibles pour features_json (clés techniques → texte produit). */
$featureLabels = [
    'forum' => 'Forum & discussions',
    'documents' => 'Documents et pièces jointes',
    'training' => 'Formations & parcours',
    'atak' => 'Carte tactique ATAK',
    'analytics' => 'Tableaux de bord & statistiques',
    'events' => 'Événements & planning',
    'community_create' => null,
];

/** Libellés marketing par slug de plan (Standard → Pro, Pro → Pro +). */
$planMarketing = static function (string $slug): array {
    return match ($slug) {
        'standard' => ['eyebrow' => 'Premium', 'title' => 'Pro'],
        'pro' => ['eyebrow' => 'Premium Plus', 'title' => 'Pro +'],
        default => ['eyebrow' => 'Premium', 'title' => $slug],
    };
};
?>
<div class="min-h-screen bg-slate-100">
    <div class="mx-auto max-w-[min(100%,100rem)] px-4 py-10 sm:px-6 lg:px-8 lg:py-12">
        <?php if ($error): ?>
        <div class="mb-6 overflow-hidden rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 shadow-sm"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
        <div class="mb-6 overflow-hidden rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900 shadow-sm"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <div class="grid gap-8 lg:gap-10 2xl:grid-cols-[1.1fr_0.9fr]">

            <section class="overflow-hidden rounded-[2rem] border border-slate-200 bg-white shadow-[0_20px_60px_-24px_rgba(15,23,42,0.18)]">
                <div class="border-b border-slate-200 bg-gradient-to-r from-slate-950 via-slate-900 to-slate-800 px-6 py-8 sm:px-8">
                    <div class="max-w-3xl">
                        <p class="text-[11px] font-black uppercase tracking-[0.28em] text-emerald-400">Athena Communities</p>
                        <h1 class="mt-3 text-3xl font-black tracking-tight text-white sm:text-4xl">Créer une communauté</h1>
                        <p class="mt-3 max-w-2xl text-sm leading-6 text-slate-300">
                            Déployez un nouvel espace communautaire avec ses modules, ses règles d’inscription et son administration dédiée. Le compte courant deviendra automatiquement administrateur de la communauté créée.
                        </p>
                    </div>
                </div>

                <form method="post" action="<?= url('communities/create') ?>" class="space-y-10 px-6 py-8 sm:px-8" id="community-create-form">
                    <?= \App\Core\Csrf::field() ?>

                    <?php if (!$stripeConfigured): ?>
                    <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-4 text-sm leading-6 text-amber-900">
                        <strong class="font-semibold">Paiement en ligne indisponible</strong> — la clé serveur Stripe n’est pas configurée. Vous pouvez créer une communauté en <strong>Quartier libre</strong> ; les abonnements payants seront proposés dès que la facturation sera activée.
                    </div>
                    <?php endif; ?>

                    <section class="space-y-5">
                        <div class="flex items-center gap-3">
                            <div class="flex h-9 w-9 items-center justify-center rounded-2xl bg-slate-900 text-white">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 21h18M5 21V7l8-4 6 4v14M9 9h.01M9 12h.01M9 15h.01M15 12h.01M15 15h.01"></path>
                                </svg>
                            </div>
                            <div>
                                <p class="text-[11px] font-black uppercase tracking-[0.22em] text-slate-400">Identité</p>
                                <h2 class="text-lg font-black tracking-tight text-slate-950">Configuration générale</h2>
                            </div>
                        </div>

                        <div class="grid gap-5 md:grid-cols-2">
                            <div class="md:col-span-2">
                                <label class="mb-2 block text-[11px] font-black uppercase tracking-[0.22em] text-slate-500">Nom affiché</label>
                                <input
                                    type="text"
                                    name="name"
                                    required
                                    maxlength="255"
                                    class="h-14 w-full rounded-2xl border border-slate-200 bg-white px-4 text-sm font-medium text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100"
                                    placeholder="92e Régiment d'infanterie"
                                >
                                <p class="mt-2 text-xs leading-5 text-slate-500">
                                    Nom public visible dans l’interface, les modules et les espaces de recrutement.
                                </p>
                            </div>

                            <div class="md:col-span-2">
                                <label class="mb-2 block text-[11px] font-black uppercase tracking-[0.22em] text-slate-500">Slug URL</label>
                                <input
                                    type="text"
                                    name="slug"
                                    pattern="[a-z0-9]([a-z0-9-]{0,48}[a-z0-9])?"
                                    class="h-14 w-full rounded-2xl border border-slate-200 bg-white px-4 font-mono text-sm text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100"
                                    placeholder="laisser-vide-pour-generation-auto"
                                >
                                <div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-slate-500">
                                    <span>Minuscules, chiffres et tirets uniquement.</span>
                                    <span class="hidden h-1 w-1 rounded-full bg-slate-300 sm:block"></span>
                                    <span>Vide = génération automatique.</span>
                                    <span class="hidden h-1 w-1 rounded-full bg-slate-300 sm:block"></span>
                                    <span>URL finale : <span class="font-mono text-slate-700">/c/&lt;slug&gt;</span></span>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="space-y-5">
                        <div class="flex items-center gap-3">
                            <div class="flex h-9 w-9 items-center justify-center rounded-2xl bg-emerald-50 text-emerald-700 ring-1 ring-emerald-100">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-3.314 0-6 1.343-6 3s2.686 3 6 3 6-1.343 6-3-2.686-3-6-3zm0 0V6m0 8v2m-7 2h14"></path>
                                </svg>
                            </div>
                            <div>
                                <p class="text-[11px] font-black uppercase tracking-[0.22em] text-slate-400">Offre</p>
                                <h2 class="text-lg font-black tracking-tight text-slate-950">Choisir une formule</h2>
                            </div>
                        </div>

                        <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-4 text-sm leading-6 text-amber-900">
                            L’offre <strong>Quartier libre</strong> démarre tout de suite. Les formules <strong>Pro</strong> et <strong>Pro +</strong> s’activent après un <strong>paiement sécurisé Stripe</strong> : la communauté n’est provisionnée qu’après confirmation du paiement.
                        </div>

                        <div class="grid grid-cols-1 gap-5 md:grid-cols-2 md:gap-6 xl:grid-cols-3">
                            <label class="group relative flex min-h-0 cursor-pointer flex-col rounded-[1.75rem] border-2 border-slate-900 bg-slate-900 p-5 text-white transition hover:-translate-y-0.5 hover:shadow-xl has-[:checked]:ring-4 has-[:checked]:ring-emerald-100 sm:p-6">
                                <input type="radio" name="plan_choice" value="free" class="sr-only" checked data-paid="0">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <p class="text-[11px] font-black uppercase tracking-[0.24em] text-emerald-400">Sans engagement</p>
                                        <h3 class="mt-2 text-2xl font-black tracking-tight">Quartier libre</h3>
                                    </div>
                                    <span class="rounded-full border border-white/15 bg-white/10 px-2.5 py-1 text-[10px] font-black uppercase tracking-[0.18em] text-white/80">
                                        Inclus
                                    </span>
                                </div>

                                <p class="mt-4 text-sm leading-6 text-slate-300">
                                    Base fonctionnelle pour ouvrir rapidement un espace communautaire standard.
                                </p>

                                <ul class="mt-5 space-y-2 text-sm text-slate-200">
                                    <li class="flex items-center gap-2"><span class="h-1.5 w-1.5 rounded-full bg-emerald-400"></span>Forum</li>
                                    <li class="flex items-center gap-2"><span class="h-1.5 w-1.5 rounded-full bg-emerald-400"></span>Documents</li>
                                    <li class="flex items-center gap-2"><span class="h-1.5 w-1.5 rounded-full bg-emerald-400"></span>Formation</li>
                                    <li class="flex items-center gap-2"><span class="h-1.5 w-1.5 rounded-full bg-emerald-400"></span>Limites produit standard</li>
                                </ul>

                                <div class="mt-6 border-t border-white/10 pt-4">
                                    <p class="text-xs font-semibold text-slate-300">Création immédiate sans facturation.</p>
                                </div>
                            </label>

                            <?php foreach ($paidPlans as $p):
                                $slug = (string) ($p['slug'] ?? '');
                                $m = $planMarketing($slug);
                                $featRaw = $p['features_json'] ?? '{}';
                                $feat = is_string($featRaw) ? json_decode($featRaw, true) : [];
                                if (!is_array($feat)) {
                                    $feat = [];
                                }
                                $hasM = $stripeConfigured && trim((string) ($p['stripe_price_id_monthly'] ?? '')) !== '';
                                $hasY = $stripeConfigured && trim((string) ($p['stripe_price_id_yearly'] ?? '')) !== '';
                                $planBadgeClass = $slug === 'pro' ? 'text-violet-700' : 'text-amber-600';
                                ?>
                            <div class="relative flex min-h-0 flex-col rounded-[1.75rem] border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-emerald-300 hover:shadow-xl has-[:checked]:ring-4 has-[:checked]:ring-emerald-100 sm:p-6">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <p class="text-[11px] font-black uppercase tracking-[0.24em] <?= $planBadgeClass ?>"><?= htmlspecialchars($m['eyebrow']) ?></p>
                                        <h3 class="mt-2 text-2xl font-black tracking-tight text-slate-950"><?= htmlspecialchars($m['title']) ?></h3>
                                    </div>
                                    <span class="rounded-full bg-slate-100 px-2.5 py-1 text-[10px] font-black uppercase tracking-[0.18em] text-slate-600">
                                        Stripe
                                    </span>
                                </div>

                                <p class="mt-4 text-sm leading-6 text-slate-600">
                                    Offre <?= $slug === 'pro' ? 'avancée' : 'intermédiaire' ?> — modules étendus selon la configuration produit.
                                </p>

                                <ul class="mt-5 grid gap-2 text-sm text-slate-700">
                                    <?php
                                    $shown = 0;
                                foreach ($feat as $k => $v) {
                                    if ($k === 'community_create') {
                                        continue;
                                    }
                                    if ($k === 'max_members' && (is_numeric($v) || $v === true)) {
                                        $n = is_numeric($v) ? (int) $v : 0;
                                        if ($n > 0) {
                                            echo '<li class="rounded-xl bg-slate-50 px-3 py-2">Jusqu’à ' . $n . ' membres actifs</li>';
                                            ++$shown;
                                        }

                                        continue;
                                    }
                                    if ($v === true || $v === 1) {
                                        $label = $featureLabels[$k] ?? null;
                                        if ($label === null) {
                                            continue;
                                        }
                                        echo '<li class="rounded-xl bg-slate-50 px-3 py-2">' . htmlspecialchars($label) . '</li>';
                                        ++$shown;
                                    }
                                }
                                if ($shown === 0):
                                    ?>
                                    <li class="rounded-xl bg-slate-50 px-3 py-2 text-slate-500">Fonctionnalités étendues par rapport à Quartier libre.</li>
                                    <?php endif; ?>
                                </ul>

                                <div class="mt-6 space-y-2 border-t border-slate-100 pt-4">
                                    <?php if ($hasM): ?>
                                    <label class="flex cursor-pointer items-center gap-3 text-sm group">
                                        <input type="radio" name="plan_choice" value="<?= htmlspecialchars($slug) ?>|monthly" class="rounded-full border-slate-300 text-emerald-600 focus:ring-emerald-500" data-paid="1">
                                        <span class="text-slate-700 group-hover:text-slate-900">Facturation <strong>mensuelle</strong> <span class="font-normal text-slate-400">(Stripe)</span></span>
                                    </label>
                                    <?php endif; ?>
                                    <?php if ($hasY): ?>
                                    <label class="flex cursor-pointer items-center gap-3 text-sm group">
                                        <input type="radio" name="plan_choice" value="<?= htmlspecialchars($slug) ?>|yearly" class="rounded-full border-slate-300 text-emerald-600 focus:ring-emerald-500" data-paid="1">
                                        <span class="text-slate-700 group-hover:text-slate-900">Facturation <strong>annuelle</strong> <span class="font-normal text-slate-400">(Stripe)</span></span>
                                    </label>
                                    <?php endif; ?>
                                    <?php if (!$hasM && !$hasY): ?>
                                    <p class="text-xs leading-5 text-amber-700">
                                        <?php if ($stripeConfigured): ?>
                                            Tarification en cours de configuration. Vérifier <code class="rounded bg-amber-100 px-1.5 py-0.5 text-[11px]">stripe_price_id_monthly</code> et <code class="rounded bg-amber-100 px-1.5 py-0.5 text-[11px]">yearly</code> pour cette formule.
                                        <?php else: ?>
                                            Paiement en ligne non disponible — choisissez <strong>Quartier libre</strong> ou réessayez lorsque Stripe sera activé.
                                        <?php endif; ?>
                                    </p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </section>

                    <section class="space-y-5">
                        <div class="flex items-center gap-3">
                            <div class="flex h-9 w-9 items-center justify-center rounded-2xl bg-sky-50 text-sky-700 ring-1 ring-sky-100">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14-6H5m14 12H5m14 6H5"></path>
                                </svg>
                            </div>
                            <div>
                                <p class="text-[11px] font-black uppercase tracking-[0.22em] text-slate-400">Parcours</p>
                                <h2 class="text-lg font-black tracking-tight text-slate-950">Paramètres d’accès</h2>
                            </div>
                        </div>

                        <div class="grid gap-5 md:grid-cols-2">
                            <div>
                                <label class="mb-2 block text-[11px] font-black uppercase tracking-[0.22em] text-slate-500">Mode d’inscription</label>
                                <select
                                    name="registration_mode"
                                    class="h-14 w-full rounded-2xl border border-slate-200 bg-white px-4 text-sm text-slate-900 outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100"
                                >
                                    <option value="milsim">Formulaire MilSim complet</option>
                                    <option value="simple">Mode simple</option>
                                </select>
                            </div>

                            <div class="md:col-span-2">
                                <label class="mb-2 block text-[11px] font-black uppercase tracking-[0.22em] text-slate-500">Message d’accueil</label>
                                <textarea
                                    name="welcome_text"
                                    rows="4"
                                    maxlength="500"
                                    class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100"
                                    placeholder="Texte visible sur la page de communauté."
                                ></textarea>
                                <p class="mt-2 text-xs leading-5 text-slate-500">
                                    Message d’introduction affiché aux visiteurs et nouveaux inscrits.
                                </p>
                            </div>
                        </div>

                        <div class="grid gap-4 md:grid-cols-2">
                            <label class="group flex items-start gap-4 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4 transition hover:border-slate-300 hover:bg-white">
                                <input type="checkbox" name="community_locked" value="1" class="mt-1 h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                                <span class="block">
                                    <span class="block text-sm font-bold text-slate-900">Verrouiller la communauté</span>
                                    <span class="mt-1 block text-xs leading-5 text-slate-500">Empêche le recrutement public et ferme l’ouverture libre des inscriptions.</span>
                                </span>
                            </label>

                            <label class="group flex items-start gap-4 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4 transition hover:border-slate-300 hover:bg-white">
                                <input type="checkbox" name="require_ai_ack" value="1" checked class="mt-1 h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                                <span class="block">
                                    <span class="block text-sm font-bold text-slate-900">Exiger la confirmation « sans IA »</span>
                                    <span class="mt-1 block text-xs leading-5 text-slate-500">Ajoute cette validation au flux d’inscription de la communauté.</span>
                                </span>
                            </label>
                        </div>
                    </section>

                    <div
                        id="paid-hint"
                        class="hidden rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-4 text-sm leading-6 text-emerald-900"
                    >
                        Vous serez redirigé vers <strong>Stripe</strong> pour valider l’abonnement. La communauté sera créée <strong>après confirmation du paiement</strong>.
                    </div>

                    <div class="flex flex-col gap-4 border-t border-slate-200 pt-6 sm:flex-row sm:items-center sm:justify-between">
                        <div class="text-xs leading-5 text-slate-500">
                            La création initialise l’espace, les modules disponibles et les paramètres de base de la communauté.
                        </div>
                        <button
                            type="submit"
                            class="inline-flex items-center justify-center rounded-2xl bg-slate-950 px-6 py-4 text-[11px] font-black uppercase tracking-[0.22em] text-white transition hover:-translate-y-0.5 hover:bg-emerald-600 focus:outline-none focus:ring-4 focus:ring-emerald-100"
                            id="submit-btn"
                        >
                            Créer la communauté
                        </button>
                    </div>
                </form>
            </section>

            <aside class="space-y-6">
                <div class="overflow-hidden rounded-[2rem] border border-slate-200 bg-white shadow-[0_20px_60px_-24px_rgba(15,23,42,0.16)]">
                    <div class="border-b border-slate-200 px-6 py-5">
                        <p class="text-[11px] font-black uppercase tracking-[0.22em] text-slate-400">Aperçu</p>
                        <h3 class="mt-2 text-lg font-black tracking-tight text-slate-950">Ce qui sera créé</h3>
                    </div>
                    <div class="space-y-4 px-6 py-5">
                        <div class="rounded-2xl bg-slate-50 px-4 py-4">
                            <p class="text-sm font-bold text-slate-900">Espace communautaire dédié</p>
                            <p class="mt-1 text-xs leading-5 text-slate-500">Structure autonome avec gestion des accès, identité propre et URL dédiée.</p>
                        </div>
                        <div class="rounded-2xl bg-slate-50 px-4 py-4">
                            <p class="text-sm font-bold text-slate-900">Compte administrateur initial</p>
                            <p class="mt-1 text-xs leading-5 text-slate-500">Le compte courant devient administrateur principal de la communauté créée.</p>
                        </div>
                        <div class="rounded-2xl bg-slate-50 px-4 py-4">
                            <p class="text-sm font-bold text-slate-900">Modules selon formule</p>
                            <p class="mt-1 text-xs leading-5 text-slate-500">Activation conditionnée par le plan sélectionné (Quartier libre, Pro, Pro +) et les paramètres commerciaux renseignés.</p>
                        </div>
                    </div>
                </div>

                <div class="overflow-hidden rounded-[2rem] border border-slate-200 bg-slate-950 text-white shadow-[0_20px_60px_-24px_rgba(15,23,42,0.22)]">
                    <div class="px-6 py-6">
                        <p class="text-[11px] font-black uppercase tracking-[0.22em] text-emerald-400">Contrôle</p>
                        <h3 class="mt-2 text-xl font-black tracking-tight">Vérifications recommandées</h3>
                        <ul class="mt-5 space-y-3 text-sm text-slate-300">
                            <li class="flex gap-3">
                                <span class="mt-2 h-1.5 w-1.5 shrink-0 rounded-full bg-emerald-400"></span>
                                <span>Choisir un nom explicite et pérenne.</span>
                            </li>
                            <li class="flex gap-3">
                                <span class="mt-2 h-1.5 w-1.5 shrink-0 rounded-full bg-emerald-400"></span>
                                <span>Vérifier les prix Stripe avant ouverture des plans Pro / Pro +.</span>
                            </li>
                            <li class="flex gap-3">
                                <span class="mt-2 h-1.5 w-1.5 shrink-0 rounded-full bg-emerald-400"></span>
                                <span>Définir immédiatement le mode d’inscription adapté à la communauté.</span>
                            </li>
                            <li class="flex gap-3">
                                <span class="mt-2 h-1.5 w-1.5 shrink-0 rounded-full bg-emerald-400"></span>
                                <span>Utiliser le verrouillage si l’ouverture doit rester privée.</span>
                            </li>
                        </ul>
                    </div>
                </div>
            </aside>

        </div>
    </div>
</div>
<script>
(function () {
    var form = document.getElementById('community-create-form');
    var hint = document.getElementById('paid-hint');
    var btn = document.getElementById('submit-btn');
    function sync() {
        var paid = false;
        form.querySelectorAll('input[name="plan_choice"]').forEach(function (el) {
            if (el.checked && el.getAttribute('data-paid') === '1') paid = true;
        });
        hint.classList.toggle('hidden', !paid);
        btn.textContent = paid ? 'Continuer vers le paiement Stripe' : 'Créer la communauté';
    }
    form.addEventListener('change', sync);
    sync();
})();
</script>
