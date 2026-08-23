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
            "COMSPEC_SSE_Module_ZeusControl",
            "COMSPEC_SSE_Module_ScenarioDirector",
            "COMSPEC_SSE_Module_DomexMark",
            "COMSPEC_SSE_Module_DomexAddIntel",
            "COMSPEC_SSE_Module_DomexSetStage",
            "COMSPEC_SSE_Module_DomexMapPoint"
        };
        weapons[] = {};
        requiredVersion = REQUIRED_VERSION;
        requiredAddons[] = {"comspec_sse_core", "comspec_sse_generator", "comspec_sse_intel", "comspec_sse_ui", "comspec_sse_main", "comspec_sse_eden", "comspec_sse_network", "cba_xeh", "A3_Modules_F"};
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
            class moduleScenarioDirector {};
            class moduleDomexMark {};
            class moduleDomexAddIntel {};
            class moduleDomexSetStage {};
            class moduleDomexMapPoint {};
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
        };
    };
};

class CfgVehicles {
    // Forward-declare uniquement. Redéfinir Module_F:Logic + AttributesBase
    // casse CfgVehicles (Eden / Zeus / ACE) : « Updating base class », iteminfo.side.
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
                description = "FALCON, INSURGENT_CELL, SAFEHOUSE, IED_WORKSHOP, WEAPONS_DEPOT, …";
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

    class COMSPEC_SSE_Module_ScenarioDirector: COMSPEC_SSE_Module_Base {
        scope = 2;
        scopeCurator = 2;
        displayName = "Scenario Director (dataset / niveau)";
        function = "comspec_sse_fnc_moduleScenarioDirector";
        class Arguments {
            class DatasetId {
                displayName = "Dataset";
                description = "falcon (recommandé) ou autre ID enregistré";
                typeName = "STRING";
                defaultValue = "falcon";
            };
            class ScenarioLevel {
                displayName = "Niveau de révélation (0–3)";
                description = "0 Surface · 1 Tactique · 2 Terrain · 3 Vérité complète";
                typeName = "NUMBER";
                defaultValue = 1;
            };
            class Radius {
                displayName = "Rayon (m)";
                typeName = "NUMBER";
                defaultValue = 50;
            };
            class Action {
                displayName = "Action";
                description = "APPLY = poser le dataset · LEVEL_ONLY = changer le niveau · LIST = lister";
                typeName = "STRING";
                defaultValue = "APPLY";
            };
        };
        class ModuleDescription {
            description = "LOT 8 — Applique un dataset (ex. FALCON) et pilote le niveau de scénario.";
        };
    };

    class COMSPEC_SSE_Module_DomexMark: COMSPEC_SSE_Module_Base {
        scope = 2;
        scopeCurator = 2;
        displayName = "Intelligence numérique (DOMEX)";
        function = "comspec_sse_fnc_moduleDomexMark";
        icon = "\A3\ui_f\data\igui\cfg\simpletasks\types\download_ca.paa";
        portrait = "\A3\ui_f\data\igui\cfg\simpletasks\types\download_ca.paa";

