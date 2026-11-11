# Thème TOC (écrans muraux et PC du poste)

Une seule source : `mod/COMSPEC_ATAK_Native/Sources/addons/main/functions/toc/fn_tocTheme.sqf`. Ses tables (JSON strict)
sont lues par le jeu, par le générateur de maquettes `mod/COMSPEC_ATAK_Native/tools/toc_mockups.py` et par
`tests/Unit/AtakTocThemeTest.php`. Les classes de config du PC (`ui/toc_theme.hpp`) recopient les mêmes valeurs (vérifié
par le test). Écrans muraux : pixels virtuels 1600 x 900 (faces 4:1 : 1600 x 400).

## Jetons de couleur

| Jeton | Valeur | Rôle |
|---|---|---|
| `bg0` | #070a0d | fond de l'écran, champs, plaques de carte (à 78-82 %) |
| `bg1` | #0d1318 | panneaux, en-tête, barre du haut du PC |
| `bg2` | #141c23 | cases en relief, ligne en évidence, onglet choisi, bouton |
| `bg3` | #1c2630 | survol d'un bouton |
| `line` | #24303a | filet (1 px) |
| `lineStrong` | #3a4a57 | quadrillage, filet de total, coins d'une image coupée |
| `text` | #e8eef2 | texte principal |
| `text2` | #aab7c1 | texte secondaire, données |
| `muted` | #8494a0 | légendes, en-têtes de colonnes, états vides |
| `accent` | #34d399 | accent unique : en direct, passé, bouton principal, filet d'en-tête |
| `accentDim` | #0e3a2b | sélection dans une liste |
| `amber` | #f5b841 | ancien, urgent, reçu, PIP, lecture seule |
| `red` | #ff5f56 | FLASH, perdu, refusé, alerte, PAS DE SIGNAL |
| `redDim` | #3a1110 | bandeau FLASH |
| `blue` | #62b0f5 | important, pris en compte, unités de l'ORBAT |
| `friend` / `hostile` / `neutral` / `unknown` | #6cb8ff / #ff6b6b / #7ee08a / #f2d36b | couleurs OTAN |
| `lost` | #8a97a2 | nœud perdu ou hors ligne |
| `mapTint` / `mapTintPic` | #a3a8ab / #6b7175 | teinte de la photo de carte / de la carte d'ensemble (fond assombri) |

Contraste vérifié : tout texte ≥ 4,5:1 sur `bg0`, `bg1`, `bg2`, `redDim`, sur les pastilles et les plaques de carte.

## Texte

