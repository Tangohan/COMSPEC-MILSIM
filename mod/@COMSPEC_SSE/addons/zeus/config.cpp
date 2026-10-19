#include "script_component.hpp"
#include "ui_macros.hpp"

/*
    COMSPEC SSE — modules Zeus / Eden (V0.8).

    - Attributs Eden définis « à plat » (sans AttributesBase) : ne pas rouvrir
      Module_F:Logic (casse Eden / Zeus / ACE : « Updating base class »).
    - Zeus n'affiche pas les attributs : chaque module ouvre à la pose un
      formulaire vanilla (comspec_sse_fnc_uiForm, sans ZEN) prérempli avec les
      valeurs par défaut ci-dessous.
    - Exécution sur un seul poste : comspec_sse_fnc_moduleContext (logique
      locale = Zeus qui pose / serveur pour Eden).
    - Catégories : SSE (génération), SSE · Outils Zeus, SSE · Numérique.
*/

class CfgPatches {
    class comspec_sse_zeus {
        name = "COMSPEC SSE - Zeus";
        units[] = {
            "COMSPEC_SSE_Module_GenerateData",
            "COMSPEC_SSE_Module_GenerateSite",
            "COMSPEC_SSE_Module_InitTarget",
            "COMSPEC_SSE_Module_PlaceEvidence",
            "COMSPEC_SSE_Module_ApplyModel",
            "COMSPEC_SSE_Module_SaveModel",
            "COMSPEC_SSE_Module_GenerateBrief",
            "COMSPEC_SSE_Module_ScenarioDirector",
            "COMSPEC_SSE_Module_SandboxSite",
            "COMSPEC_SSE_Module_LinkEntities",
            "COMSPEC_SSE_Module_CaseReference",
            "COMSPEC_SSE_Module_ZeusControl",
            "COMSPEC_SSE_Module_ViewData",
            "COMSPEC_SSE_Module_SpoilView",
            "COMSPEC_SSE_Module_SiteManager",
            "COMSPEC_SSE_Module_AfterAction",
            "COMSPEC_SSE_Module_ListModels",
            "COMSPEC_SSE_Module_ClearData",
            "COMSPEC_SSE_Module_ResetSite",
            "COMSPEC_SSE_Module_DebugInspector",
            "COMSPEC_SSE_Module_DomexMark",
            "COMSPEC_SSE_Module_DomexAddIntel",
            "COMSPEC_SSE_Module_DomexSetStage",
            "COMSPEC_SSE_Module_DomexMapPoint",
            "COMSPEC_Module_SSE_Case",
            "COMSPEC_Module_SSE_Profile",
            "COMSPEC_Module_SSE_Equip",
            "COMSPEC_Module_SSE_DocChrome"
        };
        weapons[] = {};
        requiredVersion = REQUIRED_VERSION;
        requiredAddons[] = {"comspec_sse_core", "comspec_sse_generator", "comspec_sse_intel", "comspec_sse_ui", "comspec_sse_main", "comspec_sse_eden", "comspec_sse_network", "cba_xeh", "A3_Modules_F", "comspec_atak_native_main"};
        author = "COMSPEC";
        VERSION_CONFIG;
    };
};

class CfgFactionClasses {
    class NO_CATEGORY;
    class COMSPEC_SSE_Tools: NO_CATEGORY {
        displayName = "$STR_COMSPEC_SSE_CFG_543C09E508";
        priority = 2;
        side = 7;
    };
    class COMSPEC_SSE_Domex: NO_CATEGORY {
        displayName = "$STR_COMSPEC_SSE_CFG_722625B3ED";
        priority = 2;
        side = 7;
    };
};

class CfgFunctions {
    class comspec_sse {
        tag = "comspec_sse";

        class zeus {
            file = "z\comspec_sse\addons\zeus\functions";
            class moduleGenerateData {};
            class moduleGenerateSite {};
            class moduleInitTarget {};
            class modulePlaceEvidence {};
            class moduleApplyModel {};
            class moduleSaveModel {};
            class moduleGenerateBrief {};
            class moduleScenarioDirector {};
            class moduleSandboxSite {};
            class moduleLinkEntities {};
            class moduleCaseReference {};
            class moduleZeusControl {};
            class moduleViewData {};
            class moduleSpoilView {};
            class moduleSiteManager {};
            class moduleAfterAction {};
            class moduleListModels {};
            class moduleClearData {};
            class moduleResetSite {};
            class moduleDebugInspector {};
            class moduleDomexMark {};
            class moduleDomexAddIntel {};
            class moduleDomexSetStage {};
            class moduleDomexMapPoint {};
            class moduleContext {};
            class zeusNotify {};
            class zeusChoices {};
            class zeusComboData {};
            class zeusGenerateTargets {};
            class zeusBroadcastEnabled {};
            class zeusClearEntities {};
            class zeusLink {};
            class curatorSelectedObjects {};
            class domexPickObject {};
            class domexEnsureNode {};
            class domexAddLivePacket {};
            class domexSetStage {};
            class domexPlaceMapPoint {};
            class registerZenDomexLive {};
            class openGenerateDialog {};
            class applyGenerateDialog {};
            class openModelDialog {};
            class applyModelDialog {};
            // Repris du mod Overwatch (tout le SSE passe par ce pack).
            class moduleTargets {};
            class profilePreset {};
            class applyProfile {};
            class moduleSubjectProfile {};
            class moduleEquipSeek {};
            class moduleDocChrome {};
            class giveSeek {};
            class registerZenSseModules {};
        };
    };
};

class CfgVehicles {
    // Forward-declare uniquement (voir en-tête).
    class Module_F;

    class COMSPEC_SSE_Module_Base: Module_F {
        author = "COMSPEC";
        scope = 1;
        scopeCurator = 1;
        category = "COMSPEC_SSE";
        functionPriority = 1;
        isGlobal = 1;
        isTriggerActivated = 0;
        isDisposable = 1;
        is3DEN = 0;
        curatorCanAttach = 1;
        icon = "\A3\ui_f\data\igui\cfg\simpletasks\types\intel_ca.paa";
        portrait = "\A3\ui_f\data\igui\cfg\simpletasks\types\intel_ca.paa";
    };

    class COMSPEC_SSE_Module_GenerateData: COMSPEC_SSE_Module_Base {
        scope = 2;
        scopeCurator = 2;
        displayName = "$STR_COMSPEC_SSE_CFG_7F2A68EF59";
        category = "COMSPEC_SSE";
        function = "comspec_sse_fnc_moduleGenerateData";
        icon = "\A3\ui_f\data\igui\cfg\simpletasks\types\intel_ca.paa";
        portrait = "\A3\ui_f\data\igui\cfg\simpletasks\types\intel_ca.paa";

