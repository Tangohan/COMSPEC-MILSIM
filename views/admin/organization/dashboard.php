<?php
declare(strict_types=1);

$kpis = $adminKpis ?? [];
$blockError = $adminKpiBlockError ?? null;
$tenantName = isset($tenantName) ? (string) $tenantName : '';

$gate = \App\Core\Gate::getInstance();
$canInv = $gate->allows('admin.organization') || $gate->allows('admin.access') || $gate->allows('invitations.send');
$canMemberModeration = $gate->allows('admin.members.moderate');
$canDocs = $gate->allows('documents.upload') || $gate->allows('admin.access');
$canTraining = $gate->allows('training.manage') || $gate->allows('training.assign') || $gate->allows('admin.access');
$canTenantTechModules = $gate->allows('admin.system') || $gate->allows('admin.organization') || $gate->allows('admin.access');
$canSeniorityBoTile = $gate->allows('admin.organization') || $gate->allows('admin.access') || $gate->allows('site.support');

$orgFormatDt = static function (?string $raw): string {
    if ($raw === null || $raw === '') {
        return '—';
    }
    $t = strtotime($raw);

    return $t ? date('d/m/Y H:i', $t) : htmlspecialchars($raw, ENT_QUOTES, 'UTF-8');
};

/** Libellés métier pour le tableur d’indicateurs (sans jargon technique). */
$kpiLabelFr = static function (string $id, string $fallback): string {
    return match ($id) {
        'members_active' => 'Membres actifs',
        'members_inactive' => 'Autres statuts',
        'invites_pending' => 'Invitations en attente',
        'invites_expired' => 'Invitations expirées',
        'profiles_incomplete' => 'Profils à compléter',
        'members_no_unit' => 'Sans unité',
        'members_no_role' => 'Sans rôle communautaire',
        'active_30d' => 'Actifs sur 30 jours',
        'training_expiring' => 'Formations à échéance (30 j.)',
        'moderation_open' => 'Signalements forum à traiter',
        default => $fallback !== '' ? $fallback : 'Indicateur',
    };
};

$kpiFamilyFr = static function (string $id): string {
    return match ($id) {
        'members_active', 'members_inactive', 'profiles_incomplete', 'members_no_unit', 'members_no_role' => 'Effectifs',
        'active_30d' => 'Activité',
        'invites_pending', 'invites_expired' => 'Accès',
        'training_expiring' => 'Formation',
        'moderation_open' => 'Modération',
        default => 'Synthèse',
    };
};

$kpiStatus = static function (array $k): array {
    $id = (string) ($k['id'] ?? '');
    if (!empty($k['error'])) {
        return ['Indisponible', 'bg-slate-100 text-slate-700 ring-slate-200'];
    }
    $n = is_numeric($k['value'] ?? null) ? (int) $k['value'] : null;
    return match ($id) {
        'invites_expired' => $n !== null && $n > 0
            ? ['À traiter', 'bg-amber-50 text-amber-900 ring-amber-200']
            : ['Rien en attente', 'bg-emerald-50 text-emerald-900 ring-emerald-200'],
        'invites_pending' => $n !== null && $n > 0
            ? ['En cours', 'bg-amber-50 text-amber-900 ring-amber-200']
            : ['Rien en attente', 'bg-emerald-50 text-emerald-900 ring-emerald-200'],
        'profiles_incomplete', 'members_no_unit', 'members_no_role' => $n !== null && $n > 0
            ? ['À corriger', 'bg-amber-50 text-amber-900 ring-amber-200']
            : ['À jour', 'bg-emerald-50 text-emerald-900 ring-emerald-200'],
        'training_expiring' => $n !== null && $n > 0
            ? ['À surveiller', 'bg-sky-50 text-sky-900 ring-sky-200']
            : ['Rien à signaler', 'bg-emerald-50 text-emerald-900 ring-emerald-200'],
        'moderation_open' => $n !== null && $n > 0
            ? ['À traiter', 'bg-rose-50 text-rose-900 ring-rose-200']
            : ['Rien à signaler', 'bg-emerald-50 text-emerald-900 ring-emerald-200'],
        'members_active', 'active_30d' => ['Synthèse', 'bg-blue-50 text-blue-900 ring-blue-200'],
        'members_inactive' => ['Contexte', 'bg-slate-100 text-slate-700 ring-slate-200'],
        default => ['—', 'bg-slate-100 text-slate-700 ring-slate-200'],
    };
};

$rows = $adminRecentActivity ?? [];
$activityError = $adminRecentActivityError ?? null;
$moreUrl = $adminRecentActivityMoreUrl ?? url('back-office/audit');

$kpiHref = static function (string $id) use ($moreUrl): array {
    return match ($id) {
        'members_active', 'members_inactive' => [url('back-office/users'), 'Voir'],
        'active_30d' => [$moreUrl, 'Journal'],
        'invites_pending', 'invites_expired' => [url('back-office/invitations'), 'Gérer'],
        'profiles_incomplete' => [url('back-office/users') . '?filter_incomplete=1', 'Corriger'],
        'members_no_unit' => [url('back-office/users') . '?filter_no_unit=1', 'Corriger'],
        'members_no_role' => [url('back-office/users') . '?filter_no_role=1', 'Corriger'],
        'training_expiring' => [training_lms_admin_url(), 'Ouvrir'],
        'moderation_open' => [url('back-office/forum-moderation'), 'Traiter'],
        default => ['', ''],
    };
};

$wq = $orgWorkQueue ?? [
    'expired_invitations' => [],
    'training_expiring' => [],
    'incomplete_profiles' => [],
    'users_without_unit' => [],
    'users_without_role' => [],
    'error_invitations' => null,
    'error_training' => null,
    'error_incomplete' => null,
    'error_no_unit' => null,
    'error_no_role' => null,
];
$mod = $orgModerationRecent ?? [];
$modErr = $orgModerationError ?? null;

$nowLabel = date('d/m/Y · H:i');
$showPlatformEnv = $gate->allows('admin.system');
$envLabel = '';
if ($showPlatformEnv) {
    $appEnv = function_exists('env') ? (string) env('APP_ENV', 'local') : 'local';
    $envLabel = app_environment_label_fr($appEnv);
}

