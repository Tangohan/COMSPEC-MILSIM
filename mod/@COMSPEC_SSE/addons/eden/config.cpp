#include "script_component.hpp"

class CfgPatches {
    class comspec_sse_eden {
        name = "COMSPEC SSE - Eden";
        units[] = {};
        weapons[] = {};
        requiredVersion = REQUIRED_VERSION;
        requiredAddons[] = {"comspec_sse_core", "comspec_sse_generator", "3DEN", "comspec_atak_native_main"};
        author = "COMSPEC";
        VERSION_CONFIG;
    };
};

class CfgFunctions {
    class comspec_sse {
        tag = "comspec_sse";
        class eden {
            file = "z\comspec_sse\addons\eden\functions";
            class edenApplyAttributes {};
            class edenWriteField {};
            class edenInitEntity {};
            class domexGetNode {};
            class domexApplyObject {};
        };
    };
};

// Objets, véhicules et caisses marqués dans Eden : l'InitPost ci-dessous ne couvre que
// les personnes ; un balayage serveur au lancement prend tout le reste.
class Extended_PostInit_EventHandlers {
    class comspec_sse_eden {
        serverInit = "[{ { if (!(_x isKindOf 'CAManBase') && {_x getVariable ['comspec_sse_enabled', false] || {_x getVariable ['comspec_sse_domex_enabled', false]}}) then { [_x] call comspec_sse_fnc_edenInitEntity; }; } forEach (allMissionObjects ''); }, [], 1] call CBA_fnc_waitAndExecute;";
    };
};

class Extended_InitPost_EventHandlers {
    class CAManBase {
        class comspec_sse_eden {
            // CBA passe déjà [_unit] dans _this — pas de [_this] (sinon [[_unit]]).
            init = "_this call comspec_sse_fnc_edenInitEntity";
        };
    };
};

class Cfg3DEN {
    class Object {
        class AttributeCategories {
            class COMSPEC_SSE {
                displayName = "$STR_COMSPEC_SSE_CFG_B6E32D0967";
                collapsed = 1;