        class Attributes {
            class Profile {
                displayName = "$STR_COMSPEC_SSE_CFG_669FE25A32";
                tooltip = "$STR_COMSPEC_SSE_CFG_7A901D689B";
                property = "COMSPEC_SSE_Module_GenerateData_Profile";
                control = "Combo";
                expression = "_this setVariable ['Profile',_value,true];";
                defaultValue = "'INSURGENT'";
                typeName = "STRING";
                class values {
                    class V0 { name = "$STR_COMSPEC_SSE_CFG_DA14187553"; value = "INSURGENT"; default = 1; };
                    class V1 { name = "$STR_COMSPEC_SSE_CFG_496B2073F0"; value = "CIVILIAN"; };
                    class V2 { name = "$STR_COMSPEC_SSE_CFG_1E0730DF18"; value = "MILITARY"; };
                    class V3 { name = "$STR_COMSPEC_SSE_CFG_21C1444A64"; value = "COMMANDER"; };
                    class V4 { name = "$STR_COMSPEC_SSE_CFG_6347EE1AA8"; value = "COURIER"; };
                    class V5 { name = "$STR_COMSPEC_SSE_CFG_8FAF1224A7"; value = "FINANCIER"; };
                    class V6 { name = "$STR_COMSPEC_SSE_CFG_E3CCC90C1A"; value = "TECHNICIAN"; };
                    class V7 { name = "$STR_COMSPEC_SSE_CFG_672B3D9978"; value = "INTELLIGENCE"; };
                    class V8 { name = "$STR_COMSPEC_SSE_CFG_8C266B946B"; value = "LOGISTICS"; };
                    class V9 { name = "$STR_COMSPEC_SSE_CFG_6DBD11FF8A"; value = "RANDOM"; };
                };
            };
            class Complexity {
                displayName = "$STR_COMSPEC_SSE_CFG_684A67E212";
                tooltip = "$STR_COMSPEC_SSE_CFG_E25C26010F";
                property = "COMSPEC_SSE_Module_GenerateData_Complexity";
                control = "Combo";
                expression = "_this setVariable ['Complexity',_value,true];";
                defaultValue = "'STANDARD'";
                typeName = "STRING";
                class values {
                    class V0 { name = "$STR_COMSPEC_SSE_CFG_142CF342A2"; value = "LIGHT"; };
                    class V1 { name = "$STR_COMSPEC_SSE_CFG_2DFA66079D"; value = "STANDARD"; default = 1; };
                    class V2 { name = "$STR_COMSPEC_SSE_CFG_094D4A1824"; value = "DETAILED"; };
                    class V3 { name = "$STR_COMSPEC_SSE_CFG_78AEB2046E"; value = "HIGH_VALUE"; };
                };
            };
            class WantIdentity {
                displayName = "$STR_COMSPEC_SSE_CFG_755A7A62F0";
                tooltip = "$STR_COMSPEC_SSE_CFG_F17D6914A9";
                property = "COMSPEC_SSE_Module_GenerateData_WantIdentity";
                control = "Checkbox";
                expression = "_this setVariable ['WantIdentity',_value,true];";
                defaultValue = "true";
                typeName = "BOOL";
            };
            class WantPhone {
                displayName = "$STR_COMSPEC_SSE_CFG_D3B023EABC";
                tooltip = "$STR_COMSPEC_SSE_CFG_0FA3A18743";
                property = "COMSPEC_SSE_Module_GenerateData_WantPhone";
                control = "Checkbox";
                expression = "_this setVariable ['WantPhone',_value,true];";
                defaultValue = "true";
                typeName = "BOOL";
            };
            class WantDocuments {
                displayName = "$STR_COMSPEC_SSE_CFG_687C82861C";
                tooltip = "$STR_COMSPEC_SSE_CFG_7D1E33E915";
                property = "COMSPEC_SSE_Module_GenerateData_WantDocuments";
                control = "Checkbox";
                expression = "_this setVariable ['WantDocuments',_value,true];";
                defaultValue = "true";
                typeName = "BOOL";
            };
            class WantBio {
                displayName = "$STR_COMSPEC_SSE_CFG_5427FD158C";
                tooltip = "$STR_COMSPEC_SSE_CFG_ED79F964B5";
                property = "COMSPEC_SSE_Module_GenerateData_WantBio";
                control = "Checkbox";
                expression = "_this setVariable ['WantBio',_value,true];";
                defaultValue = "true";
                typeName = "BOOL";
            };
            class WantNetwork {
                displayName = "$STR_COMSPEC_SSE_CFG_C8549ECACC";
                tooltip = "$STR_COMSPEC_SSE_CFG_11767A27F9";
                property = "COMSPEC_SSE_Module_GenerateData_WantNetwork";
                control = "Checkbox";
                expression = "_this setVariable ['WantNetwork',_value,true];";
                defaultValue = "true";
                typeName = "BOOL";
            };
            class NoisePct {
                displayName = "$STR_COMSPEC_SSE_CFG_A77A7789A1";
                tooltip = "$STR_COMSPEC_SSE_CFG_23EE930FE8";
                property = "COMSPEC_SSE_Module_GenerateData_NoisePct";
                control = "Combo";
                expression = "_this setVariable ['NoisePct',_value,true];";
                defaultValue = "25";
                typeName = "NUMBER";
                class values {
                    class V0 { name = "$STR_COMSPEC_SSE_CFG_F88CAF65E9"; value = 0; };
                    class V1 { name = "10 %"; value = 10; };
                    class V2 { name = "$STR_COMSPEC_SSE_CFG_5210C2CF7B"; value = 25; default = 1; };
                    class V3 { name = "50 %"; value = 50; };
                    class V4 { name = "75 %"; value = 75; };
                };
            };
        };

        class ModuleDescription {
            description = "$STR_COMSPEC_SSE_CFG_3C0774D059";
            sync[] = {"AnyPerson", "AnyVehicle", "Anything"};
        };
    };

    class COMSPEC_SSE_Module_GenerateSite: COMSPEC_SSE_Module_Base {
        scope = 2;
        scopeCurator = 2;
        displayName = "$STR_COMSPEC_SSE_CFG_742F4979BD";
        category = "COMSPEC_SSE";
        function = "comspec_sse_fnc_moduleGenerateSite";
        icon = "\A3\ui_f\data\igui\cfg\simpletasks\types\search_ca.paa";
        portrait = "\A3\ui_f\data\igui\cfg\simpletasks\types\search_ca.paa";
        canSetArea = 1;
        canSetAreaHeight = 0;
        canSetAreaShape = 1;

        class Attributes {
            class Radius {
                displayName = "$STR_COMSPEC_SSE_CFG_3D06EC48BE";
                tooltip = "$STR_COMSPEC_SSE_CFG_C77C2BCB7D";
                property = "COMSPEC_SSE_Module_GenerateSite_Radius";
                control = "Combo";
                expression = "_this setVariable ['Radius',_value,true];";
                defaultValue = "35";
                typeName = "NUMBER";
                class values {
                    class V0 { name = "15 m"; value = 15; };
                    class V1 { name = "25 m"; value = 25; };
                    class V2 { name = "35 m"; value = 35; default = 1; };
                    class V3 { name = "50 m"; value = 50; };
                    class V4 { name = "75 m"; value = 75; };
                    class V5 { name = "100 m"; value = 100; };
                    class V6 { name = "150 m"; value = 150; };
                };
            };
            class Profile {
                displayName = "$STR_COMSPEC_SSE_CFG_477589B159";
                tooltip = "$STR_COMSPEC_SSE_CFG_EB90E9A8BB";
                property = "COMSPEC_SSE_Module_GenerateSite_Profile";
                control = "Combo";
                expression = "_this setVariable ['Profile',_value,true];";
                defaultValue = "'INSURGENT'";
                typeName = "STRING";
                class values {
                    class V0 { name = "$STR_COMSPEC_SSE_CFG_DE7B87210E"; value = "INSURGENT"; default = 1; };
                    class V1 { name = "$STR_COMSPEC_SSE_CFG_376A02EAF1"; value = "MILITARY"; };
                    class V2 { name = "$STR_COMSPEC_SSE_CFG_153BFBF036"; value = "CIVILIAN"; };
                    class V3 { name = "$STR_COMSPEC_SSE_CFG_EB0DB645A0"; value = "COMMANDER"; };
                    class V4 { name = "$STR_COMSPEC_SSE_CFG_6672752805"; value = "LOGISTICS"; };
                    class V5 { name = "$STR_COMSPEC_SSE_CFG_4B527FCBB9"; value = "FINANCIER"; };
                    class V6 { name = "$STR_COMSPEC_SSE_CFG_8B7C63FB42"; value = "TECHNICIAN"; };
                    class V7 { name = "$STR_COMSPEC_SSE_CFG_6DBD11FF8A"; value = "RANDOM"; };
                };
            };
            class Complexity {
                displayName = "$STR_COMSPEC_SSE_CFG_684A67E212";
                tooltip = "$STR_COMSPEC_SSE_CFG_73FCB9DB05";
                property = "COMSPEC_SSE_Module_GenerateSite_Complexity";
                control = "Combo";
                expression = "_this setVariable ['Complexity',_value,true];";
                defaultValue = "'DETAILED'";
                typeName = "STRING";
                class values {
                    class V0 { name = "$STR_COMSPEC_SSE_CFG_142CF342A2"; value = "LIGHT"; };
                    class V1 { name = "$STR_COMSPEC_SSE_CFG_2DFA66079D"; value = "STANDARD"; };
                    class V2 { name = "$STR_COMSPEC_SSE_CFG_094D4A1824"; value = "DETAILED"; default = 1; };
                    class V3 { name = "$STR_COMSPEC_SSE_CFG_78AEB2046E"; value = "HIGH_VALUE"; };
                };
            };
            class MaxObjects {
                displayName = "$STR_COMSPEC_SSE_CFG_BC5DE8C5EA";
                tooltip = "$STR_COMSPEC_SSE_CFG_640278ED98";
                property = "COMSPEC_SSE_Module_GenerateSite_MaxObjects";
                control = "Combo";
                expression = "_this setVariable ['MaxObjects',_value,true];";
                defaultValue = "8";
                typeName = "NUMBER";
                class values {
                    class V0 { name = "$STR_COMSPEC_SSE_CFG_B2ED82F1BF"; value = 0; };
                    class V1 { name = "4"; value = 4; };
                    class V2 { name = "$STR_COMSPEC_SSE_CFG_7B9FBC964D"; value = 8; default = 1; };
                    class V3 { name = "12"; value = 12; };
                    class V4 { name = "20"; value = 20; };
                    class V5 { name = "30"; value = 30; };
                };
            };
            class WantDigital {
                displayName = "$STR_COMSPEC_SSE_CFG_DAF2ABC7B8";
                tooltip = "$STR_COMSPEC_SSE_CFG_7A072D2DDB";
                property = "COMSPEC_SSE_Module_GenerateSite_WantDigital";
                control = "Checkbox";
                expression = "_this setVariable ['WantDigital',_value,true];";
                defaultValue = "true";
                typeName = "BOOL";
            };
            class WantDocuments {
                displayName = "$STR_COMSPEC_SSE_CFG_687C82861C";
                tooltip = "$STR_COMSPEC_SSE_CFG_9CF741A9FE";
                property = "COMSPEC_SSE_Module_GenerateSite_WantDocuments";
                control = "Checkbox";
                expression = "_this setVariable ['WantDocuments',_value,true];";
                defaultValue = "true";
                typeName = "BOOL";
            };
            class WantNetwork {
                displayName = "$STR_COMSPEC_SSE_CFG_039B2618BD";
                tooltip = "$STR_COMSPEC_SSE_CFG_86CCB1FDFD";
                property = "COMSPEC_SSE_Module_GenerateSite_WantNetwork";
                control = "Checkbox";
                expression = "_this setVariable ['WantNetwork',_value,true];";
                defaultValue = "true";
                typeName = "BOOL";
            };
        };

        class ModuleDescription {
            description = "$STR_COMSPEC_SSE_CFG_F5D5D6B3DF";
            position = 1;
        };
    };

