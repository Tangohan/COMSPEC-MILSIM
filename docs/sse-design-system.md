# Système de design du portail SSE

Référence visuelle du Bureau SSE, issue du projet « Athena Design System » (groupe SSE). Il vit dans trois fichiers :

| Fichier | Rôle |
|---|---|
| `public/assets/css/sse-ds.css` | Jetons `--sse-*`, alias de compatibilité, coque du portail, composants `.sse-*`, fenêtres |
| `public/assets/js/sse-ds.js` | Fenêtres `<dialog>`, tiroir, onglets, liste filtrable, sélection de lignes (sans dépendance, sans texte) |
| `public/assets/css/sse_notify.css` | Messages du portail = toasts `.sse-toast`, branchés sur les jetons |

`views/atak/sse/_layout.php` charge `sse-ds.css` **après** `sse_ui.css` (puis `sse_notify.css`) et `sse-ds.js` après `sse-ui.js`.
Page de référence : `views/atak/sse/partials/document_presentation_body.php` (« Présentation des documents », menu Production).

## Polices

Archivo (texte) et JetBrains Mono (identifiants, codes, heures), toutes deux sous licence OFL, auto-hébergées :
`public/assets/fonts/archivo/archivo-latin.woff2` (+ `archivo-OFL.txt`) et `public/assets/fonts/nine-line/jetbrains-mono-latin.woff2`.
Le layout les précharge et déclare les `@font-face` en ligne avec la même URL (un seul téléchargement). La CSP du site
(`SecurityHeadersMiddleware`) autorise déjà `font-src 'self'` : aucune ouverture vers Google Fonts. Les feuilles qui écrivent encore
`"Inter"` reçoivent Archivo (alias `@font-face` sur le même fichier).

## Jetons

| Jeton | Valeur | Usage |
|---|---|---|
| `--sse-bg` `--sse-surface` `--sse-sunken` `--sse-raised` `--sse-hover` | `#0a0d0f` `#11161a` `#0d1114` `#171d22` `#151b20` | fond de page, panneaux, champs, ligne survolée |
| `--sse-line` `--sse-line-soft` `--sse-line-strong` | `#232b31` `#1a2126` `#36414a` | bordures |
| `--sse-text` `--sse-strong` `--sse-muted` `--sse-faint` | `#d3dbe0` `#fff` `#8a979e` `#5f6b72` | textes |
| `--sse-accent` `--sse-accent-ink` | `#12d18e` `#04231a` | **un seul** accent vert, et le texte posé dessus |
| `--sse-ok/info/warn/bad/neut` (+ `-bg`, `-bd`) | voir le fichier | les cinq tons de statut |
| `--sse-cls-u/cui/c/s/ts` | vert, violet, bleu, rouge, orange | classification US |
| `--sse-class` | `#8f1d1d` | bandeau d'habilitation |
| `--sse-font` `--sse-font-mono` | Archivo, JetBrains Mono | polices |
| `--sse-radius` `--sse-pad` `--sse-gap` `--sse-row` | `6px` `24px` `16px` `64px` | rayon, marge, écart, hauteur de ligne |

`[data-tone="ok|info|warn|bad|neut"]` pose `--t`, `--t-bg`, `--t-bd` pour `status`, `level`, `alert`, `hint`.

### Alias : les anciennes pages adoptent le système d'un coup

Sous `body.sse-iw`, les variables historiques pointent vers les jetons : `--bg --surface --surface-2 --surface-3 --border --line --text
--text-strong --muted --accent --green --amber --red --blue --sans --mono --font` (sse_portal / sse_workspace), `--sx-*` (sse_shell),
`--ui-*` (sse_ui), `--nt-*` (sse_notify), `--fx-*` (kit Fichiers). Le fond, les couleurs, la police et les marges de la coque
(barre du haut, bandeau, menu, titres, tuiles `.metric`, panneaux, tableaux, boutons, champs, pastilles `.badge`, états vides)
suivent. Le `h2` historique (capitales vertes à chasse fixe) devient un vrai titre (`:where()` : un composant qui règle son h2 gagne).

## Composants

`btn` (`--primary`, `--danger`, `--danger-solid`, `--ghost`, `--sm`, `--quiet`), `input`, `field`, `option`, `seg`, `tabs` / `tab` / `count`,
`nav`, `panel`, `tiles` / `tile`, `table` (`--cards`), `status`, `tag` (type, neutre), `level` (N1 à N4), `classif` (classification US),
`grade`, `lock`, `alert`, `bulk`, `banner`, `crumbs`, `empty`, `skeleton`, `modal`, `drawer`, `toast`.
**Pas de `.sse-overline`** : aucun petit libellé en capitales au-dessus d'un titre, aucune numérotation « 01 » devant un titre.

### Niveaux

```html
<span class="sse-level" data-level="3">N3 · Confidentiel</span>            <!-- N1 neutre, N2 info, N3 warn, N4 bad -->
<span class="sse-classif" data-classif="top-secret">TOP SECRET</span>       <!-- unclassified | cui | confidential | secret | top-secret -->
<span class="sse-status" data-tone="ok">En service</span>                   <!-- point de couleur + libellé -->
<span class="sse-tag">Papier propre (bureau)</span>                         <!-- type : étiquette neutre -->
```

### File de traitement (ticketing)

