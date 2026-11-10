# Document extensions: creating your own types

French version: [extensions_documents.md](extensions_documents.md). Guides index: [index.en.md](index.en.md). In-game management: [menu_extensions.en.md](menu_extensions.en.md).

## What it is for

A **document extension** is a small third-party addon that depends on the SSE and **adds document types** (press card, area pass, access card, NGO badge…) without touching the mod: its PAA textures, its fields, its layout, its countries. Once loaded, its types appear everywhere the SSE offers a type: the **Choisir un document** (Choose a document) window (Zeus, Eden), the Eden **Papiers** (Papers) attribute, the SSE editor (Paper type), the Area AI page (AJOUTER CE PAPIER (ADD THIS PAPER)), the viewer and the check.

Shipped example: `mod/@COMSPEC_SSE/extensions/example_documents/` (config.cpp, textures, README) with a **press card** and a **pass**.

## Structure of an extension

```
@MonExtension/
  addons/
    mon_ext_documents/
      $PBOPREFIX$          e.g. z\mon_ext\addons\documents
      config.cpp
      data/
        presse_recto_%1.paa   (one texture per country code, or a single texture)
        presse_verso.paa
        preview.paa
```

`config.cpp` declares:

1. a `CfgPatches` that **depends on `comspec_sse_identity`** (otherwise the registry is not loaded yet);
2. the inventory objects (`CfgWeapons`) of the blank document and its instances `_001`, `_002`…;
3. the types in `CfgComspecDocuments`;
4. the extension's identity card in `CfgComspecDocumentExtensions`.

## `CfgComspecDocuments` field by field

One class per document type.

| Field | Type | Role | Example |
|---|---|---|---|
| `displayName` | text | French name shown everywhere | `"Carte de presse"` |
| `displayNameEn` | text | English name (phone in English, no stringtable) | `"Press card"` |
| `category` | `identity` · `travel` · `permit` · `professional` · `pass` · `other` | Grouping in the type list | `"professional"` |
| `item` | class | Blank object from `CfgWeapons`; the `<item>_001`… instances are the copies handed out | `"MonExt_PressCard"` |
| `icon` | path | Icon for the list and the inventory | `"\z\mon_ext\addons\documents\data\icon_presse.paa"` |
| `ratio` | number | Width / height: **1.585** ID-1 card, **1.42** passport page, **1.414** A6 sheet | `1.585` |
| `sides` | 1 or 2 | Number of sides (RECTO / VERSO available if 2) | `2` |
| `countries[]` | codes | Allowed issuing countries; **empty: all** | `{"ALT", "TNA"}` |
| `mrz` | `""` · `td1` · `td3` | Machine-readable strip: none, 3 lines (card), 2 lines (passport). Without MRZ, the N1 check answers « Pas de bande MRZ sur ce document. » (No MRZ strip on this document.) | `""` |
| `extension` | class | Class of `CfgComspecDocumentExtensions` that groups this type | `"mon_ext"` |
| `class recto` / `class verso` | sub-classes | Layout of one side (see below) | |

### One side: `class recto { … }`

| Field | Role | Example |
|---|---|---|
| `texture` | Background of the side; `%1` is replaced by the **lowercase country code** | `"\z\mon_ext\...\presse_recto_%1.paa"` |
| `class photo { x; y; w; h; }` | ID photo area (coordinates from 0 to 1, relative to the side) | `x = 0.06; y = 0.2; w = 0.26; h = 0.55;` |
| `class holo { x; y; w; }` | Security hologram area (absent on a crude forgery) | `x = 0.78; y = 0.62; w = 0.14;` |
| `class fields { … }` | The printed fields | |

### A printed field: `class fields { class <name> { … } }`