    class COMSPEC_SSE_Module_InitTarget: COMSPEC_SSE_Module_Base {
        scope = 2;
        scopeCurator = 2;
        displayName = "$STR_COMSPEC_SSE_CFG_6E5970A28A";
        category = "COMSPEC_SSE";
        function = "comspec_sse_fnc_moduleInitTarget";
        icon = "\A3\ui_f\data\igui\cfg\simpletasks\types\use_ca.paa";
        portrait = "\A3\ui_f\data\igui\cfg\simpletasks\types\use_ca.paa";

        class Attributes {
            class EntityType {
                displayName = "$STR_COMSPEC_SSE_CFG_C7EB9F3AAD";
                tooltip = "$STR_COMSPEC_SSE_CFG_6323D66FED";
                property = "COMSPEC_SSE_Module_InitTarget_EntityType";
                control = "Combo";
                expression = "_this setVariable ['EntityType',_value,true];";
                defaultValue = "'AUTO'";
                typeName = "STRING";
                class values {
                    class V0 { name = "$STR_COMSPEC_SSE_CFG_C5F67A36BA"; value = "AUTO"; default = 1; };
                    class V1 { name = "$STR_COMSPEC_SSE_CFG_ADEDF35BDA"; value = "OBJECT"; };
                    class V2 { name = "$STR_COMSPEC_SSE_CFG_E214B8A299"; value = "DOCUMENT"; };
                    class V3 { name = "$STR_COMSPEC_SSE_CFG_D3B023EABC"; value = "PHONE"; };
                    class V4 { name = "$STR_COMSPEC_SSE_CFG_CBAB5F515D"; value = "COMPUTER"; };
                    class V5 { name = "$STR_COMSPEC_SSE_CFG_B11BF19E3F"; value = "RADIO"; };
                    class V6 { name = "$STR_COMSPEC_SSE_CFG_16ECE94EB2"; value = "CONTAINER"; };
                    class V7 { name = "$STR_COMSPEC_SSE_CFG_D7DB063433"; value = "WEAPON"; };
                    class V8 { name = "$STR_COMSPEC_SSE_CFG_87C24BCDC7"; value = "VEHICLE"; };
                };
            };
            class Profile {
                displayName = "$STR_COMSPEC_SSE_CFG_669FE25A32";
                tooltip = "$STR_COMSPEC_SSE_CFG_1EB4119497";
                property = "COMSPEC_SSE_Module_InitTarget_Profile";
                control = "Combo";
                expression = "_this setVariable ['Profile',_value,true];";
                defaultValue = "'RANDOM'";
                typeName = "STRING";
                class values {
                    class V0 { name = "$STR_COMSPEC_SSE_CFG_DA14187553"; value = "INSURGENT"; };
                    class V1 { name = "$STR_COMSPEC_SSE_CFG_496B2073F0"; value = "CIVILIAN"; };
                    class V2 { name = "$STR_COMSPEC_SSE_CFG_1E0730DF18"; value = "MILITARY"; };
                    class V3 { name = "$STR_COMSPEC_SSE_CFG_21C1444A64"; value = "COMMANDER"; };
                    class V4 { name = "$STR_COMSPEC_SSE_CFG_6347EE1AA8"; value = "COURIER"; };
                    class V5 { name = "$STR_COMSPEC_SSE_CFG_8FAF1224A7"; value = "FINANCIER"; };
                    class V6 { name = "$STR_COMSPEC_SSE_CFG_E3CCC90C1A"; value = "TECHNICIAN"; };
                    class V7 { name = "$STR_COMSPEC_SSE_CFG_672B3D9978"; value = "INTELLIGENCE"; };
                    class V8 { name = "$STR_COMSPEC_SSE_CFG_8C266B946B"; value = "LOGISTICS"; };
                    class V9 { name = "$STR_COMSPEC_SSE_CFG_6DBD11FF8A"; value = "RANDOM"; default = 1; };
                };
            };
            class Complexity {
                displayName = "$STR_COMSPEC_SSE_CFG_684A67E212";
                tooltip = "$STR_COMSPEC_SSE_CFG_1ED5C5DECE";
                property = "COMSPEC_SSE_Module_InitTarget_Complexity";
                control = "Combo";
                expression = "_this setVariable ['Complexity',_value,true];";
                defaultValue = "'STANDARD'";
                typeName = "STRING";
                class values {
                    class V0 { name = "$STR_COMSPEC_SSE_CFG_142CF342A2"; value = "LIGHT"; };
                    class V1 { name = "$STR_COMSPEC_SSE_CFG_2DFA66079D"; value = "STANDARD"; default = 1; };
                    class V2 { name = "$STR_COMSPEC_SSE_CFG_094D4A1824"; value = "DETAILED"; };
                    class V3 { name = "$STR_COMSPEC_SSE_CFG_78AEB2046E"; value = "HIGH_VALUE"; };
                };
            };
        };

        class ModuleDescription {
            description = "$STR_COMSPEC_SSE_CFG_103464D011";
            sync[] = {"Anything"};
        };
    };

    class COMSPEC_SSE_Module_PlaceEvidence: COMSPEC_SSE_Module_Base {
        scope = 2;
        scopeCurator = 2;
        displayName = "$STR_COMSPEC_SSE_CFG_D51F236499";
        category = "COMSPEC_SSE";
        function = "comspec_sse_fnc_modulePlaceEvidence";
        icon = "\A3\ui_f\data\igui\cfg\simpletasks\types\documents_ca.paa";
        portrait = "\A3\ui_f\data\igui\cfg\simpletasks\types\documents_ca.paa";

        class Attributes {
            class ObjectClass {
                displayName = "$STR_COMSPEC_SSE_CFG_3DE621C50D";
                tooltip = "$STR_COMSPEC_SSE_CFG_33CF4A0F55";
                property = "COMSPEC_SSE_Module_PlaceEvidence_ObjectClass";
                control = "Combo";
                expression = "_this setVariable ['ObjectClass',_value,true];";
                defaultValue = "'Land_Laptop_unfolded_F'";
                typeName = "STRING";
                class values {
                    class V0 { name = "$STR_COMSPEC_SSE_CFG_A9AF08EC30"; value = "Land_Laptop_unfolded_F"; default = 1; };
                    class V1 { name = "$STR_COMSPEC_SSE_CFG_F9AE10397E"; value = "Land_MobilePhone_smart_F"; };
                    class V2 { name = "$STR_COMSPEC_SSE_CFG_BEA4F182B9"; value = "Land_MobilePhone_old_F"; };
                    class V3 { name = "$STR_COMSPEC_SSE_CFG_785848D184"; value = "Land_SatellitePhone_F"; };
                    class V4 { name = "$STR_COMSPEC_SSE_CFG_C4A26F91C8"; value = "Land_PortableLongRangeRadio_F"; };
                    class V5 { name = "$STR_COMSPEC_SSE_CFG_E30A3BA460"; value = "Land_File1_F"; };
                    class V6 { name = "$STR_COMSPEC_SSE_CFG_4CF0E07E9E"; value = "Land_FilePhotos_F"; };
                    class V7 { name = "$STR_COMSPEC_SSE_CFG_BFD5F89562"; value = "Land_Suitcase_F"; };
                    class V8 { name = "$STR_COMSPEC_SSE_CFG_9D1BE95651"; value = "Box_FIA_Wps_F"; };
                };
            };
            class Profile {
                displayName = "$STR_COMSPEC_SSE_CFG_669FE25A32";
                tooltip = "$STR_COMSPEC_SSE_CFG_1EB4119497";
                property = "COMSPEC_SSE_Module_PlaceEvidence_Profile";
                control = "Combo";
                expression = "_this setVariable ['Profile',_value,true];";
                defaultValue = "'INSURGENT'";
                typeName = "STRING";
                class values {
                    class V0 { name = "$STR_COMSPEC_SSE_CFG_DA14187553"; value = "INSURGENT"; default = 1; };
                    class V1 { name = "$STR_COMSPEC_SSE_CFG_496B2073F0"; value = "CIVILIAN"; };
                    class V2 { name = "$STR_COMSPEC_SSE_CFG_1E0730DF18"; value = "MILITARY"; };
                    class V3 { name = "$STR_COMSPEC_SSE_CFG_21C1444A64"; value = "COMMANDER"; };
                    class V4 { name = "$STR_COMSPEC_SSE_CFG_6347EE1AA8"; value = "COURIER"; };
                    class V5 { name = "$STR_COMSPEC_SSE_CFG_8FAF1224A7"; value = "FINANCIER"; };
                    class V6 { name = "$STR_COMSPEC_SSE_CFG_E3CCC90C1A"; value = "TECHNICIAN"; };
                    class V7 { name = "$STR_COMSPEC_SSE_CFG_672B3D9978"; value = "INTELLIGENCE"; };
                    class V8 { name = "$STR_COMSPEC_SSE_CFG_8C266B946B"; value = "LOGISTICS"; };
                    class V9 { name = "$STR_COMSPEC_SSE_CFG_6DBD11FF8A"; value = "RANDOM"; };
                };
            };
            class Complexity {
                displayName = "$STR_COMSPEC_SSE_CFG_684A67E212";
                tooltip = "$STR_COMSPEC_SSE_CFG_1ED5C5DECE";
                property = "COMSPEC_SSE_Module_PlaceEvidence_Complexity";
                control = "Combo";
                expression = "_this setVariable ['Complexity',_value,true];";
                defaultValue = "'STANDARD'";
                typeName = "STRING";
                class values {
                    class V0 { name = "$STR_COMSPEC_SSE_CFG_142CF342A2"; value = "LIGHT"; };
                    class V1 { name = "$STR_COMSPEC_SSE_CFG_2DFA66079D"; value = "STANDARD"; default = 1; };
                    class V2 { name = "$STR_COMSPEC_SSE_CFG_094D4A1824"; value = "DETAILED"; };
                    class V3 { name = "$STR_COMSPEC_SSE_CFG_78AEB2046E"; value = "HIGH_VALUE"; };
                };
            };
            class GenerateNow {
                displayName = "$STR_COMSPEC_SSE_CFG_120CFD1431";
                tooltip = "$STR_COMSPEC_SSE_CFG_82929654C4";
                property = "COMSPEC_SSE_Module_PlaceEvidence_GenerateNow";
                control = "Checkbox";
                expression = "_this setVariable ['GenerateNow',_value,true];";
                defaultValue = "false";
                typeName = "BOOL";
            };
        };

