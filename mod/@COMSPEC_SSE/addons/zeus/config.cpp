#include "script_component.hpp"
#include "\z\comspec_sse\addons\ui\ui_macros.hpp"

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
            "COMSPEC_SSE_Module_DomexMapPoint"
        };
        weapons[] = {};
        requiredVersion = REQUIRED_VERSION;
        requiredAddons[] = {"comspec_sse_core", "comspec_sse_generator", "comspec_sse_intel", "comspec_sse_ui", "comspec_sse_main", "comspec_sse_eden", "comspec_sse_network", "cba_xeh", "A3_Modules_F"};
        author = "COMSPEC";
        VERSION_CONFIG;
    };
};

class CfgFactionClasses {
    class NO_CATEGORY;
    class COMSPEC_SSE_Tools: NO_CATEGORY {
        displayName = "COMSPEC — SSE · Outils Zeus";
        priority = 2;
        side = 7;
    };
    class COMSPEC_SSE_Domex: NO_CATEGORY {
        displayName = "COMSPEC — SSE · Numérique (DOMEX)";
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
        displayName = "Générer un profil SSE";
        category = "COMSPEC_SSE";
        function = "comspec_sse_fnc_moduleGenerateData";
        icon = "\A3\ui_f\data\igui\cfg\simpletasks\types\intel_ca.paa";
        portrait = "\A3\ui_f\data\igui\cfg\simpletasks\types\intel_ca.paa";

        class Attributes {
            class Profile {
                displayName = "Profil";
                tooltip = "Rôle narratif du sujet : oriente contacts, messages et documents.";
                property = "COMSPEC_SSE_Module_GenerateData_Profile";
                control = "Combo";
                expression = "_this setVariable ['Profile',_value,true];";
                defaultValue = "'INSURGENT'";
                typeName = "STRING";
                class values {
                    class V0 { name = "Insurgé"; value = "INSURGENT"; default = 1; };
                    class V1 { name = "Civil"; value = "CIVILIAN"; };
                    class V2 { name = "Militaire"; value = "MILITARY"; };
                    class V3 { name = "Chef / HVT"; value = "COMMANDER"; };
                    class V4 { name = "Courrier"; value = "COURIER"; };
                    class V5 { name = "Financier"; value = "FINANCIER"; };
                    class V6 { name = "Technicien / artificier"; value = "TECHNICIAN"; };
                    class V7 { name = "Renseignement"; value = "INTELLIGENCE"; };
                    class V8 { name = "Logistique"; value = "LOGISTICS"; };
                    class V9 { name = "Aléatoire"; value = "RANDOM"; };
                };
            };
            class Complexity {
                displayName = "Richesse";
                tooltip = "Quantité d'éléments exploitables générés.";
                property = "COMSPEC_SSE_Module_GenerateData_Complexity";
                control = "Combo";
                expression = "_this setVariable ['Complexity',_value,true];";
                defaultValue = "'STANDARD'";
                typeName = "STRING";
                class values {
                    class V0 { name = "Légère — quelques indices"; value = "LIGHT"; };
                    class V1 { name = "Standard"; value = "STANDARD"; default = 1; };
                    class V2 { name = "Détaillée"; value = "DETAILED"; };
                    class V3 { name = "Haute valeur — dossier riche"; value = "HIGH_VALUE"; };
                };
            };
            class WantIdentity {
                displayName = "Identité réelle (Arma / Eden)";
                tooltip = "Décoché : le terminal invente un nom SSE au lieu de reprendre l'identité de l'unité.";
                property = "COMSPEC_SSE_Module_GenerateData_WantIdentity";
                control = "Checkbox";
                expression = "_this setVariable ['WantIdentity',_value,true];";
                defaultValue = "true";
                typeName = "BOOL";
            };
            class WantPhone {
                displayName = "Téléphone";
                tooltip = "Décoché : aucun téléphone généré.";
                property = "COMSPEC_SSE_Module_GenerateData_WantPhone";
                control = "Checkbox";
                expression = "_this setVariable ['WantPhone',_value,true];";
                defaultValue = "true";
                typeName = "BOOL";
            };
            class WantDocuments {
                displayName = "Documents";
                tooltip = "Décoché : aucun document papier généré.";
                property = "COMSPEC_SSE_Module_GenerateData_WantDocuments";
                control = "Checkbox";
                expression = "_this setVariable ['WantDocuments',_value,true];";
                defaultValue = "true";
                typeName = "BOOL";
            };
            class WantBio {
                displayName = "Biométrie";
                tooltip = "Décoché : pas d'empreintes / iris / ADN pour SEEK.";
                property = "COMSPEC_SSE_Module_GenerateData_WantBio";
                control = "Checkbox";
                expression = "_this setVariable ['WantBio',_value,true];";
                defaultValue = "true";
                typeName = "BOOL";
            };
            class WantNetwork {
                displayName = "Lier les cibles entre elles";
                tooltip = "Crée des liens « associé » entre les entités synchronisées.";
                property = "COMSPEC_SSE_Module_GenerateData_WantNetwork";
                control = "Checkbox";
                expression = "_this setVariable ['WantNetwork',_value,true];";
                defaultValue = "true";
                typeName = "BOOL";
            };
            class NoisePct {
                displayName = "Données inutiles (%)";
                tooltip = "Probabilité d'ajouter des messages sans intérêt, pour masquer les vrais indices (0–100).";
                property = "COMSPEC_SSE_Module_GenerateData_NoisePct";
                control = "Combo";
                expression = "_this setVariable ['NoisePct',_value,true];";
                defaultValue = "25";
                typeName = "NUMBER";
                class values {
                    class V0 { name = "0 % — aucune"; value = 0; };
                    class V1 { name = "10 %"; value = 10; };
                    class V2 { name = "25 % (défaut)"; value = 25; default = 1; };
                    class V3 { name = "50 %"; value = 50; };
                    class V4 { name = "75 %"; value = 75; };
                };
            };
        };

        class ModuleDescription {
            description = "Génère un profil SSE (identité, téléphone, documents, biométrie) sur la personne ou l'objet ciblé. Zeus : posez le module sur la cible, un formulaire s'ouvre. Eden : synchronisez les cibles au module.";
            sync[] = {"AnyPerson", "AnyVehicle", "Anything"};
        };
    };

