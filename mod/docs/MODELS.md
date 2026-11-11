# Modèles SSE utilisateur

Les **modèles** permettent de réutiliser des profils narratifs complets (profil, thème, pools de noms, SMS, documents, biométrie, ordinateur…).

## Sources

| Source | Stockage |
|--------|----------|
| `BUILTIN` | Intégrés au mod (catalogue + ères Irak / Russie) |
| `MISSION` | `comspec_sse_models_mission` (synchronisé) |
| `USER` | `profileNamespace` (persistant local) |
| `WEB` | Atelier portail Athena `/atak/sse/dev` (export SQF / fichier d’échange) |

## Modèles intégrés

### Génériques
- Cellule insurgée — Irak
- Chef HVT
- Réseau courriers
- Financier
- Technicien IED
- Safehouse urbain
- Contrebande frontière
- Civil non pertinent (bruit)
- Cellule drone / ISR
- Propagande / média

### Irak 2010–2020
- `builtin_iq_2010_2020_cache_armes`
- `builtin_iq_2010_2020_ied`
- `builtin_iq_2010_2020_hvt`
- `builtin_iq_2010_2020_courrier`
- `builtin_iq_2010_2020_financier`
- `builtin_iq_2010_2020_safehouse`

### Russie / Est 2020–2024
- `builtin_ru_2020_2024_recon`
- `builtin_ru_2020_2024_logistics`
- `builtin_ru_2020_2024_command`
- `builtin_ru_2020_2024_drone`
- `builtin_ru_2020_2024_ew`
- `builtin_ru_2020_2024_infoops`
- `builtin_ru_2020_2024_courier`
- `builtin_ru_2020_2024_civil`

Région `RUSSIA` disponible dans les pools narratifs (noms, préfixes +7, alias) **et** dans le combo Eden « Région narrative » (`Russie / théâtre Est`).

## API

```sqf
// Créer
private _model = ["Ma cellule Abu Yassin", createHashMapFromArray [
    ["profile", "INSURGENT"],
    ["complexity", "DETAILED"],
    ["region", "IRAQ"],
    ["theme", "weapons_cache"],
    ["aliasPool", ["ABU YASSIN", "ABU HAMZA"]],
    ["contactPool", ["FARID", "MUSTAFA", "THE DRIVER"]],
    ["smsTemplates", [
        "Livraison demain après la prière.",
        "Le camion passe par le point ALPHA."
    ]],
    ["includeComputer", true],
    ["tags", ["custom", "irak"]]
], name player] call comspec_sse_fnc_createModel;

[_model] call comspec_sse_fnc_saveModel;

// Lister
private _all = [] call comspec_sse_fnc_listModels;
private _userOnly = ["USER"] call comspec_sse_fnc_listModels;

// Appliquer
[_unit, _model get "id"] call comspec_sse_fnc_applyModel;
[_unit, "builtin_chef_hvt"] call comspec_sse_fnc_applyModel;

// Capturer une entité existante
private _captured = [_unit, "Capture Karim"] call comspec_sse_fnc_modelFromEntity;

// Export / import (partage entre missions)
private _export = [_model get "id"] call comspec_sse_fnc_exportModel;
[_export, "Copie importée"] call comspec_sse_fnc_importModel;

// Supprimer (pas les builtins)
[_model get "id"] call comspec_sse_fnc_deleteModel;
```

## Zeus

- **Appliquer modèle SSE** — dialogue de sélection
- **Enregistrer comme modèle SSE** — capture la cible
- **Lister modèles SSE** — inventaire

## Eden

Champ **Modèle SSE** : saisir un ID (`builtin_chef_hvt`, etc.).  
Prioritaire sur Profil si renseigné + Génération AUTO.

## Structure d'un modèle

