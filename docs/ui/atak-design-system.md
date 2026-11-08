# COMSPEC ATAK — système de design du téléphone (3.1.0)

Une seule source pour les couleurs, les tailles de texte, les espacements et les composants de toutes les apps du
téléphone. Code : `fn_theme` (jetons de couleur), `fn_layoutGet` (échelle typographique et mise en page),
`fn_formRender` (composants). Aucune page ne doit plus écrire une couleur ou une taille en dur.

## 1. Direction

Le téléphone est un terminal de terrain : on le lit de nuit, en véhicule, dans un coin d'écran de 1080p. Trois règles
tiennent toute l'interface :

1. **Une encre par rôle.** Le texte courant est clair (`text`), les aides sont atténuées (`textDim`), les légendes
   encore plus (`textMuted`). Les couleurs ne disent qu'une chose : un état (ok, attention, alerte, info) ou une
   affiliation (ami, ennemi, neutre, inconnu, civil). L'accent choisi par le joueur marque ce qui est actif ou principal.
2. **Un liseré à gauche, jamais un cadre.** Carte d'état, notification, section, en-tête repliable : tout ce qui porte
   un état ou une action a une barre verticale à sa couleur, posée au bord gauche. Pas de bordure, pas d'ombre, pas de
   coins arrondis (Arma ne les dessine pas : on assume des angles nets, façon JBC-P).
3. **Un composant par besoin.** Les pages assemblent des lignes de `fn_formRender` ; une page qui dessine ses propres
   boutons ou ses propres cartes est l'exception (carte, chat, BFT, montre).

## 2. Jetons

### Couleurs (`[] call comspec_atak_native_fnc_theme`, global `COMSPEC_ATAK_Th`)

Trois thèmes, profil `COMSPEC_ATAK_Theme` (Réglages › Personnalisation › Thème, tuile « Thème » du volet rapide) :

| Jeton | Sombre (défaut) | Jour | Nuit JVN | Usage |
|---|---|---|---|---|
| `bg0` | #060807 | #dbe3de | #000000 | écran, bandeaux de terminal |
| `bg1` | #090b0a | #e8ede9 | #030503 | fond des pages (`fn_formRender`) |
| `surface` | #121614 | #fbfcfb | #060b08 | cartes, lignes de réglage, notifications |
| `surfaceAlt` | #0e1210 | #f2f6f4 | #050805 | ligne alternée des fiches |
| `surface2` | #1f2521 | #ccd9d1 | #0b160e | boutons secondaires, puces, relief |
| `surfaceHi` | #262f2a | #b5c9bd | #0f1f13 | ouvert, survol, choisi |
| `border` | blanc 10 % | noir 14 % | vert 18 % | filets |
| `chrome` / `onChrome` | #060807 / #e6f0e8 | idem | #000 / #85e094 | barre d'état, barre d'app, dock, rail : toujours sombres |
| `text` | #edf5f0 | #0f1712 | #85e094 | texte courant |
| `textDim` | #adbab5 | #3d4d45 | #61a86e | aides, sous-titres |
| `textMuted` | #8f9e99 | #5e7066 | #457a4f | légendes, horodatages |
| `accent` / `accentInk` / `onAccent` | accent du joueur | accent / accent × 0,62 | vert imposé | fond principal / texte accent / texte sur accent |
| `ok` `warn` `alert` `info` `off` | #5cc76b #f2ab33 #e5483a #70b5e8 #6b7870 | assombris | #5cc76b #bfc74d #e04d38 #66b39e | états |
| `friend` `hostile` `neutral` `unknown` `civil` | #4d9ffa #e5483a #5cc76b #f2c94c #d96bc2 | assombris | désaturés | affiliations (APP-6) |
| `field` `veil` `rail` `onState` | champ, voile, pastille neutre, texte sur fond d'état | | | |
| `mapPanel` `mapText` `mapDim` `mapOk` `mapWarn` `mapAlert` `mapActive` | calques de la carte : toujours sombres | | | bandeau du curseur, outils |

Chaque jeton de texte a sa version hexadécimale `hex…` pour les balises `<t color=…>`. Accès : `_th get "surface"`,
`_th get "hexTextDim"`, `["state", "warn"] call fnc_theme`, `["aff", "hostile"] call fnc_theme`.

