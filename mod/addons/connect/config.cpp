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
        class connect { file = "functions\fn_connect.sqf"; };
        class playtimeTracker { file = "functions\fn_playtimeTracker.sqf"; };
        class updatePosition { file = "functions\fn_updatePosition.sqf"; };
        class sendIntel { file = "functions\fn_sendIntel.sqf"; };
        class reportIntel { file = "functions\fn_reportIntel.sqf"; };
        class initACE { file = "functions\fn_initACE.sqf"; };
        class submitChat { file = "functions\fn_submitChat.sqf"; };
        class getRadioState { file = "functions\fn_getRadioState.sqf"; };
        class getMedicalState { file = "functions\fn_getMedicalState.sqf"; };
        class getAircraftType { file = "functions\fn_getAircraftType.sqf"; };
        class fillFlightManifest { file = "functions\fn_fillFlightManifest.sqf"; };
        class submitFlightManifest { file = "functions\fn_submitFlightManifest.sqf"; };
        class pilotResponse { file = "functions\fn_pilotResponse.sqf"; };
    };
};

#include "ui_base.hpp"
#include "display.hpp"
#include "display_flight_manifest.hpp"
#include "CfgEventHandlers.hpp"