    class COMSPEC_SSE_Module_GenerateSite: COMSPEC_SSE_Module_Base {
        scope = 2;
        scopeCurator = 2;
        displayName = "Créer un site SSE complet";
        category = "COMSPEC_SSE";
        function = "comspec_sse_fnc_moduleGenerateSite";
        icon = "\A3\ui_f\data\igui\cfg\simpletasks\types\search_ca.paa";
        portrait = "\A3\ui_f\data\igui\cfg\simpletasks\types\search_ca.paa";
        canSetArea = 1;
        canSetAreaHeight = 0;
        canSetAreaShape = 1;

        class Attributes {
            class Radius {
                displayName = "Rayon";
                tooltip = "Tout ce qui se trouve dans ce rayon est intégré au site.";
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
                displayName = "Type de site";
                tooltip = "Oriente le réseau, les thèmes et les documents.";
                property = "COMSPEC_SSE_Module_GenerateSite_Profile";
                control = "Combo";
                expression = "_this setVariable ['Profile',_value,true];";
                defaultValue = "'INSURGENT'";
                typeName = "STRING";
                class values {
                    class V0 { name = "Cellule insurgée"; value = "INSURGENT"; default = 1; };
                    class V1 { name = "Position militaire"; value = "MILITARY"; };
                    class V2 { name = "Site civil"; value = "CIVILIAN"; };
                    class V3 { name = "Poste de commandement"; value = "COMMANDER"; };
                    class V4 { name = "Nœud logistique"; value = "LOGISTICS"; };
                    class V5 { name = "Réseau financier"; value = "FINANCIER"; };
                    class V6 { name = "Atelier technique / IED"; value = "TECHNICIAN"; };
                    class V7 { name = "Aléatoire"; value = "RANDOM"; };
                };
            };
            class Complexity {
                displayName = "Richesse";
                tooltip = "Quantité d'indices par entité.";
                property = "COMSPEC_SSE_Module_GenerateSite_Complexity";
                control = "Combo";
                expression = "_this setVariable ['Complexity',_value,true];";
                defaultValue = "'DETAILED'";
                typeName = "STRING";
                class values {
                    class V0 { name = "Légère — quelques indices"; value = "LIGHT"; };
                    class V1 { name = "Standard"; value = "STANDARD"; };
                    class V2 { name = "Détaillée"; value = "DETAILED"; default = 1; };
                    class V3 { name = "Haute valeur — dossier riche"; value = "HIGH_VALUE"; };
                };
            };
            class MaxObjects {
                displayName = "Objets exploitables (max)";
                tooltip = "Nombre maximum d'objets (caisses, sacs…) intégrés au site.";
                property = "COMSPEC_SSE_Module_GenerateSite_MaxObjects";
                control = "Combo";
                expression = "_this setVariable ['MaxObjects',_value,true];";
                defaultValue = "8";
                typeName = "NUMBER";
                class values {
                    class V0 { name = "Aucun"; value = 0; };
                    class V1 { name = "4"; value = 4; };
                    class V2 { name = "8 (défaut)"; value = 8; default = 1; };
                    class V3 { name = "12"; value = 12; };
                    class V4 { name = "20"; value = 20; };
                    class V5 { name = "30"; value = 30; };
                };
            };
            class WantDigital {
                displayName = "Supports numériques";
                tooltip = "Téléphones / ordinateurs sur le site.";
                property = "COMSPEC_SSE_Module_GenerateSite_WantDigital";
                control = "Checkbox";
                expression = "_this setVariable ['WantDigital',_value,true];";
                defaultValue = "true";
                typeName = "BOOL";
            };
            class WantDocuments {
                displayName = "Documents";
                tooltip = "Documents papier liés au réseau.";
                property = "COMSPEC_SSE_Module_GenerateSite_WantDocuments";
                control = "Checkbox";
                expression = "_this setVariable ['WantDocuments',_value,true];";
                defaultValue = "true";
                typeName = "BOOL";
            };
            class WantNetwork {
                displayName = "Liens réseau";
                tooltip = "Relie les personnes du site entre elles.";
                property = "COMSPEC_SSE_Module_GenerateSite_WantNetwork";
                control = "Checkbox";
                expression = "_this setVariable ['WantNetwork',_value,true];";
                defaultValue = "true";
                typeName = "BOOL";
            };
        };

        class ModuleDescription {
            description = "Relie au même réseau les personnes, véhicules et objets du rayon (téléphones, documents, liens). Placez d'abord le décor, puis le module au centre. Eden : la zone du module peut remplacer le rayon.";
            position = 1;
        };
    };

    class COMSPEC_SSE_Module_InitTarget: COMSPEC_SSE_Module_Base {
        scope = 2;
        scopeCurator = 2;
        displayName = "Rendre exploitable (SSE)";
        category = "COMSPEC_SSE";
        function = "comspec_sse_fnc_moduleInitTarget";
        icon = "\A3\ui_f\data\igui\cfg\simpletasks\types\use_ca.paa";
        portrait = "\A3\ui_f\data\igui\cfg\simpletasks\types\use_ca.paa";

