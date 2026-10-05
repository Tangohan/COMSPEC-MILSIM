/*
    Temps d'écran du téléphone, temps de jeu et temps par rôle. Appelé toutes les 5 s (XEH_postInitClient) ; ["flush"] force l'envoi.
    Compté :
      écran   : téléphone affiché (total, en main, porté en miniature) ; app : temps passé dans chaque app ;
      jeu     : temps passé dans la mission ; rôle : temps par rôle tenu (fn_roleKey : pilote, chef d'équipe…).
    Le temps de jeu et les rôles ne dépendent pas du téléphone (batterie vide, cassé, plus de réseau en jeu) :
    le serveur Arma les compte et les envoie lui-même (fn_playTimeServer). Le client ne les envoie que si le serveur
    n'y arrive pas (COMSPEC_ATAK_SrvPlayOK faux), sinon il les garde seulement pour l'affichage.
    Rien n'est compté si le serveur coupe le réglage « Temps d'écran et temps par rôle » ; l'écran ne compte pas mort ou en pause.
    Cumuls :
      COMSPEC_ATAK_ScreenSess : cette mission, [clé "type|code" -> [libellé, secondes]] (affiché par l'app Temps d'écran)
      COMSPEC_ATAK_ScreenAcc  : pas encore envoyé à Athena (envoi toutes les 5 min, ScreenTime.Report de COMSPEC Link 2.0.63).
                                Recopié dans le profil (COMSPEC_ATAK_ScreenPending) à chaque essai : un plantage, une déconnexion
                                ou Athena injoignable ne perdent rien, l'envoi repart à la mission suivante.
      profil COMSPEC_ATAK_ScreenDay : [date, [[clé, libellé, secondes]...]] aujourd'hui sur ce PC (écran et apps)
*/
params [["_mode", "tick"]];
if (!hasInterface || {isNull player}) exitWith { false };
private _now = diag_tickTime;
private _dt = (_now - (missionNamespace getVariable ["COMSPEC_ATAK_ScreenTick", _now])) min 30;
missionNamespace setVariable ["COMSPEC_ATAK_ScreenTick", _now];
private _sess = missionNamespace getVariable ["COMSPEC_ATAK_ScreenSess", createHashMap];
private _acc = missionNamespace getVariable "COMSPEC_ATAK_ScreenAcc";
if (isNil "_acc") then {
    // Première fois de la mission : reprendre ce qui n'était pas parti (mission précédente, plantage, sans liaison).
    _acc = createHashMap;
    { _x params ["_k", "_label", "_sec"]; _acc set [_k, [_label, _sec]]; } forEach (profileNamespace getVariable ["COMSPEC_ATAK_ScreenPending", []]);
};
missionNamespace setVariable ["COMSPEC_ATAK_ScreenSess", _sess];
missionNamespace setVariable ["COMSPEC_ATAK_ScreenAcc", _acc];
private _on = missionNamespace getVariable ["comspec_atak_native_screen_time", true];
private _add = {
    params ["_k", "_label", ["_maps", [_sess, _acc]]];
    { (_x getOrDefault [_k, [_label, 0]]) params ["", "_sec"]; _x set [_k, [_label, _sec + _dt]]; } forEach _maps;
};
// Temps de jeu et rôles : comptés même sans téléphone ; envoyés par le client seulement si le serveur ne le fait pas.
private _mine = [[_sess, _acc], [_sess]] select (missionNamespace getVariable ["COMSPEC_ATAK_SrvPlayOK", false]);
if (_mode isEqualTo "tick" && {_on} && {_dt > 0}) then {
    ["play|total", "Temps de jeu", _mine] call _add;
    if (alive player) then {
        ([player] call comspec_atak_native_fnc_roleKey) params ["_rk", "_rl"];
        if (_rk isNotEqualTo "") then { [format ["role|%1", _rk], _rl, _mine] call _add; };
    };
};
if (_mode isEqualTo "tick" && {_on} && {_dt > 0} && {alive player} && {isNull findDisplay 49}) then {
    private _d = [] call comspec_atak_native_fnc_display;
    if (!isNull _d) then {
        private _st = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
        private _hand = _st getOrDefault ["interactive", false];
        ["screen|total", "Écran allumé"] call _add;
        [["screen|carry", "screen|hand"] select _hand, ["Porté en miniature", "En main"] select _hand] call _add;
        private _page = _st getOrDefault ["activePage", "LAUNCHER"];
        private _names = uiNamespace getVariable "COMSPEC_ATAK_PageNames";
        if (isNil "_names") then {
            _names = createHashMapFromArray [["LAUNCHER", "Écran d'accueil"], ["RECENTS", "Apps récentes"], ["NOTIFS", "Notifications"], ["ORDER", "Ordres"]];
            { _names set [_x get "page", _x get "name"]; } forEach ([] call comspec_atak_native_fnc_appList);
            uiNamespace setVariable ["COMSPEC_ATAK_PageNames", _names];
        };
        [format ["app|%1", _page], _names getOrDefault [_page, _page]] call _add;
        // Aujourd'hui sur ce PC (date réelle), écran et apps.
        private _date = (systemTime select [0, 3]) joinString "-";
        (profileNamespace getVariable ["COMSPEC_ATAK_ScreenDay", ["", []]]) params ["_day", "_rows"];
        if (_day isNotEqualTo _date) then { _rows = []; };
        {
            _x params ["_k", "_label"];
            private _i = _rows findIf { (_x select 0) isEqualTo _k };
            if (_i < 0) then { _rows pushBack [_k, _label, _dt]; } else { (_rows select _i) set [2, ((_rows select _i) select 2) + _dt]; };
        } forEach [["screen|total", "Écran allumé"], [format ["app|%1", _page], _names getOrDefault [_page, _page]]];
        profileNamespace setVariable ["COMSPEC_ATAK_ScreenDay", [_date, _rows]];
    };
};

