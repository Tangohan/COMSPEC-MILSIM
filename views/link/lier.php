<?php
declare(strict_types=1);

/*
 * Page dédiée /lier : le joueur autorise, depuis son navigateur (PC ou vrai téléphone, QR code du jeu),
 * le code affiché dans Arma par l'app Athena du téléphone ATAK ou par la fenêtre Connexion Athena d'Eden (COMSPEC SSE).
 * Hors du hub « Mon compte » : une seule tâche, lisible sur un téléphone.
 */

$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$guest = (bool) ($guest ?? false);
$code = (string) ($code ?? '');
$linked = (bool) ($linked ?? false);
$success = $success ?? null;
$error = $error ?? null;
$gameRequest = is_array($gameRequest ?? null) ? $gameRequest : null;
$watch = (int) ($watch ?? 0);
$steamMoved = (int) ($steamMoved ?? 0);
$account = (string) ($account ?? '');
$community = (string) ($community ?? '');
$base = url('');
$brand = function_exists('email_brand_name') ? email_brand_name() : 'Athena';
$pending = $gameRequest !== null && ($gameRequest['state'] ?? '') === 'pending';
$source = (string) ($gameRequest['source'] ?? '');
$sourceLabel = match ($source) {
    'eden' => 'Éditeur Eden · COMSPEC SSE',
    'overwatch' => 'Téléphone ATAK · pack Overwatch',
    'phone' => 'Téléphone ATAK · app Athena',
    default => '',
};
// Étape en cours : 1 code dans le jeu, 2 autorisation ici, 3 le jeu reprend la session.
$step = $linked ? 3 : 2;
$steps = [
    1 => ['Code dans le jeu', 'App Athena du téléphone ou Eden'],
    2 => ['Autoriser ici', 'Connecté à votre compte'],
    3 => ['Le jeu se connecte', 'Profil et communauté synchronisés'],
];
?>
<!DOCTYPE html>
<html lang="<?= $h(function_exists('html_lang') ? html_lang() : 'fr') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lier le jeu — <?= $h($brand) ?></title>
    <meta name="theme-color" content="#0b0f0d">
    <meta name="robots" content="noindex,nofollow">
    <link rel="icon" href="<?= $h($base) ?>/assets/icons/athena-192.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #f4f6f5; --panel: #ffffff; --ink: #0f1713; --dim: #5b6b63; --line: #dfe5e2;
            --accent: #047857; --accent-ink: #ffffff; --accent-soft: #e7f5ef; --warn: #b45309; --warn-soft: #fff7e8;
            --bad: #b91c1c; --bad-soft: #fdecec; --code-bg: #0b0f0d; --code-ink: #8fe39b; --shadow: 0 1px 2px rgba(15, 23, 19, .06), 0 8px 28px rgba(15, 23, 19, .06);
        }
        @media (prefers-color-scheme: dark) {
            :root {
                --bg: #0b0f0d; --panel: #121a16; --ink: #e8efeb; --dim: #93a39b; --line: #23302a;
                --accent: #34c27a; --accent-ink: #06140d; --accent-soft: #13281e; --warn: #f2c66b; --warn-soft: #2a2213;
                --bad: #f08a8a; --bad-soft: #2d1616; --code-bg: #050806; --shadow: 0 1px 2px rgba(0, 0, 0, .4);
            }
        }
        * { box-sizing: border-box; }
        body { margin: 0; background: var(--bg); color: var(--ink); font: 15px/1.55 Inter, system-ui, -apple-system, "Segoe UI", sans-serif; }
        a { color: var(--accent); }
        .top { display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding: .9rem clamp(1rem, 4vw, 2.5rem); border-bottom: 1px solid var(--line); background: var(--panel); }
        .brand { display: flex; align-items: center; gap: .6rem; color: var(--ink); text-decoration: none; font-weight: 800; letter-spacing: .02em; }
        .brand img { width: 30px; height: 30px; padding: 3px; border-radius: 8px; background: #0b0f0d; }
        .who { font-size: .84rem; color: var(--dim); text-align: right; }
        .who strong { color: var(--ink); }
        .wrap { max-width: 1040px; margin: 0 auto; padding: clamp(1.25rem, 4vw, 2.75rem) clamp(1rem, 4vw, 2.5rem) 3rem; }
        .kicker { margin: 0 0 .5rem; font-size: .72rem; font-weight: 800; letter-spacing: .16em; text-transform: uppercase; color: var(--accent); }
        h1 { margin: 0; font-size: clamp(1.7rem, 4.4vw, 2.5rem); line-height: 1.12; letter-spacing: -.02em; }
        .lead { margin: .7rem 0 0; max-width: 40rem; color: var(--dim); font-size: 1.02rem; }
        .stepper { display: grid; grid-template-columns: repeat(3, 1fr); gap: .6rem; margin: 1.6rem 0 1.8rem; padding: 0; list-style: none; }
        .stepper li { display: flex; gap: .65rem; align-items: flex-start; padding: .75rem .85rem; border: 1px solid var(--line); border-radius: 12px; background: var(--panel); }
        .stepper .n { flex: none; display: grid; place-items: center; width: 1.7rem; height: 1.7rem; border-radius: 50%; border: 1.5px solid var(--line); font-weight: 800; font-size: .8rem; color: var(--dim); }
        .stepper b { display: block; font-size: .9rem; }
        .stepper small { display: block; color: var(--dim); font-size: .76rem; line-height: 1.35; }
        .stepper li.done .n { background: var(--accent); border-color: var(--accent); color: var(--accent-ink); }
        .stepper li.now { border-color: var(--accent); box-shadow: 0 0 0 3px var(--accent-soft); }
        .stepper li.now .n { border-color: var(--accent); color: var(--accent); }
        .grid { display: grid; grid-template-columns: minmax(0, 1.55fr) minmax(0, 1fr); gap: 1.25rem; align-items: start; }
        .card { background: var(--panel); border: 1px solid var(--line); border-radius: 16px; padding: clamp(1.1rem, 3vw, 1.6rem); box-shadow: var(--shadow); }
        .card h2 { margin: 0 0 .35rem; font-size: 1.2rem; }
        .card p.sub { margin: 0 0 1.1rem; color: var(--dim); }
        .alert { margin: 0 0 1rem; padding: .75rem .9rem; border-radius: 10px; font-weight: 600; font-size: .92rem; }
        .alert.bad { background: var(--bad-soft); color: var(--bad); }
        .alert.ok { background: var(--accent-soft); color: var(--accent); }
        .alert.warn { background: var(--warn-soft); color: var(--warn); }
        .req { margin: 0 0 1.2rem; padding: .9rem 1rem; border: 1px solid var(--accent); border-radius: 12px; background: var(--accent-soft); }
        .req-head { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: .5rem; margin-bottom: .55rem; }
        .chip { display: inline-flex; align-items: center; gap: .4rem; padding: .22rem .6rem; border-radius: 999px; background: var(--panel); border: 1px solid var(--line); font-size: .78rem; font-weight: 700; }
        .chip i { width: .5rem; height: .5rem; border-radius: 50%; background: var(--accent); }
        .timer { font: 700 .82rem "JetBrains Mono", ui-monospace, monospace; color: var(--accent); }
        .timer.low { color: var(--warn); }
        .facts { display: grid; grid-template-columns: auto 1fr; gap: .25rem .9rem; margin: 0; font-size: .9rem; }
        .facts dt { color: var(--dim); }
        .facts dd { margin: 0; font-weight: 600; }
        .mono { font-family: "JetBrains Mono", ui-monospace, monospace; }
        label.lbl { display: block; margin-bottom: .45rem; font-weight: 700; font-size: .9rem; }
        .code-in { width: 100%; max-width: 22rem; padding: .7rem .9rem; border: 2px solid var(--line); border-radius: 12px; background: var(--code-bg); color: var(--code-ink);
            font: 700 clamp(1.6rem, 6vw, 2.1rem)/1.1 "JetBrains Mono", ui-monospace, monospace; letter-spacing: .18em; text-transform: uppercase; text-align: center; }
        .code-in:focus { outline: none; border-color: var(--accent); box-shadow: 0 0 0 4px var(--accent-soft); }
        .hint { margin: .5rem 0 0; color: var(--dim); font-size: .84rem; }
        .target { display: flex; gap: .6rem; align-items: center; margin: 1.1rem 0; padding: .7rem .85rem; border: 1px dashed var(--line); border-radius: 10px; font-size: .88rem; }
        .target .av { flex: none; display: grid; place-items: center; width: 2rem; height: 2rem; border-radius: 50%; background: var(--accent-soft); color: var(--accent); font-weight: 800; }
        .actions { display: flex; flex-wrap: wrap; gap: .7rem; align-items: center; }
        .btn { display: inline-flex; align-items: center; justify-content: center; gap: .45rem; min-height: 2.9rem; padding: .7rem 1.25rem; border-radius: 10px; border: 1px solid var(--line);
            background: var(--panel); color: var(--ink); font: 700 .95rem Inter, system-ui, sans-serif; text-decoration: none; cursor: pointer; }
        .btn.main { background: var(--accent); border-color: var(--accent); color: var(--accent-ink); }
        .btn.main:hover { filter: brightness(1.06); }
        .track { display: flex; gap: .7rem; align-items: center; margin-top: 1rem; padding: .85rem 1rem; border-radius: 12px; background: var(--accent-soft); font-weight: 700; }
        .dot { flex: none; width: .7rem; height: .7rem; border-radius: 50%; background: #d97706; box-shadow: 0 0 0 4px rgba(217, 119, 6, .18); animation: pulse 1.4s ease-in-out infinite; }
        @keyframes pulse { 50% { opacity: .45; } }
        @media (prefers-reduced-motion: reduce) { .dot { animation: none; } }
        .side h3 { margin: 0 0 .7rem; font-size: .78rem; letter-spacing: .14em; text-transform: uppercase; color: var(--dim); }
        .where { display: grid; gap: .7rem; margin-bottom: 1.1rem; }
        .where .card { padding: .95rem 1rem; box-shadow: none; }
        .where b { display: block; margin-bottom: .2rem; }
        .where p { margin: 0; color: var(--dim); font-size: .86rem; }
        .kbd { display: inline-block; padding: 0 .35rem; border: 1px solid var(--line); border-radius: 5px; font: 700 .74rem "JetBrains Mono", ui-monospace, monospace; color: var(--ink); background: var(--bg); }
        .safe { padding: .9rem 1rem; border-radius: 12px; background: var(--warn-soft); color: var(--warn); font-size: .86rem; }
        .safe b { display: block; margin-bottom: .2rem; }
        .foot { margin-top: 2rem; color: var(--dim); font-size: .84rem; }
        @media (max-width: 820px) {
            .grid { grid-template-columns: 1fr; }
            .stepper { grid-template-columns: 1fr; }
            .stepper li:not(.now) small { display: none; }
            .who span { display: none; }
        }
    </style>
</head>
<body>
<header class="top">
    <a class="brand" href="<?= $h($base !== '' ? $base : '/') ?>"><img src="<?= $h($base) ?>/assets/icons/athena-192.png" alt=""> <?= $h($brand) ?></a>
    <div class="who">
        <?php if ($guest): ?>
            <a href="<?= $h((string) $loginUrl) ?>">Se connecter</a>
        <?php else: ?>
            <span>Connecté :</span> <strong><?= $h($account !== '' ? $account : 'votre compte') ?></strong><?php if ($community !== ''): ?><br><span><?= $h($community) ?></span><?php endif; ?>
        <?php endif; ?>
    </div>
</header>

<main class="wrap" id="contenu">
    <p class="kicker">Arma 3 · Liaison du jeu</p>
    <h1><?= $linked ? 'Votre jeu est lié' : 'Relier votre jeu à Athena' ?></h1>
    <p class="lead"><?= $linked
        ? 'Le téléphone récupère votre profil, votre communauté et vos réglages. Aux parties suivantes, la connexion se fait seule avec votre compte Steam.'
        : 'Saisissez le code affiché dans Arma. Aucune clé ni identifiant de communauté : votre compte Athena suffit.' ?></p>

    <ol class="stepper" aria-label="Étapes">
        <?php foreach ($steps as $n => [$label, $help]): ?>
            <li class="<?= $n < $step ? 'done' : ($n === $step ? 'now' : '') ?>"<?= $n === $step ? ' aria-current="step"' : '' ?>>
                <span class="n"><?= $n < $step ? '✓' : $n ?></span>
                <span><b><?= $h($label) ?></b><small><?= $h($help) ?></small></span>
            </li>
        <?php endforeach; ?>
    </ol>

    <div class="grid">
        <section class="card">
            <?php if ($error): ?><p class="alert bad" role="alert"><?= $h((string) $error) ?></p><?php endif; ?>

            <?php if ($guest): ?>
                <h2>Connectez-vous pour autoriser ce code</h2>
                <p class="sub">Le jeu sera rattaché au compte avec lequel vous vous connectez. Le code est gardé : vous revenez ici juste après.</p>
                <?php if ($code !== ''): ?>
                    <label class="lbl">Code reçu du jeu</label>
                    <div class="code-in" aria-label="Code du jeu"><?= $h($code) ?></div>
                    <p class="hint">Ce code reste valable quelques minutes. S'il expire, demandez-en un nouveau dans Arma.</p>
                <?php endif; ?>
                <div class="actions" style="margin-top:1.2rem">
                    <a class="btn main" href="<?= $h((string) $loginUrl) ?>">Se connecter</a>
                    <a class="btn" href="<?= $h((string) $registerUrl) ?>">Créer un compte</a>
                </div>

            <?php elseif ($linked): ?>
                <h2>C'est fait</h2>
                <p class="sub">Retournez dans Arma : l'app Athena se connecte et synchronise votre profil toute seule.</p>
                <?php if ($watch > 0): ?>
                    <div class="track" data-link-watch="<?= $h(url('lier/etat')) ?>" role="status" aria-live="polite">
                        <span class="dot" data-link-dot></span>
                        <span data-link-text>En attente du jeu : gardez Arma ouvert, il reprend la session dans quelques secondes.</span>
                        <span hidden data-msg="consumed">Jeu connecté : votre téléphone a repris la session. Vous pouvez fermer cette page.</span>
                        <span hidden data-msg="expired">Le jeu n’a pas repris la session à temps. Dans Arma, demandez un nouveau code dans l’app Athena.</span>
                        <span hidden data-msg="unknown">Suivi indisponible. Vérifiez dans Arma, app Athena du téléphone.</span>
                        <span hidden data-msg="late">Toujours en attente du jeu. Vérifiez qu’Arma est ouvert et que COMSPEC Link est autorisé (BattlEye désactivé).</span>
                    </div>
                <?php endif; ?>
                <?php if ($steamMoved > 0): ?>
                    <p class="alert warn" style="margin-top:1rem">Ce compte Steam était aussi rattaché à un autre compte de votre communauté. Il ne mène plus qu'à celui-ci, pour que la connexion automatique ouvre toujours le bon compte.</p>
                <?php endif; ?>
                <div class="actions" style="margin-top:1.2rem">
                    <a class="btn" href="<?= $h(url('account/security/devices')) ?>">Voir mes appareils liés</a>
                    <a class="btn" href="<?= $h(url('lier')) ?>">Lier un autre jeu</a>
                </div>

            <?php else: ?>
                <?php if ($pending): ?>
                    <?php $steamTail = $gameRequest['steam'] !== '' ? '…' . substr((string) $gameRequest['steam'], -5) : 'non transmis'; ?>
                    <div class="req">
                        <div class="req-head">
                            <span class="chip"><i></i><?= $h($sourceLabel !== '' ? $sourceLabel : 'Demande reçue du jeu') ?></span>
                            <span class="timer">Valable encore <span data-left="<?= (int) ($gameRequest['left_sec'] ?? ((int) $gameRequest['left_min'] * 60)) ?>"><?= (int) $gameRequest['left_min'] ?> min</span></span>
                        </div>
                        <dl class="facts">
                            <dt>Compte Steam</dt>
                            <dd class="mono"><?= $h($steamTail) ?><?= $gameRequest['steam_here'] ? ' · déjà le vôtre' : '' ?></dd>
                            <?php if ($source === 'overwatch' && $gameRequest['mod_version'] !== ''): ?>
                                <dt>Pack Overwatch</dt><dd><?= $h((string) $gameRequest['mod_version']) ?></dd>
                            <?php endif; ?>
                            <dt>Demandé</dt>
                            <dd><?= (int) $gameRequest['asked_min'] < 1 ? 'à l\'instant' : 'il y a ' . (int) $gameRequest['asked_min'] . ' min' ?></dd>
                        </dl>
                        <?php if ($gameRequest['steam_elsewhere']): ?>
                            <p class="alert warn" style="margin:.7rem 0 0">Ce compte Steam est déjà rattaché à un autre compte de votre communauté. En autorisant, il passera sur le compte avec lequel vous êtes connecté ici.</p>
                        <?php endif; ?>
                    </div>
                <?php elseif ($gameRequest !== null): ?>
                    <p class="alert warn">Ce code a déjà été utilisé ou a expiré. Dans Arma, touchez NOUVEAU CODE.</p>
                <?php endif; ?>

                <h2><?= $pending ? 'Vérifiez, puis autorisez' : 'Saisissez le code du jeu' ?></h2>
                <p class="sub"><?= $pending
                    ? 'Le code ci-dessous doit être celui affiché dans votre jeu, à l\'instant.'
                    : '8 caractères, par exemple ABCD-2345. Espaces, tirets et minuscules acceptés.' ?></p>
                <form method="post" action="<?= $h(url('lier')) ?>">
                    <?= \App\Core\Csrf::field() ?>
                    <label class="lbl" for="code">Code affiché dans Arma</label>
                    <input class="code-in" type="text" name="code" id="code" required maxlength="11" inputmode="text"
                           autocomplete="one-time-code" autocapitalize="characters" spellcheck="false"
                           value="<?= $h($code) ?>" placeholder="XXXX-XXXX"<?= $code === '' ? ' autofocus' : '' ?>>
                    <div class="target">
                        <span class="av"><?= $h(mb_strtoupper(mb_substr($account !== '' ? $account : 'A', 0, 1))) ?></span>
                        <span>Le jeu sera rattaché à <strong><?= $h($account !== '' ? $account : 'votre compte') ?></strong><?= $community !== '' ? ', communauté <strong>' . $h($community) . '</strong>' : '' ?>. Votre compte Steam est relié en même temps.</span>
                    </div>
                    <div class="actions">
                        <button type="submit" class="btn main">Autoriser ce jeu</button>
                        <a class="btn" href="<?= $h(url('account/security/devices')) ?>">Mes appareils liés</a>
                    </div>
                </form>
            <?php endif; ?>
        </section>

        <aside class="side">
            <h3>Où trouver le code ?</h3>
            <div class="where">
                <div class="card">
                    <b>Téléphone ATAK</b>
                    <p>Sortez le téléphone (<span class="kbd">Ctrl</span> + <span class="kbd">U</span>), app <strong>Athena</strong>, onglet CODE. <strong>OUVRIR LA PAGE</strong> ouvre cette page avec le code déjà rempli, et le QR code l'ouvre sur votre vrai téléphone.</p>
                </div>
                <div class="card">
                    <b>Éditeur Eden (COMSPEC SSE)</b>
                    <p>Menu <strong>COMSPEC SSE</strong>, <strong>Connexion Athena</strong> : un code s'affiche, avec <strong>Ouvrir la page</strong> et <strong>Nouveau code</strong>.</p>
                </div>
            </div>
            <div class="safe">
                <b>N'autorisez que votre propre jeu</b>
                Un code reçu de quelqu'un d'autre lui donnerait accès à votre compte. Vous n'avez rien demandé ? Ne validez rien : sans votre accord, le jeu reste déconnecté.
            </div>
        </aside>
    </div>

    <p class="foot">Le code expire vite : s'il est refusé, touchez NOUVEAU CODE dans Arma et recommencez.</p>
</main>

<script>
(function () {
    // Saisie libre (espaces, tirets, minuscules) remise au format XXXX-XXXX.
    var input = document.getElementById('code');
    if (input) {
        input.addEventListener('input', function () {
            var raw = input.value.toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, 8);
            input.value = raw.length > 4 ? raw.slice(0, 4) + '-' + raw.slice(4) : raw;
        });
    }
    // Compte à rebours du code en attente.
    var clock = document.querySelector('[data-left]');
    if (clock) {
        var timer = clock.parentNode;
        var left = parseInt(clock.getAttribute('data-left'), 10) || 0;
        var tick = function () {
            if (left <= 0) { clock.textContent = '0:00'; timer.classList.add('low'); return; }
            var m = Math.floor(left / 60), s = left % 60;
            clock.textContent = m + ':' + (s < 10 ? '0' : '') + s;
            if (left < 60) { timer.classList.add('low'); }
            left--; setTimeout(tick, 1000);
        };
        tick();
    }
    // Après l'autorisation : le jeu a-t-il repris sa session ?
    var box = document.querySelector('[data-link-watch]');
    if (!box) { return; }
    var dot = box.querySelector('[data-link-dot]');
    var text = box.querySelector('[data-link-text]');
    var tries = 0;
    function msg(k) { var el = box.querySelector('[data-msg="' + k + '"]'); return el ? el.textContent : ''; }
    function show(color, k) { dot.style.background = color; dot.style.animation = 'none'; text.textContent = msg(k); }
    function poll() {
        tries++;
        fetch(box.getAttribute('data-link-watch'), { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (d.state === 'consumed') { show('#059669', 'consumed'); return; }
                if (d.state === 'expired') { show('#dc2626', 'expired'); return; }
                if (d.state === 'unknown') { show('#6b7280', 'unknown'); return; }
                if (tries < 150) { setTimeout(poll, 2000); } else { show('#6b7280', 'late'); }
            })
            .catch(function () { if (tries < 150) { setTimeout(poll, 4000); } });
    }
    setTimeout(poll, 1500);
})();
</script>
</body>
</html>
