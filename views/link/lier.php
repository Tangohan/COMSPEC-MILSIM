<?php
declare(strict_types=1);

/*
 * Page /lier : le joueur voit la demande du jeu, puis autorise lui-même.
 * Le code (8 caractères) s'affiche groupé XXXX-XXXX. Rien n'est envoyé tout seul.
 */

$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$guest = (bool) ($guest ?? false);
$code = (string) ($code ?? '');
$linked = (bool) ($linked ?? false);
$error = $error ?? null;
$gameRequest = is_array($gameRequest ?? null) ? $gameRequest : null;
$watch = (int) ($watch ?? 0);
$steamMoved = (int) ($steamMoved ?? 0);
$account = (string) ($account ?? '');
$community = (string) ($community ?? '');
$base = url('');
$brand = function_exists('email_brand_name') ? email_brand_name() : 'Athena';
$css = function_exists('asset_url') ? asset_url('assets/css/link-lier.css') : ($base . '/assets/css/link-lier.css');
$pending = $gameRequest !== null && ($gameRequest['state'] ?? '') === 'pending';
$source = (string) ($gameRequest['source'] ?? '');
$sourceLabel = match ($source) {
    'eden' => 'Éditeur Eden · COMSPEC SSE',
    'overwatch' => 'Téléphone ATAK · pack Overwatch',
    'phone' => 'Téléphone ATAK · app Athena',
    default => '',
};
$compactCode = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $code) ?? '');
$displayCode = strlen($compactCode) === 8
    ? substr($compactCode, 0, 4) . '-' . substr($compactCode, 4)
    : $code;
$step = $linked ? 3 : 2;
$steps = [
    1 => ['Code dans le jeu', 'App Athena du téléphone ou Eden'],
    2 => ['Autoriser ici', 'Connecté à votre compte'],
    3 => ['Le jeu se connecte', 'Profil et communauté synchronisés'],
];
$who = $account !== '' ? $account : 'votre compte';
if ($linked) {
    $title = 'Votre jeu est lié';
    $lead = 'Retournez dans Arma. Le jeu reprend votre profil, votre communauté et vos réglages. Aux parties suivantes, la connexion se fait avec votre compte Steam.';
} elseif ($guest && $code !== '') {
    $title = 'Connectez-vous pour autoriser';
    $lead = 'Le jeu sera rattaché au compte avec lequel vous vous connectez. Le code est conservé : après la connexion, vous vérifiez la demande, puis vous l’autorisez.';
} elseif ($guest) {
    $title = 'Connectez-vous pour relier le jeu';
    $lead = 'Ouvrez cette page depuis Arma pour arriver avec le code déjà rempli. Vous pouvez aussi vous connecter, puis le saisir.';
} elseif ($pending) {
    $title = 'Autoriser ce jeu';
    $lead = 'Vérifiez que ce code est bien celui affiché dans votre jeu, puis autorisez.';
} elseif ($gameRequest !== null) {
    $title = 'Ce code n’est plus valable';
    $lead = 'Demandez un nouveau code dans Arma, puis saisissez-le ici. Rien n’est autorisé tant que vous n’avez pas confirmé la demande.';
} else {
    $title = 'Saisissez le code du jeu';
    $lead = 'Huit caractères, par exemple ABCD-2345. Vous verrez la demande avant de l’autoriser.';
}
?>
<!DOCTYPE html>
<html lang="<?= $h(function_exists('html_lang') ? html_lang() : 'fr') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $h($title) ?> — <?= $h($brand) ?></title>
    <meta name="theme-color" content="#070908">
    <meta name="robots" content="noindex,nofollow">
    <link rel="icon" href="<?= $h($base) ?>/assets/icons/athena-192.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= $h($css) ?>">
</head>
<body class="ll">
<a class="ll-skip" href="#contenu">Aller au contenu</a>
<header class="ll-top">
    <a class="ll-brand" href="<?= $h($base !== '' ? $base : '/') ?>"><img src="<?= $h($base) ?>/assets/icons/athena-192.png" alt=""> <?= $h($brand) ?></a>
    <p class="ll-who">
        <?php if ($guest): ?>
            <a href="<?= $h((string) ($loginUrl ?? '')) ?>">Se connecter</a>
        <?php else: ?>
            <span class="ll-prefix">Connecté :</span> <strong><?= $h($who) ?></strong><?php if ($community !== ''): ?><br><span><?= $h($community) ?></span><?php endif; ?>
        <?php endif; ?>
    </p>
