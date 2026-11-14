// Événements CBA (XEH) : réglages et table des codes au preInit, contrôle du poste au postInit.
// L'écran des rôles ne dépend pas de CBA : son script est lancé par le onLoad de l'écran lui-même (RscLobby.hpp).
class Extended_PreInit_EventHandlers {
    class comspec_lobby {
        init = "call compileScript ['\z\comspec_lobby\addons\main\XEH_preInit.sqf']";
    };
};
class Extended_PostInit_EventHandlers {
    class comspec_lobby {
        init = "call compileScript ['\z\comspec_lobby\addons\main\XEH_postInit.sqf']";
    };
};
