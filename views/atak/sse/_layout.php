<?php
declare(strict_types=1);
/** @var string $title */
/** @var string $activeNav */
/** @var bool $canManage */
/** @var bool $canGrant */
/** @var bool $canExport */
/** @var bool $isGuest */
/** @var int $clearanceUntil */
/** @var string $guestLabel */
/** @var string|null $breadcrumbTrail */
/** @var string|null $sysref */
$activeNav = (string) ($activeNav ?? 'dossiers');
$canManage = (bool) ($canManage ?? false);
$canGrant = (bool) ($canGrant ?? false);
$canExport = (bool) ($canExport ?? false);
$isGuest = (bool) ($isGuest ?? false);
$clearanceUntil = (int) ($clearanceUntil ?? 0);
$guestLabel = (string) ($guestLabel ?? '');
$sseTheme = sse_ui_theme_normalize((string) ($sseTheme ?? sse_ui_theme()));
$sseThemeOptions = is_array($sseThemeOptions ?? null) ? $sseThemeOptions : sse_ui_theme_options();
$error = \App\Core\Session::getFlash('error');
$success = \App\Core\Session::getFlash('success');
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$sseContent = $sseContent ?? '';
$pageTitle = (string) ($title ?? 'Renseignement interpersonnel');
$navItems = [
    'dossiers' => ['01', 'Dossiers', url('atak/sse/dossiers')],
    'personnes' => ['02', 'Personnes', url('atak/sse/personnes')],
    'sites' => ['03', 'Sites exploités', url('atak/sse/sites')],
    'croisements' => ['04', 'Croisements', url('atak/sse/croisements')],
];
if ($canGrant) {
    $navItems['acces'] = ['05', 'Codes d’accès', url('atak/sse/acces')];
}
$themeSwitchUrl = url('atak/sse/apparence');
$currentPath = (string) ($_SERVER['REQUEST_URI'] ?? url('atak/sse/dossiers'));
$nowTs = time();
$expiresLabel = $clearanceUntil > 0 ? date('H:i', $clearanceUntil) : '';
$sessionRemainingLabel = '';
if ($clearanceUntil > $nowTs) {
    $remainSec = $clearanceUntil - $nowTs;
    $remainH = intdiv($remainSec, 3600);
    $remainM = intdiv($remainSec % 3600, 60);
    $sessionRemainingLabel = $remainH > 0
        ? sprintf('%dh %02d', $remainH, $remainM)
        : sprintf('%d min', max(1, $remainM));
} elseif ($clearanceUntil > 0) {
    $sessionRemainingLabel = 'expirée';
}
$sessionKindLabel = $isGuest ? 'Session invitée' : 'Session authentifiée';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title><?= $h($pageTitle) ?> — SSE</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Archivo:wdth,wght@75,400;75,600;75,700;75,800;75,900&family=JetBrains+Mono:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= $h(asset_url('assets/css/sse_portal.css')) ?>?v=202607301900">
</head>
<body class="sse-theme-<?= $h($sseTheme) ?>">

<?php if ($sseTheme === 'console'): ?>
<div class="athena-app">
    <header class="topbar">
        <div class="brand">
            <div class="brand-copy">
                <strong>ATHENA<span>.</span></strong>
                <small>Renseignement interpersonnel</small>
            </div>
            <span class="brand-badge" title="Sensitive Site Exploitation">SSE</span>
        </div>
        <div class="session-meter" aria-label="Durée de session">
            <span class="session-meter-kind"><?= $h($sessionKindLabel) ?></span>
            <?php if ($isGuest && $guestLabel !== ''): ?>
                <span class="session-meter-sep">·</span>
                <span class="session-meter-guest"><?= $h($guestLabel) ?></span>
            <?php endif; ?>
            <?php if ($sessionRemainingLabel !== ''): ?>
                <span class="session-meter-sep">·</span>
                <span class="session-meter-remain">
                    <em>Durée restante</em>
                    <strong><?= $h($sessionRemainingLabel) ?></strong>
                </span>
            <?php endif; ?>
            <?php if ($expiresLabel !== ''): ?>
                <span class="session-meter-sep">·</span>
                <span class="session-meter-exp">Fin <?= $h($expiresLabel) ?></span>
            <?php endif; ?>
        </div>
        <div class="top-actions">
            <a href="<?= $h(url('atak/sse/quitter')) ?>" class="top-button">Quitter la session</a>
        </div>
    </header>

    <div class="classif-banner" role="status">
        <span class="classif-banner-mark">Confidentiel</span>
        <span class="classif-banner-text">Diffusion restreinte — personnel habilité uniquement — journalisation active</span>
    </div>

    <section class="sse-header">
        <div class="sse-header-inner">
            <div class="sse-identity">
                <div class="sse-code">SSE</div>
                <div class="sse-title">
                    <strong>Renseignement interpersonnel</strong>
                    <small>Compartiment opérationnel Athena</small>
                </div>
            </div>
            <nav class="sse-nav" aria-label="Sections SSE">
                <?php foreach ($navItems as $key => [$num, $label, $href]): ?>
                    <a href="<?= $h($href) ?>" class="<?= $activeNav === $key ? 'is-active' : '' ?>">
                        <?= $h($label) ?>
                    </a>
                <?php endforeach; ?>
            </nav>
        </div>
    </section>

    <main class="page-shell">
        <?php if ($error): ?><div class="flash flash--error"><?= $h($error) ?></div><?php endif; ?>
        <?php if ($success): ?><div class="flash flash--ok"><?= $h($success) ?></div><?php endif; ?>
        <?= $sseContent ?>

        <div class="security-footer">
            <span>Athena // SSE // Journalisation active</span>
            <span>
                Apparence
                <?php foreach ($sseThemeOptions as $key => $opt): ?>
                    <?php if ($key === $sseTheme): ?>
                        · <strong><?= $h($opt['label']) ?></strong>
                    <?php else: ?>
                        ·
                        <form class="theme-inline-form" method="post" action="<?= $h($themeSwitchUrl) ?>">
                            <?= \App\Core\Csrf::field() ?>
                            <input type="hidden" name="theme" value="<?= $h($key) ?>">
                            <input type="hidden" name="back" value="<?= $h($currentPath) ?>">
                            <button type="submit"><?= $h($opt['label']) ?></button>
                        </form>
                    <?php endif; ?>
                <?php endforeach; ?>
            </span>
        </div>
    </main>
