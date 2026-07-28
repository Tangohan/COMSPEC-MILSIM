class CfgPatches {
    class comspec_overwatch_mavik_compat {
        name = "COMSPEC Overwatch Mavic Compat";
        units[] = {};
        weapons[] = {};
        requiredVersion = 2.0;
        // Charge uniquement si Mavic est present, et APRES lui
        requiredAddons[] = {"cba_main", "cba_settings", "cba_xeh", "Mavic_Core"};
        author = "COMSPEC";
        version = 1.41;
        versionStr = "1.4.2";
        versionAr[] = {1, 4, 1};
    };
};

class CfgFunctions {
    class Mavic {
        class functions {
            // Remplace la boucle amont non protegee (postInit)
            class handleConnect {
                file = "\z\comspec_overwatch\addons\mavik_compat\fn_handleConnectSafe.sqf";
                postInit = 1;
            };
        };
    };
};

#include "CfgEventHandlers.hpp"