        class Attributes {
            class EntityType {
                displayName = "Nature de l'objet";
                tooltip = "Automatique : déduit de la classe de l'objet (téléphone, ordinateur, document…).";
                property = "COMSPEC_SSE_Module_InitTarget_EntityType";
                control = "Combo";
                expression = "_this setVariable ['EntityType',_value,true];";
                defaultValue = "'AUTO'";
                typeName = "STRING";
                class values {
                    class V0 { name = "Automatique (selon l'objet)"; value = "AUTO"; default = 1; };
                    class V1 { name = "Objet divers"; value = "OBJECT"; };
                    class V2 { name = "Document"; value = "DOCUMENT"; };
                    class V3 { name = "Téléphone"; value = "PHONE"; };
                    class V4 { name = "Ordinateur"; value = "COMPUTER"; };
                    class V5 { name = "Radio"; value = "RADIO"; };
                    class V6 { name = "Conteneur / caisse"; value = "CONTAINER"; };
                    class V7 { name = "Arme"; value = "WEAPON"; };
                    class V8 { name = "Véhicule"; value = "VEHICLE"; };
                };
            };
            class Profile {
                displayName = "Profil";
                tooltip = "Profil narratif du contenu.";
                property = "COMSPEC_SSE_Module_InitTarget_Profile";
                control = "Combo";
                expression = "_this setVariable ['Profile',_value,true];";
                defaultValue = "'RANDOM'";
                typeName = "STRING";
                class values {
                    class V0 { name = "Insurgé"; value = "INSURGENT"; };
                    class V1 { name = "Civil"; value = "CIVILIAN"; };
                    class V2 { name = "Militaire"; value = "MILITARY"; };
                    class V3 { name = "Chef / HVT"; value = "COMMANDER"; };
                    class V4 { name = "Courrier"; value = "COURIER"; };
                    class V5 { name = "Financier"; value = "FINANCIER"; };
                    class V6 { name = "Technicien / artificier"; value = "TECHNICIAN"; };
                    class V7 { name = "Renseignement"; value = "INTELLIGENCE"; };
                    class V8 { name = "Logistique"; value = "LOGISTICS"; };
                    class V9 { name = "Aléatoire"; value = "RANDOM"; default = 1; };
                };
            };
            class Complexity {
                displayName = "Richesse";
                tooltip = "Quantité d'indices.";
                property = "COMSPEC_SSE_Module_InitTarget_Complexity";
                control = "Combo";
                expression = "_this setVariable ['Complexity',_value,true];";
                defaultValue = "'STANDARD'";
                typeName = "STRING";
                class values {
                    class V0 { name = "Légère — quelques indices"; value = "LIGHT"; };
                    class V1 { name = "Standard"; value = "STANDARD"; default = 1; };
                    class V2 { name = "Détaillée"; value = "DETAILED"; };
                    class V3 { name = "Haute valeur — dossier riche"; value = "HIGH_VALUE"; };
                };
            };
        };

        class ModuleDescription {
            description = "Rend la cible exploitable SSE ; le contenu détaillé est généré au premier examen (léger). Zeus : posez le module sur l'objet. Eden : synchronisez les objets.";
            sync[] = {"Anything"};
        };
    };

    class COMSPEC_SSE_Module_PlaceEvidence: COMSPEC_SSE_Module_Base {
        scope = 2;
        scopeCurator = 2;
        displayName = "Poser un objet de renseignement";
        category = "COMSPEC_SSE";
        function = "comspec_sse_fnc_modulePlaceEvidence";
        icon = "\A3\ui_f\data\igui\cfg\simpletasks\types\documents_ca.paa";
        portrait = "\A3\ui_f\data\igui\cfg\simpletasks\types\documents_ca.paa";

        class Attributes {
            class ObjectClass {
                displayName = "Objet";
                tooltip = "Objet vanilla créé à la position du module.";
                property = "COMSPEC_SSE_Module_PlaceEvidence_ObjectClass";
                control = "Combo";
                expression = "_this setVariable ['ObjectClass',_value,true];";
                defaultValue = "'Land_Laptop_unfolded_F'";
                typeName = "STRING";
                class values {
                    class V0 { name = "Ordinateur portable"; value = "Land_Laptop_unfolded_F"; default = 1; };
                    class V1 { name = "Smartphone"; value = "Land_MobilePhone_smart_F"; };
                    class V2 { name = "Téléphone basique"; value = "Land_MobilePhone_old_F"; };
                    class V3 { name = "Téléphone satellite"; value = "Land_SatellitePhone_F"; };
                    class V4 { name = "Radio portable"; value = "Land_PortableLongRangeRadio_F"; };
                    class V5 { name = "Dossier papier"; value = "Land_File1_F"; };
                    class V6 { name = "Photos / dossier"; value = "Land_FilePhotos_F"; };
                    class V7 { name = "Valise"; value = "Land_Suitcase_F"; };
                    class V8 { name = "Caisse d'armes"; value = "Box_FIA_Wps_F"; };
                };
            };
            class Profile {
                displayName = "Profil";
                tooltip = "Profil narratif du contenu.";
                property = "COMSPEC_SSE_Module_PlaceEvidence_Profile";
                control = "Combo";
                expression = "_this setVariable ['Profile',_value,true];";
                defaultValue = "'INSURGENT'";
                typeName = "STRING";
                class values {
                    class V0 { name = "Insurgé"; value = "INSURGENT"; default = 1; };
                    class V1 { name = "Civil"; value = "CIVILIAN"; };
                    class V2 { name = "Militaire"; value = "MILITARY"; };
                    class V3 { name = "Chef / HVT"; value = "COMMANDER"; };
                    class V4 { name = "Courrier"; value = "COURIER"; };
                    class V5 { name = "Financier"; value = "FINANCIER"; };
                    class V6 { name = "Technicien / artificier"; value = "TECHNICIAN"; };
                    class V7 { name = "Renseignement"; value = "INTELLIGENCE"; };
                    class V8 { name = "Logistique"; value = "LOGISTICS"; };
                    class V9 { name = "Aléatoire"; value = "RANDOM"; };
                };
            };
            class Complexity {
                displayName = "Richesse";
                tooltip = "Quantité d'indices.";
                property = "COMSPEC_SSE_Module_PlaceEvidence_Complexity";
                control = "Combo";
                expression = "_this setVariable ['Complexity',_value,true];";
                defaultValue = "'STANDARD'";
                typeName = "STRING";
                class values {
                    class V0 { name = "Légère — quelques indices"; value = "LIGHT"; };
                    class V1 { name = "Standard"; value = "STANDARD"; default = 1; };
                    class V2 { name = "Détaillée"; value = "DETAILED"; };
                    class V3 { name = "Haute valeur — dossier riche"; value = "HIGH_VALUE"; };
                };
            };
            class GenerateNow {
                displayName = "Générer le contenu au lancement";
                tooltip = "Décoché : le contenu est généré au premier examen (plus léger).";
                property = "COMSPEC_SSE_Module_PlaceEvidence_GenerateNow";
                control = "Checkbox";
                expression = "_this setVariable ['GenerateNow',_value,true];";
                defaultValue = "false";
                typeName = "BOOL";
            };
        };