</header>

<main class="ll-main" id="contenu">
    <ol class="ll-steps" aria-label="Étapes">
        <?php foreach ($steps as $n => [$label, $help]): ?>
            <li class="<?= $n < $step ? 'done' : ($n === $step ? 'now' : '') ?>"<?= $n === $step ? ' aria-current="step"' : '' ?>>
                <span class="n"><?= $n < $step ? '✓' : (string) $n ?></span>
                <b><?= $h($label) ?></b>
                <small><?= $h($help) ?></small>
            </li>
        <?php endforeach; ?>
    </ol>
    <p class="ll-step-caption"><?= $h($steps[$step][1]) ?></p>

    <article class="ll-panel">
        <p class="ll-kicker">Liaison du jeu</p>
        <?php if ($linked): ?><div class="ll-mark" aria-hidden="true">✓</div><?php endif; ?>
        <h1><?= $h($title) ?></h1>
        <p class="ll-lead"><?= $h($lead) ?></p>

        <?php if ($error): ?>
            <p class="ll-alert bad" role="alert"><?= $h((string) $error) ?></p>
        <?php elseif (!$guest && !$linked && $gameRequest !== null && !$pending): ?>
            <p class="ll-alert warn" role="status">Ce code a déjà été utilisé ou a expiré. Dans Arma, demandez un nouveau code.</p>
        <?php elseif (!$guest && !$linked && $code !== '' && $gameRequest === null): ?>
            <p class="ll-alert warn" role="status">Aucune demande en attente pour ce code. Vérifiez qu’il correspond à celui affiché dans Arma.</p>
        <?php endif; ?>

        <?php if ($guest): ?>
            <?php if ($displayCode !== ''): ?>
                <p class="ll-code-label" id="code-label">Code du jeu</p>
                <p class="ll-code" translate="no"><?php
                    if (strlen($compactCode) === 8) {
                        echo $h(substr($compactCode, 0, 4)) . '<span class="sep">-</span>' . $h(substr($compactCode, 4));
                    } else {
                        echo $h($displayCode);
                    }
                ?></p>
                <p class="ll-hint">Ce code reste valable quelques minutes. S’il expire, demandez-en un nouveau dans Arma.</p>
            <?php endif; ?>
            <div class="ll-actions">
                <a class="ll-btn main" href="<?= $h((string) ($loginUrl ?? '')) ?>">Se connecter</a>
                <a class="ll-btn" href="<?= $h((string) ($registerUrl ?? '')) ?>">Créer un compte</a>
            </div>

        <?php elseif ($linked): ?>
            <?php if ($watch > 0): ?>
                <div class="ll-track" data-link-watch="<?= $h(url('lier/etat')) ?>" role="status" aria-live="polite">
                    <span class="ll-dot" data-link-dot></span>
                    <span data-link-text>En attente du jeu : gardez Arma ouvert, il reprend la session dans quelques secondes.</span>
                    <span hidden data-msg="consumed">Jeu connecté : votre téléphone a repris la session. Vous pouvez fermer cette page.</span>
                    <span hidden data-msg="expired">Le jeu n’a pas repris la session à temps. Dans Arma, demandez un nouveau code dans l’app Athena.</span>
                    <span hidden data-msg="unknown">Suivi indisponible. Vérifiez dans Arma, app Athena du téléphone.</span>
                    <span hidden data-msg="late">Toujours en attente du jeu. Vérifiez qu’Arma est ouvert et que COMSPEC Link est autorisé (BattlEye désactivé).</span>
                </div>
            <?php endif; ?>
            <?php if ($steamMoved > 0): ?>
                <p class="ll-alert warn">Ce compte Steam était aussi rattaché à un autre compte de votre communauté. Il ne mène plus qu’à celui-ci, pour que la connexion automatique ouvre toujours le bon compte.</p>
            <?php endif; ?>
            <div class="ll-actions">
                <a class="ll-btn" href="<?= $h(url('account/security/devices')) ?>">Voir mes appareils liés</a>
                <a class="ll-btn" href="<?= $h(url('lier')) ?>">Lier un autre jeu</a>
            </div>

        <?php elseif ($pending): ?>
            <?php
            $steam = (string) ($gameRequest['steam'] ?? '');
            $steamTail = $steam !== '' ? '…' . substr($steam, -5) : 'non transmis';
            $leftSec = (int) ($gameRequest['left_sec'] ?? ((int) ($gameRequest['left_min'] ?? 0) * 60));
            $leftLabel = intdiv(max(0, $leftSec), 60) . ':' . str_pad((string) (max(0, $leftSec) % 60), 2, '0', STR_PAD_LEFT);
            $askedMin = (int) ($gameRequest['asked_min'] ?? 0);
            ?>
            <p class="ll-chip"><i aria-hidden="true"></i><?= $h($sourceLabel !== '' ? $sourceLabel : 'Demande reçue du jeu') ?></p>
            <p class="ll-code-label" id="code-label">Code du jeu</p>
            <p class="ll-code" translate="no"><?php
                if (strlen($compactCode) === 8) {
                    echo $h(substr($compactCode, 0, 4)) . '<span class="sep">-</span>' . $h(substr($compactCode, 4));
                } else {
                    echo $h($displayCode);
                }
            ?></p>
            <p class="ll-timer">Valable encore <span data-left="<?= $leftSec ?>"><?= $h($leftLabel) ?></span></p>
            <dl class="ll-facts">
                <dt>Compte Steam</dt>
                <dd class="ll-mono"><?= $h($steamTail) ?><?= !empty($gameRequest['steam_here']) ? ' · déjà le vôtre' : '' ?></dd>
                <?php if ($source === 'overwatch' && (string) ($gameRequest['mod_version'] ?? '') !== ''): ?>
                    <dt>Pack Overwatch</dt>
                    <dd><?= $h((string) $gameRequest['mod_version']) ?></dd>
                <?php endif; ?>
                <dt>Demandé</dt>
                <dd><?= $askedMin < 1 ? 'à l’instant' : 'il y a ' . $askedMin . ' min' ?></dd>
            </dl>
            <?php if (!empty($gameRequest['steam_elsewhere'])): ?>
                <p class="ll-alert warn">Ce compte Steam est déjà rattaché à un autre compte de votre communauté. En autorisant, il passera sur le compte avec lequel vous êtes connecté ici.</p>
            <?php endif; ?>
            <p class="ll-target">Le jeu sera rattaché à <strong><?= $h($who) ?></strong><?php if ($community !== ''): ?>, communauté <strong><?= $h($community) ?></strong><?php endif; ?>. Votre compte Steam est relié en même temps.</p>
            <form class="ll-form" method="post" action="<?= $h(url('lier')) ?>">
                <?= \App\Core\Csrf::field() ?>
                <input type="hidden" name="code" value="<?= $h($displayCode) ?>">
                <button type="submit" class="ll-btn main">Autoriser ce jeu</button>
            </form>
            <details class="ll-swap" data-swap>
                <summary>Saisir un autre code</summary>
                <form class="ll-form" method="get" action="<?= $h(url('lier')) ?>">
                    <label class="ll-label" for="code">Code affiché dans Arma</label>
                    <input class="ll-field" type="text" name="code" id="code" required maxlength="11" inputmode="text"
                           autocomplete="one-time-code" autocapitalize="characters" spellcheck="false"
                           enterkeyhint="done" placeholder="XXXX-XXXX" aria-describedby="code-hint">
                    <p class="ll-hint" id="code-hint">Huit caractères. Les espaces, les tirets et les minuscules sont acceptés.</p>
                    <div class="ll-actions">
                        <button type="submit" class="ll-btn">Vérifier ce code</button>
                    </div>
                </form>
            </details>
            <p class="ll-quiet"><a href="<?= $h(url('account/security/devices')) ?>">Mes appareils liés</a></p>

        <?php else: ?>
            <form class="ll-form" method="get" action="<?= $h(url('lier')) ?>">
                <label class="ll-label" for="code">Code affiché dans Arma</label>
                <input class="ll-field" type="text" name="code" id="code" required maxlength="11" inputmode="text"
                       autocomplete="one-time-code" autocapitalize="characters" spellcheck="false"
                       enterkeyhint="done" placeholder="XXXX-XXXX" value="<?= $h($displayCode) ?>"
                       aria-describedby="code-hint" autofocus>
                <p class="ll-hint" id="code-hint">Huit caractères. Les espaces, les tirets et les minuscules sont acceptés.</p>
                <p class="ll-target">Le jeu sera rattaché à <strong><?= $h($who) ?></strong><?php if ($community !== ''): ?>, communauté <strong><?= $h($community) ?></strong><?php endif; ?>. Votre compte Steam est relié en même temps.</p>
                <div class="ll-actions">
                    <button type="submit" class="ll-btn main">Vérifier ce code</button>
                </div>
            </form>
            <p class="ll-quiet"><a href="<?= $h(url('account/security/devices')) ?>">Mes appareils liés</a></p>
        <?php endif; ?>
    </article>

    <?php if (!$linked): ?>
        <section class="ll-side" aria-labelledby="ou-titre">
            <h2 id="ou-titre">Où trouver le code ?</h2>
            <div class="ll-where">
                <article>
                    <b>Téléphone ATAK</b>
                    <p>Sortez le téléphone (<span class="ll-kbd">Ctrl</span> + <span class="ll-kbd">U</span>), app <strong>Athena</strong>, onglet CODE. Le pack Overwatch utilise le même écran. <strong>Ouvrir la page</strong> arrive ici avec le code déjà rempli. Le QR code ouvre cette page sur votre vrai téléphone.</p>
                </article>
                <article>
                    <b>Éditeur Eden (COMSPEC SSE)</b>
                    <p>Menu <strong>COMSPEC SSE</strong>, <strong>Connexion Athena</strong> : un code s’affiche, avec <strong>Ouvrir la page</strong> et <strong>Nouveau code</strong>.</p>
                </article>
            </div>
            <div class="ll-safe">
                <b>N’autorisez que votre propre jeu</b>
                Un code reçu de quelqu’un d’autre lui donnerait accès à votre compte. Vous n’avez rien demandé ? Ne validez rien : sans votre accord, le jeu reste déconnecté.
            </div>
        </section>
        <p class="ll-foot">Le code expire vite. S’il est refusé, demandez un nouveau code dans Arma et recommencez.</p>
    <?php endif; ?>
