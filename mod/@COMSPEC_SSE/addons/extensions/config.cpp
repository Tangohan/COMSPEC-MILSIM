#include "script_component.hpp"

/*
    COMSPEC SSE — Extensions de documents (fenêtre « SSE ROLEPLAY · Extensions »).
    Une extension est un addon tiers qui déclare ses types de papiers dans CfgComspecDocuments et se présente dans
    CfgComspecDocumentExtensions (voir addons/identity/CfgComspecDocuments.hpp et extensions/example_documents/README.md).
    Ici : réglages CBA (SSE ROLEPLAY › Extensions), liste des extensions coupées calculée par le serveur et diffusée
    (comspec_sse_docExtOff + évènement CBA comspec_sse_docExtOffChanged, lue par comspec_sse_fnc_idDocTypes), fenêtre
    d'activation (Zeus, Configuration ATHENA du menu Échap, Eden via comspec_sse_fnc_docExtOpen).
    Sans l'ATAK natif, le SSE porte aussi le bouton « ATHENA Configuration » du menu Échap (fn_athenaConfig, copie
    identique de celle de l'ATAK natif ; avec l'ATAK natif, c'est l'ATAK qui l'affiche et le SSE n'y ajoute que son entrée).
*/
class CfgPatches {
    class comspec_sse_extensions {
        name = "COMSPEC SSE - Extensions";
        units[] = {};
        weapons[] = {};
        requiredVersion = REQUIRED_VERSION;
        requiredAddons[] = {"comspec_sse_identity", "comspec_sse_zeus", "comspec_sse_core", "comspec_sse_main", "cba_main", "cba_xeh"};
        author = "COMSPEC";
        VERSION_CONFIG;
    };
};

class CfgFunctions {
    class comspec_sse {
        tag = "comspec_sse";
        class extensions {
            file = "z\comspec_sse\addons\extensions\functions";
            class docExtInitSettings {};
            class docExtServer {};
            class docExtWindow {};
            class docExtOpen {};
            class athenaConfig {};
        };
    };
};

// Activer / désactiver une extension : demande d'un client (Zeus ou administrateur, vérifié par remoteExecutedOwner).
class CfgRemoteExec {
    class Functions {
        class comspec_sse_fnc_docExtServer { allowedTargets = 2; };
    };
};

class Extended_PreInit_EventHandlers {
    class comspec_sse_extensions {
        init = "call compile preprocessFileLineNumbers 'z\comspec_sse\addons\extensions\XEH_preInit.sqf'";
    };
};

class Extended_PostInit_EventHandlers {
    class comspec_sse_extensions {
        init = "call compile preprocessFileLineNumbers 'z\comspec_sse\addons\extensions\XEH_postInit.sqf'";
    };
};

// Menu Échap (SP et MP) : bouton « ATHENA Configuration » seulement sans l'ATAK natif (avec lui, son bouton suffit :
// jamais deux boutons). L'entrée « SSE · Roleplay et extensions » est ajoutée au registre au preInit, dans les deux cas.
class Extended_DisplayLoad_EventHandlers {
    class RscDisplayInterrupt {
        comspec_sse_extensions = "if (!isClass (configFile >> 'CfgPatches' >> 'comspec_atak_native_main')) then { ['button', _this select 0] call comspec_sse_fnc_athenaConfig; }";
    };
    class RscDisplayMPInterrupt {
        comspec_sse_extensions = "if (!isClass (configFile >> 'CfgPatches' >> 'comspec_atak_native_main')) then { ['button', _this select 0] call comspec_sse_fnc_athenaConfig; }";
    };
};

class RscText;
class RscButton;
class RscStructuredText;
class RscPicture;
class RscListBox;

// Ligne et bouton de la Configuration ATHENA (fn_athenaConfig) : transparent, survol et focus clavier en vert Athena.
class COMSPEC_SSE_AthenaCfgRow: RscButton {
    text = "";
    shadow = 0;
    colorText[] = {1, 1, 1, 1};
    colorDisabled[] = {0, 0, 0, 0};
    colorBackground[] = {0, 0, 0, 0};
    colorBackgroundDisabled[] = {0, 0, 0, 0};
    colorBackgroundActive[] = {0.204, 0.827, 0.6, 0.14};
    colorFocused[] = {0.204, 0.827, 0.6, 0.10};
    colorBackground2[] = {0.204, 0.827, 0.6, 0.10};
    colorBorder[] = {0, 0, 0, 0};
    colorShadow[] = {0, 0, 0, 0};
    offsetPressedX = 0;
    offsetPressedY = 0;
};

#include "dialogs\extensions.hpp"
