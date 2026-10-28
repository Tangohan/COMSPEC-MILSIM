#include "script_component.hpp"

class CfgPatches {
    class comspec_sse_generator {
        name = "COMSPEC SSE - Generator";
        units[] = {};
        weapons[] = {};
        requiredVersion = REQUIRED_VERSION;
        requiredAddons[] = {"comspec_sse_core", "comspec_atak_native_main"};
        author = "COMSPEC";
        VERSION_CONFIG;
    };
};

class CfgFunctions {
    class comspec_sse {
        tag = "comspec_sse";

        class generator {
            file = "z\comspec_sse\addons\generator\functions";
            class generateData {};
            class applyAuthoredIdentity {};
            class applyAuthoredContent {};
            class generatePerson {};
            class generatePhone {};
            class generateComputer {};
            class generateDocument {};
            class generateVehicle {};
            class generateRadio {};
            class generateWeapon {};
            class generateBuilding {};
            class generateSite {};
            class generateCluster {};
            class pickFromSeed {};
            class ensureGenerated {};
            class queueEntityJobs {};
            class resolveEntityType {};
            class resolveProfile {};
            class resolveComplexity {};
            class getNarrativePools {};
            class getThemePack {};
            class seededMapGrid {};
            class createModel {};
            class registerBuiltinModels {};
            class saveModel {};
            class loadModel {};
            class listModels {};
            class deleteModel {};
            class exportModel {};
            class importModel {};
            class modelFromEntity {};
            class applyModel {};
            class datasetFalcon {};
            class registerDatasets {};
            class listDatasets {};
            class loadDataset {};
            class applyDatasetRole {};
            class applyDataset {};
            // Sites et kits préfabriqués (serveur).
            class compositionData {};
            class spawnComposition {};
            class siteProcess {};
            class siteDossier {};
            class spawnSiteNetwork {};
            class seedEvidence {};
            class spawnKitBox {};
            class siteRequest {};
        };
    };
};

// Sites et kits : les modules Zeus demandent la création au serveur (liste blanche d'Overwatch).
class CfgRemoteExec {
    class Functions {
        class comspec_sse_fnc_siteRequest { allowedTargets = 2; jip = 0; };
    };
};

class Extended_PreInit_EventHandlers {
    class comspec_sse_generator {
        init = "call compile preprocessFileLineNumbers 'z\comspec_sse\addons\generator\XEH_preInit.sqf'";
    };
};
