class CfgPatches {
    class comspec_overwatch_main {
        name = "COMSPEC Overwatch Main";
        units[] = {};
        weapons[] = {};
        requiredVersion = 1.0;
        requiredAddons[] = {"cba_main", "cba_xeh", "A3_UI_F"};
        author = "COMSPEC";
        version = 1.41;
        versionStr = "1.4.2";
        versionAr[] = {1, 4, 1};
    };
};

class CfgMods {
    class COMSPEC_Overwatch {
        dir = "@COMSPECOverwatch";
        name = "COMSPEC Overwatch (BÊTA)";
        picture = "\z\comspec_overwatch\addons\main\img\comspec_atak_logo.paa";
        logo = "\z\comspec_overwatch\addons\main\img\comspec_atak_logo.paa";
        logoOver = "\z\comspec_overwatch\addons\main\img\comspec_atak_logo.paa";
        logoSmall = "\z\comspec_overwatch\addons\main\img\comspec_atak_logo.paa";
        actionName = "Website";
        action = "https://athena.ttrd.fr/public";
        overview = "Accès anticipé — Reliez Arma 3 à Athena : carte tactique, messagerie, tablette et téléphone.";
        tooltip = "COMSPEC Overwatch · BÊTA";
        author = "COMSPEC";
    };
};

// Logo splash via DisplayLoad (SQF) — évite Member already defined sur LoadingStart.
#include "CfgEventHandlers.hpp"
