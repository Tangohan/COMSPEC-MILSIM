<?php
/**
 * Guide écrit de l'éditeur de plans (/atak/toc/plans/aide) : docs/TOC-PLANS-TUTORIEL.md rendu dans le thème du TOC.
 *
 * @var string $title
 * @var string $tocHelpHtml
 */
$h = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$a = static fn (string $p): string => function_exists('asset_url') ? asset_url($p) : url($p);
?>
<!DOCTYPE html>
<html lang="fr" data-theme="dark">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">
<title><?= $h($title ?? 'Éditeur de plans : guide') ?> · Athena</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Roboto+Condensed:wght@400;700&family=JetBrains+Mono:wght@400;500;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= $h($a('assets/css/atak-toc.css')) ?>">
<link rel="stylesheet" href="<?= $h($a('assets/css/atak-toc-plan-help.css')) ?>">
</head>
<body class="toc-body" style="overflow:auto">
<main class="plans-wrap pe-guide">
  <p><a class="toc-btn is-small" href="<?= $h(url('atak/toc/plans')) ?>">Revenir aux plans</a></p>
  <?= $tocHelpHtml ?>
</main>
</body>
</html>
