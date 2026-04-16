<?php
$base = url('');
$title = $title ?? 'Tableau de bord — Athena';
$showcase_training_feature = $showcase_training_feature ?? false;
$showcase_items = $showcase_items ?? [];
$dashboard_tenant_label = $dashboard_tenant_label ?? null;
$dashboard_tester_program = $dashboard_tester_program ?? null;
$showcase_json = json_encode($showcase_items, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);

?>
<!DOCTYPE html>
<html lang="fr" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title) ?></title>
<?php
    $seo_og_title = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
    $meta_description = $meta_description ?? 'Tableau de bord Athena : accès rapide aux formations, messages et activités de votre communauté.';
    require base_path('views/partials/seo_meta.php');
?>
    <?php $tailwindBaseUrl = $base; require base_path('views/partials/tailwind_cdn_or_build.php'); ?>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;900&display=swap" rel="stylesheet">
    <link href="<?= $base ?>/assets/css/styles.css" rel="stylesheet">
    <?php if (is_file(base_path('public/assets/css/portal-nav.css'))): ?>
    <link href="<?= htmlspecialchars($base) ?>/assets/css/portal-nav.css" rel="stylesheet">
    <?php endif; ?>
    <?php if ($showcase_training_feature && !empty($showcase_items)): ?>
    <script>
        window.__dashboardShowcaseCourses = <?= $showcase_json ?>;
        document.addEventListener('alpine:init', () => {
            Alpine.data('trainingShowcase', () => ({
                openModal: null,
                courses: window.__dashboardShowcaseCourses || [],
                active() {
                    return this.courses.find(c => c.id === this.openModal);
                },
                scrollTrack(dx) {
                    const el = this.$refs.track;
                    if (el) {
                        el.scrollBy({ left: dx, behavior: 'smooth' });
                    }
                },
            }));
        });
    </script>
    <script defer src="https://unpkg.com/alpinejs@3/dist/cdn.min.js"></script>
    <?php endif; ?>