        class ModuleDescription {
            description = "Crée un objet (ordinateur, téléphone, dossier, radio, caisse…) à la position du module et le rend exploitable SSE. L'objet devient éditable par les Zeus.";
            position = 1;
        };
    };

    class COMSPEC_SSE_Module_ApplyModel: COMSPEC_SSE_Module_Base {
        scope = 2;
        scopeCurator = 2;
        displayName = "Appliquer un modèle SSE";
        category = "COMSPEC_SSE";
        function = "comspec_sse_fnc_moduleApplyModel";
        icon = "\A3\ui_f\data\igui\cfg\simpletasks\types\upload_ca.paa";
        portrait = "\A3\ui_f\data\igui\cfg\simpletasks\types\upload_ca.paa";

        class Attributes {
            class ModelId {
                displayName = "Identifiant du modèle";
                tooltip = "Ex. builtin_chef_hvt, builtin_cellule_insurgee_irak. Vide en Zeus = choisir dans la liste.";
                property = "COMSPEC_SSE_Module_ApplyModel_ModelId";
                control = "Edit";
                expression = "_this setVariable ['ModelId',_value,true];";
                defaultValue = "''";
                typeName = "STRING";
            };
        };

        class ModuleDescription {
            description = "Applique un modèle SSE (intégré, mission ou local) sur la cible. Zeus : sans identifiant, la liste des modèles s'ouvre.";
            sync[] = {"Anything"};
        };
    };

    class COMSPEC_SSE_Module_SaveModel: COMSPEC_SSE_Module_Base {
        scope = 1;
        scopeCurator = 2;
        displayName = "Enregistrer comme modèle SSE";
        category = "COMSPEC_SSE_Tools";
        function = "comspec_sse_fnc_moduleSaveModel";
        icon = "\A3\ui_f\data\igui\cfg\simpletasks\types\download_ca.paa";
        portrait = "\A3\ui_f\data\igui\cfg\simpletasks\types\download_ca.paa";

        class Attributes {
            class ModelName {
                displayName = "Nom du modèle";
                tooltip = "Nom affiché dans la liste des modèles.";
                property = "COMSPEC_SSE_Module_SaveModel_ModelName";
                control = "Edit";
                expression = "_this setVariable ['ModelName',_value,true];";
                defaultValue = "'Mon modèle SSE'";
                typeName = "STRING";
            };
        };

        class ModuleDescription {
            description = "Capture la cible (générée au besoin) et l'enregistre comme modèle réutilisable (mission + profil local du Zeus).";
            sync[] = {"Anything"};
        };
    };

    class COMSPEC_SSE_Module_GenerateBrief: COMSPEC_SSE_Module_Base {
        scope = 2;
        scopeCurator = 2;
        displayName = "Générer depuis un brief / scénario";
        category = "COMSPEC_SSE";
        function = "comspec_sse_fnc_moduleGenerateBrief";
        icon = "\A3\ui_f\data\igui\cfg\simpletasks\types\meet_ca.paa";
        portrait = "\A3\ui_f\data\igui\cfg\simpletasks\types\meet_ca.paa";

        class Attributes {
            class Brief {
                displayName = "Brief narratif";
                tooltip = "Ex. « cellule de 3 financiers », « atelier IED », « chef et courrier ». Ignoré si un pack est choisi.";
                property = "COMSPEC_SSE_Module_GenerateBrief_Brief";
                control = "Edit";
                expression = "_this setVariable ['Brief',_value,true];";
                defaultValue = "'cellule logistique de 5 personnes'";
                typeName = "STRING";
            };
            class ScenarioPack {
                displayName = "Pack scénario";
                tooltip = "Scénario prédéfini (prioritaire sur le brief).";
                property = "COMSPEC_SSE_Module_GenerateBrief_ScenarioPack";
                control = "Combo";
                expression = "_this setVariable ['ScenarioPack',_value,true];";
                defaultValue = "''";
                typeName = "STRING";
                class values {
                    class V0 { name = "Aucun — utiliser le brief"; value = ""; default = 1; };
                    class V1 { name = "Cellule insurgée"; value = "INSURGENT_CELL"; };
                    class V2 { name = "Réseau de contrebande"; value = "SMUGGLING_NETWORK"; };
                    class V3 { name = "Dépôt d'armes"; value = "WEAPONS_DEPOT"; };
                    class V4 { name = "Poste de commandement"; value = "COMMAND_POST"; };
                    class V5 { name = "Planque"; value = "SAFEHOUSE"; };
                    class V6 { name = "Atelier IED"; value = "IED_WORKSHOP"; };
                    class V7 { name = "Nœud financier"; value = "FINANCIAL_NODE"; };
                    class V8 { name = "Cellule renseignement"; value = "INTELLIGENCE_CELL"; };
                    class V9 { name = "Dataset FALCON (Irak 2012)"; value = "FALCON"; };
                };
            };
            class Radius {
                displayName = "Rayon";
                tooltip = "Les personnes et objets dans ce rayon forment le réseau.";
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
            description = "Génère un réseau SSE cohérent autour du module à partir d'un brief libre ou d'un pack scénario prédéfini.";
            position = 1;
        };
    };

    class COMSPEC_SSE_Module_ScenarioDirector: COMSPEC_SSE_Module_Base {
        scope = 2;
        scopeCurator = 2;
        displayName = "Directeur de scénario (dataset / niveau)";
        category = "COMSPEC_SSE";
        function = "comspec_sse_fnc_moduleScenarioDirector";
        icon = "\A3\ui_f\data\igui\cfg\simpletasks\types\whiteboard_ca.paa";
        portrait = "\A3\ui_f\data\igui\cfg\simpletasks\types\whiteboard_ca.paa";

