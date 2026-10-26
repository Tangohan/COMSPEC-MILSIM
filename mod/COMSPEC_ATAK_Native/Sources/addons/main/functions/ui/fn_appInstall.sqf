/*
    App Store du téléphone : apps installées par le joueur et apps imposées par l'encadrement.
    Le joueur installe ou désinstalle chaque app (profil COMSPEC_ATAK_HiddenApps : liste des apps désinstallées, la même
    que l'ancien « cacher l'app » des Réglages). Un téléphone neuf a toutes ses apps ; une app ajoutée par une mise à jour
    arrive installée, avec la pastille NOUVEAU.
    L'encadrement peut rendre une app obligatoire (toujours installée, le joueur ne peut pas la désinstaller) ou interdite
    (retirée de l'écran d'accueil et du dock, ne s'ouvre plus). Trois sources cumulées, l'interdiction l'emporte :
      - serveur : réglages CBA « Apps obligatoires » et « Apps interdites » (noms de classe séparés par des virgules) ;
      - mission et Zeus : missionNamespace COMSPEC_ATAK_AppPolicy = [[obligatoires], [interdites]] (variable publique),
        réglée dans l'app Admin (onglet APPS) ou par le scénario sur le serveur :
        ["policy", [["Map", "Chat"], ["Dating"]]] call comspec_atak_native_fnc_appInstall ;
      - communauté Athena : Contrôle serveur > Apps du téléphone (clés net_apps_required et net_apps_forbidden).
    Le réglage CBA « Apps retirées » (comspec_atak_native_apps_off) garde son effet : l'app n'existe plus du tout.
    Réglages, App Store et Alertes (messages de l'encadrement) ne se désinstallent pas (config system=1).
    Params :
      ["state", id]       -> "system" | "forbidden" | "required" | "installed" | "removed"
      ["source", id]      -> qui impose l'app : "" | "serveur" | "mission" | "communauté" (plusieurs : joints par « + »)
      ["installed", id]   -> l'app est-elle sur l'écran d'accueil (system, required ou installed)
      ["install", id] / ["remove", id]   choix du joueur (refusé si imposé), redessine le dock et la page
      ["installAll"]      réinstalle toutes les apps permises
      ["policy"]          -> [obligatoires, interdites] en minuscules, toutes sources
      ["set", [id, "required" | "forbidden" | "free"]]   serveur : règle de la mission (droits vérifiés par fn_adminServer)
      ["policy", [obligatoires, interdites]]           serveur : remplace toute la règle de la mission (scénario)
      ["applied"]         client : la règle a changé, ferme l'app ouverte si elle est interdite et redessine
*/
params [["_mode", "state"], ["_arg", ""]];
private _split = { params ["_v"]; if !(_v isEqualType "") exitWith { [] }; ((_v splitString ", ;") apply { toLower trim _x }) select { _x isNotEqualTo "" && {_x isNotEqualTo "player"} } };
private _lower = { params ["_a"]; if !(_a isEqualType []) exitWith { [] }; (_a select { _x isEqualType "" }) apply { toLower _x } };
// Règle de toutes les sources, recalculée une fois par image au plus (le lanceur la lit pour chaque tuile).
private _policy = {
    private _c = uiNamespace getVariable ["COMSPEC_ATAK_AppPolicyCache", [-1]];
    if ((_c select 0) isEqualTo diag_frameNo) exitWith { _c select 1 };
    private _mis = missionNamespace getVariable ["COMSPEC_ATAK_AppPolicy", [[], []]];
    if !(_mis isEqualType []) then { _mis = [[], []]; };
    private _src = createHashMapFromArray [
        ["serveur", [[missionNamespace getVariable ["comspec_atak_native_apps_required", ""]] call _split, [missionNamespace getVariable ["comspec_atak_native_apps_forbidden", ""]] call _split]],
        ["mission", [[_mis param [0, []]] call _lower, [_mis param [1, []]] call _lower]],
        ["communauté", [[["net_apps_required"] call comspec_atak_native_fnc_tenantRule] call _split, [["net_apps_forbidden"] call comspec_atak_native_fnc_tenantRule] call _split]]
    ];
    private _req = [];
    private _forb = [];
    { _req append ((_y select 0) - _req); _forb append ((_y select 1) - _forb); } forEach _src;
    private _p = [_req, _forb, _src];
    uiNamespace setVariable ["COMSPEC_ATAK_AppPolicyCache", [diag_frameNo, _p]];
    _p
};
private _cfgOf = { params ["_id"]; configFile >> "COMSPEC_ATAK_Apps" >> _id };
private _state = {
    params ["_id"];
    private _l = toLower _id;
    if ((getNumber (([_id] call _cfgOf) >> "system")) > 0) exitWith { "system" };
    ([] call _policy) params ["_req", "_forb"];
    if (_l in _forb) exitWith { "forbidden" };
    if (_l in _req) exitWith { "required" };
    private _h = profileNamespace getVariable ["COMSPEC_ATAK_HiddenApps", []];
    if !(_h isEqualType []) then { _h = []; };
    ["installed", "removed"] select (_id in _h)
};
private _redraw = {
    [{
        [] call comspec_atak_native_fnc_dockRender;
        private _p = (uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["pageDrawn", ""];
        if (_p isNotEqualTo "") then { [_p] call comspec_atak_native_fnc_pageRender; };
    }] call CBA_fnc_execNextFrame;
};

switch (_mode) do {
    case "state": { [_arg] call _state };
    case "installed": { ([_arg] call _state) in ["system", "required", "installed"] };
    case "source": {
        private _l = toLower _arg;
        private _who = [];
        { if (_l in (_y select 0) || {_l in (_y select 1)}) then { _who pushBack _x; }; } forEach (([] call _policy) select 2);
        _who sort true;
        _who joinString " + "
    };
    case "policy": {
        if (_arg isEqualType [] && {isServer}) exitWith {
            missionNamespace setVariable ["COMSPEC_ATAK_AppPolicy", [[_arg param [0, []]] call _lower, [_arg param [1, []]] call _lower], true];
            ["comspec_atak_native_appPolicy", []] call CBA_fnc_globalEvent;
            true
        };
        ([] call _policy) select [0, 2]
    };
    case "install";
    case "remove": {
        private _st = [_arg] call _state;
        if (_st in ["system", "required", "forbidden"]) exitWith {
            [[
                "Cette app est imposée par l'encadrement",
                "Cette app est interdite par l'encadrement",
                "Cette app fait partie du téléphone"
            ] select (["required", "forbidden", "system"] find _st), 2] call comspec_atak_native_fnc_toast;
            false
        };
        private _h = profileNamespace getVariable ["COMSPEC_ATAK_HiddenApps", []];
        if !(_h isEqualType []) then { _h = []; };
        _h = +_h;
        if (_mode isEqualTo "install") then { _h = _h - [_arg]; } else { _h pushBackUnique _arg; };
        profileNamespace setVariable ["COMSPEC_ATAK_HiddenApps", _h];
        // Épinglée ou dans le dock : retirée avec l'app.
        if (_mode isEqualTo "remove") then {
            private _page = toUpper getText (([_arg] call _cfgOf) >> "page");
            private _pins = profileNamespace getVariable ["COMSPEC_ATAK_PinnedApps", []];
            if (_pins isEqualType [] && {_page in _pins}) then { profileNamespace setVariable ["COMSPEC_ATAK_PinnedApps", _pins - [_page]]; };
        };
        [] call comspec_atak_native_fnc_saveProfile;
        [] call _redraw;
        true
    };
    case "installAll": {
        private _h = profileNamespace getVariable ["COMSPEC_ATAK_HiddenApps", []];
        if !(_h isEqualType []) then { _h = []; };
        private _n = count _h;
        profileNamespace setVariable ["COMSPEC_ATAK_HiddenApps", []];
        [] call comspec_atak_native_fnc_saveProfile;
        [] call _redraw;
        _n
    };
    case "set": {
        if (!isServer) exitWith { false };
        _arg params [["_id", ""], ["_rule", "free"]];
        private _l = toLower _id;
        private _mis = missionNamespace getVariable ["COMSPEC_ATAK_AppPolicy", [[], []]];
        if !(_mis isEqualType []) then { _mis = [[], []]; };
        private _req = ([_mis param [0, []]] call _lower) - [_l];
        private _forb = ([_mis param [1, []]] call _lower) - [_l];
        if (_rule isEqualTo "required") then { _req pushBack _l; };
        if (_rule isEqualTo "forbidden") then { _forb pushBack _l; };
        ["policy", [_req, _forb]] call comspec_atak_native_fnc_appInstall
    };
    case "applied": {
        if (!hasInterface) exitWith {};
        uiNamespace setVariable ["COMSPEC_ATAK_AppPolicyCache", [-1]];
        private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
        private _page = _s getOrDefault ["activePage", ""];
        private _app = (([] call comspec_atak_native_fnc_appList) select { (_x get "page") isEqualTo _page }) param [0, createHashMap];
        if ((count _app) > 0 && {([_app get "id"] call _state) isEqualTo "forbidden"}) then {
            ["Cette app vient d'être interdite par l'encadrement", 3] call comspec_atak_native_fnc_toast;
            [{ ["LAUNCHER"] call comspec_atak_native_fnc_navigate; }] call CBA_fnc_execNextFrame;
        } else {
            if (!isNull ([] call comspec_atak_native_fnc_display)) then { [] call _redraw; };
        };
    };
};