                class Attributes {
                    class comspec_sse_enabled {
                        displayName = "$STR_COMSPEC_SSE_CFG_1E06839F94";
                        tooltip = "$STR_COMSPEC_SSE_CFG_4225900977";
                        property = "comspec_sse_enabled";
                        control = "Checkbox";
                        expression = "_this setVariable ['comspec_sse_enabled', _value, isServer];";
                        defaultValue = "false";
                        typeName = "BOOL";
                        condition = "objectSimulated";
                    };
                    class comspec_sse_forcedType {
                        displayName = "$STR_COMSPEC_SSE_CFG_C7EB9F3AAD";
                        tooltip = "$STR_COMSPEC_SSE_CFG_6844285EAD";
                        property = "comspec_sse_forcedType";
                        control = "Combo";
                        expression = "if (_value isNotEqualTo '') then { _this setVariable ['comspec_sse_forcedType', _value, isServer]; };";
                        defaultValue = "''";
                        typeName = "STRING";
                        condition = "objectSimulated - objectBrain";
                        class values {
                            class Auto { name = "$STR_COMSPEC_SSE_CFG_F8A3C37BEB"; value = ""; default = 1; };
                            class Phone { name = "$STR_COMSPEC_SSE_CFG_D3B023EABC"; value = "PHONE"; };
                            class Computer { name = "$STR_COMSPEC_SSE_CFG_CBAB5F515D"; value = "COMPUTER"; };
                            class Radio { name = "$STR_COMSPEC_SSE_CFG_B11BF19E3F"; value = "RADIO"; };
                            class Document { name = "$STR_COMSPEC_SSE_CFG_F7D3B1FB6B"; value = "DOCUMENT"; };
                            class Media { name = "$STR_COMSPEC_SSE_CFG_4DB44A646B"; value = "MEDIA"; };
                            class Weapon { name = "$STR_COMSPEC_SSE_CFG_D7DB063433"; value = "WEAPON"; };
                            class Container { name = "$STR_COMSPEC_SSE_CFG_49719A1839"; value = "CONTAINER"; };
                            class Vehicle { name = "$STR_COMSPEC_SSE_CFG_87C24BCDC7"; value = "VEHICLE"; };
                            class Building { name = "$STR_COMSPEC_SSE_CFG_B228C08B27"; value = "BUILDING"; };
                            class Object { name = "$STR_COMSPEC_SSE_CFG_ADEDF35BDA"; value = "OBJECT"; };
                        };
                    };
                    class comspec_sse_profile {
                        displayName = "$STR_COMSPEC_SSE_CFG_669FE25A32";
                        tooltip = "$STR_COMSPEC_SSE_CFG_7565793116";
                        property = "comspec_sse_profile";
                        control = "Combo";
                        expression = "_this setVariable ['comspec_sse_profile', _value, isServer];";
                        defaultValue = "'RANDOM'";
                        typeName = "STRING";
                        class values {
                            class RANDOM { name = "$STR_COMSPEC_SSE_CFG_6DBD11FF8A"; value = "RANDOM"; default = 1; };
                            class CIVILIAN { name = "$STR_COMSPEC_SSE_CFG_496B2073F0"; value = "CIVILIAN"; };
                            class INSURGENT { name = "$STR_COMSPEC_SSE_CFG_DA14187553"; value = "INSURGENT"; };
                            class MILITARY { name = "$STR_COMSPEC_SSE_CFG_1E0730DF18"; value = "MILITARY"; };
                            class HVT { name = "$STR_COMSPEC_SSE_CFG_21C1444A64"; value = "HVT"; };
                            class COURIER { name = "$STR_COMSPEC_SSE_CFG_6347EE1AA8"; value = "COURIER"; };
                            class FINANCIER { name = "$STR_COMSPEC_SSE_CFG_8FAF1224A7"; value = "FINANCIER"; };
                            class TECHNICIAN { name = "$STR_COMSPEC_SSE_CFG_E3CCC90C1A"; value = "TECHNICIAN"; };
                            class INTELLIGENCE { name = "$STR_COMSPEC_SSE_CFG_672B3D9978"; value = "INTELLIGENCE"; };
                            class LOGISTICS { name = "$STR_COMSPEC_SSE_CFG_8C266B946B"; value = "LOGISTICS"; };
                            class CUSTOM { name = "$STR_COMSPEC_SSE_CFG_C99D5F121A"; value = "CUSTOM"; };
                        };
                    };
                    class comspec_sse_generation {
                        displayName = "$STR_COMSPEC_SSE_CFG_629B240053";
                        tooltip = "$STR_COMSPEC_SSE_CFG_1BBEEE7974";
                        property = "comspec_sse_generation";
                        control = "Combo";
                        expression = "_this setVariable ['comspec_sse_generation', _value, isServer];";
                        defaultValue = "'AUTO'";
                        typeName = "STRING";
                        class values {
                            class AUTO { name = "$STR_COMSPEC_SSE_CFG_D74578875D"; value = "AUTO"; default = 1; };
                            class MANUAL { name = "$STR_COMSPEC_SSE_CFG_E6A412CFEE"; value = "MANUAL"; };
                        };
                    };
                    class comspec_sse_complexity {
                        displayName = "$STR_COMSPEC_SSE_CFG_684A67E212";
                        tooltip = "$STR_COMSPEC_SSE_CFG_40DD5E3E2E";
                        property = "comspec_sse_complexity";
                        control = "Combo";
                        expression = "_this setVariable ['comspec_sse_complexity', _value, isServer];";
                        defaultValue = "'STANDARD'";
                        typeName = "STRING";
                        class values {
                            class LIGHT { name = "$STR_COMSPEC_SSE_CFG_142CF342A2"; value = "LIGHT"; };
                            class STANDARD { name = "$STR_COMSPEC_SSE_CFG_2DFA66079D"; value = "STANDARD"; default = 1; };
                            class DETAILED { name = "$STR_COMSPEC_SSE_CFG_094D4A1824"; value = "DETAILED"; };
                            class HIGH_VALUE { name = "$STR_COMSPEC_SSE_CFG_78AEB2046E"; value = "HIGH_VALUE"; };
                        };
                    };
                    class comspec_sse_identityMode {
                        displayName = "$STR_COMSPEC_SSE_CFG_3138BE1C28";
                        tooltip = "$STR_COMSPEC_SSE_CFG_FA27128D8A";
                        property = "comspec_sse_identityMode";
                        control = "Combo";
                        expression = "_this setVariable ['comspec_sse_identityMode', _value, isServer];";
                        defaultValue = "'AUTO'";
                        typeName = "STRING";
                        class values {
                            class AUTO { name = "$STR_COMSPEC_SSE_CFG_C3841EB9B7"; value = "AUTO"; default = 1; };
                            class NONE { name = "$STR_COMSPEC_SSE_CFG_3B12544651"; value = "NONE"; };
                            class CUSTOM { name = "$STR_COMSPEC_SSE_CFG_544D0C17A7"; value = "CUSTOM"; };
                        };
                    };
                    class COMSPEC_SSE_Profile_Preset {
                        displayName = "$STR_COMSPEC_SSE_CFG_144C58520E";
                        tooltip = "$STR_COMSPEC_SSE_CFG_D2616FDA97";
                        property = "COMSPEC_SSE_Profile_Preset";
                        control = "Combo";
                        expression = "[_this, 'Preset', _value] call comspec_sse_fnc_edenWriteField;";
                        defaultValue = "'auto'";
                        typeName = "STRING";
                        class values {
                            class Auto { name = "$STR_COMSPEC_SSE_CFG_A3D3991651"; value = "auto"; default = 1; };
                            class Inconnu { name = "$STR_COMSPEC_SSE_CFG_D73F31553D"; value = "inconnu"; };
                            class Signale { name = "$STR_COMSPEC_SSE_CFG_E4E69F4E5C"; value = "signale"; };
                            class Recherche { name = "$STR_COMSPEC_SSE_CFG_43C9CA349C"; value = "recherche"; };
                        };
                    };
                    class COMSPEC_SSE_LastName {
                        displayName = "$STR_COMSPEC_SSE_CFG_7FF20370C8";
                        tooltip = "$STR_COMSPEC_SSE_CFG_54288020C7";
                        property = "COMSPEC_SSE_LastName";
                        control = "Edit";
                        expression = "[_this, 'LastName', _value] call comspec_sse_fnc_edenWriteField;";
                        defaultValue = "''";
                        typeName = "STRING";
                    };
                    class COMSPEC_SSE_FirstName {
                        displayName = "$STR_COMSPEC_SSE_CFG_E4387ADE0F";
                        tooltip = "$STR_COMSPEC_SSE_CFG_031E829D33";
                        property = "COMSPEC_SSE_FirstName";
                        control = "Edit";
                        expression = "[_this, 'FirstName', _value] call comspec_sse_fnc_edenWriteField;";
                        defaultValue = "''";
                        typeName = "STRING";
                    };
                    class COMSPEC_SSE_Alias {
                        displayName = "$STR_COMSPEC_SSE_CFG_A48482A2F9";
                        tooltip = "$STR_COMSPEC_SSE_CFG_D0836A23B9";
                        property = "COMSPEC_SSE_Alias";
                        control = "Edit";
                        expression = "[_this, 'Alias', _value] call comspec_sse_fnc_edenWriteField;";
                        defaultValue = "''";
                        typeName = "STRING";
                    };
                    class COMSPEC_SSE_RecordRef
                    {
                        displayName = "$STR_COMSPEC_SSE_CFG_437A61441B";
                        tooltip = "$STR_COMSPEC_SSE_CFG_7FAF9B4872";
                        property = "COMSPEC_SSE_RecordRef";
                        control = "Edit";
                        expression = "[_this, 'RecordRef', _value] call comspec_sse_fnc_edenWriteField;";
                        defaultValue = "''";
                        typeName = "STRING";
                    };
                    class COMSPEC_SSE_Confidence
                    {
                        displayName = "$STR_COMSPEC_SSE_CFG_9E7DD41778";
                        tooltip = "$STR_COMSPEC_SSE_CFG_21B7336A80";
                        property = "COMSPEC_SSE_Confidence";
                        control = "Edit";
                        expression = "[_this, 'Confidence', _value] call comspec_sse_fnc_edenWriteField;";
                        defaultValue = "-1";
                        validate = "number";
                        typeName = "NUMBER";
                    };
                    class COMSPEC_SSE_Seed
                    {
                        displayName = "$STR_COMSPEC_SSE_CFG_15D618B854";
                        tooltip = "$STR_COMSPEC_SSE_CFG_BA0ADC5959";
                        property = "COMSPEC_SSE_Seed";
                        control = "Edit";
                        expression = "[_this, 'Seed', _value] call comspec_sse_fnc_edenWriteField;";
                        defaultValue = "0";
                        validate = "number";
                        typeName = "NUMBER";
                    };
                    class comspec_sse_personRole {
                        displayName = "$STR_COMSPEC_SSE_CFG_3C5727F957";
                        tooltip = "$STR_COMSPEC_SSE_CFG_3E775A5872";
                        property = "comspec_sse_personRole";
                        control = "Edit";
                        expression = "_this setVariable ['comspec_sse_personRole', _value, isServer];";
                        defaultValue = "''";
                        typeName = "STRING";
                    };
                    class comspec_sse_personNationality {
                        displayName = "$STR_COMSPEC_SSE_CFG_E3E921B379";
                        tooltip = "$STR_COMSPEC_SSE_CFG_F16B0327CA";
                        property = "comspec_sse_personNationality";
                        control = "Edit";
                        expression = "[_this, 'Nationality', _value] call comspec_sse_fnc_edenWriteField;";
                        defaultValue = "''";
                        typeName = "STRING";
                    };
                    class comspec_sse_personLanguage {
                        displayName = "$STR_COMSPEC_SSE_CFG_8CED3222ED";
                        tooltip = "$STR_COMSPEC_SSE_CFG_8F6B5DD607";
                        property = "comspec_sse_personLanguage";
                        control = "Edit";
                        expression = "[_this, 'Language', _value] call comspec_sse_fnc_edenWriteField;";
                        defaultValue = "''";
                        typeName = "STRING";
                    };
                    class comspec_sse_personPhone {
                        displayName = "$STR_COMSPEC_SSE_CFG_03B3FBA455";
                        tooltip = "$STR_COMSPEC_SSE_CFG_8F9A093B0E";
                        property = "comspec_sse_personPhone";
                        control = "Edit";
                        expression = "_this setVariable ['comspec_sse_personPhone', _value, isServer];";
                        defaultValue = "''";
                        typeName = "STRING";
                    };
                    class comspec_sse_bioMode {
                        displayName = "$STR_COMSPEC_SSE_CFG_5427FD158C";
                        tooltip = "$STR_COMSPEC_SSE_CFG_7B69F2D6FE";
                        property = "comspec_sse_bioMode";
                        control = "Combo";
                        expression = "_this setVariable ['comspec_sse_bioMode', _value, isServer];";
                        defaultValue = "'AUTO'";
                        typeName = "STRING";
                        class values {
                            class AUTO { name = "$STR_COMSPEC_SSE_CFG_BAA823183D"; value = "AUTO"; default = 1; };
                            class NONE { name = "$STR_COMSPEC_SSE_CFG_9FA9D5AB01"; value = "NONE"; };
                        };
                    };
                    class comspec_sse_zeusNotes {
                        displayName = "$STR_COMSPEC_SSE_CFG_BBF1B1F6C6";
                        tooltip = "$STR_COMSPEC_SSE_CFG_C23ACC81C0";
                        property = "comspec_sse_zeusNotes";
                        control = "Edit";
                        expression = "_this setVariable ['comspec_sse_zeusNotes', _value, isServer];";
                        defaultValue = "''";
                        typeName = "STRING";
                    };
                    class comspec_sse_networkId {
                        displayName = "$STR_COMSPEC_SSE_CFG_847E74EACA";
                        tooltip = "$STR_COMSPEC_SSE_CFG_C0F632D7E1";
                        property = "comspec_sse_networkId";
                        control = "Edit";
                        expression = "_this setVariable ['comspec_sse_networkId', _value, isServer];";
                        defaultValue = "''";
                        typeName = "STRING";
                    };
                    class comspec_sse_modelId {
                        displayName = "$STR_COMSPEC_SSE_CFG_4508276612";
                        tooltip = "$STR_COMSPEC_SSE_CFG_001AD3CCE1";
                        property = "comspec_sse_modelId";
                        control = "Edit";
                        expression = "_this setVariable ['comspec_sse_modelId', _value, isServer];";
                        defaultValue = "''";
                        typeName = "STRING";
                    };
                    class comspec_sse_datasetId {
                        displayName = "$STR_COMSPEC_SSE_CFG_71849A3505";
                        tooltip = "$STR_COMSPEC_SSE_CFG_092AEF7733";
                        property = "comspec_sse_datasetId";
                        control = "Combo";
                        expression = "_this setVariable ['comspec_sse_datasetId', _value, isServer];";
                        defaultValue = "''";
                        typeName = "STRING";
                        class values {
                            class None { name = "$STR_COMSPEC_SSE_CFG_B2ED82F1BF"; value = ""; default = 1; };
                            class Falcon { name = "$STR_COMSPEC_SSE_CFG_97D25CCAC9"; value = "falcon"; };
                        };
                    };
                    class comspec_sse_datasetRole {
                        displayName = "$STR_COMSPEC_SSE_CFG_DA8AB2FFDC";
                        tooltip = "$STR_COMSPEC_SSE_CFG_C6F8BEB399";
                        property = "comspec_sse_datasetRole";
                        control = "Combo";
                        expression = "_this setVariable ['comspec_sse_datasetRole', _value, isServer];";
                        defaultValue = "''";
                        typeName = "STRING";
                        class values {
                            class None { name = "$STR_COMSPEC_SSE_CFG_B2ED82F1BF"; value = ""; default = 1; };
                            class R1 { name = "$STR_COMSPEC_SSE_CFG_F28E227441"; value = "falcon_hvt"; };
                            class R2 { name = "$STR_COMSPEC_SSE_CFG_BBA6DAA8E0"; value = "falcon_ied"; };
                            class R3 { name = "$STR_COMSPEC_SSE_CFG_1E0AE6D7F7"; value = "falcon_courier"; };
                            class R4 { name = "$STR_COMSPEC_SSE_CFG_5FE5851955"; value = "falcon_finance"; };
                            class R5 { name = "$STR_COMSPEC_SSE_CFG_8126E6E984"; value = "falcon_safehouse"; };
                            class R6 { name = "$STR_COMSPEC_SSE_CFG_1A7E2B7F81"; value = "falcon_noise"; };
                        };
                    };
                    class comspec_sse_missionSeed {
                        displayName = "$STR_COMSPEC_SSE_CFG_F4D438D348";
                        tooltip = "$STR_COMSPEC_SSE_CFG_6C242C62CF";
                        property = "comspec_sse_missionSeed";
                        control = "Edit";
                        expression = "_this setVariable ['comspec_sse_missionSeed', _value, isServer];";
                        defaultValue = "''";
                        typeName = "STRING";
                    };
                    class comspec_sse_region {
                        displayName = "$STR_COMSPEC_SSE_CFG_E3357CD7D5";
                        tooltip = "$STR_COMSPEC_SSE_CFG_2C6DC8BE22";
                        property = "comspec_sse_region";
                        control = "Combo";
                        expression = "_this setVariable ['comspec_sse_region', _value, isServer];";
                        defaultValue = "'IRAQ'";
                        typeName = "STRING";
                        class values {
                            class IRAQ { name = "$STR_COMSPEC_SSE_CFG_01AA4D2337"; value = "IRAQ"; default = 1; };
                            class SYRIA { name = "$STR_COMSPEC_SSE_CFG_6211A1E3C7"; value = "SYRIA"; };
                            class LEVANT { name = "$STR_COMSPEC_SSE_CFG_A8FA522986"; value = "LEVANT"; };
                            class AFRICA_SAHEL { name = "$STR_COMSPEC_SSE_CFG_30B56C8107"; value = "AFRICA_SAHEL"; };
                            class RUSSIA { name = "$STR_COMSPEC_SSE_CFG_D031524C84"; value = "RUSSIA"; };
                            class GENERIC { name = "$STR_COMSPEC_SSE_CFG_C2C5747AD0"; value = "GENERIC"; };
                            class RANDOM { name = "$STR_COMSPEC_SSE_CFG_6DBD11FF8A"; value = "RANDOM"; };
                        };
                    };
                    class comspec_sse_advancedData {
                        displayName = "$STR_COMSPEC_SSE_CFG_BFFE9D485C";
                        tooltip = "$STR_COMSPEC_SSE_CFG_DBEE120484";
                        property = "comspec_sse_advancedData";
                        control = "Edit";
                        expression = "_this setVariable ['comspec_sse_advancedData', _value, isServer];";
                        defaultValue = "''";
                        typeName = "STRING";
                    };
                };
            };
            #include "attributes_authored.hpp"
            #include "attributes_domex.hpp"
            #include "attributes_biometrics.hpp"
        };
    };
};
