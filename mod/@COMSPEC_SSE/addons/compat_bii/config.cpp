#include "script_component.hpp"

class CfgPatches {
    class comspec_sse_compat_bii {
        name = "COMSPEC SSE - Compat BII Identifi";
        units[] = {};
        weapons[] = {};
        requiredVersion = REQUIRED_VERSION;
        // Soft-load : BII n’est PAS requis — la passerelle s’active si le PBO est présent.
        requiredAddons[] = {
            "comspec_sse_core",
            "comspec_sse_intel",
            "comspec_sse_biometrics",
            "comspec_sse_interaction"
        };
        author = "COMSPEC";
        VERSION_CONFIG;
    };
};

class CfgFunctions {
    class comspec_sse {
        tag = "comspec_sse";
        class compat_bii {
            file = "z\comspec_sse\addons\compat_bii\functions";
            class biiIsPresent {};
            class biiRegisterEquipment {};
            class biiImportEntityVars {};
            class biiExportEntityVars {};
            class biiRecordToSse {};
            class biiImportScan {};
            class biiImportEvidenceEntry {};
            class biiImportObject {};
            class biiInstallHooks {};
            class biiThreatToProfile {};
        };
    };
};

class Extended_PreInit_EventHandlers {
    class comspec_sse_compat_bii {
        init = "call compile preprocessFileLineNumbers 'z\comspec_sse\addons\compat_bii\XEH_preInit.sqf'";
    };
};

class Extended_PostInit_EventHandlers {
    class comspec_sse_compat_bii {
        clientInit = "call compile preprocessFileLineNumbers 'z\comspec_sse\addons\compat_bii\XEH_postInitClient.sqf'";
        serverInit = "call compile preprocessFileLineNumbers 'z\comspec_sse\addons\compat_bii\XEH_postInitServer.sqf'";
    };
};
