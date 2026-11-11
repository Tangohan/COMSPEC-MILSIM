/*
    Registre des TYPES DE DOCUMENTS (CfgComspecDocuments) : les quatre papiers intégrés du SSE. Une extension (addon
    tiers qui dépend de comspec_sse_identity) ajoute ses propres classes ici et se présente dans
    CfgComspecDocumentExtensions : voir mod/@COMSPEC_SSE/extensions/example_documents/README.md (chaque champ expliqué).
    Lu par comspec_sse_fnc_idDocTypes ; mise en page posée en jeu par fn_idViewerOnLoad aux mêmes coordonnées que
    tools/gen_id_assets.py (fractions du document : x sur la largeur, y sur la hauteur ; valeur à y + 0,04 sous l'étiquette).

    Classe = clé du type (minuscules, lettres/chiffres/_), portée par le document (doc.type) et le registre.
      displayName / displayNameEn   nom affiché (FR / EN)
      category   identity | travel | permit | professional | pass | other   (groupes du sélecteur)
      item       classe CfgWeapons de l'objet vierge ; ses exemplaires sont les classes <item>_001… (gen_id_instances.py)
      icon       image d'inventaire / du sélecteur     ratio   largeur / hauteur (ID-1 1,585 ; TD3 1,42 ; A6 1,414)
      sides      1 (recto seul) ou 2       countries[]   codes de pays servis (vide : tous)     mrz  "" | td1 | td3
      mrzSide / mrzY / mrzStep   côté, première ligne et pas des lignes MRZ (fractions)
      extension  classe de CfgComspecDocumentExtensions (vide : intégré au SSE)     chip = 1 : document à puce (N2)
      class recto / verso :
        texture        chemin PAA ; %1 = code pays en minuscules (repli : zone de style eu/am/ot, puis alt)
        class photo { x; y; w; h; }   cadre photo (absent : pas de photo)     class holo { x; y; w; }   hologramme
        class fields { class <nom> { key; x; y; w; h; transform; font; color; text; input; label; default; } }
          key        clé du document (last, first, sex, nat, dob, pob, number, expires, issued, authority, address,
                     profession, rank, force, country, countryName ; ou une clé propre au document)
          x y w h    position et largeur, hauteur de ligne (fractions)     transform  "" | upper | dmy (AAAA-MM-JJ -> JJ.MM.AAAA)
          font       "" (RobotoCondensed) | name (nom du titulaire, police des faux) | mono (chasse fixe)   color  #rrggbb
          text       texte fixe (ex. "P", "B") au lieu d'une clé      input = 1 : champ saisi par le Zeus (label, default)
*/
#define DOC_VALUE_H 0.062
#define DOC_FIELD(NAME,KEY,X,Y,W) class NAME { key = KEY; x = X; y = Y; w = W; h = DOC_VALUE_H; }
#define DOC_FIELD_T(NAME,KEY,X,Y,W,TR) class NAME { key = KEY; x = X; y = Y; w = W; h = DOC_VALUE_H; transform = TR; }

class CfgComspecDocuments {
    class id_card {
        displayName = "Carte d'identité";
        displayNameEn = "Identity card";
        category = "identity";
        item = "COMSPEC_ID_Card";
        icon = "\z\comspec_sse\addons\identity\data\icon_card_ca.paa";
        ratio = 1.585;
        sides = 2;
        countries[] = {};
        mrz = "td1";
        mrzSide = "verso";
        mrzY = 0.60;
        mrzStep = 0.115;
        extension = "";
        class recto {
            texture = "\z\comspec_sse\addons\identity\data\tpl_card_%1_recto_co.paa";
            class photo { x = 0.045; y = 0.215; w = 0.265; h = 0.57; };
            class holo { x = 0.23; y = 0.63; w = 0.13; };
            class fields {
                class last { key = "last"; x = 0.34; y = 0.245; w = 0.48; h = DOC_VALUE_H; transform = "upper"; font = "name"; };
                class first { key = "first"; x = 0.34; y = 0.365; w = 0.48; h = DOC_VALUE_H; font = "name"; };
                DOC_FIELD(sex, "sex", 0.34, 0.485, 0.10);
                DOC_FIELD(nat, "nat", 0.45, 0.485, 0.18);
                DOC_FIELD_T(dob, "dob", 0.64, 0.485, 0.30, "dmy");
                DOC_FIELD(pob, "pob", 0.34, 0.605, 0.58);
                class number { key = "number"; x = 0.34; y = 0.725; w = 0.28; h = DOC_VALUE_H; color = "#8c1414"; };
                DOC_FIELD_T(expires, "expires", 0.64, 0.725, 0.30, "dmy");
            };
        };
        class verso {
            texture = "\z\comspec_sse\addons\identity\data\tpl_card_%1_verso_co.paa";
            class fields {
                DOC_FIELD(address, "address", 0.05, 0.10, 0.58);
                DOC_FIELD(authority, "authority", 0.05, 0.25, 0.58);
                DOC_FIELD_T(issued, "issued", 0.05, 0.40, 0.28, "dmy");
                DOC_FIELD(profession, "profession", 0.36, 0.40, 0.36);
                // Pays réels seulement (valeur vide ailleurs, rien n'est posé) : taille, numéro personnel imprimé.
                DOC_FIELD(height, "height", 0.66, 0.25, 0.30);
                class personal { key = "personal"; x = 0.66; y = 0.10; w = 0.32; h = 0.042; font = "mono"; };
            };
        };
    };

