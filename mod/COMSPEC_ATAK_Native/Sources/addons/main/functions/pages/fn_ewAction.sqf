/*
    App Guerre électronique. Params : [action, argument, valeur]
      "allowed"         : le joueur peut-il brouiller et goniométrer ? (missionNamespace comspec_atak_native_ew_open,
                          sinon variable d'unité COMSPEC_ATAK_EwOperator ou rôle « guerre électronique / brouilleur / SIGINT »)
      "set" clé valeur  : réglages (rayon, durée, filtrage ami)
      "start" / "stop"  : brouilleur à ma position, publié dans COMSPEC_ATAK_Jammers (diffusé à tous)
      "tick"            : entretien toutes les 5 s (PFH de XEH_postInitClient) : fin du brouilleur, purge, alerte de brouillage subi
      "scan"            : goniométrie à 3 km : téléphones ennemis et brouilleurs ; relèvement approximatif, bande de distance
      "clear"           : efface les relèvements     "locate" index : carte sur le point de relèvement
    Entrée de brouilleur : [position, rayon, uid, camp, fin (serverTime), filtrage ami]. Chaque client ne réécrit que la
    sienne et retire les entrées expirées ; le tick remet la sienne si une écriture concurrente l'a effacée.
*/
params [["_act", ""], ["_arg", ""], ["_val", ""]];
private _f = uiNamespace getVariable ["COMSPEC_ATAK_Ew", createHashMap];
uiNamespace setVariable ["COMSPEC_ATAK_Ew", _f];
private _now = [time, serverTime] select isMultiplayer;
private _uid = getPlayerUID player;
if (_uid isEqualTo "") then { _uid = "local"; };
private _ret = true;
private _rerender = { [{ if (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isEqualTo "EW") then { ["EW"] call comspec_atak_native_fnc_pageRender; }; }] call CBA_fnc_execNextFrame; };
private _publish = {
    params ["_list"];
    missionNamespace setVariable ["COMSPEC_ATAK_Jammers", _list, true];
    // Effets recalculés tout de suite (débit simulé et erreur GPS).
    uiNamespace setVariable ["COMSPEC_ATAK_LinkQ", []];
    uiNamespace setVariable ["COMSPEC_ATAK_EwFx", []];
};
// Liste sans mes entrées ni les brouilleurs expirés (les entrées de mission sans fin restent).
private _others = { (missionNamespace getVariable ["COMSPEC_ATAK_Jammers", []]) select { ((_x param [4, 1e9]) >= _now) && {(_x param [2, ""]) isNotEqualTo _uid} } };
private _allowed = {
    if (missionNamespace getVariable ["comspec_atak_native_ew_open", true]) exitWith { true };
    if (player getVariable ["COMSPEC_ATAK_EwOperator", false]) exitWith { true };
    private _r = (toLower (roleDescription player)) + " ";
    (["guerre", "brouill", "sigint", "elint", "electronic warfare", "ew ", "ge "] findIf { (_r find _x) >= 0 }) >= 0
};