        class Attributes {
            class Action {
                displayName = "Action";
                tooltip = "Appliquer : pose le dataset. Niveau seul : ne change que le niveau. Lister : affiche les datasets.";
                property = "COMSPEC_SSE_Module_ScenarioDirector_Action";
                control = "Combo";
                expression = "_this setVariable ['Action',_value,true];";
                defaultValue = "'APPLY'";
                typeName = "STRING";
                class values {
                    class V0 { name = "Appliquer le dataset"; value = "APPLY"; default = 1; };
                    class V1 { name = "Changer le niveau seulement"; value = "LEVEL_ONLY"; };
                    class V2 { name = "Lister les datasets"; value = "LIST"; };
                };
            };
            class DatasetId {
                displayName = "Dataset";
                tooltip = "Identifiant du dataset (falcon recommandé).";
                property = "COMSPEC_SSE_Module_ScenarioDirector_DatasetId";
                control = "Edit";
                expression = "_this setVariable ['DatasetId',_value,true];";
                defaultValue = "'falcon'";
                typeName = "STRING";
            };
            class ScenarioLevel {
                displayName = "Niveau de révélation";
                tooltip = "0 Surface · 1 Tactique · 2 Terrain · 3 Vérité complète.";
                property = "COMSPEC_SSE_Module_ScenarioDirector_ScenarioLevel";
                control = "Combo";
                expression = "_this setVariable ['ScenarioLevel',_value,true];";
                defaultValue = "1";
                typeName = "NUMBER";
                class values {
                    class V0 { name = "0 — Surface"; value = 0; };
                    class V1 { name = "1 — Tactique"; value = 1; default = 1; };
                    class V2 { name = "2 — Terrain"; value = 2; };
                    class V3 { name = "3 — Vérité complète"; value = 3; };
                };
            };
            class Radius {
                displayName = "Rayon";
                tooltip = "Personnes concernées autour du module.";
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
            description = "Applique un dataset narratif (ex. FALCON) autour du module et règle le niveau de révélation : ce que les joueurs peuvent apprendre.";
            position = 1;
        };
    };

    class COMSPEC_SSE_Module_SandboxSite: COMSPEC_SSE_Module_Base {
        scope = 2;
        scopeCurator = 2;
        displayName = "Site d'entraînement SSE";
        category = "COMSPEC_SSE";
        function = "comspec_sse_fnc_moduleSandboxSite";
        icon = "\A3\ui_f\data\igui\cfg\simpletasks\types\box_ca.paa";
        portrait = "\A3\ui_f\data\igui\cfg\simpletasks\types\box_ca.paa";

        class Attributes {
            class Pack {
                displayName = "Scénario";
                tooltip = "Aléatoire : cellule, planque, atelier IED, dépôt ou nœud financier.";
                property = "COMSPEC_SSE_Module_SandboxSite_Pack";
                control = "Combo";
                expression = "_this setVariable ['Pack',_value,true];";
                defaultValue = "'RANDOM'";
                typeName = "STRING";
                class values {
                    class V0 { name = "Aléatoire"; value = "RANDOM"; default = 1; };
                    class V1 { name = "Cellule insurgée"; value = "INSURGENT_CELL"; };
                    class V2 { name = "Réseau de contrebande"; value = "SMUGGLING_NETWORK"; };
                    class V3 { name = "Dépôt d'armes"; value = "WEAPONS_DEPOT"; };
                    class V4 { name = "Poste de commandement"; value = "COMMAND_POST"; };
                    class V5 { name = "Planque"; value = "SAFEHOUSE"; };
                    class V6 { name = "Atelier IED"; value = "IED_WORKSHOP"; };
                    class V7 { name = "Nœud financier"; value = "FINANCIAL_NODE"; };
                    class V8 { name = "Cellule renseignement"; value = "INTELLIGENCE_CELL"; };
                };
            };
            class Radius {
                displayName = "Rayon";
                tooltip = "Personnes et objets dans ce rayon.";
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
            description = "Génère un site SSE d'entraînement (pack aléatoire ou choisi) autour du module.";
            position = 1;
        };
    };

    class COMSPEC_SSE_Module_LinkEntities: COMSPEC_SSE_Module_Base {
        scope = 2;
        scopeCurator = 2;
        displayName = "Lier des entités SSE";
        category = "COMSPEC_SSE";
        function = "comspec_sse_fnc_moduleLinkEntities";
        icon = "\A3\ui_f\data\igui\cfg\simpletasks\types\meet_ca.paa";
        portrait = "\A3\ui_f\data\igui\cfg\simpletasks\types\meet_ca.paa";

        class Attributes {
            class Relation {
                displayName = "Relation";
                tooltip = "Nature du lien affiché dans le graphe.";
                property = "COMSPEC_SSE_Module_LinkEntities_Relation";
                control = "Combo";
                expression = "_this setVariable ['Relation',_value,true];";
                defaultValue = "'ASSOCIATE'";
                typeName = "STRING";
                class values {
                    class V0 { name = "Associé"; value = "ASSOCIATE"; default = 1; };
                    class V1 { name = "Contact"; value = "CONTACT"; };
                    class V2 { name = "Propriétaire de"; value = "OWNER"; };
                    class V3 { name = "Fait référence à"; value = "REFERENCES"; };
                };
            };
            class Confidence {
                displayName = "Confiance";
                tooltip = "0 = rumeur · 1 = établi.";
                property = "COMSPEC_SSE_Module_LinkEntities_Confidence";
                control = "Combo";
                expression = "_this setVariable ['Confidence',_value,true];";
                defaultValue = "0.7";
                typeName = "NUMBER";
                class values {
                    class V0 { name = "Faible (0,3)"; value = 0.3; };
                    class V1 { name = "Moyenne (0,5)"; value = 0.5; };
                    class V2 { name = "Bonne (0,7)"; value = 0.7; default = 1; };
                    class V3 { name = "Établie (0,9)"; value = 0.9; };
                };
            };
            class Mode {
                displayName = "Disposition";
                tooltip = "Chaîne : A-B, B-C… · Étoile : la première entité reliée à toutes les autres.";
                property = "COMSPEC_SSE_Module_LinkEntities_Mode";
                control = "Combo";
                expression = "_this setVariable ['Mode',_value,true];";
                defaultValue = "'CHAIN'";
                typeName = "STRING";
                class values {
                    class V0 { name = "Chaîne (A-B, B-C…)"; value = "CHAIN"; default = 1; };
                    class V1 { name = "Étoile (première entité au centre)"; value = "STAR"; };
                };
            };
        };

        class ModuleDescription {
            description = "Crée des liens dans le graphe de renseignement. Eden : synchronisez au moins deux entités. Zeus : sélectionnez-les puis posez le module sur l'une d'elles.";
            sync[] = {"Anything"};
        };
    };

