#include "script_component.hpp"

class CfgPatches {
    class comspec_sse_eden {
        name = "COMSPEC SSE - Eden";
        units[] = {};
        weapons[] = {};
        requiredVersion = REQUIRED_VERSION;
        requiredAddons[] = {"comspec_sse_core", "comspec_sse_generator", "3DEN"};
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
                        expression = "if (_value isNotEqualTo '') then { _this setVariable ['comspec_sse_forcedType', _value, true]; };";
                        defaultValue = "''";
                        typeName = "STRING";
                        condition = "objectSimulated - objectBrain";
                        class values {
                            class Auto { name = "Automatique"; value = ""; default = 1; };
                            class Phone { name = "Téléphone"; value = "PHONE"; };
                            class Computer { name = "Ordinateur"; value = "COMPUTER"; };
                            class Radio { name = "Radio"; value = "RADIO"; };
                            class Document { name = "Document / papiers"; value = "DOCUMENT"; };
                            class Media { name = "Support (clé USB, carte, disque)"; value = "MEDIA"; };
                            class Weapon { name = "Arme"; value = "WEAPON"; };
                            class Container { name = "Caisse / sac / contenant"; value = "CONTAINER"; };
                            class Vehicle { name = "Véhicule"; value = "VEHICLE"; };
                            class Building { name = "Bâtiment / pièce"; value = "BUILDING"; };
                            class Object { name = "Objet divers"; value = "OBJECT"; };
                        };
                    };
                    class comspec_sse_profile {
                        displayName = "$STR_COMSPEC_SSE_CFG_669FE25A32";
                        tooltip = "$STR_COMSPEC_SSE_CFG_D3ABEFFAC9";
                        property = "comspec_sse_profile";
                        control = "Combo";
                        expression = "_this setVariable ['comspec_sse_profile', _value, isServer];";
                        defaultValue = "'RANDOM'";
                        typeName = "STRING";
                        class values {
                            class RANDOM { name = "Aléatoire"; value = "RANDOM"; default = 1; };
                            class CIVILIAN { name = "Civil"; value = "CIVILIAN"; };
                            class INSURGENT { name = "Insurgé"; value = "INSURGENT"; };
                            class MILITARY { name = "Militaire"; value = "MILITARY"; };
                            class HVT { name = "Chef / HVT"; value = "HVT"; };
                            class COURIER { name = "Courrier"; value = "COURIER"; };
                            class FINANCIER { name = "Financier"; value = "FINANCIER"; };
                            class TECHNICIAN { name = "Technicien / artificier"; value = "TECHNICIAN"; };
                            class INTELLIGENCE { name = "Renseignement"; value = "INTELLIGENCE"; };
                            class LOGISTICS { name = "Logistique"; value = "LOGISTICS"; };
                            class CUSTOM { name = "Personnalisé (contenu saisi)"; value = "CUSTOM"; };
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
                            class AUTO { name = "Automatique (au premier examen)"; value = "AUTO"; default = 1; };
                            class MANUAL { name = "Manuelle (module / script)"; value = "MANUAL"; };
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
                            class LIGHT { name = "Légère — quelques indices"; value = "LIGHT"; };
                            class STANDARD { name = "Standard"; value = "STANDARD"; default = 1; };
                            class DETAILED { name = "Détaillée"; value = "DETAILED"; };
                            class HIGH_VALUE { name = "Haute valeur — dossier riche"; value = "HIGH_VALUE"; };
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
                            class AUTO { name = "Automatique (identité Eden)"; value = "AUTO"; default = 1; };
                            class NONE { name = "Inventer un nom SSE"; value = "NONE"; };
                            class CUSTOM { name = "Forcer le nom Eden"; value = "CUSTOM"; };
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
                            class Auto { name = "Génération automatique (défaut)"; value = "auto"; default = 1; };
                            class Inconnu { name = "Inconnu des bases"; value = "inconnu"; };
                            class Signale { name = "Signalé — correspondance partielle"; value = "signale"; };
                            class Recherche { name = "Recherché — correspondance confirmée"; value = "recherche"; };
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
                        tooltip = "$STR_COMSPEC_SSE_CFG_DA1DD9E7AC";
                        property = "comspec_sse_bioMode";
                        control = "Combo";
                        expression = "_this setVariable ['comspec_sse_bioMode', _value, isServer];";
                        defaultValue = "'AUTO'";
                        typeName = "STRING";
                        class values {
                            class AUTO { name = "Générer automatiquement"; value = "AUTO"; default = 1; };
                            class NONE { name = "Ne pas inclure de biométrie"; value = "NONE"; };
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
                        tooltip = "$STR_COMSPEC_SSE_CFG_279BBE8E2C";
                        property = "comspec_sse_networkId";
                        control = "Edit";
                        expression = "_this setVariable ['comspec_sse_networkId', _value, isServer];";
                        defaultValue = "''";
                        typeName = "STRING";
                    };
                    class comspec_sse_modelId {
                        displayName = "$STR_COMSPEC_SSE_CFG_4508276612";
                        tooltip = "$STR_COMSPEC_SSE_CFG_8FB8E11D7C";
                        property = "comspec_sse_modelId";
                        control = "Edit";
                        expression = "_this setVariable ['comspec_sse_modelId', _value, isServer];";
                        defaultValue = "''";
                        typeName = "STRING";
                    };
                    class comspec_sse_datasetId {
                        displayName = "$STR_COMSPEC_SSE_CFG_71849A3505";
                        tooltip = "$STR_COMSPEC_SSE_CFG_4743916477";
                        property = "comspec_sse_datasetId";
                        control = "Edit";
                        expression = "_this setVariable ['comspec_sse_datasetId', _value, isServer];";
                        defaultValue = "''";
                        typeName = "STRING";
                    };
                    class comspec_sse_datasetRole {
                        displayName = "$STR_COMSPEC_SSE_CFG_DA8AB2FFDC";
                        tooltip = "$STR_COMSPEC_SSE_CFG_244DC42347";
                        property = "comspec_sse_datasetRole";
                        control = "Edit";
                        expression = "_this setVariable ['comspec_sse_datasetRole', _value, isServer];";
                        defaultValue = "''";
                        typeName = "STRING";
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
                            class IRAQ { name = "Irak"; value = "IRAQ"; default = 1; };
                            class SYRIA { name = "Syrie"; value = "SYRIA"; };
                            class LEVANT { name = "Levant"; value = "LEVANT"; };
                            class AFRICA_SAHEL { name = "Sahel"; value = "AFRICA_SAHEL"; };
                            class RUSSIA { name = "Russie / théâtre Est"; value = "RUSSIA"; };
                            class GENERIC { name = "Générique"; value = "GENERIC"; };
                            class RANDOM { name = "Aléatoire"; value = "RANDOM"; };
                        };
                    };
                    class comspec_sse_advancedData {
                        displayName = "$STR_COMSPEC_SSE_CFG_BFFE9D485C";
                        tooltip = "$STR_COMSPEC_SSE_CFG_FCA5FECEA4";
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
