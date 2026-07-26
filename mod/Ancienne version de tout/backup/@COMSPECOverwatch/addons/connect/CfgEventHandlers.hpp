class Extended_PreInit_EventHandlers {
    class comspec_overwatch_connect_preInit {
        init = "call compile preprocessFileLineNumbers '\z\comspec_overwatch\addons\connect\XEH_preInit.sqf'";
    };
};
class Extended_PostInit_EventHandlers {
    class comspec_overwatch_connect_postInit {
        init = "call compile preprocessFileLineNumbers '\z\comspec_overwatch\addons\connect\XEH_postInit.sqf'";
    };
};
