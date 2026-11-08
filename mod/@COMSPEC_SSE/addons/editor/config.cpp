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
        units[] = {};
        weapons[] = {};
        requiredVersion = REQUIRED_VERSION;
        requiredAddons[] = {"comspec_atak_native_main", "comspec_sse_main", "comspec_sse_core", "comspec_sse_ui", "comspec_sse_evidence", "comspec_sse_identity", "cba_main", "cba_xeh"};
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
        };
    };
};

class CfgRemoteExec {
    class Functions {
        class comspec_sse_fnc_editorServer { allowedTargets = 2; };
        class comspec_sse_fnc_editor { allowedTargets = 1; };
    };
};

class Extended_PostInit_EventHandlers {
    class comspec_sse_editor {
        clientInit = "call compile preprocessFileLineNumbers 'z\comspec_sse\addons\editor\XEH_postInitClient.sqf'";
    };
};

class RscText;
class RscButton;
class RscStructuredText;
class RscPicture;
class RscEdit;
class RscCombo;
class RscListNBox;

#include "dialogs\editor.hpp"
