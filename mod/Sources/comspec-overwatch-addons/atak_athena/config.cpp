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
        version = 1.0.4;
        versionStr = "1.0.4";
        versionAr[] = {1, 0, 4};
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
            class athena_setPanelFeedback {};
            class athena_bridgeIcemanAlert {};
            class athena_bridgeIcemanBda {};
            class athena_bridgeIcemanPhoto {};
            class athena_bridgeIcemanGroup {};
            class athena_bridgeComspecSent {};
            class athena_onOrderReceived {};
            class athena_refresh {};
            class athena_bridgeWeather {};
            class athena_bridgeDroneContacts {};
            class athena_bridgeCtabMarkers {};
            class athena_bridgeRoute {};
            class athena_bridgeJump {};
            class athena_bridgeVideoFeeds {};
            class athena_snapshotVideoFeed {};
            class athena_installDesktopShortcut {};
            class athena_showLinkDialog {};
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

#include "ui\athena_page.hpp"

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
    };
};
