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
    <meta name="theme-color" content="#ffffff">
    <meta name="robots" content="noindex,nofollow">
    <link rel="icon" href="<?= $h($base) ?>/assets/icons/athena-192.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Archivo:wght@400;500;600;700;800;900&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
    <style>
        /* Même langage que le back-office : fond clair, lignes fines, angles droits, Archivo, vert Athena. */
        :root {
            --ink: #0c1116; --muted: #6d7a80; --subtle: #8c979b; --line: #e3e8ea; --line-soft: #f1f4f5;
            --bg: #f6f8f9; --surface: #ffffff; --accent: #0b8a5c; --mint: #12d18e; --accent-dark: #04231a; --tint: #f2fbf7;
            --warn: #c98a12; --warn-bg: #fdf3e2; --warn-bd: #f2ddb4; --danger: #c72e2e; --danger-bg: #fdecec;
            --ease: cubic-bezier(.2, .7, .3, 1);
        }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; background: var(--bg); color: var(--ink); font: 15px/1.55 Archivo, system-ui, -apple-system, sans-serif; -webkit-font-smoothing: antialiased; }
        a { color: inherit; }
        strong { font-weight: 700; }
        .mono { font-family: "JetBrains Mono", ui-monospace, monospace; }

        .top { position: sticky; top: 0; z-index: 5; display: flex; align-items: center; justify-content: space-between; gap: 1rem; height: 56px; padding: 0 clamp(1rem, 3vw, 1.6rem);
            background: rgba(255, 255, 255, .92); backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px); border-bottom: 1px solid var(--line); }
        .brand { display: flex; align-items: center; gap: .6rem; text-decoration: none; font-weight: 900; letter-spacing: .02em; font-size: 15px; }
        .brand img { width: 30px; height: 30px; background: var(--ink); padding: 4px; }
        .who { display: flex; align-items: center; gap: .6rem; }
        .who.guest { height: 34px; padding: 0 14px; display: inline-flex; background: var(--ink); color: #fff; text-decoration: none; font-weight: 800; font-size: 12px; transition: background .18s, color .18s; }
        .who.guest:hover { background: var(--mint); color: var(--accent-dark); }
        .av { display: grid; place-items: center; width: 32px; height: 32px; flex: none; background: var(--ink); color: var(--mint); font-weight: 900; font-size: 13px; }
        .nm { display: flex; flex-direction: column; line-height: 1.2; text-align: right; }
        .nm strong { font-size: 13px; } .nm small { color: var(--muted); font-size: 11.5px; }
        .who .nm { order: -1; }

        .wrap { max-width: 1080px; margin: 0 auto; padding: clamp(1.6rem, 5vw, 3rem) clamp(1rem, 4vw, 2rem) 3.5rem; }
        .kicker { display: flex; align-items: center; gap: .55rem; margin: 0; color: var(--accent); font-size: 10.5px; font-weight: 800; letter-spacing: .22em; text-transform: uppercase; }
        .live { width: 7px; height: 7px; background: var(--mint); border-radius: 50%; box-shadow: 0 0 0 0 rgba(18, 209, 142, .5); animation: ping 2.4s ease-out infinite; }
        @keyframes ping { 70% { box-shadow: 0 0 0 7px rgba(18, 209, 142, 0); } 100% { box-shadow: 0 0 0 0 rgba(18, 209, 142, 0); } }
        h1 { margin: .55rem 0 0; font-size: clamp(1.8rem, 4.4vw, 2.6rem); font-weight: 900; letter-spacing: -.025em; line-height: 1.1; }
        .lead { margin: .6rem 0 0; max-width: 40rem; color: var(--muted); font-size: 15px; }

        /* Étapes : une barre fine qui se remplit jusqu'à l'étape en cours. */
        .steps { position: relative; display: grid; grid-template-columns: repeat(3, 1fr); margin: 2rem 0 1.6rem; padding: 0; list-style: none; background: var(--surface); border: 1px solid var(--line); }
        .steps::before, .steps::after { content: ""; position: absolute; left: 0; bottom: -1px; height: 2px; }
        .steps::before { right: 0; background: var(--line); }
        .steps::after { width: 0; background: var(--accent); animation: fill 1.1s .35s var(--ease) forwards; }
        .steps[data-step="1"]::after { --to: 16.66%; } .steps[data-step="2"]::after { --to: 50%; } .steps[data-step="3"]::after { --to: 100%; }
        @keyframes fill { to { width: var(--to, 0); } }
        .steps li { display: grid; grid-template-columns: auto 1fr; column-gap: .75rem; align-items: center; padding: .95rem 1.1rem; }
        .steps li + li { border-left: 1px solid var(--line-soft); }
        .steps .n { grid-row: span 2; display: grid; place-items: center; width: 28px; height: 28px; border: 1px solid var(--line); color: var(--subtle); font-size: 12px; font-weight: 800; transition: all .3s var(--ease); }
        .steps b { font-size: 13px; font-weight: 800; color: var(--muted); }
        .steps small { color: var(--subtle); font-size: 12px; }
        .steps li.done .n { background: var(--ink); border-color: var(--ink); color: var(--mint); }
        .steps li.done b { color: var(--ink); }
        .steps li.now { background: var(--tint); }
        .steps li.now .n { background: var(--accent); border-color: var(--accent); color: #fff; }
        .steps li.now b { color: var(--accent); }

        .grid2 { display: grid; grid-template-columns: minmax(0, 1.55fr) minmax(0, 1fr); gap: 1.1rem; align-items: start; }
        .card { position: relative; padding: clamp(1.2rem, 3vw, 1.8rem); background: var(--surface); border: 1px solid var(--line); }
        .card::before { content: ""; position: absolute; left: -1px; right: -1px; top: -1px; height: 3px; background: var(--ink); transform-origin: left; transform: scaleX(0); animation: bar .9s .45s var(--ease) forwards; }
        @keyframes bar { to { transform: scaleX(1); } }
        h2 { margin: 0; font-size: 20px; font-weight: 900; letter-spacing: -.015em; }
        .sub { margin: .35rem 0 1.3rem; color: var(--muted); font-size: 14px; }
        .alert { margin: 0 0 1.1rem; padding: .75rem .9rem; font-size: 13.5px; border: 1px solid; }
        .alert.bad { background: var(--danger-bg); border-color: #f4c6c6; color: var(--danger); }
        .alert.warn { background: var(--warn-bg); border-color: var(--warn-bd); color: #7a5208; }

        /* Demande reçue du jeu, avec le compte à rebours. */
        .req { display: flex; align-items: center; gap: 1.1rem; margin-bottom: 1.4rem; padding: .95rem 1.1rem; background: var(--tint); border: 1px solid #cfeedd; }
        .ring { position: relative; flex: none; width: 64px; height: 64px; }
        .ring svg { width: 64px; height: 64px; transform: rotate(-90deg); }
        .ring circle { fill: none; stroke-width: 4; }
        .ring .bgc { stroke: #d6ece1; }
        .ring .fg { stroke: var(--accent); transition: stroke-dashoffset 1s linear, stroke .3s; }
        .ring.low .fg { stroke: var(--danger); }
        .ring span { position: absolute; inset: 0; display: grid; place-items: center; font: 700 13px "JetBrains Mono", monospace; }
        .chip { display: inline-flex; align-items: center; gap: .4rem; color: var(--accent); font-size: 10.5px; font-weight: 800; letter-spacing: .16em; text-transform: uppercase; }
        .chip svg { width: 14px; height: 14px; }
        .facts { display: grid; grid-template-columns: auto 1fr; gap: .1rem .9rem; margin: .35rem 0 0; font-size: 13.5px; }
        .facts dt { color: var(--muted); } .facts dd { margin: 0; font-weight: 600; }

        .lbl { display: block; margin-bottom: .5rem; color: var(--subtle); font-size: 10px; font-weight: 800; letter-spacing: .18em; text-transform: uppercase; }
        .code { position: relative; display: flex; align-items: center; gap: .4rem; width: max-content; max-width: 100%; }
        .code .cell { display: grid; place-items: center; width: 46px; height: 56px; background: var(--surface); border: 1px solid var(--line);
            font: 700 22px "JetBrains Mono", monospace; color: var(--ink); opacity: 0; transform: translateY(6px); animation: rise .45s var(--ease) forwards; transition: border-color .2s, background .2s, box-shadow .2s; }
        .code .cell:nth-child(1) { animation-delay: .3s } .code .cell:nth-child(2) { animation-delay: .34s } .code .cell:nth-child(3) { animation-delay: .38s } .code .cell:nth-child(4) { animation-delay: .42s }
        .code .cell:nth-child(6) { animation-delay: .5s } .code .cell:nth-child(7) { animation-delay: .54s } .code .cell:nth-child(8) { animation-delay: .58s } .code .cell:nth-child(9) { animation-delay: .62s }
        .code .cell.on { border-color: var(--ink); border-bottom: 2px solid var(--accent); }
        .code .cell.cur { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(11, 138, 92, .14); }
        .code .cell.cur::after { content: ""; width: 2px; height: 45%; background: var(--accent); animation: blink 1s steps(1) infinite; }
        @keyframes blink { 50% { opacity: 0; } }
        .code .dash { width: 10px; height: 2px; background: var(--subtle); }
        .code input { position: absolute; inset: 0; width: 100%; opacity: 0; border: 0; font-size: 16px; cursor: text; }
        .hint { margin: .7rem 0 0; color: var(--subtle); font-size: 12.5px; }

        .target { display: flex; align-items: center; gap: .8rem; margin-top: 1.3rem; padding: .8rem .95rem; border: 1px solid var(--line); background: var(--bg); font-size: 13.5px; color: var(--muted); }
        .target strong { color: var(--ink); }
        .actions { display: flex; flex-wrap: wrap; gap: .5rem; margin-top: 1.3rem; }
        .btn { display: inline-flex; align-items: center; justify-content: center; gap: .5rem; height: 42px; padding: 0 18px; border: 1px solid var(--line); background: var(--surface); color: var(--ink);
            font: 800 13px Archivo, sans-serif; text-decoration: none; cursor: pointer; transition: background .18s, color .18s, border-color .18s; }
        .btn:hover { border-color: var(--ink); }
        .btn.main { background: var(--ink); border-color: var(--ink); color: #fff; }
        .btn.main:hover { background: var(--mint); border-color: var(--mint); color: var(--accent-dark); }
        .btn svg { width: 16px; height: 16px; transition: transform .2s var(--ease); }
        .btn:hover svg { transform: translateX(3px); }

        /* Jeu lié : coche qui se dessine. */
        .done-mark { width: 56px; height: 56px; margin-bottom: 1rem; }
        .done-mark i { display: none; }
        .done-mark svg { width: 56px; height: 56px; }
        .done-mark circle { fill: var(--tint); stroke: var(--accent); stroke-width: 4; stroke-dasharray: 315; stroke-dashoffset: 315; animation: draw .6s .2s var(--ease) forwards; }
        .done-mark path { fill: none; stroke: var(--accent); stroke-width: 7; stroke-linecap: square; stroke-dasharray: 60; stroke-dashoffset: 60; animation: draw .45s .7s var(--ease) forwards; }
        @keyframes draw { to { stroke-dashoffset: 0; } }
        .track { display: flex; align-items: flex-start; gap: .7rem; padding: .8rem .95rem; border: 1px solid var(--line); background: var(--bg); font-size: 13.5px; font-weight: 600; }
        .dot { flex: none; width: 8px; height: 8px; margin-top: .4rem; border-radius: 50%; background: var(--warn); animation: ping 2s ease-out infinite; }

        .side h3 { margin: .2rem 0 .7rem; color: var(--subtle); font-size: 10px; font-weight: 800; letter-spacing: .18em; text-transform: uppercase; }
        .where { display: grid; gap: .6rem; margin-bottom: .6rem; }
        .tile { display: flex; gap: .85rem; padding: 1rem; background: var(--surface); border: 1px solid var(--line); transition: transform .2s var(--ease), box-shadow .2s, border-color .2s; }
        .tile:hover { transform: translateY(-2px); box-shadow: 0 12px 30px rgba(12, 17, 22, .08); border-color: #c9d8d1; }
        .tile .ic { display: grid; place-items: center; flex: none; width: 34px; height: 34px; background: var(--tint); color: var(--accent); }
        .tile .ic svg { width: 18px; height: 18px; }
        .tile b { display: block; font-size: 14px; font-weight: 800; }
        .tile p { margin: .25rem 0 0; color: var(--muted); font-size: 13px; }
        .tile p strong { color: var(--ink); }
        .tile.safe { background: var(--warn-bg); border-color: var(--warn-bd); }
        .tile.safe .ic { background: #fff; color: var(--warn); }
        .tile.safe b { color: #7a5208; }
        .kbd { display: inline-block; padding: 0 5px; border: 1px solid var(--line); border-bottom-width: 2px; background: var(--surface); font: 600 11.5px "JetBrains Mono", monospace; color: var(--ink); }
        .foot { margin: 2rem 0 0; color: var(--subtle); font-size: 12.5px; text-align: center; }

        .rise { opacity: 0; transform: translateY(10px); animation: rise .6s var(--ease) forwards; }
        .d1 { animation-delay: .02s } .d2 { animation-delay: .08s } .d3 { animation-delay: .14s } .d4 { animation-delay: .2s } .d5 { animation-delay: .28s } .d6 { animation-delay: .36s }
        @keyframes rise { to { opacity: 1; transform: none; } }

        @media (max-width: 860px) {
            .grid2 { grid-template-columns: minmax(0, 1fr); }
            .steps li { grid-template-columns: 1fr; row-gap: .4rem; padding: .8rem; }
            .steps .n { grid-row: auto; }
            .steps small { display: none; }
            .steps b { font-size: 12px; }
            .who .nm { display: none; }
            .code { width: 100%; gap: .3rem; }
            .code .cell { flex: 1 1 0; min-width: 0; width: auto; height: 50px; font-size: 19px; }
            .code .dash { flex: 0 0 6px; width: 6px; }
            .btn { flex: 1 1 auto; }
        }
        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after { animation-duration: .001ms !important; animation-iteration-count: 1 !important; transition: none !important; }
        }
    </style>
</head>
<body>
<div class="page">
<header class="top">
    <a class="brand" href="<?= $h($base !== '' ? $base : '/') ?>"><img src="<?= $h($base) ?>/assets/icons/athena-192.png" alt=""> <?= $h($brand) ?></a>
    <?php if ($guest): ?>
        <a class="who guest" href="<?= $h((string) $loginUrl) ?>">Se connecter</a>
    <?php else: ?>
        <div class="who">
            <span class="av"><?= $h(mb_strtoupper(mb_substr($account !== '' ? $account : 'A', 0, 1))) ?></span>
            <span class="nm"><strong><?= $h($account !== '' ? $account : 'votre compte') ?></strong><?php if ($community !== ''): ?><small><?= $h($community) ?></small><?php endif; ?></span>
        </div>
    <?php endif; ?>
</header>

<main class="wrap" id="contenu">
    <p class="kicker rise d1"><span class="live"></span>Arma 3 · Liaison du jeu</p>
    <h1 class="rise d2"><?= $linked ? 'Votre jeu est lié' : 'Relier votre jeu à Athena' ?></h1>
    <p class="lead rise d3"><?= $linked
        ? 'Le téléphone récupère votre profil, votre communauté et vos réglages. Aux parties suivantes, la connexion se fait seule avec votre compte Steam.'
        : 'Saisissez le code affiché dans Arma. Aucune clé ni identifiant de communauté : votre compte Athena suffit.' ?></p>

    <ol class="steps rise d4" data-step="<?= (int) $step ?>" aria-label="Étapes">
        <?php foreach ($steps as $n => [$label, $help]): ?>
            <li class="<?= $n < $step ? 'done' : ($n === $step ? 'now' : '') ?>"<?= $n === $step ? ' aria-current="step"' : '' ?>>
                <span class="n"><?= $n < $step ? '✓' : $n ?></span>
                <b><?= $h($label) ?></b><small><?= $h($help) ?></small>
            </li>
        <?php endforeach; ?>
    </ol>

    <?php
    // Les 8 cases du code (XXXX-XXXX), remplies par le serveur puis par la saisie.
    $chars = str_split(str_pad(substr(strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $code) ?? ''), 0, 8), 8, ' '));
    $cells = static function (array $chars) use ($h): string {
        $out = '';
        foreach ($chars as $i => $c) {
            if ($i === 4) {
                $out .= '<span class="dash" aria-hidden="true"></span>';
            }
            $out .= '<span class="cell' . ($c !== ' ' ? ' on' : '') . '" aria-hidden="true">' . $h(trim($c)) . '</span>';
        }

        return $out;
    };
    ?>
    <div class="grid2">
        <section class="card rise d5">
            <?php if ($error): ?><p class="alert bad" role="alert"><?= $h((string) $error) ?></p><?php endif; ?>

            <?php if ($guest): ?>
                <h2>Connectez-vous pour autoriser ce code</h2>
                <p class="sub">Le jeu sera rattaché au compte avec lequel vous vous connectez. Le code est gardé : vous revenez ici juste après.</p>
                <?php if ($code !== ''): ?>
                    <label class="lbl">Code reçu du jeu</label>
                    <div class="code static" aria-label="<?= $h($code) ?>"><?= $cells($chars) ?></div>
                    <p class="hint">Ce code reste valable quelques minutes. S'il expire, demandez-en un nouveau dans Arma.</p>
                <?php endif; ?>
                <div class="actions" style="margin-top:1.6rem">
                    <a class="btn main" href="<?= $h((string) $loginUrl) ?>">Se connecter <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg></a>
                    <a class="btn" href="<?= $h((string) $registerUrl) ?>">Créer un compte</a>
                </div>

            <?php elseif ($linked): ?>
                <div class="done-mark" aria-hidden="true"><i></i><i></i><i></i>
                    <svg viewBox="0 0 108 108"><circle cx="54" cy="54" r="50"/><path d="M33 56l14 14 28-30"/></svg>
                </div>
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
                    <p class="alert warn">Ce compte Steam était aussi rattaché à un autre compte de votre communauté. Il ne mène plus qu'à celui-ci, pour que la connexion automatique ouvre toujours le bon compte.</p>
                <?php endif; ?>
                <div class="actions">
                    <a class="btn" href="<?= $h(url('account/security/devices')) ?>">Voir mes appareils liés</a>
                    <a class="btn" href="<?= $h(url('lier')) ?>">Lier un autre jeu</a>
                </div>

            <?php else: ?>
                <?php if ($pending): ?>
                    <?php
                    $steamTail = $gameRequest['steam'] !== '' ? '…' . substr((string) $gameRequest['steam'], -5) : 'non transmis';
                    $leftSec = (int) ($gameRequest['left_sec'] ?? ((int) $gameRequest['left_min'] * 60));
                    ?>
                    <div class="req">
                        <div class="ring" data-left="<?= $leftSec ?>" data-total="600" aria-hidden="true">
                            <svg viewBox="0 0 74 74" width="74" height="74"><circle class="bgc" cx="37" cy="37" r="32"/><circle class="fg" cx="37" cy="37" r="32" stroke-dasharray="201" stroke-dashoffset="0"/></svg>
                            <span data-clock><?= (int) $gameRequest['left_min'] ?>:00</span>
                        </div>
                        <div>
                            <div class="chip">
                                <?php if ($source === 'eden'): ?>
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="13" rx="2"/><path d="M8 21h8M12 17v4"/></svg>
                                <?php else: ?>
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="6" y="2" width="12" height="20" rx="2.5"/><path d="M11 18h2"/></svg>
                                <?php endif; ?>
                                <?= $h($sourceLabel !== '' ? $sourceLabel : 'Demande reçue du jeu') ?>
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
                        </div>
                    </div>
                    <?php if ($gameRequest['steam_elsewhere']): ?>
                        <p class="alert warn">Ce compte Steam est déjà rattaché à un autre compte de votre communauté. En autorisant, il passera sur le compte avec lequel vous êtes connecté ici.</p>
                    <?php endif; ?>
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
                    <div class="code" data-code>
                        <?= $cells($chars) ?>
                        <input type="text" name="code" id="code" required maxlength="11" inputmode="text"
                               autocomplete="one-time-code" autocapitalize="characters" spellcheck="false"
                               value="<?= $h($code) ?>" placeholder="XXXX-XXXX"<?= $code === '' ? ' autofocus' : '' ?>>
                    </div>
                    <div class="target">
                        <span class="av"><?= $h(mb_strtoupper(mb_substr($account !== '' ? $account : 'A', 0, 1))) ?></span>
                        <span>Le jeu sera rattaché à <strong><?= $h($account !== '' ? $account : 'votre compte') ?></strong><?= $community !== '' ? ', communauté <strong>' . $h($community) . '</strong>' : '' ?>. Votre compte Steam est relié en même temps.</span>
                    </div>
                    <div class="actions">
                        <button type="submit" class="btn main">Autoriser ce jeu <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg></button>
                        <a class="btn" href="<?= $h(url('account/security/devices')) ?>">Mes appareils liés</a>
                    </div>
                </form>
            <?php endif; ?>
        </section>

        <aside class="side rise d6">
            <h3>Où trouver le code ?</h3>
            <div class="where">
                <div class="tile">
                    <span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="6" y="2" width="12" height="20" rx="2.5"/><path d="M11 18h2"/></svg></span>
                    <div><b>Téléphone ATAK</b>
                    <p>Sortez le téléphone (<span class="kbd">Ctrl</span> + <span class="kbd">U</span>), app <strong>Athena</strong>, onglet CODE. <strong>OUVRIR LA PAGE</strong> ouvre cette page avec le code déjà rempli, et le QR code l'ouvre sur votre vrai téléphone.</p></div>
                </div>
                <div class="tile">
                    <span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="13" rx="2"/><path d="M8 21h8M12 17v4"/></svg></span>
                    <div><b>Éditeur Eden (COMSPEC SSE)</b>
                    <p>Menu <strong>COMSPEC SSE</strong>, <strong>Connexion Athena</strong> : un code s'affiche, avec <strong>Ouvrir la page</strong> et <strong>Nouveau code</strong>.</p></div>
                </div>
            </div>
            <div class="tile safe">
                <span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 3l8 3v6c0 4.5-3.4 8.3-8 9-4.6-.7-8-4.5-8-9V6l8-3z"/><path d="M12 8v5M12 16h.01"/></svg></span>
                <div><b>N'autorisez que votre propre jeu</b>
                <p>Un code reçu de quelqu'un d'autre lui donnerait accès à votre compte. Vous n'avez rien demandé ? Ne validez rien : sans votre accord, le jeu reste déconnecté.</p></div>
            </div>
        </aside>
    </div>

    <p class="foot">Le code expire vite : s'il est refusé, touchez NOUVEAU CODE dans Arma et recommencez.</p>
</main>
</div>

<script>
(function () {
    // Saisie libre (espaces, tirets, minuscules) remise au format XXXX-XXXX, reflétée dans les 8 cases.
    var box = document.querySelector('[data-code]');
    var input = document.getElementById('code');
    if (box && input) {
        var cells = box.querySelectorAll('.cell');
        var paint = function () {
            var raw = input.value.toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, 8);
            input.value = raw.length > 4 ? raw.slice(0, 4) + '-' + raw.slice(4) : raw;
            var focused = document.activeElement === input;
            for (var i = 0; i < cells.length; i++) {
                cells[i].textContent = raw.charAt(i);
                cells[i].classList.toggle('on', i < raw.length);
                cells[i].classList.toggle('cur', focused && i === Math.min(raw.length, 7) && raw.length < 8);
            }
        };
        ['input', 'focus', 'blur'].forEach(function (e) { input.addEventListener(e, paint); });
        paint();
    }
    // Anneau du compte à rebours.
    var ring = document.querySelector('.ring[data-left]');
    if (ring) {
        var left = parseInt(ring.getAttribute('data-left'), 10) || 0;
        var total = Math.max(left, parseInt(ring.getAttribute('data-total'), 10) || 600);
        var fg = ring.querySelector('.fg'), clock = ring.querySelector('[data-clock]');
        var tick = function () {
            var m = Math.floor(Math.max(left, 0) / 60), s = Math.max(left, 0) % 60;
            clock.textContent = m + ':' + (s < 10 ? '0' : '') + s;
            fg.style.strokeDashoffset = String(201 * (1 - Math.max(left, 0) / total));
            ring.classList.toggle('low', left < 60);
            if (left-- > 0) { setTimeout(tick, 1000); }
        };
        tick();
    }
    // Après l'autorisation : le jeu a-t-il repris sa session ?
    var watch = document.querySelector('[data-link-watch]');
    if (!watch) { return; }
    var dot = watch.querySelector('[data-link-dot]');
    var text = watch.querySelector('[data-link-text]');
    var tries = 0;
    function msg(k) { var el = watch.querySelector('[data-msg="' + k + '"]'); return el ? el.textContent : ''; }
    function show(color, k) { dot.style.background = color; dot.style.animation = 'none'; text.textContent = msg(k); }
    function poll() {
        tries++;
        fetch(watch.getAttribute('data-link-watch'), { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (d.state === 'consumed') { show('#0b8a5c', 'consumed'); return; }
                if (d.state === 'expired') { show('#c72e2e', 'expired'); return; }
                if (d.state === 'unknown') { show('#8c979b', 'unknown'); return; }
                if (tries < 150) { setTimeout(poll, 2000); } else { show('#8c979b', 'late'); }
            })
            .catch(function () { if (tries < 150) { setTimeout(poll, 4000); } });
    }
    setTimeout(poll, 1500);
})();
</script>
</body>
</html>