switch (_act) do {
    case "allowed": { _ret = call _allowed; };
    case "set": { _f set [_arg, _val]; call _rerender; };
    case "start": {
        if !(call _allowed) exitWith { ["WARNING", "Brouillage réservé aux opérateurs de guerre électronique", 3, 20] call comspec_atak_native_fnc_notify; };
        if ((vehicle player) isNotEqualTo player && {speed (vehicle player) > 5}) exitWith { ["WARNING", "Arrêtez-vous pour déployer le brouilleur", 3, 20] call comspec_atak_native_fnc_notify; };
        private _rad = (parseNumber (_f getOrDefault ["rad", "600"])) max 100;
        private _dur = (parseNumber (_f getOrDefault ["dur", "10"])) max 1;
        private _p = getPosATL player;
        private _e = [[_p select 0, _p select 1, 0], _rad, _uid, str side group player, _now + _dur * 60, _f getOrDefault ["exempt", true]];
        missionNamespace setVariable ["COMSPEC_ATAK_EwMine", _e];
        private _list = call _others;
        _list pushBack _e;
        [_list] call _publish;
        ["SUCCESS", format ["Brouilleur actif : %1 m pendant %2 min%3", _rad, _dur, ["", ", alliés filtrés"] select (_e select 5)], 5, 40] call comspec_atak_native_fnc_notify;
        call _rerender;
    };
    case "stop": {
        if ((count (missionNamespace getVariable ["COMSPEC_ATAK_EwMine", []])) isEqualTo 0) exitWith {};
        missionNamespace setVariable ["COMSPEC_ATAK_EwMine", []];
        [call _others] call _publish;
        ["INFO", ["Brouilleur arrêté", "Brouilleur arrêté : durée écoulée"] select (_arg isEqualTo "auto"), 4, 30] call comspec_atak_native_fnc_notify;
        call _rerender;
    };
    case "tick": {
        private _mine = missionNamespace getVariable ["COMSPEC_ATAK_EwMine", []];
        if ((count _mine) > 0 && {(_mine select 4) < _now || {!alive player}}) then {
            ["stop", "auto"] call comspec_atak_native_fnc_ewAction;
            _mine = [];
        };
        private _list = missionNamespace getVariable ["COMSPEC_ATAK_Jammers", []];
        private _kept = _list select { (_x param [4, 1e9]) >= _now };
        private _changed = (count _kept) isNotEqualTo (count _list);
        if ((count _mine) > 0 && {(_kept findIf { (_x param [2, ""]) isEqualTo _uid && {(_x param [4, 0]) isEqualTo (_mine select 4)} }) < 0}) then {
            _kept pushBack _mine;
            _changed = true;
        };
        if (_changed) then { [_kept] call _publish; };
        // Brouillage subi : alerte à l'entrée et à la sortie de zone.
        if !([player] call comspec_atak_native_fnc_hasDevice) exitWith {};
        ([] call comspec_atak_native_fnc_ewEffects) params ["_err", "_bft"];
        private _was = uiNamespace getVariable ["COMSPEC_ATAK_EwWas", false];
        if (_err > 0 && {!_was}) then {
            ["WARNING", format ["Brouillage détecté : GPS imprécis (environ %1 m)%2", _err, ["", ", suivi des forces dégradé"] select _bft], 6, 60] call comspec_atak_native_fnc_notify;
            [] call comspec_atak_native_fnc_vibrate;
        };
        if (_err isEqualTo 0 && {_was}) then { ["INFO", "Fin du brouillage : GPS et réseau rétablis", 4, 30] call comspec_atak_native_fnc_notify; };
        uiNamespace setVariable ["COMSPEC_ATAK_EwWas", _err > 0];
    };
    case "scan": {
        if !(call _allowed) exitWith { ["WARNING", "Goniométrie réservée aux opérateurs de guerre électronique", 3, 20] call comspec_atak_native_fnc_notify; };
        private _next = uiNamespace getVariable ["COMSPEC_ATAK_EwScanNext", 0];
        if (diag_tickTime < _next) exitWith { ["WARNING", format ["Récepteur en recharge : %1 s", ceil (_next - diag_tickTime)], 3, 20] call comspec_atak_native_fnc_notify; };
        uiNamespace setVariable ["COMSPEC_ATAK_EwScanNext", diag_tickTime + 45];
        // Balayer émet aussi : la goniométrie ennemie nous voit à coup sûr pendant 20 s.
        player setVariable ["COMSPEC_ATAK_EwScanUntil", _now + 20, true];
        private _from = getPosATL player;
        _from = [_from select 0, _from select 1, 0];
        private _mySide = side group player;
        private _hits = [];
        {
            if (alive _x && {_x isNotEqualTo player} && {(side group _x) isNotEqualTo _mySide} && {(_x distance2D _from) < 3000} && {[_x] call comspec_atak_native_fnc_hasDevice}) then {
                private _loud = (_x getVariable ["COMSPEC_ATAK_EwScanUntil", -1]) > _now;
                // Un téléphone au repos n'émet que par intermittence : 70 % de chances de le relever.
                if (_loud || {(random 1) < 0.7}) then { _hits pushBack [getPosATL _x, ["TÉLÉPHONE", "GONIO"] select _loud]; };
            };
        } forEach allPlayers;
        {
            _x params [["_pos", [0, 0, 0]], ["_rad", 500], ["_owner", ""], ["_side", ""], ["_until", 1e9]];
            if (_pos isEqualType objNull) then { _pos = if (isNull _pos) then { [] } else { getPosATL _pos }; };
            if ((count _pos) >= 2 && {_owner isNotEqualTo _uid} && {_until >= _now} && {(_from distance2D _pos) < (3000 + _rad)}) then { _hits pushBack [_pos, "BROUILLEUR"]; };
        } forEach (missionNamespace getVariable ["COMSPEC_ATAK_Jammers", []]);
        private _bearings = missionNamespace getVariable ["COMSPEC_ATAK_EwBearings", []];
        private _hour = [dayTime, "HH:MM"] call BIS_fnc_timeToString;
        {
            _x params ["_pos", "_kind"];
            private _d = _from distance2D _pos;
            // Erreur angulaire croissante avec la distance (2 à 12 degrés), distance donnée par bande seulement.
            private _err = 2 + 10 * ((_d / 3000) min 1.5);
            private _brg = ((_from getDir _pos) + (random [-_err, 0, _err]) + 360) mod 360;
            private _band = switch (true) do { case (_d < 500): { [0, 500] }; case (_d < 1000): { [500, 1000] }; case (_d < 2000): { [1000, 2000] }; case (_d < 3000): { [2000, 3000] }; default { [3000, 4500] }; };
            _bearings pushBack [_from, _brg, _band select 0, _band select 1, _kind, time, _err, _hour];
        } forEach _hits;
        while { (count _bearings) > 15 } do { _bearings deleteAt 0; };
        missionNamespace setVariable ["COMSPEC_ATAK_EwBearings", _bearings];
        if ((count _hits) > 0) then {
            ["SUCCESS", format ["Goniométrie : %1 émission(s) relevée(s)", count _hits], 4, 40] call comspec_atak_native_fnc_notify;
            [] call comspec_atak_native_fnc_vibrate;
        } else {
            ["INFO", "Goniométrie : aucune émission à moins de 3 km", 4, 30] call comspec_atak_native_fnc_notify;
        };
        call _rerender;
    };
    case "clear": { missionNamespace setVariable ["COMSPEC_ATAK_EwBearings", []]; call _rerender; };
    case "locate": {
        private _b = (missionNamespace getVariable ["COMSPEC_ATAK_EwBearings", []]) param [_arg, []];
        if ((count _b) < 4) exitWith {};
        private _mid = (_b select 0) getPos [((_b select 2) + (_b select 3)) / 2, _b select 1];
        ["MAP"] call comspec_atak_native_fnc_navigate;
        [{ [_this, 0.08] call comspec_atak_native_fnc_mapCenter; }, [_mid select 0, _mid select 1]] call CBA_fnc_execNextFrame;
    };
};
_ret
