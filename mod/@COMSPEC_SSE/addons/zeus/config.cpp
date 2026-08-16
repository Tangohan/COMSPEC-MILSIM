#include "script_component.hpp"

class CfgPatches {
    class comspec_sse_zeus {
        name = "COMSPEC SSE - Zeus";
        units[] = {
            "COMSPEC_SSE_Module_GenerateData",
            "COMSPEC_SSE_Module_GenerateSite",
            "COMSPEC_SSE_Module_InitTarget",
            "COMSPEC_SSE_Module_ViewData",
            "COMSPEC_SSE_Module_ClearData",
            "COMSPEC_SSE_Module_DebugInspector",
            "COMSPEC_SSE_Module_ApplyModel",
            "COMSPEC_SSE_Module_SaveModel",
            "COMSPEC_SSE_Module_ListModels",
            "COMSPEC_SSE_Module_SiteManager",
            "COMSPEC_SSE_Module_GenerateBrief",
            "COMSPEC_SSE_Module_SpoilView",
            "COMSPEC_SSE_Module_AfterAction",
            "COMSPEC_SSE_Module_SandboxSite",
            "COMSPEC_SSE_Module_ZeusControl"
        };
        weapons[] = {};
        requiredVersion = REQUIRED_VERSION;
        requiredAddons[] = {"comspec_sse_core", "comspec_sse_generator", "comspec_sse_intel", "comspec_sse_ui", "comspec_sse_main", "A3_Modules_F"};
        author = "COMSPEC";
        VERSION_CONFIG;
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
            class moduleViewData {};
            class moduleClearData {};
            class moduleDebugInspector {};
            class moduleApplyModel {};
            class moduleSaveModel {};
            class moduleListModels {};
            class moduleSiteManager {};
            class moduleGenerateBrief {};
            class moduleSpoilView {};
            class moduleAfterAction {};
            class moduleSandboxSite {};
            class moduleZeusControl {};
            class openGenerateDialog {};
            class applyGenerateDialog {};
            class openModelDialog {};
            class applyModelDialog {};
        };
    };
};

class CfgVehicles {
    class Logic;
    class Module_F: Logic {
        class AttributesBase {
            class Default;
            class Edit;
            class Combo;
            class Checkbox;
            class ModuleDescription;
        };
        class ModuleDescription;
    };

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
        displayName = "Générer profil SSE";
        function = "comspec_sse_fnc_moduleGenerateData";

        class Arguments {
            class Profile {
                displayName = "Profil";
                description = "Profil narratif SSE";
                typeName = "STRING";
                class values {
                    class INSURGENT { name = "Insurgent"; value = "INSURGENT"; default = 1; };
                    class CIVILIAN { name = "Civilian"; value = "CIVILIAN"; };
                    class MILITARY { name = "Military"; value = "MILITARY"; };
                    class COMMANDER { name = "Commander / HVT"; value = "COMMANDER"; };
                    class COURIER { name = "Courier"; value = "COURIER"; };
                    class FINANCIER { name = "Financier"; value = "FINANCIER"; };
                    class TECHNICIAN { name = "Technician"; value = "TECHNICIAN"; };
                    class INTELLIGENCE { name = "Intelligence"; value = "INTELLIGENCE"; };
                    class LOGISTICS { name = "Logistics"; value = "LOGISTICS"; };
                    class RANDOM { name = "Random"; value = "RANDOM"; };
                };
            };
            class Complexity {
                displayName = "Richesse";
                description = "Niveau de détail";
                typeName = "STRING";
                class values {
                    class LIGHT { name = "Light"; value = "LIGHT"; };
                    class STANDARD { name = "Standard"; value = "STANDARD"; default = 1; };
                    class DETAILED { name = "Detailed"; value = "DETAILED"; };
                    class HIGH_VALUE { name = "High Value"; value = "HIGH_VALUE"; };
                };
            };
            class WantIdentity {
                displayName = "Identité";
                description = "Inclure identité";
                typeName = "BOOL";
                defaultValue = "true";
            };
            class WantPhone {
                displayName = "Téléphone";
                description = "Inclure données téléphone";
                typeName = "BOOL";
                defaultValue = "true";
            };
            class WantDocuments {
                displayName = "Documents";
                typeName = "BOOL";
                defaultValue = "true";
            };
            class WantBio {
                displayName = "Biométrie";
                typeName = "BOOL";
                defaultValue = "true";
            };
            class WantNetwork {
                displayName = "Liens réseau";
                typeName = "BOOL";
                defaultValue = "true";
            };
            class NoisePct {
                displayName = "Probabilité données inutiles (%)";
                typeName = "NUMBER";
                defaultValue = "25";
            };
        };

