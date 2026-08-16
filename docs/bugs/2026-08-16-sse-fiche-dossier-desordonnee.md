# Fiche dossier : ordre incohérent et lecture polluée

## Contexte

Portail SSE, fiche d'un dossier (`/atak/sse/dossiers/{id}`).

## Symptôme

La page se lisait mal : les blocs n'étaient pas dans un ordre compréhensible, le
lien « Retour aux dossiers » tombait au milieu de la page, la carte tactique
occupait une place de choix avant même les identités et les pièces, et chaque
section mêlait la lecture du dossier aux champs de saisie.

## Cause

Ajouts successifs sans reprise de l'ensemble. Les numéros de panneau le montraient :
ils se suivaient dans l'ordre 01.09, 01.10, 01.14, 01.11, 01.12, 01.13, 01.05, 01.06.
Les pièces à conviction ne montraient qu'un lien « Voir l'image » alors qu'une pièce
se regarde, les dates s'affichaient au format brut de la base, et les actions du
dossier (compte rendu, corrélations, version expurgée, export) étaient éparpillées
entre l'en-tête et un panneau de bas de page.

## Correctif

- Ordre de lecture refait et numérotation remise en séquence : chemise (01.01),
  synthèse (01.02), identités (01.03), pièces (01.04), sites (01.05), notes (01.06),
  carte tactique (01.07), retour en pied de page.
- Actions du dossier regroupées en un seul bloc dans l'en-tête, sous un intitulé
  explicite.
- Formulaires d'ajout repliés (`.sse-fold`) : lire un dossier ne se fait plus au
  milieu des champs, et l'ajout reste à un clic. Le formulaire de rattachement
  d'identité s'ouvre d'office quand aucune identité n'est rattachée.
- Pièces versées présentées en vignettes avec aperçu de l'image, libellé, précision
  et date de versement.
- Identités rattachées cliquables vers leur fiche.
- Dates affichées en clair (« 15/08/2026 à 10h12 ») au lieu du format brut.
- États vides réécrits pour dire ce qui manque et pourquoi ça compte.
- Libellés de formulaire remis en langage métier : « Qui a le droit de le lire »
  plutôt que « Classification », « Où en est le dossier » plutôt que « Statut ».
- Styles en ligne supprimés au profit de classes.

## Fichiers touchés

- `views/atak/sse/case_show.php`
- `public/assets/css/sse_portal.css`
- `views/atak/sse/_layout.php` (version de la feuille de style)

## Vérification

- `php -l` sur les fichiers modifiés.
- Rendu vérifié au navigateur sur un dossier renseigné, un dossier vide et le mode
  lecture seule.

## Statut

Corrigé.
