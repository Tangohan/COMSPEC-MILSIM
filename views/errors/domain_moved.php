<?php

declare(strict_types=1);

/**
 * Annonce du nouveau domaine, affichée aux visiteurs de l'ancien athena.ttrd.fr (public/index.php).
 * Page autonome (pas de coque ni de session) : bouton vers la même page sur le nouveau domaine.
 *
 * @var string $domainMovedTarget
 */
$target = (string) ($domainMovedTarget ?? 'https://athena-comspec.eu/');
$newHost = (string) (parse_url($target, PHP_URL_HOST) ?: 'athena-comspec.eu');
$oldHost = \App\Support\PortalHost::LEGACY_HOST;
$e = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$hero = 'https://' . $newHost . '/assets/images/fog-team.jpg';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="robots" content="noindex">
    <meta name="theme-color" content="#0b1210">
    <title>Athena déménage · <?= $e($newHost) ?></title>
    <link rel="canonical" href="<?= $e($target) ?>">
    <style>
        :root { --bg:#0b1210; --panel:rgba(14,22,19,.82); --line:rgba(143,227,155,.22); --text:#eef3f0; --mute:#a9b8b1; --accent:#8fe39b; --accent-ink:#06210f; }
        * { box-sizing: border-box; }
        html, body { margin: 0; min-height: 100%; }
        body {
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            color: var(--text); background: var(--bg);
            display: grid; place-items: center; min-height: 100vh; padding: 24px 16px;
            background-image: linear-gradient(180deg, rgba(11,18,16,.55), rgba(11,18,16,.96) 70%), url("<?= $e($hero) ?>");
            background-size: cover; background-position: center;
        }
        .card {
            width: 100%; max-width: 640px; padding: 40px 36px 32px;
            background: var(--panel); border: 1px solid var(--line); border-radius: 18px;
            backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px);
            box-shadow: 0 30px 80px rgba(0,0,0,.45);
        }
        .kicker { margin: 0 0 14px; font-size: 12px; letter-spacing: .18em; text-transform: uppercase; color: var(--accent); font-weight: 700; }
        h1 { margin: 0 0 12px; font-size: clamp(28px, 6vw, 40px); line-height: 1.1; letter-spacing: -.01em; }
        .lead { margin: 0 0 26px; font-size: 17px; line-height: 1.55; color: var(--mute); }
        .domains { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; margin: 0 0 28px; font-family: ui-monospace, "Cascadia Mono", Consolas, monospace; font-size: 15px; }
        .domains .old { color: var(--mute); text-decoration: line-through; text-decoration-color: rgba(169,184,177,.6); }
        .domains .arrow { color: var(--accent); }
        .domains .new { color: var(--text); padding: 6px 10px; border-radius: 8px; background: rgba(143,227,155,.12); border: 1px solid var(--line); }
        .btn {
            display: inline-flex; align-items: center; justify-content: center; gap: 10px; width: 100%;
            padding: 16px 22px; border-radius: 12px; background: var(--accent); color: var(--accent-ink);
            font-size: 17px; font-weight: 700; text-decoration: none; transition: transform .12s ease, box-shadow .12s ease;
            box-shadow: 0 10px 30px rgba(143,227,155,.25);
        }
        .btn:hover, .btn:focus-visible { transform: translateY(-1px); box-shadow: 0 14px 36px rgba(143,227,155,.35); outline: none; }
        .btn svg { width: 20px; height: 20px; }
        ul.notes { margin: 26px 0 0; padding: 0; list-style: none; display: grid; gap: 10px; font-size: 14.5px; line-height: 1.5; color: var(--mute); }
        ul.notes li { padding-left: 22px; position: relative; }
        ul.notes li::before { content: ""; position: absolute; left: 4px; top: .6em; width: 7px; height: 7px; border-radius: 50%; background: var(--accent); }
        ul.notes strong { color: var(--text); font-weight: 600; }
        .en { margin-top: 28px; padding-top: 20px; border-top: 1px solid var(--line); font-size: 14px; line-height: 1.5; color: var(--mute); }
        .en strong { color: var(--text); }
        .foot { margin: 18px 0 0; font-size: 12px; letter-spacing: .12em; text-transform: uppercase; color: rgba(169,184,177,.7); }
        @media (max-width: 480px) { .card { padding: 30px 20px 24px; } }
    </style>
</head>
<body>
<main class="card">
    <p class="kicker">Athena · COMSPEC</p>
    <h1>Athena a une nouvelle adresse</h1>
    <p class="lead">Le portail déménage sur son propre domaine. Même site, mêmes comptes, mêmes communautés : seule l’adresse change.</p>

    <p class="domains" translate="no">
        <span class="old"><?= $e($oldHost) ?></span>
        <span class="arrow" aria-hidden="true">→</span>
        <span class="new"><?= $e($newHost) ?></span>
    </p>

    <a class="btn" href="<?= $e($target) ?>">
        Continuer sur <?= $e($newHost) ?>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14"/><path d="m13 6 6 6-6 6"/></svg>
    </a>

    <ul class="notes">
        <li><strong>Mettez à jour vos favoris</strong> avec la nouvelle adresse.</li>
        <li><strong>Reconnectez-vous une fois</strong> sur <?= $e($newHost) ?> : vos identifiants ne changent pas.</li>
        <li><strong>En jeu, rien ne casse</strong> : le mod ATAK continue de fonctionner, la prochaine mise à jour passera sur la nouvelle adresse.</li>
    </ul>

    <p class="en" lang="en"><strong>Athena has moved to <?= $e($newHost) ?>.</strong> Same site, same accounts, same communities. Update your bookmarks and sign in once on the new address. The in-game ATAK mod keeps working.</p>

    <p class="foot">COMSPEC · <?= $e($newHost) ?></p>
</main>
</body>
</html>
