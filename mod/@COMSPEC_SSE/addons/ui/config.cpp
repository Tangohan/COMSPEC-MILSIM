#include "script_component.hpp"

class CfgPatches {
    class comspec_sse_ui {
        name = "COMSPEC SSE - UI";
        units[] = {};
        weapons[] = {};
        requiredVersion = REQUIRED_VERSION;
        requiredAddons[] = {"comspec_sse_core", "A3_Ui_F"};
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
            class fillResultDialog {};
            class getDocumentChrome {};
            class applyPaperStyle {};
            class resultConsult {};
            class resultTransmit {};
            class uiSetRecord {};
            class uiGetRecord {};
            class uiOpenTerminal {};
            class uiOpenSeekHost {};
            class uiDisplayCtx {};
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
            class uiGrid {};
            class uiForm {};
            class uiFormConfirm {};
        };
    };
};

// Classes vanilla déclarées une seule fois : base.hpp en dérive les contrôles COMSPEC_SSE_Rsc*.
class RscText;
class RscButton;
class RscStructuredText;
class RscListBox;
class RscEdit;
class RscCombo;
class RscCheckBox;
class RscXSliderH;
class RscControlsGroup;

#include "ui_macros.hpp"
#include "dialogs\base.hpp"
#include "dialogs\resultDialog.hpp"
#include "dialogs\screens.hpp"
#include "dialogs\formDialog.hpp"
