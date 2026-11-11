#include "script_component.hpp"
#include "ui_macros.hpp"

/*
    COMSPEC SSE — Extensions de documents (menu « SSE ROLEPLAY · Extensions »).
    Une extension est un addon tiers qui déclare ses types de papiers dans CfgComspecDocuments et se présente dans
    CfgComspecDocumentExtensions (voir addons/identity/CfgComspecDocuments.hpp et extensions/example_documents/README.md).
    Ici : réglages CBA (SSE ROLEPLAY › Extensions), liste des extensions coupées calculée par le serveur et diffusée
    (comspec_sse_docExtOff + évènement CBA comspec_sse_docExtOffChanged, lue par comspec_sse_fnc_idDocTypes), fenêtre
    d'activation (Zeus, menu Échap, Eden via comspec_sse_fnc_docExtOpen) avec son tuto intégré « Comment ça marche ».
    L'extension d'exemple (extensions/example_documents) est livrée dans le pack SSE, coupée par défaut (defaultOff = 1).
*/
class CfgPatches {
    class comspec_sse_extensions {
        name = "COMSPEC SSE - Extensions";
        units[] = {};
        weapons[] = {};
        requiredVersion = REQUIRED_VERSION;
        requiredAddons[] = {"comspec_sse_identity", "comspec_sse_zeus", "comspec_sse_core", "comspec_sse_main", "comspec_sse_ui", "cba_main", "cba_xeh"};
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
            class docExtMenu {};
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

// Menu Échap (SP et MP) : bouton « SSE ROLEPLAY · Extensions », en jeu seulement (fn_docExtMenu).
class Extended_DisplayLoad_EventHandlers {
    class RscDisplayInterrupt {
        comspec_sse_extensions = "(_this select 0) call comspec_sse_fnc_docExtMenu";
    };
    class RscDisplayMPInterrupt {
        comspec_sse_extensions = "(_this select 0) call comspec_sse_fnc_docExtMenu";
    };
};

// Charte UI du SSE (addons/ui/dialogs/base.hpp) : classes déclarées par comspec_sse_ui.
class RscPicture;
class COMSPEC_SSE_RscDim;
class COMSPEC_SSE_RscBackground;
class COMSPEC_SSE_RscPanel;
class COMSPEC_SSE_RscHeader;
class COMSPEC_SSE_RscAccentLine;
class COMSPEC_SSE_RscText;
class COMSPEC_SSE_RscStructuredText;
class COMSPEC_SSE_RscControlsGroup;
class COMSPEC_SSE_RscListBox;
class COMSPEC_SSE_RscButton;
class COMSPEC_SSE_RscButtonNav;
class COMSPEC_SSE_RscButtonClose;

#include "dialogs\extensions.hpp"
