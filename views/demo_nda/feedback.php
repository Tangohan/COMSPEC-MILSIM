<?php
declare(strict_types=1);

$title = $title ?? 'Votre avis sur la démonstration';
$error = is_string($error ?? null) ? $error : null;
$success = is_string($success ?? null) ? $success : null;
$inboxConfigured = !empty($inboxConfigured);
/** @var array<string, string> $ratings */
$ratings = is_array($ratings ?? null) ? $ratings : [];
/** @var array<string, string> $highlights */
$highlights = is_array($highlights ?? null) ? $highlights : [];
/** @var array<string, string> $frictions */
$frictions = is_array($frictions ?? null) ? $frictions : [];
$old = is_array($old ?? null) ? $old : [];
$brand = email_brand_name();
$action = url(ltrim(\App\Services\DemoNda\DemoNdaGateService::FEEDBACK_PATH, '/'));

$oldOverall = (string) ($old['overall'] ?? '');
$oldNav = (string) ($old['navigation'] ?? '');
$oldClarity = (string) ($old['clarity'] ?? '');
$oldLook = (string) ($old['look_feel'] ?? '');
$oldHighlights = is_array($old['highlights'] ?? null) ? array_map('strval', $old['highlights']) : [];
$oldFrictions = is_array($old['frictions'] ?? null) ? array_map('strval', $old['frictions']) : [];
$oldIdeas = (string) ($old['ideas'] ?? '');
$oldEmail = (string) ($old['contact_email'] ?? '');
$oldContactOk = !empty($old['contact_ok']);

