#include "script_component.hpp"

class CfgPatches {
    class comspec_sse_ui {
        name = "COMSPEC SSE - UI";
        units[] = {};
        weapons[] = {};
        requiredVersion = REQUIRED_VERSION;
        requiredAddons[] = {"comspec_sse_core"};
        author = "COMSPEC";
        VERSION_CONFIG;
    };
};

class CfgFunctions {
    class comspec_sse {
        tag = "comspec_sse";
        class ui {
            file = "z\comspec_sse\addons\ui\functions";
            class showResult {};
            class resultConsult {};
            class resultTransmit {};
            class uiSetRecord {};
            class uiGetRecord {};
            class uiOpenTerminal {};
            class uiOpenScreen {};
            class uiOnLoad {};
            class uiRefresh {};
            class uiFillTerminal {};
            class uiFillDigital {};
            class uiDigitalTab {};
            class uiFillSite {};
            class uiSiteTriage {};
            class uiFillGraph {};
            class uiGraphPivot {};
            class uiFillEvidence {};
            class uiBagSelected {};
            class uiFillMission {};
            class uiMissionFilter {};
            class uiFillZeus {};
            class uiZeusGenerate {};
            class uiZeusLink {};
            class uiZeusExport {};
            class uiZeusAAR {};
            class uiTransmitRecord {};
        };
    };
};

#include "dialogs\resultDialog.hpp"
#include "dialogs\screens.hpp"
