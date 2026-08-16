#include "script_component.hpp"

class CfgPatches {
    class comspec_sse_debug {
        name = "COMSPEC SSE - Debug Instrumentation";
        units[] = {};
        weapons[] = {};
        requiredVersion = REQUIRED_VERSION;
        requiredAddons[] = {
            "comspec_sse_main",
            "cba_main",
            "cba_xeh",
            "cba_settings"
        };
        author = "COMSPEC";
        VERSION_CONFIG;
    };
};

class CfgFunctions {
    class comspec_debug {
        tag = "comspec_debug";

        class debug {
            file = "z\comspec_sse\addons\debug\functions";
            class log {};
            class enter {};
            class exit {};
            class addACEActionToClass {};
            class registerEventHandler {};
            class snapshot {};
            class watchdog {};
            class breadcrumb {};
            class guardOnce {};
            class aceStats {};
            class initSettings {};
            class isSafeMode {};
            class isModuleEnabled {};
            class perfWarn {};
            class exception {};
        };
    };
};

#include "CfgEventHandlers.hpp"
