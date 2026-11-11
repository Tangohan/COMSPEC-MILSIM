/*
    COMSPEC SSE — extension de documents d'exemple : carte de presse et laissez-passer.
    Addon tiers qui dépend de comspec_sse_identity : il déclare ses objets vierges (CfgWeapons, héritant de
    COMSPEC_ID_Base du SSE), leurs exemplaires (CfgWeapons_instances.hpp, généré par tools/gen_id_instances.py du SSE),
    ses types de papiers (CfgComspecDocuments) et se présente dans CfgComspecDocumentExtensions.
    Chaque champ est expliqué dans README.md ; textures par tools/gen_textures.py.
*/
class CfgPatches {
    class comspec_sse_ext_example_documents {
        name = "COMSPEC SSE - Documents d'exemple";
        units[] = {};
        weapons[] = {"COMSPEC_EXT_PressCard", "COMSPEC_EXT_LaissezPasser"};
        requiredVersion = 2.14;
        requiredAddons[] = {"comspec_sse_identity", "cba_main"};
        author = "COMSPEC";
        version = 1.0;
        versionStr = "1.0";
        versionAr[] = {1, 0, 0, 0};
    };
};

// Classes du SSE et de CBA reprises ici (déclarations externes : elles existent déjà, on n'en redéfinit rien).
class CBA_MiscItem_ItemInfo;
class COMSPEC_ID_Base;

class CfgWeapons {
    // Objets vierges : ce qu'un Zeus prend à l'Arsenal. Les exemplaires (…_001 à …_064) héritent de chacun.
    class COMSPEC_EXT_PressCard: COMSPEC_ID_Base {
        scope = 2;
        displayName = "$STR_COMSPEC_EXTDOC_PressCard";
        picture = "\z\comspec_sse_ext\addons\example_documents\data\icon_press_card_ca.paa";
    };
    class COMSPEC_EXT_LaissezPasser: COMSPEC_ID_Base {
        scope = 2;
        displayName = "$STR_COMSPEC_EXTDOC_LaissezPasser";
        picture = "\z\comspec_sse_ext\addons\example_documents\data\icon_laissez_passer_ca.paa";
        model = "\A3\Structures_F\Items\Documents\File2_F.p3d";
        class ItemInfo: CBA_MiscItem_ItemInfo { mass = 0.2; };
    };
    #include "CfgWeapons_instances.hpp"
};

// Types de papiers : mise en page en fractions du document (x sur la largeur, y sur la hauteur). Le SSE pose chaque
// valeur en jeu aux coordonnées données ; les étiquettes sont peintes dans la texture (tools/gen_textures.py).
#define DOC_VALUE_H 0.062
#define DOC_FIELD(NAME,KEY,X,Y,W) class NAME { key = KEY; x = X; y = Y; w = W; h = DOC_VALUE_H; }
#define DOC_FIELD_T(NAME,KEY,X,Y,W,TR) class NAME { key = KEY; x = X; y = Y; w = W; h = DOC_VALUE_H; transform = TR; }

class CfgComspecDocuments {
    class press_card {
        displayName = "Carte de presse";
        displayNameEn = "Press card";
        category = "professional";
        item = "COMSPEC_EXT_PressCard";
        icon = "\z\comspec_sse_ext\addons\example_documents\data\icon_press_card_ca.paa";
        ratio = 1.585;
        sides = 1;
        countries[] = {};
        mrz = "";
        extension = "example_documents";
        class recto {
            // Sans %1 : le même gabarit sert à tous les pays.
            texture = "\z\comspec_sse_ext\addons\example_documents\data\tpl_press_card_recto_co.paa";
            class photo { x = 0.045; y = 0.215; w = 0.265; h = 0.57; };
            class holo { x = 0.23; y = 0.63; w = 0.13; };
            class fields {
                class last { key = "last"; x = 0.34; y = 0.245; w = 0.48; h = DOC_VALUE_H; transform = "upper"; font = "name"; };
                class first { key = "first"; x = 0.34; y = 0.365; w = 0.48; h = DOC_VALUE_H; font = "name"; };
                // Champs propres au document, saisis par le Zeus (input = 1) : libellé et valeur par défaut du formulaire.
                class media { key = "media"; x = 0.34; y = 0.485; w = 0.58; h = DOC_VALUE_H; input = 1; label = "Média"; default = "Agence Altis Presse"; };
                class position { key = "position"; x = 0.34; y = 0.605; w = 0.58; h = DOC_VALUE_H; input = 1; label = "Fonction"; default = "Reporter"; };
                class number { key = "number"; x = 0.34; y = 0.725; w = 0.28; h = DOC_VALUE_H; color = "#8c1414"; };
                DOC_FIELD_T(expires, "expires", 0.64, 0.725, 0.30, "dmy");
            };
        };
    };

    class laissez_passer {
        displayName = "Laissez-passer";
        displayNameEn = "Pass";
        category = "pass";
        item = "COMSPEC_EXT_LaissezPasser";
        icon = "\z\comspec_sse_ext\addons\example_documents\data\icon_laissez_passer_ca.paa";
        ratio = 1.414;
        sides = 1;
        countries[] = {};
        mrz = "";
        extension = "example_documents";
        class recto {
            texture = "\z\comspec_sse_ext\addons\example_documents\data\tpl_laissez_passer_recto_co.paa";
            // Pas de class photo ni de class holo : document sans photo.
            class fields {
                class last { key = "last"; x = 0.08; y = 0.26; w = 0.30; h = DOC_VALUE_H; transform = "upper"; font = "name"; };
                class first { key = "first"; x = 0.08; y = 0.38; w = 0.30; h = DOC_VALUE_H; font = "name"; };
                DOC_FIELD(number, "number", 0.08, 0.50, 0.40);
                class area { key = "area"; x = 0.08; y = 0.62; w = 0.60; h = DOC_VALUE_H; input = 1; label = "Zone autorisée"; default = "Kavala — zone portuaire"; };
                DOC_FIELD_T(issued, "issued", 0.08, 0.74, 0.28, "dmy");
                DOC_FIELD_T(expires, "expires", 0.40, 0.74, 0.28, "dmy");
                DOC_FIELD(authority, "authority", 0.08, 0.86, 0.60);
            };
        };
    };
};

// Fiche de l'extension : menu « SSE ROLEPLAY · Extensions » (nom, auteur, version, description, modèles, aperçu).
class CfgComspecDocumentExtensions {
    class example_documents {
        name = "Documents d'exemple";
        author = "COMSPEC";
        version = "1.0";
        description = "Carte de presse et laissez-passer : exemple d'extension de documents.";
        documents[] = {"press_card", "laissez_passer"};
        // Livrée dans le pack SSE (build_pbo.bat) : marquée intégrée, et coupée tant qu'un Zeus ne l'active pas dans
        // la fenêtre (choix gardé sur le serveur). Une extension tierce n'a besoin d'aucun des deux.
        builtin = 1;
        defaultOff = 1;
        preview = "\z\comspec_sse_ext\addons\example_documents\data\tpl_press_card_recto_co.paa";
    };
};