```
id, name, author, source, version
profile, complexity, region, theme
namePool, aliasPool, contactPool
smsTemplates, documentTemplates, codewords, locations
forcedIdentity, forcedPhone
noiseProbability, falseLeadProbability
includeBiometrics, includePhone, includeDocuments, includeComputer
networkSize, tags, notes
```

## Créer des packs (prompts)

Voir [`docs/sse/prompts-packs-modeles-mission.md`](../../../docs/sse/prompts-packs-modeles-mission.md) :
prompt ChatGPT (contenu JSON + SQF) et prompt Cursor (intégration dépôt).

## Modèles 3D : écrans de salle d'opérations (TOC)

Sans rapport avec les modèles SSE ci-dessus : ce sont les objets 3D `COMSPEC_ATAK_Screen_*` (`Sources/addons/main/toc_screens3d.hpp`),
écrits en MLOD par `tools/model_toc_screens.py` (pur Python) dans `Sources/addons/main/data/toc/`, avec leurs aperçus Eden.

    python tools/model_toc_screens.py            # modèles, textures, matières, aperçus
    NO_TEX=1 ONLY=toc_curved,toc_136 RENDER_DIR=dossier python tools/model_toc_screens.py   # quelques modèles, grands rendus face + dos

Règles communes : origine au sol, dos contre le mur (Y = 0), milieu en largeur ; écrans suspendus : origine sur le dessus des
platines de plafond. LOD visuelles 0 / 2 / 6, ombre, géométrie (boîtes convexes, masse, `autocenter=0`), tir et vue. Une
sélection par image (`screen`, ou `panel1` à `panelN` dans l'ordre des index de `comspec_screenPanels`), UV 0 à 1 sur la
partie visible (bloc de plusieurs dalles : UV proportionnelles, l'image passe derrière les jointures), verre éteint d'origine.
Deux objets identiques se touchent sans jour si leurs origines sont espacées de la largeur hors tout.

| Classe `COMSPEC_ATAK_Screen_` | Modèle | Image (m) | Largeur hors tout (m) | Images (index) |
| --- | --- | --- | --- | --- |
| `Wall2x2` / `Wall2x2_Quad` | `toc_wall_2x2` / `_quad` | 2,43 x 1,37 | 2,4572 | 1 / 4 dalles |
| `Wall3x2` / `Wall3x2_Mixed` | `toc_wall_3x2` / `_mixed` | 3,65 x 1,37 | 3,6738 | 6 dalles / bloc 2x2 + 2 dalles |
| `Wall4x3` | `toc_wall_4x3` | 4,87 x 2,06 | 4,8904 | 0 = bloc 3x3 de gauche (16:9), 1 à 3 = colonne de droite (haut vers bas) |
| `Wall4x3_Panels` | `toc_wall_4x3_panels` | 4,87 x 2,06 | 4,8904 | 12 dalles, ordre de lecture |
| `Wall5x3` | `toc_wall_5x3` | 6,08 x 2,06 | 6,1070 | 0 = bloc 3x3 central, 1 à 3 = colonne gauche, 4 à 6 = colonne droite |
| `Wall5x3_Panels` | `toc_wall_5x3_panels` | 6,08 x 2,06 | 6,1070 | 15 dalles, ordre de lecture |
| `Wall6x4` | `toc_wall_6x4` | 7,30 x 2,75 | 7,3236 | 0 = bloc 4x4 central (220 pouces), 1 à 4 = colonne gauche, 5 à 8 = colonne droite |
| `Wall6x4_Zones` | `toc_wall_6x4_zones` | 7,30 x 2,75 | 7,3236 | six blocs 2x2 (110 pouces), 0 à 2 en haut, 3 à 5 en bas |
| `98` / `98_Stand` / `98_Ceiling` | `toc_98_wall` / `_stand` / `toc_98_ceiling` | 2,17 x 1,22 | 2,1935 | 1 |
| `136` / `136_Stand` | `toc_136_wall` / `_stand` | 3,011 x 1,694 | 3,0270 | 1 |
| `Led220` / `Led220_Truss` | `toc_led_220_wall` / `_truss` | 4,870 x 2,740 | 4,8784 | 1 |
| `65_Ceiling` | `toc_65_ceiling` | 1,44 x 0,81 | 1,4590 | 1 |
| `65_Ceiling2` / `65_Ceiling3` | `toc_65_ceiling_2` / `_3` | 2 ou 3 x (1,44 x 0,81) | 2,926 / 4,389 | 2 / 3 dalles, de gauche à droite |
| `65_Ceiling3_Arc` | `toc_65_ceiling_3_arc` | 3 x (1,44 x 0,81) | 4,354 | 3 dalles, latérales tournées de 15° |
| `ClockStrip` / `NamePlate` | `toc_clock_strip` / `toc_name_plate` | 1,12 x 0,28 / 0,86 x 0,215 (4:1) | 1,16 / 0,90 | 1 |
| `LedText8` / `LedText8_Ceiling` | `toc_led_text_8` / `_ceiling` | 2,40 x 0,30 (8:1) | 2,44 | 1 |
| `LedText16` / `LedText16_Ceiling` | `toc_led_text_16` / `_ceiling` | 4,80 x 0,30 (16:1) | 4,84 | 1 |
| `Curved` / `Curved_Wall` | `toc_curved_floor` / `toc_curved_wall` | arc 7,20 x 2,025 (32:9), rayon 7 | 7,01 | 0 = zone centrale 16:9, 1 = colonne gauche 8:9, 2 = colonne droite 8:9 |
| `Curved_Duo` / `Curved_Wall_Duo` | `toc_curved_floor_duo` / `toc_curved_wall_duo` | idem | 7,01 | 0 = moitié gauche 16:9, 1 = moitié droite 16:9 |

Pourquoi ces découpes : une texture d'interface n'existe qu'en puissances de 2 (2048 x 1024, 2048 x 512, 2048 x 256,
4096 x 256). Une image unique ne se pose donc proprement que sur une face 16:9, 4:1, 8:1 ou 16:1. Les murs 4x3 (64:27),
5x3 (80:27), 6x4 (8:3) et l'écran incurvé (32:9) sont découpés en zones 16:9 (et 8:9 pour les colonnes de l'incurvé), sans
aucune déformation ; le bandeau long est en 16:1 (4,80 x 0,30 m) et non en 12:1 pour la même raison.

