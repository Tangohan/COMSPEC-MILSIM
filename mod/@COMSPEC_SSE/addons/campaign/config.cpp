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
        displayName = "$STR_COMSPEC_SSE_CFG_BAE804BBA0";
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
                displayName = "$STR_COMSPEC_SSE_CFG_68E546FCE7";
                tooltip = "$STR_COMSPEC_SSE_CFG_59AC04E471";
                typeName = "STRING";
                defaultValue = "''";
            };
            class Name: Edit {
                property = "COMSPEC_SSE_Campaign_Name";
                displayName = "$STR_COMSPEC_SSE_CFG_7FF20370C8";
                typeName = "STRING";
                defaultValue = "''";
            };
            class Type: Combo {
                property = "COMSPEC_SSE_Campaign_Type";
                displayName = "$STR_COMSPEC_SSE_CFG_3DEB745651";
                tooltip = "$STR_COMSPEC_SSE_CFG_00DFF861D0";
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
                displayName = "$STR_COMSPEC_SSE_CFG_DB05277DFF";
                tooltip = "$STR_COMSPEC_SSE_CFG_BEAF7B27AC";
                typeName = "STRING";
                defaultValue = "''";
            };
            class Autosave: Edit {
                property = "COMSPEC_SSE_Campaign_Autosave";
                displayName = "$STR_COMSPEC_SSE_CFG_46F719D58D";
                tooltip = "$STR_COMSPEC_SSE_CFG_02E1A2A4CC";
                typeName = "NUMBER";
                defaultValue = "15";
            };
            class SaveAtEnd: CheckboxNumber {
                property = "COMSPEC_SSE_Campaign_SaveAtEnd";
                displayName = "$STR_COMSPEC_SSE_CFG_91B1EF61F7";
                tooltip = "$STR_COMSPEC_SSE_CFG_771988E23B";
                typeName = "NUMBER";
                defaultValue = "1";
            };
            class ModuleDescription: ModuleDescription {};
        };
        class ModuleDescription: ModuleDescription {
            description = "$STR_COMSPEC_SSE_CFG_0323FC1D81";
        };
    };

    class COMSPEC_SSE_Module_CampaignSave: Module_F {
        author = "COMSPEC";
        scope = 2;
        scopeCurator = 0;
        category = "COMSPEC_SSE_Mission";
        displayName = "$STR_COMSPEC_SSE_CFG_0D432BE534";
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
                displayName = "$STR_COMSPEC_SSE_CFG_97C89A4D66";
                typeName = "STRING";
                defaultValue = "'save'";
                class Values {
                    class Save { name = "Sauvegarder"; value = "save"; };
                    class End { name = "Fin de mission (sauver, ou vider un drill temporaire)"; value = "end"; };
                };
            };
            class Reason: Edit {
                property = "COMSPEC_SSE_CampaignSave_Reason";
                displayName = "$STR_COMSPEC_SSE_CFG_3CBCBC1C17";
                typeName = "STRING";
                defaultValue = "'objectif atteint'";
            };
            class ModuleDescription: ModuleDescription {};
        };
        class ModuleDescription: ModuleDescription {
            description = "$STR_COMSPEC_SSE_CFG_A7112BB3C2";
        };
    };
};