Libellés en Roboto Condensed du jeu (`RobotoCondensed`, `RobotoCondensedBold` : vrai gras), données (heures, grilles,
nombres) en `EtelkaMonospacePro`. Écran mural, jamais sous 28 px : `display` 132 (ZULU de l'horloge), `hero` 72, `num` 48
(cases à gros chiffre, chronos), `squadName` 56, `title` 40, `heading` 32, `body` / `bodyBold` / `data` / `caption` 28.
PC : grille de `fn_tocLaptop` (`font` 0,019, plancher `fontMin` 0,0145 x hauteur d'écran).

## Composants

- **Cadre** (`FRAME`) : en-tête 72 px (`bg1`, titre « AFFICHAGE · NOM » à gauche, heure ZULU et locale en monospace à
  droite), filet accent 2 px (rouge sous FLASH), contenu de 90 à 836 px, pied 48 px sous un filet (écran · affichage à
  gauche, état de la liaison à droite). FLASH : bandeau `redDim` 52 px avec liseré rouge ; tout ce qui est sous l'en-tête
  descend de 52 px (`_flashRule` : ce qui reste en place, ce qui raccourcit).
- **En-tête de section** : titre `heading` à gauche, état en légende à droite, filet `line` avec un trait accent de 40 px.
  Jamais de petit libellé au-dessus d'un titre.
- **Case à gros chiffre** : fond `bg2`, liseré 4 px à la couleur d'état, nombre `num`, libellé `body` `text2`.
- **Tableau** : en-têtes en légende, lignes de 40 px séparées d'un filet (pas de zébrure), nombres à droite, colonnes
  fixes (`_cols`), texte coupé avec « … », « + N autres » quand tout ne tient pas, ligne suivante en évidence (`bg2` +
  liseré accent).
- **Pastille d'état** : jeton à 16 % sur 32 px de haut, liseré 3 px, texte du jeton centré.
- **Carte** : photo ou carte d'ensemble teintée puis voilée (`bg0` à 35 %), quadrillage numéroté sur plaques, symboles
  OTAN amis, plaques avec liseré d'état (filet de rappel si décalées), échelle, nord, légende.
- **Image en direct** (mosaïque, caméra du chef, satellite) : coins de visée 28 px, pastille DIRECT, bandeau bas
  (indicatif, 4 barres de liaison, heure ZULU) ; sans image : PAS DE SIGNAL en rouge, case libre en sourdine.
- **État vide** : une phrase, puis ce qu'il faut faire. Pas d'icône.
- **PC** : barre du haut (poste, ZULU, LOCAL, liaison, FLASH, prise du poste, fermer), deux rangées d'onglets pleine
  largeur (choisi : `bg2`, texte accent, filet accent), panneau de l'onglet (titre et état, filet), ligne d'aide, ligne
  d'état. Boutons : normal `bg2`, survol `bg3`, désactivé texte atténué, principal en accent.

## Formats d'image et affichage TEXTE

Le format d'une dalle vient de son rapport largeur / hauteur (`comspec_screenRatio`, ou `comspec_screenPanelRatio[]` par
dalle ; 16:9 sans indication). Table `_formats` de `fn_tocScreen.sqf` (`["format", écran]`) :

| Rapport | Format | Texture | Affichages |
| --- | --- | --- | --- |
| moins de 1,3 (colonne 8:9 d'un écran incurvé) | `COL` | 1024 x 1024 | `COLA` (horloge et chronos), `COLB` (réseau et flux), `COLC` (caméras), `COLD` (main courante), `COLE` (exécution), `COLF` (demandes et pertes), `TEXT` |
| moins de 3 (16:9) | `STD` | 2048 x 1024 (1024 x 512 pour les dalles 55" d'un mur de 7 zones ou plus) | tous sauf bandeau, plaque et colonnes |
| moins de 6 (4:1) | `STRIP` | 2048 x 512 | `STRIP`, `PLATE`, `TEXT` |
| moins de 12 (8:1) | `LED` | 2048 x 256 | `TEXT` |
| au-delà (16:1) | `LED` | 4096 x 256 (2048 x 128 si elle n'est pas chargée) | `TEXT` |

Une texture procédurale n'est acceptée qu'en puissances de 2 : un bandeau de 12:1 serait tassé ou étiré, d'où le 16:1.
Colonnes `COLA` à `COLF` : 800 x 900 pixels virtuels, régions dans `_walls`, ni en-tête ni pied.

| Colonne | Contenu | Régions (`_walls`) | Colonnes (`_cols`) |
| --- | --- | --- | --- |
| `COLC` | deux vignettes caméra 16:9 (688 x 387, jamais étirées), sources et réglage de la mosaïque, pages de deux | `camHead`, `camSub`, `camRule`, `cam1`, `cam2` | aucune |
| `COLD` | main courante, entrée sur deux lignes de 84 px (heure, type, auteur ; texte) | `logHead`, `logSub`, `logRule`, `logArea` | `LOG_C` |
| `COLE` | prochaine ligne d'exécution et temps restant, puis la liste avec sa barre d'avancement | `nxHead` à `nxAt`, `exHead`, `exSub`, `exRule`, `exProg`, `exCols`, `exArea` | `EXCHECK_C` |
| `COLF` | dernières demandes des éléments, puis pertes et résultats (quatre cases, tableau par élément) | `reqHead`, `reqSub`, `reqRule`, `reqArea`, `killHead`, `killSub`, `killRule`, `killTiles`, `killCols`, `killArea` | `REQ_C`, `KILLS_C` |

Dispositions d'un écran à plusieurs dalles (`fn_tocLayout.sqf`, table `_catalog`) : liste ordonnée du contenu des
dalles ; un contenu que le format d'une dalle ne permet pas prend son jumeau (table `_twins` : sur une colonne,
`CLOCK` devient `COLA`, `FEED` / `NET` / `SPLIT` `COLB`, `MOSAIC` / `CAM` `COLC`, `LOG` `COLD`, `EXCHECK` `COLE`,
`REQ` / `KILLS` `COLF`), sinon l'affichage par défaut de la dalle. Une image 16:9 n'est jamais étirée sur une colonne.

Affichage `TEXT` (`fn_tocText.sqf`, maquettes `bandeau_texte`, `bandeau_texte_long`, `mur_texte`) : panneau `bg0`,
caractères à chasse fixe gras, couleur du message (`accent`, `amber`, `red`, `text`), bordure fine atténuée. Longueurs en
« hauteurs de panneau » : police 0,60 sur un bandeau, 0,26 (une ligne) ou 0,19 (deux lignes) sur un 16:9. Le texte qui
tient est fixe ; plus large que le panneau il défile de droite à gauche (4, 7 ou 11 caractères par seconde, blanc de
6 caractères entre deux passages), position tirée de `serverTime`. Paramètres : table `_schema` (`["params", "TEXT"]`),
les fenêtres de réglage se construisent dessus. Le texte saisi passe toujours par `ctrlSetText`, jamais par `parseText`.

## À faire / à éviter

- Toute couleur passe par un jeton (`["rgba", [jeton, alpha]]`, `color='@jeton'` + `["ink", texte]`). Aucune valeur
  écrite à la main hors de `fn_tocTheme` (le test le vérifie), sauf la coque du PC portable (objet).
- Une région nouvelle va dans `_walls` (et la maquette la reprend) ; jamais de nombres recopiés dans le code d'un écran.
- Un seul accent ; le rouge est réservé au FLASH, aux pertes et aux alertes.
- Pas de fenêtre d'alerte du jeu (hint, systemChat…) : les messages vont au téléphone ou à la ligne d'état du PC.
- Maquettes : `python -I mod/COMSPEC_ATAK_Native/tools/toc_mockups.py [dossier]` (PNG hors du dépôt).
