<?php $e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); ?>
<!doctype html><html lang="fr"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>ATHENA — Patch Studio · Pro+</title>
<?php require base_path('views/patch_studio/_head.php'); ?>
</head><body class="nl-page ps-page">
<?php require base_path('views/patch_studio/_header.php'); ?>
<main class="ps-upgrade">
<section class="card"><div class="ch"><span class="num">01</span><div><h2>Une formule Pro+ minimum est requise</h2><p>Accès à l’atelier et aux ressources du Patch Studio</p></div></div><div class="cb"><p>Votre communauté ne dispose pas actuellement de l’accès au Patch Studio. Une formule Pro+ active ou offerte est nécessaire, dans le respect des permissions de votre organisation.</p><div class="ps-inline"><a class="cta" href="<?= $e(url('platform/upgrade') . '?from=patch_studio&feature=patch_studio') ?>">Consulter les formules</a><a class="btn2" href="<?= $e(url('dashboard')) ?>">Retour à ATHENA</a></div></div></section>
<?php require base_path('views/patch_studio/_disclaimer.php'); ?>
</main></body></html>