    class COMSPEC_SSE_Module_CaseReference: COMSPEC_SSE_Module_Base {
        scope = 2;
        scopeCurator = 2;
        displayName = "Référence de dossier SSE";
        category = "COMSPEC_SSE";
        function = "comspec_sse_fnc_moduleCaseReference";
        icon = "\A3\ui_f\data\igui\cfg\simpletasks\types\documents_ca.paa";
        portrait = "\A3\ui_f\data\igui\cfg\simpletasks\types\documents_ca.paa";

        class Attributes {
            class Reference {
                displayName = "Référence du dossier";
                tooltip = "Dossier ouvert côté portail, par exemple SSE-2026-0007.";
                property = "COMSPEC_SSE_Module_CaseReference_Reference";
                control = "Edit";
                expression = "_this setVariable ['Reference',_value,true];";
                defaultValue = "''";
                typeName = "STRING";
            };
        };

        class ModuleDescription {
            description = "Fixe la référence de dossier (ex. SSE-2026-0007) utilisée pour toutes les fiches transmises vers Athena.";
        };
    };

    class COMSPEC_SSE_Module_ZeusControl: COMSPEC_SSE_Module_Base {
        scope = 1;
        scopeCurator = 2;
        displayName = "Panneau Zeus SSE";
        category = "COMSPEC_SSE_Tools";
        function = "comspec_sse_fnc_moduleZeusControl";
        icon = "\A3\ui_f\data\igui\cfg\simpletasks\types\interact_ca.paa";
        portrait = "\A3\ui_f\data\igui\cfg\simpletasks\types\interact_ca.paa";

        class ModuleDescription {
            description = "Ouvre le panneau Zeus SSE (vérité / connu des joueurs, génération, liens, AAR) sur l'entité ciblée.";
            sync[] = {"Anything"};
        };
    };

    class COMSPEC_SSE_Module_ViewData: COMSPEC_SSE_Module_Base {
        scope = 1;
        scopeCurator = 2;
        displayName = "Consulter les données SSE";
        category = "COMSPEC_SSE_Tools";
        function = "comspec_sse_fnc_moduleViewData";
        icon = "\A3\ui_f\data\igui\cfg\simpletasks\types\scout_ca.paa";
        portrait = "\A3\ui_f\data\igui\cfg\simpletasks\types\scout_ca.paa";

        class ModuleDescription {
            description = "Affiche les données SSE complètes de la cible (UID, profil, état, nom, liens).";
            sync[] = {"Anything"};
        };
    };

    class COMSPEC_SSE_Module_SpoilView: COMSPEC_SSE_Module_Base {
        scope = 1;
        scopeCurator = 2;
        displayName = "Connu des joueurs / vérité";
        category = "COMSPEC_SSE_Tools";
        function = "comspec_sse_fnc_moduleSpoilView";
        icon = "\A3\ui_f\data\igui\cfg\simpletasks\types\scout_ca.paa";
        portrait = "\A3\ui_f\data\igui\cfg\simpletasks\types\scout_ca.paa";

        class ModuleDescription {
            description = "Compare ce que les joueurs ont découvert sur la cible à la vérité complète.";
            sync[] = {"Anything"};
        };
    };

    class COMSPEC_SSE_Module_SiteManager: COMSPEC_SSE_Module_Base {
        scope = 1;
        scopeCurator = 2;
        displayName = "Gestionnaire de site SSE";
        category = "COMSPEC_SSE_Tools";
        function = "comspec_sse_fnc_moduleSiteManager";
        icon = "\A3\ui_f\data\igui\cfg\simpletasks\types\whiteboard_ca.paa";
        portrait = "\A3\ui_f\data\igui\cfg\simpletasks\types\whiteboard_ca.paa";

        class Attributes {
            class Radius {
                displayName = "Rayon";
                tooltip = "Zone analysée autour du module.";
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
            description = "Complétude et triage des éléments SSE autour du module, sans révéler de position aux joueurs.";
            position = 1;
        };
    };

    class COMSPEC_SSE_Module_AfterAction: COMSPEC_SSE_Module_Base {
        scope = 1;
        scopeCurator = 2;
        displayName = "Compte rendu SSE (AAR) + graphe";
        category = "COMSPEC_SSE_Tools";
        function = "comspec_sse_fnc_moduleAfterAction";
        icon = "\A3\ui_f\data\igui\cfg\simpletasks\types\whiteboard_ca.paa";
        portrait = "\A3\ui_f\data\igui\cfg\simpletasks\types\whiteboard_ca.paa";

        class Attributes {
            class Radius {
                displayName = "Rayon";
                tooltip = "Zone prise en compte.";
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
                displayName = "Exporter le graphe";
                tooltip = "Exporte le graphe de renseignement de la mission.";
                property = "COMSPEC_SSE_Module_AfterAction_ExportGraph";
                control = "Checkbox";
                expression = "_this setVariable ['ExportGraph',_value,true];";
                defaultValue = "true";
                typeName = "BOOL";
            };
        };

        class ModuleDescription {
            description = "Compte rendu de fin de mission (complétude, éléments exploités) et export du graphe de renseignement.";
            position = 1;
        };
    };

    class COMSPEC_SSE_Module_ListModels: COMSPEC_SSE_Module_Base {
        scope = 1;
        scopeCurator = 2;
        displayName = "Lister les modèles SSE";
        category = "COMSPEC_SSE_Tools";
        function = "comspec_sse_fnc_moduleListModels";
        icon = "\A3\ui_f\data\igui\cfg\simpletasks\types\documents_ca.paa";
        portrait = "\A3\ui_f\data\igui\cfg\simpletasks\types\documents_ca.paa";

        class ModuleDescription {
            description = "Affiche les modèles SSE disponibles (intégrés, mission, locaux).";
        };
    };

    class COMSPEC_SSE_Module_ClearData: COMSPEC_SSE_Module_Base {
        scope = 2;
        scopeCurator = 2;
        displayName = "Effacer les données SSE";
        category = "COMSPEC_SSE_Tools";
        function = "comspec_sse_fnc_moduleClearData";
        icon = "\A3\ui_f\data\igui\cfg\simpletasks\types\danger_ca.paa";
        portrait = "\A3\ui_f\data\igui\cfg\simpletasks\types\danger_ca.paa";

