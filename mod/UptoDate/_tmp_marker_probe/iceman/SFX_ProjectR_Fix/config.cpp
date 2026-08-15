class CfgPatches
{
    class SFX_ProjectR_Fix
    {
        name = "SFX ProjectR compatibility fix";
        author = "Cole / Codex";
        requiredVersion = 2.14;
        requiredAddons[] = {"cba_main"};
        units[] = {};
        weapons[] = {};
    };
};

class Extended_PreInit_EventHandlers
{
    class SFX_ProjectR_Fix
    {
        init = "call compile preprocessFileLineNumbers '\SFX_ProjectR_Fix\XEH_preInit.sqf'";
    };
};
