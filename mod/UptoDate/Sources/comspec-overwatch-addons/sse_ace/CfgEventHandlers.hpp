class Extended_PreInit_EventHandlers {
    class comspec_overwatch_sse_ace_preInit {
        init = "call compile preprocessFileLineNumbers '\z\comspec_overwatch\addons\sse_ace\XEH_preInit.sqf'";
    };
};
class Extended_PostInit_EventHandlers {
    class comspec_overwatch_sse_ace {
        clientInit = "call compile preprocessFileLineNumbers '\z\comspec_overwatch\addons\sse_ace\XEH_postInitClient.sqf'";
    };
};