        class Arguments {
            class NodeId {
                displayName = "Identifiant";
                description = "Ex. PC-KESTREL-04";
                typeName = "STRING";
                defaultValue = "";
            };
            class DeviceType {
                displayName = "Type de support";
                typeName = "STRING";
                class values {
                    class ordinateur { name = "Ordinateur"; value = "ordinateur"; default = 1; };
                    class telephone { name = "Téléphone"; value = "telephone"; };
                    class tablette { name = "Tablette"; value = "tablette"; };
                    class radio_numerique { name = "Radio"; value = "radio_numerique"; };
                    class cle_usb { name = "Clé USB"; value = "cle_usb"; };
                    class gps { name = "GPS"; value = "gps"; };
                };
            };
            class Owner {
                displayName = "Propriétaire apparent";
                typeName = "STRING";
                defaultValue = "";
            };
            class Organization {
                displayName = "Organisation";
                typeName = "STRING";
                defaultValue = "";
            };
            class Network {
                displayName = "Réseau fictif";
                typeName = "STRING";
                defaultValue = "";
            };
            class Security {
                displayName = "Sécurité scénarisée";
                typeName = "STRING";
                class values {
                    class faible { name = "Faible"; value = "faible"; };
                    class moyenne { name = "Moyenne"; value = "moyenne"; default = 1; };
                    class elevee { name = "Élevée"; value = "elevee"; };
                };
            };
            class Profile {
                displayName = "Profil de contenu";
                typeName = "STRING";
                class values {
                    class generique { name = "Générique"; value = "generique"; default = 1; };
                    class logistique { name = "Logistique"; value = "logistique"; };
                    class commandement { name = "Commandement"; value = "commandement"; };
                    class personnel { name = "Personnel"; value = "personnel"; };
                    class radio { name = "Radio / liaisons"; value = "radio"; };
                };
            };
            class Duration {
                displayName = "Durée (secondes)";
                typeName = "NUMBER";
                defaultValue = "180";
            };
            class AccessRemote {
                displayName = "Accès distant scénarisé";
                typeName = "BOOL";
                defaultValue = "false";
            };
            class PacketType {
                displayName = "Paquet — type";
                typeName = "STRING";
                class values {
                    class none { name = "(aucun)"; value = ""; default = 1; };
                    class message { name = "Message"; value = "message"; };
                    class document { name = "Document"; value = "document"; };
                    class coordinate { name = "Coordonnée / point"; value = "coordinate"; };
                    class contact { name = "Contact"; value = "contact"; };
                    class frequency { name = "Fréquence"; value = "frequency"; };
                };
            };
            class PacketText {
                displayName = "Paquet — texte";
                typeName = "STRING";
                defaultValue = "";
            };
            class PacketQuality {
                displayName = "Paquet — qualité";
                typeName = "STRING";
                class values {
                    class complet { name = "Complet"; value = "complet"; default = 1; };
                    class fragment { name = "Fragment"; value = "fragment"; };
                    class leurre_possible { name = "Peut être un leurre"; value = "leurre_possible"; };
                };
            };
            class PacketEntities {
                displayName = "Paquet — entités (Nom | type)";
                typeName = "STRING";
                defaultValue = "";
            };
        };

        class ModuleDescription {
            description = "Pose le contrat d’intelligence numérique sur l’objet (laptop, téléphone, radio…). Même schéma qu’Eden. Aucun moteur technique.";
            sync[] = {"Anything"};
        };
    };

    class COMSPEC_SSE_Module_DomexAddIntel: COMSPEC_SSE_Module_Base {
        scope = 2;
        scopeCurator = 2;
        displayName = "Ajouter un renseignement";
        function = "comspec_sse_fnc_moduleDomexAddIntel";
        icon = "\A3\ui_f\data\igui\cfg\simpletasks\types\download_ca.paa";
        portrait = "\A3\ui_f\data\igui\cfg\simpletasks\types\download_ca.paa";
        class ModuleDescription {
            description = "Ajoute un renseignement scénarisé pendant la mission. Posez le module sur un objet (pas une personne).";
            sync[] = {"Anything"};
        };
    };

    class COMSPEC_SSE_Module_DomexSetStage: COMSPEC_SSE_Module_Base {
        scope = 2;
        scopeCurator = 2;
        displayName = "Fixer le palier d’accès";
        function = "comspec_sse_fnc_moduleDomexSetStage";
        icon = "\A3\ui_f\data\igui\cfg\simpletasks\types\use_ca.paa";
        portrait = "\A3\ui_f\data\igui\cfg\simpletasks\types\use_ca.paa";
        class Arguments {
            class Stage {
                displayName = "Palier";
                typeName = "STRING";
                class values {
                    class non_identifie { name = "Non identifié"; value = "non_identifie"; };
                    class decouvert { name = "Découvert"; value = "decouvert"; default = 1; };
                    class acces_en_cours { name = "Accès en cours"; value = "acces_en_cours"; };
                    class acces_etabli { name = "Accès établi"; value = "acces_etabli"; };
                    class exploite { name = "Exploité"; value = "exploite"; };
                };
            };
        };
        class ModuleDescription {
            description = "Change le palier d’accès du support. Au palier « accès établi », les contenus prévus pour ce palier rejoignent la file.";
            sync[] = {"Anything"};
        };
    };

    class COMSPEC_SSE_Module_DomexMapPoint: COMSPEC_SSE_Module_Base {
        scope = 2;
        scopeCurator = 2;
        displayName = "Poser un point carte";
        function = "comspec_sse_fnc_moduleDomexMapPoint";
        icon = "\A3\ui_f\data\igui\cfg\simpletasks\types\map_ca.paa";
        portrait = "\A3\ui_f\data\igui\cfg\simpletasks\types\map_ca.paa";
        class Arguments {
            class Label {
                displayName = "Libellé";
                typeName = "STRING";
                defaultValue = "";
            };
        };
        class ModuleDescription {
            description = "Pose un point de renseignement sur la carte du bureau. Invisible sur la carte des joueurs.";
            position = 1;
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

#include "CfgEventHandlers.hpp"
#include "dialogs\generateDialog.hpp"
#include "dialogs\modelDialog.hpp"
