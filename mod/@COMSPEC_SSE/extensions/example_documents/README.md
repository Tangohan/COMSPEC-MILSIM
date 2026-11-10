# Extension de documents d'exemple — COMSPEC SSE

Un petit mod tiers qui ajoute deux papiers au COMSPEC SSE : une **carte de presse** et un **laissez-passer**. Copiez ce
dossier pour créer votre propre extension : il suffit de déclarer vos types dans `CfgComspecDocuments`, de vous présenter
dans `CfgComspecDocumentExtensions` et de fournir vos textures. Le SSE fait le reste : délivrance, visionneuse,
contrôle, Zeus, éditeur SSE, attribut Eden, menu « SSE ROLEPLAY · Extensions ».

```
example_documents/
  $PBOPREFIX$              z\comspec_sse_ext\addons\example_documents  (préfixe du PBO : chemin des textures)
  config.cpp               CfgPatches, CfgWeapons, CfgComspecDocuments, CfgComspecDocumentExtensions
  CfgWeapons_instances.hpp exemplaires (généré par gen_id_instances.py, voir plus bas)
  stringtable.xml          noms des objets (FR / EN)
  data/                    textures PAA (gabarits, icônes), générées par tools/gen_textures.py
  tools/gen_textures.py    dessine les gabarits avec les briques de mod/@COMSPEC_SSE/tools/gen_id_assets.py
  mod.cpp                  fiche du mod (copiée à côté du PBO)
  build_extension.bat      construit le PBO
```

## 1. Déclarer les objets (`CfgWeapons`)

Chaque type de papier est un objet d'inventaire **vierge** (ce qu'un Zeus prend à l'Arsenal), qui hérite de
`COMSPEC_ID_Base` déclaré par le SSE. Trois déclarations externes suffisent, sans rien redéfinir :

```cpp
class CBA_MiscItem;
class CBA_MiscItem_ItemInfo;
class COMSPEC_ID_Base;

class CfgWeapons {
    class COMSPEC_EXT_PressCard: COMSPEC_ID_Base {
        scope = 2;
        displayName = "$STR_COMSPEC_EXTDOC_PressCard";
        picture = "\z\comspec_sse_ext\addons\example_documents\data\icon_press_card_ca.paa";
    };
    #include "CfgWeapons_instances.hpp"
};
```

Arma ne garde aucune donnée sur un objet : chaque **exemplaire** délivré prend une classe libre (`COMSPEC_EXT_PressCard_001`…)
que le registre du serveur relie au document. Ces classes sont générées depuis la racine de `mod/@COMSPEC_SSE` :

```
python tools/gen_id_instances.py --extension extensions/example_documents --stock COMSPEC_EXT_PressCard=64,COMSPEC_EXT_LaissezPasser=64
```

Le script écrit `CfgWeapons_instances.hpp` dans le dossier de l'extension (une ligne par exemplaire, `scope = 1` :
invisibles dans l'Arsenal). `--check` échoue si le fichier versionné diffère. Le stock (64 ici) est le nombre
d'exemplaires qu'une mission peut délivrer pour ce type ; le SSE le lit en comptant les classes `<item>_001…`.

## 2. Décrire chaque type (`CfgComspecDocuments`)

Une classe par type ; **son nom est la clé** du type (minuscules, lettres, chiffres, `_`), portée par chaque document
délivré (`press_card`). Les coordonnées sont des **fractions du document** : `x` sur la largeur, `y` sur la hauteur.
Le SSE pose la valeur en jeu à ces coordonnées ; l'étiquette peinte dans la texture se place juste au-dessus (à `y - 0,04`).

| Champ | Exemple | Rôle |
|---|---|---|
| `displayName` / `displayNameEn` | `"Carte de presse"` / `"Press card"` | Nom affiché (téléphone en français / en anglais). |
| `category` | `"professional"` | Groupe du sélecteur : `identity`, `travel`, `permit`, `professional`, `pass`, `other`. |
| `item` | `"COMSPEC_EXT_PressCard"` | Classe `CfgWeapons` de l'objet vierge ; les exemplaires sont `<item>_001`… |
| `icon` | `"...\data\icon_press_card_ca.paa"` | Image de l'inventaire et du sélecteur (256 × 256, alpha). |
| `ratio` | `1.585` | Largeur / hauteur : carte ID-1 `1.585`, passeport TD3 `1.42`, A6 `1.414`. |
| `sides` | `1` | `1` recto seul, `2` recto et verso (il faut alors une `class verso`). |
| `countries[]` | `{}` | Codes de pays servis (`{"ALT", "TNA"}`) ; vide : tous les pays. |
| `mrz` | `""` | Bande lisible machine : `""` aucune, `"td1"` (3 lignes, cartes), `"td3"` (2 lignes, passeports). |
| `mrzSide`, `mrzY`, `mrzStep` | `"verso"`, `0.60`, `0.115` | Côté, première ligne et pas des lignes MRZ (fractions) ; inutiles sans MRZ. |
| `extension` | `"example_documents"` | Classe de votre fiche dans `CfgComspecDocumentExtensions` (vide : papier intégré au SSE). |