### Typographie (`fn_layoutGet`)

Barlow / Roboto Condensed pour tout, Etelka Monospace pour les valeurs alignées (grilles, heures, fréquences).

| Jeton | Taille | Plancher | Usage |
|---|---|---|---|
| `fontCaption` | 0,70 × corps | 14 px | légendes, horodatages, en-têtes de groupe de boutons |
| `fontSmall` | 0,86 × corps | 15 px | texte courant des pages, boutons, onglets, tuiles |
| `font` | corps | 16 px | champs, listes, titre de l'app |
| `fontBody` | 1,15 × corps | — | base du texte structuré (`<t size='0.8'>` reste au niveau des légendes) |
| `fontTitle` | 1,2 × corps | — | titres de carte, en-tête d'app |
| `fontDisplay` | 1,9 × corps | — | grands chiffres |
| `fontHero` | 3,4 × corps | — | heure de l'écran de verrouillage |

Le corps suit la taille de l'écran du téléphone (mini, plein écran, échelle CBA) et Réglages › Taille du texte
(0,9 à 1,3). Rien ne descend sous 14 px réels, sur aucun écran. `COMSPEC_ATAK_FontMin` = `fontCaption`.

### Espacements et hauteurs

`pad` (= 1,2 % de la largeur d'écran), `sp1`…`sp4` (1 à 4 × pad), `rowH` (1,55 × corps : ligne de formulaire),
`listH` (2,4 × corps : ligne de liste haute), `tap` (1,9 × corps : plus petite cible tactile). Le téléphone est en
portrait ou en paysage, mini ou plein écran : tout est relatif au corps, rien en pixels sauf les filets (1 px).

### Icônes

SVG dans `tools/gen_assets.py` (`ICONS`), trait blanc 1,7 sur une grille 24, rendus en PAA 128 px par
`python tools/gen_assets.py <hemtt> only nom1,nom2` (CairoSVG, ou resvg-py quand cairo manque). Teintées à l'affichage
(`ctrlSetTextColor`) : une icône n'embarque jamais sa couleur. Ajoutées en 3.1.0 : `ui_search`, `ui_chevron_right`,
`ui_pin`, `ui_theme`, `ui_info`, `ui_warning`, `ui_error`, `ui_filter`, `ui_eye`, `ui_clock`.

## 3. Composants (`fn_formRender`)

| Ligne | Rôle |
|---|---|
| `header` | en-tête d'app : grand titre, sous-titre, actions à droite, bouton retour quand un sous-écran est ouvert |
| `tabs` | onglets soulignés, l'onglet choisi en accent, pastille de compteur ; remplace « segment » comme barre d'onglets |
| `section` | barre d'accent, titre en capitales, sous-titre |
| `fold` | section repliable, résumé à droite, chevron |
| `row` | ligne de liste haute : icône ou lettre, titre, sous-titre, valeur à droite, chevron, filet |
| `info` | libellé à gauche, valeur à droite, filet |
| `line` / `lineseg` / `linecombo` | lignes de fiche numérotées (9-LINE, SALUTE, CR) |
| `switch` / `toggle` | interrupteur OUI / NON (aide, « IMPOSÉ » par la communauté) |
| `segment` | choix exclusif en boutons accolés |
| `slider` | curseur avec valeur et icône |
| `edit` / `memo` / `field` / `password` / `combo` | saisie ; `field` : Entrée appuie sur le bouton |
| `buttons` | boutons : secondaire, principal (`true`), destructif (`"red"`), désactivé |
| `big` / `bar` | gros boutons tactiles ; groupes de boutons titrés |
| `chips` | pastilles d'état ou d'étiquette |
| `status` / `check` / `steps` / `strip` / `banner` | cartes et frises d'état |
| `notif` / `cells` | carte de notification ; cartes d'état en grille |
| `person` / `hero` / `image` / `qr` | vignette + texte + boutons ; grande vignette ; photo ; QR code |
| `empty` | état vide : icône, titre, explication, bouton d'action facultatif |
| `skeleton` | squelette de chargement (n lignes grises) |
| `text` / `title` / `gap` | texte structuré, titre, espace |

Autres composants partagés : toast de notification sur la barre d'app (`fn_notificationsRender`, deux lignes si le
texte est long, jamais tronqué sous deux lignes), bulle en bas (`fn_toast`, deux lignes), bandeau « Supprimé · ANNULER »
(`fn_undoSnack`), tuile d'app (`fn_tileCreate`), volet rapide (`fn_quickSettings`), écran de verrouillage
(`fn_lockScreen`), lanceur (`fn_launcherRender`).