</div>
<div class="side-marker" aria-hidden="true">ATHENA</div>

<?php else: ?>
    <div class="classification-bar">
        Confidentiel // Diffusion restreinte // Personnel habilité uniquement
    </div>

    <header class="system-header">
        <div class="system-header-main">
            <div class="system-identity">
                <div class="system-seal">
                    <div class="system-seal-inner">SSE</div>
                </div>
                <div class="system-name">
                    <small>Athena // Environnement systèmes spéciaux</small>
                    <strong>Fiches de renseignement interpersonnel</strong>
                    <span>Compartiment opérationnel // nœud local</span>
                </div>
            </div>
            <div class="system-status">
                <div class="system-status-item">
                    <label>Session</label>
                    <strong><span class="online-dot"></span><?= $h($sessionKindLabel) ?></strong>
                </div>
                <div class="system-status-item">
                    <label>Durée restante</label>
                    <strong><?= $h($sessionRemainingLabel !== '' ? $sessionRemainingLabel : '—') ?></strong>
                </div>
                <div class="system-status-item">
                    <label>Fin de session</label>
                    <strong><?= $h($expiresLabel !== '' ? $expiresLabel : '—') ?></strong>
                </div>
            </div>
        </div>
    </header>

    <div class="nav-shell">
        <div class="nav-inner">
            <nav class="nav-main">
                <?php foreach ($navItems as $key => [$num, $label, $href]): ?>
                    <a href="<?= $h($href) ?>" class="<?= $activeNav === $key ? 'is-active' : '' ?>">
                        <?= $h($num) ?> // <?= $h($label) ?>
                    </a>
                <?php endforeach; ?>
            </nav>
            <div class="session-panel">
                <?php if ($isGuest && $guestLabel !== ''): ?>
                    <span><?= $h($guestLabel) ?></span>
                    <span>//</span>
                <?php endif; ?>
                <a href="<?= $h(url('atak/sse/quitter')) ?>">Quitter la session</a>
            </div>
        </div>
    </div>

    <main class="page-shell">
        <?php if ($error): ?><div class="flash flash--error"><?= $h($error) ?></div><?php endif; ?>
        <?php if ($success): ?><div class="flash flash--ok"><?= $h($success) ?></div><?php endif; ?>
        <?= $sseContent ?>
    </main>

    <footer class="system-footer">
        <span>Athena // SSE // Renseignement interpersonnel</span>
        <span>
            Apparence
            <?php foreach ($sseThemeOptions as $key => $opt): ?>
                <?php if ($key === $sseTheme): ?>
                    · <strong><?= $h($opt['label']) ?></strong>
                <?php else: ?>
                    ·
                    <form class="theme-inline-form" method="post" action="<?= $h($themeSwitchUrl) ?>">
                        <?= \App\Core\Csrf::field() ?>
                        <input type="hidden" name="theme" value="<?= $h($key) ?>">
                        <input type="hidden" name="back" value="<?= $h($currentPath) ?>">
                        <button type="submit"><?= $h($opt['label']) ?></button>
                    </form>
                <?php endif; ?>
            <?php endforeach; ?>
        </span>
    </footer>

    <div class="classification-bar classification-bar--bottom">
        Confidentiel // Diffusion restreinte // Personnel habilité uniquement
    </div>
<?php endif; ?>

</body>
</html>
