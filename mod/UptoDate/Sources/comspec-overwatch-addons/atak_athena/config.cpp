class CfgPatches
{
    class comspec_overwatch_atak_athena
    {
        name = "COMSPEC Overwatch — Athena (cTab)";
        author = "COMSPEC";
        requiredVersion = 2.14;
        requiredAddons[] = {
            "cba_main",
            "cba_xeh",
            "cTab",
            "ctab_core",
            "BCE_Core",
            "BCE_cTab",
            "comspec_overwatch_connect"
        };
        units[] = {};
        weapons[] = {};
        version = 1.0.32;
        versionStr = "1.0.32";
        versionAr[] = {1, 0, 32};
    };
};

class CfgFunctions
{
    class comspec_overwatch_atak_athena
    {
        tag = "comspec_overwatch_atak_athena";
        class atak_athena
        {
            file = "z\comspec_overwatch\addons\atak_athena\functions";
            class athena_onOpened {};
            class athena_updatePanel {};
            class athena_sendQuick {};
            class athena_openTablet {};
            class athena_selectInbox {};
            class athena_selectNotif {};
            class athena_pushNotification {};
            class athena_selectTab {};
            class athena_sendPhoto {};
            class athena_sendSeekData {};
            class athena_collectLocalPhotos {};
        class athena_rememberLocalPhoto {};
            class athena_setPanelFeedback {};
            class athena_bridgeIcemanAlert {};
            class athena_bridgeIcemanBda {};
            class athena_bridgeIcemanPhoto {};
            class athena_bridgeIcemanGroup {};
            class athena_installHqContact {};
            class athena_sendHqMessage {};
            class athena_archiveMpMessage {};
            class athena_bridgeComspecSent {};
            class athena_onOrderReceived {};
            class athena_syncIcemanOrderAck {};
            class athena_refresh {};
            class athena_bridgeWeather {};
            class athena_bridgeDroneContacts {};
            class athena_bridgeCtabMarkers {};
            class athena_bridgeRoute {};
            class athena_bridgeJump {};
            class athena_bridgeVideoFeeds {};
            class athena_snapshotVideoFeed {};
            class athena_installDesktopShortcut {};
            class athena_createWebMarker {};
            class athena_showLinkDialog {};
            class athena_showPhoneConnect {};
            class athena_openAtakApp {};
            class athena_openFeature {};
            class athena_pollIcemanPhotos {};
            class athena_onVibrate {};
            class athena_onHelmetMediaRequest {};
            class athena_onNotify {};
            class athena_statusOnOpened {};
            class athena_updateStatus {};
            class athena_openStatus {};
            class athena_soundOnOpened {};
            class athena_updateSound {};
            class athena_soundAction {};
            class athena_openSound {};
            class athena_briefingOnOpened {};
            class athena_applyBriefingSlide {};
            class athena_openBriefing {};
            class athena_bdaOnOpened {};
            class athena_biiOnOpened {};
            class athena_openBiiTab {};
            class athena_noteOnOpened {};
            class athena_updateNote {};
            class athena_openNote {};
            class athena_taskOnOpened {};
            class athena_updateTask {};
            class athena_taskSelect {};
            class athena_taskRespond {};
            class athena_taskSyncButtons {};
            class athena_syncOrdersToGroupChat {};
            class athena_openTask {};
            class athena_noteOnOpened {};
            class athena_updateNote {};
            class athena_openNote {};
        };
    };
    // Workaround BCE: Check_Layout uses undefined _line (Compat updateInterface)
    // + getMarkerColor: cache / RGBA sûrs pour lbSetPictureColor (display ATAK)
    class BCE
    {
        class ATAK
        {
            class ATAK_Check_Layout
            {
                file = "z\comspec_overwatch\addons\atak_athena\functions\fn_ATAK_Check_Layout.sqf";
                recompile = 1;
            };
        };
        class Components
        {
            class getMarkerColor
            {
                file = "z\comspec_overwatch\addons\atak_athena\functions\fn_getMarkerColor.sqf";
            };
        };
    };
};

class Extended_PreInit_EventHandlers
{
    class comspec_overwatch_atak_athena
    {
        init = "call compile preprocessFileLineNumbers '\z\comspec_overwatch\addons\atak_athena\XEH_preInit.sqf'";
    };
};

class Extended_PostInit_EventHandlers
{
    class comspec_overwatch_atak_athena
    {
        clientInit = "call compile preprocessFileLineNumbers '\z\comspec_overwatch\addons\atak_athena\XEH_postInitClient.sqf'";
    };
};

// Forward decls once only — Arma treats `class X;` as a definition; repeating it
// across included HPPs yields ".X: Member already defined."
class ATAK_Message;
class BCE_RscButtonMenu;
class RscStructuredText;
class RscListBox;
class RscText;
class RscEdit;
class RscCombo;
class RscPictureKeepAspect;
class RscControlsGroup;