        class ModuleDescription {
            description = "$STR_COMSPEC_SSE_CFG_7DFD48AFCC";
            position = 1;
        };
    };

    class COMSPEC_SSE_Module_ApplyModel: COMSPEC_SSE_Module_Base {
        scope = 2;
        scopeCurator = 2;
        displayName = "$STR_COMSPEC_SSE_CFG_0EBEB492CC";
        category = "COMSPEC_SSE";
        function = "comspec_sse_fnc_moduleApplyModel";
        icon = "\A3\ui_f\data\igui\cfg\simpletasks\types\upload_ca.paa";
        portrait = "\A3\ui_f\data\igui\cfg\simpletasks\types\upload_ca.paa";

        class Attributes {
            class ModelId {
                displayName = "$STR_COMSPEC_SSE_CFG_C6C3E60F79";
                tooltip = "$STR_COMSPEC_SSE_CFG_858C62EAA6";
                property = "COMSPEC_SSE_Module_ApplyModel_ModelId";
                control = "Combo";
                expression = "_this setVariable ['ModelId',_value,true];";
                defaultValue = "''";
                typeName = "STRING";
                class values {
                    class M0 { name = "$STR_COMSPEC_SSE_CFG_EA2769807B"; value = ""; default = 1; };
                    class M1 { name = "$STR_COMSPEC_SSE_CFG_3BFCB38A4B"; value = "builtin_cellule_insurgee_irak"; };
                    class M2 { name = "$STR_COMSPEC_SSE_CFG_9FE0FC9DA7"; value = "builtin_chef_hvt"; };
                    class M3 { name = "$STR_COMSPEC_SSE_CFG_6010C7C817"; value = "builtin_reseau_courriers"; };
                    class M4 { name = "$STR_COMSPEC_SSE_CFG_8FAF1224A7"; value = "builtin_financier"; };
                    class M5 { name = "$STR_COMSPEC_SSE_CFG_3693546040"; value = "builtin_technicien_ied"; };
                    class M6 { name = "$STR_COMSPEC_SSE_CFG_75EC0B4D62"; value = "builtin_safehouse"; };
                    class M7 { name = "$STR_COMSPEC_SSE_CFG_4C8CC7A3EC"; value = "builtin_contrebande_sahel"; };
                    class M8 { name = "$STR_COMSPEC_SSE_CFG_B86495F4EB"; value = "builtin_civil_bruit"; };
                    class M9 { name = "$STR_COMSPEC_SSE_CFG_276E969B91"; value = "builtin_drone_isr"; };
                    class M10 { name = "$STR_COMSPEC_SSE_CFG_0FACE5185C"; value = "builtin_propagande"; };
                    class M11 { name = "$STR_COMSPEC_SSE_CFG_8B7D880BF4"; value = "builtin_iq_2010_2020_cache_armes"; };
                    class M12 { name = "$STR_COMSPEC_SSE_CFG_D2F7CDF304"; value = "builtin_iq_2010_2020_ied"; };
                    class M13 { name = "$STR_COMSPEC_SSE_CFG_144FC9F839"; value = "builtin_iq_2010_2020_hvt"; };
                    class M14 { name = "$STR_COMSPEC_SSE_CFG_AAC31F19B0"; value = "builtin_iq_2010_2020_courrier"; };
                    class M15 { name = "$STR_COMSPEC_SSE_CFG_7682EADCE0"; value = "builtin_iq_2010_2020_financier"; };
                    class M16 { name = "$STR_COMSPEC_SSE_CFG_7A8AF82CAB"; value = "builtin_iq_2010_2020_safehouse"; };
                    class M17 { name = "$STR_COMSPEC_SSE_CFG_FE43AD8A72"; value = "builtin_ru_2020_2024_recon"; };
                    class M18 { name = "$STR_COMSPEC_SSE_CFG_A70B51DFDB"; value = "builtin_ru_2020_2024_logistics"; };
                    class M19 { name = "$STR_COMSPEC_SSE_CFG_626DFD9908"; value = "builtin_ru_2020_2024_command"; };
                    class M20 { name = "$STR_COMSPEC_SSE_CFG_AA22585C95"; value = "builtin_ru_2020_2024_drone"; };
                    class M21 { name = "$STR_COMSPEC_SSE_CFG_6FD04FDA7D"; value = "builtin_ru_2020_2024_ew"; };
                    class M22 { name = "$STR_COMSPEC_SSE_CFG_81C4D66AAB"; value = "builtin_ru_2020_2024_infoops"; };
                    class M23 { name = "$STR_COMSPEC_SSE_CFG_671DC73F67"; value = "builtin_ru_2020_2024_courier"; };
                    class M24 { name = "$STR_COMSPEC_SSE_CFG_BF9DB25346"; value = "builtin_ru_2020_2024_civil"; };
                };
            };
            class ModelIdCustom {
                displayName = "$STR_COMSPEC_SSE_CFG_2BAA7A0466";
                tooltip = "$STR_COMSPEC_SSE_CFG_F7F514A142";
                property = "COMSPEC_SSE_Module_ApplyModel_ModelIdCustom";
                control = "Edit";
                expression = "_this setVariable ['ModelIdCustom',_value,true];";
                defaultValue = "''";
                typeName = "STRING";
            };
        };

        class ModuleDescription {
            description = "$STR_COMSPEC_SSE_CFG_B24A522F8C";
            sync[] = {"Anything"};
        };
    };

    class COMSPEC_SSE_Module_SaveModel: COMSPEC_SSE_Module_Base {
        scope = 1;
        scopeCurator = 2;
        displayName = "$STR_COMSPEC_SSE_CFG_105C4BC7ED";
        category = "COMSPEC_SSE_Tools";
        function = "comspec_sse_fnc_moduleSaveModel";
        icon = "\A3\ui_f\data\igui\cfg\simpletasks\types\download_ca.paa";
        portrait = "\A3\ui_f\data\igui\cfg\simpletasks\types\download_ca.paa";

        class Attributes {
            class ModelName {
                displayName = "$STR_COMSPEC_SSE_CFG_68D49F6742";
                tooltip = "$STR_COMSPEC_SSE_CFG_6C1449490C";
                property = "COMSPEC_SSE_Module_SaveModel_ModelName";
                control = "Edit";
                expression = "_this setVariable ['ModelName',_value,true];";
                defaultValue = "'Mon modèle SSE'";
                typeName = "STRING";
            };
        };

        class ModuleDescription {
            description = "$STR_COMSPEC_SSE_CFG_D08DE436A5";
            sync[] = {"Anything"};
        };
    };

    class COMSPEC_SSE_Module_GenerateBrief: COMSPEC_SSE_Module_Base {
        scope = 2;
        scopeCurator = 2;
        displayName = "$STR_COMSPEC_SSE_CFG_D70012B745";
        category = "COMSPEC_SSE_Mission";
        function = "comspec_sse_fnc_moduleGenerateBrief";
        icon = "\A3\ui_f\data\igui\cfg\simpletasks\types\meet_ca.paa";
        portrait = "\A3\ui_f\data\igui\cfg\simpletasks\types\meet_ca.paa";

