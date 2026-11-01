# Recherche d'apps qui recharge la page, réglages qui remettent la page à zéro, « Lire » traduit « Read »

## Contexte
ATAK natif 2.9.26, retours de joueurs anglophones :
« searching for apps on in-game ATAK refreshes the page every letter », « some of the new / updated apps still refresh
on click (turning on speaker mode in music resets the page) », « music app still says "read" instead of "play" ».

## Symptôme
- Lanceur : à chaque lettre tapée dans « Rechercher une app », tout l'écran se redessinait 0,25 s plus tard ; une frappe
  rapide perdait des lettres ou le curseur. Pas de recherche en mode PETIT.
- App Musique : activer le haut-parleur, couper le son, aléatoire, répéter ou pause redessinait toute la page ; la liste
  sous le lecteur revenait en haut. Même retour en haut dans toutes les pages en formulaire après un clic qui redessine.
- En anglais, les boutons de piste et de lien de l'app Musique affichaient « READ », l'onglet « LECTURE » restait en
  français, le bouton LECTURE du débriefing (AAR) aussi.

## Cause
- `fn_launcherRender` relançait `fn_pageRender` (effacement et recréation de tous les contrôles, champ compris) après
  chaque frappe.
- `fn_musicAction` appelait le rendu complet de la page pour chaque réglage ; le groupe défilant de `fn_formRender` n'est
  pas la zone de contenu (88531) dont `fn_pageRender` garde le défilement.
- Dictionnaire : une seule entrée « LIRE » → « READ » (sens lecture d'un document) ; « LECTURE » sans traduction.

## Correctif
- Lanceur : tuiles et en-têtes créés une fois ; `fn_launcherFilter` masque, affiche et replace les contrôles existants
  (0,1 s après la dernière touche), sans casse ni accents, sur le nom français, le nom traduit et le nom de classe ;
  en-têtes sans résultat masqués, « Aucune app ne correspond. » ; Entrée ouvre la première app, Échap efface
  (`fn_keyBlock`) ; champ compact en mode PETIT.
- `fn_formSet` : mise à jour sur place des lignes `switch` / `slider` / `fold` déclarées avec un id. Musique : haut-parleur,
  entendre, volumes (et icônes couper / rétablir), aléatoire, répéter, pause sans rendu de page.
- `fn_formRender` : défilement du formulaire gardé quand la même page se redessine (groupe nommé page + premières
  lignes, relevé par `fn_pageRender` via `COMSPEC_ATAK_ScrollGroups`). Messagerie exclue.
- Libellés : « ÉCOUTER » (LISTEN) pour les pistes et les liens, onglet « LECTEUR » (PLAYER), « LECTURE » → PLAY ; les
  « Lire » des documents, messages et licence gardent « Read ».

## Fichiers touchés
`functions/ui/fn_launcherRender.sqf`, `fn_launcherFilter.sqf` (nouveau), `fn_keyBlock.sqf`, `fn_formRender.sqf`,
`fn_formSet.sqf` (nouveau), `fn_pageRender.sqf`, `functions/music/fn_pageMusic.sqf`, `fn_musicAction.sqf`,
`functions/viz/fn_vizPlayer.sqf`, `functions/pages/fn_pageSettings.sqf`, `config.cpp`, `i18n/en.txt` (ATAK natif).

## Vérification
`hemtt check` sans erreur nouvelle (seules les 2 erreurs connues `ctrlWebBrowserAction`). À tester en jeu : voir la
liste de l'entrée « Pack 2.9.27 » de `CHANGELOG-ATAK.md`.

## Statut
Corrigé dans le code, non testé en jeu.
