#include "script_component.hpp"

class CfgPatches
{
    class Iceman_ATAK_Elevation
    {
        name = "Iceman ATAK Elevation";
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
        class ATAK_Elevation
        {
            file = "\ATAK_Elevation\functions";
            class elev_apply {};
            class elev_clear {};
            class elev_computeHeatmap {};
            class elev_computeViewshed {};
            class elev_draw {};
            class elev_getState {};
            class elev_initButtons {};
            class elev_installDesktopShortcut {};
            class elev_installDrawHooks {};
            class elev_installMapHandlers {};
            class elev_installOpenMapHandlers {};
            class elev_onMapClick {};
            class elev_onOpened {};
            class elev_posToGrid {};
            class elev_selectPoint {};
            class elev_setMode {};
            class elev_setPoint {};
            class elev_updatePanel {};
        };
    };
};

class Extended_PreInit_EventHandlers
{
    class Iceman_ATAK_Elevation
    {
        init = "call compile preprocessFileLineNumbers '\ATAK_Elevation\XEH_preInit.sqf'";
    };
};

class Extended_PostInit_EventHandlers
{
    class Iceman_ATAK_Elevation
    {
        init = "call compile preprocessFileLineNumbers '\ATAK_Elevation\XEH_postInitClient.sqf'";
    };
};

#include "ui\ElevationPage.hpp"

class ATAK_Buttons
{
    class Iceman_Elevation_Menu
    {
        onLoad = "Iceman_fnc_elev_initButtons";
        clickEvents[] = {"Iceman_fnc_elev_apply", "Iceman_fnc_elev_clear"};
    };
};

class ATAK_APPs
{
    class message;
    class Iceman_Elevation: message
    {
        class Menu_Property
        {
            ORDER = 9;
            PAGE_CTRL = "Iceman_ATAK_Elevation";
            Opened = "Iceman_fnc_elev_onOpened";
            ATAK_Buttons = "Iceman_Elevation_Menu";
        };

        onButtonClick = "[_this # 0] call BCE_fnc_ATAK_ChangeTool;";
        text = "<t size='0.8'>Elevation App</t>";
        textureNoShortcut = "\z\BCE\addons\Core\data\route.paa";
    };
};
