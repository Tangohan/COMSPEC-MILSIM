class CfgPatches
{
    class Iceman_ATAK_Aware
    {
        name = "Iceman ATAK Aware";
        author = "Cole / Codex";
        requiredVersion = 2.14;
        requiredAddons[] = {"cba_main", "cba_common", "cTab", "ctab_core", "BCE_Core", "BCE_cTab"};
        units[] = {};
        weapons[] = {};
    };
};

class CfgFunctions
{
    class Iceman
    {
        class ATAK_Aware
        {
            file = "\ATAK_Aware\functions";
            class aware_applyLists {};
            class aware_attachMapControls {};
            class aware_drawBftMarkers {};
            class aware_drawFrame {};
            class aware_drawHook {};
            class aware_drawOwnCursor {};
            class aware_findControls {};
            class aware_followMiniMap {};
            class aware_getMode {};
            class aware_install {};
            class aware_installDrawHooks {};
            class aware_onOpened {};
            class aware_setMode {};
            class aware_updatePanel {};
        };
    };
};

class Extended_PostInit_EventHandlers
{
    class Iceman_ATAK_Aware
    {
        init = "call compile preprocessFileLineNumbers '\ATAK_Aware\XEH_postInitClient.sqf'";
    };
};

#include "ui\AwarePage.hpp"

class ATAK_APPs
{
    class message;
    class Aware: message
    {
        class Menu_Property
        {
            ORDER = 9;
            PAGE_CTRL = "Iceman_ATAK_Aware";
            Opened = "Iceman_fnc_aware_onOpened";
        };

        onButtonClick = "[_this # 0] call BCE_fnc_ATAK_ChangeTool;";
        text = "<t size='1'>Aware</t>";
        textureNoShortcut = "\ATAK_Aware\data\aware_icon_ca.paa";
    };
};

class RscTitles
{
    class ATAK_APPs
    {
        class message;
        class Aware: message
        {
            class Menu_Property
            {
                ORDER = 9;
                PAGE_CTRL = "Iceman_ATAK_Aware";
                Opened = "Iceman_fnc_aware_onOpened";
            };

            onButtonClick = "[_this # 0] call BCE_fnc_ATAK_ChangeTool;";
            text = "<t size='1'>Aware</t>";
            textureNoShortcut = "\ATAK_Aware\data\aware_icon_ca.paa";
        };
    };
};
