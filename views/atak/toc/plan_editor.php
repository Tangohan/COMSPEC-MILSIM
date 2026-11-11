<?php
/**
 * Éditeur de plan d'opération (/atak/toc/plans/{id}) et lecture par lien (/atak/toc/plans/lien/{jeton}).
 *
 * @var int $tocPlanId
 * @var bool $tocReadOnlyLink
 * @var array<string, mixed>|null $tocSharedDoc
 */
$h = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$a = static fn (string $p): string => function_exists('asset_url') ? asset_url($p) : url($p);
$tocExtraBoot = ['planId' => (int) $tocPlanId, 'readOnlyLink' => !empty($tocReadOnlyLink), 'sharedDoc' => $tocSharedDoc ?? null];
$tocKeys = [
    ['?', 'Cette aide'], ['V · H · M', 'Sélection · main · mesure'], ['U · T · I', 'Unité · texte · repère'], ['L · A', 'Trait · flèche'],
    ['P · R · E · C', 'Polygone · rectangle · ellipse · cercle'], ['Entrée · double-clic', 'Terminer un trait ou une zone'], ['Retour arrière', 'Retirer le dernier point'],
    ['Échap', 'Annuler le dessin, désélectionner, fermer'], ['Suppr', 'Supprimer la sélection'], ['Ctrl + Z · Ctrl + Y', 'Annuler · rétablir'],
    ['Ctrl + C · Ctrl + V · Ctrl + D', 'Copier · coller · dupliquer'], ['Ctrl + A', 'Tout choisir'], ['Ctrl + G · Ctrl + Maj + G', 'Grouper · dégrouper'],
    ['Ctrl + ] · Ctrl + [', 'Premier plan · arrière-plan'], ['[ · ]', 'Pivoter de 15°'], ['+ · −', 'Agrandir · réduire'], ['Flèches (Maj)', 'Décaler de 10 m (100 m)'],
    ['K', 'Verrouiller'], ['S', 'Aimantation aux sommets'], ['G', 'Quadrillage'], ['B', 'Fond suivant'], ['J', 'Calques du jeu'],
    ['Page préc. · Page suiv.', 'Phase précédente · suivante'], ['← → (briefing)', 'Phase précédente · suivante'],
];
$tocHelpTitle = 'Éditeur de plans : raccourcis';
?>
<!DOCTYPE html>
<html lang="fr" data-theme="dark">
<head>
<?php require __DIR__ . '/_head.php'; ?>
<script src="<?= $h($a('assets/js/toc/toc-plan-editor.js')) ?>" defer></script>
</head>
<body class="toc-body">
<div class="plan">
  <header class="toc-top">
    <div class="toc-post">
      <label class="toc-sr" for="plan-title">Titre du plan</label>
      <input id="plan-title" class="plan-title" maxlength="120" value="<?= $h($title ?? '') ?>">
      <p><span id="plan-terrain"></span> · <span id="plan-save" role="status">Chargement…</span> · <span id="plan-presence"></span></p>
    </div>
    <div></div>
    <div class="toc-actions">
      <button type="button" class="toc-btn" data-top="undo" data-needs-edit title="Annuler (Ctrl+Z)">Annuler</button>
      <button type="button" class="toc-btn" data-top="redo" data-needs-edit title="Rétablir (Ctrl+Y)">Rétablir</button>
      <button type="button" class="toc-btn is-primary" data-top="publish" data-needs-api title="Poser le plan dans l’ATAK en jeu (plan partagé du camp)">Envoyer en jeu</button>
      <button type="button" class="toc-btn" data-top="import" data-needs-edit title="Plan du camp dessiné en jeu">Importer du jeu</button>
      <button type="button" class="toc-btn" data-top="versions" data-needs-api>Versions</button>
      <button type="button" class="toc-btn" data-top="share" data-needs-admin>Partage</button>
      <details class="toc-menu">
        <summary class="toc-btn">Exporter</summary>
        <div class="toc-menu-list">
          <button type="button" class="toc-btn" data-top="png">Image PNG (vue affichée)</button>
          <button type="button" class="toc-btn" data-top="pdf" data-needs-api>Fiche de briefing PDF</button>
          <a class="toc-btn" data-needs-api href="<?= $h(url('atak/toc/plans/' . (int) $tocPlanId . '/export.json')) ?>">Fichier JSON (échange)</a>
        </div>
      </details>
      <button type="button" class="toc-btn" data-top="help" aria-label="Aide et raccourcis clavier">?</button>
      <a class="toc-btn" href="<?= $h(url('atak/toc/plans')) ?>">Fermer</a>
    </div>
  </header>
  <main class="plan-main">
    <aside class="plan-tools" id="plan-tools" aria-label="Outils de dessin"></aside>
    <section class="toc-left" aria-label="Carte du plan">
      <div class="toc-toolbar">
        <button type="button" class="toc-btn" data-top="layers" title="Calques du jeu (J)">Calques du jeu</button>
        <label class="toc-sr" for="plan-bg">Fond de carte</label>
        <select id="plan-bg" class="toc-select" title="Fond de carte (B)"></select>
        <span class="toc-muted" id="plan-bg-note"></span>
      </div>
      <div class="toc-mapwrap">
        <div class="toc-map" id="plan-map" aria-label="Carte du terrain"></div>
        <div class="toc-layers" id="plan-game-layers" hidden aria-label="Calques du jeu"></div>
        <div class="toc-banner plan-readonly" id="plan-ro" hidden></div>
        <div class="plan-brief" id="plan-brief-card" hidden aria-live="polite"></div>
        <div class="toc-loading" id="plan-loading">Chargement du plan…</div>
      </div>
      <div class="toc-help"><span>Grille <b id="plan-cursor">----</b></span><span id="plan-hint"></span><b id="plan-measure"></b><span id="plan-msg" class="plan-msg" role="status" aria-live="polite"></span></div>
    </section>
    <aside class="plan-side" aria-label="Réglages du plan">
      <nav class="plan-side-tabs" id="plan-side-tabs" role="tablist" aria-label="Panneaux du plan"></nav>
      <div class="plan-side-body" id="plan-side"></div>
    </aside>
  </main>
  <div class="plan-phases" id="plan-phases" aria-label="Phases du plan"></div>
