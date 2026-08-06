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
/** @var list<array{id:int,title:string,status:string,status_label:string}> $sseMissions */
/** @var int $sseMissionId */
/** @var string $sseMissionLabel */
/** @var string $sseClassification */
/** @var string $sseClassificationLabel */
/** @var array<string,string> $sseClassificationOptions */
$activeNav = (string) ($activeNav ?? 'dossiers');
$canManage = (bool) ($canManage ?? false);
$canGrant = (bool) ($canGrant ?? false);
$canExport = (bool) ($canExport ?? false);
$isGuest = (bool) ($isGuest ?? false);
$clearanceUntil = (int) ($clearanceUntil ?? 0);
$guestLabel = (string) ($guestLabel ?? '');
$sseTheme = sse_ui_theme_normalize((string) ($sseTheme ?? sse_ui_theme()));
$sseThemeOptions = is_array($sseThemeOptions ?? null) ? $sseThemeOptions : sse_ui_theme_options();
$sseMissions = is_array($sseMissions ?? null) ? $sseMissions : [];
$sseMissionId = (int) ($sseMissionId ?? 0);
$sseMissionLabel = (string) ($sseMissionLabel ?? 'Aucune mission ouverte');
$sseClassification = sse_ui_classification_normalize((string) ($sseClassification ?? sse_ui_classification()));
$sseClassificationLabel = (string) ($sseClassificationLabel ?? sse_ui_classification_label($sseClassification));
$sseClassificationOptions = is_array($sseClassificationOptions ?? null)
    ? $sseClassificationOptions
    : sse_ui_classification_options();
$error = \App\Core\Session::getFlash('error');
$success = \App\Core\Session::getFlash('success');
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$sseContent = $sseContent ?? '';
$pageTitle = (string) ($title ?? 'Renseignement interpersonnel');
$navItems = [
    'dossiers' => ['01', 'Dossiers', url('atak/sse/dossiers')],
    'personnes' => ['02', 'Personnes', url('atak/sse/personnes')],
    'interet' => ['03', 'Dossiers d’intérêt', url('atak/sse/interet')],
    'sites' => ['04', 'Sites exploités', url('atak/sse/sites')],
    'croisements' => ['05', 'Croisements', url('atak/sse/croisements')],
];
if ($canGrant) {
    $navItems['acces'] = ['06', 'Codes d’accès', url('atak/sse/acces')];
}
$themeSwitchUrl = url('atak/sse/apparence');
$missionSwitchUrl = url('atak/sse/contexte/mission');
$classificationSwitchUrl = url('atak/sse/contexte/classification');
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
$classTone = match ($sseClassification) {
    'tres_restreint' => 'is-restricted',
    'confidentiel' => 'is-confidential',
    'interne' => 'is-internal',
    default => 'is-command',
};
$bannerMission = $sseMissionId > 0 ? $sseMissionLabel : 'Hors cycle de mission';
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
    <link rel="stylesheet" href="<?= $h(asset_url('assets/css/sse_portal.css')) ?>?v=202608061000">
</head>
<body class="sse-theme-<?= $h($sseTheme) ?>">