        class ModuleDescription {
            description = "Génère un profil SSE déterministe sur l'entité synchronisée. Posez le module sur une unité ou un objet.";
            sync[] = {"AnyPerson", "AnyVehicle", "Anything"};
        };
    };

    class COMSPEC_SSE_Module_GenerateSite: COMSPEC_SSE_Module_Base {
        scope = 2;
        scopeCurator = 2;
        displayName = "Créer site SSE complet";
        function = "comspec_sse_fnc_moduleGenerateSite";
        canSetArea = 1;
        canSetAreaHeight = 0;
        canSetAreaShape = 1;

        class Arguments {
            class Radius {
                displayName = "Rayon (m)";
                typeName = "NUMBER";
                defaultValue = "35";
            };
            class Profile {
                displayName = "Profil cellule";
                typeName = "STRING";
                class values {
                    class INSURGENT { name = "Insurgent Cell"; value = "INSURGENT"; default = 1; };
                    class MILITARY { name = "Military"; value = "MILITARY"; };
                    class CIVILIAN { name = "Civilian"; value = "CIVILIAN"; };
                };
            };
            class Complexity {
                displayName = "Intensité";
                typeName = "STRING";
                class values {
                    class STANDARD { name = "Standard"; value = "STANDARD"; };
                    class DETAILED { name = "Detailed"; value = "DETAILED"; default = 1; };
                    class HIGH_VALUE { name = "High Value"; value = "HIGH_VALUE"; };
                };
            };
            class MaxObjects {
                displayName = "Objets exploitables (max)";
                typeName = "NUMBER";
                defaultValue = "8";
            };
        };

        class ModuleDescription {
            description = "Génère un site SSE cohérent (personnes, objets, véhicule, téléphone, documents) dans le rayon.";
            position = 1;
        };
    };

    class COMSPEC_SSE_Module_InitTarget: COMSPEC_SSE_Module_Base {
        scope = 2;
        scopeCurator = 2;
        displayName = "Initialiser cible SSE";
        function = "comspec_sse_fnc_moduleInitTarget";
        class ModuleDescription {
            description = "Marque la cible comme exploitable SSE (lazy generation).";
            sync[] = {"Anything"};
        };
    };

    class COMSPEC_SSE_Module_ViewData: COMSPEC_SSE_Module_Base {
        scope = 2;
        scopeCurator = 2;
        displayName = "Voir données SSE";
        function = "comspec_sse_fnc_moduleViewData";
        class ModuleDescription {
            description = "Affiche les données SSE de la cible (Zeus).";
            sync[] = {"Anything"};
        };
    };

    class COMSPEC_SSE_Module_ClearData: COMSPEC_SSE_Module_Base {
        scope = 2;
        scopeCurator = 2;
        displayName = "Effacer données SSE";
        function = "comspec_sse_fnc_moduleClearData";
        class ModuleDescription {
            description = "Supprime les données SSE de la cible.";
            sync[] = {"Anything"};
        };
    };

    class COMSPEC_SSE_Module_DebugInspector: COMSPEC_SSE_Module_Base {
        scope = 2;
        scopeCurator = 2;
        displayName = "SSE Debug Inspector";
        function = "comspec_sse_fnc_moduleDebugInspector";
        class Arguments {
            class DrawLinks {
                displayName = "Afficher liens 3D";
                typeName = "BOOL";
                defaultValue = "true";
            };
        };
        class ModuleDescription {
            description = "Active l'inspecteur debug SSE (UID, état, relations, drawLine3D).";
        };
    };

    class COMSPEC_SSE_Module_ApplyModel: COMSPEC_SSE_Module_Base {
        scope = 2;
        scopeCurator = 2;
        displayName = "Appliquer modèle SSE";
        function = "comspec_sse_fnc_moduleApplyModel";
        class Arguments {
            class ModelId {
                displayName = "ID modèle (vide = dialogue)";
                description = "Ex. builtin_cellule_insurgee_irak — laisser vide pour choisir";
                typeName = "STRING";
                defaultValue = "";
            };
        };
        class ModuleDescription {
            description = "Applique un modèle SSE (intégré ou utilisateur) sur la cible.";
            sync[] = {"Anything"};
        };
    };

    class COMSPEC_SSE_Module_SaveModel: COMSPEC_SSE_Module_Base {
        scope = 2;
        scopeCurator = 2;
        displayName = "Enregistrer comme modèle SSE";
        function = "comspec_sse_fnc_moduleSaveModel";
        class Arguments {
            class ModelName {
                displayName = "Nom du modèle";
                typeName = "STRING";
                defaultValue = "Mon modèle SSE";
            };
        };
        class ModuleDescription {
            description = "Capture la cible SSE actuelle et l'enregistre comme modèle réutilisable.";
            sync[] = {"Anything"};
        };
    };

    class COMSPEC_SSE_Module_ListModels: COMSPEC_SSE_Module_Base {
        scope = 2;
        scopeCurator = 2;
        displayName = "Lister modèles SSE";
        function = "comspec_sse_fnc_moduleListModels";
        class ModuleDescription {
            description = "Affiche tous les modèles SSE disponibles (intégrés + mission + locaux).";
        };
    };

    class COMSPEC_SSE_Module_SiteManager: COMSPEC_SSE_Module_Base {
        scope = 2;
        scopeCurator = 2;
        displayName = "Site Manager SSE";
        function = "comspec_sse_fnc_moduleSiteManager";
        class ModuleDescription {
            description = "Liste les éléments SSE du site, triage et score de complétude (sans spoil de position).";
        };
    };

    class COMSPEC_SSE_Module_GenerateBrief: COMSPEC_SSE_Module_Base {
        scope = 2;
        scopeCurator = 2;
        displayName = "Générer depuis brief / scénario";
        function = "comspec_sse_fnc_moduleGenerateBrief";
        class Arguments {
            class Brief {
                displayName = "Brief narratif";
                typeName = "STRING";
                defaultValue = "cellule logistique de 5 personnes";
            };
            class ScenarioPack {
                displayName = "Pack scénario (optionnel)";
                description = "INSURGENT_CELL, SAFEHOUSE, IED_WORKSHOP, WEAPONS_DEPOT, …";
                typeName = "STRING";
                defaultValue = "";
            };
            class Radius {
                displayName = "Rayon (m)";
                typeName = "NUMBER";
                defaultValue = 40;
            };
        };
        class ModuleDescription {
            description = "Génère un réseau SSE cohérent à partir d'un brief ou d'un pack scénario.";
        };
    };

    class COMSPEC_SSE_Module_SpoilView: COMSPEC_SSE_Module_Base {
        scope = 2;
        scopeCurator = 2;
        displayName = "Spoil Control (connu vs vérité)";
        function = "comspec_sse_fnc_moduleSpoilView";
        class ModuleDescription {
            description = "Compare ce que les joueurs ont découvert à la vérité complète.";
            sync[] = {"Anything"};
        };
    };

    class COMSPEC_SSE_Module_AfterAction: COMSPEC_SSE_Module_Base {
        scope = 2;
        scopeCurator = 2;
        displayName = "After Action SSE + export graphe";
        function = "comspec_sse_fnc_moduleAfterAction";
        class ModuleDescription {
            description = "Compte rendu de fin de mission et export du graphe SSE.";
        };
    };

    class COMSPEC_SSE_Module_SandboxSite: COMSPEC_SSE_Module_Base {
        scope = 2;
        scopeCurator = 2;
        displayName = "Sandbox — site aléatoire";
        function = "comspec_sse_fnc_moduleSandboxSite";
        class ModuleDescription {
            description = "Génère un site SSE aléatoire pour tests / entraînement.";
        };
    };

    class COMSPEC_SSE_Module_ZeusControl: COMSPEC_SSE_Module_Base {
        scope = 2;
        scopeCurator = 2;
        displayName = "Zeus SSE Control (UI)";
        function = "comspec_sse_fnc_moduleZeusControl";
        class ModuleDescription {
            description = "Ouvre le panneau Zeus SSE Control (vérité / connu joueurs, génération, liens, AAR).";
            sync[] = {"Anything"};
        };
    };
};

// Classes de base déclarées une seule fois : chaque dialogue inclus ensuite les réutilise.
class RscText;
class RscButton;
class RscCombo;
class RscCheckbox;
class RscSlider;
class RscListbox;

#include "dialogs\generateDialog.hpp"
#include "dialogs\modelDialog.hpp"
