class Extended_PreInit_EventHandlers {
    class comspec_sse_loadout {
        init = "call compile preprocessFileLineNumbers 'z\comspec_sse\addons\loadout\XEH_preInit.sqf'";
    };
};

class Extended_PostInit_EventHandlers {
    class comspec_sse_loadout {
        init = "call compile preprocessFileLineNumbers 'z\comspec_sse\addons\loadout\XEH_postInit.sqf'";
        clientInit = "call compile preprocessFileLineNumbers 'z\comspec_sse\addons\loadout\XEH_postInitClient.sqf'";
    };
};
