#include "script_component.hpp"

/*
    Tutoriels du SSE :
      - manuel de terrain (Field Manual, catégorie COMSPEC SSE), lisible dans Eden, en jeu et au Zeus ;
      - menu « COMSPEC SSE » dans la barre de menus d'Eden ;
      - Zeus : manuel et exercice complet posé en un clic ;
      - ACE : « Guide SSE (manuel) ».
*/
class CfgPatches {
    class comspec_sse_guide {
        name = "COMSPEC SSE - Tutoriels";
        units[] = {};
        weapons[] = {};
        requiredVersion = REQUIRED_VERSION;
        requiredAddons[] = {"comspec_sse_main", "comspec_sse_analysis", "comspec_sse_campaign", "comspec_sse_zeus", "3DEN", "cba_xeh"};
        author = "COMSPEC";
        VERSION_CONFIG;
    };
};

class CfgFunctions {
    class comspec_sse {
        tag = "comspec_sse";
        class guide {
            file = "z\comspec_sse\addons\guide\functions";
            class guideExercise {};
        };
    };
};

class Extended_PostInit_EventHandlers {
    class comspec_sse_guide {
        init = "call compile preprocessFileLineNumbers 'z\comspec_sse\addons\guide\XEH_postInit.sqf'";
    };
};

#define HINT_IMG "\a3\ui_f\data\igui\cfg\simpleTasks\types\intel_ca.paa"

class CfgHints {
    class COMSPEC_SSE {
        displayName = "$STR_COMSPEC_SSE_CFG_25ECDF92AF";
        logicalOrder = 90;
        class Start {
            displayName = "$STR_COMSPEC_SSE_CFG_7549FAA67C";
            displayNameShort = "$STR_COMSPEC_SSE_CFG_7549FAA67C";
            description = "$STR_COMSPEC_SSE_CFG_9A715CB0F1";
            tip = "Chaque chapitre de ce manuel correspond à une étape réelle d'un SSE.";
            arguments[] = {};
            image = HINT_IMG;
            noImage = 0;
        };
        class Bio {
            displayName = "$STR_COMSPEC_SSE_CFG_B2EF3D8634";
            displayNameShort = "$STR_COMSPEC_SSE_CFG_5427FD158C";
            description = "$STR_COMSPEC_SSE_CFG_ACDEF74322";
            tip = "Une identification certaine confirme l'appartenance du sujet à son organisation.";
            arguments[] = {};
            image = HINT_IMG;
            noImage = 0;
        };
        class Capture {
            displayName = "$STR_COMSPEC_SSE_CFG_F503CC4364";
            displayNameShort = "$STR_COMSPEC_SSE_CFG_3D7AF5EC00";
            description = "$STR_COMSPEC_SSE_CFG_25C0AE9942";
            tip = "Le modèle du sac se règle dans les réglages CBA (COMSPEC SSE, Biométrie).";
            arguments[] = {};
            image = HINT_IMG;
            noImage = 0;
        };
        class Evidence {
            displayName = "$STR_COMSPEC_SSE_CFG_B80CA83E9F";
            displayNameShort = "$STR_COMSPEC_SSE_CFG_F4F05DEBDE";
            description = "$STR_COMSPEC_SSE_CFG_B59B00E2C9";
            tip = "« Pièces que je détiens » liste ce que vous portez.";
            arguments[] = {};
            image = HINT_IMG;
            noImage = 0;
        };
        class Links {
            displayName = "$STR_COMSPEC_SSE_CFG_DB6388D385";
            displayNameShort = "$STR_COMSPEC_SSE_CFG_3343043971";
            description = "$STR_COMSPEC_SSE_CFG_54F61CC779";
            tip = "Le Zeus voit tout le réseau, ce que vous ignorez en gris.";
            arguments[] = {};
            image = HINT_IMG;
            noImage = 0;
        };
        class Digital {
            displayName = "$STR_COMSPEC_SSE_CFG_9FA7DC4B0C";
            displayNameShort = "$STR_COMSPEC_SSE_CFG_BFFAB189C7";
            description = "$STR_COMSPEC_SSE_CFG_A873796D42";
            tip = "Le contenu numérique du SSE devient extractible au DL-7 sans rien saisir.";
            arguments[] = {};
            image = HINT_IMG;
            noImage = 0;
        };
        class Campaigns {
            displayName = "$STR_COMSPEC_SSE_CFG_EF527E85F0";
            displayNameShort = "$STR_COMSPEC_SSE_CFG_EF527E85F0";
            description = "$STR_COMSPEC_SSE_CFG_0DA4135C9F";
            tip = "Menu personnel, Campagne en cours : type, mission n°, personnages récurrents, journal.";
            arguments[] = {};
            image = HINT_IMG;
            noImage = 0;
        };
        class Eden {
            displayName = "$STR_COMSPEC_SSE_CFG_D34119DF2D";
            displayNameShort = "$STR_COMSPEC_SSE_CFG_770CB7BEB2";
            description = "$STR_COMSPEC_SSE_CFG_F42128454E";
            tip = "Menu COMSPEC SSE de la barre d'Eden : ce manuel à tout moment.";
            arguments[] = {};
            image = HINT_IMG;
            noImage = 0;
        };
        class Zeus {
            displayName = "$STR_COMSPEC_SSE_CFG_0F926D953A";
            displayNameShort = "$STR_COMSPEC_SSE_CFG_0093EDC3AB";
            description = "$STR_COMSPEC_SSE_CFG_6E79CF4CEF";
            tip = "Pour un drill SOT : campagne temporaire, puis « poser un exercice ici ».";
            arguments[] = {};
            image = HINT_IMG;
            noImage = 0;
        };
        class Custom {
            displayName = "$STR_COMSPEC_SSE_CFG_385FD78DF8";
            displayNameShort = "$STR_COMSPEC_SSE_CFG_385FD78DF8";
            description = "$STR_COMSPEC_SSE_CFG_763969454F";
            tip = "Les rôles ajoutés apparaissent dans les formulaires Zeus.";
            arguments[] = {};
            image = HINT_IMG;
            noImage = 0;
        };
    };
};