#include "ui\athena_page.hpp"
#include "ui\status_page.hpp"
#include "ui\sound_page.hpp"
#include "ui\briefing_page.hpp"
#include "ui\bda_host_page.hpp"
#include "ui\bii_page.hpp"
#include "ui\note_page.hpp"
#include "ui\task_page.hpp"
#include "ui\note_page.hpp"

class ATAK_APPs
{
    class message;
    class Athena: message
    {
        text = "<t size='1'>Athena</t>";
        textureNoShortcut = "a3\ui_f\data\gui\cfg\communicationmenu\instructor_ca.paa";
        onButtonClick = "[_this # 0] call BCE_fnc_ATAK_ChangeTool";
        class Menu_Property
        {
            ORDER = 3.5;
            PAGE_CTRL = "COMSPEC_ATAK_Athena";
            Opened = "comspec_overwatch_atak_athena_fnc_athena_onOpened";
        };
    };
    class AtakTask: message
    {
        text = "<t size='1'>TASK</t>";
        textureNoShortcut = "\A3\ui_f\data\igui\cfg\simpletasks\types\documents_ca.paa";
        onButtonClick = "[_this # 0] call BCE_fnc_ATAK_ChangeTool";
        class Menu_Property
        {
            ORDER = 1.15;
            PAGE_CTRL = "COMSPEC_ATAK_Task";
            Opened = "comspec_overwatch_atak_athena_fnc_athena_taskOnOpened";
        };
    };
    class AtakStatus: message
    {
        text = "<t size='1'>État ATAK</t>";
        textureNoShortcut = "\A3\ui_f\data\igui\cfg\simpletasks\types\Radio_ca.paa";
        onButtonClick = "[_this # 0] call BCE_fnc_ATAK_ChangeTool";
        class Menu_Property
        {
            ORDER = 3.6;
            PAGE_CTRL = "COMSPEC_ATAK_Status";
            Opened = "comspec_overwatch_atak_athena_fnc_athena_statusOnOpened";
        };
    };
    class AtakSound: message
    {
        text = "<t size='1'>Sons</t>";
        textureNoShortcut = "\A3\ui_f\data\gui\cfg\communicationmenu\call_ca.paa";
        onButtonClick = "[_this # 0] call BCE_fnc_ATAK_ChangeTool";
        class Menu_Property
        {
            ORDER = 3.7;
            PAGE_CTRL = "COMSPEC_ATAK_Sound";
            Opened = "comspec_overwatch_atak_athena_fnc_athena_soundOnOpened";
        };
    };
    class AtakBriefing: message
    {
        text = "<t size='1'>Briefing</t>";
        textureNoShortcut = "a3\ui_f\data\gui\cfg\communicationmenu\instructor_ca.paa";
        onButtonClick = "[_this # 0] call BCE_fnc_ATAK_ChangeTool";
        class Menu_Property
        {
            ORDER = 3.4;
            PAGE_CTRL = "COMSPEC_ATAK_Briefing";
            Opened = "comspec_overwatch_atak_athena_fnc_athena_briefingOnOpened";
        };
    };
    class BII_Identifi: message
    {
        text = "<t size='1'>BII-10</t>";
        textureNoShortcut = "a3\ui_f\data\igui\cfg\holdactions\holdaction_search_ca.paa";
        onButtonClick = "[_this # 0] call BCE_fnc_ATAK_ChangeTool";
        class Menu_Property
        {
            ORDER = 3.55;
            PAGE_CTRL = "COMSPEC_ATAK_BII";
            Opened = "comspec_overwatch_atak_athena_fnc_athena_biiOnOpened";
        };
    };
    // Menu dédié des fiches de renseignement : ouvre un rédacteur plein cadre.
    class AtakNote: message
    {
        text = "<t size='1'>RENS</t>";
        textureNoShortcut = "\A3\ui_f\data\igui\cfg\simpletasks\types\intel_ca.paa";
        onButtonClick = "[_this # 0] call BCE_fnc_ATAK_ChangeTool";
        class Menu_Property
        {
            ORDER = 1.16;
            PAGE_CTRL = "COMSPEC_ATAK_Note";
            Opened = "comspec_overwatch_atak_athena_fnc_athena_noteOnOpened";
        };
    };
    // Stub BCE BDA_Report : PAGE_CTRL/Opened vides → erreur "Opened function...". On le répare.
    class BDA_Report: message
    {
        text = "<t size='1'>BDA Report</t>";
        textureNoShortcut = "a3\ui_f\data\igui\cfg\holdactions\holdaction_search_ca.paa";
        onButtonClick = "[_this # 0] call BCE_fnc_ATAK_ChangeTool";
        class Menu_Property
        {
            ORDER = 7;
            PAGE_CTRL = "COMSPEC_ATAK_BdaHost";
            Opened = "comspec_overwatch_atak_athena_fnc_athena_bdaOnOpened";
        };
    };
};