        class Attributes {
            class Brief {
                displayName = "$STR_COMSPEC_SSE_CFG_693EE04D82";
                tooltip = "$STR_COMSPEC_SSE_CFG_9D0EA0FAAD";
                property = "COMSPEC_SSE_Module_GenerateBrief_Brief";
                control = "Edit";
                expression = "_this setVariable ['Brief',_value,true];";
                defaultValue = "'cellule logistique de 5 personnes'";
                typeName = "STRING";
            };
            class ScenarioPack {
                displayName = "$STR_COMSPEC_SSE_CFG_7EEBB29A47";
                tooltip = "$STR_COMSPEC_SSE_CFG_A23CBDE313";
                property = "COMSPEC_SSE_Module_GenerateBrief_ScenarioPack";
                control = "Combo";
                expression = "_this setVariable ['ScenarioPack',_value,true];";
                defaultValue = "''";
                typeName = "STRING";
                class values {
                    class V0 { name = "$STR_COMSPEC_SSE_CFG_6B34025C4D"; value = ""; default = 1; };
                    class V1 { name = "$STR_COMSPEC_SSE_CFG_DE7B87210E"; value = "INSURGENT_CELL"; };
                    class V2 { name = "$STR_COMSPEC_SSE_CFG_8C700F2723"; value = "SMUGGLING_NETWORK"; };
                    class V3 { name = "$STR_COMSPEC_SSE_CFG_197A4A1389"; value = "WEAPONS_DEPOT"; };
                    class V4 { name = "$STR_COMSPEC_SSE_CFG_EB0DB645A0"; value = "COMMAND_POST"; };
                    class V5 { name = "$STR_COMSPEC_SSE_CFG_84775AE87C"; value = "SAFEHOUSE"; };
                    class V6 { name = "$STR_COMSPEC_SSE_CFG_6545F04364"; value = "IED_WORKSHOP"; };
                    class V7 { name = "$STR_COMSPEC_SSE_CFG_3299228C00"; value = "FINANCIAL_NODE"; };
                    class V8 { name = "$STR_COMSPEC_SSE_CFG_221A82EE48"; value = "INTELLIGENCE_CELL"; };
                    class V9 { name = "$STR_COMSPEC_SSE_CFG_4549E689FB"; value = "FALCON"; };
                };
            };
            class Radius {
                displayName = "$STR_COMSPEC_SSE_CFG_3D06EC48BE";
                tooltip = "$STR_COMSPEC_SSE_CFG_BA9C7D1B15";
                property = "COMSPEC_SSE_Module_GenerateBrief_Radius";
                control = "Combo";
                expression = "_this setVariable ['Radius',_value,true];";
                defaultValue = "40";
                typeName = "NUMBER";
                class values {
                    class V0 { name = "20 m"; value = 20; };
                    class V1 { name = "30 m"; value = 30; };
                    class V2 { name = "40 m"; value = 40; default = 1; };
                    class V3 { name = "60 m"; value = 60; };
                    class V4 { name = "80 m"; value = 80; };
                    class V5 { name = "120 m"; value = 120; };
                    class V6 { name = "200 m"; value = 200; };
                };
            };
        };

        class ModuleDescription {
            description = "$STR_COMSPEC_SSE_CFG_1B46B112E0";
            position = 1;
        };
    };

    class COMSPEC_SSE_Module_ScenarioDirector: COMSPEC_SSE_Module_Base {
        scope = 2;
        scopeCurator = 2;
        displayName = "$STR_COMSPEC_SSE_CFG_B528BC522D";
        category = "COMSPEC_SSE_Mission";
        function = "comspec_sse_fnc_moduleScenarioDirector";
        icon = "\A3\ui_f\data\igui\cfg\simpletasks\types\whiteboard_ca.paa";
        portrait = "\A3\ui_f\data\igui\cfg\simpletasks\types\whiteboard_ca.paa";

        class Attributes {
            class Action {
                displayName = "$STR_COMSPEC_SSE_CFG_97C89A4D66";
                tooltip = "$STR_COMSPEC_SSE_CFG_2F61CB11E6";
                property = "COMSPEC_SSE_Module_ScenarioDirector_Action";
                control = "Combo";
                expression = "_this setVariable ['Action',_value,true];";
                defaultValue = "'APPLY'";
                typeName = "STRING";
                class values {
                    class V0 { name = "$STR_COMSPEC_SSE_CFG_824DB2AE85"; value = "APPLY"; default = 1; };
                    class V1 { name = "$STR_COMSPEC_SSE_CFG_51F4E8DCDB"; value = "LEVEL_ONLY"; };
                    class V2 { name = "$STR_COMSPEC_SSE_CFG_FAFDE2D21D"; value = "LIST"; };
                };
            };
            class DatasetId {
                displayName = "$STR_COMSPEC_SSE_CFG_1052689464";
                tooltip = "$STR_COMSPEC_SSE_CFG_E96DADB71D";
                property = "COMSPEC_SSE_Module_ScenarioDirector_DatasetId";
                control = "Combo";
                expression = "_this setVariable ['DatasetId',_value,true];";
                defaultValue = "'falcon'";
                typeName = "STRING";
                class values {
                    class Falcon { name = "$STR_COMSPEC_SSE_CFG_2FD4FC4095"; value = "falcon"; default = 1; };
                };
            };
            class ScenarioLevel {
                displayName = "$STR_COMSPEC_SSE_CFG_F734A5538E";
                tooltip = "$STR_COMSPEC_SSE_CFG_B635C7AB7B";
                property = "COMSPEC_SSE_Module_ScenarioDirector_ScenarioLevel";
                control = "Combo";
                expression = "_this setVariable ['ScenarioLevel',_value,true];";
                defaultValue = "1";
                typeName = "NUMBER";
                class values {
                    class V0 { name = "$STR_COMSPEC_SSE_CFG_D2FF7BE2E9"; value = 0; };
                    class V1 { name = "$STR_COMSPEC_SSE_CFG_EBA8D31677"; value = 1; default = 1; };
                    class V2 { name = "$STR_COMSPEC_SSE_CFG_3633138E7F"; value = 2; };
                    class V3 { name = "$STR_COMSPEC_SSE_CFG_8A3121EE17"; value = 3; };
                };
            };
            class Radius {
                displayName = "$STR_COMSPEC_SSE_CFG_3D06EC48BE";
                tooltip = "$STR_COMSPEC_SSE_CFG_2786B3F013";
                property = "COMSPEC_SSE_Module_ScenarioDirector_Radius";
                control = "Combo";
                expression = "_this setVariable ['Radius',_value,true];";
                defaultValue = "50";
                typeName = "NUMBER";
                class values {
                    class V0 { name = "20 m"; value = 20; };
                    class V1 { name = "35 m"; value = 35; };
                    class V2 { name = "50 m"; value = 50; default = 1; };
                    class V3 { name = "75 m"; value = 75; };
                    class V4 { name = "100 m"; value = 100; };
                    class V5 { name = "150 m"; value = 150; };
                    class V6 { name = "300 m"; value = 300; };
                };
            };
        };

        class ModuleDescription {
            description = "$STR_COMSPEC_SSE_CFG_A771181EC8";
            position = 1;
        };
    };

    class COMSPEC_SSE_Module_SandboxSite: COMSPEC_SSE_Module_Base {
        scope = 2;
        scopeCurator = 2;
        displayName = "$STR_COMSPEC_SSE_CFG_AE206F9F36";
        category = "COMSPEC_SSE";
        function = "comspec_sse_fnc_moduleSandboxSite";
        icon = "\A3\ui_f\data\igui\cfg\simpletasks\types\box_ca.paa";
        portrait = "\A3\ui_f\data\igui\cfg\simpletasks\types\box_ca.paa";

        class Attributes {
            class Pack {
                displayName = "$STR_COMSPEC_SSE_CFG_8B9A2A34FE";
                tooltip = "$STR_COMSPEC_SSE_CFG_EB355E382D";
                property = "COMSPEC_SSE_Module_SandboxSite_Pack";
                control = "Combo";
                expression = "_this setVariable ['Pack',_value,true];";
                defaultValue = "'RANDOM'";
                typeName = "STRING";
                class values {
                    class V0 { name = "$STR_COMSPEC_SSE_CFG_6DBD11FF8A"; value = "RANDOM"; default = 1; };
                    class V1 { name = "$STR_COMSPEC_SSE_CFG_DE7B87210E"; value = "INSURGENT_CELL"; };
                    class V2 { name = "$STR_COMSPEC_SSE_CFG_8C700F2723"; value = "SMUGGLING_NETWORK"; };
                    class V3 { name = "$STR_COMSPEC_SSE_CFG_197A4A1389"; value = "WEAPONS_DEPOT"; };
                    class V4 { name = "$STR_COMSPEC_SSE_CFG_EB0DB645A0"; value = "COMMAND_POST"; };
                    class V5 { name = "$STR_COMSPEC_SSE_CFG_84775AE87C"; value = "SAFEHOUSE"; };
                    class V6 { name = "$STR_COMSPEC_SSE_CFG_6545F04364"; value = "IED_WORKSHOP"; };
                    class V7 { name = "$STR_COMSPEC_SSE_CFG_3299228C00"; value = "FINANCIAL_NODE"; };
                    class V8 { name = "$STR_COMSPEC_SSE_CFG_221A82EE48"; value = "INTELLIGENCE_CELL"; };
                };
            };
            class Radius {
                displayName = "$STR_COMSPEC_SSE_CFG_3D06EC48BE";
                tooltip = "$STR_COMSPEC_SSE_CFG_C682317A51";
                property = "COMSPEC_SSE_Module_SandboxSite_Radius";
                control = "Combo";
                expression = "_this setVariable ['Radius',_value,true];";
                defaultValue = "60";
                typeName = "NUMBER";
                class values {
                    class V0 { name = "20 m"; value = 20; };
                    class V1 { name = "40 m"; value = 40; };
                    class V2 { name = "60 m"; value = 60; default = 1; };
                    class V3 { name = "100 m"; value = 100; };
                    class V4 { name = "150 m"; value = 150; };
                };
            };
        };

        class ModuleDescription {
            description = "$STR_COMSPEC_SSE_CFG_33ED08AAAF";
            position = 1;
        };
    };

    class COMSPEC_SSE_Module_LinkEntities: COMSPEC_SSE_Module_Base {
        scope = 2;
        scopeCurator = 2;
        displayName = "$STR_COMSPEC_SSE_CFG_5FA266FD79";
        category = "COMSPEC_SSE_People";
        function = "comspec_sse_fnc_moduleLinkEntities";
        icon = "\A3\ui_f\data\igui\cfg\simpletasks\types\meet_ca.paa";
        portrait = "\A3\ui_f\data\igui\cfg\simpletasks\types\meet_ca.paa";

