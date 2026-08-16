class Extended_PreInit_EventHandlers {
    class comspec_sse_debug {
        init = "call compile preprocessFileLineNumbers 'z\comspec_sse\addons\debug\XEH_preInit.sqf'";
    };
};

class Extended_PostInit_EventHandlers {
    class comspec_sse_debug {
        init = "call compile preprocessFileLineNumbers 'z\comspec_sse\addons\debug\XEH_postInit.sqf'";
    };
};