    // Titre de séjour (pays réels : Titre de séjour, Aufenthaltstitel, Permesso di soggiorno…) : carte ID-1 à puce, MRZ TD1
    // (code « IR », « AR » en Allemagne). Gabarit de la carte du pays, titre et étiquettes posés en jeu.
    class residence_permit: id_card {
        displayName = "Titre de séjour";
        displayNameEn = "Residence permit";
        item = "COMSPEC_ID_ResidencePermit";
        icon = "\z\comspec_sse\addons\identity\data\icon_residence_ca.paa";
        chip = 1;
        class recto: recto {
            class fields {
                class last { key = "last"; x = 0.34; y = 0.245; w = 0.48; h = DOC_VALUE_H; transform = "upper"; font = "name"; };
                class first { key = "first"; x = 0.34; y = 0.365; w = 0.48; h = DOC_VALUE_H; font = "name"; };
                DOC_FIELD(sex, "sex", 0.34, 0.485, 0.10);
                DOC_FIELD(nat, "nat", 0.45, 0.485, 0.18);
                DOC_FIELD_T(dob, "dob", 0.64, 0.485, 0.30, "dmy");
                DOC_FIELD(permit, "permit", 0.34, 0.605, 0.58);
                class number { key = "number"; x = 0.34; y = 0.725; w = 0.28; h = DOC_VALUE_H; color = "#8c1414"; };
                DOC_FIELD_T(expires, "expires", 0.64, 0.725, 0.30, "dmy");
            };
        };
    };

    // Certificat d'immatriculation (carte grise, Zulassungsbescheinigung Teil I, V5C, registration) : feuillet A6, sans
    // photo ni MRZ ; plaque et numéro de châssis (VIN, clé nord-américaine juste) tirés pour le titulaire.
    class vehicle_registration {
        displayName = "Certificat d'immatriculation";
        displayNameEn = "Vehicle registration";
        category = "permit";
        item = "COMSPEC_ID_VehicleRegistration";
        icon = "\z\comspec_sse\addons\identity\data\icon_vreg_ca.paa";
        ratio = 1.414;
        sides = 1;
        countries[] = {};
        mrz = "";
        extension = "";
        class recto {
            texture = "\z\comspec_sse\addons\identity\data\tpl_vreg_%1_co.paa";
            class fields {
                class plate { key = "plate"; x = 0.05; y = 0.215; w = 0.42; h = 0.065; font = "mono"; };
                DOC_FIELD_T(firstReg, "firstReg", 0.55, 0.215, 0.40, "dmy");
                class holder { key = "holder"; x = 0.05; y = 0.355; w = 0.90; h = DOC_VALUE_H; font = "name"; };
                DOC_FIELD(address, "address", 0.05, 0.495, 0.90);
                DOC_FIELD(vehicle, "vehicle", 0.05, 0.635, 0.45);
                class vin { key = "vin"; x = 0.55; y = 0.635; w = 0.42; h = 0.055; font = "mono"; };
                class number { key = "number"; x = 0.05; y = 0.775; w = 0.45; h = DOC_VALUE_H; color = "#8c1414"; };
                DOC_FIELD_T(issued, "issued", 0.55, 0.775, 0.40, "dmy");
                class authority { key = "authority"; x = 0.05; y = 0.905; w = 0.90; h = 0.05; };
            };
        };
    };

    class military_id: id_card {
        displayName = "Carte militaire";
        displayNameEn = "Military ID";
        item = "COMSPEC_ID_MilitaryCard";
        icon = "\z\comspec_sse\addons\identity\data\icon_military_ca.paa";
        class recto: recto {
            texture = "\z\comspec_sse\addons\identity\data\tpl_military_%1_recto_co.paa";
            class fields {
                class last { key = "last"; x = 0.34; y = 0.245; w = 0.48; h = DOC_VALUE_H; transform = "upper"; font = "name"; };
                class first { key = "first"; x = 0.34; y = 0.365; w = 0.48; h = DOC_VALUE_H; font = "name"; };
                DOC_FIELD(rank, "rank", 0.34, 0.485, 0.28);
                DOC_FIELD_T(dob, "dob", 0.64, 0.485, 0.30, "dmy");
                DOC_FIELD(force, "force", 0.34, 0.605, 0.58);
                class number { key = "number"; x = 0.34; y = 0.725; w = 0.28; h = DOC_VALUE_H; color = "#8c1414"; };
                DOC_FIELD_T(expires, "expires", 0.64, 0.725, 0.30, "dmy");
            };
        };
        class verso: verso {
            texture = "\z\comspec_sse\addons\identity\data\tpl_card_%1_verso_co.paa";
        };
    };

