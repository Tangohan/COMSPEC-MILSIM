#include "script_component.hpp"
#include "ui_macros.hpp"

/*
    Analyse : organisations et groupes, tableau des liens (graphe, ORBAT),
    chaîne de possession des pièces (scellés numérotés, journal chaîné).

      - Eden : catégorie « COMSPEC SSE — Organisation » sur les personnes,
        « Rattaché à une organisation » sur les objets, module « Organisation » ;
      - Zeus (ZEN) : affecter, générer un réseau, rattacher, révéler, tableau vue Zeus,
        altérer un scellé (exercice) ;
      - ACE : tableau des liens, registre des scellés, remise des scellés à un équipier.
*/
class CfgPatches {
    class comspec_sse_analysis {
        name = "COMSPEC SSE - Analyse (organisations, liens, scellés)";
        units[] = {"COMSPEC_SSE_Module_Organisation"};
        weapons[] = {};
        requiredVersion = REQUIRED_VERSION;
        requiredAddons[] = {
            "comspec_sse_main",
            "comspec_sse_core",
            "comspec_sse_intel",
            "comspec_sse_ui",
            "comspec_sse_zeus",
            "comspec_sse_eden",
            "comspec_sse_biometrics",
            "A3_Ui_F",
            "A3_Modules_F",
            "cba_xeh"
        };
        author = "COMSPEC";
        VERSION_CONFIG;
    };
};

class CfgFunctions {
    class comspec_sse {
        tag = "comspec_sse";
        class analysis {
            file = "z\comspec_sse\addons\analysis\functions";
            class anaInitSettings {};
            class orgTables {};
            class anaLabel {};
            class orgOp {};
            class orgGenerate {};
            class orgApplyEden {};
            class moduleOrganisation {};
            class orgZeusForm {};
            class custody {};
            class custodyVerify {};
            class custodyText {};
            class chartBuild {};
            class chartLayout {};
            class chartOpen {};
            class chartOnLoad {};
            class chartRefresh {};
            class chartSet {};
            class chartDraw {};
            class chartPick {};
            class chartDetail {};
        };
    };
};

class Extended_PreInit_EventHandlers {
    class comspec_sse_analysis {
        init = "call compile preprocessFileLineNumbers 'z\comspec_sse\addons\analysis\XEH_preInit.sqf'";
    };
};

class Extended_PostInit_EventHandlers {
    class comspec_sse_analysis {
        init = "call compile preprocessFileLineNumbers 'z\comspec_sse\addons\analysis\XEH_postInit.sqf'";
    };
};

// ---------------------------------------------------------------- module Eden
class CfgVehicles {
    class Logic;
    class Module_F: Logic {
        class AttributesBase {
            class Edit;
            class Combo;
        };
        class ModuleDescription {};
    };
    class COMSPEC_SSE_Module_Organisation: Module_F {
        author = "COMSPEC";
        scope = 2;
        scopeCurator = 0;
        category = "COMSPEC_SSE_People";
        displayName = "$STR_COMSPEC_SSE_CFG_B0F0414D47";
        function = "comspec_sse_fnc_moduleOrganisation";
        functionPriority = 3;
        isGlobal = 0;
        isTriggerActivated = 0;
        isDisposable = 1;
        is3DEN = 0;
        icon = "\A3\ui_f\data\map\markers\nato\o_hq.paa";
        portrait = "\A3\ui_f\data\map\markers\nato\o_hq.paa";

