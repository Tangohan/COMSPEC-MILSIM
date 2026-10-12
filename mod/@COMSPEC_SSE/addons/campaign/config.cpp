#include "script_component.hpp"

/*
    Campagnes SSE : permanente, récurrente (bootcamp), temporaire (drill SOT).
    Sauvegarde serveur, personnages récurrents, journal de campagne, export et import.
*/
class CfgPatches {
    class comspec_sse_campaign {
        name = "COMSPEC SSE - Campagnes";
        units[] = {"COMSPEC_SSE_Module_Campaign", "COMSPEC_SSE_Module_CampaignSave"};
        weapons[] = {};
        requiredVersion = REQUIRED_VERSION;
        requiredAddons[] = {"comspec_sse_main", "comspec_sse_core", "comspec_sse_biometrics", "comspec_sse_analysis", "comspec_sse_zeus", "A3_Modules_F", "cba_xeh"};
        author = "COMSPEC";
        VERSION_CONFIG;
    };
};

class CfgFunctions {
    class comspec_sse {
        tag = "comspec_sse";
        class campaign {
            file = "z\comspec_sse\addons\campaign\functions";
            class campaignOp {};
            class campaignDate {};
            class campaignInfo {};
            class campaignZeus {};
            class campaignAthena {};
            class moduleCampaign {};
            class moduleCampaignSave {};
        };
    };
};

class Extended_PreInit_EventHandlers {
    class comspec_sse_campaign {
        init = "call compile preprocessFileLineNumbers 'z\comspec_sse\addons\campaign\XEH_preInit.sqf'";
    };
};

class Extended_PostInit_EventHandlers {
    class comspec_sse_campaign {
        init = "call compile preprocessFileLineNumbers 'z\comspec_sse\addons\campaign\XEH_postInit.sqf'";
    };
};

// Personnage récurrent apparu en cours de partie : identité et appartenance de la campagne.
class Extended_InitPost_EventHandlers {
    class CAManBase {
        class comspec_sse_campaign {
            serverInit = "[{ if (!isNull (_this select 0) && {count (missionNamespace getVariable ['comspec_sse_campaign', createHashMap]) > 0}) then { ['bind', [_this select 0]] call comspec_sse_fnc_campaignOp; }; }, [_this select 0], 0.5] call CBA_fnc_waitAndExecute;";
        };
    };
};

class CfgVehicles {
    class Logic;
    class Module_F: Logic {
        class AttributesBase {
            class Edit;
            class Combo;
            class CheckboxNumber;
        };
        class ModuleDescription {};
    };

    class COMSPEC_SSE_Module_Campaign: Module_F {
        author = "COMSPEC";
        scope = 2;
        scopeCurator = 0;
        category = "COMSPEC_SSE_Mission";
        displayName = "Campagne SSE (charger ou créer)";
        function = "comspec_sse_fnc_moduleCampaign";
        functionPriority = 1;
        isGlobal = 0;
        isTriggerActivated = 0;
        isDisposable = 1;
        is3DEN = 0;
        icon = "\a3\ui_f\data\igui\cfg\simpleTasks\types\map_ca.paa";
        portrait = "\a3\ui_f\data\igui\cfg\simpleTasks\types\map_ca.paa";

        class Attributes: AttributesBase {
            class Id: Edit {
                property = "COMSPEC_SSE_Campaign_Id";
                displayName = "Identifiant";
                tooltip = "Même identifiant dans chaque mission de la campagne, par exemple OP-SABLE (vraie carte) ou BOOTCAMP (drills). Créée au premier lancement.";
                typeName = "STRING";
                defaultValue = "''";
            };
            class Name: Edit {
                property = "COMSPEC_SSE_Campaign_Name";
                displayName = "Nom";
                typeName = "STRING";
                defaultValue = "''";
            };
            class Type: Combo {
                property = "COMSPEC_SSE_Campaign_Type";
                displayName = "Type";
                tooltip = "Permanente : tout est gardé. Récurrente : mêmes personnages et organisations à chaque drill, rien de connu au départ. Temporaire : drill SOT jetable, rien n'est gardé.";
                typeName = "STRING";
                defaultValue = "'permanente'";
                class Values {
                    class Permanente { name = "Permanente (vraie campagne)"; value = "permanente"; };
                    class Recurrente { name = "Récurrente (bootcamp, drills)"; value = "recurrente"; };
                    class Temporaire { name = "Temporaire (drill SOT jetable)"; value = "temporaire"; };
                };
            };
            class CopyFrom: Edit {
                property = "COMSPEC_SSE_Campaign_CopyFrom";
                displayName = "Copier la structure de";
                tooltip = "Identifiant d'une autre campagne : ses personnages et organisations, sans rien de découvert. Pour un drill réaliste sur le réseau de la vraie campagne.";
                typeName = "STRING";
                defaultValue = "''";
            };
            class Autosave: Edit {
                property = "COMSPEC_SSE_Campaign_Autosave";
                displayName = "Sauvegarde automatique (minutes)";
                tooltip = "0 = jamais. Sans effet sur un drill temporaire.";
                typeName = "NUMBER";
                defaultValue = "15";
            };
            class SaveAtEnd: CheckboxNumber {
                property = "COMSPEC_SSE_Campaign_SaveAtEnd";
                displayName = "Sauvegarder à la fin de la mission";
                tooltip = "Temporaire : le bac à sable est vidé à la place.";
                typeName = "NUMBER";
                defaultValue = "1";
            };
            class ModuleDescription: ModuleDescription {};
        };
        class ModuleDescription: ModuleDescription {
            description = "Charge la campagne au lancement (sur le serveur). Une campagne garde sa graine biométrique (les mêmes personnages ont les mêmes empreintes), ses personnages récurrents (identifiant biométrique fixé), ses organisations, ce que les joueurs savent et le registre des scellés. Un seul module par mission.";
        };
    };

    class COMSPEC_SSE_Module_CampaignSave: Module_F {
        author = "COMSPEC";
        scope = 2;
        scopeCurator = 0;
        category = "COMSPEC_SSE_Mission";
        displayName = "Campagne SSE : sauvegarder (déclencheur)";
        function = "comspec_sse_fnc_moduleCampaignSave";
        functionPriority = 5;
        isGlobal = 0;
        isTriggerActivated = 1;
        isDisposable = 0;
        is3DEN = 0;
        icon = "\a3\ui_f\data\igui\cfg\simpleTasks\types\download_ca.paa";
        portrait = "\a3\ui_f\data\igui\cfg\simpleTasks\types\download_ca.paa";

        class Attributes: AttributesBase {
            class Action: Combo {
                property = "COMSPEC_SSE_CampaignSave_Action";
                displayName = "Action";
                typeName = "STRING";
                defaultValue = "'save'";
                class Values {
                    class Save { name = "Sauvegarder"; value = "save"; };
                    class End { name = "Fin de mission (sauver, ou vider un drill temporaire)"; value = "end"; };
                };
            };
            class Reason: Edit {
                property = "COMSPEC_SSE_CampaignSave_Reason";
                displayName = "Motif (journal de campagne)";
                typeName = "STRING";
                defaultValue = "'objectif atteint'";
            };
            class ModuleDescription: ModuleDescription {};
        };
        class ModuleDescription: ModuleDescription {
            description = "Synchronisez un déclencheur : à son activation, la campagne est sauvegardée (ou le drill temporaire vidé).";
        };
    };
};
