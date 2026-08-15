#include "script_component.hpp"

class CfgPatches
{
    class Iceman_ATAK_BDA
    {
        name = "Iceman ATAK BDA";
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
        class ATAK_BDA
        {
            file = "\ATAK_BDA\functions";
            class bda_clearForm {};
            class bda_clearReports {};
            class bda_onOpened {};
            class bda_receive {};
            class bda_send {};
            class bda_updatePanel {};
        };
    };
};

class Extended_PreInit_EventHandlers
{
    class Iceman_ATAK_BDA
    {
        init = "call compile preprocessFileLineNumbers '\ATAK_BDA\XEH_preInit.sqf'";
    };
};

class Extended_PostInit_EventHandlers
{
    class Iceman_ATAK_BDA
    {
        init = "call compile preprocessFileLineNumbers '\ATAK_BDA\XEH_postInitClient.sqf'";
    };
};