$modActionLabelFr = static function (string $t): string {
    $k = strtolower(trim($t));

    return match ($k) {
        'mute' => 'Limitation des échanges',
        'suspend' => 'Suspension temporaire',
        'ban' => 'Exclusion',
        'warn', 'warning' => 'Avertissement',
        '' => 'Mesure enregistrée',
        default => 'Mesure enregistrée',
    };
};
?>
<style>
    .bo-sheet { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 0.8125rem; }
    .bo-sheet thead th {
        position: sticky; top: 0; z-index: 2;
        background: #0f172a; color: #e2e8f0;
        border-bottom: 1px solid #1e293b;
        padding: 0.7rem 0.85rem;
        text-align: left;
        font-size: 0.625rem;
        font-weight: 900;
        letter-spacing: 0.14em;
        text-transform: uppercase;
        white-space: nowrap;
    }
    .bo-sheet thead th.num { text-align: right; }
    .bo-sheet tbody td {
        padding: 0.65rem 0.85rem;
        border-bottom: 1px solid #e2e8f0;
        border-right: 1px solid #f1f5f9;
        vertical-align: middle;
        color: #0f172a;
        background: #fff;
    }
    .bo-sheet tbody td:last-child { border-right: none; }
    .bo-sheet tbody tr:nth-child(even) td { background: #f8fafc; }
    .bo-sheet tbody tr:hover td { background: #eff6ff; }
    .bo-sheet tbody tr:last-child td { border-bottom: none; }
    .bo-sheet .num { text-align: right; font-variant-numeric: tabular-nums; font-weight: 700; }
    .bo-sheet .mono { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; font-size: 0.75rem; }
    .bo-sheet-wrap {
        max-height: min(70vh, 42rem);
        overflow: auto;
        border: 1px solid #cbd5e1;
        border-radius: 0.75rem;
        background: #fff;
        box-shadow: 0 1px 2px rgba(15, 23, 42, 0.06);
    }
    .bo-sheet-toolbar {
        display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 0.75rem;
        padding: 0.85rem 1rem;
        border: 1px solid #cbd5e1; border-bottom: none;
        border-radius: 0.75rem 0.75rem 0 0;
        background: linear-gradient(180deg, #f8fafc, #fff);
    }
    .bo-sheet-panel { border-radius: 0.75rem; }
    .bo-sheet-panel .bo-sheet-wrap { border-radius: 0 0 0.75rem 0.75rem; }
</style>
<?php
$setupBanner = is_array($initialSetupBanner ?? null) ? $initialSetupBanner : null;
$discordInviteMissing = !empty($discordInviteMissing);
?>
<?php if (!empty($isBackOfficeShell)): ?>
<div class="ath-dash-page">
    <?php if ($discordInviteMissing): ?>
    <div class="ath-banner-warn ath-rise" role="alert">
        <div class="ath-banner-warn__kicker">Recrutement Discord</div>
        <div class="ath-banner-warn__text">Le recrutement via Discord est actif, mais aucun lien d’invitation n’est renseigné. Les candidats ne peuvent pas rejoindre votre serveur depuis le formulaire.</div>
        <a href="<?= htmlspecialchars(url('back-office/organisation/parametres') . '#contact', ENT_QUOTES, 'UTF-8') ?>" class="ath-btn ath-btn--solid" style="margin-top:12px;display:inline-flex;">Renseigner le lien</a>
    </div>
    <?php endif; ?>
    <?php if ($setupBanner !== null): ?>
    <div class="ath-banner-warn ath-rise" role="status">
        <div class="ath-banner-warn__kicker">Configuration initiale · <?= (int) ($setupBanner['percent'] ?? 0) ?> %</div>
        <div class="ath-banner-warn__text">Finalisez les réglages essentiels : logo, contact, inscription et modules publics.</div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:12px;">
            <a href="<?= htmlspecialchars(url('back-office/configuration-initiale'), ENT_QUOTES, 'UTF-8') ?>" class="ath-btn ath-btn--solid">Continuer</a>
            <form method="post" action="<?= htmlspecialchars(url('back-office/configuration-initiale/dismiss'), ENT_QUOTES, 'UTF-8') ?>" class="inline">
                <?= \App\Core\Csrf::field() ?>
                <button type="submit" class="ath-btn">Plus tard</button>
            </form>
        </div>
    </div>
    <?php endif; ?>
    <?php require base_path('views/partials/ath_dashboard_dash.php'); ?>
</div>
<?php else: ?>
<div class="min-h-0 flex-1 flex flex-col">
<div
    class="org-dash"
    x-data="{ tab: 'overview' }"
    x-init="if (location.hash === '#rh') { tab = 'rh'; } else if (location.hash === '#watch') { tab = 'watch'; }"
>
    <div class="org-dash__frame">

        <?php if ($discordInviteMissing): ?>
        <div class="org-dash__setup org-dash__setup--warn" role="alert">
            <div class="org-dash__setup-inner">
                <div class="org-dash__setup-copy">
                    <p class="org-dash__setup-kicker">Recrutement Discord</p>
                    <p class="org-dash__setup-title">Lien Discord manquant</p>
                    <p class="org-dash__setup-lead">Le recrutement via Discord est actif, mais aucun lien d’invitation n’est renseigné. Les candidats ne peuvent pas rejoindre votre serveur depuis le formulaire.</p>
                </div>
                <div class="org-dash__setup-actions">
                    <a href="<?= htmlspecialchars(url('back-office/organisation/parametres') . '#contact', ENT_QUOTES, 'UTF-8') ?>" class="org-dash__btn org-dash__btn--solid">Renseigner le lien</a>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($setupBanner !== null): ?>
        <div class="org-dash__setup">
            <div class="org-dash__setup-inner">
                <div class="org-dash__setup-copy">
                    <p class="org-dash__setup-kicker">Configuration initiale</p>
                    <p class="org-dash__setup-title">
                        Finalisez les réglages essentiels
                        <span class="org-dash__setup-pct"><?= (int) ($setupBanner['percent'] ?? 0) ?>&nbsp;%</span>
                    </p>
                    <p class="org-dash__setup-lead">Logo, contact, inscription, modules publics — reportable à tout moment.</p>
                    <div class="org-dash__setup-bar" role="progressbar" aria-valuenow="<?= (int) ($setupBanner['percent'] ?? 0) ?>" aria-valuemin="0" aria-valuemax="100">
                        <span style="width:<?= max(0, min(100, (int) ($setupBanner['percent'] ?? 0))) ?>%"></span>
                    </div>
                </div>
                <div class="org-dash__setup-actions">
                    <a href="<?= htmlspecialchars(url('back-office/configuration-initiale'), ENT_QUOTES, 'UTF-8') ?>" class="org-dash__btn org-dash__btn--solid">Continuer</a>
                    <form method="post" action="<?= htmlspecialchars(url('back-office/configuration-initiale/dismiss'), ENT_QUOTES, 'UTF-8') ?>" class="inline">
                        <?= \App\Core\Csrf::field() ?>
                        <button type="submit" class="org-dash__btn org-dash__btn--ghost">Plus tard</button>
                    </form>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <header class="org-dash__hero<?= ($setupBanner !== null || $discordInviteMissing) ? ' org-dash__hero--after-setup' : '' ?>">
            <div class="org-dash__hero-inner">
                <div>
                    <p class="org-dash__brand">Athena · État-major</p>
                    <h1 class="org-dash__title">Centre de <span>pilotage</span></h1>
                    <p class="org-dash__lead">
                        Membres, structure, recrutement et modération pour
                        <?php if ($tenantName !== ''): ?>
                            <strong><?= htmlspecialchars($tenantName, ENT_QUOTES, 'UTF-8') ?></strong>.
                        <?php else: ?>
                            <strong>votre communauté</strong>.
                        <?php endif; ?>
                        Consultez les indicateurs, les alertes formation, puis les tableurs RH et surveillance.
                    </p>
                    <dl class="org-dash__hero-meta">
                        <div class="org-dash__meta-pill">
                            <dt>Affichage</dt>
                            <dd class="tabular-nums"><?= htmlspecialchars($nowLabel, ENT_QUOTES, 'UTF-8') ?></dd>
                        </div>
                        <?php if ($showPlatformEnv && $envLabel !== ''): ?>
                        <div class="org-dash__meta-pill">
                            <dt>Mode</dt>
                            <dd><?= htmlspecialchars($envLabel, ENT_QUOTES, 'UTF-8') ?></dd>
                        </div>
                        <?php endif; ?>
                    </dl>
                </div>
                <div class="org-dash__hero-actions">
                    <a href="<?= url('dashboard') ?>" class="org-dash__btn org-dash__btn--solid">Portail membre</a>
                    <?php if ($gate->allows('admin.system')): ?>
                    <a href="<?= url('admin') ?>" class="org-dash__btn org-dash__btn--warn">Plateforme</a>
                    <?php endif; ?>
                    <a href="<?= htmlspecialchars($moreUrl, ENT_QUOTES, 'UTF-8') ?>" class="org-dash__btn org-dash__btn--ghost">Journal d’audit</a>
                </div>
            </div>
        </header>

        <nav class="org-dash__tabs" aria-label="Sections du tableau de bord">
            <button type="button" class="org-dash__tab" :class="tab === 'overview' && 'is-active'" @click="tab = 'overview'; if (history.replaceState) { history.replaceState(null, '', window.location.pathname + window.location.search); }">Synthèse</button>
            <button type="button" class="org-dash__tab" :class="tab === 'rh' && 'is-active'" @click="tab = 'rh'; if (history.replaceState) { history.replaceState(null, '', window.location.pathname + window.location.search + '#rh'); }">RH &amp; recrutement</button>
            <button type="button" class="org-dash__tab" :class="tab === 'watch' && 'is-active'" @click="tab = 'watch'; if (history.replaceState) { history.replaceState(null, '', window.location.pathname + window.location.search + '#watch'); }">Surveillance</button>
        </nav>

        <div x-show="tab === 'overview'">

        <div class="org-dash__deck">

        <?php
        $orgTrainingFeed = $orgTrainingFeed ?? [];
        $orgTrainingFeedErr = $orgTrainingFeedError ?? null;
        $orgTrainingFeedCompletionAnalytics = $orgTrainingFeedCompletionAnalytics ?? [];
        $trainingFeedBadge = static function (string $cat): array {
            return match ($cat) {
                'training_enrollment_pending' => ['Inscription', 'bg-violet-100 text-violet-950 ring-violet-200/80'],
                'training_course_completed' => ['Réussite', 'bg-emerald-100 text-emerald-950 ring-emerald-200/80'],
                'training_module_blocked' => ['Accompagnement', 'bg-amber-100 text-amber-950 ring-amber-200/80'],
                default => ['Formation', 'bg-slate-100 text-slate-800 ring-slate-200/80'],
            };
        };
        $trainingFeedCount = is_array($orgTrainingFeed) ? count($orgTrainingFeed) : 0;
        $kpiRowCount = is_array($kpis) ? count($kpis) : 0;
        ?>

        <section
            class="org-dash__intro"
            id="org-dash-intro"
            data-org-intro
            data-org-intro-persist="pilotage"
            data-org-intro-default="open"
            aria-labelledby="org-dash-intro-heading"
        >
            <div class="org-dash__intro-bar">
                <div class="org-dash__intro-copy">
                    <p class="org-dash__kicker">Mode d’emploi</p>
                    <h2 id="org-dash-intro-heading" class="org-dash__section-title">Comment utiliser ce centre de pilotage</h2>
                </div>
                <button
                    type="button"
                    class="org-dash__intro-toggle"
                    data-org-intro-toggle
                    aria-expanded="true"
                    aria-controls="org-dash-intro-panel"
                >
                    <span data-org-intro-label>Masquer</span>
                    <i data-org-intro-meta aria-hidden="true">−</i>
                </button>
            </div>
            <div id="org-dash-intro-panel" class="org-dash__intro-panel" data-org-intro-panel>
                <div class="org-dash__intro-body">
                    <p>
                        Cette page regroupe l’essentiel pour piloter
                        <?php if ($tenantName !== ''): ?>
                            <strong><?= htmlspecialchars($tenantName, ENT_QUOTES, 'UTF-8') ?></strong>
                        <?php else: ?>
                            <strong>votre communauté</strong>
                        <?php endif; ?>
                        : effectifs, invitations, formations, recrutement et modération.
                        Les chiffres et listes ci-dessous sont des instantanés ; pour agir, utilisez les raccourcis ou les onglets dédiés.
                    </p>
                    <ul>
                        <li><strong>Synthèse</strong> — indicateurs chiffrés, alertes formation et accès rapides aux tâches fréquentes.</li>
                        <li><strong>RH &amp; recrutement</strong> — candidatures, mouvements d’affectation et profils à compléter, en vue tableur.</li>
                        <li><strong>Surveillance</strong> — invitations expirées, formations à échéance, mesures de modération et journal d’activité.</li>
                    </ul>
                    <p class="org-dash__intro-note">
                        Astuce : le menu latéral liste toutes les rubriques ; cette page sert de tableau de bord, pas de remplacement des écrans métier.
                    </p>
                </div>
            </div>
        </section>
        <script>
        (function () {
          var root = document.getElementById('org-dash-intro');
          if (!root || root.getAttribute('data-org-intro-bound') === '1') return;
          root.setAttribute('data-org-intro-bound', '1');

          var toggle = root.querySelector('[data-org-intro-toggle]');
          var panel = root.querySelector('[data-org-intro-panel]');
          var meta = root.querySelector('[data-org-intro-meta]');
          var label = root.querySelector('[data-org-intro-label]');
          if (!toggle || !panel) return;

          var persistKey = 'athena_org_dash_intro_open_' + (root.getAttribute('data-org-intro-persist') || 'default');
          var defOpen = root.getAttribute('data-org-intro-default') !== 'closed';

          function apply(open) {
            root.classList.toggle('is-open', open);
            root.classList.toggle('is-collapsed', !open);
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            if (open) {
              panel.removeAttribute('hidden');
            } else {
              panel.setAttribute('hidden', '');
            }
            if (meta) meta.textContent = open ? '−' : '+';
            if (label) label.textContent = open ? 'Masquer' : 'Afficher';
            try { localStorage.setItem(persistKey, open ? '1' : '0'); } catch (e) {}
          }

          var stored = null;
          try { stored = localStorage.getItem(persistKey); } catch (e) {}
          if (stored === '1') apply(true);
          else if (stored === '0') apply(false);
          else apply(defOpen);

          toggle.addEventListener('click', function (e) {
            e.preventDefault();
            apply(toggle.getAttribute('aria-expanded') !== 'true');
          });
        })();
        </script>

        <section class="org-dash__section" aria-labelledby="org-kpi-heading">
            <div class="org-dash__section-head">
                <div>
                    <p class="org-dash__kicker">Section 01</p>
                    <h2 id="org-kpi-heading" class="org-dash__section-title">Indicateurs stratégiques</h2>
                    <p class="org-dash__section-lead">Vue tableur des effectifs, accès, formations et modération — chaque ligne mène vers l’écran utile.</p>
                </div>
            </div>

            <?php if ($blockError): ?>
                <div class="org-dash__alert"><?= htmlspecialchars($blockError, ENT_QUOTES, 'UTF-8') ?></div>
            <?php else: ?>
            <div class="bo-sheet-panel">
                <div class="bo-sheet-toolbar">
                    <div>
                        <h3 class="text-sm font-black uppercase tracking-[0.12em] text-slate-800">Tableau de bord chiffré</h3>
                        <p class="mt-0.5 text-xs text-slate-500"><?= (int) $kpiRowCount ?> indicateur(s) · actualisé à l’ouverture de la page.</p>
                    </div>
                    <a href="<?= url('back-office/users') ?>" class="rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-[11px] font-bold uppercase tracking-wide text-slate-700 shadow-sm hover:border-blue-300 hover:text-blue-800">Effectifs</a>
                </div>
                <div class="bo-sheet-wrap" style="max-height:min(50vh,28rem)">
                    <table class="bo-sheet min-w-[44rem]">
                        <thead>
                            <tr>
                                <th style="width:2.5rem">#</th>
                                <th>Famille</th>
                                <th>Indicateur</th>
                                <th class="num">Valeur</th>
                                <th>Situation</th>
                                <th class="num">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($kpis)): ?>
                            <tr><td colspan="6" class="!bg-white px-4 py-12 text-center text-sm text-slate-500">Aucun indicateur disponible pour le moment.</td></tr>
                        <?php else: ?>
                            <?php foreach ($kpis as $i => $k):
                                $kid = (string) ($k['id'] ?? '');
                                $lab = $kpiLabelFr($kid, (string) ($k['label'] ?? ''));
                                [$stLab, $stClass] = $kpiStatus($k);
                                [$href, $actLab] = $kpiHref($kid);
                                ?>
                                <tr>
                                    <td class="num text-slate-400"><?= (int) ($i + 1) ?></td>
                                    <td class="text-slate-500"><?= htmlspecialchars($kpiFamilyFr($kid), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td class="font-semibold"><?= htmlspecialchars($lab, ENT_QUOTES, 'UTF-8') ?></td>
                                    <td class="num">
                                        <?php if (!empty($k['error'])): ?>
                                            <span class="text-rose-600"><?= htmlspecialchars((string) $k['error'], ENT_QUOTES, 'UTF-8') ?></span>
                                        <?php else: ?>
                                            <?= htmlspecialchars((string) ($k['value'] ?? '—'), ENT_QUOTES, 'UTF-8') ?>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="inline-flex rounded-md px-2 py-0.5 text-[10px] font-black uppercase tracking-wide ring-1 ring-inset <?= htmlspecialchars($stClass, ENT_QUOTES, 'UTF-8') ?>">
                                            <?= htmlspecialchars($stLab, ENT_QUOTES, 'UTF-8') ?>
                                        </span>
                                    </td>
                                    <td class="num">
                                        <?php if ($href !== '' && $actLab !== ''): ?>
                                            <a href="<?= htmlspecialchars($href, ENT_QUOTES, 'UTF-8') ?>" class="inline-flex rounded-md border border-slate-200 bg-white px-2.5 py-1 text-[11px] font-bold uppercase tracking-wide text-slate-700 hover:border-blue-300 hover:bg-blue-50 hover:text-blue-800"><?= htmlspecialchars($actLab, ENT_QUOTES, 'UTF-8') ?></a>
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
            </div>
            <?php endif; ?>
        </section>

        <?php if ($canTraining): ?>
        <section class="org-dash__section" aria-labelledby="org-training-feed-heading">
            <div class="org-dash__section-head">
                <div>
                    <p class="org-dash__kicker">Section 02</p>
                    <h2 id="org-training-feed-heading" class="org-dash__section-title">Formations — alertes récentes</h2>
                    <p class="org-dash__section-lead">Inscriptions à valider, parcours terminés et demandes d’aide sur un module — à traiter juste après les indicateurs.</p>
                </div>
                <a href="<?= htmlspecialchars(training_lms_admin_url('enrollments'), ENT_QUOTES, 'UTF-8') ?>" class="org-dash__section-link">Assignations →</a>
            </div>
            <div class="bo-sheet-panel">
                <div class="bo-sheet-toolbar">
                    <div>
                        <h3 class="text-sm font-black uppercase tracking-[0.12em] text-slate-800">Fil formation</h3>
                        <p class="mt-0.5 text-xs text-slate-500"><?= (int) $trainingFeedCount ?> alerte(s) récente(s).</p>
                    </div>
                    <a href="<?= htmlspecialchars(training_lms_admin_url(), ENT_QUOTES, 'UTF-8') ?>" class="rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-[11px] font-bold uppercase tracking-wide text-slate-700 shadow-sm hover:border-blue-300 hover:text-blue-800">Espace formation</a>
                </div>
                <?php if ($orgTrainingFeedErr): ?>
                    <div class="border border-t-0 border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800"><?= htmlspecialchars($orgTrainingFeedErr, ENT_QUOTES, 'UTF-8') ?></div>
                <?php endif; ?>
                <div class="bo-sheet-wrap" style="max-height:min(50vh,28rem)">
                    <table class="bo-sheet min-w-[52rem]">
                        <thead>
                            <tr>
                                <th style="width:2.5rem">#</th>
                                <th>Type</th>
                                <th>Titre</th>
                                <th>Détail</th>
                                <th>Date</th>
                                <th class="num">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if ($orgTrainingFeedErr): ?>
                            <tr><td colspan="6" class="!bg-white px-4 py-8 text-center text-sm text-slate-500">Alertes formation temporairement indisponibles.</td></tr>
                        <?php elseif ($orgTrainingFeed === []): ?>
                            <tr><td colspan="6" class="!bg-white px-4 py-12 text-center text-sm text-slate-500">Aucune alerte récente liée aux formations.</td></tr>
                        <?php else: ?>
                            <?php foreach ($orgTrainingFeed as $i => $frow):
                                $cat = (string) ($frow['category'] ?? '');
                                [$catLab, $catClass] = $trainingFeedBadge($cat);
                                $fLink = trim((string) ($frow['link_url'] ?? ''));
                                $fb = trim((string) ($frow['body'] ?? ''));
                                $feedRowId = (int) ($frow['id'] ?? 0);
                                $analyticsBlock = $feedRowId > 0 ? ($orgTrainingFeedCompletionAnalytics[$feedRowId] ?? null) : null;
                                $analyticsLines = is_array($analyticsBlock) ? ($analyticsBlock['lines'] ?? []) : [];
                                $detailParts = [];
                                if ($fb !== '') {
                                    $detailParts[] = $fb;
                                }
                                if ($cat === 'training_course_completed' && $analyticsLines !== []) {
                                    foreach ($analyticsLines as $aline) {
                                        $aline = trim((string) $aline);
                                        if ($aline !== '') {
                                            $detailParts[] = $aline;
                                        }
                                    }
                                }
                                $detail = $detailParts !== [] ? implode(' · ', $detailParts) : '—';
                                ?>
                                <tr>
                                    <td class="num text-slate-400"><?= (int) ($i + 1) ?></td>
                                    <td>
                                        <span class="inline-flex rounded-md px-2 py-0.5 text-[10px] font-black uppercase tracking-wide ring-1 ring-inset <?= htmlspecialchars($catClass, ENT_QUOTES, 'UTF-8') ?>">
                                            <?= htmlspecialchars($catLab, ENT_QUOTES, 'UTF-8') ?>
                                        </span>
                                    </td>
                                    <td class="font-semibold"><?= htmlspecialchars((string) ($frow['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td class="text-slate-600 max-w-[18rem]" title="<?= htmlspecialchars($detail, ENT_QUOTES, 'UTF-8') ?>">
                                        <span class="line-clamp-2"><?= htmlspecialchars($detail, ENT_QUOTES, 'UTF-8') ?></span>
                                    </td>
                                    <td class="mono text-slate-500 whitespace-nowrap"><?= htmlspecialchars($orgFormatDt(isset($frow['created_at']) ? (string) $frow['created_at'] : null), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td class="num">
                                        <?php if ($fLink !== ''): ?>
                                            <a href="<?= htmlspecialchars($fLink, ENT_QUOTES, 'UTF-8') ?>" class="inline-flex rounded-md border border-slate-200 bg-white px-2.5 py-1 text-[11px] font-bold uppercase tracking-wide text-slate-700 hover:border-blue-300 hover:bg-blue-50 hover:text-blue-800">Ouvrir</a>
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
            </div>
        </section>
        <?php endif; ?>

        <section class="org-dash__section" aria-labelledby="org-actions-rapides-heading">
            <div class="org-dash__section-head">
                <div>
                    <p class="org-dash__kicker">Section 03</p>
                    <h2 id="org-actions-rapides-heading" class="org-dash__section-title">Raccourcis</h2>
                    <p class="org-dash__section-lead">Accès direct aux tâches fréquentes — le menu latéral liste l’ensemble des rubriques.</p>
                </div>
            </div>
            <div class="org-dash__grid org-dash__grid--actions">
                <a href="<?= url('back-office/users/create') ?>" class="org-dash__action">
                    <div>
                        <div class="org-dash__action-top">
                            <span class="org-dash__action-mark org-dash__action-mark--blue" aria-hidden="true">+</span>
                            <span class="org-dash__action-tag">Effectifs</span>
                        </div>
                        <h3 class="org-dash__action-title">Nouveau membre</h3>
                        <p class="org-dash__action-text">Créer un compte et préparer l’arrivée d’un opérateur.</p>
                    </div>
                    <span class="org-dash__action-foot">Ouvrir →</span>
                </a>
                <a href="<?= url('back-office/groups/create') ?>" class="org-dash__action">
                    <div>
                        <div class="org-dash__action-top">
                            <span class="org-dash__action-mark" aria-hidden="true">G</span>
                            <span class="org-dash__action-tag">Structure</span>
                        </div>
                        <h3 class="org-dash__action-title">Nouveau groupe</h3>
                        <p class="org-dash__action-text">Organiser une sous-unité ou une cellule.</p>
                    </div>
                    <span class="org-dash__action-foot">Ouvrir →</span>
                </a>
                <a href="<?= url('back-office/teams/create') ?>" class="org-dash__action">
                    <div>
                        <div class="org-dash__action-top">
                            <span class="org-dash__action-mark" aria-hidden="true">E</span>
                            <span class="org-dash__action-tag">Équipes</span>
                        </div>
                        <h3 class="org-dash__action-title">Nouvelle équipe</h3>
                        <p class="org-dash__action-text">Constituer une équipe pour une mission ou un créneau.</p>
                    </div>
                    <span class="org-dash__action-foot">Ouvrir →</span>
                </a>
                <?php if ($canInv): ?>
                <a href="<?= url('back-office/invitations') ?>" class="org-dash__action">
                    <div>
                        <div class="org-dash__action-top">
                            <span class="org-dash__action-mark org-dash__action-mark--amber" aria-hidden="true">@</span>
                            <span class="org-dash__action-tag">Accès</span>
                        </div>
                        <h3 class="org-dash__action-title">Invitations</h3>
                        <p class="org-dash__action-text">Inviter par e-mail et suivre les liens envoyés.</p>
                    </div>
                    <span class="org-dash__action-foot">Ouvrir →</span>
                </a>
                <?php endif; ?>
                <?php if ($canMemberModeration): ?>
                <a href="<?= url('back-office/moderation') ?>" class="org-dash__action">
                    <div>
                        <div class="org-dash__action-top">
                            <span class="org-dash__action-mark org-dash__action-mark--rose" aria-hidden="true">!</span>
                            <span class="org-dash__action-tag">Modération</span>
                        </div>
                        <h3 class="org-dash__action-title">Restrictions</h3>
                        <p class="org-dash__action-text">Sanctions, limitations et suivi des comptes concernés.</p>
                    </div>
                    <span class="org-dash__action-foot">Ouvrir →</span>
                </a>
                <?php endif; ?>
                <a href="<?= url('back-office/centre-operations') ?>" class="org-dash__action">
                    <div>
                        <div class="org-dash__action-top">
                            <span class="org-dash__action-mark org-dash__action-mark--mint" aria-hidden="true">⌁</span>
                            <span class="org-dash__action-tag">Opérations</span>
                        </div>
                        <h3 class="org-dash__action-title">Ops admin</h3>
                        <p class="org-dash__action-text">File actionnable, playbooks incidents, audit et objectifs.</p>
                    </div>
                    <span class="org-dash__action-foot">Ouvrir →</span>
                </a>
                <a href="<?= url('back-office/tableau-operationnel') ?>" class="org-dash__action">
                    <div>
                        <div class="org-dash__action-top">
                            <span class="org-dash__action-mark org-dash__action-mark--blue" aria-hidden="true">▣</span>
                            <span class="org-dash__action-tag">Opérations</span>
                        </div>
                        <h3 class="org-dash__action-title">Tableau opérationnel</h3>
                        <p class="org-dash__action-text">Vue consolidée des fiches, readiness et planning.</p>
                    </div>
                    <span class="org-dash__action-foot">Ouvrir →</span>
                </a>
                <?php if (!empty($orgIntegrationsPlanAllowed)): ?>
                <a href="<?= url('back-office/integrations') ?>" class="org-dash__action">
                    <div>
                        <div class="org-dash__action-top">
                            <span class="org-dash__action-mark org-dash__action-mark--violet" aria-hidden="true">↗</span>
                            <span class="org-dash__action-tag">Connexions</span>
                        </div>
                        <h3 class="org-dash__action-title">Intégrations</h3>
                        <p class="org-dash__action-text">Services liés et paramètres d’interopérabilité.</p>
                    </div>
                    <span class="org-dash__action-foot">Ouvrir →</span>
                </a>
                <?php endif; ?>
                <?php if (\App\Core\Gate::getInstance()->allows('admin.compliance.export')): ?>
                <a href="<?= url('back-office/conformite/export-dossier') ?>" class="org-dash__action">
                    <div>
                        <div class="org-dash__action-top">
                            <span class="org-dash__action-mark org-dash__action-mark--outline" aria-hidden="true">D</span>
                            <span class="org-dash__action-tag">Conformité</span>
                        </div>
                        <h3 class="org-dash__action-title">Export dossier</h3>
                        <p class="org-dash__action-text">Assembler les pièces utiles à un contrôle ou une revue.</p>
                    </div>
                    <span class="org-dash__action-foot">Ouvrir →</span>
                </a>
                <?php endif; ?>
                <?php if ($canSeniorityBoTile): ?>
                <a href="<?= url('back-office/organisation/anciennete') ?>" class="org-dash__action">
                    <div>
                        <div class="org-dash__action-top">
                            <span class="org-dash__action-mark org-dash__action-mark--indigo" aria-hidden="true">A</span>
                            <span class="org-dash__action-tag">Effectifs</span>
                        </div>
                        <h3 class="org-dash__action-title">Ancienneté</h3>
                        <p class="org-dash__action-text">Indicateurs visibles sur les fiches et dans l’espace RH.</p>
                    </div>
                    <span class="org-dash__action-foot">Configurer →</span>
                </a>
                <?php endif; ?>
                <?php if ($canTenantTechModules): ?>
                <a href="<?= url('back-office/ressources/modpacks') ?>" class="org-dash__action org-dash__action--wide">
                    <div>
                        <div class="org-dash__action-top">
                            <span class="org-dash__action-mark" aria-hidden="true">R</span>
                            <span class="org-dash__action-tag">Ressources</span>
                        </div>
                        <h3 class="org-dash__action-title">Modpacks &amp; outils terrain</h3>
                        <p class="org-dash__action-text">Packs mods, cartographie et configuration associée pour votre communauté.</p>
                    </div>
                    <span class="org-dash__action-foot">Ouvrir les ressources →</span>
                </a>
                <?php endif; ?>
            </div>
        </section>

        </div><!-- /.org-dash__deck -->

        </div>

        <?php
        $orgEnlistmentCounts = $orgEnlistmentCounts ?? [];
        $orgEnlistmentRecent = $orgEnlistmentRecent ?? [];
        $orgEnlistmentErr = $orgEnlistmentError ?? null;
        $rhRows = $orgRhRecent ?? [];
        $rhErr = $orgRhRecentError ?? null;
        $ecSubmitted = (int) ($orgEnlistmentCounts['submitted'] ?? 0);
        $ecReviewed = (int) ($orgEnlistmentCounts['reviewed'] ?? 0);
        $ecRejected = (int) ($orgEnlistmentCounts['rejected'] ?? 0);
        $enlistStatusBadge = static function (string $status): array {
            return match ($status) {
                'submitted' => ['En attente', 'bg-amber-50 text-amber-900 ring-amber-200'],
                'reviewed' => ['Traitée', 'bg-emerald-50 text-emerald-900 ring-emerald-200'],
                'rejected' => ['Rejetée', 'bg-rose-50 text-rose-900 ring-rose-200'],
                default => ['Autre état', 'bg-slate-100 text-slate-800 ring-slate-200'],
            };
        };

        $rhAlertSheet = [];
        $pushAlertRows = static function (string $kind, string $kindLabel, string $chip, array $list, string $listUrl) use (&$rhAlertSheet): void {
            foreach ($list as $row) {
                $rhAlertSheet[] = [
                    'kind' => $kind,
                    'kind_label' => $kindLabel,
                    'kind_chip' => $chip,
                    'name' => (string) ($row['display_name'] ?? $row['email'] ?? '—'),
                    'email' => (string) ($row['email'] ?? ''),
                    'user_id' => (int) ($row['id'] ?? 0),
                    'list_url' => $listUrl,
                ];
            }
        };
        if (empty($wq['error_incomplete'])) {
            $pushAlertRows('incomplete', 'Profil incomplet', 'bg-amber-50 text-amber-900 ring-amber-200', $wq['incomplete_profiles'] ?? [], url('back-office/users') . '?filter_incomplete=1');
        }
        if (empty($wq['error_no_unit'])) {
            $pushAlertRows('no_unit', 'Sans unité', 'bg-violet-50 text-violet-900 ring-violet-200', $wq['users_without_unit'] ?? [], url('back-office/users') . '?filter_no_unit=1');
        }
        if (empty($wq['error_no_role'])) {
            $pushAlertRows('no_role', 'Sans rôle', 'bg-orange-50 text-orange-900 ring-orange-200', $wq['users_without_role'] ?? [], url('back-office/users') . '?filter_no_role=1');
        }
        $rhAlertCount = count($rhAlertSheet);
        $rhJournalCount = is_array($rhRows) ? count($rhRows) : 0;
        $rhEnlistCount = is_array($orgEnlistmentRecent) ? count($orgEnlistmentRecent) : 0;
        ?>
        <div x-show="tab === 'rh'" x-cloak class="org-dash__deck space-y-8 lg:space-y-10" id="rh">

        <header class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-[11px] font-black uppercase tracking-[0.28em] text-blue-700">RH &amp; recrutement</p>
                <h2 class="mt-1 text-2xl font-black tracking-tight text-slate-900">Tableur RH</h2>
                <p class="mt-1 max-w-2xl text-sm text-slate-600">Candidatures, mouvements RH et alertes effectifs en vue tableur — pleine largeur.</p>
                <p class="mt-3">
                    <a href="<?= htmlspecialchars(function_exists('effectifs_workspace_url') ? effectifs_workspace_url() : url('back-office/ressources/effectifs'), ENT_QUOTES, 'UTF-8') ?>" class="org-dash__section-link">Ouvrir le bureau effectifs →</a>
                </p>
            </div>
            <dl class="flex flex-wrap gap-3 text-xs">
                <div class="rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 shadow-sm">
                    <dt class="font-bold uppercase tracking-wider text-amber-800">En attente</dt>
                    <dd class="mt-0.5 text-lg font-black tabular-nums text-amber-950"><?= (int) $ecSubmitted ?></dd>
                </div>
                <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2 shadow-sm">
                    <dt class="font-bold uppercase tracking-wider text-emerald-800">Traitées</dt>
                    <dd class="mt-0.5 text-lg font-black tabular-nums text-emerald-950"><?= (int) $ecReviewed ?></dd>
                </div>
                <div class="rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 shadow-sm">
                    <dt class="font-bold uppercase tracking-wider text-rose-800">Rejetées</dt>
                    <dd class="mt-0.5 text-lg font-black tabular-nums text-rose-950"><?= (int) $ecRejected ?></dd>
                </div>
                <div class="rounded-xl border border-slate-200 bg-white px-3 py-2 shadow-sm">
                    <dt class="font-bold uppercase tracking-wider text-slate-500">Alertes</dt>
                    <dd class="mt-0.5 text-lg font-black tabular-nums text-slate-900"><?= (int) $rhAlertCount ?></dd>
                </div>
            </dl>
        </header>

        <section class="bo-sheet-panel" aria-labelledby="org-rh-enlist-heading" x-data="{ filter: 'all' }">
            <div class="bo-sheet-toolbar">
                <div>
                    <h3 id="org-rh-enlist-heading" class="text-sm font-black uppercase tracking-[0.12em] text-slate-800">Candidatures</h3>
                    <p class="mt-0.5 text-xs text-slate-500"><?= (int) $rhEnlistCount ?> dossier(s) récents · répartition par état ci-dessus.</p>
                </div>
                <div class="flex flex-wrap items-center gap-1.5">
                    <?php
                    $enlistFilters = [
                        'all' => 'Tout',
                        'submitted' => 'En attente',
                        'reviewed' => 'Traitées',
                        'rejected' => 'Rejetées',
                    ];
                    foreach ($enlistFilters as $fkey => $flabel):
                    ?>
                    <button
                        type="button"
                        @click="filter = '<?= htmlspecialchars($fkey, ENT_QUOTES, 'UTF-8') ?>'"
                        :class="filter === '<?= htmlspecialchars($fkey, ENT_QUOTES, 'UTF-8') ?>' ? 'bg-slate-900 text-white' : 'bg-white text-slate-600 hover:bg-slate-50'"
                        class="rounded-lg border border-slate-200 px-2.5 py-1.5 text-[11px] font-bold uppercase tracking-wide shadow-sm"
                    ><?= htmlspecialchars($flabel, ENT_QUOTES, 'UTF-8') ?></button>
                    <?php endforeach; ?>
                    <a href="<?= url('back-office/recruitments') ?>" class="rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-[11px] font-bold uppercase tracking-wide text-slate-700 shadow-sm hover:border-blue-300 hover:text-blue-800">Liste complète</a>
                </div>
            </div>
            <?php if ($orgEnlistmentErr): ?>
                <div class="border border-t-0 border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800"><?= htmlspecialchars($orgEnlistmentErr, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>
            <div class="bo-sheet-wrap">
                <table class="bo-sheet min-w-[52rem]">
                    <thead>
                        <tr>
                            <th style="width:2.5rem">#</th>
                            <th>Candidat</th>
                            <th>E-mail</th>
                            <th>État</th>
                            <th>Mise à jour</th>
                            <th class="num">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ($orgEnlistmentErr): ?>
                        <tr><td colspan="6" class="!bg-white px-4 py-8 text-center text-sm text-slate-500">Candidatures temporairement indisponibles.</td></tr>
                    <?php elseif (empty($orgEnlistmentRecent)): ?>
                        <tr><td colspan="6" class="!bg-white px-4 py-12 text-center text-sm text-slate-500">Aucune candidature enregistrée pour le moment.</td></tr>
                    <?php else: ?>
                        <?php foreach ($orgEnlistmentRecent as $i => $erow):
                            $st = (string) ($erow['status'] ?? '');
                            [$stLabel, $stClass] = $enlistStatusBadge($st);
                            $eid = (int) ($erow['id'] ?? 0);
                            $name = trim((string) ($erow['first_name'] ?? '') . ' ' . (string) ($erow['last_name'] ?? ''));
                            if ($name === '') {
                                $name = (string) ($erow['email'] ?? '—');
                            }
                            $filterKey = in_array($st, ['submitted', 'reviewed', 'rejected'], true) ? $st : 'all';
                            ?>
                            <tr x-show="filter === 'all' || filter === '<?= htmlspecialchars($filterKey, ENT_QUOTES, 'UTF-8') ?>'">
                                <td class="num text-slate-400"><?= (int) ($i + 1) ?></td>
                                <td class="font-semibold"><?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="text-slate-600"><?= htmlspecialchars((string) ($erow['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                <td>
                                    <span class="inline-flex rounded-md px-2 py-0.5 text-[10px] font-black uppercase tracking-wide ring-1 ring-inset <?= htmlspecialchars($stClass, ENT_QUOTES, 'UTF-8') ?>">
                                        <?= htmlspecialchars($stLabel, ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                </td>
                                <td class="mono text-slate-500 whitespace-nowrap"><?= htmlspecialchars($orgFormatDt(isset($erow['updated_at']) ? (string) $erow['updated_at'] : (isset($erow['created_at']) ? (string) $erow['created_at'] : null)), ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="num">
                                    <a href="<?= url('back-office/recruitments/' . $eid . '?dossier=1') ?>" class="inline-flex rounded-md border border-slate-200 bg-white px-2.5 py-1 text-[11px] font-bold uppercase tracking-wide text-slate-700 hover:border-blue-300 hover:bg-blue-50 hover:text-blue-800">Dossier</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="bo-sheet-panel" aria-labelledby="org-rh-journal-heading">
            <div class="bo-sheet-toolbar">
                <div>
                    <h3 id="org-rh-journal-heading" class="text-sm font-black uppercase tracking-[0.12em] text-slate-800">Fil RH &amp; affectations</h3>
                    <p class="mt-0.5 text-xs text-slate-500"><?= (int) $rhJournalCount ?> mouvement(s) · rôles, comptes, groupes, invitations.</p>
                </div>
                <a href="<?= htmlspecialchars($moreUrl, ENT_QUOTES, 'UTF-8') ?>" class="rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-[11px] font-bold uppercase tracking-wide text-slate-700 shadow-sm hover:border-blue-300 hover:text-blue-800">Journal complet</a>
            </div>
            <?php if ($rhErr): ?>
                <div class="border border-t-0 border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800"><?= htmlspecialchars($rhErr, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>
            <div class="bo-sheet-wrap">
                <table class="bo-sheet min-w-[56rem]">
                    <thead>
                        <tr>
                            <th style="width:2.5rem">#</th>
                            <th style="width:10rem">Date</th>
                            <th>Action</th>
                            <th>Acteur</th>
                            <th>Ancienne valeur</th>
                            <th>Nouvelle valeur</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ($rhErr): ?>
                        <tr><td colspan="6" class="!bg-white px-4 py-8 text-center text-sm text-slate-500">Fil RH temporairement indisponible.</td></tr>
                    <?php elseif (empty($rhRows)): ?>
                        <tr><td colspan="6" class="!bg-white px-4 py-12 text-center text-sm text-slate-500">Aucun mouvement RH récent.</td></tr>
                    <?php else: ?>
                        <?php foreach ($rhRows as $i => $rrow):
                            $rhAction = (string) ($rrow['action'] ?? '');
                            $ov = trim((string) ($rrow['old_value'] ?? ''));
                            $nv = trim((string) ($rrow['new_value'] ?? ''));
                            if (($rhAction === 'group_member_added' || $rhAction === 'group_member_removed') && ($nv !== '' || $ov !== '')) {
                                $ovShow = $ov;
                                $nvShow = $nv !== '' ? $nv : $ov;
                            } else {
                                $ovShow = $ov;
                                $nvShow = $nv;
                            }
                            ?>
                            <tr>
                                <td class="num text-slate-400"><?= (int) ($i + 1) ?></td>
                                <td class="mono text-slate-500 whitespace-nowrap"><?= htmlspecialchars($orgFormatDt(isset($rrow['created_at']) ? (string) $rrow['created_at'] : null), ENT_QUOTES, 'UTF-8') ?></td>
                                <td>
                                    <span class="inline-flex rounded-md bg-indigo-50 px-2 py-0.5 text-[11px] font-bold text-indigo-950 ring-1 ring-indigo-200/80">
                                        <?= htmlspecialchars(audit_action_label_fr($rhAction), ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                </td>
                                <td class="font-medium"><?= htmlspecialchars((string) ($rrow['actor_email'] ?? ('#' . (string) ($rrow['user_id'] ?? ''))), ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="mono text-slate-500 max-w-[14rem] truncate" title="<?= htmlspecialchars($ovShow, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($ovShow !== '' ? $ovShow : '—', ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="mono text-slate-700 max-w-[14rem] truncate" title="<?= htmlspecialchars($nvShow, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($nvShow !== '' ? $nvShow : '—', ENT_QUOTES, 'UTF-8') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="bo-sheet-panel" aria-labelledby="org-rh-alerts-heading" x-data="{ filter: 'all' }">
            <div class="bo-sheet-toolbar">
                <div>
                    <h3 id="org-rh-alerts-heading" class="text-sm font-black uppercase tracking-[0.12em] text-slate-800">Alertes effectifs</h3>
                    <p class="mt-0.5 text-xs text-slate-500">
                        Profils incomplets, sans unité ou sans rôle communautaire
                        <?php if ($tenantName !== ''): ?>
                            — communauté <strong class="font-semibold text-slate-700"><?= htmlspecialchars($tenantName, ENT_QUOTES, 'UTF-8') ?></strong> uniquement
                        <?php else: ?>
                            — communauté active uniquement
                        <?php endif; ?>.
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-1.5">
                    <?php
                    $alertFilters = [
                        'all' => 'Tout',
                        'incomplete' => 'Profils',
                        'no_unit' => 'Sans unité',
                        'no_role' => 'Sans rôle',
                    ];
                    foreach ($alertFilters as $fkey => $flabel):
                    ?>
                    <button
                        type="button"
                        @click="filter = '<?= htmlspecialchars($fkey, ENT_QUOTES, 'UTF-8') ?>'"
                        :class="filter === '<?= htmlspecialchars($fkey, ENT_QUOTES, 'UTF-8') ?>' ? 'bg-slate-900 text-white' : 'bg-white text-slate-600 hover:bg-slate-50'"
                        class="rounded-lg border border-slate-200 px-2.5 py-1.5 text-[11px] font-bold uppercase tracking-wide shadow-sm"
                    ><?= htmlspecialchars($flabel, ENT_QUOTES, 'UTF-8') ?></button>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php
            $alertErrs = array_filter([
                $wq['error_incomplete'] ?? null,
                $wq['error_no_unit'] ?? null,
                $wq['error_no_role'] ?? null,
            ]);
            ?>
            <?php if ($alertErrs !== []): ?>
                <div class="border border-t-0 border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950">
                    <?php foreach ($alertErrs as $ae): ?>
                        <p><?= htmlspecialchars((string) $ae, ENT_QUOTES, 'UTF-8') ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <div class="bo-sheet-wrap">
                <table class="bo-sheet min-w-[48rem]">
                    <thead>
                        <tr>
                            <th style="width:2.5rem">#</th>
                            <th>Type d’alerte</th>
                            <th>Membre</th>
                            <th>E-mail</th>
                            <th class="num">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ($rhAlertSheet === []): ?>
                        <tr><td colspan="5" class="!bg-white px-4 py-12 text-center text-sm text-slate-500">Aucune alerte effectifs — les profils actifs sont complets.</td></tr>
                    <?php else: ?>
                        <?php foreach ($rhAlertSheet as $i => $arow): ?>
                            <tr x-show="filter === 'all' || filter === '<?= htmlspecialchars((string) $arow['kind'], ENT_QUOTES, 'UTF-8') ?>'">
                                <td class="num text-slate-400"><?= (int) ($i + 1) ?></td>
                                <td>
                                    <span class="inline-flex rounded-md px-2 py-0.5 text-[10px] font-black uppercase tracking-wide ring-1 ring-inset <?= htmlspecialchars((string) $arow['kind_chip'], ENT_QUOTES, 'UTF-8') ?>">
                                        <?= htmlspecialchars((string) $arow['kind_label'], ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                </td>
                                <td class="font-semibold"><?= htmlspecialchars((string) $arow['name'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="text-slate-600"><?= htmlspecialchars((string) $arow['email'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="num">
                                    <div class="inline-flex flex-wrap justify-end gap-1.5">
                                        <?php if ((int) $arow['user_id'] > 0): ?>
                                            <a href="<?= url('back-office/users/' . (int) $arow['user_id']) ?>" class="inline-flex rounded-md border border-slate-200 bg-white px-2.5 py-1 text-[11px] font-bold uppercase tracking-wide text-slate-700 hover:border-blue-300 hover:bg-blue-50 hover:text-blue-800">Fiche</a>
                                        <?php endif; ?>
                                        <a href="<?= htmlspecialchars((string) $arow['list_url'], ENT_QUOTES, 'UTF-8') ?>" class="inline-flex rounded-md border border-slate-200 bg-white px-2.5 py-1 text-[11px] font-bold uppercase tracking-wide text-slate-700 hover:border-blue-300 hover:bg-blue-50 hover:text-blue-800">Liste</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        </div>

        <div x-show="tab === 'watch'" x-cloak class="org-dash__deck space-y-8 lg:space-y-10" id="watch">
        <?php
        $watchSheetRows = [];
        $expErr = $wq['error_invitations'] ?? null;
        $trErr = $wq['error_training'] ?? null;
        if (empty($expErr) && is_array($wq['expired_invitations'] ?? null)) {
            foreach ($wq['expired_invitations'] as $inv) {
                $watchSheetRows[] = [
                    'kind' => 'invitation',
                    'kind_label' => 'Invitation expirée',
                    'kind_chip' => 'bg-amber-50 text-amber-900 ring-amber-200',
                    'subject' => (string) ($inv['email'] ?? '—'),
                    'detail' => 'À traiter dans les invitations',
                    'when' => (string) ($inv['expires_at'] ?? ''),
                    'when_label' => $orgFormatDt(isset($inv['expires_at']) ? (string) $inv['expires_at'] : null),
                    'href' => url('back-office/invitations'),
                    'action_label' => 'Gérer',
                ];
            }
        }
        if (empty($trErr) && is_array($wq['training_expiring'] ?? null)) {
            foreach ($wq['training_expiring'] as $trow) {
                $watchSheetRows[] = [
                    'kind' => 'formation',
                    'kind_label' => 'Formation · échéance',
                    'kind_chip' => 'bg-sky-50 text-sky-900 ring-sky-200',
                    'subject' => (string) ($trow['email'] ?? '—'),
                    'detail' => (string) ($trow['course_title'] ?? 'Parcours'),
                    'when' => (string) ($trow['expires_at'] ?? $trow['due_at'] ?? ''),
                    'when_label' => $orgFormatDt(isset($trow['expires_at']) ? (string) $trow['expires_at'] : (isset($trow['due_at']) ? (string) $trow['due_at'] : null)),
                    'href' => training_lms_admin_url(),
                    'action_label' => 'Ouvrir',
                ];
            }
        }
        if (empty($modErr) && is_array($mod)) {
            foreach ($mod as $a) {
                $watchSheetRows[] = [
                    'kind' => 'moderation',
                    'kind_label' => $modActionLabelFr((string) ($a['action_type'] ?? '')),
                    'kind_chip' => 'bg-rose-50 text-rose-900 ring-rose-200',
                    'subject' => (string) ($a['target_email'] ?? '—'),
                    'detail' => !empty($a['actor_email']) ? ('Par ' . (string) $a['actor_email']) : 'Mesure récente',
                    'when' => (string) ($a['created_at'] ?? ''),
                    'when_label' => $orgFormatDt(isset($a['created_at']) ? (string) $a['created_at'] : null),
                    'href' => $canMemberModeration ? url('back-office/moderation') : '',
                    'action_label' => 'Voir',
                ];
            }
        }
        $watchCount = count($watchSheetRows);
        $journalCount = is_array($rows) ? count($rows) : 0;
        ?>

        <header class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-[11px] font-black uppercase tracking-[0.28em] text-blue-700">Surveillance</p>
                <h2 class="mt-1 text-2xl font-black tracking-tight text-slate-900">Tableur opérationnel</h2>
                <p class="mt-1 max-w-2xl text-sm text-slate-600">File à traiter et journal d’activité en vue tableur — pleine largeur, colonnes fixes, défilement vertical.</p>
            </div>
            <dl class="flex flex-wrap gap-3 text-xs">
                <div class="rounded-xl border border-slate-200 bg-white px-3 py-2 shadow-sm">
                    <dt class="font-bold uppercase tracking-wider text-slate-500">À traiter</dt>
                    <dd class="mt-0.5 text-lg font-black tabular-nums text-slate-900"><?= (int) $watchCount ?></dd>
                </div>
                <div class="rounded-xl border border-slate-200 bg-white px-3 py-2 shadow-sm">
                    <dt class="font-bold uppercase tracking-wider text-slate-500">Journal</dt>
                    <dd class="mt-0.5 text-lg font-black tabular-nums text-slate-900"><?= (int) $journalCount ?></dd>
                </div>
            </dl>
        </header>

        <section class="bo-sheet-panel" aria-labelledby="org-watch-sheet-heading" x-data="{ filter: 'all' }">
            <div class="bo-sheet-toolbar">
                <div>
                    <h3 id="org-watch-sheet-heading" class="text-sm font-black uppercase tracking-[0.12em] text-slate-800">File à traiter</h3>
                    <p class="mt-0.5 text-xs text-slate-500">Invitations expirées, formations à échéance et mesures de modération.</p>
                </div>
                <div class="flex flex-wrap items-center gap-1.5" role="group" aria-label="Filtrer la file">
                    <?php
                    $filters = [
                        'all' => 'Tout',
                        'invitation' => 'Invitations',
                        'formation' => 'Formations',
                        'moderation' => 'Modération',
                    ];
                    foreach ($filters as $fkey => $flabel):
                    ?>
                    <button
                        type="button"
                        @click="filter = '<?= htmlspecialchars($fkey, ENT_QUOTES, 'UTF-8') ?>'"
                        :class="filter === '<?= htmlspecialchars($fkey, ENT_QUOTES, 'UTF-8') ?>' ? 'bg-slate-900 text-white' : 'bg-white text-slate-600 hover:bg-slate-50'"
                        class="rounded-lg border border-slate-200 px-2.5 py-1.5 text-[11px] font-bold uppercase tracking-wide shadow-sm"
                    ><?= htmlspecialchars($flabel, ENT_QUOTES, 'UTF-8') ?></button>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php if (!empty($expErr) || !empty($trErr) || !empty($modErr)): ?>
                <div class="border border-t-0 border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950">
                    <?php if (!empty($expErr)): ?><p><?= htmlspecialchars((string) $expErr, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
                    <?php if (!empty($trErr)): ?><p><?= htmlspecialchars((string) $trErr, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
                    <?php if (!empty($modErr)): ?><p><?= htmlspecialchars((string) $modErr, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
                </div>
            <?php endif; ?>
            <div class="bo-sheet-wrap">
                <table class="bo-sheet min-w-[56rem]">
                    <thead>
                        <tr>
                            <th style="width:2.5rem">#</th>
                            <th>Type</th>
                            <th>Personne / e-mail</th>
                            <th>Détail</th>
                            <th>Date</th>
                            <th class="num">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ($watchSheetRows === []): ?>
                        <tr>
                            <td colspan="6" class="!bg-white px-4 py-12 text-center text-sm text-slate-500">
                                Rien à traiter pour le moment — invitations, formations et modération sont au vert.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($watchSheetRows as $i => $wrow): ?>
                            <tr
                                x-show="filter === 'all' || filter === '<?= htmlspecialchars((string) $wrow['kind'], ENT_QUOTES, 'UTF-8') ?>'"
                                data-kind="<?= htmlspecialchars((string) $wrow['kind'], ENT_QUOTES, 'UTF-8') ?>"
                            >
                                <td class="num text-slate-400"><?= (int) ($i + 1) ?></td>
                                <td>
                                    <span class="inline-flex rounded-md px-2 py-0.5 text-[10px] font-black uppercase tracking-wide ring-1 ring-inset <?= htmlspecialchars((string) $wrow['kind_chip'], ENT_QUOTES, 'UTF-8') ?>">
                                        <?= htmlspecialchars((string) $wrow['kind_label'], ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                </td>
                                <td class="font-semibold"><?= htmlspecialchars((string) $wrow['subject'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="text-slate-600"><?= htmlspecialchars((string) $wrow['detail'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="mono text-slate-500 whitespace-nowrap"><?= htmlspecialchars((string) $wrow['when_label'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="num">
                                    <?php if ((string) $wrow['href'] !== ''): ?>
                                        <a href="<?= htmlspecialchars((string) $wrow['href'], ENT_QUOTES, 'UTF-8') ?>" class="inline-flex rounded-md border border-slate-200 bg-white px-2.5 py-1 text-[11px] font-bold uppercase tracking-wide text-slate-700 hover:border-blue-300 hover:bg-blue-50 hover:text-blue-800">
                                            <?= htmlspecialchars((string) $wrow['action_label'], ENT_QUOTES, 'UTF-8') ?>
                                        </a>
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
        </section>

        <section class="bo-sheet-panel" aria-labelledby="org-journal-heading">
            <div class="bo-sheet-toolbar">
                <div>
                    <h3 id="org-journal-heading" class="text-sm font-black uppercase tracking-[0.12em] text-slate-800">Journal opérationnel</h3>
                    <p class="mt-0.5 text-xs text-slate-500">Derniers événements enregistrés pour cette communauté.</p>
                </div>
                <a href="<?= htmlspecialchars($moreUrl, ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-[11px] font-bold uppercase tracking-wide text-slate-700 shadow-sm hover:border-blue-300 hover:text-blue-800">
                    Journal complet
                    <svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" /></svg>
                </a>
            </div>
            <?php if ($activityError): ?>
                <div class="border border-t-0 border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800"><?= htmlspecialchars($activityError, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>
            <div class="bo-sheet-wrap">
                <table class="bo-sheet min-w-[52rem]">
                    <thead>
                        <tr>
                            <th style="width:2.5rem">#</th>
                            <th style="width:10rem">Date</th>
                            <th>Action</th>
                            <th>Acteur</th>
                            <th>Ancienne valeur</th>
                            <th>Nouvelle valeur</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ($activityError): ?>
                        <tr><td colspan="6" class="!bg-white px-4 py-8 text-center text-sm text-slate-500">Journal temporairement indisponible.</td></tr>
                    <?php elseif (empty($rows)): ?>
                        <tr>
                            <td colspan="6" class="!bg-white px-4 py-12 text-center text-sm text-slate-500">
                                Aucun événement récent — le journal se remplira au fil des actions administratives.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($rows as $i => $row):
                            $actionSlug = (string) ($row['action'] ?? '');
                            $oldVal = trim((string) ($row['old_value'] ?? ''));
                            $newVal = trim((string) ($row['new_value'] ?? ''));
                            ?>
                            <tr>
                                <td class="num text-slate-400"><?= (int) ($i + 1) ?></td>
                                <td class="mono text-slate-500 whitespace-nowrap"><?= htmlspecialchars($orgFormatDt(isset($row['created_at']) ? (string) $row['created_at'] : null), ENT_QUOTES, 'UTF-8') ?></td>
                                <td>
                                    <span class="inline-flex rounded-md bg-slate-100 px-2 py-0.5 text-[11px] font-bold text-slate-800 ring-1 ring-slate-200/80">
                                        <?= htmlspecialchars(audit_action_label_fr($actionSlug), ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                </td>
                                <td class="font-medium"><?= htmlspecialchars((string) ($row['actor_email'] ?? ('#' . (string) ($row['user_id'] ?? ''))), ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="mono text-slate-500 max-w-[14rem] truncate" title="<?= htmlspecialchars($oldVal, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($oldVal !== '' ? $oldVal : '—', ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="mono text-slate-700 max-w-[14rem] truncate" title="<?= htmlspecialchars($newVal, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($newVal !== '' ? $newVal : '—', ENT_QUOTES, 'UTF-8') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        </div>

    </div>
</div>
</div>
<?php endif; ?>