class RscTitles
{
    class ATAK_APPs
    {
        class message;
        class Athena: message
        {
            text = "<t size='1'>Athena</t>";
            textureNoShortcut = "a3\ui_f\data\gui\cfg\communicationmenu\instructor_ca.paa";
            onButtonClick = "[_this # 0] call BCE_fnc_ATAK_ChangeTool";
            class Menu_Property
            {
                ORDER = 3.5;
                PAGE_CTRL = "COMSPEC_ATAK_Athena";
                Opened = "comspec_overwatch_atak_athena_fnc_athena_onOpened";
            };
        };
        class AtakTask: message
        {
            text = "<t size='1'>TASK</t>";
            textureNoShortcut = "\A3\ui_f\data\igui\cfg\simpletasks\types\documents_ca.paa";
            onButtonClick = "[_this # 0] call BCE_fnc_ATAK_ChangeTool";
            class Menu_Property
            {
                ORDER = 1.15;
                PAGE_CTRL = "COMSPEC_ATAK_Task";
                Opened = "comspec_overwatch_atak_athena_fnc_athena_taskOnOpened";
            };
        };
        class AtakStatus: message
        {
            text = "<t size='1'>État ATAK</t>";
            textureNoShortcut = "\A3\ui_f\data\igui\cfg\simpletasks\types\Radio_ca.paa";
            onButtonClick = "[_this # 0] call BCE_fnc_ATAK_ChangeTool";
            class Menu_Property
            {
                ORDER = 3.6;
                PAGE_CTRL = "COMSPEC_ATAK_Status";
                Opened = "comspec_overwatch_atak_athena_fnc_athena_statusOnOpened";
            };
        };
        class AtakSound: message
        {
            text = "<t size='1'>Sons</t>";
            textureNoShortcut = "\A3\ui_f\data\gui\cfg\communicationmenu\call_ca.paa";
            onButtonClick = "[_this # 0] call BCE_fnc_ATAK_ChangeTool";
            class Menu_Property
            {
                ORDER = 3.7;
                PAGE_CTRL = "COMSPEC_ATAK_Sound";
                Opened = "comspec_overwatch_atak_athena_fnc_athena_soundOnOpened";
            };
        };
        class AtakBriefing: message
        {
            text = "<t size='1'>Briefing</t>";
            textureNoShortcut = "a3\ui_f\data\gui\cfg\communicationmenu\instructor_ca.paa";
            onButtonClick = "[_this # 0] call BCE_fnc_ATAK_ChangeTool";
            class Menu_Property
            {
                ORDER = 3.4;
                PAGE_CTRL = "COMSPEC_ATAK_Briefing";
                Opened = "comspec_overwatch_atak_athena_fnc_athena_briefingOnOpened";
            };
        };
        class BII_Identifi: message
        {
            text = "<t size='1'>BII-10</t>";
            textureNoShortcut = "a3\ui_f\data\igui\cfg\holdactions\holdaction_search_ca.paa";
            onButtonClick = "[_this # 0] call BCE_fnc_ATAK_ChangeTool";
            class Menu_Property
            {
                ORDER = 3.55;
                PAGE_CTRL = "COMSPEC_ATAK_BII";
                Opened = "comspec_overwatch_atak_athena_fnc_athena_biiOnOpened";
            };
        };
        class AtakNote: message
        {
            text = "<t size='1'>RENS</t>";
            textureNoShortcut = "\A3\ui_f\data\igui\cfg\simpletasks\types\intel_ca.paa";
            onButtonClick = "[_this # 0] call BCE_fnc_ATAK_ChangeTool";
            class Menu_Property
            {
                ORDER = 1.16;
                PAGE_CTRL = "COMSPEC_ATAK_Note";
                Opened = "comspec_overwatch_atak_athena_fnc_athena_noteOnOpened";
            };
        };
        class BDA_Report: message
        {
            text = "<t size='1'>BDA Report</t>";
            textureNoShortcut = "a3\ui_f\data\igui\cfg\holdactions\holdaction_search_ca.paa";
            onButtonClick = "[_this # 0] call BCE_fnc_ATAK_ChangeTool";
            class Menu_Property
            {
                ORDER = 7;
                PAGE_CTRL = "COMSPEC_ATAK_BdaHost";
                Opened = "comspec_overwatch_atak_athena_fnc_athena_bdaOnOpened";
            };
        };
    };
};
