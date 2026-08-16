#include "script_component.hpp"

class CfgPatches {
    class comspec_sse_core {
        name = "COMSPEC SSE - Core";
        units[] = {};
        weapons[] = {};
        requiredVersion = REQUIRED_VERSION;
        requiredAddons[] = {"comspec_sse_main", "cba_main", "cba_xeh", "cba_settings"};
        author = "COMSPEC";
        VERSION_CONFIG;
    };
};

class CfgFunctions {
    class comspec_sse {
        tag = "comspec_sse";

        class core {
            file = "z\comspec_sse\addons\core\functions";
            class log {};
            class getLog {};
            class showLog {};
            class hash {};
            class idToken {};
            class generateUID {};
            class createDataModel {};
            class getData {};
            class setData {};
            class getPair {};
            class setPair {};
            class getSection {};
            class setSection {};
            class getSeed {};
            class setSeed {};
            class getState {};
            class setState {};
            class setIdentity {};
            class syncIdentityBridgeVars {};
            class setDigitalData {};
            class linkEntities {};
            class getLinks {};
            class makeSearchable {};
            class hasEquipment {};
            class getEquipmentAliases {};
            class resolveEquipment {};
            class calcQuality {};
            class qualityLabel {};
            class revealFog {};
            class serializeData {};
            class deserializeData {};
            class initSettings {};
            class requestServerOp {};
            class serverHandleOp {};
            class raiseSseEvent {};
            class onSseEvent {};
            class markVehicleSection {};
            class ensureDebugApi {};
        };
    };
};

#include "CfgEventHandlers.hpp"
