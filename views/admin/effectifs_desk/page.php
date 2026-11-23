<?php
declare(strict_types=1);

/**
 * Bureau des effectifs, vue plein écran « application de bureau » (modèle « Bureau effectifs — mode tableur »).
 * Page autonome : sans la navigation du back-office, avec un lien de retour. Les zones sont posées ici, le script
 * effectifs-desk.js les remplit avec les données de GET /back-office/bureau-effectifs/donnees (lecture seule).
 *
 * @var string $deskCommunity
 * @var string $deskCsrf
 * @var bool $deskCanImport
 * @var bool $deskCanCreate
 * @var bool $deskCanInvite
 * @var bool $deskCanPdf
 * @var string $deskFlashOk
 * @var string $deskFlashError
 */
$h = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$cssFile = dirname(__DIR__, 3) . '/public/assets/css/effectifs-desk.css';
$jsFile = dirname(__DIR__, 3) . '/public/assets/js/effectifs-desk.js';
$ver = (int) @filemtime($cssFile) . '.' . (int) @filemtime($jsFile);
$cfg = [
    'dataUrl' => effectifs_desk_url('donnees'),
    'csrf' => (string) $deskCsrf,
    'community' => (string) $deskCommunity,
    'canImport' => (bool) $deskCanImport,
    'canCreate' => (bool) $deskCanCreate,
    'canInvite' => (bool) $deskCanInvite,
    'canPdf' => (bool) $deskCanPdf,
    'flashOk' => (string) $deskFlashOk,
    'flashError' => (string) $deskFlashError,
    'urls' => [
        'back' => url('back-office'),
        'classic' => effectifs_workspace_url(),
        'newMember' => effectifs_workspace_url('nouveau'),
        'noAccount' => effectifs_workspace_url('sans-comptes'),
        'invitations' => effectifs_workspace_url('invitations'),
        'exportCsv' => effectifs_workspace_url('export'),
        'exportPdf' => effectifs_workspace_url('export.pdf'),
        'template' => effectifs_workspace_url('modele-tableur'),
        'import' => effectifs_workspace_url('importer'),
        'inviteBase' => effectifs_workspace_url('membres'),
        'orgChart' => effectifs_workspace_url('chaine'),
        'roles' => effectifs_workspace_url('roles'),
        'assignments' => effectifs_workspace_url('affectations'),
    ],
];
?>
<!DOCTYPE html>
<html lang="<?= $h(function_exists('html_lang') ? html_lang() : 'fr') ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex">
  <title>Bureau des effectifs | Athena</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Archivo:wght@400;600;700;800;900&amp;family=JetBrains+Mono:wght@400;700&amp;display=swap">
  <link rel="stylesheet" href="<?= $h(url('assets/css/effectifs-desk.css')) ?>?v=<?= $h($ver) ?>">
</head>
<body class="ed-body">
<div class="ed-app" id="ed-app" tabindex="-1" data-ed-config="<?= $h((string) json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP)) ?>">

  <header class="ed-titlebar" data-zone="titlebar">
    <span class="ed-logo" aria-hidden="true">A</span>
    <span class="ed-brand">Athena<span class="ed-brand__dot">.</span></span>
    <span class="ed-sep" aria-hidden="true"></span>
    <h1 class="ed-doc"><?= $h($deskCommunity) ?> · Bureau des effectifs</h1>
    <div class="ed-seg" role="group" aria-label="Périmètre">
      <button type="button" data-act="scope" data-v="unit" aria-pressed="false">Bureau d’unité</button>
      <button type="button" data-act="scope" data-v="org" aria-pressed="true">Organisation · gestion complète</button>
    </div>
    <span class="ed-spacer"></span>
    <button type="button" class="ed-searchbtn" data-act="palette">
      <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.2-3.2"/></svg>
      Rechercher…<span class="ed-searchbtn__key">Ctrl K</span>
    </button>
    <button type="button" class="ed-net" data-act="toggle-net" data-zone="net"><span class="ed-net__dot" aria-hidden="true"></span><span data-net-label>En ligne</span></button>
    <button type="button" class="ed-net" data-act="sync" data-zone="sync" title="Synchroniser maintenant (F5)">
      <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" aria-hidden="true"><path d="M21 12a9 9 0 1 1-3-6.7L21 8"/><path d="M21 3v5h-5"/></svg>
      <span data-sync-label>Synchro</span>
    </button>
    <a class="ed-net ed-back" href="<?= $h(url('back-office')) ?>">← Retour au back-office</a>
    <span class="ed-sweep" data-zone="sweep" hidden><span></span></span>
  </header>

  <nav class="ed-menubar" data-zone="menubar" aria-label="Menus"></nav>
  <div class="ed-toolbar" data-zone="toolbar" role="toolbar" aria-label="Outils du classeur"></div>
  <div class="ed-shortcuts" data-zone="shortcuts" role="group" aria-label="Raccourcis de tableaux"></div>
  <div class="ed-cards" data-zone="cards"></div>
  <div class="ed-filters" data-zone="filters" role="group" aria-label="Filtres du tableau"></div>
  <div class="ed-formula" data-zone="formula"></div>

  <div class="ed-work">
    <nav class="ed-tree" data-zone="tree" aria-label="Structure de l’organisation"></nav>
    <main class="ed-main" data-zone="main" id="ed-main"></main>
    <aside class="ed-dossier" data-zone="dossier" aria-label="Dossier du membre sélectionné"></aside>
  </div>

  <footer class="ed-footer" data-zone="footer"></footer>

  <div data-zone="overlay"></div>
  <div class="ed-toast" data-zone="toast" role="status" aria-live="polite" hidden></div>
  <div class="ed-boot" data-zone="boot" role="status" aria-label="Chargement du classeur">
    <div class="ed-boot__in">
      <div class="ed-boot__head">
        <svg class="ed-spin" width="40" height="40" viewBox="0 0 40 40" aria-hidden="true"><circle cx="20" cy="20" r="16" fill="none" stroke="#161b1e" stroke-width="3"/><path d="M20 4a16 16 0 0 1 16 16" fill="none" stroke="#12d18e" stroke-width="3" stroke-linecap="round"/></svg>
        <div><span class="ed-boot__logo">Athena<span class="ed-brand__dot">.</span></span><span class="ed-boot__sub">Classeur des effectifs</span></div>
      </div>
      <div class="ed-boot__bar"><div data-boot-bar style="width:0%"></div></div>
      <div class="ed-boot__steps" data-boot-steps></div>
    </div>
  </div>

  <?php if ($deskCanImport): ?>
  <form class="ed-import" method="post" action="<?= $h(effectifs_workspace_url('importer')) ?>" enctype="multipart/form-data" hidden>
    <input type="hidden" name="_csrf_token" value="<?= $h($deskCsrf) ?>">
    <input type="hidden" name="return_to" value="bureau-effectifs">
    <input type="file" name="roster_file" accept=".csv,text/csv" aria-label="Fichier CSV à importer" data-import-file>
  </form>
  <?php endif; ?>
  <noscript><p class="ed-noscript">Le Bureau des effectifs demande JavaScript. <a href="<?= $h(effectifs_workspace_url()) ?>">Ouvrir la liste des membres</a>.</p></noscript>
</div>
<script src="<?= $h(url('assets/js/effectifs-desk.js')) ?>?v=<?= $h($ver) ?>" defer></script>
</body>
</html>