| Field | Role | Example |
|---|---|---|
| `key` | Document data to print: `last`, `first`, `dob`, `pob`, `number`, `expiry`, `issued`, `authority`, `country`, `sex`, `nationality`…; or the name of a custom field (`input = 1`) | `"last"` |
| `x`, `y`, `w`, `h` | Position and size (0 to 1, relative to the side) | `x = 0.36; y = 0.22; w = 0.5; h = 0.07;` |
| `transform` | `upper` (UPPERCASE), `dmy` (date dd.mm.yyyy), `""` as is | `"upper"` |
| `font` | `""` document font, `name` name font, `mono` machine font | `"mono"` |
| `color` | Text colour `{r, g, b, a}` | `{0.1, 0.1, 0.15, 1}` |
| `text` | **Fixed** text (printed label), instead of a data field | `"PRESSE / PRESS"` |
| `input` | `1`: field **entered by Zeus** in « Choisir un document » (and in the Eden attribute); `default` = suggested value | `input = 1; default = "Agence Altis Presse";` |

### `CfgComspecDocumentExtensions`

| Field | Role | Example |
|---|---|---|
| `name` | Name shown in the Extensions menu and in parentheses after the types | `"Documents d'exemple"` |
| `author` | Author | `"COMSPEC"` |
| `version` | Version | `"1.0.0"` |
| `description` | One sentence | `"Carte de presse et laissez-passer de zone."` |
| `documents[]` | The extension's `CfgComspecDocuments` classes | `{"press_card", "zone_pass"}` |
| `preview` | Preview texture for the Extensions menu | `"\z\mon_ext\...\preview.paa"` |

## Full example (press card)

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
    class COMSPEC_ID_Card;                       // SSE base object (ID-1 card)
    class MonExt_PressCard: COMSPEC_ID_Card {
        displayName = "Carte de presse";
        picture = "\z\mon_ext\addons\documents\data\icon_presse.paa";
    };
    class MonExt_PressCard_001: MonExt_PressCard { scope = 1; };
    class MonExt_PressCard_002: MonExt_PressCard { scope = 1; };
    // ... as many copies as cards to hand out in one session
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
        countries[] = {};            // all countries
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

## Step by step: testing the extension

1. Copy `extensions/example_documents/` under your own name, rename the `$PBOPREFIX$`, the classes and the textures.
2. Build the PBO (hemtt or Addon Builder) and load the mod **after** @COMSPEC_SSE.
3. In game, Esc › **SSE ROLEPLAY · Extensions**: the extension appears (name, author, version, number of templates, preview); check that it is **activée** (enabled).
4. Zeus › **Donner des papiers** (Give papers): country, then « Carte de presse (Mon extension) » (Press card (My extension)); fill in « Média » (Media); preview on the right; target: yourself.
5. ACE self menu › **Mes papiers › Ouvrir mes papiers** (My papers › Open my papers): check the layout, RECTO / VERSO (FRONT / BACK), ZOOM.
6. Get yourself checked: without MRZ or chip, only N0 (visual), N3 (database) and N4 (face) apply.

## Common pitfalls

- **`requiredAddons[]` must contain `comspec_sse_identity`**: otherwise your classes may be read before the SSE's (`COMSPEC_ID_Card` inheritance not found).
- **`_001`… instances**: plan enough of them; beyond the stock, the document stays virtual (logged to the RPT).
- **Texture per country**: with `%1`, every country in `countries[]` (or every loaded country if empty) must have its texture; otherwise make a single texture without `%1`.
- **Coordinates 0 to 1**: test with the viewer's ZOOM; a field that overflows is clipped.
- **`mrz = "td1"`** assumes an MRZ area in the texture (three lines at the bottom); the SSE generates the lines and the check digits.
- **Safeguards**: no reproduction of real templates, coats of arms or official emblems; keep the « SIMULATION — DOCUMENT DE JEU » (SIMULATION — GAME DOCUMENT) marking on every side.
- The extension's name is shown **in parentheses** after each type: keep it short.
- An extension disabled in the Extensions menu no longer offers its types; documents already handed out remain readable.

## Athena

Athena does not know your textures, but recognises the **type** by its class: a transmitted check carries `document.type = "press_card"` and the record (RELEVER (RECORD)) notes the type, the country and the number. Custom fields (`input = 1`) go with the record.