    class passport {
        displayName = "Passeport";
        displayNameEn = "Passport";
        category = "travel";
        item = "COMSPEC_ID_Passport";
        icon = "\z\comspec_sse\addons\identity\data\icon_passport_ca.paa";
        ratio = 1.42;
        sides = 1;
        countries[] = {};
        mrz = "td3";
        mrzSide = "recto";
        mrzY = 0.80;
        mrzStep = 0.085;
        extension = "";
        class recto {
            texture = "\z\comspec_sse\addons\identity\data\tpl_passport_%1_co.paa";
            class photo { x = 0.04; y = 0.145; w = 0.275; h = 0.52; };
            class holo { x = 0.22; y = 0.52; w = 0.12; };
            class fields {
                class ptype { text = "P"; x = 0.36; y = 0.175; w = 0.08; h = DOC_VALUE_H; };
                DOC_FIELD(code, "country", 0.45, 0.175, 0.14);
                class number { key = "number"; x = 0.60; y = 0.175; w = 0.36; h = DOC_VALUE_H; color = "#8c1414"; };
                class last { key = "last"; x = 0.36; y = 0.285; w = 0.60; h = DOC_VALUE_H; transform = "upper"; font = "name"; };
                class first { key = "first"; x = 0.36; y = 0.395; w = 0.60; h = DOC_VALUE_H; font = "name"; };
                DOC_FIELD(nat, "nat", 0.36, 0.505, 0.28);
                DOC_FIELD_T(dob, "dob", 0.66, 0.505, 0.30, "dmy");
                DOC_FIELD(sex, "sex", 0.36, 0.615, 0.10);
                DOC_FIELD(pob, "pob", 0.47, 0.615, 0.50);
                DOC_FIELD_T(issued, "issued", 0.36, 0.715, 0.18, "dmy");
                DOC_FIELD_T(expires, "expires", 0.55, 0.715, 0.18, "dmy");
                class authority { key = "authority"; x = 0.74; y = 0.715; w = 0.24; h = 0.05; };
            };
        };
    };

    class driving_license {
        displayName = "Permis de conduire";
        displayNameEn = "Driving licence";
        category = "permit";
        item = "COMSPEC_ID_DrivingLicense";
        icon = "\z\comspec_sse\addons\identity\data\icon_license_ca.paa";
        ratio = 1.585;
        sides = 2;
        countries[] = {};
        mrz = "td1";
        mrzSide = "verso";
        mrzY = 0.64;
        mrzStep = 0.105;
        extension = "";
        class recto {
            texture = "\z\comspec_sse\addons\identity\data\tpl_license_%1_recto_co.paa";
            class photo { x = 0.045; y = 0.215; w = 0.265; h = 0.57; };
            class fields {
                class last { key = "last"; x = 0.34; y = 0.245; w = 0.60; h = DOC_VALUE_H; transform = "upper"; font = "name"; };
                class first { key = "first"; x = 0.34; y = 0.355; w = 0.60; h = DOC_VALUE_H; font = "name"; };
                DOC_FIELD_T(dob, "dob", 0.34, 0.465, 0.22, "dmy");
                DOC_FIELD(pob, "pob", 0.57, 0.465, 0.40);
                DOC_FIELD_T(issued, "issued", 0.34, 0.575, 0.22, "dmy");
                DOC_FIELD_T(expires, "expires", 0.58, 0.575, 0.30, "dmy");
                class authority { key = "authority"; x = 0.34; y = 0.685; w = 0.60; h = 0.055; };
                class number { key = "number"; x = 0.34; y = 0.795; w = 0.32; h = DOC_VALUE_H; color = "#8c1414"; };
                class cat { text = "B"; x = 0.70; y = 0.795; w = 0.2; h = DOC_VALUE_H; };
            };
        };
        class verso {
            texture = "\z\comspec_sse\addons\identity\data\tpl_license_verso_co.paa";
            class fields {
                class issued { key = "issued"; x = 0.22; y = 0.30; w = 0.18; h = 0.05; transform = "dmy"; };
                class expires { key = "expires"; x = 0.42; y = 0.30; w = 0.18; h = 0.05; transform = "dmy"; };
            };
        };
    };
};

// Extensions de documents : le SSE lui-même n'en déclare aucune ; chaque addon tiers ajoute sa classe (voir README de
// l'extension d'exemple). Menu « SSE ROLEPLAY · Extensions » : activer / désactiver chacune (serveur, synchronisé).
class CfgComspecDocumentExtensions {};
