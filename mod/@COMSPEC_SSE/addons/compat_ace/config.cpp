#include "script_component.hpp"

class CfgPatches {
    class comspec_sse_compat_ace {
        name = "COMSPEC SSE - Compat ACE Medical / Dogtags";
        units[] = {};
        weapons[] = {};
        requiredVersion = REQUIRED_VERSION;
        // Soft-load : ace_dogtags n’est PAS requis — la passerelle s’active si le PBO est présent.
        requiredAddons[] = {
            "comspec_sse_core",
            "comspec_sse_generator",
            "comspec_sse_interaction"
        };
        author = "COMSPEC";
        VERSION_CONFIG;
    };
};

class CfgFunctions {
    class comspec_sse {
        tag = "comspec_sse";
        class compat_ace {
            file = "z\comspec_sse\addons\compat_ace\functions";
            class aceDogtagIsPresent {};
            class aceDogtagFromSse {};
            class aceDogtagSync {};
            class aceDogtagInstallHooks {};
            class aceDogtagOnCheck {};
        };
    };
};

class Extended_PreInit_EventHandlers {
    class comspec_sse_compat_ace {
        init = "call compile preprocessFileLineNumbers 'z\comspec_sse\addons\compat_ace\XEH_preInit.sqf'";
    };
};

class Extended_PostInit_EventHandlers {
    class comspec_sse_compat_ace {
        clientInit = "call compile preprocessFileLineNumbers 'z\comspec_sse\addons\compat_ace\XEH_postInitClient.sqf'";
        serverInit = "call compile preprocessFileLineNumbers 'z\comspec_sse\addons\compat_ace\XEH_postInitServer.sqf'";
    };
};
