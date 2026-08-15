class CfgPatches
{
    class Iceman_ATAK_DroneOps
    {
        name = "Iceman ATAK Drone Ops";
        author = "Cole / Codex";
        requiredVersion = 2.14;
        requiredAddons[] = {"cba_main", "cba_common", "cTab", "ctab_core", "BCE_Core", "BCE_cTab", "ace_interact_menu"};
        units[] = {};
        weapons[] = {};
    };
};

class CfgFunctions
{
    class Iceman
    {
        class ATAK_DroneOps
        {
            file = "\ATAK_DroneOps\functions";
            class drone_applyTask {};
            class drone_canControl {};
            class drone_connect {};
            class drone_findControls {};
            class drone_getState {};
            class drone_gridToPos {};
            class drone_draw {};
            class drone_installActions {};
            class drone_installDrawHooks {};
            class drone_installMapHandlers {};
            class drone_installOpenMapHandlers {};
            class drone_isSupported {};
            class drone_markContact {};
            class drone_onMapClick {};
            class drone_onOpened {};
            class drone_posToGrid {};
            class drone_readUi {};
            class drone_registerFeedLocal {};
            class drone_scanForScrollActions {};
            class drone_scanTick {};
            class drone_selectTarget {};
            class drone_sendTask {};
            class drone_startup {};
            class drone_trackTarget {};
            class drone_tick {};
            class drone_updatePanel {};
        };
    };
};

class Extended_PostInit_EventHandlers
{
    class Iceman_ATAK_DroneOps
    {
        init = "call compile preprocessFileLineNumbers '\ATAK_DroneOps\XEH_postInitClient.sqf'";
    };
};

#include "ui\DroneOpsPage.hpp"

class ATAK_APPs
{
    class message;
    class DroneOps: message
    {
        class Menu_Property
        {
            ORDER = 7;
            PAGE_CTRL = "Iceman_ATAK_DroneOps";
            Opened = "Iceman_fnc_drone_onOpened";
        };

        onButtonClick = "[_this # 0] call BCE_fnc_ATAK_ChangeTool;";
        text = "<t size='1'>Drone Ops</t>";
        textureNoShortcut = "\A3\ui_f\data\map\markers\nato\b_uav.paa";
    };
};

class RscTitles
{
    class ATAK_APPs
    {
        class message;
        class DroneOps: message
        {
            class Menu_Property
            {
                ORDER = 7;
                PAGE_CTRL = "Iceman_ATAK_DroneOps";
                Opened = "Iceman_fnc_drone_onOpened";
            };

            onButtonClick = "[_this # 0] call BCE_fnc_ATAK_ChangeTool;";
            text = "<t size='1'>Drone Ops</t>";
            textureNoShortcut = "\A3\ui_f\data\map\markers\nato\b_uav.paa";
        };
    };
};
