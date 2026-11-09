#include "script_component.hpp"
#include "ui_macros.hpp"

/*
    COMSPEC SSE — IA RP : parler aux IA en texte libre, bulles 3D, journal des échanges.
    Terminal « Parler à… » (ACE, molette sans ACE, touche CBA) sur une IA à 5 m ; commandes RP /me /do /try /todo ;
    interprète d'intentions local (mots-clés FR et EN, fn_iarpIntent) et générateur de répliques à grammaire
    (fn_iarpGen) : aucune IA générative, aucune requête réseau, rien vers Athena.
    La réponse vient de la couverture de CETTE IA (légende de l'identité SSE, faits de l'atelier IA) et de son état
    (automate des IA réalistes) quand l'addon identity est chargé ; sans lui, des faits de l'atelier seulement.
    L'ATAK n'est pas requis par cet addon : bulles, terminal et textes (stringtable.xml) sont propres au SSE.
*/
class CfgPatches {
    class comspec_sse_ia_rp {
        name = "COMSPEC SSE - IA RP";
        units[] = {};
        weapons[] = {};
        requiredVersion = REQUIRED_VERSION;
        requiredAddons[] = {"comspec_sse_main", "comspec_sse_core", "comspec_sse_ui", "cba_main", "cba_xeh", "cba_settings"};
        author = "COMSPEC";
        VERSION_CONFIG;
    };
};

class CfgFunctions {
    class comspec_sse {
        tag = "comspec_sse";
        class ia_rp {
            file = "z\comspec_sse\addons\ia_rp\functions";
            class iarpSettings {};
            class iarpData {};
            class iarpNorm {};
            class iarpIntent {};
            class iarpGen {};
            class iarpFill {};
            class iarpProfile {};
            class iarpServer {};
            class iarpClient {};
            class iarpBubble {};
            class iarpUi {};
            class iarpSelfTest {};
        };
    };
};

// Demandes du joueur au serveur (demandeur retrouvé par remoteExecutedOwner) ; réponses du serveur au joueur.
class CfgRemoteExec {
    class Functions {
        class comspec_sse_fnc_iarpServer { allowedTargets = 2; };
        class comspec_sse_fnc_iarpClient { allowedTargets = 1; };
    };
};

class Extended_PreInit_EventHandlers {
    class comspec_sse_ia_rp {
        init = "call compile preprocessFileLineNumbers 'z\comspec_sse\addons\ia_rp\XEH_preInit.sqf'";
    };
};
class Extended_PostInit_EventHandlers {
    class comspec_sse_ia_rp {
        init = "call compile preprocessFileLineNumbers 'z\comspec_sse\addons\ia_rp\XEH_postInit.sqf'";
        clientInit = "call compile preprocessFileLineNumbers 'z\comspec_sse\addons\ia_rp\XEH_postInitClient.sqf'";
    };
};

class COMSPEC_SSE_RscBackground;
class COMSPEC_SSE_RscHeader;
class COMSPEC_SSE_RscAccentLine;
class COMSPEC_SSE_RscText;
class COMSPEC_SSE_RscLabel;
class COMSPEC_SSE_RscButton;
class COMSPEC_SSE_RscButtonNav;
class COMSPEC_SSE_RscButtonClose;
class COMSPEC_SSE_RscEdit;
class COMSPEC_SSE_RscStructuredText;
class COMSPEC_SSE_RscControlsGroup;

#include "dialogs\iarp_terminal.hpp"