Grand écran incurvé : la surface d'image est continue (48 segments de 15 cm, sommets partagés, normales lissées, aucun joint
entre les zones), UV déroulées le long de l'arc. Sur pieds, la face est à 0,90 m du mur au milieu de l'arc et à 1,81 m aux
bords (les semelles restent devant le mur) ; mural, 0,27 m et 1,18 m.

Contrôles après toute modification du générateur :

1. `python tools/model_toc_screens.py` sans erreur ; `git status` : les modèles et aperçus non concernés ne changent pas.
2. Regarder les aperçus (`*_preview.jpg`) et les grands rendus (`RENDER_DIR`), face et dos : pas de face manquante (une face
   mal orientée laisse un trou), image dans le bon sens, haut de l'image en haut.
3. Chaque sélection du modèle est dans `hiddenSelections[]` de la classe et dans `data/toc/model.cfg` (`sections[]`), sinon
   l'image ne se pose pas.
4. `hemtt build` depuis `Sources` : « Binarized N files » sans avertissement de modèle (les deux seules erreurs connues sont
   `fn_screenCast.sqf:486` et `fn_slideWebShow.sqf:17`).
5. Nouvelle classe : `units[]` du `CfgPatches`, nom FR + EN (`stringtable.xml`, `i18n/en.txt`, `localisation/template/`),
   liste « classes » de `fn_tocScreen`.

## Modèles 3D : postes PC fixes (TOC)