</head>
<body class="dashboard-shell layout-light bg-slate-50 text-slate-900 selection:bg-slate-900 selection:text-white overflow-x-hidden antialiased">
<?php
$baseUrl = $base;
require base_path('views/partials/header_portal.php');
?>
<script defer src="<?= htmlspecialchars($base) ?>/assets/js/portal-alerts.js"></script>
<script defer src="<?= htmlspecialchars($base) ?>/assets/js/navigation.js"></script>
<script defer src="<?= htmlspecialchars($base) ?>/assets/js/ui_confirm_modal.js"></script>
<script defer src="<?= htmlspecialchars($base) ?>/assets/js/portal_command_palette.js"></script>
<?php require base_path('views/partials/alert_banners.php'); ?>
<?php require base_path('views/partials/forum_moderation_alerts.php'); ?>

    <style>
        .dash-vers-details > summary {
            list-style: none;
        }
        .dash-vers-details > summary::-webkit-details-marker {
            display: none;
        }
    </style>

    <div class="min-h-screen">
    <div class="w-full border-b border-white/5 bg-slate-900 text-white/30 select-none">
        <div class="mx-auto flex max-w-[1800px] flex-wrap items-center justify-between gap-x-4 gap-y-2 px-4 py-2 sm:px-6 lg:px-8">
            <div class="flex min-w-0 flex-wrap items-center gap-x-3 gap-y-1 font-mono text-[8px] uppercase tracking-[0.15em]">
                <div class="flex items-center gap-2">
                    <span class="font-black tracking-[0.28em] text-emerald-500">ZULU</span>
                    <span id="t-zulu" class="text-[10px] font-medium tracking-normal text-white w-[4.25rem] tabular-nums">00:00:00</span>
                </div>
                <span class="text-white/15" aria-hidden="true">|</span>
                <div class="hidden items-center gap-2 sm:flex sm:opacity-70">
                    <span>PST</span>
                    <span id="t-pst" class="text-[10px] tracking-normal text-white/85 w-[4.25rem] tabular-nums">00:00:00</span>
                    <span class="text-white/15" aria-hidden="true">|</span>
                    <span>MTN</span>
                    <span id="t-mtn" class="text-[10px] tracking-normal text-white/85 w-[4.25rem] tabular-nums">00:00:00</span>
                    <span class="text-white/15" aria-hidden="true">|</span>
                    <span>EST</span>
                    <span id="t-est" class="text-[10px] tracking-normal text-white/85 w-[4.25rem] tabular-nums">00:00:00</span>
                </div>
            </div>
            <div class="flex flex-wrap items-center justify-end gap-3 sm:gap-4 text-[8px] font-black uppercase tracking-[0.2em]">
                <div class="flex items-center gap-2 text-white/90">
                    <span class="hidden text-white/45 sm:inline">Heure locale</span>
                    <span id="clock-local" class="text-[10px] tracking-wider text-white tabular-nums sm:text-[11px]">00:00:00</span>
                </div>
                <span class="hidden h-3 w-px bg-white/15 sm:block" aria-hidden="true"></span>
                <div class="flex items-center gap-2 text-white/50">
                    <span class="relative flex h-1.5 w-1.5">
                        <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-25"></span>
                        <span class="relative inline-flex h-1.5 w-1.5 rounded-full bg-emerald-400/90"></span>
                    </span>
                    <span>Réseau actif</span>
                </div>
                <span class="hidden h-3 w-px bg-white/15 sm:block" aria-hidden="true"></span>
                <div class="flex items-center gap-2 text-white/40">
                    <span class="relative flex h-1.5 w-1.5">
                        <span class="relative inline-flex h-1.5 w-1.5 rounded-full bg-emerald-500/90"></span>
                    </span>
                    <span class="italic">Actif</span>
                </div>
            </div>
        </div>
    </div>

    <script>
        function formatClock(date, timeZone = 'UTC') {
            return new Intl.DateTimeFormat('en-GB', {
                hour: '2-digit', minute: '2-digit', second: '2-digit',
                hour12: false,
                timeZone: timeZone
            }).format(date);
        }

        function updateOperationalClocks() {
            const now = new Date();
            const zones = {
                't-zulu': 'UTC',
                't-pst': 'America/Los_Angeles',
                't-mtn': 'America/Denver',
                't-est': 'America/New_York',
                'clock-local': Intl.DateTimeFormat().resolvedOptions().timeZone
            };
            for (const [id, tz] of Object.entries(zones)) {
                const el = document.getElementById(id);
                if (el) el.textContent = formatClock(now, tz);
            }
        }

        setInterval(updateOperationalClocks, 1000);
        updateOperationalClocks();
    </script>
    <main class="min-h-screen bg-[#f8fafc] text-slate-900">
        <?php require base_path('views/partials/layout_flash_toasts.php'); ?>
        <?php
        $communityMemberships = $communityMemberships ?? [];
        $currentTid = (int) (\App\Core\Session::get('tenant_id') ?? 0);
        ?>

        <?php if ($currentTid === 1): ?>
        <section class="relative overflow-hidden border-b border-emerald-900/20 bg-gradient-to-br from-[#022c22] via-[#064e3b] to-[#0f172a] text-white">
            <div class="absolute inset-0 opacity-[0.07] pointer-events-none bg-[url('https://www.transparenttextures.com/patterns/carbon-fibre.png')]"></div>
            <div class="absolute top-0 right-0 w-[min(100%,480px)] h-[min(100%,320px)] bg-emerald-400/10 blur-3xl rounded-full -translate-y-1/2 translate-x-1/4"></div>
            <div class="relative max-w-7xl mx-auto px-6 md:px-10 py-12 md:py-16">
                <div class="max-w-3xl">
                    <p class="text-[10px] font-black uppercase tracking-[0.45em] text-emerald-300/90 mb-4">Sans organisation rattachée</p>
                    <h2 class="text-3xl md:text-5xl font-black uppercase italic tracking-tight leading-[1.05] text-white mb-5">
                        Rejoignez une unité ou une communauté
                    </h2>
                    <p class="text-sm md:text-base text-emerald-100/90 leading-relaxed mb-8">
                        Vous n’êtes rattaché à aucune organisation pour l’instant. Parcourez le registre des communautés,
                        ou utilisez un code d’invitation pour rejoindre votre unité.
                    </p>
                    <div class="flex flex-col sm:flex-row flex-wrap gap-4">
                        <a href="<?= url('communities') ?>" class="inline-flex items-center justify-center px-8 py-4 bg-emerald-500 text-[#022c22] text-xs font-black uppercase tracking-[0.2em] rounded-xl hover:bg-emerald-400 transition-colors shadow-lg shadow-black/20">
                            Ouvrir le registre des unités
                        </a>
                        <a href="<?= url('join') ?>" class="inline-flex items-center justify-center px-8 py-4 border-2 border-white/25 text-white text-xs font-black uppercase tracking-[0.2em] rounded-xl hover:bg-white/10 transition-colors">
                            Rejoindre avec un code
                        </a>
                        <a href="<?= url('communities/create') ?>" class="inline-flex items-center justify-center px-6 py-4 text-emerald-200/90 text-[11px] font-bold uppercase tracking-wider hover:text-white underline decoration-emerald-500/50 underline-offset-4">
                            Créer une communauté
                        </a>
                    </div>
                </div>
            </div>
        </section>
        <?php endif; ?>


        <?php
        $mission_briefing = $mission_briefing ?? null;
        $atakModDownloadUrl = $atakModDownloadUrl ?? null;
        if (is_array($mission_briefing) && ($currentTid ?? 0) > 1):
            $mbOp = $mission_briefing['next_op'] ?? null;
            $mbTrain = $mission_briefing['trainings'] ?? [];
            $mbMod = $mission_briefing['modpack'] ?? null;
            $mbExcerpt = $mission_briefing['consigne_excerpt'] ?? null;
            $mbPinsA = $mission_briefing['pins_anchor_href'] ?? url('dashboard');
            $dashOrdreJourTenantId = (int) $currentTid;
        ?>
        <div class="dash-ordre-du-jour-root border-b border-slate-200/80">
            <div id="dashboard-mission-briefing-collapsed" class="hidden bg-gradient-to-r from-slate-900 via-slate-800 to-slate-900 text-white">
                <div class="mx-auto flex max-w-5xl flex-wrap items-center justify-between gap-3 px-4 py-2.5 sm:px-8">
                    <p class="text-xs font-medium text-slate-400">L’ordre du jour et les rappels du jour sont masqués sur cet appareil.</p>
                    <button type="button" id="btn-show-ordre-jour" class="shrink-0 rounded-lg border border-emerald-500/40 bg-emerald-500/10 px-3 py-1.5 text-[10px] font-black uppercase tracking-wider text-emerald-200 transition hover:border-emerald-400/60 hover:bg-emerald-500/20 hover:text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-400/50">
                        Afficher l’ordre du jour
                    </button>
                </div>
            </div>
        <section id="dashboard-mission-briefing" class="relative overflow-hidden bg-slate-950 py-10 text-slate-100" aria-label="Préparation opérationnelle">
            <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_50%_-20%,rgba(51,65,85,0.15),transparent)]" aria-hidden="true"></div>
            <div class="relative mx-auto max-w-5xl px-6">
                <div class="mb-10 flex flex-wrap items-end justify-between gap-4">
                    <div class="min-w-0">
                        <p class="text-[10px] font-black uppercase tracking-[0.3em] text-slate-500">Focus</p>
                        <h2 class="mt-1 text-3xl font-light tracking-tight text-white">Préparer la mission</h2>
                        <p class="mt-2 text-sm text-slate-400/80">Prochaine séance, matériel et formations.</p>
                    </div>
                    <button type="button" id="btn-hide-ordre-jour" class="rounded-2xl border border-white/5 bg-white/5 px-4 py-2 text-[10px] font-bold uppercase tracking-widest text-slate-400 transition hover:bg-white/10 hover:text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-white/25" title="Masquer cette zone sur ce navigateur">
                        Masquer
                    </button>
                </div>

                <div class="grid gap-6 sm:grid-cols-2">
                    <article class="group relative rounded-3xl border border-white/[0.03] bg-slate-900/40 p-6 shadow-2xl transition duration-500 hover:bg-slate-900/60 hover:shadow-emerald-500/5">
                        <div class="flex items-center gap-4">
                            <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-white/5 text-slate-500 transition-colors group-hover:text-emerald-400" aria-hidden="true">
                                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5a2.25 2.25 0 002.25-2.25m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5a2.25 2.25 0 012.25 2.25v7.5"/></svg>
                            </span>
                            <p class="text-[10px] font-black uppercase tracking-[0.25em] text-slate-500">Calendrier</p>
                        </div>
                        <div class="mt-8 border-t border-white/[0.05] pt-6">
                            <?php if ($mbOp): ?>
                                <?php
                                $mbStart = $mbOp['starts_at'] ?? '';
                                $mbStartFmt = '';
                                if ($mbStart !== '') {
                                    $tsOp = strtotime($mbStart);
                                    $mbStartFmt = $tsOp !== false ? date('d/m/Y à H\hi', $tsOp) : '';
                                }
                                ?>
                                <p class="text-base font-bold leading-snug text-white"><?= htmlspecialchars($mbOp['title'], ENT_QUOTES, 'UTF-8') ?></p>
                                <?php if ($mbStartFmt !== ''): ?>
                                    <p class="mt-1 text-sm text-slate-400"><?= htmlspecialchars($mbStartFmt, ENT_QUOTES, 'UTF-8') ?></p>
                                <?php endif; ?>
                                <?php if (!empty($mbOp['rsvp_label'])): ?>
                                    <p class="mt-2 inline-flex rounded-xl border border-white/10 bg-white/[0.04] px-2.5 py-1 text-[11px] font-semibold text-slate-200"><?= htmlspecialchars((string) $mbOp['rsvp_label'], ENT_QUOTES, 'UTF-8') ?></p>
                                <?php endif; ?>
                                <?php if (!empty($mbOp['list_href'])): ?>
                                    <p class="mt-4">
                                        <a href="<?= htmlspecialchars($mbOp['list_href'], ENT_QUOTES, 'UTF-8') ?>" class="text-xs font-bold uppercase tracking-wider text-emerald-400/90 transition hover:text-emerald-300">Voir le calendrier</a>
                                    </p>
                                <?php endif; ?>
                            <?php else: ?>
                                <p class="text-sm font-medium italic text-slate-600">Aucune opération planifiée.</p>
                            <?php endif; ?>
                        </div>
                    </article>

                    <article class="group relative rounded-3xl border border-white/[0.03] bg-slate-900/40 p-6 shadow-2xl transition duration-500 hover:bg-slate-900/60 hover:shadow-sky-500/5">
                        <div class="flex items-center gap-4">
                            <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-white/5 text-slate-500 transition-colors group-hover:text-sky-400" aria-hidden="true">
                                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z"/></svg>
                            </span>
                            <p class="text-[10px] font-black uppercase tracking-[0.25em] text-slate-500">Ressources</p>
                        </div>
                        <div class="mt-8 border-t border-white/[0.05] pt-6">
                            <?php if (is_array($mbMod)): ?>
                                <a href="<?= htmlspecialchars($mbMod['detail_href'], ENT_QUOTES, 'UTF-8') ?>" class="text-base font-bold text-white transition hover:text-sky-400"><?= htmlspecialchars($mbMod['title'], ENT_QUOTES, 'UTF-8') ?></a>
                                <p class="mt-1 text-xs text-slate-500"><?= !empty($mbMod['has_pack']) ? 'Fiche et pack communautaire à jour.' : 'Parcourez les packs proposés pour votre unité.' ?></p>
                            <?php else: ?>
                                <p class="text-sm font-medium italic text-slate-600">Aucun pack principal renseigné pour l’instant.</p>
                            <?php endif; ?>
                            <?php if (!empty($atakModDownloadUrl)): ?>
                                <p class="mt-4">
                                    <a href="<?= htmlspecialchars((string) $atakModDownloadUrl, ENT_QUOTES, 'UTF-8') ?>" class="text-xs font-bold uppercase tracking-wider text-sky-400/90 transition hover:text-sky-300">Module terrain complémentaire</a>
                                </p>
                            <?php endif; ?>
                        </div>
                    </article>
                </div>

                <?php if ($mbExcerpt !== null && $mbExcerpt !== ''): ?>
                    <div class="mt-8 rounded-[2rem] border border-amber-500/20 bg-amber-950/20 p-5 shadow-inner">
                        <p class="text-[10px] font-black uppercase tracking-[0.2em] text-amber-200/90">Consigne communautaire</p>
                        <p class="mt-2 text-sm leading-relaxed text-amber-50/95"><?= htmlspecialchars($mbExcerpt, ENT_QUOTES, 'UTF-8') ?></p>
                        <a href="<?= htmlspecialchars($mbPinsA, ENT_QUOTES, 'UTF-8') ?>" class="mt-3 inline-flex text-xs font-bold text-amber-200 transition hover:text-white">Voir les raccourcis</a>
                    </div>
                <?php endif; ?>

                <?php if ($mbTrain !== []): ?>
                    <?php
                    $mbTrainProgressSum = 0;
                    $mbTrainProgressN = 0;
                    foreach ($mbTrain as $_t) {
                        if (isset($_t['progress_pct']) && is_numeric($_t['progress_pct'])) {
                            $mbTrainProgressSum += (int) $_t['progress_pct'];
                            $mbTrainProgressN++;
                        }
                    }
                    $mbTrainProgressAvg = $mbTrainProgressN > 0 ? (int) round($mbTrainProgressSum / $mbTrainProgressN) : null;
                    ?>
                    <div class="mt-8 rounded-[2rem] border border-white/[0.03] bg-slate-900/20 p-2 shadow-inner">
                        <div class="flex flex-wrap items-center justify-between gap-4 px-6 py-5">
                            <div class="flex min-w-0 items-center gap-5">
                                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-violet-500/10 text-violet-400" aria-hidden="true">
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M4.26 10.147a60.438 60.438 0 0 0-.491 6.347A48.62 48.62 0 0 1 12 20.904a48.62 48.62 0 0 1 8.232-4.41 60.46 60.46 0 0 0-.491-6.347m-15.482 0a50.636 50.636 0 0 0-2.658-.813A59.906 59.906 0 0 1 12 3.493a59.903 59.903 0 0 1 10.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.717 50.717 0 0 1 12 13.489a50.702 50.702 0 0 1 7.74-3.342M6.75 15a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5Zm0 0v-3.675A55.378 55.378 0 0 1 12 8.443m-7.007 11.55A5.981 5.981 0 0 0 6.75 15.75v-1.5"/></svg>
                                </span>
                                <div class="min-w-0">
                                    <p class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-500">Cursus</p>
                                    <?php if ($mbTrainProgressAvg !== null): ?>
                                        <p class="text-xs font-bold text-white">Progression : <span class="text-violet-400 italic tabular-nums"><?= (int) $mbTrainProgressAvg ?> %</span></p>
                                    <?php else: ?>
                                        <p class="text-xs font-bold text-white">Formations à suivre</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <a href="<?= htmlspecialchars(url('formations/mes-formations'), ENT_QUOTES, 'UTF-8') ?>" class="rounded-xl border border-white/5 bg-white/5 px-5 py-2.5 text-[10px] font-black uppercase tracking-widest text-white transition hover:bg-white/10">Mes formations</a>
                        </div>
                        <div class="space-y-1 px-2 pb-2">
                            <?php foreach ($mbTrain as $t): ?>
                                <?php $tPct = isset($t['progress_pct']) ? max(0, min(100, (int) $t['progress_pct'])) : null; ?>
                                <a href="<?= htmlspecialchars($t['href'], ENT_QUOTES, 'UTF-8') ?>" class="group block rounded-[1.5rem] bg-white/[0.02] p-5 transition hover:bg-white/[0.05] <?= !empty($t['urgent']) ? 'ring-1 ring-rose-500/30' : '' ?>">
                                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between sm:gap-4">
                                        <span class="text-sm font-medium text-slate-300 group-hover:text-white"><?= htmlspecialchars($t['title'], ENT_QUOTES, 'UTF-8') ?></span>
                                        <?php if ($tPct !== null): ?>
                                            <span class="text-[10px] font-bold tabular-nums text-slate-500 sm:shrink-0"><?= $tPct ?>%</span>
                                        <?php endif; ?>
                                    </div>
                                    <?php
                                    $tSub = isset($t['subtitle']) ? trim((string) $t['subtitle']) : '';
                                    if ($tSub !== ''):
                                    ?>
                                        <p class="mt-1 text-xs text-slate-500"><?= htmlspecialchars($tSub, ENT_QUOTES, 'UTF-8') ?></p>
                                    <?php endif; ?>
                                    <?php if ($tPct !== null): ?>
                                        <div class="mt-4 h-1 w-full overflow-hidden rounded-full bg-white/5" role="progressbar" aria-valuenow="<?= $tPct ?>" aria-valuemin="0" aria-valuemax="100">
                                            <div class="h-full bg-white transition-all duration-700 ease-out group-hover:bg-violet-400" style="width: <?= $tPct ?>%"></div>
                                        </div>
                                    <?php endif; ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </section>
        </div>
        <script>
        (function () {
            var tid = <?= (int) $dashOrdreJourTenantId ?>;
            var sec = document.getElementById('dashboard-mission-briefing');
            var col = document.getElementById('dashboard-mission-briefing-collapsed');
            var btnHide = document.getElementById('btn-hide-ordre-jour');
            var btnShow = document.getElementById('btn-show-ordre-jour');
            if (!tid || tid <= 1 || !sec || !col) return;
            var key = 'athena_dash_ordre_jour_masque_' + String(tid);
            function apply() {
                var hidden = localStorage.getItem(key) === '1';
                sec.classList.toggle('hidden', hidden);
                col.classList.toggle('hidden', !hidden);
            }
            apply();
            if (btnHide) btnHide.addEventListener('click', function () { localStorage.setItem(key, '1'); apply(); });
            if (btnShow) btnShow.addEventListener('click', function () { localStorage.removeItem(key); apply(); });
        })();
        </script>
        <?php endif; ?>

        <?php
        $showFounderTrialBanner = $show_founder_trial_banner ?? false;
        $founderTrialEndsAt = $founder_trial_ends_at ?? null;
        $dashCtxCommunity = count($communityMemberships) > 0;
        $dashCtxTrial = $showFounderTrialBanner && is_string($founderTrialEndsAt) && $founderTrialEndsAt !== '';
        ?>
        <?php if ($dashCtxCommunity || $dashCtxTrial): ?>
        <section class="border-b border-slate-200/90 bg-gradient-to-b from-slate-50/95 to-white" aria-label="Contexte de session">
            <div class="mx-auto max-w-5xl space-y-3 px-4 py-3 sm:px-8">
                <?php if ($dashCtxCommunity): ?>
                <div class="flex min-w-0 flex-wrap items-center gap-x-3 gap-y-2 text-[11px] leading-snug">
                    <span class="shrink-0 text-[10px] font-black uppercase tracking-wider text-slate-500">Communauté</span>
                    <div class="flex min-w-0 flex-1 flex-wrap items-center gap-2">
                        <?php foreach ($communityMemberships as $m): ?>
                            <?php if ((int) $m['tenant_id'] === $currentTid): ?>
                                <span class="rounded-lg bg-emerald-100 px-2.5 py-1 font-bold text-emerald-900"><?= htmlspecialchars(community_display_name($m)) ?></span>
                            <?php else: ?>
                                <form method="post" action="<?= url('community/switch') ?>" class="inline" onsubmit="var b=this.querySelector('button[type=submit]');if(b){b.disabled=true;b.setAttribute('aria-busy','true');b.textContent='Chargement…';}">
                                    <?= \App\Core\Csrf::field() ?>
                                    <input type="hidden" name="tenant_id" value="<?= (int) $m['tenant_id'] ?>">
                                    <button type="submit" class="font-semibold text-slate-600 underline decoration-slate-300 underline-offset-2 hover:text-emerald-700"><?= htmlspecialchars(community_display_name($m)) ?></button>
                                </form>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                    <details class="dash-vers-details relative shrink-0">
                        <summary class="inline-flex cursor-pointer items-center gap-2 rounded-full border border-slate-200/90 bg-white px-3 py-1.5 text-[10px] font-black uppercase tracking-[0.18em] text-slate-700 shadow-sm transition hover:border-emerald-200 hover:bg-emerald-50/50 hover:text-emerald-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/40">
                            <svg class="h-4 w-4 shrink-0 text-emerald-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 008.716-6.747M12 21a9.004 9.004 0 01-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 017.843 4.582M12 3a8.997 8.997 0 00-7.843 4.582m15.686 0A11.953 11.953 0 0112 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0121 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0112 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 013 12c0-1.605.42-3.113 1.157-4.418" />
                            </svg>
                            Vers
                        </summary>
                        <div class="absolute right-0 z-30 mt-1.5 min-w-[14rem] overflow-hidden rounded-xl border border-slate-200/90 bg-white py-1 shadow-lg shadow-slate-900/10 ring-1 ring-black/[0.03]" role="menu">
                            <a href="<?= url('platform/invite-unit') ?>" class="block px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 hover:text-emerald-800" role="menuitem">Inviter une unité</a>
                            <a href="<?= url('communities/create') ?>" class="block border-t border-slate-100 px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-emerald-50/80 hover:text-emerald-900" role="menuitem">Nouvelle communauté</a>
                        </div>
                    </details>
                </div>
                <?php endif; ?>
                <?php if ($dashCtxTrial): ?>
                <div class="relative flex flex-col gap-3 overflow-hidden rounded-2xl border border-amber-300/80 bg-gradient-to-br from-amber-50 via-white to-orange-50/70 p-4 shadow-[0_16px_35px_-22px_rgba(180,83,9,0.45)] ring-1 ring-amber-200/40 sm:flex-row sm:items-center sm:gap-5 sm:p-5">
                    <span class="pointer-events-none absolute -right-8 -top-10 h-32 w-32 rounded-full bg-amber-200/35 blur-2xl" aria-hidden="true"></span>
                    <div class="flex shrink-0 items-center justify-center rounded-xl bg-amber-100/80 p-2.5 text-amber-900 ring-1 ring-amber-200/60 sm:p-3" aria-hidden="true">
                        <svg class="h-7 w-7 sm:h-8 sm:w-8" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09zM18.259 8.715L18 9.75l-.259-1.035a3.375 3.375 0 00-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 002.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 002.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 00-2.456 2.456zM16.894 20.567L16.5 21.75l-.394-1.183a2.25 2.25 0 00-1.423-1.423L13.5 18.75l1.183-.394a2.25 2.25 0 001.423-1.423l.394-1.183.394 1.183a2.25 2.25 0 001.423 1.423l1.183.394-1.183.394a2.25 2.25 0 00-1.423 1.423z" />
                        </svg>
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="text-[10px] font-black uppercase tracking-[0.24em] text-amber-900">Essai étendu — Fondateur</p>
                        <p class="mt-1 text-sm leading-snug text-slate-700">
                            Votre accès premium est actif et débloque toutes les fonctionnalités avancées jusqu’au <strong class="font-bold text-slate-900"><?= htmlspecialchars(date('d/m/Y', strtotime($founderTrialEndsAt))) ?></strong>.
                        </p>
                    </div>
                    <a href="<?= url('platform/upgrade') ?>" class="inline-flex shrink-0 items-center justify-center rounded-xl bg-slate-900 px-5 py-2.5 text-center text-[10px] font-black uppercase tracking-[0.2em] text-white shadow-sm transition hover:bg-slate-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-400/80 sm:self-stretch sm:py-0 sm:min-h-[2.75rem]">
                        Découvrir les offres
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </section>
        <?php endif; ?>

        <?php
        $next_steps = $dashboard_next_steps ?? [];
        if ($next_steps !== []): ?>
        <div class="mx-auto max-w-5xl px-4 sm:px-8 md:px-10">
            <?php
            $next_steps_title = 'Prochaines étapes sur le portail';
            $next_steps_intro = 'Pistes basées sur votre situation actuelle dans cette communauté.';
            require base_path('views/partials/ui/next_steps_block.php');
            ?>
        </div>
        <?php endif; ?>

        <?php
        $my_enlistments_pending = $my_enlistments_pending ?? [];
        $staff_enlistments_pending = $staff_enlistments_pending ?? [];
        $show_staff_enlistments = $show_staff_enlistments ?? false;
        require base_path('views/partials/dashboard_enlistments.php');
        ?>

        <?php
        $dashboard_pins = $dashboard_pins ?? [];
        if (!empty($dashboard_pins)):
        ?>
        <section id="dashboard-community-pins" class="border-b border-slate-200 bg-white scroll-mt-24">
            <div class="max-w-7xl mx-auto px-6 md:px-10 py-8">
                <div class="flex flex-wrap items-end justify-between gap-4 mb-5">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-[0.35em] text-slate-400 mb-1">Communauté</p>
                        <h2 class="text-lg font-black uppercase italic tracking-tight text-[#001529]">Raccourcis</h2>
                    </div>
                    <?php if (\App\Core\Gate::getInstance()->allows('dashboard.pins.manage')): ?>
                        <a href="<?= url('back-office/dashboard-pins') ?>" class="text-[10px] font-black uppercase tracking-wider text-emerald-700 hover:text-slate-900">Gérer</a>
                    <?php endif; ?>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-3">
                    <?php foreach ($dashboard_pins as $pin): ?>
                        <?php
                        $pk = (string) ($pin['kind'] ?? '');
                        $icon = match ($pk) {
                            'document_category' => 'M',
                            'document' => 'D',
                            'courrier_document' => 'C',
                            'external_url' => '↗',
                            'notice' => 'i',
                            default => '•',
                        };
                        ?>
                        <?php if ($pk === 'notice' && !empty($pin['notice_text'])): ?>
                            <div class="rounded-xl border border-amber-200/90 bg-amber-50/40 p-4 sm:col-span-2 xl:col-span-3">
                                <div class="flex items-start gap-3">
                                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-amber-100 text-[10px] font-black text-amber-900"><?= htmlspecialchars($icon, ENT_QUOTES, 'UTF-8') ?></span>
                                    <div class="min-w-0">
                                        <p class="text-[10px] font-black uppercase tracking-wider text-amber-900/80 mb-1"><?= htmlspecialchars((string) ($pin['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                                        <div class="text-sm text-slate-800 whitespace-pre-wrap"><?= nl2br(htmlspecialchars((string) ($pin['notice_text'] ?? ''), ENT_QUOTES, 'UTF-8')) ?></div>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <a href="<?= htmlspecialchars((string) ($pin['href'] ?? '#'), ENT_QUOTES, 'UTF-8') ?>" class="group flex items-center gap-3 rounded-xl border border-slate-200 bg-slate-50/50 p-4 hover:border-emerald-300 hover:bg-white hover:shadow-sm transition-all">
                                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-white border border-slate-200 text-[11px] font-black text-slate-500 group-hover:border-emerald-200 group-hover:text-emerald-700"><?= htmlspecialchars($icon, ENT_QUOTES, 'UTF-8') ?></span>
                                <span class="min-w-0 text-sm font-bold text-slate-800 truncate group-hover:text-slate-900"><?= htmlspecialchars((string) ($pin['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                            </a>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
        <?php endif; ?>

        <!-- HERO / HUB -->
        <section class="relative overflow-hidden border-b border-slate-200 bg-white">
            <div class="absolute inset-0 bg-[radial-gradient(circle_at_top_right,rgba(37,99,235,0.08),transparent_30%),radial-gradient(circle_at_bottom_left,rgba(15,23,42,0.05),transparent_35%)]"></div>
    
            <div class="relative max-w-7xl mx-auto px-6 md:px-10 py-10 md:py-14">
                <div class="grid grid-cols-1 xl:grid-cols-[1.25fr_0.75fr] gap-8 items-start">
    
                    <!-- Bloc principal -->
                    <div class="space-y-8">
                        <div class="flex flex-wrap items-center gap-3">
                            <span class="inline-flex items-center gap-2 px-3 py-1.5 bg-emerald-50 border border-emerald-200 text-emerald-700 text-[10px] font-black uppercase tracking-[0.2em]">
                                <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                                Session sécurisée active
                            </span>
    
                            <span class="inline-flex items-center px-3 py-1.5 bg-slate-100 border border-slate-200 text-slate-600 text-[10px] font-black uppercase tracking-[0.2em]">
                                Niveau d’accès : opérateur
                            </span>
                        </div>
    
                        <div class="max-w-4xl">
                            <p class="text-[11px] font-black uppercase tracking-[0.35em] text-slate-400 mb-3">
                                <?php if ($dashboard_tenant_label !== null && $dashboard_tenant_label !== ''): ?>
                                    <?= htmlspecialchars('Communauté · ' . $dashboard_tenant_label) ?>
                                <?php else: ?>
                                    Centre de commandement
                                <?php endif; ?>
                            </p>
    
                            <h1 class="text-4xl md:text-6xl font-black uppercase italic tracking-[-0.04em] text-[#001529] leading-none">
                                Hub opérationnel
                            </h1>
    
                            <p class="mt-5 max-w-2xl text-sm md:text-base text-slate-600 leading-relaxed">
                                Votre point d’entrée pour la vie de l’unité : cartographie, organigramme, formations, documents et outils tactiques.
                                Les raccourcis ci-dessous regroupent l’essentiel ; le menu latéral donne accès au reste du portail.
                            </p>
                        </div>
    
                        <!-- Actions critiques -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <a href="<?= url('atak') ?>" class="group flex items-center justify-between p-5 bg-white border border-slate-200 hover:shadow-xl hover:shadow-slate-100 transition-all">
                                <div class="space-y-1">
                                    <span class="text-[8px] font-black text-slate-400 uppercase tracking-[0.3em]">Tactique</span>
                                    <h3 class="text-sm font-black uppercase tracking-wider text-slate-800">ATAK / Tacmap</h3>
                                </div>
                                <svg class="w-4 h-4 text-slate-300 group-hover:text-emerald-600 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/></svg>
                            </a>
                            <a href="<?= url('orbat') ?>" class="group flex items-center justify-between p-5 bg-white border border-slate-200 hover:shadow-xl hover:shadow-slate-100 transition-all">
                                <div class="space-y-1">
                                    <span class="text-[8px] font-black text-slate-400 uppercase tracking-[0.3em]">Organisation</span>
                                    <h3 class="text-sm font-black uppercase tracking-wider text-slate-800">ORBAT / Unité</h3>
                                </div>
                                <svg class="w-4 h-4 text-slate-300 group-hover:text-emerald-600 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                            </a>
                            <a href="<?= url('formations') ?>" class="group flex items-center justify-between p-5 bg-white border border-slate-200 hover:shadow-xl hover:shadow-slate-100 transition-all">
                                <div class="space-y-1">
                                    <span class="text-[8px] font-black text-slate-400 uppercase tracking-[0.3em]">Instruction</span>
                                    <h3 class="text-sm font-black uppercase tracking-wider text-slate-800">Formations</h3>
                                </div>
                                <svg class="h-5 w-5 shrink-0 text-slate-300 transition-colors group-hover:text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                            </a>
                            <a href="<?= url('documents') ?>" class="group flex items-center justify-between p-5 bg-white border border-slate-200 hover:shadow-xl hover:shadow-slate-100 transition-all">
                                <div class="space-y-1">
                                    <span class="text-[8px] font-black text-slate-400 uppercase tracking-[0.3em]">Référence</span>
                                    <h3 class="text-sm font-black uppercase tracking-wider text-slate-800">Documents</h3>
                                </div>
                                <svg class="w-4 h-4 text-slate-300 group-hover:text-emerald-600 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                            </a>
                        </div>
                    </div>
    
                    <!-- Bloc situation Modpack -->
                    <aside class="bg-slate-900 text-white p-6 md:p-7 border border-slate-800 shadow-2xl relative overflow-hidden group xl:sticky xl:top-24 xl:self-start">
                        <div class="absolute inset-0 opacity-[0.03] pointer-events-none bg-[url('https://www.transparenttextures.com/patterns/carbon-fibre.png')]"></div>
                        <div class="relative z-10">
                            <div class="flex items-center justify-between gap-4">
                                <div>
                                    <p class="text-[10px] font-black uppercase tracking-[0.25em] text-emerald-500">Infrastructure</p>
                                    <h2 class="mt-2 text-xl font-black uppercase italic tracking-tight text-white">Modpack Arma 3</h2>
                                </div>
                                <div class="w-10 h-10 border border-white/10 flex items-center justify-center bg-white/5">
                                    <svg class="w-5 h-5 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 19l3 3m0 0l3-3m-3 3V10"/>
                                    </svg>
                                </div>
                            </div>
                            <?php
                            $modpack = $modpack ?? null;
                            if ($modpack):
                                $sizeFormatted = '—';
                                if (!empty($modpack['size'])) {
                                    $b = (int) $modpack['size'];
                                    $sizeFormatted = $b >= 1073741824 ? number_format($b / 1073741824, 1, ',', ' ') . ' Go' : ($b >= 1048576 ? number_format($b / 1048576, 1, ',', ' ') . ' Mo' : number_format($b / 1024, 1, ',', ' ') . ' Ko');
                                }
                                $updatedAt = !empty($modpack['updated_at']) ? date('d.m.y', strtotime($modpack['updated_at'])) : '—';
                                $detailUrl = url('modpacks/' . htmlspecialchars($modpack['slug']));
                                $downloadUrl = url('modpacks/' . $modpack['id'] . '/download');
                            ?>
                            <div class="mt-8 space-y-4">
                                <div class="flex items-center justify-between p-3 border border-white/5 bg-white/[0.02]">
                                    <div class="flex flex-col">
                                        <span class="text-[9px] font-black text-white/30 uppercase tracking-widest">Version Actuelle</span>
                                        <span class="text-sm font-mono font-bold text-white"><?= htmlspecialchars($modpack['version'] ?? '—') ?></span>
                                    </div>
                                    <span class="px-2 py-1 bg-emerald-500/10 text-emerald-400 text-[8px] font-black rounded-sm border border-emerald-500/20">À JOUR</span>
                                </div>
                                <div class="grid grid-cols-2 gap-3">
                                    <div class="p-3 border border-white/5 bg-white/[0.02]">
                                        <span class="text-[9px] font-black text-white/30 uppercase tracking-widest block">Taille Totale</span>
                                        <span class="text-sm font-mono font-bold text-white"><?= $sizeFormatted ?></span>
                                    </div>
                                    <div class="p-3 border border-white/5 bg-white/[0.02]">
                                        <span class="text-[9px] font-black text-white/30 uppercase tracking-widest block">Dernière mise à jour</span>
                                        <span class="text-sm font-mono font-bold text-white"><?= $updatedAt ?></span>
                                    </div>
                                </div>
                                <div class="flex items-center gap-3 p-3 text-[10px] tracking-wider text-white/60">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 shadow-[0_0_8px_rgba(16,185,129,0.8)]"></span>
                                    Dépôt synchronisé — prêt à l’emploi
                                </div>
                            </div>
                            <div class="mt-8">
                                <a href="<?= $downloadUrl ?>" class="flex items-center justify-center gap-3 w-full py-4 bg-emerald-600 hover:bg-emerald-500 text-white transition-all duration-300 shadow-lg shadow-emerald-900/20 group/btn">
                                    <span class="text-[11px] font-black uppercase tracking-[0.3em]">Télécharger le modpack</span>
                                    <svg class="w-4 h-4 transition-transform duration-500 group-hover/btn:translate-y-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M19 14l-7 7m0 0l-7-7m7 7V3"/>
                                    </svg>
                                </a>
                                <p class="mt-3 text-center">
                                    <a href="<?= $detailUrl ?>" class="text-[8px] font-bold text-white/40 hover:text-white/70 uppercase tracking-[0.2em]">Voir la fiche modpack</a>
                                </p>
                            </div>
                            <?php else: ?>
                            <div class="mt-8 space-y-4">
                                <p class="text-sm text-white/60">Aucun pack n’est encore publié pour votre communauté.</p>
                                <?php if (function_exists('can') && can('admin.access')): ?>
                                <a href="<?= url('admin/modpacks/create') ?>" class="inline-block rounded-lg border border-white/20 bg-white/10 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-white/20">Configurer un modpack</a>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </aside>

                    <!-- Mod COMSPEC ATAK -->
                    <?php $atakModDownloadUrl = $atakModDownloadUrl ?? null; if ($atakModDownloadUrl): ?>
                    <aside class="bg-slate-900 text-white p-6 md:p-7 border border-slate-800 shadow-2xl relative overflow-hidden group">
                        <div class="absolute inset-0 opacity-[0.03] pointer-events-none bg-[url('https://www.transparenttextures.com/patterns/carbon-fibre.png')]"></div>
                        <div class="relative z-10">
                            <div class="flex items-center justify-between gap-4">
                                <div>
                                    <p class="text-[10px] font-black uppercase tracking-[0.25em] text-emerald-500">Tactique</p>
                                    <h2 class="mt-2 text-xl font-black uppercase italic tracking-tight text-white">Mod COMSPEC ATAK</h2>
                                </div>
                                <div class="w-10 h-10 border border-white/10 flex items-center justify-center bg-white/5">
                                    <svg class="w-5 h-5 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                                    </svg>
                                </div>
                            </div>
                            <p class="mt-4 text-sm text-white/70">Téléchargez le mod ATAK COMSPEC Overwatch pour la carte tactique et la synchronisation avec le serveur.</p>
                            <div class="mt-6">
                                <a href="<?= htmlspecialchars($atakModDownloadUrl) ?>" class="flex items-center justify-center gap-3 w-full py-4 bg-emerald-600 hover:bg-emerald-500 text-white transition-all duration-300 shadow-lg shadow-emerald-900/20 group/btn">
                                    <span class="text-[11px] font-black uppercase tracking-[0.3em]">Télécharger le mod ATAK</span>
                                    <svg class="w-4 h-4 transition-transform duration-500 group-hover/btn:translate-y-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M19 14l-7 7m0 0l-7-7m7 7V3"/>
                                    </svg>
                                </a>
                                <p class="mt-3 text-center">
                                    <a href="<?= url('atak') ?>" class="text-[8px] font-bold text-white/40 hover:text-white/70 uppercase tracking-[0.2em]">Page ATAK / Tacmap</a>
                                </p>
                            </div>
                        </div>
                    </aside>
                    <?php endif; ?>
                </div>
            </div>
        </section>
        <div class="py-12 flex justify-center">
            <div class="w-1 h-1 bg-slate-300 rounded-full"></div>
        </div>
        <?php if ($showcase_training_feature): ?>
        <section class="py-12 bg-slate-50 overflow-hidden" <?php if (!empty($showcase_items)): ?>x-data="trainingShowcase"<?php endif; ?>>
            <div class="max-w-7xl mx-auto px-6 mb-8 flex justify-between items-end gap-4 flex-wrap">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-[0.3em] text-blue-600 mb-2">Catalogue</p>
                    <h2 class="text-3xl font-black uppercase tracking-tighter text-slate-900 italic">Nos formations</h2>
                    <?php if ($dashboard_tenant_label !== null && $dashboard_tenant_label !== ''): ?>
                    <p class="mt-2 text-xs font-bold text-slate-500 uppercase tracking-wider"><?= htmlspecialchars($dashboard_tenant_label) ?></p>
                    <?php endif; ?>
                </div>
                <?php if (!empty($showcase_items)): ?>
                <div class="flex gap-2">
                    <button type="button" @click="scrollTrack(-360)" class="p-3 border border-slate-200 rounded-full hover:bg-white transition-colors" aria-label="Défiler vers la gauche">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                    </button>
                    <button type="button" @click="scrollTrack(360)" class="p-3 border border-slate-200 rounded-full hover:bg-white transition-colors" aria-label="Défiler vers la droite">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </button>
                </div>
                <?php endif; ?>
            </div>

            <?php if (empty($showcase_items)): ?>
            <div class="max-w-7xl mx-auto px-6 pb-12">
                <p class="text-slate-600 text-sm mb-4">Aucune formation publiée pour cette communauté pour le moment.</p>
                <div class="flex flex-wrap gap-3">
                    <a href="<?= url('formations') ?>" class="inline-flex items-center px-4 py-2 bg-slate-900 text-white text-xs font-bold uppercase tracking-wider rounded-lg hover:bg-blue-700 transition-colors">Ouvrir le catalogue</a>
                    <?php if (function_exists('can') && (can('training.update') || can('training.publish') || can('admin.access') || can('training.manage'))): ?>
                    <a href="<?= url('back-office/ressources/training/courses') ?>" class="inline-flex items-center px-4 py-2 border border-slate-300 text-slate-800 text-xs font-bold uppercase tracking-wider rounded-lg hover:bg-white">Gérer les parcours</a>
                    <?php endif; ?>
                </div>
            </div>
            <?php else: ?>
            <div x-ref="track" class="flex gap-4 overflow-x-auto pb-12 px-[max(1.5rem,calc((100vw-80rem)/2))] no-scrollbar snap-x">
                <?php foreach ($showcase_items as $sc): ?>
                <div
                    @click="openModal = <?= (int) $sc['id'] ?>"
                    class="flex-none w-72 h-[450px] relative group cursor-pointer snap-start overflow-hidden rounded-3xl transition-all duration-500 hover:-translate-y-2 hover:shadow-2xl <?= ($sc['card_style'] ?? '') === 'grayscale' ? '' : 'hover:shadow-blue-900/20' ?>"
                >
                    <img src="<?= htmlspecialchars($sc['thumb']) ?>" class="absolute inset-0 w-full h-full object-cover transition-transform duration-700 group-hover:scale-110 <?= ($sc['card_style'] ?? '') === 'grayscale' ? 'grayscale group-hover:grayscale-0' : '' ?>" alt="<?= htmlspecialchars($sc['title']) ?>">
                    <div class="absolute inset-0 bg-gradient-to-t <?= ($sc['card_style'] ?? '') === 'grayscale' ? 'from-slate-900 via-slate-900/40' : 'from-slate-900 via-slate-900/20' ?> to-transparent"></div>
                    <div class="absolute bottom-0 left-0 p-6 w-full">
                        <div class="flex items-center gap-2 mb-3">
                            <span class="px-2 py-0.5 <?= htmlspecialchars($sc['badge_classes']) ?> text-[8px] font-black text-white uppercase tracking-widest rounded"><?= htmlspecialchars($sc['badge_label']) ?></span>
                        </div>
                        <h3 class="text-xl font-black text-white uppercase italic leading-none tracking-tighter mb-1"><?= htmlspecialchars($sc['title']) ?></h3>
                        <p class="text-[10px] text-slate-300 font-bold uppercase tracking-[0.2em]"><?= htmlspecialchars($sc['card_line']) ?></p>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <template x-if="openModal !== null">
                <div class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6">
                    <div class="absolute inset-0 bg-slate-950/90 backdrop-blur-sm" @click="openModal = null"></div>
                    <div class="relative bg-white w-full max-w-4xl max-h-[85vh] overflow-hidden rounded-[2.5rem] shadow-2xl flex flex-col md:flex-row"
                         x-transition:enter="transition ease-out duration-300"
                         x-transition:enter-start="opacity-0 scale-95"
                         x-transition:enter-end="opacity-100 scale-100">
                        <button type="button" @click="openModal = null" class="absolute top-6 right-6 z-10 p-2 bg-slate-100 rounded-full hover:bg-slate-200 transition-colors" aria-label="Fermer">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                        <div class="w-full md:w-1/2 h-64 md:h-auto min-h-[16rem] bg-slate-900 shrink-0" x-show="active()">
                            <img :src="active() ? active().banner : ''" :alt="active() ? active().title : ''" class="w-full h-full object-cover opacity-90">
                        </div>
                        <div class="flex-1 p-8 md:p-12 overflow-y-auto bg-white min-h-0" x-show="active()">
                            <p class="text-[10px] font-black uppercase tracking-[0.4em] text-blue-600 mb-4 italic">Détails formation</p>
                            <h2 class="text-3xl md:text-4xl font-black uppercase tracking-tighter text-slate-900 mb-6 italic leading-none" x-text="active().title"></h2>
                            <div class="grid grid-cols-2 gap-6 mb-10 border-y border-slate-100 py-8">
                                <div>
                                    <span class="block text-[8px] font-black text-slate-400 uppercase tracking-widest mb-1">Date du cycle</span>
                                    <span class="text-sm font-bold text-slate-900 uppercase" x-text="active().cycle_display"></span>
                                </div>
                                <div>
                                    <span class="block text-[8px] font-black text-slate-400 uppercase tracking-widest mb-1">Lieu d'affectation</span>
                                    <span class="text-sm font-bold text-slate-900 uppercase" x-text="active().location_display"></span>
                                </div>
                            </div>
                            <p class="text-slate-600 leading-relaxed mb-10 whitespace-pre-wrap" x-text="active().description"></p>
                            <a :href="active().course_url" class="block w-full text-center py-5 bg-slate-900 text-white text-[11px] font-black uppercase tracking-[0.3em] rounded-2xl hover:bg-blue-600 transition-all hover:translate-y-[-2px]">
                                Ouvrir la formation
                            </a>
                        </div>
                    </div>
                </div>
            </template>
            <?php endif; ?>
        </section>
        <?php endif; ?>
        
        <style>
            /* Pour cacher la scrollbar mais garder le défilement */
            .no-scrollbar::-webkit-scrollbar { display: none; }
            .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
        </style>
        <section class="max-w-7xl mx-auto px-6 md:px-10 py-12">
            <div class="grid grid-cols-1 2xl:grid-cols-[1.2fr_0.8fr] gap-12">
        
                <div class="space-y-12">

                    <?php if (is_array($dashboard_tester_program) && !empty($dashboard_tester_program['communities'])): ?>
                    <section class="rounded-3xl border-2 border-amber-300/60 bg-gradient-to-br from-amber-50/90 via-white to-violet-50/50 p-8 shadow-md shadow-amber-900/5" aria-labelledby="dash-tester-heading">
                        <div class="flex flex-col gap-8 lg:flex-row lg:items-start lg:justify-between">
                            <div class="min-w-0 max-w-2xl">
                                <p id="dash-tester-heading" class="text-[10px] font-black uppercase tracking-[0.3em] text-amber-800">Programme de préqualification</p>
                                <h2 class="mt-2 text-2xl font-black uppercase tracking-tight text-slate-900">Accès anticipé activé</h2>
                                <p class="mt-4 text-sm leading-relaxed text-slate-600">
                                    Vous faites partie d’un dispositif de validation : les raccourcis ci-dessous ouvrent les parties du portail concernées par les versions en cours d’évaluation pour votre communauté.
                                </p>
                                <ul class="mt-6 flex flex-wrap gap-2" aria-label="Dispositifs auxquels vous participez">
                                    <?php foreach ($dashboard_tester_program['communities'] as $tc): ?>
                                        <li class="inline-flex items-center rounded-full border border-amber-200 bg-white/90 px-3 py-1.5 text-xs font-semibold text-amber-950">
                                            <?= htmlspecialchars((string) ($tc['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                            <div class="flex shrink-0 flex-col gap-3 sm:flex-row sm:flex-wrap sm:justify-end">
                                <a href="<?= htmlspecialchars(url('personnel/mon-espace-rh'), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center justify-center rounded-xl border border-slate-300 bg-white px-5 py-3 text-xs font-black uppercase tracking-wider text-slate-800 shadow-sm transition hover:bg-slate-50">
                                    Espace RH et formations
                                </a>
                                <a href="<?= htmlspecialchars(url('hub'), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center justify-center rounded-xl bg-slate-900 px-5 py-3 text-xs font-black uppercase tracking-wider text-white shadow-sm transition hover:bg-slate-800">
                                    Centre opérationnel
                                </a>
                            </div>
                        </div>

                        <?php
                        $dtMods = $dashboard_tester_program['modules'] ?? [];
                        $dtMods = is_array($dtMods) ? $dtMods : [];
                        ?>
                        <?php if ($dtMods !== []): ?>
                            <div class="mt-10 space-y-6 border-t border-amber-200/60 pt-10">
                                <?php foreach ($dtMods as $mod): ?>
                                    <?php if (!is_array($mod)) {
                                        continue;
                                    } ?>
                                    <div class="rounded-2xl border border-slate-200/80 bg-white/90 p-6 sm:p-7">
                                        <h3 class="text-sm font-black uppercase tracking-wide text-slate-900"><?= htmlspecialchars((string) ($mod['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></h3>
                                        <?php if (!empty($mod['notice'])): ?>
                                            <p class="mt-3 text-xs font-semibold leading-relaxed text-amber-900"><?= htmlspecialchars((string) $mod['notice'], ENT_QUOTES, 'UTF-8') ?></p>
                                        <?php endif; ?>
                                        <div class="mt-5 flex flex-wrap gap-2 sm:gap-3">
                                            <?php foreach (($mod['links'] ?? []) as $lnk): ?>
                                                <?php
                                                if (!is_array($lnk) || empty($lnk['href'])) {
                                                    continue;
                                                }
                                                ?>
                                                <a href="<?= htmlspecialchars((string) $lnk['href'], ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center justify-center rounded-lg bg-violet-600 px-4 py-2.5 text-xs font-bold text-white shadow-sm transition hover:bg-violet-700">
                                                    <?= htmlspecialchars((string) ($lnk['label'] ?? 'Ouvrir'), ENT_QUOTES, 'UTF-8') ?>
                                                </a>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="mt-10 rounded-2xl border border-dashed border-amber-200/80 bg-white/70 p-6 text-sm leading-relaxed text-slate-600">
                                Aucun module spécifique n’est encore relié à votre participation. Les boutons ci-dessus restent utiles pour naviguer ; votre encadrement vous indiquera les prochaines ouvertures à tester.
                            </div>
                        <?php endif; ?>
                    </section>
                    <?php endif; ?>
        
                    <section class="bg-white border-t-4 border-slate-900 shadow-sm">
                        <div class="px-8 py-6 border-b border-slate-100 flex items-center justify-between">
                            <div>
                                <p class="text-[11px] font-black uppercase tracking-[0.2em] text-blue-600">Priorités immédiates</p>
                                <h2 class="mt-1 text-2xl font-black uppercase tracking-tight text-slate-900">Tableau de conduite</h2>
                            </div>
                            <a href="<?= url('formations') ?>" class="group flex items-center gap-2 text-[10px] font-black uppercase tracking-[0.2em] text-slate-400 hover:text-slate-900 transition-colors">
                                Consulter l'intégralité
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
                            </a>
                        </div>
        
                        <div class="grid grid-cols-1 lg:grid-cols-3 divide-y lg:divide-y-0 lg:divide-x divide-slate-100">
                            <a href="<?= url('formations') ?>" class="p-8 hover:bg-slate-50 transition-all group">
                                <span class="text-[10px] font-mono font-bold text-slate-300 group-hover:text-blue-600 transition-colors">01</span>
                                <h3 class="mt-4 text-[13px] font-black uppercase tracking-[0.1em] text-slate-900 leading-snug">Finaliser le module opérateur</h3>
                                <p class="mt-4 text-[13px] text-slate-500 leading-relaxed font-medium">
                                    Reprise de la progression sur le manuel fondamental. Validation des séquences théoriques restantes.
                                </p>
                            </a>
        
                            <a href="<?= url('dossier-operateur/accreditation') ?>" class="p-8 hover:bg-slate-50 transition-all group text-balance">
                                <span class="text-[10px] font-mono font-bold text-slate-300 group-hover:text-blue-600 transition-colors">02</span>
                                <h3 class="mt-4 text-[13px] font-black uppercase tracking-[0.1em] text-slate-900 leading-snug">Mettre à jour l’accréditation</h3>
                                <p class="mt-4 text-[13px] text-slate-500 leading-relaxed font-medium">
                                    Audit des pièces justificatives et état de validation du profil individuel.
                                </p>
                            </a>
        
                            <a href="<?= url('documents') ?>" class="p-8 hover:bg-slate-50 transition-all group">
                                <span class="text-[10px] font-mono font-bold text-slate-300 group-hover:text-blue-600 transition-colors">03</span>
                                <h3 class="mt-4 text-[13px] font-black uppercase tracking-[0.1em] text-slate-900 leading-snug">Note opérationnelle</h3>
                                <p class="mt-4 text-[13px] text-slate-500 leading-relaxed font-medium">
                                    Consultation des derniers comptes-rendus et directives stratégiques en vigueur.
                                </p>
                            </a>
                        </div>
                    </section>
        
                    <section>
                        <div class="mb-6 flex items-baseline gap-4">
                            <h2 class="text-[11px] font-black uppercase tracking-[0.3em] text-slate-400">Modules stratégiques</h2>
                            <div class="h-[1px] flex-grow bg-slate-100"></div>
                        </div>
        
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            <a href="<?= url('dashboard') ?>" class="bg-white p-8 border border-slate-200 rounded-3xl shadow-sm hover:shadow-xl hover:shadow-slate-200/50 transition-all group">
                                <div class="flex flex-col h-full">
                                    <h3 class="text-sm font-black uppercase tracking-[0.2em] text-slate-900 group-hover:text-blue-600 transition-colors italic">Commandement</h3>
                                    <p class="mt-4 text-[13px] text-slate-500 leading-relaxed">Vue tactique, briefings, état du réseau et cellules actives.</p>
                                    <div class="mt-8 pt-6 border-t border-slate-50 flex justify-between items-center text-[10px] font-black uppercase tracking-widest text-slate-300">
                                        <span>Accès Niveau 1</span>
                                        <svg class="w-4 h-4 opacity-0 group-hover:opacity-100 transition-opacity" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                                    </div>
                                </div>
                            </a>
        
                            <a href="<?= url('formations') ?>" class="bg-white p-8 border border-slate-200 rounded-3xl shadow-sm hover:shadow-xl hover:shadow-slate-200/50 transition-all group">
                                <div class="flex flex-col h-full">
                                    <h3 class="text-sm font-black uppercase tracking-[0.2em] text-slate-900 group-hover:text-blue-600 transition-colors italic">Académie</h3>
                                    <p class="mt-4 text-[13px] text-slate-500 leading-relaxed">Parcours d'instruction, progression et résultats des validations.</p>
                                    <div class="mt-8 pt-6 border-t border-slate-50 flex justify-between items-center text-[10px] font-black uppercase tracking-widest text-slate-300">
                                        <span>96% Validé</span>
                                        <svg class="w-4 h-4 opacity-0 group-hover:opacity-100 transition-opacity" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                                    </div>
                                </div>
                            </a>
        
                            <a href="<?= url('documents') ?>" class="bg-white p-8 border border-slate-200 rounded-3xl shadow-sm hover:shadow-xl hover:shadow-slate-200/50 transition-all group">
                                <div class="flex flex-col h-full">
                                    <h3 class="text-sm font-black uppercase tracking-[0.2em] text-slate-900 group-hover:text-blue-600 transition-colors italic">Référentiel</h3>
                                    <p class="mt-4 text-[13px] text-slate-500 leading-relaxed">Doctrines, procédures, fiches et manuels techniques.</p>
                                    <div class="mt-8 pt-6 border-t border-slate-50 flex justify-between items-center text-[10px] font-black uppercase tracking-widest text-slate-300">
                                        <span>1.4k Entrées</span>
                                        <svg class="w-4 h-4 opacity-0 group-hover:opacity-100 transition-opacity" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                                    </div>
                                </div>
                            </a>
                        </div>  
                    </section>
              

                    <!-- Bloc activité -->
                    <section class="bg-white border border-slate-200">
                        <div class="px-6 py-5 border-b border-slate-100">
                            <p class="text-[10px] font-black uppercase tracking-[0.25em] text-slate-400">Journal</p>
                            <h2 class="mt-2 text-xl font-black uppercase tracking-tight text-[#001529]">Activité récente</h2>
                        </div>
    
                        <div class="divide-y divide-slate-100">
                            <div class="px-6 py-5 flex items-start justify-between gap-4">
                                <div>
                                    <p class="text-sm font-black uppercase text-[#001529]">Connexion validée sur le nœud principal</p>
                                    <p class="mt-1 text-sm text-slate-500">Session ouverte depuis un terminal reconnu avec journalisation active.</p>
                                </div>
                                <span class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-400 whitespace-nowrap">20:15</span>
                            </div>
    
                            <div class="px-6 py-5 flex items-start justify-between gap-4">
                                <div>
                                    <p class="text-sm font-black uppercase text-[#001529]">Progression mise à jour sur le module fondamental</p>
                                    <p class="mt-1 text-sm text-slate-500">Dernière séquence validée : procédures d’entrée, organisation et doctrine.</p>
                                </div>
                                <span class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-400 whitespace-nowrap">18:42</span>
                            </div>
    
                            <div class="px-6 py-5 flex items-start justify-between gap-4">
                                <div>
                                    <p class="text-sm font-black uppercase text-[#001529]">Révision du dossier opérateur effectuée</p>
                                    <p class="mt-1 text-sm text-slate-500">Statut documentaire inchangé. Aucune anomalie bloquante détectée.</p>
                                </div>
                                <span class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-400 whitespace-nowrap">17:10</span>
                            </div>
                        </div>
                    </section>
                </div>
    
    
                <!-- Colonne droite -->
                <aside class="space-y-8">
                    <?php
                    $cu = $currentUser ?? null;
                    $pe = $personnelExtras ?? null;
                    $gr = $grade ?? null;
                    $displayName = $cu ? ($cu['display_name'] ?? $cu['email']) : '—';
                    $initials = $cu && !empty($cu['display_name']) ? strtoupper(preg_replace('/[^A-Z]/', '', substr((string)$cu['display_name'], 0, 2)) ?: 'OP') : 'OP';
                    $matricule = $pe ? ($pe['service_number'] ?? null) : null;
                    $idLine = $matricule ? 'Matricule: ' . $matricule : ($cu ? 'ID: ' . (int)$cu['id'] : '—');
                    $statut = $cu ? ($cu['status'] ?? '—') : '—';
                    $statutLabel = ($statut === 'active') ? 'Opérationnel' : $statut;
                    $gradeName = $gr ? ($gr['label_short'] ?? $gr['short_name'] ?? $gr['label_long'] ?? $gr['name'] ?? '—') : '—';
                    $clearance = $pe ? ($pe['clearance_level'] ?? '—') : '—';
                    $squadron = $pe ? ($pe['squadron'] ?? '—') : '—';
                    ?>
                    <section class="bg-white border border-slate-200 rounded-[2rem] overflow-hidden shadow-sm shadow-slate-200/50 transition-all hover:shadow-xl hover:shadow-slate-200/60">
                        <div class="px-8 py-6 border-b border-slate-100 flex justify-between items-center">
                            <div class="space-y-1">
                                <p class="text-[10px] font-[900] uppercase tracking-[0.3em] text-blue-600">Identité Opérateur</p>
                                <h2 class="text-xl font-black uppercase tracking-tighter text-slate-900">Dossier personnel</h2>
                            </div>
                            <div class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></div>
                        </div>
                
                        <div class="p-8">
                            <div class="flex items-center gap-6">
                                <div class="relative group">
                                    <div class="absolute inset-0 bg-blue-600 rounded-full blur opacity-10 group-hover:opacity-20 transition-opacity"></div>
                                    <div class="relative w-16 h-16 rounded-full border-2 border-slate-900 flex items-center justify-center bg-white overflow-hidden">
                                        <span class="text-xl font-black text-slate-900 italic tracking-tighter"><?= htmlspecialchars($initials) ?></span>
                                    </div>
                                </div>
                
                                <div class="min-w-0">
                                    <h3 class="text-2xl font-[950] uppercase italic tracking-tighter text-slate-900 leading-none"><?= htmlspecialchars($displayName) ?></h3>
                                    <p class="mt-2 font-mono text-[10px] font-bold text-slate-400 uppercase tracking-widest"><?= htmlspecialchars($idLine) ?></p>
                                </div>
                            </div>
                
                            <div class="grid grid-cols-2 gap-px bg-slate-100 border border-slate-100 mt-10 rounded-2xl overflow-hidden">
                                <div class="bg-slate-50 p-5 space-y-1">
                                    <span class="block text-[8px] font-black text-slate-400 uppercase tracking-[0.3em]">Statut</span>
                                    <span class="block text-xs font-black uppercase <?= $statut === 'active' ? 'text-emerald-600 italic' : 'text-slate-900' ?>"><?= htmlspecialchars($statutLabel) ?></span>
                                </div>
                                <div class="bg-slate-50 p-5 space-y-1 text-right lg:text-left">
                                    <span class="block text-[8px] font-black text-slate-400 uppercase tracking-[0.3em]">Rang</span>
                                    <span class="block text-xs font-black uppercase text-slate-900"><?= htmlspecialchars($gradeName) ?></span>
                                </div>
                                <div class="bg-slate-50 p-5 space-y-1">
                                    <span class="block text-[8px] font-black text-slate-400 uppercase tracking-[0.3em]">Habilitation</span>
                                    <span class="block text-xs font-black uppercase text-slate-900"><?= htmlspecialchars($clearance ?: '—') ?></span>
                                </div>
                                <div class="bg-slate-50 p-5 space-y-1 text-right lg:text-left">
                                    <span class="block text-[8px] font-black text-slate-400 uppercase tracking-[0.3em]">Unité</span>
                                    <span class="block text-xs font-black uppercase text-slate-400 italic"><?= htmlspecialchars($squadron) ?></span>
                                </div>
                            </div>
                
                            <div class="mt-8">
                                <a href="<?= url('personnel/me') ?>" class="group flex items-center justify-center gap-4 w-full py-4 bg-white border-2 border-slate-900 text-slate-900 hover:bg-slate-900 hover:text-white transition-all duration-300 rounded-2xl">
                                    <span class="text-[11px] font-[900] uppercase tracking-[0.25em]">Accès dossier complet</span>
                                    <svg class="w-4 h-4 transition-transform group-hover:translate-x-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M17 8l4 4m0 0l-4 4m4-4H3"/>
                                    </svg>
                                </a>
                                <p class="mt-4 text-center text-[9px] font-bold text-slate-300 uppercase tracking-widest italic">
                                    Fiche détaillée et données administratives
                                </p>
                            </div>
                        </div>
                    </section>
                                      
                        <!-- Alertes -->
                    <section class="bg-white border border-slate-200">
                        <div class="px-6 py-5 border-b border-slate-100">
                            <p class="text-[10px] font-black uppercase tracking-[0.25em] text-slate-400">Surveillance</p>
                            <h2 class="mt-2 text-xl font-black uppercase tracking-tight text-[#001529]">Alertes et échéances</h2>
                        </div>
    
                        <div class="divide-y divide-slate-100">
                            <div class="p-6">
                                <div class="flex items-center justify-between gap-4">
                                    <p class="text-sm font-black uppercase text-[#001529]">Validation documentaire à confirmer</p>
                                    <span class="text-[10px] px-2 py-1 bg-amber-50 border border-amber-200 text-amber-700 font-black uppercase tracking-[0.2em]">Majeur</span>
                                </div>
                                <p class="mt-2 text-sm text-slate-500">Une pièce justificative requiert une vérification avant clôture du cycle.</p>
                            </div>
    
                            <div class="p-6">
                                <div class="flex items-center justify-between gap-4">
                                    <p class="text-sm font-black uppercase text-[#001529]">Module avancé non terminé</p>
                                    <span class="text-[10px] px-2 py-1 bg-slate-100 border border-slate-200 text-slate-600 font-black uppercase tracking-[0.2em]">Suivi</span>
                                </div>
                                <p class="mt-2 text-sm text-slate-500">La progression est suspendue à 68 %. Reprise recommandée avant affectation.</p>
                            </div>
    
                            <div class="p-6">
                                <div class="flex items-center justify-between gap-4">
                                    <p class="text-sm font-black uppercase text-[#001529]">Synchronisation matériel prévue</p>
                                    <span class="text-[10px] px-2 py-1 bg-blue-50 border border-blue-200 text-blue-700 font-black uppercase tracking-[0.2em]">Info</span>
                                </div>
                                <p class="mt-2 text-sm text-slate-500">Maintenance logicielle programmée sur l’équipement personnel enregistré.</p>
                            </div>
                        </div>
                    </section>
    
                    <!-- Raccourcis -->
                    <section class="bg-[#001529] text-white border border-slate-800">
                        <div class="px-6 py-5 border-b border-white/10">
                            <p class="text-[10px] font-black uppercase tracking-[0.25em] text-white/40">Accès rapide</p>
                            <h2 class="mt-2 text-xl font-black uppercase tracking-tight">Raccourcis de service</h2>
                        </div>
    
                        <div class="grid grid-cols-2 gap-px bg-white/10">
                            <a href="<?= url('atak') ?>" class="bg-[#001529] p-5 hover:bg-white/[0.04] transition">
                                <p class="text-sm font-black uppercase">ATAK / Tacmap</p>
                                <p class="mt-1 text-xs text-white/50 uppercase">Carte tactique temps réel</p>
                            </a>
                            <a href="<?= url('orbat') ?>" class="bg-[#001529] p-5 hover:bg-white/[0.04] transition">
                                <p class="text-sm font-black uppercase">ORBAT / Unité</p>
                                <p class="mt-1 text-xs text-white/50 uppercase">Organisation et personnel</p>
                            </a>
                            <a href="<?= url('personnel/me') ?>" class="bg-[#001529] p-5 hover:bg-white/[0.04] transition">
                                <p class="text-sm font-black uppercase">Ma fiche</p>
                                <p class="mt-1 text-xs text-white/50 uppercase">Dossier personnel</p>
                            </a>
                            <a href="<?= url('documents') ?>" class="bg-[#001529] p-5 hover:bg-white/[0.04] transition">
                                <p class="text-sm font-black uppercase">Documents</p>
                                <p class="mt-1 text-xs text-white/50 uppercase">Ordres et notes</p>
                            </a>
                            <a href="<?= url('formations') ?>" class="bg-[#001529] p-5 hover:bg-white/[0.04] transition">
                                <p class="text-sm font-black uppercase">Formations</p>
                                <p class="mt-1 text-xs text-white/50 uppercase">Séquences</p>
                            </a>
                            <a href="<?= url('account') ?>" class="bg-[#001529] p-5 hover:bg-white/[0.04] transition">
                                <p class="text-sm font-black uppercase">Paramètres</p>
                                <p class="mt-1 text-xs text-white/50 uppercase">Profil et paramètres</p>
                            </a>
                        </div>
                    </section>
    
                </aside>
    
            </div>
        </section>
    </main>
    </div>
</body>
</html>