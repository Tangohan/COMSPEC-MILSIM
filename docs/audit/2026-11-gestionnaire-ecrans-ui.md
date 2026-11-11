# Gestionnaire des écrans TOC : nouvelle interface (conception) — novembre 2026

Demande de TTRD : « il faut vraiment améliorer l'UI du menu des écrans ». Périmètre : la fenêtre « Gérer les écrans »
(`fn_tocManager.sqf`, ouverte depuis Eden, le Zeus, ACE et le PC du poste). Ce document est une **conception** : aucun
SQF n'est modifié ici, l'écriture suivra l'accord de TTRD sur les maquettes.

Maquettes (PNG **dessinés hors jeu**, ce ne sont pas des captures) : `python -I
mod/COMSPEC_ATAK_Native/tools/toc_manager_mockups.py [dossier]`, par défaut dans `_toc_maquettes/gestionnaire_ui/` à
côté du dépôt. Couleurs lues dans `_tokens` de `fn_tocTheme.sqf` ; polices du jeu (Roboto Condensed, monospace pour les
données).

| Fichier | Montre |
|---|---|
| `vue_generale.png` | mur incurvé de 2 dalles, arbre, inspecteur, onglet SOURCE |
| `source_picker.png` | famille CAMÉRAS, liste avec état, caméra hors ligne, « source prête » à poser |
| `vue_zoom_suivi.png` | onglet VUE d'une carte : zoom, centre, orientation, suivi, calques |
| `vue_satellite.png` | onglet VUE d'un satellite : capteur, champ, qualité, visée (même pavé pour une caméra orientable) |
| `texte.png` | bandeau LED : aperçus, file des messages, éditeur, messages enregistrés |
| `alimentation_dispositions.png` | mur 3 × 2 avec dalles en veille et éteinte, les 12 dispositions |
| `alimentation.png` | sélection de 3 dalles, « valeurs différentes », état de rendu par dalle |
| `routage.png` | matrice source → sortie de la salle |
| `salle_suppression.png` | liste des salles, génération, confirmation de suppression dans la fenêtre |
| `compact_eden.png` | variante Eden, deux colonnes |
| `1366.png` | vue générale en 1366 × 768 |

## Ce qui ne va pas aujourd'hui

Titre = nom anglais du modèle ; 15 boutons à plat en haut ; liste de sources à plat dont le menu déroulant recouvre
APPLIQUER ; colonne « État » coupée ; champs sans valeur visible ; aucune image de ce que montre la dalle ; rien pour le
zoom, le suivi, l'alimentation par dalle, les salles.

## Mise en page

Quatre zones fixes, une seule fenêtre et un seul code pour Eden, le Zeus et le jeu.