        class Attributes: AttributesBase {
            class Name: Edit {
                property = "COMSPEC_SSE_OrgModule_Name";
                displayName = "$STR_COMSPEC_SSE_CFG_7FF20370C8";
                tooltip = "$STR_COMSPEC_SSE_CFG_D2964B732C";
                typeName = "STRING";
                defaultValue = "''";
            };
            class Kind: Combo {
                property = "COMSPEC_SSE_OrgModule_Kind";
                displayName = "$STR_COMSPEC_SSE_CFG_BDDC7ADB12";
                tooltip = "$STR_COMSPEC_SSE_CFG_DB6463085C";
                typeName = "STRING";
                defaultValue = "'reseau'";
                class Values {
                    class Reseau { name = "$STR_COMSPEC_SSE_CFG_5CBDDF1BDA"; value = "reseau"; };
                    class Cellule { name = "$STR_COMSPEC_SSE_CFG_B8C77BEB16"; value = "cellule"; };
                    class Groupe { name = "$STR_COMSPEC_SSE_CFG_D0C2714243"; value = "groupe"; };
                    class Milice { name = "$STR_COMSPEC_SSE_CFG_EB08DFF156"; value = "milice"; };
                    class Criminel { name = "$STR_COMSPEC_SSE_CFG_757A01995B"; value = "criminel"; };
                    class Clan { name = "$STR_COMSPEC_SSE_CFG_81BB813464"; value = "clan"; };
                    class Entreprise { name = "$STR_COMSPEC_SSE_CFG_898AB8D570"; value = "entreprise"; };
                    class Service { name = "$STR_COMSPEC_SSE_CFG_4BFB2603C5"; value = "service"; };
                    class Soutien { name = "$STR_COMSPEC_SSE_CFG_C713B7703C"; value = "soutien"; };
                };
            };
            class Parent: Edit {
                property = "COMSPEC_SSE_OrgModule_Parent";
                displayName = "$STR_COMSPEC_SSE_CFG_F203020200";
                tooltip = "$STR_COMSPEC_SSE_CFG_E0024277D7";
                typeName = "STRING";
                defaultValue = "''";
            };
            class Threat: Combo {
                property = "COMSPEC_SSE_OrgModule_Threat";
                displayName = "$STR_COMSPEC_SSE_CFG_E39BBE2519";
                tooltip = "$STR_COMSPEC_SSE_CFG_A0343D674B";
                typeName = "STRING";
                defaultValue = "'moyenne'";
                class Values {
                    class Faible { name = "$STR_COMSPEC_SSE_CFG_3DF7A6B1E1"; value = "faible"; };
                    class Moyenne { name = "$STR_COMSPEC_SSE_CFG_EF33304D4F"; value = "moyenne"; };
                    class Elevee { name = "$STR_COMSPEC_SSE_CFG_4C4DE74821"; value = "elevee"; };
                    class Critique { name = "$STR_COMSPEC_SSE_CFG_6256DE98F1"; value = "critique"; };
                };
            };
            class Description: Edit {
                control = "EditMulti3";
                property = "COMSPEC_SSE_OrgModule_Description";
                displayName = "$STR_COMSPEC_SSE_CFG_55F8EBC805";
                tooltip = "$STR_COMSPEC_SSE_CFG_AED4789BAC";
                typeName = "STRING";
                defaultValue = "''";
            };
            class Distribution: Combo {
                property = "COMSPEC_SSE_OrgModule_Distribution";
                displayName = "$STR_COMSPEC_SSE_CFG_E40C27961A";
                tooltip = "$STR_COMSPEC_SSE_CFG_6344F70286";
                typeName = "STRING";
                defaultValue = "'manual'";
                class Values {
                    class Manual { name = "$STR_COMSPEC_SSE_CFG_3D4AD104BA"; value = "manual"; };
                    class Auto { name = "$STR_COMSPEC_SSE_CFG_1A5FE6557C"; value = "auto"; };
                };
            };
            class Seed: Edit {
                property = "COMSPEC_SSE_OrgModule_Seed";
                displayName = "$STR_COMSPEC_SSE_CFG_24FFB3949F";
                tooltip = "$STR_COMSPEC_SSE_CFG_8CEE6A1955";
                typeName = "NUMBER";
                defaultValue = "0";
            };
            class Known: Combo {
                property = "COMSPEC_SSE_OrgModule_Known";
                displayName = "$STR_COMSPEC_SSE_CFG_01CE550E6F";
                tooltip = "$STR_COMSPEC_SSE_CFG_698FD3A976";
                typeName = "NUMBER";
                defaultValue = "0";
                class Values {
                    class Rien { name = "$STR_COMSPEC_SSE_CFG_107309E51C"; value = 0; };
                    class Chef { name = "$STR_COMSPEC_SSE_CFG_338A300200"; value = 1; };
                    class Org { name = "$STR_COMSPEC_SSE_CFG_2E8D40A6EE"; value = 2; };
                };
            };
            class ModuleDescription: ModuleDescription {};
        };

        class ModuleDescription: ModuleDescription {
            description = "$STR_COMSPEC_SSE_CFG_8CBD195789";
            sync[] = {"AnyBrain", "AnyStaticObject"};
        };
    };
};

