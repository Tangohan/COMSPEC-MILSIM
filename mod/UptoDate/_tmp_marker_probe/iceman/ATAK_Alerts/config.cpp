#include "script_component.hpp"

class CfgPatches
{
    class Iceman_ATAK_Alerts
    {
        name = "Iceman ATAK Reports";
        author = "Cole / Codex";
        requiredVersion = 2.14;
        requiredAddons[] = {"cba_main", "cTab", "ctab_core", "BCE_Core", "BCE_cTab", "Iceman_ATAK_BDA"};
        units[] = {};
        weapons[] = {};
    };
};

class CfgFunctions
{
    class Iceman
    {
        class ATAK_Alerts
        {
            file = "\ATAK_Alerts\functions";
            class alerts_clearReports {};
            class alerts_clearForm {};
            class alerts_initButtons {};
            class alerts_installAppFilter {};
            class alerts_locateSelected {};
            class alerts_onOpened {};
            class alerts_panicOpened {};
            class alerts_receive {};
            class alerts_reportTypeChanged {};
            class alerts_send {};
            class alerts_sendFrago {};
            class alerts_sendPanic {};
            class alerts_sendQuick {};
            class alerts_selectReport {};
            class alerts_selectTab {};
            class alerts_submitReport {};
            class alerts_ticAlert {};
            class alerts_updatePanel {};
            class group_onOpened {};
            class group_receive {};
            class group_selectMessage {};
            class group_sendMessage {};
            class group_updatePanel {};
            class panic_locateSelected {};
            class panic_selectReport {};
            class panic_updatePanel {};
        };
    };
};

class Extended_PreInit_EventHandlers
{
    class Iceman_ATAK_Alerts
    {
        init = "call compile preprocessFileLineNumbers '\ATAK_Alerts\XEH_preInit.sqf'";
    };
};

class Extended_PostInit_EventHandlers
{
    class Iceman_ATAK_Alerts
    {
        init = "call compile preprocessFileLineNumbers '\ATAK_Alerts\XEH_postInitClient.sqf'";
    };
};

#include "ui\ReportsPage.hpp"

class ATAK_Buttons
{
    class Iceman_Reports_Menu
    {
        onLoad = "Iceman_fnc_alerts_initButtons";
        clickEvents[] = {"Iceman_fnc_alerts_locateSelected", "Iceman_fnc_alerts_clearReports"};
    };
    class Iceman_Alert_Menu
    {
        onLoad = "Iceman_fnc_panic_updatePanel";
        clickEvents[] = {"Iceman_fnc_alerts_sendPanic", "Iceman_fnc_panic_locateSelected"};
    };
    class Iceman_Group_Menu
    {
        onLoad = "Iceman_fnc_group_updatePanel";
        clickEvents[] = {"Iceman_fnc_group_sendMessage"};
    };
};

class cTab_RscControlsGroup;
class RscStructuredText;
class Iceman_ReportsScrollGroup: cTab_RscControlsGroup
{
    x = 0;
    y = 0;
    w = 1;
    h = 1;
    class VScrollbar
    {
        width = 0.021;
        autoScrollEnabled = 1;
        autoScrollSpeed = -1;
        autoScrollDelay = 5;
        autoScrollRewind = 0;
    };
    class HScrollbar
    {
        height = 0;
    };
};
class Iceman_ReportsDetailText: RscStructuredText
{
    x = 0;
    y = 0;
    w = 1;
    h = 1;
};

class ATAK_APPs
{
    class message;
    class Reports: message
    {
        text = "<t size='1'>Reports</t>";
        textureNoShortcut = "a3\ui_f\data\igui\cfg\holdactions\holdaction_search_ca.paa";
        onButtonClick = "[_this # 0] call BCE_fnc_ATAK_ChangeTool";

        class Menu_Property
        {
            ORDER = 6.5;
            PAGE_CTRL = "Iceman_ATAK_Reports";
            Opened = "Iceman_fnc_alerts_onOpened";
            ATAK_Buttons = "Iceman_Reports_Menu";
        };
    };
    class Group: message
    {
        text = "<t size='1'>Groups</t>";
        textureNoShortcut = "a3\3den\data\displays\display3den\panelright\modegroups_ca.paa";
        onButtonClick = "[_this # 0] call BCE_fnc_ATAK_ChangeTool";

        class Menu_Property
        {
            ORDER = 4;
            PAGE_CTRL = "ATAK_Message";
            Opened = "Iceman_fnc_group_onOpened";
            ATAK_Buttons = "Iceman_Group_Menu";
        };
    };
    class Alerts: message
    {
        text = "<t size='1'>Alert</t>";
        textureNoShortcut = "\z\BCE\addons\Core\data\explosion.paa";
        onButtonClick = "[_this # 0] call BCE_fnc_ATAK_ChangeTool";

        class Menu_Property
        {
            ORDER = 6.6;
            PAGE_CTRL = "ATAK_Message";
            Opened = "Iceman_fnc_alerts_panicOpened";
            ATAK_Buttons = "Iceman_Alert_Menu";
        };
    };
};

class RscTitles
{
    class ATAK_APPs
    {
        class message;
        class Reports: message
        {
            text = "<t size='1'>Reports</t>";
            textureNoShortcut = "a3\ui_f\data\igui\cfg\holdactions\holdaction_search_ca.paa";
            onButtonClick = "[_this # 0] call BCE_fnc_ATAK_ChangeTool";

            class Menu_Property
            {
                ORDER = 6.5;
                PAGE_CTRL = "Iceman_ATAK_Reports";
                Opened = "Iceman_fnc_alerts_onOpened";
                ATAK_Buttons = "Iceman_Reports_Menu";
            };
        };
        class Group: message
        {
            text = "<t size='1'>Groups</t>";
            textureNoShortcut = "a3\3den\data\displays\display3den\panelright\modegroups_ca.paa";
            onButtonClick = "[_this # 0] call BCE_fnc_ATAK_ChangeTool";

            class Menu_Property
            {
                ORDER = 4;
                PAGE_CTRL = "ATAK_Message";
                Opened = "Iceman_fnc_group_onOpened";
                ATAK_Buttons = "Iceman_Group_Menu";
            };
        };
        class Alerts: message
        {
            text = "<t size='1'>Alert</t>";
            textureNoShortcut = "\z\BCE\addons\Core\data\explosion.paa";
            onButtonClick = "[_this # 0] call BCE_fnc_ATAK_ChangeTool";

            class Menu_Property
            {
                ORDER = 6.6;
                PAGE_CTRL = "ATAK_Message";
                Opened = "Iceman_fnc_alerts_panicOpened";
                ATAK_Buttons = "Iceman_Alert_Menu";
            };
        };
    };
};