</main>

<script>
(function () {
    var input = document.getElementById('code');
    if (input) {
        input.addEventListener('input', function () {
            var raw = input.value.toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, 8);
            input.value = raw.length > 4 ? raw.slice(0, 4) + '-' + raw.slice(4) : raw;
        });
    }
    var swap = document.querySelector('[data-swap]');
    if (swap) {
        swap.addEventListener('toggle', function () {
            if (!swap.open) { return; }
            var field = swap.querySelector('input');
            if (field) { field.focus(); }
        });
    }
    var clock = document.querySelector('[data-left]');
    if (clock) {
        var timer = clock.parentNode;
        var left = parseInt(clock.getAttribute('data-left'), 10) || 0;
        var tick = function () {
            if (left <= 0) { clock.textContent = '0:00'; timer.classList.add('low'); return; }
            var m = Math.floor(left / 60), s = left % 60;
            clock.textContent = m + ':' + (s < 10 ? '0' : '') + s;
            if (left < 60) { timer.classList.add('low'); }
            left--;
            setTimeout(tick, 1000);
        };
        tick();
    }
    var box = document.querySelector('[data-link-watch]');
    if (!box) { return; }
    var dot = box.querySelector('[data-link-dot]');
    var text = box.querySelector('[data-link-text]');
    var tries = 0;
    function msg(k) {
        var el = box.querySelector('[data-msg="' + k + '"]');
        return el ? el.textContent : '';
    }
    function show(color, k) {
        dot.style.background = color;
        dot.style.animation = 'none';
        text.textContent = msg(k);
    }
    function poll() {
        tries++;
        fetch(box.getAttribute('data-link-watch'), { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (d.state === 'consumed') { show('#34d399', 'consumed'); return; }
                if (d.state === 'expired') { show('#f0a0a0', 'expired'); return; }
                if (d.state === 'unknown') { show('#9fb3a8', 'unknown'); return; }
                if (tries < 150) { setTimeout(poll, 2000); } else { show('#9fb3a8', 'late'); }
            })
            .catch(function () { if (tries < 150) { setTimeout(poll, 4000); } });
    }
    setTimeout(poll, 1500);
})();
</script>
</body>
</html>
