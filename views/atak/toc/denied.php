<?php
/** Accès refusé ou plan introuvable (pas de fuite : même page qu'un plan qui n'existe pas). */
$h = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$a = static fn (string $p): string => function_exists('asset_url') ? asset_url($p) : url($p);
?>
<!DOCTYPE html>
<html lang="fr" data-theme="dark">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">
<title><?= $h($title ?? 'Poste TOC') ?> · Athena</title>
<link rel="stylesheet" href="<?= $h($a('assets/css/atak-toc.css')) ?>">
</head>
<body class="toc-body" style="overflow:auto">
<main class="plans-wrap" style="max-width:640px;padding-top:15vh">
  <div class="toc-sec"><h1 style="margin:0;font:700 var(--fs-title) var(--sans);text-transform:uppercase"><?= !empty($tocMissing) ? 'Plan introuvable' : 'Poste TOC' ?></h1><span></span></div>
  <?php if (!empty($tocMissing)): ?>
    <p>Ce plan n’existe pas ou n’est pas partagé avec vous.</p>
    <a class="toc-btn" href="<?= $h(url('atak/toc/plans')) ?>">Revenir aux plans</a>
  <?php else: ?>
    <p>Le poste TOC web demande le droit « Tenir le poste TOC web », donné par l’encadrement de votre communauté (Gestion › Accès).</p>
    <a class="toc-btn" href="<?= $h(url('atak')) ?>">Ouvrir ATAK web</a>
  <?php endif; ?>
</main>
</body>
</html>