Objets `COMSPEC_ATAK_TocPC_*` (`Sources/addons/main/toc_pc.hpp`), écrits par `tools/model_toc_pc.py` avec les primitives, les
matières et le moteur d'aperçu de `tools/model_toc_screens.py` (importé tel quel ; les textures PAA ne sont pas réécrites).

    python tools/model_toc_pc.py                       # modèles et aperçus
    RENDER_DIR=dossier python tools/model_toc_pc.py    # grands rendus de contrôle, face et dos

Un poste est UN objet : unité centrale, moniteur(s), clavier mécanique (touches une à une en LOD 0), souris et tapis, câbles.
Origine sur le plateau de la table (0,8 m sur le bureau du jeu), milieu du poste en largeur ; l'opérateur est du côté de la
direction de l'objet, les dalles lui font face. Index des images : de gauche à droite vu par l'opérateur.

| Classe | Modèle | Dalles | Encombrement l x p x h (m) | Images (index) |
| --- | --- | --- | --- | --- |
| `COMSPEC_ATAK_TocPC_1` | `toc_pc_1` | un 27" (0,598 x 0,336) sur pied, tour | 1,03 x 0,79 x 0,47 | 1 (`screen`) |
| `COMSPEC_ATAK_TocPC_2` | `toc_pc_2` | deux 27" sur bras double, tour | 1,58 x 0,79 x 0,48 | 0 = gauche, 1 = droite |
| `COMSPEC_ATAK_TocPC_3` | `toc_pc_3` | trois 27" en arc (latéraux à 22°), mini-PC | 1,76 x 0,75 x 0,48 | 0 = gauche, 1 = centre, 2 = droite |
| `COMSPEC_ATAK_TocPC_Curved` | `toc_pc_curved` | 49" incurvé 32:9 (1,196 x 0,336, rayon 1,8), tour | 1,51 x 0,79 x 0,47 | 0 = moitié gauche 16:9, 1 = moitié droite 16:9 |

