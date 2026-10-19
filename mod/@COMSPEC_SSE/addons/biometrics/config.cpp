#include "script_component.hpp"

class CfgPatches {
    class comspec_sse_biometrics {
        name = "COMSPEC SSE - Biometrics / SEEK II";
        units[] = {};
        weapons[] = {};
        requiredVersion = REQUIRED_VERSION;
        requiredAddons[] = {
            "comspec_sse_core",
            "comspec_sse_interaction",
            "comspec_sse_evidence",
            "comspec_sse_ui",
            "ace_interact_menu",
            "comspec_atak_native_main"
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
            class openSeekKeybind {};
            class seekOnLoad {};
            class seekCapture {};
            class seekIdentify {};
            class seekTransmit {};
            class seekClose {};
            class compareBiometrics {};
        };
        // Moteur biométrique : identités biologiques, base ABIS, qualité de capture, recherche 1:N.
        class bioEngine {
            file = "z\comspec_sse\addons\biometrics\functions";
            class bioInitSettings {};
            class bioHash {};
            class bioRand {};
            class bioHex {};
            class bioPack {};
            class bioUnpack {};
            class bioPersonKey {};
            class bioProfile {};
            class bioRecord {};
            class bioCapture {};
            class bioCaptureLatent {};
            class bioLatentOwners {};
            class bioSearch {};
            class bioEnroll {};
            class bioDnaLab {};
            class bioRequest {};
            class bioServerHandle {};
            class bioClientResult {};
            class bioNotify {};
            class bioWantedCache {};
            class bioQuery {};
            class bioDescribe {};
            class bioCaptureLatentAction {};
            class bioSetProfile {};
            class bioApplyCapture {};
            class bioRegistry {};
            class bioTemplates {};
            class headBag {};
            class bioAnimate {};
            class bioBloodStain {};
        };
    };
};

// Zeus (ZEN) règle la biométrie d'un sujet sur le serveur. Pas de « mode » ici :
// la liste blanche éventuelle reste celle de la mission ou d'un autre pack.
class CfgRemoteExec {
    class Functions {
        class comspec_sse_fnc_bioSetProfile { allowedTargets = 2; };
        class comspec_sse_fnc_headBag { allowedTargets = 0; };
    };
};

class Extended_PreInit_EventHandlers {
    class comspec_sse_biometrics {
        init = "call compile preprocessFileLineNumbers 'z\comspec_sse\addons\biometrics\XEH_preInit.sqf'";
    };
};

class Extended_PostInit_EventHandlers {
    class comspec_sse_biometrics {
        init = "call compile preprocessFileLineNumbers 'z\comspec_sse\addons\biometrics\XEH_postInit.sqf'";
        clientInit = "call compile preprocessFileLineNumbers 'z\comspec_sse\addons\biometrics\XEH_postInitClient.sqf'";
    };
};

// Chaque personne reçoit sa clé biométrique du registre du serveur dès son apparition
// (image suivante : les attributs Eden et le champ init sont alors appliqués).
class Extended_InitPost_EventHandlers {
    class CAManBase {
        class comspec_sse_biometrics {
            serverInit = "[{ if (!isNull (_this select 0) && {missionNamespace getVariable ['comspec_sse_bio_enabled', true]}) then { ['assign', _this select 0] call comspec_sse_fnc_bioRegistry; }; }, [_this select 0]] call CBA_fnc_execNextFrame;";
        };
    };
};

// Classes de base déclarées une seule fois : chaque dialogue inclus ensuite les réutilise.
class RscText;
class RscButton;
class RscStructuredText;

#include "dialogs\seekDialog.hpp"
