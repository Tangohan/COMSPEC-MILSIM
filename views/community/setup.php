<?php
/** @var array $tenant */
/** @var array $settings */
$tz = $settings['timezone'] ?? 'Europe/Paris';
$zones = \DateTimeZone::listIdentifiers();
if (!in_array($tz, $zones, true)) {
    $zones[] = $tz;
    sort($zones);
}
$communityName = htmlspecialchars((string) ($tenant['name'] ?? ''), ENT_QUOTES, 'UTF-8');
?>
<div class="relative isolate min-h-[calc(100vh-3.5rem)] w-full overflow-hidden bg-slate-950 text-white">
    <div class="absolute inset-0 bg-[radial-gradient(circle_at_top,rgba(16,185,129,0.18),transparent_30%),radial-gradient(circle_at_80%_20%,rgba(59,130,246,0.14),transparent_28%)]"></div>
    <div class="absolute inset-0 bg-[linear-gradient(to_bottom,rgba(2,6,23,0.65),rgba(2,6,23,0.92))]"></div>

    <div class="relative mx-auto flex min-h-[calc(100vh-3.5rem)] max-w-7xl items-center px-4 py-10 sm:px-6 lg:px-8">
        <div class="grid w-full gap-10 xl:grid-cols-[0.95fr_1.05fr] xl:gap-12">

            <section class="flex flex-col justify-center">
                <div class="max-w-xl">
                    <div class="inline-flex items-center gap-2 rounded-full border border-emerald-400/20 bg-emerald-400/10 px-3 py-1.5 text-[11px] font-black uppercase tracking-[0.22em] text-emerald-300">
                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-400"></span>
                        Configuration finale
                    </div>

                    <h1 class="mt-6 text-4xl font-black tracking-tight text-white sm:text-5xl">
                        Bienvenue sur <span class="text-emerald-400">ATHENA</span>
                    </h1>

                    <p class="mt-5 max-w-lg text-base leading-7 text-slate-300">
                        Dernière étape de mise en service<?= $communityName !== '' ? ' pour <strong class="font-semibold text-white">' . $communityName . '</strong>' : '' ?>.
                        Définissez le fuseau horaire de la communauté afin d’uniformiser l’affichage des événements, des échéances, des outils et des journaux temporels.
                    </p>

                    <div class="mt-8 grid gap-4 sm:grid-cols-2">
                        <div class="rounded-2xl border border-white/10 bg-white/5 p-4 backdrop-blur-sm">
                            <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-400">Impact</p>
                            <p class="mt-2 text-sm font-semibold text-white">Événements et calendrier</p>
                            <p class="mt-1 text-sm leading-6 text-slate-400">
                                Les horaires affichés dans l’interface seront alignés sur ce fuseau.
                            </p>
                        </div>

                        <div class="rounded-2xl border border-white/10 bg-white/5 p-4 backdrop-blur-sm">
                            <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-400">Recommandation</p>
                            <p class="mt-2 text-sm font-semibold text-white">Fuseau principal de l’unité</p>
                            <p class="mt-1 text-sm leading-6 text-slate-400">
                                Choisir le fuseau réellement utilisé par la communauté et son administration.
                            </p>
                        </div>
                    </div>
                </div>
            </section>

            <section class="xl:pl-4 2xl:pl-8">
                <div class="overflow-hidden rounded-[2rem] border border-white/10 bg-white/[0.06] shadow-[0_30px_80px_-24px_rgba(0,0,0,0.55)] backdrop-blur-xl">
                    <div class="border-b border-white/10 px-6 py-6 sm:px-8">
                        <p class="text-[11px] font-black uppercase tracking-[0.22em] text-slate-400">Paramétrage</p>
                        <h2 class="mt-2 text-2xl font-black tracking-tight text-white">
                            Fuseau horaire de la communauté
                        </h2>
                        <p class="mt-2 text-sm leading-6 text-slate-400">
                            Ce réglage servira de référence par défaut pour l’ensemble des modules dépendants du temps.
                        </p>
                    </div>

                    <form method="post" action="<?= url('c/' . rawurlencode((string) ($tenant['slug'] ?? '')) . '/setup') ?>" class="space-y-6 px-6 py-6 sm:px-8 sm:py-8">
                        <?= \App\Core\Csrf::field() ?>

                        <div class="space-y-3">
                            <label class="block text-[11px] font-black uppercase tracking-[0.22em] text-slate-400" for="setup-timezone">
                                Fuseau
                            </label>

                            <div class="rounded-2xl border border-white/10 bg-slate-950/70 p-2 ring-1 ring-white/5 transition focus-within:border-emerald-400/40 focus-within:ring-emerald-400/20">
                                <select
                                    id="setup-timezone"
                                    name="timezone"
                                    class="h-14 w-full cursor-pointer rounded-xl border-0 bg-transparent px-3 text-sm font-medium text-white outline-none"
                                >
                                    <?php foreach ($zones as $z): ?>
                                    <option value="<?= htmlspecialchars($z, ENT_QUOTES, 'UTF-8') ?>" class="bg-slate-900 text-slate-100" <?= $z === $tz ? 'selected' : '' ?>><?= htmlspecialchars($z, ENT_QUOTES, 'UTF-8') ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="rounded-2xl border border-emerald-400/15 bg-emerald-400/10 px-4 py-4 text-sm leading-6 text-emerald-100" id="setup-tz-hint">
                                Le fuseau <span id="setup-tz-preview" class="font-semibold text-white"><?= htmlspecialchars($tz, ENT_QUOTES, 'UTF-8') ?></span> est actuellement présélectionné. Modifiez-le uniquement si la communauté opère principalement sur un autre référentiel horaire.
                            </div>
                        </div>

                        <div class="grid gap-4 rounded-2xl border border-white/10 bg-white/[0.04] p-4 sm:grid-cols-2">
                            <div>
                                <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-400">Référence conseillée</p>
                                <p class="mt-2 text-sm font-semibold text-white">Fuseau principal d’exploitation</p>
                                <p class="mt-1 text-sm leading-6 text-slate-400">
                                    Conserver un seul référentiel pour éviter les écarts de lecture entre planning, alertes et journaux.
                                </p>
                            </div>
                            <div>
                                <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-400">Effet immédiat</p>
                                <p class="mt-2 text-sm font-semibold text-white">Application au tableau de bord</p>
                                <p class="mt-1 text-sm leading-6 text-slate-400">
                                    Le paramètre est appliqué après validation et utilisé par défaut dans les interfaces de la communauté.
                                </p>
                            </div>
                        </div>

                        <button
                            type="submit"
                            class="inline-flex w-full items-center justify-center rounded-2xl bg-emerald-600 px-6 py-4 text-sm font-black uppercase tracking-[0.18em] text-white transition hover:bg-emerald-500 focus:outline-none focus:ring-4 focus:ring-emerald-400/20"
                        >
                            Terminer et ouvrir le tableau de bord
                        </button>
                    </form>
                </div>
            </section>

        </div>
    </div>
</div>
<script>
(function () {
    var sel = document.getElementById('setup-timezone');
    var preview = document.getElementById('setup-tz-preview');
    if (!sel || !preview) return;
    sel.addEventListener('change', function () {
        preview.textContent = sel.value;
    });
})();
</script>
