#include "BIS_AddonInfo.hpp"
#include "script_component.hpp"

class CfgPatches
{
    class Iceman_ATAK_Route
    {
        name = "Iceman ATAK Route";
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
        class ATAK_Route
        {
            file = "\ATAK_Route\functions";
            class route_addWaypoint {};
            class route_addWaypointFromInput {};
            class route_clear {};
            class route_clearWaypoints {};
            class route_draw {};
            class route_findConcealedPath {};
            class route_findPath {};
            class route_formatEta {};
            class route_getState {};
            class route_getInfoLines {};
            class route_initButtons {};
            class route_gridToPos {};
            class route_posToGrid {};
            class route_installDrawHooks {};
            class route_installMapHandlers {};
            class route_installOpenMapHandlers {};
            class route_measureRemaining {};
            class route_onMapClick {};
            class route_onOpened {};
            class route_planFromInputs {};
            class route_removeWaypoint {};
            class route_selectMode {};
            class route_selectTab {};
            class route_setMot {};
            class route_setPoint {};
            class route_startNavigation {};
            class route_stopNavigation {};
            class route_tickNavigation {};
            class route_updatePanel {};
            class route_updateMiniInfo {};
        };
    };
};

class Extended_PreInit_EventHandlers
{
    class Iceman_ATAK_Route
    {
        init = "call compile preprocessFileLineNumbers '\ATAK_Route\XEH_preInit.sqf'";
    };
};

class Extended_PostInit_EventHandlers
{
    class Iceman_ATAK_Route
    {
        init = "call compile preprocessFileLineNumbers '\ATAK_Route\XEH_postInitClient.sqf'";
    };
};

#include "ui\RoutePage.hpp"

class ATAK_Buttons
{
    class Iceman_Route_Menu
    {
        onLoad = "Iceman_fnc_route_initButtons";
        clickEvents[] = {"Iceman_fnc_route_planFromInputs", "Iceman_fnc_route_clear"};
    };
};

class ATAK_APPs
{
    class message;
    class Route: message
    {
        class Menu_Property
        {
            ORDER = 5;
            PAGE_CTRL = "Iceman_ATAK_Route";
            Opened = "Iceman_fnc_route_onOpened";
            ATAK_Buttons = "Iceman_Route_Menu";
        };

        onButtonClick = "[_this # 0] call BCE_fnc_ATAK_ChangeTool;";
        text = "<t size='1'>Route</t>";
        textureNoShortcut = "\z\BCE\addons\Core\data\route.paa";
    };
};