### `class recto` / `class verso`

| Champ | Exemple | Rôle |
|---|---|---|
| `texture` | `"...\data\tpl_press_card_recto_co.paa"` | Gabarit PAA (1024 × 512, étiré au `ratio`). Avec `%1` dans le nom (`tpl_card_%1_recto_co.paa`), `%1` est remplacé par le code pays en minuscules, avec repli sur la zone de style (`eu`, `am`, `ot`) puis `alt`. Sans `%1` : le même gabarit sert à tous les pays. |
| `class photo { x; y; w; h; }` | `{ x = 0.045; y = 0.215; w = 0.265; h = 0.57; }` | Cadre de la photo du titulaire. Pas de classe : document sans photo (laissez-passer). |
| `class holo { x; y; w; }` | `{ x = 0.23; y = 0.63; w = 0.13; }` | Hologramme (anti-contrefaçon, visible en inclinant). Facultatif. |
| `class fields { class … }` | voir ci-dessous | Les valeurs posées sur le gabarit. |

### `class fields { class <nom> { … } }`

| Champ | Exemple | Rôle |
|---|---|---|
| `key` | `"last"` | Clé du document à afficher : `last`, `first`, `sex`, `nat`, `dob`, `pob`, `number`, `expires`, `issued`, `authority`, `address`, `profession`, `rank`, `force`, `country`, `countryName` ; ou **une clé propre à votre document** (`media`, `area`), remplie par le Zeus (`input = 1`). |
| `x`, `y`, `w`, `h` | `0.34`, `0.245`, `0.48`, `0.062` | Position, largeur et hauteur de ligne (fractions). `h = 0.062` convient à une carte. |
| `transform` | `"upper"` | `""` tel quel, `"upper"` majuscules, `"dmy"` date `AAAA-MM-JJ` affichée `JJ.MM.AAAA`. |
| `font` | `"name"` | `""` RobotoCondensed, `"name"` police du nom (celle que les faux imitent mal), `"mono"` chasse fixe. |
| `color` | `"#8c1414"` | Couleur du texte (`#rrggbb`) ; par défaut encre sombre. |
| `text` | `"P"` | Texte fixe à la place d'une clé (type de passeport, catégorie de permis). |
| `input` | `1` | Champ **saisi par le Zeus** dans le formulaire « Donner des papiers » et l'éditeur SSE. |
| `label` | `"Média"` | Libellé du champ saisi (sinon la clé). |
| `default` | `"Agence Altis Presse"` | Valeur proposée au Zeus. |

Exemple complet (carte de presse) :

```cpp
class CfgComspecDocuments {
    class press_card {
        displayName = "Carte de presse"; displayNameEn = "Press card";
        category = "professional"; item = "COMSPEC_EXT_PressCard";
        icon = "\z\comspec_sse_ext\addons\example_documents\data\icon_press_card_ca.paa";
        ratio = 1.585; sides = 1; countries[] = {}; mrz = ""; extension = "example_documents";
        class recto {
            texture = "\z\comspec_sse_ext\addons\example_documents\data\tpl_press_card_recto_co.paa";
            class photo { x = 0.045; y = 0.215; w = 0.265; h = 0.57; };
            class holo { x = 0.23; y = 0.63; w = 0.13; };
            class fields {
                class last  { key = "last";  x = 0.34; y = 0.245; w = 0.48; h = 0.062; transform = "upper"; font = "name"; };
                class first { key = "first"; x = 0.34; y = 0.365; w = 0.48; h = 0.062; font = "name"; };
                class media { key = "media"; x = 0.34; y = 0.485; w = 0.58; h = 0.062; input = 1; label = "Média"; default = "Agence Altis Presse"; };
                class number { key = "number"; x = 0.34; y = 0.725; w = 0.28; h = 0.062; color = "#8c1414"; };
                class expires { key = "expires"; x = 0.64; y = 0.725; w = 0.30; h = 0.062; transform = "dmy"; };
            };
        };
    };
};
```

Un type peut hériter d'un autre (`class military_id: id_card`) pour ne changer que la texture et quelques champs, comme
le fait le SSE dans `addons/identity/CfgComspecDocuments.hpp`.

## 3. Se présenter (`CfgComspecDocumentExtensions`)

Une classe par extension ; son nom est la valeur du champ `extension` de vos types. C'est elle que le Zeus voit dans
le menu « SSE ROLEPLAY · Extensions » et qu'il peut activer ou désactiver.

