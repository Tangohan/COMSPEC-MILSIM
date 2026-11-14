/*
    App Guerre électronique. Params : [action, argument, valeur]
      "allowed"         : le joueur peut-il brouiller et goniométrer ? (missionNamespace comspec_atak_native_ew_open,
                          sinon variable d'unité COMSPEC_ATAK_EwOperator ou rôle « guerre électronique / brouilleur / SIGINT »)
      "set" clé valeur  : réglages (rayon, durée, filtrage ami)
      "start" / "stop"  : brouilleur à ma position, publié dans COMSPEC_ATAK_Jammers (diffusé à tous)
      "tick"            : entretien toutes les 5 s (PFH de XEH_postInitClient) : fin du brouilleur, purge, alerte de brouillage subi
      "scan"            : goniométrie à 3 km : téléphones ennemis et brouilleurs ; relèvement approximatif, bande de distance
      "clear"           : efface les relèvements     "locate" index : carte sur le point de relèvement
      "fixes"           : points estimés par recoupement, gardés dans COMSPEC_ATAK_EwFixes et renvoyés :
                          [[position, rayon d'erreur (m), type, heure du dernier relèvement, recoupements]...].
                          Deux relèvements du même type, de moins de 10 min, pris à plus de 150 m l'un de l'autre et
                          qui se croisent à 15 degrés ou plus donnent un point ; le rayon vient des erreurs angulaires
                          et de l'angle de croisement (50 à 1500 m). Sans identité d'émetteur, deux émetteurs du même
                          type peuvent donner un point fantôme : un troisième relèvement le départage.
      "locateFix" index : carte sur le point estimé
      "geoAllowed", "geoLocate", "geoTrack" i, "geoDel" i, "geoMap" i : onglet TRAÇAGE, passés à fn_traceAction
                          (allowed, submit, track, del, map) : la position tracée vient du serveur (fn_traceServer)
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
private _rerender = { [{ if (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isEqualTo "EW") then { ["EW"] call comspec_atak_native_fnc_pageRender; }; }] call comspec_atak_native_fnc_nextFrame; };
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
        // Numéros tracés (fn_traceAction) : suivis à leur rythme, requêtes restées sans réponse.
        ["tick"] call comspec_atak_native_fnc_traceAction;
        private _mine = missionNamespace getVariable ["COMSPEC_ATAK_EwMine", []];
        if ((count _mine) > 0 && {(_mine select 4) < _now || {!alive player}}) then {
            ["stop", "auto"] call comspec_atak_native_fnc_ewAction;
            _mine = [];
        };
        private _list = missionNamespace getVariable ["COMSPEC_ATAK_Jammers", []];
        private _kept = _list select { (_x param [4, 1e9]) >= _now };
        // Brouilleur expiré : retiré par son propriétaire ou par le serveur seulement (tous les clients republiaient
        // la liste au même moment).
        private _changed = (count _kept) isNotEqualTo (count _list)
            && {isServer || {(_list select { (_x param [4, 1e9]) < _now }) findIf { (_x param [2, ""]) isEqualTo _uid } >= 0}};
        if ((count _mine) > 0 && {(_kept findIf { (_x param [2, ""]) isEqualTo _uid && {(_x param [4, 0]) isEqualTo (_mine select 4)} }) < 0}) then {
            _kept pushBack _mine;
            _changed = true;
        };
        if (_changed) then { [_kept] call _publish; };
        // Brouillage subi : alerte à l'entrée et à la sortie de zone.
        if !([player] call comspec_atak_native_fnc_hasDevice) exitWith {};
        ([] call comspec_atak_native_fnc_ewEffects) params ["_err", "_bft"];
        private _was = missionNamespace getVariable ["COMSPEC_ATAK_EwWas", false];
        if (_err > 0 && {!_was}) then {
            ["WARNING", format ["Brouillage détecté : GPS imprécis (environ %1 m)%2", _err, ["", ", suivi des forces dégradé"] select _bft], 6, 60] call comspec_atak_native_fnc_notify;
            [] call comspec_atak_native_fnc_vibrate;
        };
        if (_err isEqualTo 0 && {_was}) then { ["INFO", "Fin du brouillage : GPS et réseau rétablis", 4, 30] call comspec_atak_native_fnc_notify; };
        missionNamespace setVariable ["COMSPEC_ATAK_EwWas", _err > 0];
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
                if (_loud || {(random 1) < 0.7}) then { _hits pushBack [getPosATL _x, ["TÉLÉPHONE", "GONIO"] select _loud, getPlayerUID _x]; };
            };
        } forEach allPlayers;
        {
            _x params [["_pos", [0, 0, 0]], ["_rad", 500], ["_owner", ""], ["_side", ""], ["_until", 1e9]];
            if (_pos isEqualType objNull) then { _pos = if (isNull _pos) then { [] } else { getPosATL _pos }; };
            if ((count _pos) >= 2 && {_owner isNotEqualTo _uid} && {_until >= _now} && {(_from distance2D _pos) < (3000 + _rad)}) then { _hits pushBack [_pos, "BROUILLEUR", _owner]; };
        } forEach (missionNamespace getVariable ["COMSPEC_ATAK_Jammers", []]);
        private _bearings = missionNamespace getVariable ["COMSPEC_ATAK_EwBearings", []];
        private _hour = [dayTime, "HH:MM"] call BIS_fnc_timeToString;
        // Relèvements envoyés au poste (SendSigint : position du goniomètre + gisement, 8 au plus par balayage) :
        // lignes de relèvement de la carte SIGINT d'Athena, recoupées par émetteur (indicatif anonyme stable).
        private _tx = [] call comspec_atak_native_fnc_bridge;
        {
            _x params ["_pos", "_kind", ["_id", ""]];
            private _d = _from distance2D _pos;
            // Erreur angulaire croissante avec la distance (2 à 12 degrés), distance donnée par bande seulement.
            private _err = 2 + 10 * ((_d / 3000) min 1.5);
            private _brg = ((_from getDir _pos) + (random [-_err, 0, _err]) + 360) mod 360;
            private _band = switch (true) do { case (_d < 500): { [0, 500] }; case (_d < 1000): { [500, 1000] }; case (_d < 2000): { [1000, 2000] }; case (_d < 3000): { [2000, 3000] }; default { [3000, 4500] }; };
            _bearings pushBack [_from, _brg, _band select 0, _band select 1, _kind, time, _err, _hour];
            if (_tx && {_forEachIndex < 8}) then {
                private _code = 0;
                { _code = (_code * 31 + _x) mod 9973; } forEach (toArray _id);
                (["COMSPECExtension", "SendSigint", [format ["GE %1-%2", ["EMETTEUR", "BROUILLEUR"] select (_kind isEqualTo "BROUILLEUR"), _code], str round (_from select 0), str round (_from select 1), str round _brg]] call comspec_overwatch_connect_fnc_extDirect);
            };
        } forEach _hits;
        while { (count _bearings) > 15 } do { _bearings deleteAt 0; };
        missionNamespace setVariable ["COMSPEC_ATAK_EwBearings", _bearings];
        ["fixes"] call comspec_atak_native_fnc_ewAction;
        if ((count _hits) > 0) then {
            ["SUCCESS", format ["Goniométrie : %1 émission(s) relevée(s)", count _hits], 4, 40] call comspec_atak_native_fnc_notify;
            [] call comspec_atak_native_fnc_vibrate;
        } else {
            ["INFO", "Goniométrie : aucune émission à moins de 3 km", 4, 30] call comspec_atak_native_fnc_notify;
        };
        call _rerender;
    };
    // Ancienne GÉOLOC : passe au traçage de numéro (fn_traceAction, requête au serveur).
    case "geoAllowed": { _ret = ["allowed"] call comspec_atak_native_fnc_traceAction; };
    case "geoLocate": { ["submit"] call comspec_atak_native_fnc_traceAction; };
    case "geoTrack": { ["track", _arg] call comspec_atak_native_fnc_traceAction; };
    case "geoDel": { ["del", _arg] call comspec_atak_native_fnc_traceAction; };
    case "geoMap": { ["map", _arg] call comspec_atak_native_fnc_traceAction; };
    case "clear": { missionNamespace setVariable ["COMSPEC_ATAK_EwBearings", []]; missionNamespace setVariable ["COMSPEC_ATAK_EwFixes", []]; call _rerender; };
    case "fixes": {
        private _b = (missionNamespace getVariable ["COMSPEC_ATAK_EwBearings", []]) select { (time - (_x select 5)) <= 600 };
        private _raw = [];
        for "_i" from 0 to ((count _b) - 2) do {
            (_b select _i) params ["_p1", "_a1", "_n1", "_f1", "_k1", "_t1", "_e1"];
            for "_j" from (_i + 1) to ((count _b) - 1) do {
                (_b select _j) params ["_p2", "_a2", "_n2", "_f2", "_k2", "_t2", "_e2"];
                if (_k1 isNotEqualTo _k2 || {(_p1 distance2D _p2) < 150}) then { continue; };
                // Angle de croisement des deux droites (0 à 90 degrés) : trop fermé, le point n'a pas de sens.
                private _df = abs (((_a1 - _a2 + 540) mod 360) - 180);
                private _cross = _df min (180 - _df);
                if (_cross < 15) then { continue; };
                // Demi-droites p + s.d (d = [sin cap, cos cap]) : le point doit être devant les deux goniomètres.
                private _den = (sin _a1) * (cos _a2) - (cos _a1) * (sin _a2);
                private _dx = (_p2 select 0) - (_p1 select 0);
                private _dy = (_p2 select 1) - (_p1 select 1);
                private _s = (_dx * (cos _a2) - _dy * (sin _a2)) / _den;
                private _u = (_dx * (cos _a1) - _dy * (sin _a1)) / _den;
                if (_s <= 0 || {_u <= 0}) then { continue; };
                // Cohérent avec les bandes de distance des deux relèvements (30 % de marge).
                if (_s < _n1 * 0.7 || {_s > _f1 * 1.3} || {_u < _n2 * 0.7} || {_u > _f2 * 1.3}) then { continue; };
                _raw pushBack [[(_p1 select 0) + _s * (sin _a1), (_p1 select 1) + _s * (cos _a1), 0],
                    (((((_s * tan _e1) max (_u * tan _e2)) / (sin _cross)) max 50) min 1500), _k1, _t1 max _t2];
            };
        };
        // Points voisins du même type : un seul point (moyenne), plus sûr à chaque recoupement de plus.
        private _out = [];
        {
            _x params ["_pt", "_r", "_k", "_t"];
            private _i = _out findIf { (_x select 2) isEqualTo _k && {((_x select 0) distance2D _pt) < ((_x select 1) max _r)} };
            if (_i < 0) then { _out pushBack [_pt, _r, _k, _t, 1]; } else {
                (_out select _i) params ["_p0", "_r0", "", "_t0", "_n0"];
                _out set [_i, [[((_p0 select 0) * _n0 + (_pt select 0)) / (_n0 + 1), ((_p0 select 1) * _n0 + (_pt select 1)) / (_n0 + 1), 0],
                    ((_r0 min _r) * 0.85) max 50, _k, _t0 max _t, _n0 + 1]];
            };
        } forEach _raw;
        missionNamespace setVariable ["COMSPEC_ATAK_EwFixes", _out];
        _ret = _out;
    };
    case "locateFix": {
        private _fx = (missionNamespace getVariable ["COMSPEC_ATAK_EwFixes", []]) param [_arg, []];
        if ((count _fx) < 2) exitWith {};
        ["MAP"] call comspec_atak_native_fnc_navigate;
        [{ [_this, 0.08] call comspec_atak_native_fnc_mapCenter; }, [(_fx select 0) select 0, (_fx select 0) select 1]] call comspec_atak_native_fnc_nextFrame;
    };
    case "locate": {
        private _b = (missionNamespace getVariable ["COMSPEC_ATAK_EwBearings", []]) param [_arg, []];
        if ((count _b) < 4) exitWith {};
        private _mid = (_b select 0) getPos [((_b select 2) + (_b select 3)) / 2, _b select 1];
        ["MAP"] call comspec_atak_native_fnc_navigate;
        [{ [_this, 0.08] call comspec_atak_native_fnc_mapCenter; }, [_mid select 0, _mid select 1]] call comspec_atak_native_fnc_nextFrame;
    };
};
_ret
