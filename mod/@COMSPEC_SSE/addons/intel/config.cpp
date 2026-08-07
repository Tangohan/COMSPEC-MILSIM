#include "script_component.hpp"

class CfgPatches {
    class comspec_sse_intel {
        name = "COMSPEC SSE - Intel Engine";
        units[] = {};
        weapons[] = {};
        requiredVersion = REQUIRED_VERSION;
        requiredAddons[] = {
            "comspec_sse_core",
            "comspec_sse_generator",
            "cba_main",
            "cba_events",
            "cba_settings"
        };
        author = "COMSPEC";
        VERSION_CONFIG;
    };
};

class CfgFunctions {
    class comspec_sse {
        tag = "comspec_sse";

        class intel {
            file = "z\comspec_sse\addons\intel\functions";
            class initIntelSettings {};
            class emitEvent {};
            class createIntelDatum {};
            class attachIntelLayers {};
            class getExploitationLevel {};
            class setExploitationLevel {};
            class advanceExploitation {};
            class getRevealedIntel {};
            class triageSite {};
            class confidenceLabel {};
            class addLogicalEntity {};
            class listLogicalEntities {};
            class pivotSearch {};
            class fuseIntel {};
            class deduplicateIntel {};
            class buildTimeline {};
            class extractGeopoints {};
            class createMapMarkers {};
            class applyEvidenceState {};
            class rollAccessState {};
            class revealHiddenCache {};
            class generateOpticalMedia {};
            class generateTechint {};
            class enrichBiometrics {};
            class matchBiometric {};
            class registerActionHistory {};
            class getActionHistory {};
            class getOperatorSkill {};
            class checkTimeSensitivity {};
            class registerZeusHook {};
            class fireZeusHook {};
            class isDiscovered {};
            class getDiscoveryState {};
            class setDiscoveryState {};
            class siteCompleteness {};
            class listSiteEntities {};
            class exportMissionGraph {};
            class importMissionGraph {};
            class loadScenarioPack {};
            class generateFromBrief {};
            class getPlayerKnownView {};
            class registerModClasses {};
            class bagEvidence {};
            class makeEvidenceLabel {};
            class afterActionReport {};
            class trainingFeedback {};
            class sandboxGenerateSite {};
            class markExploitedLocal {};
        };
    };
};

#include "CfgEventHandlers.hpp"