| Champ | Exemple | Rôle |
|---|---|---|
| `name` | `"Documents d'exemple"` | Nom affiché. |
| `author` | `"COMSPEC"` | Auteur. |
| `version` | `"1.0"` | Version affichée. |
| `description` | `"Carte de presse et laissez-passer : …"` | Une phrase pour le volet de droite. |
| `documents[]` | `{"press_card", "laissez_passer"}` | Clés de vos types (ajoutées à celles qui portent `extension = "<classe>"`). |
| `preview` | `"...\data\tpl_press_card_recto_co.paa"` | Image d'aperçu (sinon le recto du premier modèle). |

```cpp
class CfgComspecDocumentExtensions {
    class example_documents {
        name = "Documents d'exemple"; author = "COMSPEC"; version = "1.0";
        description = "Carte de presse et laissez-passer : exemple d'extension de documents.";
        documents[] = {"press_card", "laissez_passer"};
        preview = "\z\comspec_sse_ext\addons\example_documents\data\tpl_press_card_recto_co.paa";
    };
};
```

## 4. Dessiner les textures (`tools/gen_textures.py`)

Les gabarits (1024 × 512, `_co`) et les icônes (256 × 256, `_ca`) sont dessinés en Python avec Pillow, en réutilisant
les briques de `mod/@COMSPEC_SSE/tools/gen_id_assets.py` (fond guilloché, en-tête, étiquettes, cadre photo, puce,
mention « SIMULATION — DOCUMENT DE JEU », conversion PAA par `hemtt utils paa convert`, repli `paa_dxt`) :

```
cd mod/@COMSPEC_SSE/extensions/example_documents
python tools/gen_textures.py            # écrit data\tpl_press_card_recto_co.paa, data\tpl_laissez_passer_recto_co.paa,
                                        #        data\icon_press_card_ca.paa, data\icon_laissez_passer_ca.paa
python tools/gen_textures.py --png out  # garde aussi les PNG (pour vérifier la mise en page)
```

Les étiquettes peintes doivent se trouver à `y - 0,04` au-dessus de chaque champ de `CfgComspecDocuments` : le script
et le `config.cpp` partagent les mêmes coordonnées. Pour un papier par pays, nommez le fichier avec le code en
minuscules (`tpl_xxx_alt_recto_co.paa`, `tpl_xxx_tna_recto_co.paa`…) et mettez `%1` dans `texture`.

## 5. Construire et charger

```
build_extension.bat
```

Le script reprend AddonBuilder (Arma 3 Tools, chemin en tête du script, comme `mod/@COMSPEC_SSE/build_pbo.bat`),
copie les sources sans `README.md`, `tools/` ni `mod.cpp`, et produit :

```
mod/@COMSPEC_SSE/extensions/@COMSPEC_SSE_ExampleDocuments/
  mod.cpp
  addons/comspec_sse_ext_example_documents.pbo
```

Chargez le mod après ses dépendances : `-mod=@CBA_A3;@ace;@COMSPEC_ATAK_Native;@COMSPEC_SSE;@COMSPEC_SSE_ExampleDocuments`
(`requiredAddons[] = {"comspec_sse_identity", "cba_main"}` dans `CfgPatches`). Pour le Workshop, signez le PBO avec
votre propre clé.

Vérification sans Arma : copiez le dossier dans un projet HEMTT (`.hemtt/project.toml` : `name = "comspec_sse_ext"`,
`prefix = "z\\comspec_sse_ext\\addons"`, `mainprefix = "z"`) et lancez `hemtt check`.

## 6. Activer en jeu (« SSE ROLEPLAY · Extensions »)

Une extension chargée est **active par défaut**. Réglages CBA, catégorie **SSE ROLEPLAY › Extensions** :

- **Extensions de documents** (`comspec_sse_docExt_enabled`) : décoché, seuls les quatre papiers intégrés sont servis ;
- **Extensions désactivées** (`comspec_sse_docExt_off`) : classes coupées au lancement, séparées par des virgules
  (`example_documents`) ;
- **Annoncer les extensions au lancement** (`comspec_sse_docExt_notify`) : notification ATAK du Zeus.

En mission, la fenêtre **SSE ROLEPLAY · Extensions** s'ouvre depuis le module Zeus « COMSPEC · Extensions », le bouton du
menu Échap (en haut à droite) ou le menu COMSPEC SSE d'Eden (consultation). Elle liste les extensions détectées (nom,
auteur, version, nombre de modèles, état), montre la description, les modèles et l'aperçu, et le bouton
**Activer / Désactiver** (Zeus ou administrateur) envoie le choix au serveur, qui le garde d'une partie à l'autre
(`profileNamespace` du serveur) et le diffuse à tous (`comspec_sse_docExtOff`).

Une extension désactivée n'est plus proposée (Zeus, éditeur SSE, IA, attribut Eden) ; les papiers déjà délivrés restent
lisibles. Depuis un script : `["list", [""]] call comspec_sse_fnc_idDocTypes` donne les clés servies,
`["enabled", "example_documents"]` l'état d'une extension.
