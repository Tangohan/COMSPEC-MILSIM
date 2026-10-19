class Extended_PostInit_EventHandlers {
    class comspec_sse_interaction {
        init = "call compile preprocessFileLineNumbers 'z\comspec_sse\addons\interaction\XEH_postInit.sqf'";
        clientInit = "call compile preprocessFileLineNumbers 'z\comspec_sse\addons\interaction\XEH_postInitClient.sqf'";
    };
};