        class Attributes {
            class Relation {
                displayName = "$STR_COMSPEC_SSE_CFG_33A5A7571B";
                tooltip = "$STR_COMSPEC_SSE_CFG_5F2CB33B4C";
                property = "COMSPEC_SSE_Module_LinkEntities_Relation";
                control = "Combo";
                expression = "_this setVariable ['Relation',_value,true];";
                defaultValue = "'ASSOCIATE'";
                typeName = "STRING";
                class values {
                    class V0 { name = "$STR_COMSPEC_SSE_CFG_7AB382A5D2"; value = "ASSOCIATE"; default = 1; };
                    class V1 { name = "$STR_COMSPEC_SSE_CFG_B37456C453"; value = "CONTACT"; };
                    class V2 { name = "$STR_COMSPEC_SSE_CFG_CB98812BD3"; value = "OWNER"; };
                    class V3 { name = "$STR_COMSPEC_SSE_CFG_031A4C2B5B"; value = "REFERENCES"; };
                };
            };
            class Confidence {
                displayName = "$STR_COMSPEC_SSE_CFG_7B2239F605";
                tooltip = "$STR_COMSPEC_SSE_CFG_179B7D437F";
                property = "COMSPEC_SSE_Module_LinkEntities_Confidence";
                control = "Combo";
                expression = "_this setVariable ['Confidence',_value,true];";
                defaultValue = "0.7";
                typeName = "NUMBER";
                class values {
                    class V0 { name = "$STR_COMSPEC_SSE_CFG_E55AD16A0A"; value = 0.3; };
                    class V1 { name = "$STR_COMSPEC_SSE_CFG_9176F89C12"; value = 0.5; };
                    class V2 { name = "$STR_COMSPEC_SSE_CFG_BDD948572F"; value = 0.7; default = 1; };
                    class V3 { name = "$STR_COMSPEC_SSE_CFG_BC22D6CE0D"; value = 0.9; };
                };
            };
            class Mode {
                displayName = "$STR_COMSPEC_SSE_CFG_009CE48F70";
                tooltip = "$STR_COMSPEC_SSE_CFG_4404F84D2B";
                property = "COMSPEC_SSE_Module_LinkEntities_Mode";
                control = "Combo";
                expression = "_this setVariable ['Mode',_value,true];";
                defaultValue = "'CHAIN'";
                typeName = "STRING";
                class values {
                    class V0 { name = "$STR_COMSPEC_SSE_CFG_E5F97AB85F"; value = "CHAIN"; default = 1; };
                    class V1 { name = "$STR_COMSPEC_SSE_CFG_BA0C1E7230"; value = "STAR"; };
                };
            };
        };

        class ModuleDescription {
            description = "$STR_COMSPEC_SSE_CFG_A0BDD5B10B";
            sync[] = {"Anything"};
        };
    };

    class COMSPEC_SSE_Module_CaseReference: COMSPEC_SSE_Module_Base {
        scope = 2;
        scopeCurator = 2;
        displayName = "$STR_COMSPEC_SSE_CFG_EA0F6EC944";
        category = "COMSPEC_SSE_Mission";
        function = "comspec_sse_fnc_moduleCaseReference";
        icon = "\A3\ui_f\data\igui\cfg\simpletasks\types\documents_ca.paa";
        portrait = "\A3\ui_f\data\igui\cfg\simpletasks\types\documents_ca.paa";

        class Attributes {
            class Reference {
                displayName = "$STR_COMSPEC_SSE_CFG_B37A519E31";
                tooltip = "$STR_COMSPEC_SSE_CFG_33D43E50DE";
                property = "COMSPEC_SSE_Module_CaseReference_Reference";
                control = "Edit";
                expression = "_this setVariable ['Reference',_value,true];";
                defaultValue = "''";
                typeName = "STRING";
            };
        };

        class ModuleDescription {
            description = "$STR_COMSPEC_SSE_CFG_A29053E4CB";
        };
    };

    class COMSPEC_SSE_Module_ZeusControl: COMSPEC_SSE_Module_Base {
        scope = 1;
        scopeCurator = 2;
        displayName = "$STR_COMSPEC_SSE_CFG_EC5E30D840";
        category = "COMSPEC_SSE_Tools";
        function = "comspec_sse_fnc_moduleZeusControl";
        icon = "\A3\ui_f\data\igui\cfg\simpletasks\types\interact_ca.paa";
        portrait = "\A3\ui_f\data\igui\cfg\simpletasks\types\interact_ca.paa";

        class ModuleDescription {
            description = "$STR_COMSPEC_SSE_CFG_1F0DF0186D";
            sync[] = {"Anything"};
        };
    };

    class COMSPEC_SSE_Module_ViewData: COMSPEC_SSE_Module_Base {
        scope = 1;
        scopeCurator = 2;
        displayName = "$STR_COMSPEC_SSE_CFG_8ECD3BB5A9";
        category = "COMSPEC_SSE_Tools";
        function = "comspec_sse_fnc_moduleViewData";
        icon = "\A3\ui_f\data\igui\cfg\simpletasks\types\scout_ca.paa";
        portrait = "\A3\ui_f\data\igui\cfg\simpletasks\types\scout_ca.paa";

        class ModuleDescription {
            description = "$STR_COMSPEC_SSE_CFG_7543C45D49";
            sync[] = {"Anything"};
        };
    };

    class COMSPEC_SSE_Module_SpoilView: COMSPEC_SSE_Module_Base {
        scope = 1;
        scopeCurator = 2;
        displayName = "$STR_COMSPEC_SSE_CFG_F0B768C43B";
        category = "COMSPEC_SSE_Tools";
        function = "comspec_sse_fnc_moduleSpoilView";
        icon = "\A3\ui_f\data\igui\cfg\simpletasks\types\scout_ca.paa";
        portrait = "\A3\ui_f\data\igui\cfg\simpletasks\types\scout_ca.paa";

        class ModuleDescription {
            description = "$STR_COMSPEC_SSE_CFG_576B2E731F";
            sync[] = {"Anything"};
        };
    };

    class COMSPEC_SSE_Module_SiteManager: COMSPEC_SSE_Module_Base {
        scope = 1;
        scopeCurator = 2;
        displayName = "$STR_COMSPEC_SSE_CFG_A881B9C0E8";
        category = "COMSPEC_SSE_Tools";
        function = "comspec_sse_fnc_moduleSiteManager";
        icon = "\A3\ui_f\data\igui\cfg\simpletasks\types\whiteboard_ca.paa";
        portrait = "\A3\ui_f\data\igui\cfg\simpletasks\types\whiteboard_ca.paa";

        class Attributes {
            class Radius {
                displayName = "$STR_COMSPEC_SSE_CFG_3D06EC48BE";
                tooltip = "$STR_COMSPEC_SSE_CFG_D488CDA57E";
                property = "COMSPEC_SSE_Module_SiteManager_Radius";
                control = "Combo";
                expression = "_this setVariable ['Radius',_value,true];";
                defaultValue = "60";
                typeName = "NUMBER";
                class values {
                    class V0 { name = "20 m"; value = 20; };
                    class V1 { name = "40 m"; value = 40; };
                    class V2 { name = "60 m"; value = 60; default = 1; };
                    class V3 { name = "100 m"; value = 100; };
                    class V4 { name = "200 m"; value = 200; };
                    class V5 { name = "300 m"; value = 300; };
                };
            };
        };

        class ModuleDescription {
            description = "$STR_COMSPEC_SSE_CFG_785535508F";
            position = 1;
        };
    };

    class COMSPEC_SSE_Module_AfterAction: COMSPEC_SSE_Module_Base {
        scope = 1;
        scopeCurator = 2;
        displayName = "$STR_COMSPEC_SSE_CFG_FAE0B0104A";
        category = "COMSPEC_SSE_Tools";
        function = "comspec_sse_fnc_moduleAfterAction";
        icon = "\A3\ui_f\data\igui\cfg\simpletasks\types\whiteboard_ca.paa";
        portrait = "\A3\ui_f\data\igui\cfg\simpletasks\types\whiteboard_ca.paa";

        class Attributes {
            class Radius {
                displayName = "$STR_COMSPEC_SSE_CFG_3D06EC48BE";
                tooltip = "$STR_COMSPEC_SSE_CFG_4C9DC478FA";
                property = "COMSPEC_SSE_Module_AfterAction_Radius";
                control = "Combo";
                expression = "_this setVariable ['Radius',_value,true];";
                defaultValue = "150";
                typeName = "NUMBER";
                class values {
                    class V0 { name = "50 m"; value = 50; };
                    class V1 { name = "100 m"; value = 100; };
                    class V2 { name = "150 m"; value = 150; default = 1; };
                    class V3 { name = "300 m"; value = 300; };
                    class V4 { name = "600 m"; value = 600; };
                    class V5 { name = "1000 m"; value = 1000; };
                };
            };
            class ExportGraph {
                displayName = "$STR_COMSPEC_SSE_CFG_1114F6FB9B";
                tooltip = "$STR_COMSPEC_SSE_CFG_D5DD1C5D78";
                property = "COMSPEC_SSE_Module_AfterAction_ExportGraph";
                control = "Checkbox";
                expression = "_this setVariable ['ExportGraph',_value,true];";
                defaultValue = "true";
                typeName = "BOOL";
            };
        };

        class ModuleDescription {
            description = "$STR_COMSPEC_SSE_CFG_A3244D7CD1";
            position = 1;
        };
    };

