class CfgPatches {
    class comspec_overwatch_main {
        name = "COMSPEC Overwatch Main";
        units[] = {};
        weapons[] = {};
        requiredVersion = 1.0;
        requiredAddons[] = {"cba_main"};
        author = "COMSPEC";
        version = 1.20;
        versionStr = "1.2.0";
        versionAr[] = {1, 2, 0};
    };
};

class CfgMods {
    class COMSPEC_Overwatch {
        dir = "@COMSPECOverwatch";
        name = "COMSPEC Overwatch (BÊTA)";
        picture = "\z\comspec_overwatch\addons\connect\img\comspec_atak_logo.paa";
        actionName = "Website";
        action = "https://athena.ttrd.fr/public";
        overview = "Accès anticipé — Reliez Arma 3 à Athena : carte tactique, messagerie, tablette et téléphone.";
        tooltip = "COMSPEC Overwatch · BÊTA";
        author = "COMSPEC";
    };
};

// Logo au démarrage (séquence BI/Arma3/Nvidia) — syntaxe confirmée fonctionnelle par la
// communauté (forums.bohemia.net "Custom Arma 3 game loading graphics [SOLVED]" / r/armadev).
// Ajoute un contrôle en plus dans RscDisplayStart, ne remplace aucun des logos existants.
#include "\a3\ui_f\hpp\defineCommonGrids.inc"

#define COMSPEC_LOGO_SIZE 10
#define COMSPEC_POS_WIDTH(X) ((X) * GUI_GRID_W)
#define COMSPEC_POS_HEIGHT(X) ((X) * GUI_GRID_H)
#define COMSPEC_POS_LEFT_CENTERED(X) ((safezoneW - COMSPEC_POS_WIDTH(X)) / 2)
#define COMSPEC_POS_TOP_CENTERED(X) ((safezoneH - COMSPEC_POS_HEIGHT(X)) / 2)

class RscStandardDisplay;
class RscControlsGroup;
class RscPicture;

class RscDisplayStart: RscStandardDisplay {
    class controls {
        class LoadingStart: RscControlsGroup {
            class controls {
                class COMSPEC_StartLogo: RscPicture {
                    text = "\z\comspec_overwatch\addons\connect\img\comspec_atak_logo.paa";
                    x = COMSPEC_POS_LEFT_CENTERED(COMSPEC_LOGO_SIZE);
                    y = COMSPEC_POS_TOP_CENTERED(COMSPEC_LOGO_SIZE);
                    w = COMSPEC_POS_WIDTH(COMSPEC_LOGO_SIZE);
                    h = COMSPEC_POS_HEIGHT(COMSPEC_LOGO_SIZE);
                };
            };
        };
    };
};
