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
    <meta name="theme-color" content="#05080a">
    <meta name="robots" content="noindex,nofollow">
    <link rel="icon" href="<?= $h($base) ?>/assets/icons/athena-192.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@600;700;800&family=Barlow:wght@400;500;600;700&family=JetBrains+Mono:wght@600;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #05080a; --ink: #eef5f1; --dim: #8fa39a; --mute: #5f7068; --line: rgba(160, 255, 200, .10);
            --glass: rgba(14, 22, 19, .62); --glass-2: rgba(20, 31, 27, .72);
            --neon: #5cf600; --green: #34d27a; --teal: #19c6b1; --amber: #f2c66b; --red: #ff7a6b;
            --ease: cubic-bezier(.22, 1, .36, 1);
        }
        * { box-sizing: border-box; }
        html { color-scheme: dark; }
        body { margin: 0; min-height: 100vh; background: var(--bg); color: var(--ink); font: 16px/1.55 Barlow, system-ui, sans-serif; overflow-x: hidden; }
        a { color: var(--green); }

        /* Fond : aurores qui dérivent, grille tactique en perspective, balayage radar, grain. */
        .bg { position: fixed; inset: 0; z-index: 0; pointer-events: none; overflow: hidden; }
        .blob { position: absolute; border-radius: 50%; filter: blur(90px); opacity: .55; mix-blend-mode: screen; }
        .blob.a { width: 52vw; height: 52vw; left: -12vw; top: -18vw; background: radial-gradient(circle, #14a36b 0%, transparent 65%); animation: drift-a 22s ease-in-out infinite alternate; }
        .blob.b { width: 46vw; height: 46vw; right: -14vw; top: 8vh; background: radial-gradient(circle, #0f7f8c 0%, transparent 65%); animation: drift-b 26s ease-in-out infinite alternate; }
        .blob.c { width: 40vw; height: 40vw; left: 30vw; bottom: -22vw; background: radial-gradient(circle, rgba(92, 246, 0, .55) 0%, transparent 62%); opacity: .28; animation: drift-c 30s ease-in-out infinite alternate; }
        @keyframes drift-a { to { transform: translate(14vw, 10vh) scale(1.15); } }
        @keyframes drift-b { to { transform: translate(-12vw, 16vh) scale(.9); } }
        @keyframes drift-c { to { transform: translate(-16vw, -8vh) scale(1.2); } }
        .grid { position: absolute; left: -50%; right: -50%; bottom: -10vh; height: 70vh; transform: perspective(600px) rotateX(62deg); transform-origin: bottom;
            background-image: linear-gradient(var(--line) 1px, transparent 1px), linear-gradient(90deg, var(--line) 1px, transparent 1px); background-size: 64px 64px;
            mask-image: linear-gradient(to top, #000 10%, transparent 85%); -webkit-mask-image: linear-gradient(to top, #000 10%, transparent 85%); animation: grid 9s linear infinite; }
        @keyframes grid { to { background-position: 0 64px, 0 0; } }
        .radar { position: absolute; width: 900px; height: 900px; right: -260px; top: -300px; border-radius: 50%; opacity: .35;
            background: repeating-radial-gradient(circle, transparent 0 89px, rgba(92, 246, 0, .14) 89px 90px); }
        .radar::after { content: ""; position: absolute; inset: 0; border-radius: 50%;
            background: conic-gradient(from 0deg, rgba(92, 246, 0, .28), transparent 22%); animation: sweep 6s linear infinite; }
        @keyframes sweep { to { transform: rotate(360deg); } }
        .vignette { position: absolute; inset: 0; background: radial-gradient(ellipse at 50% 35%, transparent 40%, rgba(0, 0, 0, .7) 100%); }

        .page { position: relative; z-index: 1; }
        .top { position: sticky; top: 0; z-index: 5; display: flex; align-items: center; justify-content: space-between; gap: 1rem;
            padding: .8rem clamp(1rem, 4vw, 2.5rem); background: rgba(5, 8, 10, .55); backdrop-filter: blur(14px); -webkit-backdrop-filter: blur(14px); border-bottom: 1px solid var(--line); }
        .brand { display: flex; align-items: center; gap: .65rem; color: var(--ink); text-decoration: none; font: 800 1.15rem "Barlow Condensed", sans-serif; letter-spacing: .18em; text-transform: uppercase; }
        .brand img { width: 34px; height: 34px; border-radius: 9px; padding: 4px; background: #0b0f0d; box-shadow: 0 0 0 1px var(--line), 0 0 24px rgba(92, 246, 0, .25); }
        .who { display: flex; align-items: center; gap: .6rem; padding: .3rem .85rem .3rem .35rem; border-radius: 999px; background: var(--glass); border: 1px solid var(--line); font-size: .86rem; }
        .who .av { display: grid; place-items: center; width: 1.9rem; height: 1.9rem; border-radius: 50%; background: linear-gradient(135deg, var(--neon), var(--teal)); color: #04110a; font-weight: 800; }
        .who small { display: block; color: var(--dim); font-size: .74rem; line-height: 1.1; }
        .who strong { display: block; line-height: 1.15; }
        .who.guest { padding: .45rem 1rem; text-decoration: none; font-weight: 700; color: var(--ink); }

        .wrap { max-width: 1120px; margin: 0 auto; padding: clamp(1.6rem, 5vw, 3.6rem) clamp(1rem, 4vw, 2.5rem) 4rem; }
        .kicker { display: inline-flex; align-items: center; gap: .55rem; margin: 0 0 1rem; padding: .3rem .8rem; border-radius: 999px; background: rgba(92, 246, 0, .08); border: 1px solid rgba(92, 246, 0, .25);
            font: 700 .78rem "Barlow Condensed", sans-serif; letter-spacing: .22em; text-transform: uppercase; color: var(--neon); }
        .live { width: .5rem; height: .5rem; border-radius: 50%; background: var(--neon); box-shadow: 0 0 0 0 rgba(92, 246, 0, .7); animation: ping 1.8s infinite; }
        @keyframes ping { 70% { box-shadow: 0 0 0 10px rgba(92, 246, 0, 0); } 100% { box-shadow: 0 0 0 0 rgba(92, 246, 0, 0); } }
        h1 { margin: 0; max-width: 16ch; font: 800 clamp(2.4rem, 7vw, 4.6rem)/.95 "Barlow Condensed", sans-serif; letter-spacing: -.01em; text-transform: uppercase;
            background: linear-gradient(100deg, #fff 0%, #dff7e9 35%, var(--neon) 50%, #dff7e9 65%, #fff 100%); background-size: 250% 100%;
            -webkit-background-clip: text; background-clip: text; color: transparent; animation: shine 7s linear infinite; }
        @keyframes shine { from { background-position: 100% 0; } to { background-position: -150% 0; } }
        .lead { margin: 1rem 0 0; max-width: 38rem; color: var(--dim); font-size: clamp(1rem, 2.2vw, 1.15rem); }

        /* Étapes : rail lumineux qui se remplit jusqu'à l'étape en cours. */
        .steps { position: relative; display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; margin: 2.4rem 0 2.2rem; padding: 0; list-style: none; }
        .steps::before, .steps::after { content: ""; position: absolute; top: 1.3rem; left: 1.3rem; height: 2px; border-radius: 2px; }
        .steps::before { right: 1.3rem; background: rgba(255, 255, 255, .08); }
        .steps::after { width: 0; background: linear-gradient(90deg, var(--neon), var(--teal)); box-shadow: 0 0 14px var(--neon); animation: fill 1.4s .5s var(--ease) forwards; }
        .steps[data-step="2"]::after { --to: 50%; } .steps[data-step="3"]::after { --to: calc(100% - 2.6rem); }
        @keyframes fill { to { width: var(--to, 0); } }
        .steps li { position: relative; z-index: 1; }
        .steps .n { display: grid; place-items: center; width: 2.6rem; height: 2.6rem; border-radius: 50%; background: #0b120f; border: 2px solid rgba(255, 255, 255, .14);
            font: 800 1rem "Barlow Condensed", sans-serif; color: var(--mute); transition: all .4s var(--ease); }
        .steps b { display: block; margin-top: .7rem; font: 700 1.05rem "Barlow Condensed", sans-serif; letter-spacing: .06em; text-transform: uppercase; color: var(--dim); }
        .steps small { display: block; color: var(--mute); font-size: .82rem; }
        .steps li.done .n { background: var(--neon); border-color: var(--neon); color: #04110a; box-shadow: 0 0 22px rgba(92, 246, 0, .55); }
        .steps li.done b { color: var(--ink); }
        .steps li.now .n { border-color: var(--neon); color: var(--neon); box-shadow: 0 0 0 6px rgba(92, 246, 0, .12), 0 0 26px rgba(92, 246, 0, .35); animation: halo 2.2s ease-in-out infinite; }
        .steps li.now b { color: var(--neon); }
        @keyframes halo { 50% { box-shadow: 0 0 0 12px rgba(92, 246, 0, .04), 0 0 34px rgba(92, 246, 0, .5); } }

        .grid2 { display: grid; grid-template-columns: minmax(0, 1.6fr) minmax(0, 1fr); gap: 1.4rem; align-items: start; }
        /* Carte principale : verre dépoli et bordure en dégradé qui tourne. */
        .card { position: relative; padding: clamp(1.3rem, 3.4vw, 2.1rem); border-radius: 22px; background: var(--glass); backdrop-filter: blur(18px); -webkit-backdrop-filter: blur(18px);
            box-shadow: 0 30px 80px rgba(0, 0, 0, .55), inset 0 1px 0 rgba(255, 255, 255, .05); isolation: isolate; }
        .card.glow::before { content: ""; position: absolute; inset: -1px; z-index: -1; border-radius: 23px; padding: 1px;
            background: conic-gradient(from var(--a, 0deg), rgba(92, 246, 0, .9), rgba(25, 198, 177, .2) 25%, transparent 45%, transparent 60%, rgba(25, 198, 177, .4) 80%, rgba(92, 246, 0, .9));
            -webkit-mask: linear-gradient(#000 0 0) content-box, linear-gradient(#000 0 0); -webkit-mask-composite: xor; mask-composite: exclude; animation: spin 8s linear infinite; }
        @property --a { syntax: "<angle>"; inherits: false; initial-value: 0deg; }
        @keyframes spin { to { --a: 360deg; } }
        .card h2 { margin: 0 0 .3rem; font: 800 clamp(1.5rem, 3.4vw, 2rem)/1.05 "Barlow Condensed", sans-serif; text-transform: uppercase; letter-spacing: .02em; }
        .card p.sub { margin: 0 0 1.4rem; color: var(--dim); }
        .alert { margin: 0 0 1.2rem; padding: .85rem 1rem; border-radius: 12px; font-weight: 600; font-size: .95rem; border: 1px solid; }
        .alert.bad { background: rgba(255, 122, 107, .1); border-color: rgba(255, 122, 107, .35); color: var(--red); animation: shake .5s var(--ease); }
        .alert.warn { background: rgba(242, 198, 107, .08); border-color: rgba(242, 198, 107, .3); color: var(--amber); }
        @keyframes shake { 20%, 60% { transform: translateX(-6px); } 40%, 80% { transform: translateX(6px); } }

        .req { display: flex; gap: 1.1rem; align-items: center; margin: 0 0 1.6rem; padding: 1rem 1.1rem; border-radius: 16px; background: rgba(92, 246, 0, .05); border: 1px solid rgba(92, 246, 0, .22); }
        .ring { position: relative; flex: none; width: 74px; height: 74px; }
        .ring svg { transform: rotate(-90deg); }
        .ring circle { fill: none; stroke-width: 5; }
        .ring .bgc { stroke: rgba(255, 255, 255, .08); }
        .ring .fg { stroke: var(--neon); stroke-linecap: round; filter: drop-shadow(0 0 6px rgba(92, 246, 0, .7)); transition: stroke-dashoffset 1s linear, stroke .4s; }
        .ring.low .fg { stroke: var(--amber); filter: drop-shadow(0 0 6px rgba(242, 198, 107, .7)); }
        .ring span { position: absolute; inset: 0; display: grid; place-items: center; font: 800 .95rem "JetBrains Mono", monospace; }
        .chip { display: inline-flex; align-items: center; gap: .45rem; margin-bottom: .35rem; font: 700 .82rem "Barlow Condensed", sans-serif; letter-spacing: .14em; text-transform: uppercase; color: var(--neon); }
        .chip svg { width: 16px; height: 16px; }
        .facts { display: grid; grid-template-columns: auto 1fr; gap: .1rem .9rem; margin: 0; font-size: .92rem; }
        .facts dt { color: var(--mute); }
        .facts dd { margin: 0; font-weight: 600; }
        .mono { font-family: "JetBrains Mono", ui-monospace, monospace; }

        /* Code en 8 cases néon ; un vrai champ transparent par-dessus reçoit la saisie. */
        label.lbl { display: block; margin-bottom: .6rem; font: 700 .8rem "Barlow Condensed", sans-serif; letter-spacing: .2em; text-transform: uppercase; color: var(--dim); }
        .code { position: relative; display: flex; align-items: center; gap: .45rem; width: max-content; max-width: 100%; }
        .code .cell { display: grid; place-items: center; width: clamp(2.3rem, 7vw, 3.3rem); height: clamp(3rem, 9vw, 4.2rem); border-radius: 12px; background: rgba(0, 0, 0, .45);
            border: 1px solid rgba(255, 255, 255, .1); font: 800 clamp(1.4rem, 4.4vw, 2.1rem) "JetBrains Mono", monospace; color: #c8ffb0;
            text-shadow: 0 0 12px rgba(92, 246, 0, .8); transition: border-color .25s, box-shadow .25s, transform .25s var(--ease); opacity: 0; transform: translateY(10px); animation: cell .5s var(--ease) forwards; }
        .code .cell:nth-child(1) { animation-delay: .35s } .code .cell:nth-child(2) { animation-delay: .4s } .code .cell:nth-child(3) { animation-delay: .45s } .code .cell:nth-child(4) { animation-delay: .5s }
        .code .cell:nth-child(6) { animation-delay: .6s } .code .cell:nth-child(7) { animation-delay: .65s } .code .cell:nth-child(8) { animation-delay: .7s } .code .cell:nth-child(9) { animation-delay: .75s }
        @keyframes cell { to { opacity: 1; transform: none; } }
        .code .cell.on { border-color: rgba(92, 246, 0, .55); box-shadow: 0 0 0 1px rgba(92, 246, 0, .25), 0 0 22px rgba(92, 246, 0, .25) inset; }
        .code .cell.cur { border-color: var(--neon); box-shadow: 0 0 0 3px rgba(92, 246, 0, .25); }
        .code .cell.cur::after { content: ""; width: 2px; height: 46%; background: var(--neon); animation: blink 1s steps(1) infinite; }
        @keyframes blink { 50% { opacity: 0; } }
        .code .dash { width: .9rem; height: 3px; border-radius: 2px; background: rgba(255, 255, 255, .25); }
        .code input { position: absolute; inset: 0; width: 100%; opacity: 0; border: 0; font-size: 16px; cursor: text; }
        .code.static .cell.on { box-shadow: 0 0 22px rgba(92, 246, 0, .25) inset; }
        .hint { margin: .7rem 0 0; color: var(--mute); font-size: .86rem; }
        .target { display: flex; gap: .8rem; align-items: center; margin: 1.5rem 0; padding: .85rem 1rem; border-radius: 14px; background: rgba(255, 255, 255, .03); border: 1px dashed rgba(255, 255, 255, .12); font-size: .93rem; color: var(--dim); }
        .target strong { color: var(--ink); }
        .target .av { flex: none; display: grid; place-items: center; width: 2.4rem; height: 2.4rem; border-radius: 50%; background: linear-gradient(135deg, var(--neon), var(--teal)); color: #04110a; font-weight: 800; }

        .actions { display: flex; flex-wrap: wrap; gap: .8rem; align-items: center; }
        .btn { position: relative; display: inline-flex; align-items: center; justify-content: center; gap: .55rem; min-height: 3.2rem; padding: .8rem 1.5rem; overflow: hidden;
            border-radius: 12px; border: 1px solid rgba(255, 255, 255, .14); background: rgba(255, 255, 255, .04); color: var(--ink);
            font: 700 1.05rem "Barlow Condensed", sans-serif; letter-spacing: .12em; text-transform: uppercase; text-decoration: none; cursor: pointer; transition: transform .2s var(--ease), box-shadow .2s, background .2s; }
        .btn:hover { transform: translateY(-2px); background: rgba(255, 255, 255, .08); }
        .btn.main { border: 0; color: #04110a; background: linear-gradient(100deg, var(--neon), #3fe08a 55%, var(--teal)); box-shadow: 0 10px 30px rgba(92, 246, 0, .3); }
        .btn.main:hover { box-shadow: 0 14px 40px rgba(92, 246, 0, .45); }
        .btn.main::after { content: ""; position: absolute; top: 0; bottom: 0; width: 40%; left: -60%; background: linear-gradient(100deg, transparent, rgba(255, 255, 255, .55), transparent); transform: skewX(-20deg); animation: sweepbtn 3.2s 1.5s ease-in-out infinite; }
        @keyframes sweepbtn { 0% { left: -60%; } 40%, 100% { left: 130%; } }
        .btn svg { width: 18px; height: 18px; transition: transform .2s var(--ease); }
        .btn:hover svg { transform: translateX(3px); }

        /* Jeu lié : coche qui se dessine, ondes. */
        .done-mark { position: relative; width: 108px; height: 108px; margin: .2rem 0 1.3rem; }
        .done-mark i { position: absolute; inset: 0; border-radius: 50%; border: 2px solid var(--neon); opacity: 0; animation: wave 2.4s ease-out infinite; }
        .done-mark i:nth-child(2) { animation-delay: .8s; } .done-mark i:nth-child(3) { animation-delay: 1.6s; }
        @keyframes wave { 0% { transform: scale(.7); opacity: .8; } 100% { transform: scale(1.7); opacity: 0; } }
        .done-mark svg { position: relative; width: 108px; height: 108px; filter: drop-shadow(0 0 14px rgba(92, 246, 0, .6)); }
        .done-mark circle { fill: rgba(92, 246, 0, .1); stroke: var(--neon); stroke-width: 3; }
        .done-mark path { fill: none; stroke: var(--neon); stroke-width: 7; stroke-linecap: round; stroke-linejoin: round; stroke-dasharray: 60; stroke-dashoffset: 60; animation: draw .7s .4s var(--ease) forwards; }
        @keyframes draw { to { stroke-dashoffset: 0; } }
        .track { display: flex; gap: .8rem; align-items: center; margin: 0 0 1.4rem; padding: .95rem 1.1rem; border-radius: 14px; background: rgba(255, 255, 255, .04); border: 1px solid var(--line); font-weight: 600; }
        .dot { flex: none; width: .75rem; height: .75rem; border-radius: 50%; background: var(--amber); box-shadow: 0 0 0 0 rgba(242, 198, 107, .6); animation: ping 1.6s infinite; }

        .side h3 { margin: .3rem 0 .9rem; font: 700 .82rem "Barlow Condensed", sans-serif; letter-spacing: .24em; text-transform: uppercase; color: var(--dim); }
        .where { display: grid; gap: .9rem; margin-bottom: .9rem; }
        .tile { display: flex; gap: .9rem; padding: 1.05rem 1.1rem; border-radius: 18px; background: var(--glass-2); border: 1px solid var(--line); backdrop-filter: blur(14px); -webkit-backdrop-filter: blur(14px);
            transition: transform .3s var(--ease), border-color .3s, box-shadow .3s; }
        .tile:hover { transform: translateY(-3px); border-color: rgba(92, 246, 0, .35); box-shadow: 0 16px 40px rgba(0, 0, 0, .4), 0 0 30px rgba(92, 246, 0, .08); }
        .tile .ic { flex: none; display: grid; place-items: center; width: 2.7rem; height: 2.7rem; border-radius: 12px; background: rgba(92, 246, 0, .1); color: var(--neon); }
        .tile .ic svg { width: 22px; height: 22px; }
        .tile b { display: block; margin-bottom: .2rem; font: 700 1.05rem "Barlow Condensed", sans-serif; letter-spacing: .06em; text-transform: uppercase; }
        .tile p { margin: 0; color: var(--dim); font-size: .9rem; }
        .tile strong { color: var(--ink); }
        .tile.safe { background: rgba(242, 198, 107, .06); border-color: rgba(242, 198, 107, .22); }
        .tile.safe .ic { background: rgba(242, 198, 107, .12); color: var(--amber); }
        .tile.safe b { color: var(--amber); }
        .kbd { display: inline-block; padding: 0 .35rem; border: 1px solid rgba(255, 255, 255, .18); border-bottom-width: 2px; border-radius: 5px; font: 700 .76rem "JetBrains Mono", monospace; color: var(--ink); background: rgba(255, 255, 255, .05); }
        .foot { margin-top: 2.4rem; color: var(--mute); font-size: .88rem; text-align: center; }

        /* Entrée en scène décalée. */
        .rise { opacity: 0; transform: translateY(18px); animation: rise .8s var(--ease) forwards; }
        .d1 { animation-delay: .05s } .d2 { animation-delay: .15s } .d3 { animation-delay: .25s } .d4 { animation-delay: .35s } .d5 { animation-delay: .5s } .d6 { animation-delay: .65s }
        @keyframes rise { to { opacity: 1; transform: none; } }

        @media (max-width: 860px) {
            .grid2 { grid-template-columns: minmax(0, 1fr); }
            .code { width: 100%; gap: .3rem; }
            .code .cell { flex: 1 1 0; min-width: 0; width: auto; }
            .code .dash { flex: 0 0 .5rem; width: .5rem; }
            .steps { gap: .4rem; }
            .steps small { display: none; }
            .steps b { font-size: .88rem; }
            .who small, .who .nm { display: none; }
            .who { padding: .3rem; }
            .radar { width: 600px; height: 600px; right: -300px; top: -260px; }
        }
        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after { animation-duration: .001ms !important; animation-iteration-count: 1 !important; transition: none !important; }
            .steps::after { width: var(--to, 0); }
        }
    </style>
</head>
<body>
<div class="bg" aria-hidden="true"><div class="blob a"></div><div class="blob b"></div><div class="blob c"></div><div class="radar"></div><div class="grid"></div><div class="vignette"></div></div>
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
        <section class="card glow rise d5">
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
                if (d.state === 'consumed') { show('#5cf600', 'consumed'); return; }
                if (d.state === 'expired') { show('#ff7a6b', 'expired'); return; }
                if (d.state === 'unknown') { show('#8fa39a', 'unknown'); return; }
                if (tries < 150) { setTimeout(poll, 2000); } else { show('#8fa39a', 'late'); }
            })
            .catch(function () { if (tries < 150) { setTimeout(poll, 4000); } });
    }
    setTimeout(poll, 1500);
})();
</script>
</body>
</html>
