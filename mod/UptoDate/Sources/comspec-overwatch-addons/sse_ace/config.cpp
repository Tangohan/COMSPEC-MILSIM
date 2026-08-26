class CfgPatches {
    class comspec_overwatch_sse_ace {
        name = "COMSPEC Overwatch SSE — Interaction ACE";
        units[] = {};
        weapons[] = {};
        requiredVersion = 1.0;
        // Pas de dépendance dure à ACE : sans ace_interact_menu, Arma afficherait une
        // erreur au démarrage chez les joueurs qui ne le chargent pas. La présence d’ACE
        // est vérifiée au runtime (fn_initSseAce), et la couche se retire en silence.
        requiredAddons[] = {"comspec_overwatch_connect", "cba_main", "cba_xeh", "cba_settings"};
        author = "COMSPEC";
        version = 1.416;
        versionStr = "1.4.16";
        versionAr[] = {1, 4, 16};
    };
};

// Format obligatoire Tag > Category > Function (file = dossier des fn_*.sqf).
class CfgFunctions {
    class comspec_overwatch_sse_ace {
        tag = "comspec_overwatch_sse_ace";
        class sse_ace {
            file = "z\comspec_overwatch\addons\sse_ace\functions";
            class initSseAce {};
            class sseCanExploit {};
            class sseExploitTargetLabel {};
        };
    };
};

#include "CfgEventHandlers.hpp"
