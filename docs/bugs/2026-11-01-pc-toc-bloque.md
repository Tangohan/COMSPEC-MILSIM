# PC du poste TOC qui ne marche pas

## Contexte
ATAK natif 2.9.23, PC portable du poste TOC et écrans TOC (retour de TTRD : « le PC TOC ne fonctionne pas »).

## Symptôme
- Onglet GROUPES : choisir un groupe dont personne n'a d'ATAK fige le PC (panneau du groupe vide, PIP de l'écran figé).
- Sans ACE : aucune action d'écran TOC ne fait rien, dont « Ouvrir le PC du poste ».
- Avec ACE : l'objet « PC portable de TOC » posé dans l'éditeur n'a parfois aucune action pour s'ouvrir.
- Camp écrit « BLUFOR », « OPFOR » ou « INDEP » dans l'attribut Eden : le PC reste muet.
- Indicatif, consigne ou message saisis : traduits à l'affichage en anglais, puis réenregistrés traduits.

## Cause
- `fn_tocLaptop` construisait la grille du groupe avec `[a, b] select` : les deux textes sont évalués, et `_p` vide
  (groupe sans nœud ATAK) donnait une erreur à chaque rafraîchissement (1 s).
- `fn_tocScreen aceInit` sortait sans ACE avant d'enregistrer `COMSPEC_ATAK_TocDo`, utilisé par les actions de secours.
- `fn_tocPost` posait l'action ACE du PC par héritage (dépend d'un événement d'init sur un objet Static) et retirait
  l'action de la molette pour cet objet dès qu'ACE est là.
- Seuls WEST / EAST / GUER / CIV étaient reconnus ; textes saisis passés par `fnc_tr`.

## Correctif
`if / else` pour la grille ; `COMSPEC_ATAK_TocDo` enregistré avant la sortie sans ACE ; action ACE sur la classe exacte ;
alias de camp ; portée de l'action de secours 4,5 m ; textes saisis marqués `i18n:brut`.

## Fichiers touchés
`functions/toc/fn_tocLaptop.sqf`, `fn_tocScreen.sqf`, `fn_tocPost.sqf` (ATAK natif).

## Vérification
`hemtt check` sans erreur nouvelle. En jeu : PC posé dans Eden (avec et sans ACE), onglet GROUPES sur un groupe d'IA
sans ATAK, camp « BLUFOR » dans l'attribut, indicatif modifié en anglais puis ENREGISTRER.

## Statut
corrigé à vérifier en jeu (2.9.24)