    class COMSPEC_SSE_Module_ListModels: COMSPEC_SSE_Module_Base {
        scope = 1;
        scopeCurator = 2;
        displayName = "$STR_COMSPEC_SSE_CFG_3D7D0F8FD5";
        category = "COMSPEC_SSE_Tools";
        function = "comspec_sse_fnc_moduleListModels";
        icon = "\A3\ui_f\data\igui\cfg\simpletasks\types\documents_ca.paa";
        portrait = "\A3\ui_f\data\igui\cfg\simpletasks\types\documents_ca.paa";

        class ModuleDescription {
            description = "$STR_COMSPEC_SSE_CFG_A709CD819D";
        };
    };

    class COMSPEC_SSE_Module_ClearData: COMSPEC_SSE_Module_Base {
        scope = 2;
        scopeCurator = 2;
        displayName = "$STR_COMSPEC_SSE_CFG_54550F2C93";
        category = "COMSPEC_SSE_Tools";
        function = "comspec_sse_fnc_moduleClearData";
        icon = "\A3\ui_f\data\igui\cfg\simpletasks\types\danger_ca.paa";
        portrait = "\A3\ui_f\data\igui\cfg\simpletasks\types\danger_ca.paa";

        class ModuleDescription {
            description = "$STR_COMSPEC_SSE_CFG_3FC684B6E2";
            sync[] = {"Anything"};
        };
    };

    class COMSPEC_SSE_Module_ResetSite: COMSPEC_SSE_Module_Base {
        scope = 1;
        scopeCurator = 2;
        displayName = "$STR_COMSPEC_SSE_CFG_11BBF23CE8";
        category = "COMSPEC_SSE_Tools";
        function = "comspec_sse_fnc_moduleResetSite";
        icon = "\A3\ui_f\data\igui\cfg\simpletasks\types\danger_ca.paa";
        portrait = "\A3\ui_f\data\igui\cfg\simpletasks\types\danger_ca.paa";

        class Attributes {
            class Radius {
                displayName = "$STR_COMSPEC_SSE_CFG_3D06EC48BE";
                tooltip = "$STR_COMSPEC_SSE_CFG_A009479765";
                property = "COMSPEC_SSE_Module_ResetSite_Radius";
                control = "Combo";
                expression = "_this setVariable ['Radius',_value,true];";
                defaultValue = "50";
                typeName = "NUMBER";
                class values {
                    class V0 { name = "10 m"; value = 10; };
                    class V1 { name = "25 m"; value = 25; };
                    class V2 { name = "50 m"; value = 50; default = 1; };
                    class V3 { name = "100 m"; value = 100; };
                    class V4 { name = "200 m"; value = 200; };
                    class V5 { name = "300 m"; value = 300; };
                };
            };
        };

        class ModuleDescription {
            description = "$STR_COMSPEC_SSE_CFG_2787839EAF";
            position = 1;
        };
    };

    class COMSPEC_SSE_Module_DebugInspector: COMSPEC_SSE_Module_Base {
        scope = 1;
        scopeCurator = 2;
        displayName = "$STR_COMSPEC_SSE_CFG_24401FD1CC";
        category = "COMSPEC_SSE_Tools";
        function = "comspec_sse_fnc_moduleDebugInspector";
        icon = "\A3\ui_f\data\igui\cfg\simpletasks\types\target_ca.paa";
        portrait = "\A3\ui_f\data\igui\cfg\simpletasks\types\target_ca.paa";

        class Attributes {
            class DrawLinks {
                displayName = "$STR_COMSPEC_SSE_CFG_A83AA8FE95";
                tooltip = "$STR_COMSPEC_SSE_CFG_078A7F324F";
                property = "COMSPEC_SSE_Module_DebugInspector_DrawLinks";
                control = "Checkbox";
                expression = "_this setVariable ['DrawLinks',_value,true];";
                defaultValue = "true";
                typeName = "BOOL";
            };
        };

        class ModuleDescription {
            description = "$STR_COMSPEC_SSE_CFG_E697BC97A6";
        };
    };

    class COMSPEC_SSE_Module_DomexMark: COMSPEC_SSE_Module_Base {
        scope = 2;
        scopeCurator = 2;
        displayName = "$STR_COMSPEC_SSE_CFG_BE09B59B17";
        category = "COMSPEC_SSE_Domex";
        function = "comspec_sse_fnc_moduleDomexMark";
        icon = "\A3\ui_f\data\igui\cfg\simpletasks\types\download_ca.paa";
        portrait = "\A3\ui_f\data\igui\cfg\simpletasks\types\download_ca.paa";

        class Attributes {
            class NodeId {
                displayName = "$STR_COMSPEC_SSE_CFG_68E546FCE7";
                tooltip = "$STR_COMSPEC_SSE_CFG_997405DE96";
                property = "COMSPEC_SSE_Module_DomexMark_NodeId";
                control = "Edit";
                expression = "_this setVariable ['NodeId',_value,true];";
                defaultValue = "''";
                typeName = "STRING";
            };
            class DeviceType {
                displayName = "$STR_COMSPEC_SSE_CFG_3E882DE7BA";
                tooltip = "$STR_COMSPEC_SSE_CFG_69403D5D82";
                property = "COMSPEC_SSE_Module_DomexMark_DeviceType";
                control = "Combo";
                expression = "_this setVariable ['DeviceType',_value,true];";
                defaultValue = "'ordinateur'";
                typeName = "STRING";
                class values {
                    class V0 { name = "$STR_COMSPEC_SSE_CFG_CBAB5F515D"; value = "ordinateur"; default = 1; };
                    class V1 { name = "$STR_COMSPEC_SSE_CFG_D3B023EABC"; value = "telephone"; };
                    class V2 { name = "$STR_COMSPEC_SSE_CFG_7F73B0F8AB"; value = "tablette"; };
                    class V3 { name = "$STR_COMSPEC_SSE_CFG_1F2BFEDA2D"; value = "radio_numerique"; };
                    class V4 { name = "$STR_COMSPEC_SSE_CFG_B972CCAE08"; value = "cle_usb"; };
                    class V5 { name = "$STR_COMSPEC_SSE_CFG_1776FA32F2"; value = "gps"; };
                };
            };
            class Owner {
                displayName = "$STR_COMSPEC_SSE_CFG_64155AAEA0";
                tooltip = "$STR_COMSPEC_SSE_CFG_1B4C72A7DE";
                property = "COMSPEC_SSE_Module_DomexMark_Owner";
                control = "Edit";
                expression = "_this setVariable ['Owner',_value,true];";
                defaultValue = "''";
                typeName = "STRING";
            };
            class Organization {
                displayName = "$STR_COMSPEC_SSE_CFG_6E99C1D3B1";
                tooltip = "$STR_COMSPEC_SSE_CFG_AB02ADDCB1";
                property = "COMSPEC_SSE_Module_DomexMark_Organization";
                control = "Edit";
                expression = "_this setVariable ['Organization',_value,true];";
                defaultValue = "''";
                typeName = "STRING";
            };
            class Network {
                displayName = "$STR_COMSPEC_SSE_CFG_0D324E2BD2";
                tooltip = "$STR_COMSPEC_SSE_CFG_5AD6252BF7";
                property = "COMSPEC_SSE_Module_DomexMark_Network";
                control = "Edit";
                expression = "_this setVariable ['Network',_value,true];";
                defaultValue = "''";
                typeName = "STRING";
            };
            class Security {
                displayName = "$STR_COMSPEC_SSE_CFG_86BC4AF6AC";
                tooltip = "$STR_COMSPEC_SSE_CFG_5F03624271";
                property = "COMSPEC_SSE_Module_DomexMark_Security";
                control = "Combo";
                expression = "_this setVariable ['Security',_value,true];";
                defaultValue = "'moyenne'";
                typeName = "STRING";
                class values {
                    class V0 { name = "$STR_COMSPEC_SSE_CFG_3DF7A6B1E1"; value = "faible"; };
                    class V1 { name = "$STR_COMSPEC_SSE_CFG_EF33304D4F"; value = "moyenne"; default = 1; };
                    class V2 { name = "$STR_COMSPEC_SSE_CFG_4C4DE74821"; value = "elevee"; };
                };
            };
            class Profile {
                displayName = "$STR_COMSPEC_SSE_CFG_3F9713E6BC";
                tooltip = "$STR_COMSPEC_SSE_CFG_E74A5AAC3E";
                property = "COMSPEC_SSE_Module_DomexMark_Profile";
                control = "Combo";
                expression = "_this setVariable ['Profile',_value,true];";
                defaultValue = "'generique'";
                typeName = "STRING";
                class values {
                    class V0 { name = "$STR_COMSPEC_SSE_CFG_C2C5747AD0"; value = "generique"; default = 1; };
                    class V1 { name = "$STR_COMSPEC_SSE_CFG_8C266B946B"; value = "logistique"; };
                    class V2 { name = "$STR_COMSPEC_SSE_CFG_2AAFBA4D6B"; value = "commandement"; };
                    class V3 { name = "$STR_COMSPEC_SSE_CFG_87EECD7729"; value = "personnel"; };
                    class V4 { name = "$STR_COMSPEC_SSE_CFG_C211FD39A4"; value = "radio"; };
                };
            };
            class Duration {
                displayName = "$STR_COMSPEC_SSE_CFG_45C89A4C43";
                tooltip = "$STR_COMSPEC_SSE_CFG_485EA85006";
                property = "COMSPEC_SSE_Module_DomexMark_Duration";
                control = "Combo";
                expression = "_this setVariable ['Duration',_value,true];";
                defaultValue = "180";
                typeName = "NUMBER";
                class values {
                    class V0 { name = "$STR_COMSPEC_SSE_CFG_3EB8A90885"; value = 60; };
                    class V1 { name = "$STR_COMSPEC_SSE_CFG_47742D68E8"; value = 120; };
                    class V2 { name = "$STR_COMSPEC_SSE_CFG_D484D3A0D3"; value = 180; default = 1; };
                    class V3 { name = "$STR_COMSPEC_SSE_CFG_B45EBD80DE"; value = 300; };
                    class V4 { name = "$STR_COMSPEC_SSE_CFG_4605EE7759"; value = 600; };
                    class V5 { name = "$STR_COMSPEC_SSE_CFG_1028D69A2F"; value = 900; };
                };
            };
            class AccessRemote {
                displayName = "$STR_COMSPEC_SSE_CFG_F3D9E18AC0";
                tooltip = "$STR_COMSPEC_SSE_CFG_0D162D8671";
                property = "COMSPEC_SSE_Module_DomexMark_AccessRemote";
                control = "Checkbox";
                expression = "_this setVariable ['AccessRemote',_value,true];";
                defaultValue = "false";
                typeName = "BOOL";
            };
            class PacketType {
                displayName = "$STR_COMSPEC_SSE_CFG_12DBF14B8D";
                tooltip = "$STR_COMSPEC_SSE_CFG_6D917739B8";
                property = "COMSPEC_SSE_Module_DomexMark_PacketType";
                control = "Combo";
                expression = "_this setVariable ['PacketType',_value,true];";
                defaultValue = "''";
                typeName = "STRING";
                class values {
                    class V0 { name = "$STR_COMSPEC_SSE_CFG_106A1FD778"; value = ""; default = 1; };
                    class V1 { name = "$STR_COMSPEC_SSE_CFG_68F4145FEE"; value = "message"; };
                    class V2 { name = "$STR_COMSPEC_SSE_CFG_E214B8A299"; value = "document"; };
                    class V3 { name = "$STR_COMSPEC_SSE_CFG_6C68FEA85E"; value = "coordinate"; };
                    class V4 { name = "$STR_COMSPEC_SSE_CFG_B37456C453"; value = "contact"; };
                    class V5 { name = "$STR_COMSPEC_SSE_CFG_BAFBFBA73A"; value = "frequency"; };
                };
            };
            class PacketText {
                displayName = "$STR_COMSPEC_SSE_CFG_1F34553EEA";
                tooltip = "$STR_COMSPEC_SSE_CFG_58AC927D81";
                property = "COMSPEC_SSE_Module_DomexMark_PacketText";
                control = "Edit";
                expression = "_this setVariable ['PacketText',_value,true];";
                defaultValue = "''";
                typeName = "STRING";
            };
            class PacketQuality {
                displayName = "$STR_COMSPEC_SSE_CFG_FCED55D4BB";
                tooltip = "$STR_COMSPEC_SSE_CFG_91AFA678C0";
                property = "COMSPEC_SSE_Module_DomexMark_PacketQuality";
                control = "Combo";
                expression = "_this setVariable ['PacketQuality',_value,true];";
                defaultValue = "'complet'";
                typeName = "STRING";
                class values {
                    class V0 { name = "$STR_COMSPEC_SSE_CFG_26275EBD66"; value = "complet"; default = 1; };
                    class V1 { name = "$STR_COMSPEC_SSE_CFG_5CFBE37779"; value = "fragment"; };
                    class V2 { name = "$STR_COMSPEC_SSE_CFG_A2556E2679"; value = "leurre_possible"; };
                };
            };
            class PacketEntities {
                displayName = "$STR_COMSPEC_SSE_CFG_D664532674";
                tooltip = "$STR_COMSPEC_SSE_CFG_FAFD121A5A";
                property = "COMSPEC_SSE_Module_DomexMark_PacketEntities";
                control = "Edit";
                expression = "_this setVariable ['PacketEntities',_value,true];";
                defaultValue = "''";
                typeName = "STRING";
            };
        };