// Barre de menus d'Eden : COMSPEC SSE.
class ctrlMenuStrip;
class Display3DEN {
    class Controls {
        class MenuStrip: ctrlMenuStrip {
            class Items {
                items[] += {"COMSPEC_SSE_Menu"};
                class COMSPEC_SSE_Menu {
                    text = "COMSPEC SSE";
                    items[] = {"COMSPEC_SSE_MenuEden", "COMSPEC_SSE_MenuStart", "COMSPEC_SSE_MenuLinks", "COMSPEC_SSE_MenuCampaigns", "COMSPEC_SSE_MenuZeus", "COMSPEC_SSE_MenuCustom"};
                };
                class COMSPEC_SSE_MenuEden { text = "Mise en place d'une mission"; action = "['COMSPEC_SSE', 'Eden'] call BIS_fnc_openFieldManual"; };
                class COMSPEC_SSE_MenuStart { text = "Prise en main (joueurs)"; action = "['COMSPEC_SSE', 'Start'] call BIS_fnc_openFieldManual"; };
                class COMSPEC_SSE_MenuLinks { text = "Organisations et tableau des liens"; action = "['COMSPEC_SSE', 'Links'] call BIS_fnc_openFieldManual"; };
                class COMSPEC_SSE_MenuCampaigns { text = "Campagnes"; action = "['COMSPEC_SSE', 'Campaigns'] call BIS_fnc_openFieldManual"; };
                class COMSPEC_SSE_MenuZeus { text = "Conduite au Zeus"; action = "['COMSPEC_SSE', 'Zeus'] call BIS_fnc_openFieldManual"; };
                class COMSPEC_SSE_MenuCustom { text = "Personnalisation"; action = "['COMSPEC_SSE', 'Custom'] call BIS_fnc_openFieldManual"; };
            };
        };
    };
};
