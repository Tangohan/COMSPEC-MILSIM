/*
    Icônes d'apps « réalistes » (fond carré arrondi coloré, data\ui_icon_squircle.paa, tools/gen_ui_realism.py).
    Params : [action, argument]
      "on"    : style réaliste actif ? (profil COMSPEC_ATAK_IconStyle, "real" par défaut ; "flat" = tuiles plates d'avant)
      "color" : page -> couleur RGBA du fond de l'icône : couleur propre à l'app, sinon celle de sa section du lanceur.
                Table page -> couleur gardée en uiNamespace (refaite quand le nombre d'apps change).
    Teintes franches mais un peu sourdes (écran militaire) ; le gris de la texture assombrit le bas de l'icône.
*/
params [["_act", "on"], ["_arg", ""]];
switch (_act) do {
    case "on": { (profileNamespace getVariable ["COMSPEC_ATAK_IconStyle", "real"]) isNotEqualTo "flat" };
    case "color": {
        private _apps = [] call comspec_atak_native_fnc_appList;
        (uiNamespace getVariable ["COMSPEC_ATAK_IconTintMap", [-1, createHashMap]]) params ["_n", "_map"];
        if (_n isNotEqualTo (count _apps)) then {
            private _bySection = createHashMapFromArray [
                ["Navigation", [0.17, 0.56, 0.31, 1]],
                ["Communication", [0.15, 0.44, 0.80, 1]],
                ["Mission", [0.84, 0.54, 0.11, 1]],
                ["Appui et feux", [0.78, 0.22, 0.18, 1]],
                ["Renseignement", [0.45, 0.32, 0.74, 1]],
                ["Spécialités", [0.07, 0.55, 0.57, 1]],
                ["Système", [0.36, 0.40, 0.44, 1]],
                ["Civil", [0.86, 0.33, 0.47, 1]]
            ];
            private _byPage = createHashMapFromArray [
                ["MAP", [0.13, 0.50, 0.27, 1]],
                ["WEATHER", [0.16, 0.56, 0.86, 1]],
                ["CHAT", [0.10, 0.60, 0.38, 1]],
                ["DISCORD", [0.35, 0.40, 0.90, 1]],
                ["MEDICAL", [0.84, 0.17, 0.22, 1]],
                ["MUSIC", [0.93, 0.33, 0.27, 1]],
                ["ATHENA", [0.10, 0.20, 0.30, 1]],
                ["SETTINGS", [0.42, 0.45, 0.48, 1]],
                ["ALERTS", [0.86, 0.30, 0.12, 1]],
                ["EW", [0.55, 0.20, 0.62, 1]]
            ];
            _map = createHashMap;
            {
                private _p = _x get "page";
                _map set [_p, _byPage getOrDefault [_p, _bySection getOrDefault [_x get "section", [0.28, 0.34, 0.40, 1]]]];
            } forEach _apps;
            uiNamespace setVariable ["COMSPEC_ATAK_IconTintMap", [count _apps, _map]];
        };
        +(_map getOrDefault [_arg, [0.28, 0.34, 0.40, 1]])
    };
    default { false };
}
