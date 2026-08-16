#include "script_component.hpp"

class CfgPatches {
    class comspec_sse_digital {
        name = "COMSPEC SSE - Digital Exploitation";
        units[] = {};
        weapons[] = {};
        requiredVersion = REQUIRED_VERSION;
        requiredAddons[] = {
            "comspec_sse_core",
            "comspec_sse_debug",
            "comspec_sse_generator",
            "comspec_sse_interaction",
            "comspec_sse_evidence",
            "comspec_sse_ui",
            "ace_interact_menu"
        };
        author = "COMSPEC";
        VERSION_CONFIG;
    };
};

class CfgFunctions {
    class comspec_sse {
        tag = "comspec_sse";
        class digital {
            file = "z\comspec_sse\addons\digital\functions";
            class initDigitalACE {};
            class exploitDevice {};
            class exploitComputer {};
            class extractContacts {};
            class extractMessages {};
            class extractCalls {};
            class extractPhotos {};
            class extractLocations {};
            class extractFull {};
            class extractSystemInfo {};
            class extractUsers {};
            class extractFiles {};
            class extractBrowser {};
            class extractMail {};
            class extractUsbHistory {};
            class extractCredentials {};
            class getDeviceSummary {};
            class getComputerSummary {};
            class generateUSB {};
            class collectMedia {};
            class revealDigitalFog {};
            class openDigitalPanel {};
        };
    };
};

class Extended_PostInit_EventHandlers {
    class comspec_sse_digital {
        clientInit = "call compile preprocessFileLineNumbers 'z\comspec_sse\addons\digital\XEH_postInitClient.sqf'";
    };
};