// ---------------------------------------------------------------- attributs Eden
class Cfg3DEN {
    class Object {
        class AttributeCategories {
            class COMSPEC_SSE_Org {
                displayName = "$STR_COMSPEC_SSE_CFG_071DE35685";
                collapsed = 1;
                class Attributes {
                    class COMSPEC_SSE_Org_Name {
                        displayName = "$STR_COMSPEC_SSE_CFG_6E99C1D3B1";
                        tooltip = "$STR_COMSPEC_SSE_CFG_7AC6F20F4B";
                        property = "COMSPEC_SSE_Org_Name";
                        control = "Edit";
                        expression = "_this setVariable ['COMSPEC_SSE_Org_Name', _value, true];";
                        defaultValue = "''";
                        typeName = "STRING";
                        condition = "objectBrain";
                    };
                    class COMSPEC_SSE_Org_Cell {
                        displayName = "$STR_COMSPEC_SSE_CFG_B8C77BEB16";
                        tooltip = "$STR_COMSPEC_SSE_CFG_42B778C200";
                        property = "COMSPEC_SSE_Org_Cell";
                        control = "Edit";
                        expression = "_this setVariable ['COMSPEC_SSE_Org_Cell', _value, true];";
                        defaultValue = "''";
                        typeName = "STRING";
                        condition = "objectBrain";
                    };
                    class COMSPEC_SSE_Org_Role {
                        displayName = "$STR_COMSPEC_SSE_CFG_8B82479022";
                        tooltip = "$STR_COMSPEC_SSE_CFG_E7EC1A8FA0";
                        property = "COMSPEC_SSE_Org_Role";
                        control = "Combo";
                        expression = "_this setVariable ['COMSPEC_SSE_Org_Role', _value, true];";
                        defaultValue = "'membre'";
                        typeName = "STRING";
                        condition = "objectBrain";
                        class Values {
                            class Chef { name = "$STR_COMSPEC_SSE_CFG_46DE8428AC"; value = "chef"; };
                            class Adjoint { name = "$STR_COMSPEC_SSE_CFG_F5DF03113D"; value = "adjoint"; };
                            class ChefCellule { name = "$STR_COMSPEC_SSE_CFG_D4601A84BA"; value = "chefcellule"; };
                            class Financier { name = "$STR_COMSPEC_SSE_CFG_8FAF1224A7"; value = "financier"; };
                            class Logisticien { name = "$STR_COMSPEC_SSE_CFG_12B553B4AB"; value = "logisticien"; };
                            class Artificier { name = "$STR_COMSPEC_SSE_CFG_3D067E1103"; value = "artificier"; };
                            class Recruteur { name = "$STR_COMSPEC_SSE_CFG_B732FA329A"; value = "recruteur"; };
                            class Propagande { name = "$STR_COMSPEC_SSE_CFG_14DBC51120"; value = "propagande"; };
                            class Transmissions { name = "$STR_COMSPEC_SSE_CFG_1BC4C81762"; value = "transmissions"; };
                            class Courrier { name = "$STR_COMSPEC_SSE_CFG_6347EE1AA8"; value = "courrier"; };
                            class Guetteur { name = "$STR_COMSPEC_SSE_CFG_B000B0D387"; value = "guetteur"; };
                            class Combattant { name = "$STR_COMSPEC_SSE_CFG_6B7D371AD3"; value = "combattant"; };
                            class Membre { name = "$STR_COMSPEC_SSE_CFG_BBC7E79964"; value = "membre"; };
                            class Facilitateur { name = "$STR_COMSPEC_SSE_CFG_78A1D8F3DF"; value = "facilitateur"; };
                            class Sympathisant { name = "$STR_COMSPEC_SSE_CFG_A29B47B929"; value = "sympathisant"; };
                        };
                    };
                    class COMSPEC_SSE_Org_Alias {
                        displayName = "$STR_COMSPEC_SSE_CFG_D2BF4CB656";
                        tooltip = "$STR_COMSPEC_SSE_CFG_B481F13022";
                        property = "COMSPEC_SSE_Org_Alias";
                        control = "Edit";
                        expression = "_this setVariable ['COMSPEC_SSE_Org_Alias', _value, true];";
                        defaultValue = "''";
                        typeName = "STRING";
                        condition = "objectBrain";
                    };
                    class COMSPEC_SSE_Org_Known {
                        displayName = "$STR_COMSPEC_SSE_CFG_01CE550E6F";
                        tooltip = "$STR_COMSPEC_SSE_CFG_2820E4C1AC";
                        property = "COMSPEC_SSE_Org_Known";
                        control = "Combo";
                        expression = "_this setVariable ['COMSPEC_SSE_Org_Known', _value, true];";
                        defaultValue = "0";
                        typeName = "NUMBER";
                        condition = "objectBrain";
                        class Values {
                            class Rien { name = "$STR_COMSPEC_SSE_CFG_E11BD7E4AB"; value = 0; };
                            class Soupcon { name = "$STR_COMSPEC_SSE_CFG_B5F75A0B25"; value = 1; };
                            class Confirme { name = "$STR_COMSPEC_SSE_CFG_3D1993661F"; value = 2; };
                        };
                    };
                    class COMSPEC_SSE_OrgLinked {
                        displayName = "$STR_COMSPEC_SSE_CFG_7E2991EECF";
                        tooltip = "$STR_COMSPEC_SSE_CFG_5AB4C2B5CC";
                        property = "COMSPEC_SSE_OrgLinked";
                        control = "Edit";
                        expression = "_this setVariable ['COMSPEC_SSE_OrgLinked', _value, true];";
                        defaultValue = "''";
                        typeName = "STRING";
                        condition = "objectSimulated - objectBrain";
                    };
                };
            };
        };
    };
};

// ---------------------------------------------------------------- dialogue
class RscMapControl;
class RscControlsGroup;
class COMSPEC_SSE_RscDim;
class COMSPEC_SSE_RscBackground;
class COMSPEC_SSE_RscHeader;
class COMSPEC_SSE_RscLabel;
class COMSPEC_SSE_RscAccentLine;
class COMSPEC_SSE_RscSubHeader;
class COMSPEC_SSE_RscPanel;
class COMSPEC_SSE_RscSection;
class COMSPEC_SSE_RscStructuredText;
class COMSPEC_SSE_RscButtonNav;
class COMSPEC_SSE_RscButtonZeus;
class COMSPEC_SSE_RscButtonClose;
#include "dialogs\chart.hpp"