        class ModuleDescription {
            description = "$STR_COMSPEC_SSE_CFG_34B5829EE4";
            sync[] = {"Anything"};
        };
    };

    class COMSPEC_SSE_Module_DomexAddIntel: COMSPEC_SSE_Module_Base {
        scope = 1;
        scopeCurator = 2;
        displayName = "$STR_COMSPEC_SSE_CFG_32A649EB94";
        category = "COMSPEC_SSE_Domex";
        function = "comspec_sse_fnc_moduleDomexAddIntel";
        icon = "\A3\ui_f\data\igui\cfg\simpletasks\types\download_ca.paa";
        portrait = "\A3\ui_f\data\igui\cfg\simpletasks\types\download_ca.paa";

        class ModuleDescription {
            description = "$STR_COMSPEC_SSE_CFG_B27FD8908E";
            sync[] = {"Anything"};
        };
    };

    class COMSPEC_SSE_Module_DomexSetStage: COMSPEC_SSE_Module_Base {
        scope = 2;
        scopeCurator = 2;
        displayName = "$STR_COMSPEC_SSE_CFG_123BDC155F";
        category = "COMSPEC_SSE_Domex";
        function = "comspec_sse_fnc_moduleDomexSetStage";
        icon = "\A3\ui_f\data\igui\cfg\simpletasks\types\use_ca.paa";
        portrait = "\A3\ui_f\data\igui\cfg\simpletasks\types\use_ca.paa";

        class Attributes {
            class Stage {
                displayName = "$STR_COMSPEC_SSE_CFG_7564CB6E0C";
                tooltip = "$STR_COMSPEC_SSE_CFG_0895A119AF";
                property = "COMSPEC_SSE_Module_DomexSetStage_Stage";
                control = "Combo";
                expression = "_this setVariable ['Stage',_value,true];";
                defaultValue = "'decouvert'";
                typeName = "STRING";
                class values {
                    class V0 { name = "$STR_COMSPEC_SSE_CFG_34DF732954"; value = "non_identifie"; };
                    class V1 { name = "$STR_COMSPEC_SSE_CFG_B5D15CFC78"; value = "decouvert"; default = 1; };
                    class V2 { name = "$STR_COMSPEC_SSE_CFG_C34AAD85BE"; value = "acces_en_cours"; };
                    class V3 { name = "$STR_COMSPEC_SSE_CFG_3A745C983B"; value = "acces_etabli"; };
                    class V4 { name = "$STR_COMSPEC_SSE_CFG_674BBBD83C"; value = "exploite"; };
                };
            };
        };

        class ModuleDescription {
            description = "$STR_COMSPEC_SSE_CFG_19F1C27D23";
            sync[] = {"Anything"};
        };
    };

    class COMSPEC_SSE_Module_DomexMapPoint: COMSPEC_SSE_Module_Base {
        scope = 2;
        scopeCurator = 2;
        displayName = "$STR_COMSPEC_SSE_CFG_A847407C06";
        category = "COMSPEC_SSE_Domex";
        function = "comspec_sse_fnc_moduleDomexMapPoint";
        icon = "\A3\ui_f\data\igui\cfg\simpletasks\types\map_ca.paa";
        portrait = "\A3\ui_f\data\igui\cfg\simpletasks\types\map_ca.paa";

        class Attributes {
            class Label {
                displayName = "$STR_COMSPEC_SSE_CFG_F5485BBAE8";
                tooltip = "$STR_COMSPEC_SSE_CFG_CE784579DC";
                property = "COMSPEC_SSE_Module_DomexMapPoint_Label";
                control = "Edit";
                expression = "_this setVariable ['Label',_value,true];";
                defaultValue = "''";
                typeName = "STRING";
            };
        };

        class ModuleDescription {
            description = "$STR_COMSPEC_SSE_CFG_74AB3E1C97";
            position = 1;
        };
    };


    // Modules Eden repris du mod Overwatch (Dossier, Identité, SEEK, Documents).
    #include "modules_seek.hpp"
};

// Overwatch impose une liste blanche stricte (mode = 1) : sans ces entrées, les appels
// distants du pack SSE seraient rejetés en silence quand les deux mods sont chargés.
class CfgRemoteExec {
    class Functions {
        class comspec_sse_fnc_setCaseReference { allowedTargets = 0; jip = 1; };
        class comspec_sse_fnc_applyProfile { allowedTargets = 2; };
        class comspec_sse_fnc_linkEntities { allowedTargets = 2; };
        class comspec_sse_fnc_giveSeek { allowedTargets = 0; };
    };
};

// Classes de contrôles partagées (addon comspec_sse_ui, dialogs\base.hpp).
class COMSPEC_SSE_RscText;
class COMSPEC_SSE_RscLabel;
class COMSPEC_SSE_RscSection;
class COMSPEC_SSE_RscBackground;
class COMSPEC_SSE_RscDim;
class COMSPEC_SSE_RscPanel;
class COMSPEC_SSE_RscHeaderZeus;
class COMSPEC_SSE_RscAccentLineZeus;
class COMSPEC_SSE_RscListBox;
class COMSPEC_SSE_RscButtonZeus;
class COMSPEC_SSE_RscButtonClose;
class COMSPEC_SSE_RscCombo;
class COMSPEC_SSE_RscCheckBox;
class COMSPEC_SSE_RscSlider;

#include "CfgEventHandlers.hpp"
#include "dialogs\generateDialog.hpp"
#include "dialogs\modelDialog.hpp"