Ce sont des postes TOC et des écrans TOC à la fois : `comspec_tocPost=1` (lu par `fn_tocPost` : action « PC du poste TOC »,
et par `fn_tocPc` : désignation comme écran à la pose), `comspec_tocPostView` (affichage du moniteur d'index 0),
`comspec_screenPanelViews[]` (affichage par défaut de chaque moniteur ; PIP du poste sur le moniteur principal). Toute classe
portant `comspec_tocPost=1` est prise sans autre liste (modules « Poste opérateur » et « Salle TOC complète », action ACE).
Mêmes contrôles que les écrans (sélections dans `hiddenSelections[]` et `data/toc/model.cfg`, `units[]`, noms FR + EN).

## Modèles 3D : mobilier et périphériques de TOC

Objets `COMSPEC_ATAK_TocDesk_*`, `COMSPEC_ATAK_TocChair`, `COMSPEC_ATAK_TocKeyboard*`, `TocMouse*`, `TocTower*`, `TocMiniPC`,
`TocProp_*` (`Sources/addons/main/toc_furniture.hpp`), écrits par `tools/model_toc_furniture.py` avec les primitives des écrans.
Les postes PC `COMSPEC_ATAK_TocPC_*` reprennent les mêmes pièces (clavier, souris, tapis, unité centrale).

    python tools/model_toc_furniture.py                   # modèles, textures, matières, tools/toc_furniture_manifest.json
    python tools/model_toc_pc.py                          # postes PC
    BLENDER=...blender.exe python tools/render_toc_furniture.py DOSSIER   # rendus de contrôle et aperçus Eden

**Règle d'orientation** (une seule, table `_facing` de `fn_tocHallBuild`, mode « facing ») : l'opérateur est du côté de la
direction d'une console, d'un poste PC, d'un clavier (+Y : espace des genoux, façade de la baie PC, face des moniteurs) ;
le fauteuil regarde dans sa direction. Pour une salle dont le mur d'images est dans la direction D : écrans, postes PC et
consoles à D + 180, fauteuils à D. Un poste PC se pose sur une console à la même position et à la même direction, à
0,74 m (`comspec_tocDeskTop`). Module « Poste opérateur » : la flèche du module est le regard de l'opérateur.

Console droite : plateau de y = -0,45 (fond) à +0,40 (opérateur), un poste tous les 1,6 m, points mémoire `pc_N`, `mon_N`,
`chair_N`, `bay_N`, `name_N` (N de la gauche de l'opérateur vers sa droite). Console en arc : centre de l'arc à 1,25 m côté
opérateur, rayons 0,86 et 1,70 m, demi-angle 40° ; elle porte le poste à trois écrans et l'écran incurvé
(`comspec_tocDesk`). Pièce d'angle : origine au centre de l'arc, bouts sur le demi-axe -Y et le demi-axe +X.
LOD : 0 / 2 / 6, ombre, géométrie (boîtes convexes, `autocenter=0`), vue, tir, mémoire. Fauteuil : `ace_sitting`.

| Classe | Modèle | Objet | L x P x H (m) | Faces LOD 0 / 1 / 2 |
|---|---|---|---|---|
| `COMSPEC_ATAK_TocProp_CableTray` | `toc_furn_cabletray` | Panier à câbles sous plateau | 0.80 x 0.15 x 0.10 | 228 / 156 / 6 |
| `COMSPEC_ATAK_TocChair` | `toc_furn_chair` | Fauteuil d'opérateur TOC | 0.66 x 0.66 x 1.12 | 756 / 422 / 176 |
| `COMSPEC_ATAK_TocDesk_Corner` | `toc_furn_desk_corner` | Console TOC, angle | 1.15 x 1.15 x 0.74 | 198 / 114 / 68 |
| `COMSPEC_ATAK_TocDesk_Curved` | `toc_furn_desk_curved` | Console TOC en arc | 2.19 x 1.04 x 0.79 | 342 / 163 / 66 |
| `COMSPEC_ATAK_TocDesk_Double` | `toc_furn_desk_double` | Console TOC 2 postes | 3.20 x 0.85 x 0.79 | 659 / 168 / 62 |
| `COMSPEC_ATAK_TocDesk_Single` | `toc_furn_desk_single` | Console TOC 1 poste | 1.60 x 0.85 x 0.79 | 412 / 118 / 44 |
| `COMSPEC_ATAK_TocDesk_Triple` | `toc_furn_desk_triple` | Console TOC 3 postes | 4.80 x 0.85 x 0.79 | 906 / 218 / 80 |
| `COMSPEC_ATAK_TocProp_Handset` | `toc_furn_handset` | Combiné radio de table | 0.10 x 0.36 x 0.06 | 171 / 27 / 12 |
| `COMSPEC_ATAK_TocProp_Headset` | `toc_furn_headset` | Casque micro | 0.20 x 0.19 x 0.18 | 400 / 196 / 42 |
| `COMSPEC_ATAK_TocKeyboard` | `toc_furn_kbd` | Clavier AZERTY | 0.44 x 0.13 x 0.02 | 573 / 11 / 7 |
| `COMSPEC_ATAK_TocKeyboard_Backlit` | `toc_furn_kbd_backlit` | Clavier rétroéclairé | 0.44 x 0.13 x 0.02 | 573 / 11 / 7 |
| `COMSPEC_ATAK_TocKeyboard_Qwerty` | `toc_furn_kbd_qwerty` | Clavier QWERTY | 0.44 x 0.13 x 0.02 | 568 / 11 / 7 |
| `COMSPEC_ATAK_TocKeyboard_TKL` | `toc_furn_kbd_tkl` | Clavier compact AZERTY | 0.36 x 0.13 x 0.02 | 478 / 11 / 7 |
| `COMSPEC_ATAK_TocMiniPC` | `toc_furn_minipc` | Mini-PC | 0.18 x 0.18 x 0.05 | 58 / 7 / 6 |
| `COMSPEC_ATAK_TocMouse` | `toc_furn_mouse` | Souris sans fil | 0.06 x 0.12 x 0.04 | 123 / 36 / 5 |
| `COMSPEC_ATAK_TocMouse_Pad` | `toc_furn_mouse_pad` | Souris et tapis de bureau | 0.80 x 0.30 x 0.04 | 171 / 42 / 11 |
| `COMSPEC_ATAK_TocProp_Mug` | `toc_furn_mug` | Tasse à café | 0.12 x 0.08 x 0.10 | 158 / 88 / 12 |
| `COMSPEC_ATAK_TocProp_Notepad` | `toc_furn_notepad` | Bloc-notes et stylo | 0.20 x 0.22 x 0.01 | 44 / 40 / 10 |
| `COMSPEC_ATAK_TocTower_SFF` | `toc_furn_sff` | Unité centrale compacte | 0.10 x 0.29 x 0.32 | 54 / 39 / 6 |
| `COMSPEC_ATAK_TocTower_Under` | `toc_furn_sff_under` | Unité centrale sous plateau | 0.45 x 0.29 x 0.14 | 114 / 99 / 42 |
| `COMSPEC_ATAK_TocDesk_Briefing` | `toc_furn_table_brief` | Table de briefing TOC | 2.40 x 1.10 x 0.74 | 184 / 112 / 40 |
| `COMSPEC_ATAK_TocTower` | `toc_furn_tower` | Station de travail, tour | 0.21 x 0.45 x 0.47 | 70 / 51 / 6 |

Textures : `toc_top` (stratifié gris clair), `toc_mesh` (résille, feutre), atlas `toc_kit_co` 2048 x 1024 (légendes AZERTY et
QWERTY, clavier rétroéclairé, façades d'unité centrale, porte-nom, bloc-notes), matière lumineuse `toc_kit_glow`.

## Modèles 3D : premiers écrans TOC refaits, projection, PC portable

Les premiers écrans (`COMSPEC_ATAK_Screen_Tripod`, `_Dual`, `_Desk`, `_Wall`), les toiles (`COMSPEC_ATAK_ProjScreen`,
`_ProjScreen_Wall`), le vidéoprojecteur (`COMSPEC_ATAK_Projector`) et le PC portable du poste (`COMSPEC_ATAK_TocLaptop`)
étaient des objets du jeu de base réhabillés. Ce sont des modèles du mod, écrits par `tools/model_toc_legacy.py` avec les
primitives, les matières et le moteur d'aperçu de `tools/model_toc_screens.py` (importé tel quel ; les textures PAA ne sont
pas réécrites ; seule matière ajoutée : `toc_canvas.rvmat`, toile blanche mate). Classes dans
`Sources/addons/main/toc_screens_legacy.hpp` (le portable reste dans `config.cpp`). **Les noms de classes ne changent
pas** : missions et sauvegardes de salle TOC retrouvent leurs écrans.

    python tools/model_toc_legacy.py                       # modèles, toc_canvas.rvmat, aperçus
    ONLY=toc_desk RENDER_DIR=dossier python tools/model_toc_legacy.py    # quelques modèles, grands rendus face et dos

| Classe | Modèle | Dalle(s) visibles (m) | Hors tout l x p x h (m) | Sélections (index) |
| --- | --- | --- | --- | --- |
| `COMSPEC_ATAK_Screen_Desk` | `toc_desk_27` | 27" 0,598 x 0,336 sur pied | 0,61 x 0,20 x 0,47 | `screen` (0) |
| `COMSPEC_ATAK_Screen_Desk2` (nouvelle) | `toc_desk_27x2` | deux 27" sur bras | 1,23 x 0,24 x 0,47 | `panel1` gauche, `panel2` droite |
| `COMSPEC_ATAK_Screen_Desk3` (nouvelle) | `toc_desk_27x3` | trois 27", latéraux à 20° | 1,77 x 0,37 x 0,47 | `panel1` à `panel3`, de gauche à droite |
| `COMSPEC_ATAK_Screen_Tripod` | `toc_tripod_65` | 65" de terrain 1,439 x 0,809, bas à 1,13 | 1,50 x 1,00 x 1,97 | `screen` (0) |
| `COMSPEC_ATAK_Screen_Dual` | `toc_tripod_dual_43` | deux 43" 0,952 x 0,536, bas à 1,34 | 1,96 x 1,00 x 1,88 | `screen` (0), `screen2` (1, recopiée) |
| `COMSPEC_ATAK_Screen_Wall` | `toc_tv_55` | 55" 1,210 x 0,680, mural | 1,23 x 0,08 x 0,70 | `screen` (0) |
| `COMSPEC_ATAK_Screen_Wall75` (nouvelle) | `toc_tv_75` | 75" 1,660 x 0,934, mural | 1,68 x 0,08 x 0,96 | `screen` (0) |
| `COMSPEC_ATAK_ProjScreen` | `toc_proj_screen_tripod` | toile 80" 1,771 x 0,996, bas à 1,10 | 1,87 x 0,91 x 2,15 | `canvas` (la toile, pas l'image) |
| `COMSPEC_ATAK_ProjScreen_Wall` | `toc_proj_screen_wall` | toile tendue 100" 2,214 x 1,245 | 2,30 x 0,04 x 1,34 | `canvas` |
| `COMSPEC_ATAK_Projector` | `toc_projector` | — | 0,34 x 0,31 x 0,12 | — |
| `COMSPEC_ATAK_Projector_Ceiling` (nouvelle) | `toc_projector_ceiling` | — (potence de plafond) | 0,34 x 0,31 x 0,25 | — |
| `COMSPEC_ATAK_TocLaptop` | `toc_laptop` | portable durci, écran 13,3" | 0,37 x 0,30 x 0,29 | `screen` (allumé, sans image) |

Règles propres à ces modèles :

- **Origine** : comme les objets du jeu qu'ils remplacent, ils sont centrés par le moteur (pas de `autocenter=0` dans la
  géométrie) : l'origine est le milieu de la boîte englobante, les positions ACE et de portage sont données autour du
  centre. Les hauteurs hors tout visent celles des anciens objets (trépied 2,0 m, double 1,9 m, tableau sur trépied
  2,1 m) pour qu'un objet déjà posé dans une mission ne flotte ni ne s'enfonce : ces hauteurs sont **estimées** (les boîtes
  englobantes du jeu n'ont pas été relevées), à vérifier en jeu. Seul `toc_projector_ceiling` (classe nouvelle) a son
  origine sur le dessus de la platine de plafond.
- **Face** : l'écran regarde +Y, comme tous les écrans du mod.
- **Dalle à l'index 0** (`screen`) : c'est l'index gardé dans les sauvegardes des anciens écrans. Double écran : deuxième
  dalle recopiée (`comspec_screenMirror[]={1}`), comme avant.
- **Toiles** : `comspec_projBoard=1`, `comspec_screen=0`. L'image n'est jamais sur la toile : `fn_projector` mesure la face
  plane par des rayons sur la géométrie (la toile est une boîte plane, le mât du trépied est à plus de 10 cm derrière) et
  pose devant elle `COMSPEC_ATAK_ProjSurface`, seulement quand un projecteur allumé l'éclaire. Ne pas mettre de relief sur
  la face de la toile dans la géométrie.
- **Vidéoprojecteur** : objectif centré sur la face avant (`fn_projector` prend le milieu de la face de la boîte
  englobante tournée vers la toile) ; lueur et faisceau : lumières de `fn_projector` (« fx »), rien dans le modèle.
- Sélections retexturables déclarées dans `data/toc/model.cfg`. Test : `tests/Unit/AtakTocLegacyScreensAssetTest.php`.