        class ModuleDescription {
            description = "Supprime les données SSE de la cible (confirmation demandée en Zeus).";
            sync[] = {"Anything"};
        };
    };

    class COMSPEC_SSE_Module_ResetSite: COMSPEC_SSE_Module_Base {
        scope = 1;
        scopeCurator = 2;
        displayName = "Réinitialiser un site SSE";
        category = "COMSPEC_SSE_Tools";
        function = "comspec_sse_fnc_moduleResetSite";
        icon = "\A3\ui_f\data\igui\cfg\simpletasks\types\danger_ca.paa";
        portrait = "\A3\ui_f\data\igui\cfg\simpletasks\types\danger_ca.paa";

        class Attributes {
            class Radius {
                displayName = "Rayon";
                tooltip = "Toutes les entités SSE de ce rayon perdent leurs données.";
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
            description = "Retire les données SSE de toutes les entités du rayon, pour rejouer un site. Confirmation demandée en Zeus.";
            position = 1;
        };
    };

    class COMSPEC_SSE_Module_DebugInspector: COMSPEC_SSE_Module_Base {
        scope = 1;
        scopeCurator = 2;
        displayName = "Inspecteur SSE (debug)";
        category = "COMSPEC_SSE_Tools";
        function = "comspec_sse_fnc_moduleDebugInspector";
        icon = "\A3\ui_f\data\igui\cfg\simpletasks\types\target_ca.paa";
        portrait = "\A3\ui_f\data\igui\cfg\simpletasks\types\target_ca.paa";

        class Attributes {
            class DrawLinks {
                displayName = "Afficher les liens 3D";
                tooltip = "Trace les relations du graphe vers les autres entités.";
                property = "COMSPEC_SSE_Module_DebugInspector_DrawLinks";
                control = "Checkbox";
                expression = "_this setVariable ['DrawLinks',_value,true];";
                defaultValue = "true";
                typeName = "BOOL";
            };
        };

        class ModuleDescription {
            description = "Affiche UID, profil et état de l'entité survolée en Zeus, avec ses liens en 3D. Reposer le module pour le désactiver.";
        };
    };

    class COMSPEC_SSE_Module_DomexMark: COMSPEC_SSE_Module_Base {
        scope = 2;
        scopeCurator = 2;
        displayName = "Intelligence numérique (DOMEX)";
        category = "COMSPEC_SSE_Domex";
        function = "comspec_sse_fnc_moduleDomexMark";
        icon = "\A3\ui_f\data\igui\cfg\simpletasks\types\download_ca.paa";
        portrait = "\A3\ui_f\data\igui\cfg\simpletasks\types\download_ca.paa";

        class Attributes {
            class NodeId {
                displayName = "Identifiant";
                tooltip = "Ex. PC-KESTREL-04. Vide = identifiant automatique.";
                property = "COMSPEC_SSE_Module_DomexMark_NodeId";
                control = "Edit";
                expression = "_this setVariable ['NodeId',_value,true];";
                defaultValue = "''";
                typeName = "STRING";
            };
            class DeviceType {
                displayName = "Type de support";
                tooltip = "Nature du support numérique.";
                property = "COMSPEC_SSE_Module_DomexMark_DeviceType";
                control = "Combo";
                expression = "_this setVariable ['DeviceType',_value,true];";
                defaultValue = "'ordinateur'";
                typeName = "STRING";
                class values {
                    class V0 { name = "Ordinateur"; value = "ordinateur"; default = 1; };
                    class V1 { name = "Téléphone"; value = "telephone"; };
                    class V2 { name = "Tablette"; value = "tablette"; };
                    class V3 { name = "Radio numérique"; value = "radio_numerique"; };
                    class V4 { name = "Clé USB"; value = "cle_usb"; };
                    class V5 { name = "GPS"; value = "gps"; };
                };
            };
            class Owner {
                displayName = "Propriétaire apparent";
                tooltip = "Ce que le support laisse croire, pas forcément la vérité.";
                property = "COMSPEC_SSE_Module_DomexMark_Owner";
                control = "Edit";
                expression = "_this setVariable ['Owner',_value,true];";
                defaultValue = "''";
                typeName = "STRING";
            };
            class Organization {
                displayName = "Organisation";
                tooltip = "Groupe ou structure fictive.";
                property = "COMSPEC_SSE_Module_DomexMark_Organization";
                control = "Edit";
                expression = "_this setVariable ['Organization',_value,true];";
                defaultValue = "''";
                typeName = "STRING";
            };
            class Network {
                displayName = "Réseau fictif";
                tooltip = "Nom du réseau / SSID / canal.";
                property = "COMSPEC_SSE_Module_DomexMark_Network";
                control = "Edit";
                expression = "_this setVariable ['Network',_value,true];";
                defaultValue = "''";
                typeName = "STRING";
            };
            class Security {
                displayName = "Sécurité scénarisée";
                tooltip = "Plus élevée = accès plus long.";
                property = "COMSPEC_SSE_Module_DomexMark_Security";
                control = "Combo";
                expression = "_this setVariable ['Security',_value,true];";
                defaultValue = "'moyenne'";
                typeName = "STRING";
                class values {
                    class V0 { name = "Faible"; value = "faible"; };
                    class V1 { name = "Moyenne"; value = "moyenne"; default = 1; };
                    class V2 { name = "Élevée"; value = "elevee"; };
                };
            };
            class Profile {
                displayName = "Profil de contenu";
                tooltip = "Oriente les contenus proposés.";
                property = "COMSPEC_SSE_Module_DomexMark_Profile";
                control = "Combo";
                expression = "_this setVariable ['Profile',_value,true];";
                defaultValue = "'generique'";
                typeName = "STRING";
                class values {
                    class V0 { name = "Générique"; value = "generique"; default = 1; };
                    class V1 { name = "Logistique"; value = "logistique"; };
                    class V2 { name = "Commandement"; value = "commandement"; };
                    class V3 { name = "Personnel"; value = "personnel"; };
                    class V4 { name = "Radio / liaisons"; value = "radio"; };
                };
            };
            class Duration {
                displayName = "Durée d'accès (s)";
                tooltip = "Temps d'exploitation scénarisé, en secondes.";
                property = "COMSPEC_SSE_Module_DomexMark_Duration";
                control = "Combo";
                expression = "_this setVariable ['Duration',_value,true];";
                defaultValue = "180";
                typeName = "NUMBER";
                class values {
                    class V0 { name = "1 min"; value = 60; };
                    class V1 { name = "2 min"; value = 120; };
                    class V2 { name = "3 min (défaut)"; value = 180; default = 1; };
                    class V3 { name = "5 min"; value = 300; };
                    class V4 { name = "10 min"; value = 600; };
                    class V5 { name = "15 min"; value = 900; };
                };
            };
            class AccessRemote {
                displayName = "Accès distant scénarisé";
                tooltip = "Le laboratoire peut poursuivre l'exploitation à distance.";
                property = "COMSPEC_SSE_Module_DomexMark_AccessRemote";
                control = "Checkbox";
                expression = "_this setVariable ['AccessRemote',_value,true];";
                defaultValue = "false";
                typeName = "BOOL";
            };
            class PacketType {
                displayName = "Paquet — type";
                tooltip = "Premier renseignement contenu (optionnel).";
                property = "COMSPEC_SSE_Module_DomexMark_PacketType";
                control = "Combo";
                expression = "_this setVariable ['PacketType',_value,true];";
                defaultValue = "''";
                typeName = "STRING";
                class values {
                    class V0 { name = "(aucun)"; value = ""; default = 1; };
                    class V1 { name = "Message"; value = "message"; };
                    class V2 { name = "Document"; value = "document"; };
                    class V3 { name = "Coordonnée / point"; value = "coordinate"; };
                    class V4 { name = "Contact"; value = "contact"; };
                    class V5 { name = "Fréquence"; value = "frequency"; };
                };
            };
            class PacketText {
                displayName = "Paquet — texte";
                tooltip = "Renseignement scénarisé. Ce n'est pas une preuve.";
                property = "COMSPEC_SSE_Module_DomexMark_PacketText";
                control = "Edit";
                expression = "_this setVariable ['PacketText',_value,true];";
                defaultValue = "''";
                typeName = "STRING";
            };
            class PacketQuality {
                displayName = "Paquet — qualité";
                tooltip = "Un fragment ou un leurre devra être corroboré.";
                property = "COMSPEC_SSE_Module_DomexMark_PacketQuality";
                control = "Combo";
                expression = "_this setVariable ['PacketQuality',_value,true];";
                defaultValue = "'complet'";
                typeName = "STRING";
                class values {
                    class V0 { name = "Complet"; value = "complet"; default = 1; };
                    class V1 { name = "Fragment (à croiser)"; value = "fragment"; };
                    class V2 { name = "Peut être un leurre"; value = "leurre_possible"; };
                };
            };
            class PacketEntities {
                displayName = "Paquet — entités";
                tooltip = "Format : Nom | type (lieu, personne, organisation…).";
                property = "COMSPEC_SSE_Module_DomexMark_PacketEntities";
                control = "Edit";
                expression = "_this setVariable ['PacketEntities',_value,true];";
                defaultValue = "''";
                typeName = "STRING";
            };
        };