<?php if ($sseTheme === 'console'): ?>
<div class="athena-app">
    <header class="topbar">
        <div class="brand">
            <div class="brand-copy">
                <strong>ATHENA<span>.</span></strong>
                <small>SSE Intelligence Workspace</small>
            </div>
            <span class="brand-badge" title="Sensitive Site Exploitation">SSE</span>
        </div>

        <div class="topbar-context" aria-label="Contexte opérationnel">
            <form class="ctx-field" method="post" action="<?= $h($missionSwitchUrl) ?>" id="sse-mission-form">
                <?= \App\Core\Csrf::field() ?>
                <input type="hidden" name="back" value="<?= $h($currentPath) ?>">
                <label for="sse-mission-select">Mission</label>
                <select id="sse-mission-select" name="mission_id" onchange="this.form.submit()">
                    <?php if ($sseMissions === []): ?>
                        <option value="0">Aucune mission ouverte</option>
                    <?php else: ?>
                        <option value="0" <?= $sseMissionId === 0 ? 'selected' : '' ?>>Hors cycle</option>
                        <?php foreach ($sseMissions as $mission): ?>
                            <option value="<?= (int) $mission['id'] ?>" <?= $sseMissionId === (int) $mission['id'] ? 'selected' : '' ?>>
                                <?= $h($mission['title']) ?> — <?= $h($mission['status_label']) ?>
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </form>

            <form class="ctx-field ctx-field--class <?= $h($classTone) ?>" method="post" action="<?= $h($classificationSwitchUrl) ?>" id="sse-class-form">
                <?= \App\Core\Csrf::field() ?>
                <input type="hidden" name="back" value="<?= $h($currentPath) ?>">
                <label for="sse-class-select">Diffusion</label>
                <select id="sse-class-select" name="classification" onchange="this.form.submit()">
                    <?php foreach ($sseClassificationOptions as $code => $label): ?>
                        <option value="<?= $h($code) ?>" <?= $sseClassification === $code ? 'selected' : '' ?>>
                            <?= $h($label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
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
            <span class="session-meter-sep">·</span>
            <span class="session-meter-clock" id="sse-live-clock" aria-live="off">--:--:--Z</span>
        </div>
        <div class="top-actions">
            <?php if ($canManage): ?>
                <a href="<?= $h(url('atak/sse/dossiers/nouveau')) ?>" class="top-button top-button--accent">Nouveau dossier</a>
            <?php endif; ?>
            <a href="<?= $h(url('atak/sse/quitter')) ?>" class="top-button">Quitter</a>
        </div>
    </header>

    <div class="classif-banner" role="status">
        <span class="classif-banner-mark"><?= $h($sseClassificationLabel) ?></span>
        <span class="classif-banner-text">
            Diffusion restreinte — personnel habilité uniquement — journalisation active — <?= $h($bannerMission) ?>
        </span>
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
                        <span class="sse-nav-num"><?= $h($num) ?></span>
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
        <?= $h($sseClassificationLabel) ?> // Diffusion restreinte // Personnel habilité uniquement // <?= $h($bannerMission) ?>
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
                    <label>Mission</label>
                    <strong>
                        <?php if ($sseMissions === []): ?>
                            Aucune ouverte
                        <?php else: ?>
                            <form class="ctx-inline" method="post" action="<?= $h($missionSwitchUrl) ?>">
                                <?= \App\Core\Csrf::field() ?>
                                <input type="hidden" name="back" value="<?= $h($currentPath) ?>">
                                <select name="mission_id" onchange="this.form.submit()" aria-label="Mission active">
                                    <option value="0" <?= $sseMissionId === 0 ? 'selected' : '' ?>>Hors cycle</option>
                                    <?php foreach ($sseMissions as $mission): ?>
                                        <option value="<?= (int) $mission['id'] ?>" <?= $sseMissionId === (int) $mission['id'] ? 'selected' : '' ?>>
                                            <?= $h($mission['title']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </form>
                        <?php endif; ?>
                    </strong>
                </div>
                <div class="system-status-item">
                    <label>Diffusion</label>
                    <strong>
                        <form class="ctx-inline" method="post" action="<?= $h($classificationSwitchUrl) ?>">
                            <?= \App\Core\Csrf::field() ?>
                            <input type="hidden" name="back" value="<?= $h($currentPath) ?>">
                            <select name="classification" onchange="this.form.submit()" aria-label="Niveau de diffusion">
                                <?php foreach ($sseClassificationOptions as $code => $label): ?>
                                    <option value="<?= $h($code) ?>" <?= $sseClassification === $code ? 'selected' : '' ?>>
                                        <?= $h($label) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </form>
                    </strong>
                </div>
                <div class="system-status-item">
                    <label>Session</label>
                    <strong><span class="online-dot"></span><?= $h($sessionKindLabel) ?></strong>
                </div>
                <div class="system-status-item">
                    <label>Durée restante</label>
                    <strong><?= $h($sessionRemainingLabel !== '' ? $sessionRemainingLabel : '—') ?></strong>
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
        <?= $h($sseClassificationLabel) ?> // Diffusion restreinte // Personnel habilité uniquement
    </div>
<?php endif; ?>

<script>
(function () {
  var el = document.getElementById('sse-live-clock');
  if (!el) return;
  function tick() {
    var now = new Date();
    var hh = String(now.getUTCHours()).padStart(2, '0');
    var mm = String(now.getUTCMinutes()).padStart(2, '0');
    var ss = String(now.getUTCSeconds()).padStart(2, '0');
    el.textContent = hh + ':' + mm + ':' + ss + 'Z';
  }
  tick();
  setInterval(tick, 1000);
})();
</script>
</body>
</html>