// Envoi à Athena : toutes les 5 min (ou forcé), seulement ce qui n'est pas encore parti.
private _total = 0;
{ _total = _total + ((_y select 1)); } forEach _acc;
private _last = missionNamespace getVariable ["COMSPEC_ATAK_ScreenSent", [-1e9, "jamais"]];
if (_total < 1 || {_mode isNotEqualTo "flush" && {(_now - (_last select 0)) < 300}}) exitWith { true };
// Garde-fou : au-delà de 100 h en attente, on repart de zéro.
if (_total > 360000) then { _acc = createHashMap; missionNamespace setVariable ["COMSPEC_ATAK_ScreenAcc", _acc]; };
private _keep = {
    profileNamespace setVariable ["COMSPEC_ATAK_ScreenPending", (keys _acc) apply { [_x, (_acc get _x) select 0, (_acc get _x) select 1] }];
    saveProfileNamespace;
};
if !([] call comspec_atak_native_fnc_bridge) exitWith {
    call _keep;
    missionNamespace setVariable ["COMSPEC_ATAK_ScreenSent", [_now, "SANS LIAISON, gardé pour plus tard"]];
    true
};
// Au plus 2 h par élément et par envoi (plafond d'Athena) : le reste part à l'envoi suivant.
private _items = [];
{
    (_x splitString "|") params [["_kind", ""], ["_code", ""]];
    if ((_y select 1) >= 1) then {
        _items pushBack (createHashMapFromArray [["kind", _kind], ["key", _code], ["label", _y select 0], ["seconds", round ((_y select 1) min 7200)]]);
    };
} forEach _acc;
private _payload = createHashMapFromArray [
    ["player_uid", getPlayerUID player], ["call_sign", [player] call comspec_atak_native_fnc_unitCallsign],
    ["mission_key", missionNamespace getVariable ["COMSPEC_ATAK_MissionKey", format ["%1@%2", missionName, worldName]]],
    ["items", _items]
];
private _r = "COMSPECExtension" callExtension ["ScreenTime.Report", [[_payload] call comspec_atak_native_fnc_json]];
if (_r isEqualType []) then { _r = _r param [0, ""]; };
if ((_r select [0, 3]) isEqualTo "OK|") then {
    { private _v = _acc get _x; _acc set [_x, [_v select 0, ((_v select 1) - 7200) max 0]]; } forEach (keys _acc);
    { _acc deleteAt _x; } forEach ((keys _acc) select { ((_acc get _x) select 1) < 1 });
    missionNamespace setVariable ["COMSPEC_ATAK_ScreenSent", [_now, "OK"]];
} else {
    missionNamespace setVariable ["COMSPEC_ATAK_ScreenSent", [_now, [format ["ERREUR %1, gardé pour plus tard", _r], "COMSPEC Link 2.0.63 requis"] select (_r isEqualTo "")]];
};
call _keep;
true
