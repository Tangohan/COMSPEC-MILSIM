#include "script_component.hpp"

class CfgPatches {
    class comspec_sse_interaction {
        name = "COMSPEC SSE - ACE Interaction";
        units[] = {};
        weapons[] = {};
        requiredVersion = REQUIRED_VERSION;
        requiredAddons[] = {
            "comspec_sse_core",
            "comspec_sse_generator",
            "comspec_sse_evidence",
            "comspec_sse_ui",
            "comspec_sse_network",
            "comspec_sse_intel",
            "ace_interact_menu",
            "ace_common"
        };
        author = "COMSPEC";
        VERSION_CONFIG;
    };
};

class CfgFunctions {
    class comspec_sse {
        tag = "comspec_sse";

        class interaction {
            file = "z\comspec_sse\addons\interaction\functions";
            class initACE {};
            class installEntityAceMenus {};
            class acePadAction {};
            class aceWrapMenuChildren {};
            class canInspect {};
            class doInspect {};
            class doPhotograph {};
            class doSearch {};
            class doCollect {};
            class doMarkExploited {};
            class doReadDocuments {};
            class doExploitRadio {};
            class equipSseKit {};
            class addJournalEntry {};
            class getJournal {};
            class openJournal {};
            class progressAction {};
        };
    };
};

#include "CfgEventHandlers.hpp"