1. **Barre du haut** (62) : titre « ÉCRANS DU TOC », salle (liste), scène (liste + CHARGER + ENREGISTRER), recherche
   (Ctrl+F : filtre l'arbre et les sources), SALLES, JOURNAL, FERMER. Filet accent 2 px dessous.
2. **Arbre à gauche** (304) : salle → écran → dalle. Pastille d'état (en direct, figée ou veille, lointaine, éteinte),
   nombre de dalles, témoin d'alimentation. Dessous : AJOUTER, DUPLIQUER, SUPPRIMER, ALLER À L'ÉCRAN, IDENTIFIER.
3. **Centre** (le reste) : titre = nom de l'écran, sous-titre = type en français, salle, distance, états. Trois vues
   (bouton à segments) : ÉCRAN (schéma du mur : une dalle = bandeau « n° · source » + état, vignette, luminosité, bouton
   d'alimentation), SALLE (plan de la salle, repris de la console du poste), ROUTAGE (matrice). Sous le schéma : les
   autres écrans de la salle en vignettes. La vue « Salles » (bouton SALLES) remplace le centre par la liste des salles.
4. **Inspecteur à droite** (540) : titre = « Dalle 2 · Carte et flux » (jamais le nom du modèle), ligne d'état, six
   onglets **SOURCE · VUE · DISPOSITION · TEXTE · ALIMENTATION · AVANCÉ**, contenu défilant, puis pied fixe : portée
   « Appliquer à » (cette dalle, sélection, tout l'écran, la salle) et cadre d'aide du champ survolé (ce que c'est + un
   exemple ; ambre ou rouge pour un avertissement).
5. **Barre d'actions en bas** (62) : fil « salle › écran › dalle », résultat (« Appliqué à 1 dalle · 12:35:42 Z ») ou
   « 2 changements non appliqués » en ambre, puis RÉINITIALISER, COPIER VERS…, ANNULER, APPLIQUER (accent seulement s'il
   y a quelque chose à appliquer). Aucun message hors de la fenêtre (ni hint, ni systemChat, ni notification).

Onglets : SOURCE = familles à gauche (CARTES, CAMÉRAS, SATELLITE-ISR, DONNÉES, TEMPS, PLANS, DIFFUSION, TEXTE, SPÉCIAL,
avec leur nombre), sources de la famille à droite (état, « aussi montrée sur »), source actuelle toujours affichée
au-dessus. VUE = dépend de la source (carte : cadrage, suivi, calques ; satellite ou caméra orientable : capteur, champ,
qualité, visée ; autre : « Cette source n'a pas de réglage de vue »). DISPOSITION = 12 cartes avec mini-schéma + image
sur plusieurs dalles. TEXTE = éditeur du message, la file et les messages enregistrés sont au centre. ALIMENTATION =
allumée, veille, éteinte, luminosité, veille automatique, qualité et état de rendu. AVANCÉ = nom, salle, titre de
diffusion, accès (verrou par rôle), lien vers le journal, diagnostic.

### Unités et tailles

- Maquette en « pixels de conception » sur 1080 de haut. En jeu : `_k = (getResolution select 1) / 1080`, largeur
  `px * _k * pixelW`, hauteur `px * _k * pixelH`, origine `safeZoneX/Y` (écran central en triple écran). La taille
  d'interface (Petite à Très grande) ne change donc rien, comme `_grid` du Centre Zeus.
- Fenêtre : 1840 × 1024 centrée (96 % × 95 %). Largeur plafonnée à 2300 (21:9 : le centre s'élargit, 6 vignettes par
  rangée) ; au-delà elle reste centrée. Sous 1500 de large (4:3, 5:4) : variante compacte.
- Texte (hauteur de ligne) : titre 30, titre d'inspecteur 26, section 21, champ 19, liste 18, aide 16, plancher
  0,0145 × hauteur d'écran (11 px en 768). Lignes : arbre 31, liste 34, champ 34, bouton 34 à 38.
- Variante compacte (Eden, 4:3) : 1192 × 780, arbre 300 + inspecteur ; le schéma devient une rangée de cases sans image ;
  ROUTAGE et « autres écrans » absents ; scènes et journal dans AVANCÉ. Dans Eden les résultats disent « enregistré dans
  la mission » (attributs de l'objet).

## Contrôles par zone

Identifiants proposés : 8700 à 8799 (plage à vérifier libre à l'écriture). Classes créées par `ctrlCreate` dans des
groupes ; les classes propres au mod (`COMSPEC_RscToc…`) héritent des classes du jeu pour fixer police et couleurs.

| Zone | Élément | Classe Arma | Id | Remarques |
|---|---|---|---|---|
| Fond | voile, cadre | `RscText` | 8700-8701 | voile `bg0` 60 % sur tout `safeZone` |
| Haut | salle, scène | `RscCombo` (`wholeHeight` borné à 8 lignes) | 8710-8711 | au plus 8 lignes visibles, jamais au-dessus d'un bouton |
| Haut | recherche | `RscEdit` | 8712 | `KeyUp` : filtre ; Échap : vide |
| Haut | boutons | `COMSPEC_RscButton` | 8713-8718 | info-bulle avec exemple sur chacun |
| Gauche | arbre | `RscTree` (`multiselectEnabled = 1`) | 8720 | `tvSetPicture` + `tvSetPictureColor` (pastille), `tvSetPictureRight` (alimentation), `tvSetTooltip` |
| Gauche | actions | `COMSPEC_RscButton` | 8721-8725 | |
| Centre | groupe par vue | `RscControlsGroupNoHScrollbars` | 8730-8733 | un groupe par vue, `ctrlShow` |
| Centre | vues | `RscToolbox` | 8734 | flèches du clavier |
| Centre | dalle | `RscText` (bandeau, pied), `RscPictureKeepAspect` (vignette), 4 `RscText` (cadre), `RscButton` transparent (clic), `RscActivePicture` (alimentation) | créés à la volée, `-1` | 12 dalles au plus (mur 4 × 3) |
| Centre | matrice | `RscText` (lignes, colonnes) + `RscButton` par case | `-1` | 14 × 14 cases au plus ; au-delà la vue défile |
| Centre | salles, file des messages | `RscListNBox` | 8740-8742 | `lnbSetPicture`, `lnbSetColor` par cellule |
| Droite | onglets | 6 `COMSPEC_RscButton` + filet `RscText` | 8750-8756 | Ctrl+1 à Ctrl+6, Ctrl+Tab |
| Droite | page d'onglet | `RscControlsGroupNoHScrollbars` | 8760-8765 | défilement vertical automatique |
| Droite | familles | `RscListBox` | 8770 | |
| Droite | sources, caméras | `RscListNBox` | 8771 | double-clic = appliquer ; filtre : `RscEdit` 8772 + `RscToolbox` 8773 |
| Droite | choix exclusif | `RscToolbox` | par champ | 2 à 5 choix ; au-delà une liste |
| Droite | nombre | `RscXSliderH` + `RscEdit` | par champ | valeur toujours lisible et saisissable |
| Droite | case | `RscCheckBox` + `RscText` | par champ | |
| Droite | texte du message | `RscEditMulti` | 8780 | lu par `ctrlText`, jamais `parseText` |
| Droite | portée, aide | `RscToolbox`, `RscStructuredText` | 8790-8791 | aide mise à jour sur `MouseEnter` et `SetFocus` |
| Bas | fil, état | `RscStructuredText` | 8792 | |
| Bas | actions | `COMSPEC_RscButton` | 8793-8796 | Entrée = APPLIQUER, Échap = ANNULER puis FERMER |
| Confirmation | voile + boîte | `RscControlsGroup` au-dessus, reste désactivé (`ctrlEnable false`) | 8798 | bouton par défaut = ANNULER |

## Parcours

- **Changer une source** : clic sur une dalle (schéma ou arbre) → SOURCE → famille → source → APPLIQUER. Raccourci :
  double-clic sur la source. Sans dalle choisie : la source devient « prête » (bandeau vert sous le schéma), le clic
  suivant sur une dalle la pose, Échap abandonne. C'est le remplaçant du glisser-déposer.
- **Plusieurs dalles** : Ctrl+clic, Maj+clic, Ctrl+A. L'inspecteur titre « 3 dalles choisies » ; un champ dont les
  valeurs diffèrent affiche « valeurs différentes » et n'est écrit que si on le touche.
- **Vue** : molette sur la vignette = zoom, clic droit maintenu = déplacer, double-clic = centrer ; l'inspecteur suit.
  Satellite et caméra orientable : clic = pointer, flèches = déplacer la visée.
- **Alimentation** : bouton de la dalle (allumée → veille → éteinte), ou onglet ALIMENTATION pour la sélection.
- **Texte** : choisir un message de la file, l'éditer (aperçu immédiat sous l'image en direct), APPLIQUER.
- **Salles** : SALLES → créer, générer (petite, moyenne, grande), générer tous les flux, renommer, supprimer.
  Suppression : boîte dans la fenêtre, choix « garder les écrans » (par défaut) ou « supprimer aussi les écrans ».
- **Scènes** : choisir dans la liste puis CHARGER ; ENREGISTRER écrit la scène courante (profil, par carte).
- **États** : vide (« Aucun écran dans cette salle. Posez-en un avec AJOUTER. ») ; caméra hors ligne (aide ambre, la
  dalle montrera PAS DE SIGNAL, application permise) ; source refusée par le format de la dalle (ligne grisée, aide :
  « Cette dalle est un bandeau : choisissez TEXTE ou TEMPS. ») ; salle verrouillée (champs en lecture seule, aide :
  qui peut régler) ; écran lointain (vignette assombrie, « lointaine »).

## Hors de portée d'une fenêtre Arma, et ce qu'on fait à la place

| Voulu | Limite | Choix |
|---|---|---|
| Glisser une source sur une dalle | glisser-déposer limité aux listes (`canDrag`, `LBDrop`), absent de `RscListNBox` | « source prête » + clic, double-clic |
| Vignettes en direct | pas de capture d'écran d'un objet | la vignette affiche la **texture déjà rendue** de la dalle (`getObjectTextures`, `ctrlSetText`) : aucun rendu de plus ; écran lointain ou figé : dernière image assombrie ; texture absente ou Eden : case avec le nom de la source. À vérifier en jeu pour les textures `ui(...)` ; repli : case sans image |
| Bouton dans une ligne d'arbre | une ligne d'arbre n'a pas de bouton | témoin à droite (`tvSetPictureRight`) ; l'action est sur la dalle du schéma, dans ALIMENTATION et sur la touche P |
| Sélection multiple dans l'arbre | `multiselectEnabled` et `tvSelection` à vérifier sur un arbre créé par script | repli : Ctrl+clic géré par `TreeSelChanged` + état de la touche, lignes marquées par leur couleur |
| Liste déroulante avec recherche | `RscCombo` ne filtre pas | champ de saisie + liste filtrée dessous (cible du suivi, caméras) |
| Lignes de liste sur deux lignes | `RscListNBox` : une ligne par entrée | la description passe dans le cadre d'aide |
| Dalles incurvées dessinées en perspective | pas de trapèze | dalles à plat côte à côte ; le type « incurvé » est dit dans le sous-titre |
| Pastilles rondes, pictogramme d'alimentation | pas de dessin vectoriel | 3 petites images `.paa` du mod, teintées par jeton |
| Aperçu animé du texte défilant | pas d'animation de texture dans la fenêtre | `RscText` monospace déplacé dans un groupe (`ctrlSetPosition` + `ctrlCommit`), ou aperçu fixe |
| Info-bulles riches | texte simple, une ligne | info-bulle courte + cadre d'aide avec l'exemple |

Coût : aucun rendu ajouté ; la fenêtre relit les lignes à 1 Hz (PFH CBA retiré à la fermeture), listes reconstruites
sur signature comme la console du poste.

## Ce que la fenêtre attend du code des écrans

Modes déjà présents de `fn_tocManager` (`rows`, `sources`, `groups`, `category`, `setSource`, `frames`, `setView`,
`feeds`, `uniqueHall`, `canEdit`) ; à compléter par l'agent du tableau : liste des caméras avec état (`camList`),
alimentation et veille par dalle, état de rendu par écran (en direct, figé, lointain), file et effets du texte, scènes,
rôles et journal. La fenêtre ne lit que ces modes : elle peut être écrite sans attendre, les onglets sans données
affichant leur état vide.

## Questions ouvertes

1. Vignettes : accepter la texture en direct de chaque dalle (12 images au plus à l'écran), ou préférer des cases sans
   image, plus sobres ?
2. La vue ROUTAGE vaut-elle sa place dès la première version, ou après SOURCE, VUE et ALIMENTATION ?
3. Le Zeus et le PC du poste ouvrent-ils la même fenêtre pleine, ou le PC garde-t-il son onglet SALLE avec un bouton
   « Gérer les écrans » ?
