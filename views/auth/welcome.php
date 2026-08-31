<?php
/** @var string $brand */
/** @var string $displayName */
/** @var string $gradeLabel */
/** @var string $unitLabel */
/** @var string|null $avatarUrl */
/** @var string $initials */
/** @var list<array{label: string, when: string}> $changes */
/** @var string $enterUrl */
/** @var string $lockBackgroundUrl */
/** @var string $title */

$brand = trim((string) ($brand ?? (function_exists('email_brand_name') ? email_brand_name() : 'Athena')));
$brandText = htmlspecialchars($brand, ENT_QUOTES, 'UTF-8');
$displayName = trim((string) ($displayName ?? 'Opérateur'));
$gradeLabel = trim((string) ($gradeLabel ?? ''));
$unitLabel = trim((string) ($unitLabel ?? ''));
$avatarUrl = is_string($avatarUrl ?? null) && $avatarUrl !== '' ? $avatarUrl : null;
$initials = trim((string) ($initials ?? 'A'));
$changes = is_array($changes ?? null) ? $changes : [];
$enterUrl = (string) ($enterUrl ?? url('login/accueil'));
$lockBackgroundUrl = (string) ($lockBackgroundUrl ?? asset_url('assets/images/WES_Operator_V2_re_05.jpg'));
$error = \App\Core\Session::getFlash('error');
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$title = (string) ($title ?? 'Bienvenue');
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars(html_lang(), ENT_QUOTES, 'UTF-8') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $h($title) ?> — <?= $brandText ?></title>
    <meta name="theme-color" content="#020706">
    <meta name="robots" content="noindex,nofollow">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Archivo:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #020706;
            --text: #f5f7f7;
            --muted: #a8b2af;
            --green: #30d59a;
        }
        * { box-sizing: border-box; }
        html, body {
            height: 100%;
            margin: 0;
        }
        body {
            font-family: Archivo, system-ui, sans-serif;
            background: #000;
            color: var(--text);
            overflow: hidden;
            -webkit-font-smoothing: antialiased;
        }
        .lock {
            position: relative;
            height: 100vh;
            height: 100dvh;
            width: 100vw;
            background:
                linear-gradient(90deg, rgba(0, 14, 12, .76), rgba(0, 0, 0, .55) 52%, rgba(0, 18, 14, .58)),
                radial-gradient(circle at 72% 36%, rgba(19, 123, 92, .22), transparent 34%),
                url('<?= $h($lockBackgroundUrl) ?>') center / cover no-repeat;
        }
        .lock::after {
            content: "";
            position: absolute;
            inset: 0;
            background: linear-gradient(180deg, rgba(0, 0, 0, .10), rgba(0, 0, 0, .22) 55%, rgba(0, 0, 0, .72));
            pointer-events: none;
        }
        .brand {
            position: absolute;
            top: 34px;
            left: 42px;
            z-index: 2;
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 800;
            letter-spacing: .18em;
            font-size: 14px;
            text-transform: uppercase;
        }
        .brand-dot {
            width: 9px;
            height: 9px;
            border-radius: 50%;
            background: var(--green);
            box-shadow: 0 0 18px var(--green);
        }
        .clock {
            position: absolute;
            left: 58px;
            bottom: 72px;
            z-index: 2;
        }
        .time {
            font-size: clamp(70px, 9vw, 150px);
            line-height: .86;
            font-weight: 300;
            letter-spacing: -.06em;
        }
        .date {
            margin-top: 18px;
            font-size: clamp(18px, 2vw, 30px);
            font-weight: 500;
            color: #d6dcda;
        }
        .hint {
            margin-top: 24px;
            color: #aab4b1;
            font-size: 13px;
            letter-spacing: .12em;
            text-transform: uppercase;
        }
        .profile-layer {
            position: absolute;
            inset: 0;
            z-index: 5;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(0, 0, 0, .44);
            backdrop-filter: blur(15px);
            -webkit-backdrop-filter: blur(15px);
            opacity: 0;
            pointer-events: none;
            transition: opacity .28s ease;
            padding: 1.25rem;
        }
        .profile-layer.show {
            opacity: 1;
            pointer-events: auto;
        }
        .card {
            width: min(460px, calc(100vw - 34px));
            text-align: center;
        }
        .avatar {
            width: 124px;
            height: 124px;
            margin: 0 auto 22px;
            border-radius: 50%;
            border: 2px solid rgba(255, 255, 255, .55);
            object-fit: cover;
            box-shadow: 0 14px 60px rgba(0, 0, 0, .45);
            display: block;
            background: rgba(255, 255, 255, .06);
        }
        .avatar--initials {
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            font-weight: 700;
            letter-spacing: .04em;
            color: #e8f5f0;
        }
        .name {
            font-size: clamp(1.6rem, 4vw, 2rem);
            font-weight: 750;
            letter-spacing: -.03em;
        }
        .meta {
            margin-top: 7px;
            color: #c5cfcc;
            font-size: 15px;
        }
        .unit {
            margin-top: 4px;
            color: #8fa09a;
            font-size: 13px;
        }
        .separator {
            width: 64px;
            height: 1px;
            background: rgba(255, 255, 255, .22);
            margin: 24px auto;
        }
        .changes {
            text-align: left;
            border-top: 1px solid rgba(255, 255, 255, .10);
            border-bottom: 1px solid rgba(255, 255, 255, .10);
        }
        .change {
            padding: 14px 4px;
            display: flex;
            justify-content: space-between;
            gap: 20px;
            border-bottom: 1px solid rgba(255, 255, 255, .07);
            font-size: 13px;
        }
        .change:last-child { border-bottom: 0; }
        .change span:first-child { color: #e9eeec; }
        .change span:last-child {
            color: #7f8c88;
            white-space: nowrap;
            flex-shrink: 0;
        }
        .enter {
            margin-top: 24px;
            border: 1px solid rgba(255, 255, 255, .24);
            background: rgba(0, 0, 0, .20);
            color: white;
            min-width: 220px;
            padding: 13px 20px;
            border-radius: 8px;
            font-weight: 700;
            font-family: inherit;
            font-size: 14px;
            cursor: pointer;
            transition: background .18s, color .18s, border-color .18s;
        }
        .enter:hover,
        .enter:focus-visible {
            background: var(--green);
            color: #00150f;
            border-color: var(--green);
            outline: none;
        }
        .enter:disabled {
            opacity: .7;
            cursor: wait;
        }
        .small {
            margin-top: 13px;
            color: #798580;
            font-size: 11px;
            letter-spacing: .11em;
            text-transform: uppercase;
        }
        .flash {
            position: absolute;
            top: 72px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 6;
            max-width: min(420px, calc(100vw - 2rem));
            padding: .75rem 1rem;
            border-radius: 8px;
            background: rgba(120, 20, 20, .85);
            border: 1px solid rgba(255, 180, 180, .35);
            color: #ffe8e8;
            font-size: 13px;
            text-align: center;
        }
        @media (max-width: 700px) {
            .brand { left: 22px; top: 22px; }
            .clock { left: 24px; bottom: 36px; }
            .change { font-size: 12px; }
        }
        @media (prefers-reduced-motion: reduce) {
            .profile-layer { transition: none; }
            .enter { transition: none; }
        }
    </style>
</head>
<body>
<section class="lock" id="lock" role="main" aria-label="Écran d’accueil <?= $brandText ?>">
    <div class="brand"><span class="brand-dot" aria-hidden="true"></span> <?= $brandText ?></div>

    <?php if ($error): ?>
        <div class="flash" role="alert"><?= $h($error) ?></div>
    <?php endif; ?>

    <div class="clock">
        <div class="time" id="time" aria-live="polite">––:––</div>
        <div class="date" id="date"></div>
        <div class="hint">Appuyez sur Entrée pour continuer</div>
    </div>

    <div class="profile-layer" id="profile" aria-hidden="true">
        <div class="card">
            <?php if ($avatarUrl !== null): ?>
                <img class="avatar" src="<?= $h($avatarUrl) ?>" alt="" width="124" height="124" decoding="async">
            <?php else: ?>
                <div class="avatar avatar--initials" aria-hidden="true"><?= $h($initials) ?></div>
            <?php endif; ?>

            <div class="name"><?= $h($displayName) ?></div>
            <?php if ($gradeLabel !== ''): ?>
                <div class="meta"><?= $h($gradeLabel) ?></div>
            <?php endif; ?>
            <?php if ($unitLabel !== ''): ?>
                <div class="unit"><?= $h($unitLabel) ?></div>
            <?php endif; ?>

            <div class="separator" aria-hidden="true"></div>

            <?php if ($changes !== []): ?>
                <div class="changes">
                    <?php foreach ($changes as $change): ?>
                        <div class="change">
                            <span><?= $h($change['label'] ?? '') ?></span>
                            <span><?= $h($change['when'] ?? '') ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form method="post" action="<?= $h($enterUrl) ?>" id="enter-form">
                <?= \App\Core\Csrf::field() ?>
                <button type="submit" class="enter" id="enter-btn">Entrer dans <?= $brandText ?></button>
            </form>
            <div class="small">Profil synchronisé · accès autorisé</div>
        </div>
    </div>
</section>

<script>
(function () {
    var profile = document.getElementById('profile');
    var form = document.getElementById('enter-form');
    var btn = document.getElementById('enter-btn');
    var lock = document.getElementById('lock');
    var submitting = false;

    function formatDate() {
        var now = new Date();
        document.getElementById('time').textContent =
            now.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });
        document.getElementById('date').textContent =
            now.toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long' });
    }
    formatDate();
    setInterval(formatDate, 1000);

    function showProfile() {
        profile.classList.add('show');
        profile.setAttribute('aria-hidden', 'false');
        if (btn) btn.focus({ preventScroll: true });
    }
    function hideProfile() {
        profile.classList.remove('show');
        profile.setAttribute('aria-hidden', 'true');
    }
    function enterAthena() {
        if (submitting || !form) return;
        submitting = true;
        if (btn) {
            btn.textContent = 'Ouverture…';
            btn.disabled = true;
        }
        form.submit();
    }

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            if (profile.classList.contains('show')) enterAthena();
            else showProfile();
        }
        if (e.key === 'Escape') hideProfile();
    });

    lock.addEventListener('click', function (e) {
        if (!profile.classList.contains('show') && !e.target.closest('#profile')) {
            showProfile();
        }
    });

    if (form) {
        form.addEventListener('submit', function () {
            if (btn && !submitting) {
                btn.textContent = 'Ouverture…';
                btn.disabled = true;
            }
            submitting = true;
        });
    }
})();
</script>
</body>
</html>
