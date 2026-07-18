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
    <?php if (is_file(base_path('public/assets/css/dashboard-impact.css'))): ?>
    <link href="<?= htmlspecialchars($base) ?>/assets/css/dashboard-impact.css" rel="stylesheet">
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
<body class="dashboard-shell layout-light bg-[#050505] text-slate-900 selection:bg-emerald-500/30 selection:text-white overflow-x-hidden antialiased">
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
    <div class="w-full border-b border-white/5 bg-black text-white/40 select-none">
        <div class="mx-auto flex max-w-[1800px] flex-wrap items-center justify-between gap-x-4 gap-y-2 px-4 py-2 sm:px-6 lg:px-8">
            <div class="flex min-w-0 flex-wrap items-center gap-x-3 gap-y-1 font-mono text-[8px] uppercase tracking-[0.15em]">
                <div class="flex items-center gap-2">
                    <span class="font-black tracking-[0.28em] text-emerald-400">ZULU</span>
                    <span id="t-zulu" class="text-[10px] font-medium tracking-normal text-white/85 w-[4.25rem] tabular-nums">00:00:00</span>
                </div>
                <span class="text-white/10" aria-hidden="true">|</span>
                <div class="hidden items-center gap-2 sm:flex sm:opacity-60">
                    <span>PST</span>
                    <span id="t-pst" class="text-[10px] tracking-normal text-white/80 w-[4.25rem] tabular-nums">00:00:00</span>
                    <span class="text-white/10" aria-hidden="true">|</span>
                    <span>MTN</span>
                    <span id="t-mtn" class="text-[10px] tracking-normal text-white/80 w-[4.25rem] tabular-nums">00:00:00</span>
                    <span class="text-white/10" aria-hidden="true">|</span>
                    <span>EST</span>
                    <span id="t-est" class="text-[10px] tracking-normal text-white/80 w-[4.25rem] tabular-nums">00:00:00</span>
                </div>
            </div>
            <div class="flex flex-wrap items-center justify-end gap-3 sm:gap-4 text-[8px] font-bold uppercase tracking-[0.18em]">
                <div class="flex items-center gap-2 text-white/80">
                    <span class="hidden text-white/35 sm:inline">Heure locale</span>
                    <span id="clock-local" class="text-[10px] tracking-wider text-white tabular-nums sm:text-[11px]">00:00:00</span>
                </div>
                <span class="hidden h-3 w-px bg-white/10 sm:block" aria-hidden="true"></span>
                <div class="flex items-center gap-2 text-white/45">
                    <span class="relative flex h-1.5 w-1.5">
                        <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-25"></span>
                        <span class="relative inline-flex h-1.5 w-1.5 rounded-full bg-emerald-400/90"></span>
                    </span>
                    <span>Réseau actif</span>
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
    <main class="min-h-screen bg-[#050505] text-slate-900">
        <?php require base_path('views/partials/layout_flash_toasts.php'); ?>
        <?php
        $communityMemberships = $communityMemberships ?? [];
        $candidate_enlistment_tracking = is_array($candidate_enlistment_tracking ?? null) ? $candidate_enlistment_tracking : [];
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

        <?php if ($candidate_enlistment_tracking !== []): ?>
        <section class="border-b border-emerald-900/20 bg-slate-950 text-white">
            <div class="max-w-7xl mx-auto px-6 md:px-10 py-10">
                <div class="mb-6">
                    <p class="text-[10px] font-black uppercase tracking-[0.35em] text-emerald-300/90">Candidatures existantes</p>
                    <h3 class="mt-2 text-2xl md:text-3xl font-black uppercase italic tracking-tight">Suivez l’état de vos dossiers</h3>
                    <p class="mt-3 text-sm text-slate-300 max-w-3xl">Retrouvez les candidatures déjà transmises, consultez leur statut en direct et ouvrez le portail de suivi du dossier.</p>
                </div>
                <div class="grid gap-4 md:grid-cols-2">
                    <?php foreach ($candidate_enlistment_tracking as $track): ?>
                        <?php
                        $statusRaw = (string) ($track['status'] ?? 'submitted');
                        $statusLabel = [
                            'submitted' => 'En cours d’instruction',
                            'reviewed' => 'Accepté',
                            'rejected' => 'Refusé',
                            'blocked' => 'Non admis',
                        ][$statusRaw] ?? ucfirst($statusRaw);
                        $statusClass = match ($statusRaw) {
                            'reviewed' => 'border-emerald-400/40 bg-emerald-500/10 text-emerald-200',
                            'rejected', 'blocked' => 'border-rose-400/40 bg-rose-500/10 text-rose-200',
                            default => 'border-sky-400/40 bg-sky-500/10 text-sky-200',
                        };
                        $tenantName = trim((string) ($track['tenant_name'] ?? 'Communauté'));
                        $createdAt = (string) ($track['created_at'] ?? '');
                        $createdFmt = $createdAt !== '' ? date('d/m/Y H:i', strtotime($createdAt)) : '—';
                        $portalHref = is_string($track['candidate_portal_href'] ?? null) ? (string) $track['candidate_portal_href'] : null;
                        ?>
                        <article class="rounded-2xl border border-white/10 bg-white/[0.03] p-5">
                            <div class="flex items-center justify-between gap-3">
                                <p class="text-sm font-bold text-white"><?= htmlspecialchars($tenantName, ENT_QUOTES, 'UTF-8') ?></p>
                                <span class="inline-flex items-center rounded-full border px-2.5 py-1 text-[10px] font-black uppercase tracking-wide <?= $statusClass ?>"><?= htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8') ?></span>
                            </div>
                            <p class="mt-2 text-xs text-slate-300">Dossier #<?= (int) ($track['id'] ?? 0) ?> · Déposé le <?= htmlspecialchars($createdFmt, ENT_QUOTES, 'UTF-8') ?></p>
                            <div class="mt-4 flex flex-wrap gap-3">
                                <?php if ($portalHref !== null): ?>
                                <a href="<?= htmlspecialchars($portalHref, ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center justify-center rounded-xl bg-emerald-500 px-4 py-2 text-[11px] font-black uppercase tracking-wider text-slate-950 hover:bg-emerald-400 transition-colors">
                                    Ouvrir la page du dossier
                                </a>
                                <?php endif; ?>
                                <a href="<?= htmlspecialchars(url('communities'), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center justify-center rounded-xl border border-white/20 px-4 py-2 text-[11px] font-black uppercase tracking-wider text-white hover:bg-white/10 transition-colors">
                                    Voir les unités
                                </a>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
        <?php endif; ?>
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
        <section id="dashboard-mission-briefing" class="dash-impact relative overflow-hidden bg-[var(--di-void)] py-14 text-[var(--di-ink)] md:py-20" aria-label="Préparation opérationnelle">
            <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_at_30%_0%,rgba(52,211,153,0.12),transparent_55%)]" aria-hidden="true"></div>
            <div class="pointer-events-none absolute inset-0 bg-[linear-gradient(180deg,transparent,rgba(5,5,5,0.4))]" aria-hidden="true"></div>
            <div class="relative mx-auto max-w-6xl px-6 md:px-10">
                <div class="mb-12 flex flex-wrap items-end justify-between gap-6 di-rise">
                    <div class="min-w-0 max-w-2xl">
                        <p class="di-kicker text-emerald-400/90">Focus</p>
                        <h2 class="di-display di-display-lg mt-3 text-white">Préparer<br class="hidden sm:block"> la mission<span class="text-emerald-400">.</span></h2>
                        <p class="di-body mt-4 max-w-lg">Prochaine séance, matériel à préparer et formations en cours.</p>
                    </div>
                    <button type="button" id="btn-hide-ordre-jour" class="rounded-md border border-white/10 bg-white/[0.03] px-4 py-2.5 text-[10px] font-bold uppercase tracking-[0.2em] text-white/45 transition hover:border-white/20 hover:bg-white/[0.06] hover:text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-400/40" title="Masquer cette zone sur ce navigateur">
                        Masquer
                    </button>
                </div>

                <div class="grid gap-4 sm:grid-cols-2 di-rise-2">
                    <article class="di-panel group relative p-7 md:p-8">
                        <p class="di-kicker text-white/35">Calendrier</p>
                        <div class="mt-8">
                            <?php if ($mbOp): ?>
                                <?php
                                $mbStart = $mbOp['starts_at'] ?? '';
                                $mbStartFmt = '';
                                if ($mbStart !== '') {
                                    $tsOp = strtotime($mbStart);
                                    $mbStartFmt = $tsOp !== false ? date('d/m/Y à H\hi', $tsOp) : '';
                                }
                                ?>
                                <p class="text-xl font-black leading-snug tracking-tight text-white md:text-2xl"><?= htmlspecialchars($mbOp['title'], ENT_QUOTES, 'UTF-8') ?></p>
                                <?php if ($mbStartFmt !== ''): ?>
                                    <p class="mt-3 text-sm font-medium text-emerald-300/80"><?= htmlspecialchars($mbStartFmt, ENT_QUOTES, 'UTF-8') ?></p>
                                <?php endif; ?>
                                <?php if (!empty($mbOp['rsvp_label'])): ?>
                                    <p class="mt-4 inline-flex border border-emerald-400/25 bg-emerald-400/10 px-3 py-1.5 text-[11px] font-semibold text-emerald-200"><?= htmlspecialchars((string) $mbOp['rsvp_label'], ENT_QUOTES, 'UTF-8') ?></p>
                                <?php endif; ?>
                                <?php if (!empty($mbOp['list_href'])): ?>
                                    <p class="mt-6">
                                        <a href="<?= htmlspecialchars($mbOp['list_href'], ENT_QUOTES, 'UTF-8') ?>" class="di-cta-ghost">Voir le calendrier →</a>
                                    </p>
                                <?php endif; ?>
                            <?php else: ?>
                                <p class="text-lg font-semibold text-white/40">Aucune opération planifiée</p>
                                <p class="mt-2 text-sm text-white/30">Le prochain créneau apparaîtra ici dès qu’il sera publié.</p>
                            <?php endif; ?>
                        </div>
                    </article>

                    <article class="di-panel group relative p-7 md:p-8">
                        <p class="di-kicker text-white/35">Ressources</p>
                        <div class="mt-8">
                            <?php if (is_array($mbMod)): ?>
                                <a href="<?= htmlspecialchars($mbMod['detail_href'], ENT_QUOTES, 'UTF-8') ?>" class="text-xl font-black leading-snug tracking-tight text-white transition hover:text-emerald-300 md:text-2xl"><?= htmlspecialchars($mbMod['title'], ENT_QUOTES, 'UTF-8') ?></a>
                                <p class="mt-3 text-sm text-white/45"><?= !empty($mbMod['has_pack']) ? 'Fiche et pack communautaire à jour.' : 'Parcourez les packs proposés pour votre unité.' ?></p>
                            <?php else: ?>
                                <p class="text-lg font-semibold text-white/40">Aucun pack principal</p>
                                <p class="mt-2 text-sm text-white/30">Le matériel de référence de l’unité sera affiché ici.</p>
                            <?php endif; ?>
                            <?php if (!empty($atakModDownloadUrl)): ?>
                                <p class="mt-6">
                                    <a href="<?= htmlspecialchars((string) $atakModDownloadUrl, ENT_QUOTES, 'UTF-8') ?>" class="di-cta-ghost">Module terrain complémentaire →</a>
                                </p>
                            <?php endif; ?>
                        </div>
                    </article>
                </div>

                <?php if ($mbExcerpt !== null && $mbExcerpt !== ''): ?>
                    <div class="di-rise-3 mt-4 border border-amber-400/20 bg-amber-950/30 p-6 md:p-7">
                        <p class="di-kicker text-amber-200/80">Consigne communautaire</p>
                        <p class="mt-3 max-w-3xl text-base font-medium leading-relaxed text-amber-50/95"><?= htmlspecialchars($mbExcerpt, ENT_QUOTES, 'UTF-8') ?></p>
                        <a href="<?= htmlspecialchars($mbPinsA, ENT_QUOTES, 'UTF-8') ?>" class="mt-5 inline-flex text-xs font-bold uppercase tracking-[0.16em] text-amber-200 transition hover:text-white">Voir les raccourcis →</a>
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
                    <div class="di-rise-3 mt-4 border border-white/[0.06] bg-white/[0.02]">
                        <div class="flex flex-wrap items-center justify-between gap-4 border-b border-white/[0.06] px-6 py-6 md:px-8">
                            <div class="min-w-0">
                                <p class="di-kicker text-white/35">Cursus</p>
                                <?php if ($mbTrainProgressAvg !== null): ?>
                                    <p class="mt-2 text-2xl font-black tracking-tight text-white">Progression <span class="text-emerald-400 tabular-nums"><?= (int) $mbTrainProgressAvg ?>%</span></p>
                                <?php else: ?>
                                    <p class="mt-2 text-2xl font-black tracking-tight text-white">Formations à suivre</p>
                                <?php endif; ?>
                            </div>
                            <a href="<?= htmlspecialchars(url('formations/mes-formations'), ENT_QUOTES, 'UTF-8') ?>" class="di-cta">Mes formations</a>
                        </div>
                        <div class="divide-y divide-white/[0.05]">
                            <?php foreach ($mbTrain as $t): ?>
                                <?php $tPct = isset($t['progress_pct']) ? max(0, min(100, (int) $t['progress_pct'])) : null; ?>
                                <a href="<?= htmlspecialchars($t['href'], ENT_QUOTES, 'UTF-8') ?>" class="group block px-6 py-5 transition hover:bg-white/[0.03] md:px-8 <?= !empty($t['urgent']) ? 'bg-rose-500/[0.04]' : '' ?>">
                                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between sm:gap-4">
                                        <span class="text-base font-semibold text-white/85 group-hover:text-white"><?= htmlspecialchars($t['title'], ENT_QUOTES, 'UTF-8') ?></span>
                                        <?php if ($tPct !== null): ?>
                                            <span class="text-xs font-bold tabular-nums text-emerald-400/90 sm:shrink-0"><?= $tPct ?>%</span>
                                        <?php endif; ?>
                                    </div>
                                    <?php
                                    $tSub = isset($t['subtitle']) ? trim((string) $t['subtitle']) : '';
                                    if ($tSub !== ''):
                                    ?>
                                        <p class="mt-1 text-sm text-white/40"><?= htmlspecialchars($tSub, ENT_QUOTES, 'UTF-8') ?></p>
                                    <?php endif; ?>
                                    <?php if ($tPct !== null): ?>
                                        <div class="di-progress mt-4" role="progressbar" aria-valuenow="<?= $tPct ?>" aria-valuemin="0" aria-valuemax="100">
                                            <span style="width: <?= $tPct ?>%"></span>
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
        <section class="border-b border-white/5 bg-[#0a0c0b] text-white" aria-label="Contexte de session">
            <div class="mx-auto max-w-6xl space-y-3 px-6 py-4 md:px-10">
                <?php if ($dashCtxCommunity): ?>
                <div class="flex min-w-0 flex-wrap items-center gap-x-3 gap-y-2 text-[11px] leading-snug">
                    <span class="shrink-0 text-[10px] font-bold uppercase tracking-wider text-white/40">Communauté</span>
                    <div class="flex min-w-0 flex-1 flex-wrap items-center gap-2">
                        <?php foreach ($communityMemberships as $m): ?>
                            <?php if ((int) $m['tenant_id'] === $currentTid): ?>
                                <span class="border border-emerald-400/30 bg-emerald-400/10 px-2.5 py-1 font-bold text-emerald-200"><?= htmlspecialchars(community_display_name($m)) ?></span>
                            <?php else: ?>
                                <form method="post" action="<?= url('community/switch') ?>" class="inline" onsubmit="var b=this.querySelector('button[type=submit]');if(b){b.disabled=true;b.setAttribute('aria-busy','true');b.textContent='Chargement…';}">
                                    <?= \App\Core\Csrf::field() ?>
                                    <input type="hidden" name="tenant_id" value="<?= (int) $m['tenant_id'] ?>">
                                    <button type="submit" class="font-semibold text-white/55 underline decoration-white/20 underline-offset-2 hover:text-emerald-300"><?= htmlspecialchars(community_display_name($m)) ?></button>
                                </form>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                    <details class="dash-vers-details relative shrink-0">
                        <summary class="inline-flex cursor-pointer items-center gap-2 border border-white/10 bg-white/[0.04] px-3 py-1.5 text-[10px] font-bold uppercase tracking-[0.18em] text-white/70 transition hover:border-emerald-400/30 hover:text-emerald-200 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/40">
                            <svg class="h-4 w-4 shrink-0 text-emerald-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 008.716-6.747M12 21a9.004 9.004 0 01-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 017.843 4.582M12 3a8.997 8.997 0 00-7.843 4.582m15.686 0A11.953 11.953 0 0112 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0121 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0112 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 013 12c0-1.605.42-3.113 1.157-4.418" />
                            </svg>
                            Vers
                        </summary>
                        <div class="absolute right-0 z-30 mt-1.5 min-w-[14rem] overflow-hidden border border-white/10 bg-[#111] py-1 shadow-lg" role="menu">
                            <a href="<?= url('platform/invite-unit') ?>" class="block px-4 py-2.5 text-sm font-semibold text-white/80 transition hover:bg-white/[0.06] hover:text-emerald-300" role="menuitem">Inviter une unité</a>
                            <a href="<?= url('communities/create') ?>" class="block border-t border-white/5 px-4 py-2.5 text-sm font-semibold text-white/80 transition hover:bg-white/[0.06] hover:text-emerald-300" role="menuitem">Nouvelle communauté</a>
                        </div>
                    </details>
                </div>
                <?php endif; ?>
                <?php if ($dashCtxTrial): ?>
                <div class="relative flex flex-col gap-3 overflow-hidden border border-amber-400/25 bg-amber-950/40 p-4 sm:flex-row sm:items-center sm:gap-5 sm:p-5">
                    <div class="min-w-0 flex-1">
                        <p class="text-[10px] font-bold uppercase tracking-[0.24em] text-amber-200">Essai étendu — Fondateur</p>
                        <p class="mt-1 text-sm leading-snug text-amber-50/80">
                            Votre accès premium est actif jusqu’au <strong class="font-bold text-white"><?= htmlspecialchars(date('d/m/Y', strtotime($founderTrialEndsAt))) ?></strong>.
                        </p>
                    </div>
                    <a href="<?= url('platform/upgrade') ?>" class="inline-flex shrink-0 items-center justify-center bg-emerald-400 px-5 py-2.5 text-center text-[10px] font-bold uppercase tracking-[0.2em] text-slate-950 transition hover:bg-emerald-300 sm:self-stretch sm:min-h-[2.75rem] sm:py-0">
                        Découvrir les offres
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </section>
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
        <section id="dashboard-community-pins" class="dash-impact border-b border-slate-200 bg-[#0a0a0a] text-white scroll-mt-24">
            <div class="mx-auto max-w-6xl px-6 py-12 md:px-10">
                <div class="mb-8 flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <p class="di-kicker text-emerald-400/90">Communauté</p>
                        <h2 class="di-display di-display-md mt-3 text-white">Raccourcis<span class="text-emerald-400">.</span></h2>
                        <p class="di-body mt-3 max-w-lg">Liens publiés par votre organisation pour accélérer les actions d’équipe.</p>
                    </div>
                    <?php if (\App\Core\Gate::getInstance()->allows('dashboard.pins.manage')): ?>
                        <a href="<?= url('back-office/dashboard-pins') ?>" class="di-cta-ghost">Gérer →</a>
                    <?php else: ?>
                        <span class="text-[10px] font-bold uppercase tracking-[0.16em] text-white/35">Configuré par les responsables</span>
                    <?php endif; ?>
                </div>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
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
                            <div class="border border-amber-400/20 bg-amber-950/40 p-5 sm:col-span-2 xl:col-span-3">
                                <div class="flex items-start gap-4">
                                    <span class="flex h-10 w-10 shrink-0 items-center justify-center border border-amber-400/25 bg-amber-400/10 text-[11px] font-black text-amber-200"><?= htmlspecialchars($icon, ENT_QUOTES, 'UTF-8') ?></span>
                                    <div class="min-w-0">
                                        <p class="di-kicker text-amber-200/70 mb-2"><?= htmlspecialchars((string) ($pin['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                                        <div class="text-sm leading-relaxed text-amber-50/90 whitespace-pre-wrap"><?= nl2br(htmlspecialchars((string) ($pin['notice_text'] ?? ''), ENT_QUOTES, 'UTF-8')) ?></div>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <a href="<?= htmlspecialchars((string) ($pin['href'] ?? '#'), ENT_QUOTES, 'UTF-8') ?>" class="di-panel group flex items-center gap-4 p-5">
                                <span class="flex h-11 w-11 shrink-0 items-center justify-center border border-white/10 bg-white/[0.04] text-[11px] font-black text-white/50 transition group-hover:border-emerald-400/30 group-hover:text-emerald-300"><?= htmlspecialchars($icon, ENT_QUOTES, 'UTF-8') ?></span>
                                <span class="min-w-0 truncate text-sm font-bold text-white/85 group-hover:text-white"><?= htmlspecialchars((string) ($pin['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                            </a>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
        <?php endif; ?>

        <!-- HERO / HUB -->
        <section class="dash-impact relative overflow-hidden border-b border-white/5 bg-[var(--di-void)] text-white">
            <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_at_80%_20%,rgba(52,211,153,0.14),transparent_50%)]" aria-hidden="true"></div>
            <div class="pointer-events-none absolute inset-0 opacity-[0.04]" style="background-image:linear-gradient(rgba(255,255,255,.5) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,.5) 1px,transparent 1px);background-size:48px 48px;" aria-hidden="true"></div>
            <div class="relative mx-auto max-w-6xl px-6 py-14 md:px-10 md:py-20">
                <div class="grid grid-cols-1 items-start gap-12 xl:grid-cols-[1.15fr_0.85fr]">
                    <div class="di-rise space-y-8">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="inline-flex items-center gap-2 border border-emerald-400/25 bg-emerald-400/10 px-2.5 py-1 text-[10px] font-bold uppercase tracking-[0.16em] text-emerald-200">
                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-400"></span>
                                Session active
                            </span>
                            <span class="inline-flex items-center border border-white/10 bg-white/[0.04] px-2.5 py-1 text-[10px] font-bold uppercase tracking-[0.16em] text-white/55">
                                Opérateur
                            </span>
                        </div>

                        <div class="max-w-3xl">
                            <p class="di-kicker text-emerald-400/90 mb-4">
                                <?php if ($dashboard_tenant_label !== null && $dashboard_tenant_label !== ''): ?>
                                    <?= htmlspecialchars('Communauté · ' . $dashboard_tenant_label) ?>
                                <?php else: ?>
                                    Centre de commandement
                                <?php endif; ?>
                            </p>
                            <h1 class="di-display di-display-lg text-white">
                                Hub<br class="hidden sm:block"> opérationnel<span class="text-emerald-400">.</span>
                            </h1>
                            <p class="di-body mt-5 max-w-xl text-base">
                                Cartographie, organigramme, formations, documents et outils tactiques — l’entrée unique de l’unité.
                            </p>
                        </div>

                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <a href="<?= url('atak') ?>" class="di-panel group flex items-center justify-between p-5">
                                <div>
                                    <span class="di-kicker text-white/30">Tactique</span>
                                    <h3 class="mt-2 text-sm font-black uppercase tracking-wide text-white">ATAK / Tacmap</h3>
                                </div>
                                <span class="text-emerald-400/70 transition group-hover:translate-x-0.5 group-hover:text-emerald-300" aria-hidden="true">→</span>
                            </a>
                            <a href="<?= url('orbat') ?>" class="di-panel group flex items-center justify-between p-5">
                                <div>
                                    <span class="di-kicker text-white/30">Organisation</span>
                                    <h3 class="mt-2 text-sm font-black uppercase tracking-wide text-white">ORBAT / Unité</h3>
                                </div>
                                <span class="text-emerald-400/70 transition group-hover:translate-x-0.5 group-hover:text-emerald-300" aria-hidden="true">→</span>
                            </a>
                            <a href="<?= url('formations') ?>" class="di-panel group flex items-center justify-between p-5">
                                <div>
                                    <span class="di-kicker text-white/30">Instruction</span>
                                    <h3 class="mt-2 text-sm font-black uppercase tracking-wide text-white">Formations</h3>
                                </div>
                                <span class="text-emerald-400/70 transition group-hover:translate-x-0.5 group-hover:text-emerald-300" aria-hidden="true">→</span>
                            </a>
                            <a href="<?= url('documents') ?>" class="di-panel group flex items-center justify-between p-5">
                                <div>
                                    <span class="di-kicker text-white/30">Référence</span>
                                    <h3 class="mt-2 text-sm font-black uppercase tracking-wide text-white">Documents</h3>
                                </div>
                                <span class="text-emerald-400/70 transition group-hover:translate-x-0.5 group-hover:text-emerald-300" aria-hidden="true">→</span>
                            </a>
                        </div>
                    </div>

                    <aside class="di-rise-2 border border-white/[0.08] bg-gradient-to-b from-white/[0.06] to-transparent p-7 md:p-8 xl:sticky xl:top-24">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p class="di-kicker text-emerald-400">Infrastructure</p>
                                <h2 class="mt-3 text-2xl font-black uppercase tracking-tight text-white">Modpack Arma 3</h2>
                            </div>
                            <div class="flex h-11 w-11 items-center justify-center border border-white/10 bg-white/[0.04]" aria-hidden="true">
                                <svg class="h-5 w-5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
                        <div class="mt-8 space-y-3">
                            <div class="flex items-center justify-between border border-white/[0.06] bg-black/30 px-4 py-3">
                                <div>
                                    <span class="di-kicker text-white/30">Version</span>
                                    <p class="mt-1 text-sm font-bold text-white"><?= htmlspecialchars($modpack['version'] ?? '—') ?></p>
                                </div>
                                <span class="border border-emerald-400/30 bg-emerald-400/10 px-2 py-1 text-[9px] font-black uppercase tracking-wider text-emerald-300">À jour</span>
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <div class="border border-white/[0.06] bg-black/20 px-4 py-3">
                                    <span class="di-kicker text-white/30">Taille</span>
                                    <p class="mt-1 text-sm font-bold text-white"><?= $sizeFormatted ?></p>
                                </div>
                                <div class="border border-white/[0.06] bg-black/20 px-4 py-3">
                                    <span class="di-kicker text-white/30">Mise à jour</span>
                                    <p class="mt-1 text-sm font-bold text-white"><?= $updatedAt ?></p>
                                </div>
                            </div>
                            <p class="flex items-center gap-2 pt-1 text-xs text-white/50">
                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-400"></span>
                                Dépôt synchronisé — prêt à l’emploi
                            </p>
                        </div>
                        <div class="mt-8">
                            <a href="<?= $downloadUrl ?>" class="di-cta w-full">Télécharger le modpack</a>
                            <p class="mt-3 text-center">
                                <a href="<?= $detailUrl ?>" class="di-cta-ghost text-[9px]">Voir la fiche</a>
                            </p>
                        </div>
                        <?php else: ?>
                        <div class="mt-8 space-y-4">
                            <p class="text-sm text-white/50">Aucun pack n’est encore publié pour votre communauté.</p>
                            <?php if (function_exists('can') && can('admin.access')): ?>
                            <a href="<?= url('admin/modpacks/create') ?>" class="di-cta-ghost">Configurer un modpack →</a>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </aside>

                    <?php $atakModDownloadUrl = $atakModDownloadUrl ?? null; if ($atakModDownloadUrl): ?>
                    <aside class="di-rise-3 border border-white/[0.08] bg-white/[0.03] p-7 md:p-8 xl:col-span-2">
                        <div class="flex flex-wrap items-start justify-between gap-6">
                            <div class="max-w-xl">
                                <p class="di-kicker text-emerald-400">Tactique</p>
                                <h2 class="mt-3 text-xl font-black uppercase tracking-tight text-white">Mod COMSPEC ATAK</h2>
                                <p class="mt-3 text-sm leading-relaxed text-white/50">Carte tactique et synchronisation avec le serveur — téléchargez le module terrain.</p>
                            </div>
                            <div class="flex flex-col gap-3 sm:items-end">
                                <a href="<?= htmlspecialchars($atakModDownloadUrl) ?>" class="di-cta">Télécharger le mod ATAK</a>
                                <a href="<?= url('atak') ?>" class="di-cta-ghost text-[9px]">Page ATAK / Tacmap</a>
                            </div>
                        </div>
                    </aside>
                    <?php endif; ?>
                </div>
            </div>
        </section>
        <?php if ($showcase_training_feature): ?>
        <section class="dash-impact border-b border-white/5 bg-[#050505] py-14 overflow-hidden" <?php if (!empty($showcase_items)): ?>x-data="trainingShowcase"<?php endif; ?>>
            <div class="mx-auto mb-10 flex max-w-6xl flex-wrap items-end justify-between gap-4 px-6 md:px-10">
                <div>
                    <p class="di-kicker text-emerald-400/90">Catalogue</p>
                    <h2 class="di-display di-display-md mt-3 text-white">Nos formations<span class="text-emerald-400">.</span></h2>
                    <?php if ($dashboard_tenant_label !== null && $dashboard_tenant_label !== ''): ?>
                    <p class="mt-3 text-xs font-bold uppercase tracking-[0.18em] text-white/40"><?= htmlspecialchars($dashboard_tenant_label) ?></p>
                    <?php endif; ?>
                </div>
                <?php if (!empty($showcase_items)): ?>
                <div class="flex gap-2">
                    <button type="button" @click="scrollTrack(-360)" class="border border-white/10 bg-white/[0.04] p-3 text-white/60 transition hover:border-emerald-400/30 hover:text-emerald-300" aria-label="Défiler vers la gauche">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                    </button>
                    <button type="button" @click="scrollTrack(360)" class="border border-white/10 bg-white/[0.04] p-3 text-white/60 transition hover:border-emerald-400/30 hover:text-emerald-300" aria-label="Défiler vers la droite">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </button>
                </div>
                <?php endif; ?>
            </div>

            <?php if (empty($showcase_items)): ?>
            <div class="mx-auto max-w-6xl px-6 pb-4 md:px-10">
                <p class="mb-4 text-sm text-white/50">Aucune formation publiée pour cette communauté pour le moment.</p>
                <div class="flex flex-wrap gap-3">
                    <a href="<?= url('formations') ?>" class="di-cta">Ouvrir le catalogue</a>
                    <?php if (function_exists('can') && (can('training.update') || can('training.publish') || can('admin.access') || can('training.manage'))): ?>
                    <a href="<?= training_lms_admin_url('courses') ?>" class="di-cta-ghost border border-white/15 px-4 py-3">Gérer les parcours</a>
                    <?php endif; ?>
                </div>
            </div>
            <?php else: ?>
            <div x-ref="track" class="flex gap-4 overflow-x-auto pb-4 px-[max(1.5rem,calc((100vw-72rem)/2))] no-scrollbar snap-x">
                <?php foreach ($showcase_items as $sc): ?>
                <div
                    @click="openModal = <?= (int) $sc['id'] ?>"
                    class="group relative h-[420px] w-72 flex-none cursor-pointer snap-start overflow-hidden border border-white/10 transition duration-500 hover:-translate-y-1 hover:border-emerald-400/40"
                >
                    <img src="<?= htmlspecialchars($sc['thumb']) ?>" class="absolute inset-0 h-full w-full object-cover transition-transform duration-700 group-hover:scale-105 <?= ($sc['card_style'] ?? '') === 'grayscale' ? 'grayscale group-hover:grayscale-0' : '' ?>" alt="<?= htmlspecialchars($sc['title']) ?>">
                    <div class="absolute inset-0 bg-gradient-to-t from-black via-black/50 to-transparent"></div>
                    <div class="absolute bottom-0 left-0 w-full p-6">
                        <div class="mb-3 flex items-center gap-2">
                            <span class="border border-white/20 bg-black/40 px-2 py-0.5 text-[8px] font-black uppercase tracking-widest text-white"><?= htmlspecialchars($sc['badge_label']) ?></span>
                        </div>
                        <h3 class="text-xl font-black uppercase leading-none tracking-tight text-white"><?= htmlspecialchars($sc['title']) ?></h3>
                        <p class="mt-2 text-[10px] font-bold uppercase tracking-[0.2em] text-white/50"><?= htmlspecialchars($sc['card_line']) ?></p>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <template x-if="openModal !== null">
                <div class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6">
                    <div class="absolute inset-0 bg-black/90 backdrop-blur-sm" @click="openModal = null"></div>
                    <div class="relative flex max-h-[85vh] w-full max-w-4xl flex-col overflow-hidden border border-white/10 bg-[#0a0a0a] shadow-2xl md:flex-row"
                         x-transition:enter="transition ease-out duration-300"
                         x-transition:enter-start="opacity-0 scale-95"
                         x-transition:enter-end="opacity-100 scale-100">
                        <button type="button" @click="openModal = null" class="absolute right-5 top-5 z-10 border border-white/10 bg-white/10 p-2 text-white transition hover:bg-white/20" aria-label="Fermer">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                        <div class="h-64 w-full shrink-0 bg-black md:h-auto md:w-1/2 min-h-[16rem]" x-show="active()">
                            <img :src="active() ? active().banner : ''" :alt="active() ? active().title : ''" class="h-full w-full object-cover opacity-90">
                        </div>
                        <div class="min-h-0 flex-1 overflow-y-auto bg-[#0a0a0a] p-8 text-white md:p-12" x-show="active()">
                            <p class="di-kicker mb-4 text-emerald-400">Détails formation</p>
                            <h2 class="text-3xl font-black uppercase tracking-tight text-white md:text-4xl" x-text="active().title"></h2>
                            <div class="mb-8 grid grid-cols-2 gap-6 border-y border-white/10 py-8">
                                <div>
                                    <span class="di-kicker mb-1 block text-white/35">Date du cycle</span>
                                    <span class="text-sm font-bold uppercase text-white" x-text="active().cycle_display"></span>
                                </div>
                                <div>
                                    <span class="di-kicker mb-1 block text-white/35">Lieu</span>
                                    <span class="text-sm font-bold uppercase text-white" x-text="active().location_display"></span>
                                </div>
                            </div>
                            <p class="mb-10 whitespace-pre-wrap leading-relaxed text-white/60" x-text="active().description"></p>
                            <a :href="active().course_url" class="di-cta w-full">Ouvrir la formation</a>
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
        <div class="border-t border-black/5 bg-[#f4f4f0]">
        <section class="mx-auto max-w-6xl px-6 py-14 md:px-10">
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
                                                <a href="<?= htmlspecialchars((string) $lnk['href'], ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center justify-center bg-slate-900 px-4 py-2.5 text-xs font-bold text-white transition hover:bg-emerald-700">
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
        
                    <section class="border border-slate-200 bg-white">
                        <div class="flex items-center justify-between border-b border-slate-100 px-8 py-6">
                            <div>
                                <p class="text-[11px] font-bold uppercase tracking-[0.22em] text-emerald-700">Priorités</p>
                                <h2 class="mt-1 text-2xl font-black uppercase tracking-tight text-slate-950">Tableau de conduite</h2>
                            </div>
                            <a href="<?= url('formations') ?>" class="group flex items-center gap-2 text-[10px] font-bold uppercase tracking-[0.18em] text-slate-400 transition hover:text-emerald-700">
                                Tout voir
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
                            </a>
                        </div>

                        <div class="grid grid-cols-1 divide-y divide-slate-100 lg:grid-cols-3 lg:divide-x lg:divide-y-0">
                            <a href="<?= url('formations') ?>" class="group p-8 transition hover:bg-emerald-50/40">
                                <span class="text-[10px] font-bold tracking-[0.2em] text-slate-300 transition group-hover:text-emerald-600">01</span>
                                <h3 class="mt-4 text-[13px] font-black uppercase tracking-[0.08em] leading-snug text-slate-900">Finaliser le module opérateur</h3>
                                <p class="mt-4 text-[13px] font-medium leading-relaxed text-slate-500">
                                    Reprise de la progression sur le manuel fondamental. Validation des séquences théoriques restantes.
                                </p>
                            </a>

                            <a href="<?= url('dossier-operateur/accreditation') ?>" class="group p-8 transition hover:bg-emerald-50/40">
                                <span class="text-[10px] font-bold tracking-[0.2em] text-slate-300 transition group-hover:text-emerald-600">02</span>
                                <h3 class="mt-4 text-[13px] font-black uppercase tracking-[0.08em] leading-snug text-slate-900">Mettre à jour l’accréditation</h3>
                                <p class="mt-4 text-[13px] font-medium leading-relaxed text-slate-500">
                                    Audit des pièces justificatives et état de validation du profil individuel.
                                </p>
                            </a>

                            <a href="<?= url('documents') ?>" class="group p-8 transition hover:bg-emerald-50/40">
                                <span class="text-[10px] font-bold tracking-[0.2em] text-slate-300 transition group-hover:text-emerald-600">03</span>
                                <h3 class="mt-4 text-[13px] font-black uppercase tracking-[0.08em] leading-snug text-slate-900">Note opérationnelle</h3>
                                <p class="mt-4 text-[13px] font-medium leading-relaxed text-slate-500">
                                    Consultation des derniers comptes-rendus et directives en vigueur.
                                </p>
                            </a>
                        </div>
                    </section>

                    <section>
                        <div class="mb-6 flex items-baseline gap-4">
                            <h2 class="text-[11px] font-bold uppercase tracking-[0.28em] text-slate-400">Modules stratégiques</h2>
                            <div class="h-px flex-grow bg-slate-200"></div>
                        </div>

                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                            <a href="<?= url('dashboard') ?>" class="group border border-slate-200 bg-white p-7 transition hover:border-emerald-300 hover:bg-emerald-50/30">
                                <h3 class="text-sm font-black uppercase tracking-[0.16em] text-slate-900 transition group-hover:text-emerald-800">Commandement</h3>
                                <p class="mt-4 text-[13px] leading-relaxed text-slate-500">Vue tactique, briefings, état du réseau et cellules actives.</p>
                                <div class="mt-8 flex items-center justify-between border-t border-slate-100 pt-5 text-[10px] font-bold uppercase tracking-widest text-slate-300">
                                    <span>Accès niveau 1</span>
                                    <span class="text-emerald-600 opacity-0 transition group-hover:opacity-100">→</span>
                                </div>
                            </a>

                            <a href="<?= url('formations') ?>" class="group border border-slate-200 bg-white p-7 transition hover:border-emerald-300 hover:bg-emerald-50/30">
                                <h3 class="text-sm font-black uppercase tracking-[0.16em] text-slate-900 transition group-hover:text-emerald-800">Académie</h3>
                                <p class="mt-4 text-[13px] leading-relaxed text-slate-500">Parcours d’instruction, progression et résultats des validations.</p>
                                <div class="mt-8 flex items-center justify-between border-t border-slate-100 pt-5 text-[10px] font-bold uppercase tracking-widest text-slate-300">
                                    <span>En cours</span>
                                    <span class="text-emerald-600 opacity-0 transition group-hover:opacity-100">→</span>
                                </div>
                            </a>

                            <a href="<?= url('documents') ?>" class="group border border-slate-200 bg-white p-7 transition hover:border-emerald-300 hover:bg-emerald-50/30">
                                <h3 class="text-sm font-black uppercase tracking-[0.16em] text-slate-900 transition group-hover:text-emerald-800">Référentiel</h3>
                                <p class="mt-4 text-[13px] leading-relaxed text-slate-500">Doctrines, procédures, fiches et manuels.</p>
                                <div class="mt-8 flex items-center justify-between border-t border-slate-100 pt-5 text-[10px] font-bold uppercase tracking-widest text-slate-300">
                                    <span>Documents</span>
                                    <span class="text-emerald-600 opacity-0 transition group-hover:opacity-100">→</span>
                                </div>
                            </a>
                        </div>
                    </section>

                    <section class="border border-slate-200 bg-white">
                        <div class="border-b border-slate-100 px-6 py-5">
                            <p class="text-[10px] font-bold uppercase tracking-[0.25em] text-emerald-700">Journal</p>
                            <h2 class="mt-2 text-xl font-black uppercase tracking-tight text-slate-950">Activité récente</h2>
                        </div>

                        <div class="divide-y divide-slate-100">
                            <div class="flex items-start justify-between gap-4 px-6 py-5">
                                <div>
                                    <p class="text-sm font-bold uppercase text-slate-900">Feuille de présence validée — section Alpha</p>
                                    <p class="mt-1 text-sm text-slate-500">Pointage opérationnel confirmé avec présence en briefing et signature du registre RH.</p>
                                </div>
                                <span class="whitespace-nowrap text-[10px] font-bold uppercase tracking-[0.2em] text-slate-400">20:15</span>
                            </div>

                            <div class="flex items-start justify-between gap-4 px-6 py-5">
                                <div>
                                    <p class="text-sm font-bold uppercase text-slate-900">Entretien hebdomadaire encadrement enregistré</p>
                                    <p class="mt-1 text-sm text-slate-500">Compte-rendu de suivi RH joint au dossier : disponibilité confirmée pour le prochain cycle.</p>
                                </div>
                                <span class="whitespace-nowrap text-[10px] font-bold uppercase tracking-[0.2em] text-slate-400">18:42</span>
                            </div>

                            <div class="flex items-start justify-between gap-4 px-6 py-5">
                                <div>
                                    <p class="text-sm font-bold uppercase text-slate-900">Mise à jour administrative effectuée</p>
                                    <p class="mt-1 text-sm text-slate-500">Coordonnées de contact d’urgence et habilitation documentaire synchronisées.</p>
                                </div>
                                <span class="whitespace-nowrap text-[10px] font-bold uppercase tracking-[0.2em] text-slate-400">17:10</span>
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
                    $dashNow = new \DateTimeImmutable('now');
                    $hrReviewDate = $dashNow->modify('+12 days')->format('d/m/Y');
                    $hrMedicalDate = $dashNow->modify('+41 days')->format('d/m/Y');
                    $hrRotationDate = $dashNow->modify('+7 days')->format('d/m/Y');
                    ?>
                    <section class="overflow-hidden border border-slate-200 bg-white">
                        <div class="flex items-center justify-between border-b border-slate-100 px-8 py-6">
                            <div class="space-y-1">
                                <p class="text-[10px] font-bold uppercase tracking-[0.28em] text-emerald-700">Identité</p>
                                <h2 class="text-xl font-black uppercase tracking-tight text-slate-950">Dossier personnel</h2>
                            </div>
                            <div class="h-2 w-2 rounded-full bg-emerald-500" aria-hidden="true"></div>
                        </div>

                        <div class="p-8">
                            <div class="flex items-center gap-5">
                                <div class="flex h-16 w-16 items-center justify-center border-2 border-slate-900 bg-slate-50">
                                    <span class="text-xl font-black tracking-tight text-slate-900"><?= htmlspecialchars($initials) ?></span>
                                </div>

                                <div class="min-w-0">
                                    <h3 class="text-2xl font-black uppercase tracking-tight leading-none text-slate-900"><?= htmlspecialchars($displayName) ?></h3>
                                    <p class="mt-2 text-[10px] font-bold uppercase tracking-widest text-slate-400"><?= htmlspecialchars($idLine) ?></p>
                                </div>
                            </div>

                            <div class="mt-10 grid grid-cols-2 gap-px overflow-hidden border border-slate-100 bg-slate-100">
                                <div class="space-y-1 bg-slate-50 p-5">
                                    <span class="block text-[8px] font-bold uppercase tracking-[0.28em] text-slate-400">Statut</span>
                                    <span class="block text-xs font-black uppercase <?= $statut === 'active' ? 'text-emerald-600' : 'text-slate-900' ?>"><?= htmlspecialchars($statutLabel) ?></span>
                                </div>
                                <div class="space-y-1 bg-slate-50 p-5">
                                    <span class="block text-[8px] font-bold uppercase tracking-[0.28em] text-slate-400">Rang</span>
                                    <span class="block text-xs font-black uppercase text-slate-900"><?= htmlspecialchars($gradeName) ?></span>
                                </div>
                                <div class="space-y-1 bg-slate-50 p-5">
                                    <span class="block text-[8px] font-bold uppercase tracking-[0.28em] text-slate-400">Habilitation</span>
                                    <span class="block text-xs font-black uppercase text-slate-900"><?= htmlspecialchars($clearance ?: '—') ?></span>
                                </div>
                                <div class="space-y-1 bg-slate-50 p-5">
                                    <span class="block text-[8px] font-bold uppercase tracking-[0.28em] text-slate-400">Unité</span>
                                    <span class="block text-xs font-black uppercase text-slate-500"><?= htmlspecialchars($squadron) ?></span>
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

                    <section class="overflow-hidden border border-slate-200 bg-white">
                        <div class="border-b border-slate-100 px-6 py-5">
                            <p class="text-[10px] font-bold uppercase tracking-[0.25em] text-emerald-700">Cellule RH</p>
                            <h2 class="mt-2 text-xl font-black uppercase tracking-tight text-slate-950">Disponibilité & suivi</h2>
                        </div>
                        <div class="p-6 space-y-4">
                            <div class="rounded-2xl border border-emerald-200 bg-emerald-50/60 p-4">
                                <p class="text-[10px] font-black uppercase tracking-[0.2em] text-emerald-700">Prochain entretien individuel</p>
                                <p class="mt-1 text-sm font-black uppercase text-slate-900"><?= htmlspecialchars($hrReviewDate) ?></p>
                                <p class="mt-1 text-sm text-slate-600">Objectif: ajustement des objectifs, charge de formation et disponibilité mission.</p>
                            </div>
                            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                                    <p class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-500">Visite médicale</p>
                                    <p class="mt-1 text-xs font-black uppercase text-slate-900">Échéance <?= htmlspecialchars($hrMedicalDate) ?></p>
                                </div>
                                <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                                    <p class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-500">Rotation de service</p>
                                    <p class="mt-1 text-xs font-black uppercase text-slate-900">Prévue le <?= htmlspecialchars($hrRotationDate) ?></p>
                                </div>
                            </div>
                            <p class="text-xs text-slate-500">Les échéances RH sont consolidées avec les obligations d'habilitation pour éviter les indisponibilités opérationnelles.</p>
                        </div>
                    </section>
                                      
                        <!-- Alertes -->
                    <section class="bg-white border border-slate-200">
                        <div class="px-6 py-5 border-b border-slate-100">
                            <p class="text-[10px] font-bold uppercase tracking-[0.25em] text-emerald-700">Surveillance</p>
                            <h2 class="mt-2 text-xl font-black uppercase tracking-tight text-slate-950">Alertes et échéances</h2>
                        </div>
    
                        <div class="divide-y divide-slate-100">
                            <div class="p-6">
                                <div class="flex items-center justify-between gap-4">
                                    <p class="text-sm font-black uppercase text-slate-950">Contrat de réserve à revalider</p>
                                    <span class="text-[10px] px-2 py-1 bg-amber-50 border border-amber-200 text-amber-700 font-black uppercase tracking-[0.2em]">Majeur</span>
                                </div>
                                <p class="mt-2 text-sm text-slate-500">Une annexe administrative est en attente de signature pour maintenir l'éligibilité opérationnelle.</p>
                            </div>
    
                            <div class="p-6">
                                <div class="flex items-center justify-between gap-4">
                                    <p class="text-sm font-black uppercase text-slate-950">Recyclage sécurité non finalisé</p>
                                    <span class="text-[10px] px-2 py-1 bg-slate-100 border border-slate-200 text-slate-600 font-black uppercase tracking-[0.2em]">Suivi</span>
                                </div>
                                <p class="mt-2 text-sm text-slate-500">La qualification annuelle est à 68 %. Clôture recommandée avant prochaine affectation en poste sensible.</p>
                            </div>
    
                            <div class="p-6">
                                <div class="flex items-center justify-between gap-4">
                                    <p class="text-sm font-black uppercase text-slate-950">Point administratif de section planifié</p>
                                    <span class="border border-emerald-200 bg-emerald-50 px-2 py-1 text-[10px] font-bold uppercase tracking-[0.16em] text-emerald-800">Info</span>
                                </div>
                                <p class="mt-2 text-sm text-slate-500">Vérification coordonnée des dossiers de disponibilité, permissions et coordonnées d'urgence.</p>
                            </div>
                        </div>
                    </section>
    
                    <!-- Raccourcis -->
                    <section class="bg-[#050505] text-white border border-slate-800" id="dashboard-service-shortcuts" data-tenant-id="<?= (int) $currentTid ?>">
                        <div class="px-6 py-5 border-b border-white/10">
                            <p class="text-[10px] font-black uppercase tracking-[0.25em] text-white/40">Accès rapide</p>
                            <div class="mt-2 flex items-center justify-between gap-4">
                                <h2 class="text-xl font-black uppercase tracking-tight">Raccourcis de service</h2>
                                <?php if (\App\Core\Gate::getInstance()->allows('dashboard.pins.manage')): ?>
                                    <a href="<?= url('back-office/dashboard-pins') ?>" class="text-[10px] font-bold uppercase tracking-[0.18em] text-emerald-300 hover:text-white">Configurer</a>
                                <?php endif; ?>
                            </div>
                            <p class="mt-2 text-xs text-white/55">Personnalisation locale: choisissez les raccourcis affichés pour cette communauté sur cet appareil.</p>
                        </div>

                        <div class="px-6 py-4 border-b border-white/10 bg-white/[0.02]">
                            <details>
                                <summary class="cursor-pointer text-[10px] font-black uppercase tracking-[0.2em] text-white/70 hover:text-white">Personnaliser les raccourcis visibles</summary>
                                <div class="mt-3 grid grid-cols-2 gap-2 text-[10px] font-bold uppercase tracking-[0.12em] text-white/80">
                                    <label class="inline-flex items-center gap-2"><input type="checkbox" data-shortcut-toggle="shortcut-atak" checked class="h-3.5 w-3.5 rounded border-white/30 bg-transparent">ATAK</label>
                                    <label class="inline-flex items-center gap-2"><input type="checkbox" data-shortcut-toggle="shortcut-orbat" checked class="h-3.5 w-3.5 rounded border-white/30 bg-transparent">ORBAT</label>
                                    <label class="inline-flex items-center gap-2"><input type="checkbox" data-shortcut-toggle="shortcut-fiche" checked class="h-3.5 w-3.5 rounded border-white/30 bg-transparent">Ma fiche</label>
                                    <label class="inline-flex items-center gap-2"><input type="checkbox" data-shortcut-toggle="shortcut-docs" checked class="h-3.5 w-3.5 rounded border-white/30 bg-transparent">Documents</label>
                                    <label class="inline-flex items-center gap-2"><input type="checkbox" data-shortcut-toggle="shortcut-formations" checked class="h-3.5 w-3.5 rounded border-white/30 bg-transparent">Formations</label>
                                    <label class="inline-flex items-center gap-2"><input type="checkbox" data-shortcut-toggle="shortcut-account" checked class="h-3.5 w-3.5 rounded border-white/30 bg-transparent">Paramètres</label>
                                </div>
                            </details>
                        </div>

                        <div class="grid grid-cols-2 gap-px bg-white/10" id="service-shortcuts-grid">
                            <a href="<?= url('atak') ?>" id="shortcut-atak" class="bg-[#050505] p-5 hover:bg-white/[0.04] transition">
                                <p class="text-sm font-black uppercase">ATAK / Tacmap</p>
                                <p class="mt-1 text-xs text-white/50 uppercase">Carte tactique temps réel</p>
                            </a>
                            <a href="<?= url('orbat') ?>" id="shortcut-orbat" class="bg-[#050505] p-5 hover:bg-white/[0.04] transition">
                                <p class="text-sm font-black uppercase">ORBAT / Unité</p>
                                <p class="mt-1 text-xs text-white/50 uppercase">Organisation et personnel</p>
                            </a>
                            <a href="<?= url('personnel/me') ?>" id="shortcut-fiche" class="bg-[#050505] p-5 hover:bg-white/[0.04] transition">
                                <p class="text-sm font-black uppercase">Ma fiche</p>
                                <p class="mt-1 text-xs text-white/50 uppercase">Dossier personnel</p>
                            </a>
                            <a href="<?= url('documents') ?>" id="shortcut-docs" class="bg-[#050505] p-5 hover:bg-white/[0.04] transition">
                                <p class="text-sm font-black uppercase">Documents</p>
                                <p class="mt-1 text-xs text-white/50 uppercase">Ordres et notes</p>
                            </a>
                            <a href="<?= url('formations') ?>" id="shortcut-formations" class="bg-[#050505] p-5 hover:bg-white/[0.04] transition">
                                <p class="text-sm font-black uppercase">Formations</p>
                                <p class="mt-1 text-xs text-white/50 uppercase">Séquences</p>
                            </a>
                            <a href="<?= url('account') ?>" id="shortcut-account" class="bg-[#050505] p-5 hover:bg-white/[0.04] transition">
                                <p class="text-sm font-black uppercase">Paramètres</p>
                                <p class="mt-1 text-xs text-white/50 uppercase">Profil et paramètres</p>
                            </a>
                        </div>
                    </section>
                    <script>
                    (function () {
                        var root = document.getElementById('dashboard-service-shortcuts');
                        if (!root) return;
                        var tid = root.getAttribute('data-tenant-id') || '0';
                        var key = 'athena_dash_shortcuts_' + tid;
                        var toggles = root.querySelectorAll('[data-shortcut-toggle]');
                        function applyState(state) {
                            toggles.forEach(function (toggle) {
                                var targetId = toggle.getAttribute('data-shortcut-toggle');
                                var card = document.getElementById(targetId);
                                if (!card) return;
                                var visible = state[targetId] !== false;
                                toggle.checked = visible;
                                card.classList.toggle('hidden', !visible);
                            });
                        }
                        var state = {};
                        try { state = JSON.parse(localStorage.getItem(key) || '{}') || {}; } catch (_) { state = {}; }
                        applyState(state);
                        toggles.forEach(function (toggle) {
                            toggle.addEventListener('change', function () {
                                var targetId = toggle.getAttribute('data-shortcut-toggle');
                                state[targetId] = !!toggle.checked;
                                localStorage.setItem(key, JSON.stringify(state));
                                applyState(state);
                            });
                        });
                    })();
                    </script>
    
                </aside>
    
            </div>
        </section>
        </div>
    </main>
    </div>
</body>
</html>