</div>

<div class="toc-overlay" id="dlg-publish" hidden role="dialog" aria-modal="true" aria-labelledby="pub-title">
  <div class="toc-dialog">
    <div class="toc-sec"><h2 id="pub-title">Envoyer en jeu</h2><span><button type="button" class="toc-btn is-small" data-close>Fermer</button></span></div>
    <p>Le plan est posé dans le plan partagé du camp (app Plan des téléphones, carte du PC du poste et écrans de la salle). Seul le plan part : aucune position d’unité.</p>
    <div class="toc-form"><div class="toc-row" style="--cols:2">
      <label>Camp<select class="toc-select" id="pub-side"><option value="WEST">BLUFOR</option><option value="EAST">OPFOR</option><option value="GUER">INDFOR</option><option value="CIV">CIVILS</option></select></label>
      <label>Contenu<select class="toc-select" id="pub-phase"></select></label>
    </div></div>
    <p class="toc-note" id="pub-info"></p>
    <p class="toc-note">Un nouvel envoi remplace en jeu les éléments déjà envoyés par ce plan. Le jeu relève l’envoi dans les secondes qui suivent s’il est en liaison avec Athena, sinon à sa prochaine liaison (30 min au plus).</p>
    <button type="button" class="toc-btn is-primary is-block" id="pub-go">Envoyer</button>
  </div>
</div>
<div class="toc-overlay" id="dlg-import" hidden role="dialog" aria-modal="true" aria-labelledby="imp-title">
  <div class="toc-dialog">
    <div class="toc-sec"><h2 id="imp-title">Importer depuis le jeu</h2><span><button type="button" class="toc-btn is-small" data-close>Fermer</button></span></div>
    <p>Le plan du camp dessiné en jeu (app Plan du téléphone, outil PLAN du PC du poste), tel que les téléphones reliés l’ont envoyé à Athena.</p>
    <div class="toc-form"><label>Camp<select class="toc-select" id="imp-side"><option value="WEST">BLUFOR</option><option value="EAST">OPFOR</option><option value="GUER">INDFOR</option><option value="CIV">CIVILS</option></select></label></div>
    <p class="toc-note" id="imp-info"></p>
    <button type="button" class="toc-btn is-primary is-block" id="imp-go" disabled>Ajouter au plan</button>
  </div>
</div>
<div class="toc-overlay" id="dlg-versions" hidden role="dialog" aria-modal="true" aria-labelledby="ver-title">
  <div class="toc-dialog">
    <div class="toc-sec"><h2 id="ver-title">Versions</h2><span><button type="button" class="toc-btn is-small" data-close>Fermer</button></span></div>
    <div class="toc-form" data-needs-edit><div class="toc-row" style="grid-template-columns:1fr auto"><input class="toc-input" id="ver-label" maxlength="120" placeholder="Nom de la version (avant briefing, version validée…)" aria-label="Nom de la version"><button type="button" class="toc-btn is-primary" id="ver-save">Enregistrer une version</button></div></div>
    <div id="ver-list"></div>
  </div>
</div>
<div class="toc-overlay" id="dlg-share" hidden role="dialog" aria-modal="true" aria-labelledby="sh-title">
  <div class="toc-dialog">
    <div class="toc-sec"><h2 id="sh-title">Partage</h2><span><button type="button" class="toc-btn is-small" data-close>Fermer</button></span></div>
    <div class="toc-form">
      <label>Qui voit et modifie ce plan<select class="toc-select" id="sh-sharing"><option value="private">Moi seul (et les partages nominatifs)</option><option value="unit">Mon unité</option><option value="community">Toute la communauté (droit « tenir le poste TOC web »)</option></select></label>
      <label><span><input type="checkbox" id="sh-lock"> Verrouiller le plan (vous et la configuration du poste seuls le modifient)</span></label>
      <button type="button" class="toc-btn is-primary" id="sh-save">Enregistrer le partage</button>
      <label>Lien de lecture (membres connectés de la communauté)<input class="toc-input mono" id="sh-link" readonly placeholder="Pas de lien"></label>
      <div class="toc-buttons" style="--cols:3"><button type="button" class="toc-btn" id="sh-create">Créer un lien</button><button type="button" class="toc-btn" id="sh-revoke">Retirer le lien</button><button type="button" class="toc-btn" id="sh-template" hidden>Enregistrer comme modèle</button></div>
    </div>
  </div>
</div>
<?php require __DIR__ . '/_help.php'; ?>
</body>
</html>
