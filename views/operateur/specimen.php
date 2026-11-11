<?php

declare(strict_types=1);

/**
 * Spécimen d'un papier du membre (OperatorHubController::documentSpecimen) : page seule, à imprimer (PDF) ou à
 * enregistrer en image. Mention SPÉCIMEN · SIMULATION sur chaque face ; photo intégrée, aucune autre requête.
 *
 * @var array<string, mixed> $doc OperatorIdentityService::documents() + photo_data
 */
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
require __DIR__ . '/_document_svg.php';
$lang = function_exists('locale') ? locale() : 'fr';
$name = 'specimen-' . preg_replace('/[^A-Za-z0-9-]/', '', (string) $doc['ref']);
?>
<!doctype html>
<html lang="<?= $h($lang) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Spécimen · <?= $h($doc['doc_type_label']) ?> · <?= $h($doc['ref']) ?></title>
    <link href="<?= $h(asset_url('assets/css/operateur-hub.css')) ?>" rel="stylesheet">
</head>
<body class="opx-specimen">
    <main class="opx-specimen__page">
        <header class="opx-specimen__head">
            <h1>Spécimen · <?= $h($doc['doc_type_label']) ?></h1>
            <p><span translate="no"><?= $h($doc['holder_name']) ?></span> · <?= $h($doc['country_name']) ?> · <span translate="no"><?= $h($doc['ref']) ?></span>. Document fictif de simulation, sans aucune valeur.</p>
            <div class="opx-specimen__acts" data-opx-specimen="<?= $h($name) ?>">
                <button type="button" class="opx-btn opx-btn--primary" data-opx-print>Imprimer ou enregistrer en PDF</button>
                <button type="button" class="opx-btn opx-btn--line" data-opx-png="recto">Image du recto (PNG)</button>
                <button type="button" class="opx-btn opx-btn--line" data-opx-png="verso">Image du verso (PNG)</button>
                <a class="opx-btn opx-btn--quiet" href="<?= $h(url('operateur/identite/documents')) ?>">Retour à mes papiers</a>
            </div>
        </header>
        <figure class="opx-specimen__face" data-opx-face="recto">
            <?= $opxDocSvg($doc, 'recto', $doc['photo_data'] ?? null) ?>
            <figcaption>Recto</figcaption>
        </figure>
        <figure class="opx-specimen__face" data-opx-face="verso">
            <?= $opxDocSvg($doc, 'verso', $doc['photo_data'] ?? null) ?>
            <figcaption>Verso</figcaption>
        </figure>
    </main>
    <script src="<?= $h(asset_url('assets/js/operateur-hub.js')) ?>" defer></script>
</body>
</html>