$ratingQuestions = [
    'overall' => ['label' => 'Impression générale', 'help' => 'Comment jugez-vous l’expérience dans son ensemble ?', 'value' => $oldOverall],
    'navigation' => ['label' => 'Facilité à se retrouver', 'help' => 'Était-il simple de comprendre où aller et quoi faire ?', 'value' => $oldNav],
    'clarity' => ['label' => 'Clarté des écrans', 'help' => 'Les textes, titres et informations étaient-ils faciles à lire ?', 'value' => $oldClarity],
    'look_feel' => ['label' => 'Ambiance visuelle', 'help' => 'Le rendu graphique vous a-t-il paru adapté et agréable ?', 'value' => $oldLook],
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?> — <?= htmlspecialchars($brand, ENT_QUOTES, 'UTF-8') ?></title>
    <meta name="robots" content="noindex,nofollow">
    <meta name="theme-color" content="#050505">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:ital,wght@0,400;0,600;0,700;0,900;1,900&display=swap">
    <style>
        :root {
            --ink: #f4f4f0;
            --muted: rgba(244, 244, 240, 0.68);
            --dim: rgba(244, 244, 240, 0.42);
            --accent: #34d399;
            --accent-soft: rgba(52, 211, 153, 0.14);
            --void: #050505;
            --surface: #0c0c0c;
            --panel: #121212;
            --panel-hover: #171717;
            --line: rgba(244, 244, 240, 0.12);
            --line-strong: rgba(244, 244, 240, 0.2);
            --font: Inter, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
        }
        * { box-sizing: border-box; }
        html, body {
            margin: 0;
            min-height: 100%;
            background:
                radial-gradient(ellipse 80% 40% at 50% -10%, rgba(52, 211, 153, 0.08), transparent 55%),
                var(--void);
            color: var(--ink);
            font-family: var(--font);
            -webkit-font-smoothing: antialiased;
        }
        body {
            min-height: 100svh;
            padding: 2.75rem 1.25rem 4rem;
        }
        .wrap {
            width: 100%;
            max-width: 42rem;
            margin: 0 auto;
        }
        .hero {
            text-align: center;
            padding-bottom: 0.5rem;
        }
        .kicker {
            margin: 0;
            font-size: 0.6875rem;
            font-weight: 600;
            letter-spacing: 0.28em;
            text-transform: uppercase;
            color: var(--accent);
        }
        h1 {
            margin: 1.25rem 0 0;
            font-size: clamp(2.25rem, 9vw, 3.5rem);
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: -0.045em;
            line-height: 0.92;
            color: #fff;
        }
        h1 span { color: var(--accent); }
        .lead {
            margin: 1.5rem auto 0;
            max-width: 30rem;
            font-size: 1.05rem;
            font-weight: 500;
            line-height: 1.65;
            color: var(--muted);
        }
        .flash {
            margin: 2rem 0 0;
            padding: 1rem 1.15rem;
            font-size: 0.95rem;
            font-weight: 600;
            line-height: 1.45;
            text-align: center;
        }
        .flash-error {
            color: #fecaca;
            border: 1px solid rgba(248, 113, 113, 0.4);
            background: rgba(248, 113, 113, 0.1);
        }
        .flash-ok {
            color: #a7f3d0;
            border: 1px solid rgba(52, 211, 153, 0.4);
            background: rgba(52, 211, 153, 0.12);
        }
        form {
            margin: 2.75rem 0 0;
            display: flex;
            flex-direction: column;
            gap: 1.75rem;
        }
        .block {
            position: relative;
            padding: 1.75rem 1.5rem 1.85rem;
            background: var(--surface);
            border: 1px solid var(--line);
            border-left: 3px solid rgba(52, 211, 153, 0.55);
        }
        .block-head {
            display: flex;
            align-items: flex-start;
            gap: 1rem;
            margin: 0 0 1.5rem;
            padding-bottom: 1.25rem;
            border-bottom: 1px solid var(--line);
        }
        .block-step {
            flex-shrink: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 2rem;
            height: 2rem;
            margin-top: 0.1rem;
            border: 1px solid rgba(52, 211, 153, 0.45);
            background: var(--accent-soft);
            color: var(--accent);
            font-size: 0.8rem;
            font-weight: 800;
            letter-spacing: 0.02em;
        }
        .block-titles { min-width: 0; }
        .block h2 {
            margin: 0;
            font-size: 1.15rem;
            font-weight: 800;
            letter-spacing: -0.02em;
            line-height: 1.25;
            color: #fff;
            text-transform: none;
        }
        .block-head p {
            margin: 0.4rem 0 0;
            font-size: 0.9rem;
            font-weight: 500;
            line-height: 1.5;
            color: var(--muted);
        }
        .q {
            margin: 0;
            padding: 1.35rem 0 0;
            border: 0;
        }
        .q + .q {
            margin-top: 1.35rem;
            padding-top: 1.35rem;
            border-top: 1px dashed var(--line);
        }
        .q-label {
            margin: 0;
            padding: 0;
            font-size: 1.05rem;
            font-weight: 700;
            color: #fff;
        }
        .q-help {
            margin: 0.45rem 0 0;
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--dim);
            line-height: 1.5;
        }
        .scale-legend {
            display: flex;
            justify-content: space-between;
            gap: 0.75rem;
            margin: 0 0 0.35rem;
            font-size: 0.72rem;
            font-weight: 600;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            color: var(--dim);
        }
        .rating {
            display: grid;
            grid-template-columns: repeat(5, minmax(0, 1fr));
            gap: 0.55rem;
            margin-top: 0.85rem;
        }
        .rating label {
            position: relative;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 0.35rem;
            min-height: 4.5rem;
            padding: 0.65rem 0.4rem;
            border: 1px solid var(--line-strong);
            background: var(--panel);
            cursor: pointer;
            text-align: center;
            transition: border-color 0.15s ease, background-color 0.15s ease, transform 0.15s ease;
        }
        .rating input {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }
        .rating .score {
            font-size: 1.25rem;
            font-weight: 800;
            line-height: 1;
            color: #fff;
        }
        .rating .caption {
            font-size: 0.65rem;
            font-weight: 600;
            letter-spacing: 0.01em;
            line-height: 1.3;
            color: var(--dim);
        }
        .rating label:hover,
        .rating label:has(input:focus-visible) {
            border-color: rgba(52, 211, 153, 0.65);
            background: var(--panel-hover);
        }
        .rating label:has(input:checked) {
            border-color: var(--accent);
            background: var(--accent-soft);
            box-shadow: inset 0 0 0 1px rgba(52, 211, 153, 0.25);
        }
        .rating label:has(input:checked) .score { color: #ecfdf5; }
        .rating label:has(input:checked) .caption { color: #a7f3d0; }
        .checks {
            display: flex;
            flex-direction: column;
            gap: 0.65rem;
        }
        .checks label {
            display: flex;
            align-items: flex-start;
            gap: 0.85rem;
            padding: 1rem 1.05rem;
            border: 1px solid var(--line-strong);
            background: var(--panel);
            cursor: pointer;
            font-size: 0.95rem;
            font-weight: 500;
            line-height: 1.45;
            color: var(--muted);
            transition: border-color 0.15s ease, background-color 0.15s ease, color 0.15s ease;
        }
        .checks label:hover,
        .checks label:has(input:focus-visible) {
            border-color: rgba(52, 211, 153, 0.5);
            background: var(--panel-hover);
            color: #ecfdf5;
        }
        .checks label:has(input:checked) {
            border-color: var(--accent);
            background: var(--accent-soft);
            color: #ecfdf5;
            box-shadow: inset 0 0 0 1px rgba(52, 211, 153, 0.2);
        }
        .checks input {
            margin-top: 0.15rem;
            width: 1.1rem;
            height: 1.1rem;
            accent-color: var(--accent);
            flex-shrink: 0;
        }
        .field-label {
            display: block;
            margin-top: 0.25rem;
            font-size: 0.72rem;
            font-weight: 700;
            letter-spacing: 0.16em;
            text-transform: uppercase;
            color: var(--dim);
        }
        textarea,
        input[type="email"] {
            display: block;
            width: 100%;
            margin-top: 0.75rem;
            padding: 1rem 1.05rem;
            border: 1px solid var(--line-strong);
            background: var(--panel);
            color: #fff;
            font-family: var(--font);
            font-size: 1rem;
            font-weight: 500;
            line-height: 1.55;
            resize: vertical;
            transition: border-color 0.15s ease, background-color 0.15s ease;
        }
        textarea:hover,
        input[type="email"]:hover {
            background: var(--panel-hover);
        }
        textarea:focus,
        input[type="email"]:focus {
            outline: none;
            border-color: var(--accent);
            background: #101614;
        }
        textarea::placeholder,
        input[type="email"]::placeholder {
            color: rgba(244, 244, 240, 0.3);
        }
        .hp {
            position: absolute;
            left: -9999px;
            opacity: 0;
            height: 0;
            width: 0;
            overflow: hidden;
        }
        .actions {
            margin-top: 0.5rem;
            padding-top: 0.5rem;
        }
        .submit {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            min-height: 3.25rem;
            padding: 0.95rem 1.5rem;
            border: 0;
            background: var(--accent);
            color: #052e1c;
            font-family: var(--font);
            font-size: 0.72rem;
            font-weight: 800;
            letter-spacing: 0.18em;
            text-transform: uppercase;
            cursor: pointer;
            transition: background-color 0.2s ease, transform 0.15s ease;
        }
        .submit:hover { background: #6ee7b7; }
        .submit:active { transform: translateY(1px); }
        .submit:disabled {
            opacity: 0.45;
            cursor: not-allowed;
            transform: none;
        }
        .actions .hint {
            margin: 0.9rem 0 0;
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--dim);
            text-align: center;
            line-height: 1.45;
        }
        .foot {
            margin: 2.75rem 0 0;
            font-size: 0.65rem;
            font-weight: 600;
            letter-spacing: 0.18em;
            text-transform: uppercase;
            color: var(--dim);
            text-align: center;
        }
        @media (max-width: 640px) {
            body { padding: 2rem 1rem 3.25rem; }
            .block {
                padding: 1.4rem 1.1rem 1.5rem;
            }
            .block-head {
                gap: 0.85rem;
                margin-bottom: 1.25rem;
                padding-bottom: 1rem;
            }
            .block h2 { font-size: 1.05rem; }
            .scale-legend { display: none; }
            .rating {
                grid-template-columns: 1fr;
                gap: 0.5rem;
            }
            .rating label {
                flex-direction: row;
                justify-content: flex-start;
                gap: 0.9rem;
                min-height: 0;
                padding: 0.85rem 1rem;
                text-align: left;
            }
            .rating .score {
                width: 1.5rem;
                text-align: center;
            }
            .rating .caption { margin-top: 0; font-size: 0.8rem; }
        }
    </style>
</head>
<body>
    <main class="wrap">
        <header class="hero">
            <p class="kicker">TTRD.FR · Démonstration</p>
            <h1>Votre avis<span>.</span></h1>
            <p class="lead">
                Quelques questions sur l’expérience d’utilisation. Vos réponses aident à améliorer les écrans et le parcours.
            </p>
        </header>

        <?php if ($success !== null && $success !== ''): ?>
            <p class="flash flash-ok" role="status"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></p>
        <?php endif; ?>

        <?php if ($error !== null && $error !== ''): ?>
            <p class="flash flash-error" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
        <?php endif; ?>

        <?php if ($success === null || $success === ''): ?>
        <form method="post" action="<?= htmlspecialchars($action, ENT_QUOTES, 'UTF-8') ?>" novalidate>
            <?= \App\Core\Csrf::field() ?>
            <div class="hp" aria-hidden="true">
                <label for="company_website">Ne pas remplir</label>
                <input type="text" id="company_website" name="company_website" tabindex="-1" autocomplete="off">
            </div>

            <section class="block" aria-labelledby="sec-ratings">
                <div class="block-head">
                    <span class="block-step" aria-hidden="true">1</span>
                    <div class="block-titles">
                        <h2 id="sec-ratings">Appréciation</h2>
                        <p>Choisissez une note pour chaque critère.</p>
                    </div>
                </div>
                <p class="scale-legend" aria-hidden="true">
                    <span>1 · Faible</span>
                    <span>5 · Excellent</span>
                </p>
                <?php foreach ($ratingQuestions as $name => $q): ?>
                    <fieldset class="q">
                        <legend class="q-label"><?= htmlspecialchars($q['label'], ENT_QUOTES, 'UTF-8') ?></legend>
                        <p class="q-help"><?= htmlspecialchars($q['help'], ENT_QUOTES, 'UTF-8') ?></p>
                        <div class="rating" role="radiogroup" aria-label="<?= htmlspecialchars($q['label'], ENT_QUOTES, 'UTF-8') ?>">
                            <?php foreach ($ratings as $value => $caption): ?>
                                <label>
                                    <input
                                        type="radio"
                                        name="<?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>"
                                        value="<?= htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8') ?>"
                                        <?= (string) $q['value'] === (string) $value ? 'checked' : '' ?>
                                        required
                                    >
                                    <span class="score"><?= htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8') ?></span>
                                    <span class="caption"><?= htmlspecialchars($caption, ENT_QUOTES, 'UTF-8') ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </fieldset>
                <?php endforeach; ?>
            </section>

            <section class="block" aria-labelledby="sec-highlights">
                <div class="block-head">
                    <span class="block-step" aria-hidden="true">2</span>
                    <div class="block-titles">
                        <h2 id="sec-highlights">Ce qui a bien fonctionné</h2>
                        <p>Cochez tout ce qui vous a paru réussi (optionnel).</p>
                    </div>
                </div>
                <div class="checks">
                    <?php foreach ($highlights as $value => $label): ?>
                        <label>
                            <input
                                type="checkbox"
                                name="highlights[]"
                                value="<?= htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8') ?>"
                                <?= in_array((string) $value, $oldHighlights, true) ? 'checked' : '' ?>
                            >
                            <span><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="block" aria-labelledby="sec-frictions">
                <div class="block-head">
                    <span class="block-step" aria-hidden="true">3</span>
                    <div class="block-titles">
                        <h2 id="sec-frictions">Ce qui a gêné</h2>
                        <p>Cochez les points qui ont freiné ou embrouillé (optionnel).</p>
                    </div>
                </div>
                <div class="checks">
                    <?php foreach ($frictions as $value => $label): ?>
                        <label>
                            <input
                                type="checkbox"
                                name="frictions[]"
                                value="<?= htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8') ?>"
                                <?= in_array((string) $value, $oldFrictions, true) ? 'checked' : '' ?>
                            >
                            <span><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="block" aria-labelledby="sec-ideas">
                <div class="block-head">
                    <span class="block-step" aria-hidden="true">4</span>
                    <div class="block-titles">
                        <h2 id="sec-ideas">Vos idées</h2>
                        <p>Fonctionnalités manquantes, détails à retravailler, priorités… tout est utile.</p>
                    </div>
                </div>
                <label class="field-label" for="ideas">Suggestions libres</label>
                <textarea
                    id="ideas"
                    name="ideas"
                    rows="6"
                    maxlength="4000"
                    placeholder="Par exemple : un écran trop dense, un bouton mal placé, une idée de parcours…"
                ><?= htmlspecialchars($oldIdeas, ENT_QUOTES, 'UTF-8') ?></textarea>
            </section>

            <section class="block" aria-labelledby="sec-contact">
                <div class="block-head">
                    <span class="block-step" aria-hidden="true">5</span>
                    <div class="block-titles">
                        <h2 id="sec-contact">Recontact</h2>
                        <p>Optionnel — si vous acceptez un échange court sur votre retour.</p>
                    </div>
                </div>
                <div class="checks">
                    <label>
                        <input type="checkbox" name="contact_ok" value="1" <?= $oldContactOk ? 'checked' : '' ?>>
                        <span>Oui, vous pouvez me recontacter à propos de ce retour</span>
                    </label>
                </div>
                <label class="field-label" for="contact_email" style="margin-top:1.35rem;">Adresse e-mail</label>
                <input
                    type="email"
                    id="contact_email"
                    name="contact_email"
                    maxlength="190"
                    autocomplete="email"
                    placeholder="vous@exemple.fr"
                    value="<?= htmlspecialchars($oldEmail, ENT_QUOTES, 'UTF-8') ?>"
                >
            </section>

            <div class="actions">
                <button
                    type="submit"
                    class="submit"
                    <?= $inboxConfigured ? '' : 'disabled' ?>
                ><?= $inboxConfigured ? 'Envoyer mon retour' : 'Envoi indisponible' ?></button>
                <?php if (!$inboxConfigured): ?>
                    <p class="hint">L’envoi en ligne n’est pas configuré. Contactez directement TTRD.FR.</p>
                <?php endif; ?>
            </div>
        </form>
        <?php endif; ?>

        <p class="foot">Démonstration · <?= htmlspecialchars($brand, ENT_QUOTES, 'UTF-8') ?> · TTRD.FR</p>
    </main>
</body>
</html>