        class ModuleDescription {
            description = "Pose le contrat d'intelligence numérique sur l'objet (ordinateur, téléphone, radio…). Même schéma que les attributs Eden. Aucun moteur technique : le laboratoire lit ce que vous saisissez.";
            sync[] = {"Anything"};
        };
    };

    class COMSPEC_SSE_Module_DomexAddIntel: COMSPEC_SSE_Module_Base {
        scope = 1;
        scopeCurator = 2;
        displayName = "Ajouter un renseignement";
        category = "COMSPEC_SSE_Domex";
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
        displayName = "Fixer le palier d'accès";
        category = "COMSPEC_SSE_Domex";
        function = "comspec_sse_fnc_moduleDomexSetStage";
        icon = "\A3\ui_f\data\igui\cfg\simpletasks\types\use_ca.paa";
        portrait = "\A3\ui_f\data\igui\cfg\simpletasks\types\use_ca.paa";

        class Attributes {
            class Stage {
                displayName = "Palier";
                tooltip = "Palier appliqué au support synchronisé.";
                property = "COMSPEC_SSE_Module_DomexSetStage_Stage";
                control = "Combo";
                expression = "_this setVariable ['Stage',_value,true];";
                defaultValue = "'decouvert'";
                typeName = "STRING";
                class values {
                    class V0 { name = "Non identifié"; value = "non_identifie"; };
                    class V1 { name = "Découvert"; value = "decouvert"; default = 1; };
                    class V2 { name = "Accès en cours"; value = "acces_en_cours"; };
                    class V3 { name = "Accès établi"; value = "acces_etabli"; };
                    class V4 { name = "Exploité"; value = "exploite"; };
                };
            };
        };

        class ModuleDescription {
            description = "Change le palier d'accès du support. Au palier « accès établi », les contenus prévus pour ce palier rejoignent la file.";
            sync[] = {"Anything"};
        };
    };

    class COMSPEC_SSE_Module_DomexMapPoint: COMSPEC_SSE_Module_Base {
        scope = 2;
        scopeCurator = 2;
        displayName = "Poser un point carte";
        category = "COMSPEC_SSE_Domex";
        function = "comspec_sse_fnc_moduleDomexMapPoint";
        icon = "\A3\ui_f\data\igui\cfg\simpletasks\types\map_ca.paa";
        portrait = "\A3\ui_f\data\igui\cfg\simpletasks\types\map_ca.paa";

        class Attributes {
            class Label {
                displayName = "Libellé";
                tooltip = "Ce que le bureau verra. Vide en Zeus = formulaire.";
                property = "COMSPEC_SSE_Module_DomexMapPoint_Label";
                control = "Edit";
                expression = "_this setVariable ['Label',_value,true];";
                defaultValue = "''";
                typeName = "STRING";
            };
        };

        class ModuleDescription {
            description = "Pose un point de renseignement sur la carte du bureau, invisible sur la carte des joueurs.";
            position = 1;
        };
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