## 4. Règles

- **Rendu seulement sur changement** : une page se redessine quand ses données changent (signature dans
  `fn_statusUpdate`) ou sur un clic ; aucun gestionnaire par image pour l'interface, les transitions passent par
  `ctrlCommit` (moteur). Le défilement et le focus de saisie sont gardés par `fn_pageRender`.
- **Pas de couleur ni de taille en dur** dans une page : `COMSPEC_ATAK_Th get "…"` et `fn_layoutGet`. Les calques de la
  carte (bandeau, outils) utilisent les jetons `map…`, toujours sombres.
- **Textes** : phrases complètes, pas d'abréviation, FR dans le code, EN dans `i18n/en.txt` (`tools/i18n.py`).
  Boutons en capitales (vernaculaire du terminal), titres de section en capitales, tout le reste en casse normale.
- **États vides** : toujours un titre, une explication et, si possible, l'action qui remplit l'écran.
- **Clavier** : Tab passe de bouton en bouton (moteur), Entrée valide un champ `field` et ouvre la première app
  trouvée dans la recherche du lanceur, Échap efface la recherche puis rend la main.
- **Mini et plein écran, portrait et paysage** : les composants se replient (boutons et onglets passent à la ligne
  quand leur libellé ne tient pas, `_fit` rétrécit un libellé jusqu'au plancher et jamais en dessous).

## 5. Avant / après (captures mentales)

| Écran | Avant | Après |
|---|---|---|
| Lanceur | loupe « zoom + » de la carte dans la recherche, en-têtes gris sur fond d'écran, puces grises | loupe dédiée, en-têtes sur le fond du thème, puce choisie en accent, épingle visible sur les apps épinglées, message de recherche vide qui explique quoi faire |
| Écran de verrouillage | une carte par notification, les répétitions en file | une carte par app avec le compteur, point d'accent sur le non lu, CARTE et MESSAGES sous les cartes |
| Centre de notifications | boutons de filtre, liste à plat | onglets soulignés avec compteurs, groupes par app (icône, nom, nouvelles), état vide avec RÉGLER LES ALERTES |
| Volet rapide | tuiles sombres fixes | tuiles au thème, tuile Thème (sombre / jour / nuit JVN) |
| Réglages | lignes « person » avec bouton OUVRIR, bouton « < RÉGLAGES · … » | lignes de liste avec chevron, en-tête de catégorie avec retour, réglage Thème |
| Fiche de marqueur | titre + champs à la suite, SUPPRIMER entre ENREGISTRER et ANNULER | en-tête avec la grille, sections Identité / Apparence / Diffusion, SUPPRIMER en rouge à part |
| Toasts | « Non connecté à votre compte Athena : touchez ic… » | deux lignes, texte complet |
| Bascules « toggle » | bouton « ○ libellé » | même carte et pastille OUI / NON que les interrupteurs |
| Debug, Groupe | onglets en boutons verts | onglets soulignés, compteurs en pastille |
| Thème jour / nuit JVN | inexistants (filtre rouge ou écran assombri seulement) | surfaces claires au soleil ; vert phosphore sous JVN, accent vert imposé, rouge d'alerte conservé |

## 6. Ce qui reste

- Pages à dessin propre (Carte en mini, Chat, BFT, Alertes, SALUTE, Reco, Zones, Routes, JTAC) : couleurs passées
  aux jetons, mais leurs tailles restent calculées à la main ; à migrer vers `row` / `tabs` / `header` page par page.
- Icônes d'apps : homogènes en style (trait 1,7 sur grille 24) mais pas toutes revues une à une.
- Les textures de la coque et le fond d'écran ne changent pas avec le thème.
