# COMSPEC Base SOF

Mod indépendant (sans dépendance à l'ATAK natif) : mobilier de base forces spéciales posable dans **Eden et Zeus**,
catégorie **COMSPEC · Base SOF**. Aucune arme ni équipement modélisé : le dépôt d'équipement viendra en custom via ACE.

| Classe | Objet |
|---|---|
| `COMSPEC_SOF_CagePanel_Blue` / `_Black` | Panneau de cage grillagée 1 m x 2,6 m (à assembler) |
| `COMSPEC_SOF_CageDoor_Blue` / `_Black` | Panneau de cage avec porte (Ouvrir / Fermer) |
| `COMSPEC_SOF_Cage4x3_Blue` / `_Black` | Cage complète 4 x 3 x 2,6 m, toit grillagé, porte |
| `COMSPEC_SOF_MeshLocker` | Casier grillagé double porte 1 x 0,6 x 2 m |
| `COMSPEC_SOF_TeamBay` / `_x5` | Alvéole team room en contreplaqué, seule ou rangée de 5 |
| `COMSPEC_SOF_GunRack` | Râtelier d'armes bois 8 places (vide) |
| `COMSPEC_SOF_Shelf` | Étagère acier 4 niveaux |
| `COMSPEC_SOF_Workbench` | Établi 2,2 m avec panneau perforé, étau |
| `COMSPEC_SOF_ToolChest` | Servante d'atelier |
| `COMSPEC_SOF_SignLarge` / `SignSmall` / `SignStand` | Pancartes vierges personnalisables |
| `COMSPEC_SOF_WallFlag` | Drapeau mural vierge personnalisable |

## Bâtiments explorables

| Classe | Bâtiment |
|---|---|
| `COMSPEC_SOF_HQ` | QG 3 niveaux 24 x 14 m : hall vitré, couloir central et bureaux à chaque étage, escalier jusqu'au toit |
| `COMSPEC_SOF_Annex` | Annexe 2 niveaux 18 x 14 m, même plan intérieur sans hall vitré |

- **Portes animées** (action Ouvrir / Fermer, portes vitrées du hall en double battant).
- **Vitres cassables** : chaque vitre a son point de vie, une balle la remplace par du verre brisé.
- **Étages praticables** : LOD roadway sur les dalles, escaliers et toit, collisions par pièce.
- **Destruction** : le bâtiment détruit laisse une ruine (`COMSPEC_SOF_HQ_ruins`, `COMSPEC_SOF_Annex_ruins`).
- Pas encore de LOD *Paths* : l'IA ne connaît pas les positions intérieures.

**Personnaliser une pancarte ou le drapeau** : attribut Eden « Image » = chemin d'une image `.paa` ou `.jpg`
(format 2:1 conseillé), par exemple `images\panneau.paa` dans le dossier de la mission. En script :
`_obj setObjectTextureGlobal [0, "images\panneau.paa"]`.

**Couleur des cages en script** : sélections `frame` (0) et `mesh` (1), par exemple
`_cage setObjectTextureGlobal [0, "#(argb,8,8,3)color(0.2,0.25,0.12,1,co)"]` pour un cadre vert.

## Construire

    cd tools
    python3 gen_sof_textures.py            # textures PNG + rvmats
    python3 model_sof_base.py              # p3d MLOD + vignettes d'éditeur (Blender bpy + Arma 3 Object Builder)
    python3 model_sof_buildings.py         # bâtiments + ruines, data/model.cfg et buildings.hpp générés
    for f in render/*.png; do hemtt utils paa convert $f ../Sources/addons/main/data/$(basename ${f%.png}).paa; done
    python3 ../../COMSPEC_ATAK_Native/tools/pack_pbo.py ../Sources/addons/main ../@COMSPEC_SOF_Base/addons/comspec_sof_main.pbo

Sous Windows, `build_mod.bat` emballe le même dossier avec AddonBuilder (`-packonly`).
