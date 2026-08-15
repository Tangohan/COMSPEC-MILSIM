#include "script_component.hpp"

class CfgPatches
{
    class Iceman_ATAK_HAHO_HALO
    {
        name = "Iceman ATAK HAHO HALO";
        author = "Cole / Codex";
        requiredVersion = 2.14;
        requiredAddons[] = {"cba_main", "cTab", "ctab_core", "BCE_Core", "BCE_cTab"};
        units[] = {};
        weapons[] = {};
    };
};

class CfgFunctions
{
    class Iceman
    {
        class ATAK_HAHO_HALO
        {
            file = "\ATAK_HAHO_HALO\functions";
            class jump_addWaypoint {};
            class jump_calculatePlan {};
            class jump_clear {};
            class jump_clearWaypoints {};
            class jump_draw {};
            class jump_formatTime {};
            class jump_getState {};
            class jump_initPage {};
            class jump_installMapHandlers {};
            class jump_onMapClick {};
            class jump_onOpened {};
            class jump_plan {};
            class jump_removeWaypoint {};
            class jump_selectMode {};
            class jump_selectTab {};
            class jump_setMode {};
            class jump_setPoint {};
            class jump_updatePanel {};
        };
    };
};

class Extended_PreInit_EventHandlers
{
    class Iceman_ATAK_HAHO_HALO
    {
        init = "call compile preprocessFileLineNumbers '\ATAK_HAHO_HALO\XEH_preInit.sqf'";
    };
};

class Extended_PostInit_EventHandlers
{
    class Iceman_ATAK_HAHO_HALO
    {
        init = "call compile preprocessFileLineNumbers '\ATAK_HAHO_HALO\XEH_postInitClient.sqf'";
    };
};

class ATAK_Buttons
{
    class Iceman_HAHO_HALO_Menu
    {
        onLoad = "Iceman_fnc_jump_updatePanel";
        clickEvents[] = {"Iceman_fnc_jump_plan", "Iceman_fnc_jump_clear"};
    };
};

class ATAK_APPs
{
    class message;
    class settings: message
    {
        text = "<t size='1'>HAHO/HALO</t>";
        textureNoShortcut = "\ATAK_HAHO_HALO\data\haho_halo_icon_ca.paa";
        onButtonClick = "[_this # 0] call BCE_fnc_ATAK_ChangeTool";

        class Menu_Property
        {
            ORDER = 8;
            PAGE_CTRL = "ATAK_Message";
            Opened = "Iceman_fnc_jump_onOpened";
            ATAK_Buttons = "Iceman_HAHO_HALO_Menu";
        };
    };
};

class RscTitles
{
    class ATAK_APPs
    {
        class message;
        class settings: message
        {
            text = "<t size='1'>HAHO/HALO</t>";
            textureNoShortcut = "\ATAK_HAHO_HALO\data\haho_halo_icon_ca.paa";
            onButtonClick = "[_this # 0] call BCE_fnc_ATAK_ChangeTool";

            class Menu_Property
            {
                ORDER = 8;
                PAGE_CTRL = "ATAK_Message";
                Opened = "Iceman_fnc_jump_onOpened";
                ATAK_Buttons = "Iceman_HAHO_HALO_Menu";
            };
        };
    };
};
