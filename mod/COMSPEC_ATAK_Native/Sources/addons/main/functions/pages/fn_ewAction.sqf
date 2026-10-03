/*
    App Guerre électronique. Params : [action, argument, valeur]
      "allowed"         : le joueur peut-il brouiller et goniométrer ? (missionNamespace comspec_atak_native_ew_open,
                          sinon variable d'unité COMSPEC_ATAK_EwOperator ou rôle « guerre électronique / brouilleur / SIGINT »)
      "set" clé valeur  : réglages (rayon, durée, filtrage ami)
      "start" / "stop"  : brouilleur à ma position, publié dans COMSPEC_ATAK_Jammers (diffusé à tous)
      "tick"            : entretien toutes les 5 s (PFH de XEH_postInitClient) : fin du brouilleur, purge, alerte de brouillage subi
      "scan"            : goniométrie à 3 km : téléphones ennemis et brouilleurs ; relèvement approximatif, bande de distance
      "clear"           : efface les relèvements     "locate" index : carte sur le point de relèvement
      "geoAllowed"      : [autorisé, raison] pour la géolocalisation (réglages CBA « Géolocalisation (GEOLOC) »)
      "geoLocate"       : localise le téléphone dont le numéro, l'IMEI ou la MAC est saisi (champ geoq)
      "geoTrack" i      : suivi actif on/off (nouvelle position toutes les N s)   "geoDel" i   "geoMap" i : carte sur la position
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
private _geoFix = {
    params ["_e"];
    private _u = _e get "unit";
    private _hour = [dayTime, "HH:MM"] call BIS_fnc_timeToString;
    private _prev = _e getOrDefault ["status", ""];
    private _st = switch (true) do {
        case (isNull _u || {!alive _u}): { "LOST" };
        case !((_e get "qn") in ([_u, "norm"] call comspec_atak_native_fnc_phoneIdent)): { "LOST" };
        case !([_u] call comspec_atak_native_fnc_hasDevice): { "OFF" };
        case (isPlayer _u && {!(_u getVariable ["COMSPEC_ATAK_Beacon", true])}): { "OFF" };
        default { "OK" };
    };
    _e set ["status", _st];
    _e set ["checked", time];
    if (_st isNotEqualTo "OK") exitWith {
        if (_prev isNotEqualTo _st) then {
            ["WARNING", format ["GÉOLOC %1 : %2", _e get "q", ["téléphone éteint ou hors réseau, dernière position conservée", "abonné introuvable (appareil changé ou détruit)"] select (_st isEqualTo "LOST")], 5, 40] call comspec_atak_native_fnc_notify;
        };
        false
    };
    // Triangulation par antennes : position décalée dans le rayon d'incertitude.
    private _prec = (missionNamespace getVariable ["comspec_atak_native_geoloc_precision", 150]) max 10;
    private _r = _prec * (0.6 + random 0.8);
    private _p = (getPosATL _u) getPos [random (_r * 0.8), random 360];
    _e set ["pos", [_p select 0, _p select 1, 0]];
    _e set ["rad", round _r];
    _e set ["t", time];
    _e set ["hour", _hour];
    if (isPlayer _u && {missionNamespace getVariable ["comspec_atak_native_geoloc_warn", false]}) then {
        ["comspec_atak_native_geoWarn", [], _u] call CBA_fnc_targetEvent;
    };
    true
};
private _geoAllowed = {
    if !(missionNamespace getVariable ["comspec_atak_native_geoloc_enabled", true]) exitWith { [false, "Géolocalisation désactivée sur ce serveur."] };
    if ((missionNamespace getVariable ["comspec_atak_native_geoloc_ew_only", false]) && {!(call _allowed)}) exitWith { [false, "Réservé aux opérateurs de guerre électronique sur cette mission."] };
    [true, ""]
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
        // Suivis GÉOLOC actifs : nouvelle position à l'intervalle réglé.
        private _every = (missionNamespace getVariable ["comspec_atak_native_geoloc_refresh", 30]) max 10;
        private _geoChanged = false;
        {
            if ((_x getOrDefault ["track", false]) && {(time - (_x getOrDefault ["checked", -1e9])) >= _every}) then { [_x] call _geoFix; _geoChanged = true; };
        } forEach (missionNamespace getVariable ["COMSPEC_ATAK_GeoTracks", []]);
        if (_geoChanged) then { call _rerender; };
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
    case "geoAllowed": { _ret = call _geoAllowed; };
    case "geoLocate": {
        (call _geoAllowed) params ["_ok", "_why"];
        if !(_ok) exitWith { ["WARNING", _why, 3, 20] call comspec_atak_native_fnc_notify; };
        private _q = ["geoq", _f getOrDefault ["geoq", ""]] call comspec_atak_native_fnc_formValue;
        _f set ["geoq", _q];
        private _qn = toString ((toArray toUpper _q) select { (_x >= 48 && _x <= 57) || {_x >= 65 && _x <= 90} });
        // Numéro international (+33 6…, 0033 6…) ramené au format national.
        if ((_qn find "0033") isEqualTo 0) then { _qn = _qn select [2]; };
        if ((_qn find "33") isEqualTo 0 && {(count _qn) isEqualTo 11}) then { _qn = "0" + (_qn select [2]); };
        if ((count _qn) < 8) exitWith { ["WARNING", "Saisissez un numéro, un IMEI ou une adresse MAC complète", 3, 20] call comspec_atak_native_fnc_notify; };
        private _next = uiNamespace getVariable ["COMSPEC_ATAK_GeoNext", 0];
        if (diag_tickTime < _next) exitWith { ["WARNING", format ["Requête opérateur en cours : réessayez dans %1 s", ceil (_next - diag_tickTime)], 3, 20] call comspec_atak_native_fnc_notify; };
        uiNamespace setVariable ["COMSPEC_ATAK_GeoNext", diag_tickTime + 10];
        private _ai = missionNamespace getVariable ["comspec_atak_native_geoloc_ai", true];
        private _target = objNull; private _kind = "";
        {
            private _ids = [_x, "norm"] call comspec_atak_native_fnc_phoneIdent;
            private _k = _ids find _qn;
            if (_k >= 0) exitWith { _target = _x; _kind = ["NUMÉRO", "IMEI", "MAC"] select _k; };
        } forEach (allUnits select { alive _x && {isPlayer _x || _ai} && {[_x] call comspec_atak_native_fnc_hasDevice} });
        if (isNull _target || {!(_target getVariable ["COMSPEC_ATAK_Traceable", true])}) exitWith {
            ["WARNING", format ["GÉOLOC : aucun abonné ne correspond à %1", _q], 4, 30] call comspec_atak_native_fnc_notify;
        };
        private _ts = side group _target;
        private _sideKey = switch (_ts) do { case west: { "west" }; case east: { "east" }; case resistance: { "guer" }; default { "civ" }; };
        if (_ts isEqualTo (side group player) && {_target isNotEqualTo player} && {!(missionNamespace getVariable ["comspec_atak_native_geoloc_own", false])}) exitWith {
            ["WARNING", "GÉOLOC : numéro de votre camp, traçage non autorisé", 4, 30] call comspec_atak_native_fnc_notify;
        };
        if !(missionNamespace getVariable [format ["comspec_atak_native_geoloc_%1", _sideKey], true]) exitWith {
            ["WARNING", "GÉOLOC : opérateur hors d'atteinte, ce camp n'est pas traçable sur cette mission", 4, 30] call comspec_atak_native_fnc_notify;
        };
        private _list = missionNamespace getVariable ["COMSPEC_ATAK_GeoTracks", []];
        private _e = createHashMapFromArray [["q", _q], ["qn", _qn], ["kind", _kind], ["unit", _target], ["pos", []], ["rad", 0], ["t", -1], ["hour", ""], ["track", false], ["status", ""]];
        if ([_e] call _geoFix) then {
            ["SUCCESS", format ["GÉOLOC %1 : position à %2 m près, %3", _q, _e get "rad", [_e get "pos", 6] call comspec_atak_native_fnc_gridRef], 5, 40] call comspec_atak_native_fnc_notify;
            [] call comspec_atak_native_fnc_vibrate;
        };
        _list = _list select { (_x get "qn") isNotEqualTo _qn };
        _list pushBack _e;
        while { (count _list) > 6 } do { _list deleteAt 0; };
        missionNamespace setVariable ["COMSPEC_ATAK_GeoTracks", _list];
        call _rerender;
    };
    case "geoTrack": {
        private _e = (missionNamespace getVariable ["COMSPEC_ATAK_GeoTracks", []]) param [_arg, createHashMap];
        if ((count _e) isEqualTo 0) exitWith {};
        _e set ["track", !(_e getOrDefault ["track", false])];
        if (_e get "track") then { [_e] call _geoFix; };
        call _rerender;
    };
    case "geoDel": {
        private _list = missionNamespace getVariable ["COMSPEC_ATAK_GeoTracks", []];
        if (_arg isEqualType 0 && {_arg < count _list}) then { _list deleteAt _arg; };
        call _rerender;
    };
    case "geoMap": {
        private _e = (missionNamespace getVariable ["COMSPEC_ATAK_GeoTracks", []]) param [_arg, createHashMap];
        private _p = _e getOrDefault ["pos", []];
        if ((count _p) < 2) exitWith {};
        ["MAP"] call comspec_atak_native_fnc_navigate;
        [{ [_this, 0.06] call comspec_atak_native_fnc_mapCenter; }, [_p select 0, _p select 1]] call CBA_fnc_execNextFrame;
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
