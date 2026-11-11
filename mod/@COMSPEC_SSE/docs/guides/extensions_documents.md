# Extensions de documents : créer ses propres types

Version anglaise : [extensions_documents.en.md](extensions_documents.en.md). Sommaire : [index.md](index.md). Gestion en jeu : [menu_extensions.md](menu_extensions.md).

## À quoi ça sert

Une **extension de documents** est un petit addon tiers qui dépend du SSE et **ajoute des types de documents** (carte de presse, laissez-passer de zone, carte d'accès, badge d'ONG…) sans toucher au mod : ses textures PAA, ses champs, sa mise en page, ses pays. Une fois chargée, ses types apparaissent partout où le SSE propose un type : fenêtre **Choisir un document** (Zeus, Eden), attribut Eden **Papiers**, éditeur SSE (type Papier), page IA de la zone (AJOUTER CE PAPIER), visionneuse et contrôle.

Exemple livré : `mod/@COMSPEC_SSE/extensions/example_documents/` (config.cpp, textures, README) avec une **carte de presse** et un **laissez-passer**. Il est **inclus dans le pack SSE** (`comspec_sse_ext_example_documents.pbo`) et **coupé par défaut** : activez « Documents d'exemple » dans la fenêtre **SSE ROLEPLAY · Extensions** pour l'essayer.

