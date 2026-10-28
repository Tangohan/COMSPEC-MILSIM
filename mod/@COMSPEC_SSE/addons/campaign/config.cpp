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
        requiredAddons[] = {"comspec_sse_main", "comspec_sse_core", "comspec_sse_biometrics", "comspec_sse_analysis", "comspec_sse_zeus", "A3_Modules_F", "cba_xeh", "comspec_atak_native_main"};
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

// Demandes des clients (Zeus, administrateur) : appel distant au serveur seulement. Liste blanche stricte
// d'Overwatch (mode = 1) : sans ces entrées, les appels seraient rejetés en silence.
class CfgRemoteExec {
    class Functions {
        class comspec_sse_fnc_campaignOp { allowedTargets = 2; jip = 0; };
        class comspec_sse_fnc_campaignAthena { allowedTargets = 2; jip = 0; };
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
    // Déclaration seule : rouvrir Module_F: Logic (AttributesBase, ModuleDescription) casse Eden / Zeus / ACE
    // (« Updating base class ») et faisait hériter le bloc de description du module au lieu du contrôle.
    // Attributs « à plat », comme les modules de comspec_sse_zeus.
    class Module_F;

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

        class Attributes {
            class Id {
                control = "Edit";
                expression = "_this setVariable ['Id', _value];";
                property = "COMSPEC_SSE_Campaign_Id";
                displayName = "$STR_COMSPEC_SSE_CFG_68E546FCE7";
                tooltip = "$STR_COMSPEC_SSE_CFG_3D733DBC98";
                typeName = "STRING";
                defaultValue = "''";
            };
            class Name {
                control = "Edit";
                expression = "_this setVariable ['Name', _value];";
                property = "COMSPEC_SSE_Campaign_Name";
                displayName = "$STR_COMSPEC_SSE_CFG_7FF20370C8";
                tooltip = "$STR_COMSPEC_SSE_CFG_17208B92CD";
                typeName = "STRING";
                defaultValue = "''";
            };
            class Type {
                control = "Combo";
                expression = "_this setVariable ['Type', _value];";
                property = "COMSPEC_SSE_Campaign_Type";
                displayName = "$STR_COMSPEC_SSE_CFG_3DEB745651";
                tooltip = "$STR_COMSPEC_SSE_CFG_00DFF861D0";
                typeName = "STRING";
                defaultValue = "'permanente'";
                class Values {
                    class Permanente { name = "$STR_COMSPEC_SSE_CFG_39E7554B3B"; value = "permanente"; };
                    class Recurrente { name = "$STR_COMSPEC_SSE_CFG_BC72CC8240"; value = "recurrente"; };
                    class Temporaire { name = "$STR_COMSPEC_SSE_CFG_33814F2738"; value = "temporaire"; };
                };
            };
            class CopyFrom {
                control = "Edit";
                expression = "_this setVariable ['CopyFrom', _value];";
                property = "COMSPEC_SSE_Campaign_CopyFrom";
                displayName = "$STR_COMSPEC_SSE_CFG_DB05277DFF";
                tooltip = "$STR_COMSPEC_SSE_CFG_6BC5A2F521";
                typeName = "STRING";
                defaultValue = "''";
            };
            class Autosave {
                control = "Combo";
                expression = "_this setVariable ['Autosave', _value];";
                property = "COMSPEC_SSE_Campaign_Autosave";
                displayName = "$STR_COMSPEC_SSE_CFG_46F719D58D";
                tooltip = "$STR_COMSPEC_SSE_CFG_431CAD1E47";
                typeName = "NUMBER";
                defaultValue = "15";
                class Values {
                    class M0 { name = "$STR_COMSPEC_SSE_CFG_F405E1BE2C"; value = 0; };
                    class M5 { name = "$STR_COMSPEC_SSE_CFG_112F9360A3"; value = 5; };
                    class M10 { name = "$STR_COMSPEC_SSE_CFG_F4731FA7D1"; value = 10; };
                    class M15 { name = "$STR_COMSPEC_SSE_CFG_C83C4B7DD1"; value = 15; };
                    class M30 { name = "$STR_COMSPEC_SSE_CFG_98F537212E"; value = 30; };
                    class M60 { name = "$STR_COMSPEC_SSE_CFG_04AF531503"; value = 60; };
                };
            };
            class SaveAtEnd {
                control = "CheckboxNumber";
                expression = "_this setVariable ['SaveAtEnd', _value];";
                property = "COMSPEC_SSE_Campaign_SaveAtEnd";
                displayName = "$STR_COMSPEC_SSE_CFG_91B1EF61F7";
                tooltip = "$STR_COMSPEC_SSE_CFG_7749B9EDD8";
                typeName = "NUMBER";
                defaultValue = "1";
            };
        };
        class ModuleDescription {
            description = "$STR_COMSPEC_SSE_CFG_8F17D61D8E";
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

        class Attributes {
            class Action {
                control = "Combo";
                expression = "_this setVariable ['Action', _value];";
                property = "COMSPEC_SSE_CampaignSave_Action";
                displayName = "$STR_COMSPEC_SSE_CFG_97C89A4D66";
                tooltip = "$STR_COMSPEC_SSE_CFG_325E6A9763";
                typeName = "STRING";
                defaultValue = "'save'";
                class Values {
                    class Save { name = "$STR_COMSPEC_SSE_CFG_E24E94AD56"; value = "save"; };
                    class End { name = "$STR_COMSPEC_SSE_CFG_BF2E09C2F4"; value = "end"; };
                };
            };
            class Reason {
                control = "Edit";
                expression = "_this setVariable ['Reason', _value];";
                property = "COMSPEC_SSE_CampaignSave_Reason";
                displayName = "$STR_COMSPEC_SSE_CFG_3CBCBC1C17";
                tooltip = "$STR_COMSPEC_SSE_CFG_EEEF930C08";
                typeName = "STRING";
                defaultValue = "'objectif atteint'";
            };
        };
        class ModuleDescription {
            description = "$STR_COMSPEC_SSE_CFG_53AA62AC95";
        };
    };
};
