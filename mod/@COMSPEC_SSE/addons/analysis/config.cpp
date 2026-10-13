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
                typeName = "STRING";
                defaultValue = "'reseau'";
                class Values {
                    class Reseau { name = "Réseau"; value = "reseau"; };
                    class Cellule { name = "Cellule"; value = "cellule"; };
                    class Groupe { name = "Groupe armé"; value = "groupe"; };
                    class Milice { name = "Milice"; value = "milice"; };
                    class Criminel { name = "Réseau criminel"; value = "criminel"; };
                    class Clan { name = "Clan, famille"; value = "clan"; };
                    class Entreprise { name = "Entreprise écran"; value = "entreprise"; };
                    class Service { name = "Service de renseignement"; value = "service"; };
                    class Soutien { name = "Réseau de soutien"; value = "soutien"; };
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
                typeName = "STRING";
                defaultValue = "'moyenne'";
                class Values {
                    class Faible { name = "Faible"; value = "faible"; };
                    class Moyenne { name = "Moyenne"; value = "moyenne"; };
                    class Elevee { name = "Élevée"; value = "elevee"; };
                    class Critique { name = "Critique"; value = "critique"; };
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
                    class Manual { name = "Saisis sur chaque personne"; value = "manual"; };
                    class Auto { name = "Générés (cellules et rôles)"; value = "auto"; };
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
                typeName = "NUMBER";
                defaultValue = "0";
                class Values {
                    class Rien { name = "Rien"; value = 0; };
                    class Chef { name = "Le chef ou les membres sont soupçonnés"; value = 1; };
                    class Org { name = "L'organisation est connue des services"; value = 2; };
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
                        property = "COMSPEC_SSE_Org_Role";
                        control = "Combo";
                        expression = "_this setVariable ['COMSPEC_SSE_Org_Role', _value, true];";
                        defaultValue = "'membre'";
                        typeName = "STRING";
                        condition = "objectBrain";
                        class Values {
                            class Chef { name = "Chef"; value = "chef"; };
                            class Adjoint { name = "Adjoint"; value = "adjoint"; };
                            class ChefCellule { name = "Chef de cellule"; value = "chefcellule"; };
                            class Financier { name = "Financier"; value = "financier"; };
                            class Logisticien { name = "Logisticien"; value = "logisticien"; };
                            class Artificier { name = "Artificier"; value = "artificier"; };
                            class Recruteur { name = "Recruteur"; value = "recruteur"; };
                            class Propagande { name = "Propagandiste"; value = "propagande"; };
                            class Transmissions { name = "Transmissions"; value = "transmissions"; };
                            class Courrier { name = "Courrier"; value = "courrier"; };
                            class Guetteur { name = "Guetteur"; value = "guetteur"; };
                            class Combattant { name = "Combattant"; value = "combattant"; };
                            class Membre { name = "Membre"; value = "membre"; };
                            class Facilitateur { name = "Facilitateur"; value = "facilitateur"; };
                            class Sympathisant { name = "Sympathisant"; value = "sympathisant"; };
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
                        property = "COMSPEC_SSE_Org_Known";
                        control = "Combo";
                        expression = "_this setVariable ['COMSPEC_SSE_Org_Known', _value, true];";
                        defaultValue = "0";
                        typeName = "NUMBER";
                        condition = "objectBrain";
                        class Values {
                            class Rien { name = "Rien (à découvrir)"; value = 0; };
                            class Soupcon { name = "Appartenance soupçonnée"; value = 1; };
                            class Confirme { name = "Appartenance confirmée"; value = 2; };
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
