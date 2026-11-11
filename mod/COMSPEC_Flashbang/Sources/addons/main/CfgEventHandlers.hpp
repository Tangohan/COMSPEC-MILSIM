// Événements CBA (XEH) : réglages au preInit, initialisation au postInit, lancer de grenade sur tous les fantassins.
class Extended_PreInit_EventHandlers {
    class comspec_flashbang {
        init = "call compileScript ['\z\comspec_flashbang\addons\main\XEH_preInit.sqf']";
    };
};
class Extended_PostInit_EventHandlers {
    class comspec_flashbang {
        init = "call compileScript ['\z\comspec_flashbang\addons\main\XEH_postInit.sqf']";
    };
};
// FiredBIS se déclenche sur toutes les machines ; fn_fired ne fait agir que la machine du lanceur (local).
class Extended_FiredBIS_EventHandlers {
    class CAManBase {
        class comspec_flashbang {
            firedBIS = "_this call comspec_fb_fnc_fired";
        };
    };
};
