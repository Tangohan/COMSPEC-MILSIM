#include "script_component.hpp"
#include "ui_macros.hpp"

/*
    Éditeur SSE (maquette validée) : créer un renseignement, une identité, un papier, un dossier ou un kit, le voir tel
    que les joueurs le verront, le retrouver dans une bibliothèque, le poser dans le monde.
    Ouvert par le module Zeus « COMSPEC · Renseignement (SSE) » et le menu COMSPEC SSE d'Eden. Icônes et logo : tools/gen_editor_assets.py.
*/
class CfgPatches {
    class comspec_sse_editor {
        name = "COMSPEC SSE - Éditeur";
        units[] = {"COMSPEC_SSE_Module_Editor"};
        weapons[] = {};
        requiredVersion = REQUIRED_VERSION;
        requiredAddons[] = {"comspec_atak_native_main", "comspec_sse_main", "comspec_sse_core", "comspec_sse_ui", "comspec_sse_evidence", "comspec_sse_identity", "comspec_sse_zeus", "cba_main", "cba_xeh"};
        author = "COMSPEC";
        VERSION_CONFIG;
    };
};

class CfgFunctions {
    class comspec_sse {
        tag = "comspec_sse";
        class editor {
            file = "z\comspec_sse\addons\editor\functions";
            class editor {};
            class editorPlace {};
            class editorServer {};
            class editorLibrary {};
            class editorFound {};
            class editorAi {};
            class editorAiServer {};
            class aiWho {};
            class aiConfigOpen {};
            class editorModule {};
        };
    };
};

class CfgRemoteExec {
    class Functions {
        class comspec_sse_fnc_editorServer { allowedTargets = 2; };
        class comspec_sse_fnc_editor { allowedTargets = 1; };
        // Fenêtre « IA de la zone » : demandes au serveur, réponses au seul poste demandeur.
        class comspec_sse_fnc_editorAiServer { allowedTargets = 2; jip = 0; };
        class comspec_sse_fnc_editorAi { allowedTargets = 1; jip = 0; };
    };
};

class Extended_PostInit_EventHandlers {
    class comspec_sse_editor {
        clientInit = "call compile preprocessFileLineNumbers 'z\comspec_sse\addons\editor\XEH_postInitClient.sqf'";
        serverInit = "call compile preprocessFileLineNumbers 'z\comspec_sse\addons\editor\XEH_postInitServer.sqf'";
    };
};

// Module Zeus sans ZEN : l'éditeur s'ouvre aussi dans le Zeus de base. Posé sur une IA : page « IA de la zone ».
class CfgVehicles {
    class COMSPEC_SSE_Module_Base;
    class COMSPEC_SSE_Module_Editor: COMSPEC_SSE_Module_Base {
        scope = 1;
        scopeCurator = 2;
        displayName = "Éditeur SSE";
        function = "comspec_sse_fnc_editorModule";
        isGlobal = 1;
        icon = "\z\comspec_sse\addons\editor\data\type_intel_ca.paa";
        portrait = "\z\comspec_sse\addons\editor\data\type_intel_ca.paa";
    };
};

class RscText;
class RscButton;
class RscStructuredText;
class RscPicture;
class RscEdit;
class RscCombo;
class RscListNBox;
class COMSPEC_SSE_RscControlsGroup;

#include "dialogs\editor.hpp"