```html
<section class="sse-panel" data-sse-list data-sse-page-size="50">
  <div class="sse-tabs" data-sse-tabs data-sse-hash role="tablist" aria-label="…">
    <button class="sse-tab" role="tab" data-sse-tab="all" aria-selected="true">Tous <span class="sse-count">40</span></button>
    <button class="sse-tab" role="tab" data-sse-tab="actif" aria-selected="false" tabindex="-1">En service <span class="sse-count">1</span></button>
  </div>
  <div class="sse-toolbar">
    <div class="sse-search"><svg …/><input class="sse-input" type="search" data-sse-search></div>
    <select class="sse-input sse-input--select" data-sse-filter="style">…</select>
    <p class="sse-toolbar__meta" aria-live="polite"><span data-sse-count data-template="%1 / %2"></span></p>
  </div>
  <div class="sse-table-wrap">
    <table class="sse-table sse-table--cards" data-sse-drawer="#tiroir">
      <thead><tr><th data-sse-sort="name">Nom</th>…</tr></thead>
      <tbody>
        <tr data-sse-row data-sse-item="code" data-groups="all actif" data-style="clean" data-search="texte cherché" data-sort-name="…">
          <td class="sse-main" data-label="Nom"><a class="sse-rowlink" href="…">Titre</a><small>Détail</small></td>…
```

* Onglets : `data-sse-tab` filtre sur `data-groups` ; `data-sse-hash` garde l'onglet dans l'URL (`#actif`) ; flèches, Début, Fin.
* Recherche : insensible à la casse et aux accents sur `data-search`. Filtre `select[data-sse-filter="clé"]` compare `data-clé` de la ligne.
* Tri : `th[data-sse-sort="clé"]` lit `data-sort-clé` (sinon le texte de la cellule), `aria-sort` mis à jour.
* Plus de `data-sse-page-size` lignes : un bouton `[data-sse-more]` affiche la suite. Vide filtré : `[data-sse-empty-filtered]` + `[data-sse-reset]`.
* Sélection : `data-sse-select` sur le tableau, cases `.sse-tick input`, case d'en-tête `[data-sse-select-all]`, barre `[data-sse-bulk]` / `[data-sse-bulk-count]`.
* Téléphone (≤ 720 px) : `.sse-table--cards` empile chaque ligne en carte ; chaque `td` porte `data-label` ; zones tactiles de 44 px.

### Tiroir et modales

```html
<button data-sse-open="#sse-edit" data-sse-record="code">Modifier</button>      <!-- ouvre, remplit, rend le focus à la fermeture -->
<dialog class="sse-modal" id="sse-edit" data-sse-records="#donnees">             <!-- #donnees : <script type="application/json"> -->
  <form method="post" action="…">
    <div class="sse-dialog__head"><h2>Titre</h2><button type="button" class="sse-iconbtn" data-sse-close aria-label="Fermer">×</button></div>
    <div class="sse-dialog__body">…champs .sse-field…</div>
    <div class="sse-dialog__foot"><button type="button" class="sse-btn" data-sse-close>Annuler</button><button class="sse-btn sse-btn--primary">Enregistrer</button></div>
  </form>
</dialog>
```

* `<dialog>` natif (`showModal`) : focus piégé, Échap, fond ; le script ajoute le retour du focus, le clic sur le fond, le blocage du défilement.
* Remplissage depuis le bouton : `data-sse-record` + `data-sse-records` (JSON clé → champs ; clé vide = nouveau, `data-sse-mode="new|edit"`),
  `data-sse-field-<nom>="valeur"`, `data-sse-text-<clé>="texte"` vers `[data-sse-bind="<clé>"]`. `data-sse-autoopen` ouvre au chargement.
* Tiroir : `.sse-drawer` + `<template data-sse-template>` rempli depuis le JSON (`data-sse-text`, `data-sse-if`, `data-sse-unless`,
  `data-sse-attrs`, `data-sse-class-from`) ; `data-sse-open-item="clé"` sur le tableau l'ouvre au chargement.
* Messages : `window.SseNotify.error/success/warning(...)` (inchangé) ; le toast est le `.sse-note.sse-toast` du composant sse-notify.

## Règles d'usage

1. **Une page = un en-tête** (le fil d'Ariane vient du layout ; la page apporte `h1` + une ligne de description + actions), une file ticketing,
   un tiroir, des modales. Pas de quatre bandes, pas de cartes dans des cartes.
2. **Marges** : 24 px dans les panneaux, tuiles et fenêtres (16 px sur téléphone), 32 px entre blocs ; jamais de texte collé à une bordure.
3. **Texte** : 14 px courant, 13 px secondaire, **jamais sous 12 px** (hors aperçus de papier reproduisant un document).
4. **Un seul accent vert** pour l'action principale ; les autres états passent par les cinq tons.
5. **Aucun sur-titre** (« eyebrow ») et aucune numérotation devant un titre. Les libellés de colonne et de champ sont en casse normale.
6. Lignes de tableau de 56 à 64 px ; statut = point de couleur + libellé ; type = `.sse-tag` neutre.
7. Un état vide explique ce qu'est la liste et propose l'action ; un vide filtré propose de réinitialiser.
8. Le texte vit dans le HTML (traduit par `lang/en/site_auto.php`), jamais dans `sse-ds.js`.
9. Vérifier une page avec des données réalistes (noms longs, 0 / 1 / 150 lignes) à 1440, 1024 et 390 px.

## Dette connue

`.sse-modal__kicker` (modales de dossier), les titres `fx-ch h2` du kit Fichiers et quelques pages à feuille de style propre gardent des
petits libellés en capitales : à reprendre page par page avec ce socle.
