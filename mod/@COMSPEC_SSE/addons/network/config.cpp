#include "script_component.hpp"

class CfgPatches {
    class comspec_sse_network {
        name = "COMSPEC SSE - Network / Athena";
        units[] = {};
        weapons[] = {};
        requiredVersion = REQUIRED_VERSION;
        requiredAddons[] = {"comspec_sse_core", "cba_main"};
        author = "COMSPEC";
        VERSION_CONFIG;
    };
};

class CfgFunctions {
    class comspec_sse {
        tag = "comspec_sse";
        class network {
            file = "z\comspec_sse\addons\network\functions";
            class submitRecord {};
            class buildPayload {};
            class queueOffline {};
            class flushQueue {};
            class isOnline {};
            class sendViaOverwatch {};
            class toJsonApprox {};
            class makeIdempotencyKey {};
            class getCaseReference {};
            class setCaseReference {};
            class buildAthenaPersonPayload {};
            class buildAthenaBiometricsPayload {};
            class buildAthenaDigitalPayload {};
            class submitPersonRecord {};
            class submitBiometricsSim {};
            class submitDigitalAcquisition {};
            class extensionCall {};
            class markTransmitted {};
        };
    };
};

class Extended_PreInit_EventHandlers {
    class comspec_sse_network {
        init = "call compile preprocessFileLineNumbers 'z\comspec_sse\addons\network\XEH_preInit.sqf'";
    };
};

class Extended_PostInit_EventHandlers {
    class comspec_sse_network {
        init = "call compile preprocessFileLineNumbers 'z\comspec_sse\addons\network\XEH_postInit.sqf'";
    };
};
