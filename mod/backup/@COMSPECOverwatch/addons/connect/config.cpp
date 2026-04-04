class CfgPatches {
    class comspec_overwatch_connect {
        name = "COMSPEC Overwatch Connect";
        units[] = {};
        weapons[] = {};
        requiredVersion = 1.0;
        requiredAddons[] = {"comspec_overwatch_main", "cba_main", "cba_xeh", "cba_settings"};
        author = "COMSPEC";
        version = "1.0";
    };
};

class CfgFunctions {
    class comspec_overwatch_connect {
        tag = "comspec_overwatch_connect";
        class connect {
            file = "\z\comspec_overwatch\addons\connect\functions";
            class connect {};
            class updatePosition {};
            class sendIntel {};
            class initACE {};
            class pollMarkersAndUnits {};
            class submitChat {};
        };
    };
};

#include "ui_base.hpp"
#include "display.hpp"
#include "CfgEventHandlers.hpp"
