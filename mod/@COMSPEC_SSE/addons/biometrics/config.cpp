#include "script_component.hpp"

class CfgPatches {
    class comspec_sse_biometrics {
        name = "COMSPEC SSE - Biometrics / SEEK II";
        units[] = {};
        weapons[] = {};
        requiredVersion = REQUIRED_VERSION;
        requiredAddons[] = {
            "comspec_sse_core",
            "comspec_sse_debug",
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
        class biometrics {
            file = "z\comspec_sse\addons\biometrics\functions";
            class initBiometricsACE {};
            class captureFingerprint {};
            class captureIris {};
            class captureFace {};
            class captureDNA {};
            class captureAll {};
            class getBiometricSummary {};
            class identifySubject {};
            class openSeek {};
            class seekOnLoad {};
            class seekCapture {};
            class seekIdentify {};
            class seekTransmit {};
            class seekClose {};
            class compareBiometrics {};
        };
    };
};

class Extended_PostInit_EventHandlers {
    class comspec_sse_biometrics {
        clientInit = "call compile preprocessFileLineNumbers 'z\comspec_sse\addons\biometrics\XEH_postInitClient.sqf'";
    };
};

// Classes de base déclarées une seule fois : chaque dialogue inclus ensuite les réutilise.
class RscText;
class RscButton;
class RscStructuredText;

#include "dialogs\seekDialog.hpp"
