class Extended_PreInit_EventHandlers {
    class comspec_overwatch_connect_preInit {
        init = "call compile preprocessFileLineNumbers '\z\comspec_overwatch\addons\connect\XEH_preInit.sqf'";
    };
};
class Extended_PostInit_EventHandlers {
    class comspec_overwatch_connect_postInit {
        init = "call compile preprocessFileLineNumbers '\z\comspec_overwatch\addons\connect\XEH_postInit.sqf'";
    };
    class comspec_overwatch_connect {
        clientInit = "call compile preprocessFileLineNumbers '\z\comspec_overwatch\addons\connect\XEH_postInitClient.sqf'";
    };
};
class Extended_DisplayLoad_EventHandlers {
    class RscDisplayMain {
        comspec_overwatch_connect = "_this call comspec_overwatch_connect_fnc_onMainMenuLoad";
    };
};