**Tuto intégré** : la même fenêtre a un bouton **Aide** (« Comment ça marche », 10 étapes, FR / EN) qui reprend ce guide en jeu et donne un `config.cpp` minimal commenté à copier dans le presse-papiers (« Copier l'exemple »).

## Structure d'une extension

```
@MonExtension/
  addons/
    mon_ext_documents/
      $PBOPREFIX$          ex. z\mon_ext\addons\documents
      config.cpp
      data/
        presse_recto_%1.paa   (une texture par code pays, ou une texture unique)
        presse_verso.paa
        preview.paa
```

`config.cpp` déclare :

1. un `CfgPatches` qui **dépend de `comspec_sse_identity`** (sinon le registre n'est pas encore chargé) ;
2. les objets d'inventaire (`CfgWeapons`) du document vierge et ses instances `_001`, `_002`… ;
3. les types dans `CfgComspecDocuments` ;
4. la carte d'identité de l'extension dans `CfgComspecDocumentExtensions`.

## `CfgComspecDocuments` champ par champ

Une classe par type de document.

| Champ | Type | Rôle | Exemple |
|---|---|---|---|
| `displayName` | texte | Nom français affiché partout | `"Carte de presse"` |
| `displayNameEn` | texte | Nom anglais (téléphone en anglais, stringtable absente) | `"Press card"` |
| `category` | `identity` · `travel` · `permit` · `professional` · `pass` · `other` | Regroupement dans la liste des types | `"professional"` |
| `item` | classe | Objet vierge de `CfgWeapons` ; les instances `<item>_001`… sont les exemplaires remis | `"MonExt_PressCard"` |
| `icon` | chemin | Icône de la liste et de l'inventaire | `"\z\mon_ext\addons\documents\data\icon_presse.paa"` |
| `ratio` | nombre | Largeur / hauteur : **1.585** carte ID-1, **1.42** page de passeport, **1.414** feuille A6 | `1.585` |
| `sides` | 1 ou 2 | Nombre de faces (RECTO / VERSO disponible si 2) | `2` |
| `countries[]` | codes | Pays émetteurs autorisés ; **vide : tous** | `{"ALT", "TNA"}` |
| `mrz` | `""` · `td1` · `td3` | Bande lisible machine : aucune, 3 lignes (carte), 2 lignes (passeport). Sans MRZ, le contrôle N1 répond « Pas de bande MRZ sur ce document. » | `""` |
| `extension` | classe | Classe de `CfgComspecDocumentExtensions` qui regroupe ce type | `"mon_ext"` |
| `class recto` / `class verso` | sous-classes | Mise en page d'une face (voir ci-dessous) | |

### Une face : `class recto { … }`

| Champ | Rôle | Exemple |
|---|---|---|
| `texture` | Fond de la face ; `%1` est remplacé par le **code pays en minuscules** | `"\z\mon_ext\...\presse_recto_%1.paa"` |
| `class photo { x; y; w; h; }` | Zone de la photo d'identité (coordonnées de 0 à 1, relatives à la face) | `x = 0.06; y = 0.2; w = 0.26; h = 0.55;` |
| `class holo { x; y; w; }` | Zone de l'hologramme de sécurité (absent sur un faux grossier) | `x = 0.78; y = 0.62; w = 0.14;` |
| `class fields { … }` | Les champs imprimés | |

### Un champ imprimé : `class fields { class <nom> { … } }`

| Champ | Rôle | Exemple |
|---|---|---|
| `key` | Donnée du document à imprimer : `last`, `first`, `dob`, `pob`, `number`, `expiry`, `issued`, `authority`, `country`, `sex`, `nationality`… ; ou le nom d'un champ propre (`input = 1`) | `"last"` |
| `x`, `y`, `w`, `h` | Position et taille (0 à 1, relatives à la face) | `x = 0.36; y = 0.22; w = 0.5; h = 0.07;` |
| `transform` | `upper` (MAJUSCULES), `dmy` (date jj.mm.aaaa), `""` tel quel | `"upper"` |
| `font` | `""` police du document, `name` police des noms, `mono` police machine | `"mono"` |
| `color` | Couleur du texte `{r, g, b, a}` | `{0.1, 0.1, 0.15, 1}` |
| `text` | Texte **fixe** (libellé imprimé), à la place d'une donnée | `"PRESSE / PRESS"` |
| `input` | `1` : champ **saisi par le Zeus** dans « Choisir un document » (et dans l'attribut Eden) ; `default` = valeur proposée | `input = 1; default = "Agence Altis Presse";` |

### `CfgComspecDocumentExtensions`

| Champ | Rôle | Exemple |
|---|---|---|
| `name` | Nom affiché dans le menu Extensions et entre parenthèses après les types | `"Documents d'exemple"` |
| `author` | Auteur | `"COMSPEC"` |
| `version` | Version | `"1.0.0"` |
| `description` | Une phrase | `"Carte de presse et laissez-passer de zone."` |
| `documents[]` | Les classes de `CfgComspecDocuments` de l'extension | `{"press_card", "zone_pass"}` |
| `preview` | Texture d'aperçu du menu Extensions | `"\z\mon_ext\...\preview.paa"` |
| `defaultOff` | `1` : extension coupée tant qu'un Zeus ne l'active pas dans la fenêtre (facultatif, utilisé par l'exemple livré) | `defaultOff = 1;` |
| `builtin` | `1` : affichée « livrée avec le SSE » (réservé aux extensions du pack SSE) | `builtin = 1;` |

## Exemple complet (carte de presse)

```cpp
class CfgPatches {
    class mon_ext_documents {
        name = "Mon extension : documents";
        units[] = {}; weapons[] = {"MonExt_PressCard"};
        requiredVersion = 2.14;
        requiredAddons[] = {"comspec_sse_identity"};
        author = "Moi";
    };
};

class CfgWeapons {
    class COMSPEC_ID_Card;                       // objet de base du SSE (carte ID-1)
    class MonExt_PressCard: COMSPEC_ID_Card {
        displayName = "Carte de presse";
        picture = "\z\mon_ext\addons\documents\data\icon_presse.paa";
    };
    class MonExt_PressCard_001: MonExt_PressCard { scope = 1; };
    class MonExt_PressCard_002: MonExt_PressCard { scope = 1; };
    // ... autant d'exemplaires que de cartes à remettre dans une partie
};

class CfgComspecDocumentExtensions {
    class mon_ext {
        name = "Mon extension";
        author = "Moi";
        version = "1.0.0";
        description = "Carte de presse pour les journalistes de la mission.";
        documents[] = {"press_card"};
        preview = "\z\mon_ext\addons\documents\data\preview.paa";
    };
};

class CfgComspecDocuments {
    class press_card {
        displayName = "Carte de presse";
        displayNameEn = "Press card";
        category = "professional";
        item = "MonExt_PressCard";
        icon = "\z\mon_ext\addons\documents\data\icon_presse.paa";
        ratio = 1.585;
        sides = 2;
        countries[] = {};            // tous les pays
        mrz = "";
        extension = "mon_ext";
        class recto {
            texture = "\z\mon_ext\addons\documents\data\presse_recto_%1.paa";
            class photo { x = 0.05; y = 0.18; w = 0.27; h = 0.6; };
            class holo { x = 0.8; y = 0.66; w = 0.13; };
            class fields {
                class title  { text = "CARTE DE PRESSE"; x = 0.36; y = 0.06; w = 0.6; h = 0.08; font = "name"; color[] = {0.55, 0.1, 0.1, 1}; };
                class last   { key = "last";  x = 0.36; y = 0.24; w = 0.6; h = 0.08; transform = "upper"; font = "name"; };
                class first  { key = "first"; x = 0.36; y = 0.34; w = 0.6; h = 0.07; };
                class media  { key = "media"; x = 0.36; y = 0.48; w = 0.6; h = 0.07; input = 1; default = "Agence Altis Presse"; };
                class number { key = "number"; x = 0.36; y = 0.62; w = 0.4; h = 0.06; font = "mono"; };
                class expiry { key = "expiry"; x = 0.36; y = 0.72; w = 0.4; h = 0.06; transform = "dmy"; };
            };
        };
        class verso {
            texture = "\z\mon_ext\addons\documents\data\presse_verso.paa";
            class fields {
                class notice { text = "SIMULATION — DOCUMENT DE JEU"; x = 0.05; y = 0.85; w = 0.9; h = 0.06; color[] = {0.3, 0.3, 0.3, 1}; };
            };
        };
    };
};
```

## Pas à pas : tester l'extension

1. Copiez `extensions/example_documents/` sous votre propre nom, renommez le `$PBOPREFIX$`, les classes et les textures.
2. Construisez le PBO (hemtt ou Addon Builder) et chargez le mod **après** @COMSPEC_SSE.
3. En jeu, Échap › **SSE ROLEPLAY · Extensions** : l'extension apparaît (nom, auteur, version, nombre de modèles, aperçu) ; vérifiez qu'elle est **activée**.
4. Zeus › **Donner des papiers** : pays, puis « Carte de presse (Mon extension) » ; remplissez « Média » ; aperçu à droite ; cible : vous-même.
5. Menu ACE personnel › **Mes papiers › Ouvrir mes papiers** : vérifiez la mise en page, RECTO / VERSO, ZOOM.
6. Faites-vous contrôler : sans MRZ ni puce, seuls N0 (visuel), N3 (base) et N4 (visage) s'appliquent.

## Pièges fréquents

- **La fenêtre affiche « Aucune extension de documents chargée »** : aucun addon ne déclare `CfgComspecDocumentExtensions` (PBO non chargé, ou SSE ancien sans l'exemple intégré). Les papiers intégrés restent disponibles ; le bouton **Ouvrir le tuto** explique la suite.

- **`requiredAddons[]` doit contenir `comspec_sse_identity`** : sinon vos classes peuvent être lues avant celles du SSE (héritage `COMSPEC_ID_Card` introuvable).
- **Instances `_001`…** : prévoyez-en assez ; au-delà du stock, le document reste virtuel (journalisé au RPT).
- **Texture par pays** : avec `%1`, chaque pays de `countries[]` (ou tous les pays chargés si vide) doit avoir sa texture ; sinon faites une texture unique sans `%1`.
- **Coordonnées 0 à 1** : testez au ZOOM de la visionneuse ; un champ qui déborde est coupé.
- **`mrz = "td1"`** suppose une zone MRZ dans la texture (trois lignes en bas) ; le SSE génère les lignes et les chiffres de contrôle.
- **Garde-fous** : aucune reproduction de vrai modèle, d'armoiries ou d'emblème officiel ; gardez la mention « SIMULATION — DOCUMENT DE JEU » sur chaque face.
- Le nom de l'extension s'affiche **entre parenthèses** après chaque type : choisissez-le court.
- Une extension désactivée dans le menu Extensions ne propose plus ses types ; les documents déjà remis restent lisibles.

## Athena

Athena ne connaît pas vos textures, mais reconnaît le **type** par sa classe : un contrôle transmis porte `document.type = "press_card"` et le relevé (RELEVER) note le type, le pays et le numéro. Les champs propres (`input = 1`) partent avec le relevé.
