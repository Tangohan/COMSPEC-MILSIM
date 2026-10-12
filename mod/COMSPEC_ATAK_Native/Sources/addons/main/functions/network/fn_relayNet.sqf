/*
    Relais du réseau ATAK : liste, carte de couverture (débit, perte) et triangulation d'un appareil par les relais qui l'entendent.
      "list"     : relais Overwatch (COMSPEC_AtakRelays, intacts et détruits) puis passerelles de véhicule et PC mobiles du camp.
                   [[objet, position antenne ASL, nom, type ("relay" | "gw" | "cp"), portée m, Mbit/s, intact]...]
      "link"     : [position antenne ASL, portée, Mbit/s, position ASL visée] -> [qualité 0-1, Mbit/s, perte %] ;
                   portée réduite à 35 % quand le relief coupe la ligne (même règle que le maillage Wave Relay).
      "coverage" : calcule en tâche de fond une grille 41 x 41 autour du centre donné (ou de moi), rayon rcRadius
                   (2, 5 ou 10 km) : meilleur débit reçu à 1,7 m du sol, brouilleurs compris.
                   Résultat : missionNamespace COMSPEC_ATAK_Coverage = [centre, case, [[pos, Mbit/s, perte %, relais]...],
                   rayon, % couvert, heure, nb relais, débit moyen].
      "at"       : [position] -> [Mbit/s, perte %, nom du relais] d'après la dernière couverture, ou [] hors grille.
      "clear"    : efface la couverture.
      "locate"   : [objet visé] triangule l'appareil : chaque relais ou téléphone du maillage qui l'entend donne une distance
                   estimée (puissance reçue, bruitée selon la qualité du lien) ; position par moindres carrés.
                   Résultat ajouté à missionNamespace COMSPEC_ATAK_Trilat (10 dernières, effacées au bout de 10 min) :
                   [nom, position estimée ou [], rayon d'erreur m, [[position source, distance estimée]...], heure, netId].
      "trilatClear" : efface les triangulations.
*/
params [["_act", "list"], ["_arg", []]];
private _now = [time, serverTime] select isMultiplayer;
switch (_act) do {
    case "list": {
        private _out = [];
        private _defR = 2000;
        if (!isNil "ATHENA_fnc_getRealismParam") then { _defR = ["radio_relays", "relay_range_m", 2000] call ATHENA_fnc_getRealismParam; };
        {
            if (isNull _x || {!(_x getVariable ["COMSPEC_AtakRelay", false])}) then { continue };
            private _r = _x getVariable ["COMSPEC_AtakRelayRange", _defR];
            if !(_r isEqualType 0) then { _r = _defR; };
            private _mb = _x getVariable ["COMSPEC_AtakRelayThroughput", 12];
            if !(_mb isEqualType 0) then { _mb = 12; };
            private _n = _x getVariable ["COMSPEC_AtakRelayName", "Relais ATAK"];
            if (!(_n isEqualType "") || {_n isEqualTo ""}) then { _n = "Relais ATAK"; };
            private _ok = alive _x && {damage _x < 0.95};
            _out pushBack [_x, (getPosASL _x) vectorAdd [0, 0, 4], _n, "relay", _r, _mb * (1 - 0.7 * damage _x), _ok];
        } forEach (missionNamespace getVariable ["COMSPEC_AtakRelays", []]);
        {
            _x params ["_o", "_m", "_r", "_mb", "_n"];
            _out pushBack [_o, (getPosASL _o) vectorAdd [0, 0, [3, 9] select (_m isEqualTo "cp")], _n, ["gw", "cp"] select (_m isEqualTo "cp"), _r, _mb, true];
        } forEach (["list"] call comspec_atak_native_fnc_vehicleGateway);
        _out
    };
    case "link": {
        _arg params ["_ant", "_range", "_mb", "_p"];
        private _d = _ant distance _p;
        if (_d > _range) exitWith { [0, 0, 100] };
        private _eff = _range;
        if (terrainIntersectASL [_ant, _p]) then { _eff = _eff * 0.35; };
        if (_d > _eff) exitWith { [0, 0, 100] };
        private _q = (1 - (_d / _eff) ^ 2) * (1 - 0.15 * rain);
        [_q, _mb * (0.45 + 0.55 * _q), round ((1 - _q) * 35)]
    };
    case "coverage": {
        if (missionNamespace getVariable ["COMSPEC_ATAK_CoverageBusy", false]) exitWith { ["INFO", "Calcul de couverture déjà en cours", 3, 20] call comspec_atak_native_fnc_notify; };
        private _relays = (["list"] call comspec_atak_native_fnc_relayNet) select { _x select 6 };
        if (_relays isEqualTo []) exitWith { ["WARNING", "Aucun relais intact ni passerelle de véhicule dans votre camp", 4, 20] call comspec_atak_native_fnc_notify; };
        private _radius = (uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["rcRadius", 5000];
        private _c = if (_arg isEqualType [] && {(count _arg) >= 2}) then { _arg } else { getPosASL player };
        missionNamespace setVariable ["COMSPEC_ATAK_CoverageBusy", true];
        missionNamespace setVariable ["COMSPEC_ATAK_CoverageProgress", 0];
        [_relays, _radius, [_c select 0, _c select 1]] spawn {
            params ["_relays", "_radius", "_center"];
            private _n = 41;
            private _half = floor (_n / 2);
            private _cell = (2 * _radius) / (_n - 1);
            private _jam = (missionNamespace getVariable ["COMSPEC_ATAK_Jammers", []]) select { ((_x param [4, 1e9]) >= ([time, serverTime] select isMultiplayer)) && {!((_x param [5, false]) && {(_x param [3, ""]) isEqualTo str side group player})} };
            private _cells = [];
            private _cov = 0;
            private _sum = 0;
            for "_i" from 0 to (_n - 1) do {
                for "_j" from 0 to (_n - 1) do {
                    private _dx = (_i - _half) * _cell;
                    private _dy = (_j - _half) * _cell;
                    if (sqrt (_dx * _dx + _dy * _dy) > _radius + _cell / 2) then { continue };
                    private _p2 = [(_center select 0) + _dx, (_center select 1) + _dy];
                    private _p = [_p2 select 0, _p2 select 1, ((getTerrainHeightASL _p2) max 0) + 1.7];
                    private _best = [0, 0, 100];
                    private _src = "";
                    {
                        _x params ["", "_ant", "_name", "", "_r", "_mb"];
                        if ((_ant distance2D _p) > _r) then { continue };
                        private _l = ["link", [_ant, _r, _mb, _p]] call comspec_atak_native_fnc_relayNet;
                        if ((_l select 1) > (_best select 1)) then { _best = _l; _src = _name; };
                    } forEach _relays;
                    private _mbps = _best select 1;
                    private _loss = _best select 2;
                    {
                        _x params [["_jp", [0, 0, 0]], ["_jr", 500]];
                        if (_jp isEqualType objNull) then { _jp = getPosATL _jp; };
                        private _d = _p distance2D _jp;
                        if (_d < _jr) then { private _m = (_d / _jr) ^ 2; _mbps = _mbps * _m; _loss = _loss max (round ((1 - _m) * 80)); };
                    } forEach _jam;
                    if (_mbps > 0.05) then { _cov = _cov + 1; _sum = _sum + _mbps; } else { _mbps = 0; _loss = 100; };
                    _cells pushBack [_p, _mbps, _loss, _src];
                };
                missionNamespace setVariable ["COMSPEC_ATAK_CoverageProgress", (_i + 1) / _n];
            };
            private _pct = round (100 * _cov / ((count _cells) max 1));
            missionNamespace setVariable ["COMSPEC_ATAK_Coverage", [_center, _cell, _cells, _radius, _pct, dayTime, count _relays, [0, _sum / (_cov max 1)] select (_cov > 0)]];
            missionNamespace setVariable ["COMSPEC_ATAK_CoverageBusy", false];
            profileNamespace setVariable ["COMSPEC_ATAK_MeshOnMap", true];
            ["SUCCESS", format ["Couverture calculée : %1 %% du terrain reçoit le réseau sur %2 km", _pct, round (_radius / 1000)], 4, 30] call comspec_atak_native_fnc_notify;
            if (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isEqualTo "WAVERELAY") then { [{ ["WAVERELAY"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame; };
        };
    };
    case "at": {
        private _cv = missionNamespace getVariable ["COMSPEC_ATAK_Coverage", []];
        if ((count _cv) < 8) exitWith { [] };
        _cv params ["_center", "_cell", "_cells", "_radius"];
        if ((_arg distance2D _center) > _radius + _cell / 2) exitWith { [] };
        // Coins hors cercle sautés au calcul : on cherche la case la plus proche.
        private _best = [];
        private _bd = 1e9;
        {
            private _d = (_x select 0) distance2D _arg;
            if (_d < _bd) then { _bd = _d; _best = _x; };
            if (_d < _cell / 2) exitWith {};
        } forEach _cells;
        if (_best isEqualTo [] || {_bd > _cell}) exitWith { [] };
        [_best select 1, _best select 2, _best select 3]
    };
    case "clear": { missionNamespace setVariable ["COMSPEC_ATAK_Coverage", nil]; };
    case "trilatClear": { missionNamespace setVariable ["COMSPEC_ATAK_Trilat", []]; };
    case "locate": {
        private _t = _arg;
        if (_t isEqualType "") then { _t = objectFromNetId _t; };
        if (isNull _t) exitWith { ["WARNING", "Appareil introuvable", 3, 20] call comspec_atak_native_fnc_notify; };
        private _name = if (_t isKindOf "CAManBase") then { [_t, true] call comspec_atak_native_fnc_unitCallsign } else { getText (configOf _t >> "displayName") };
        private _tp = if (_t isKindOf "CAManBase") then { (eyePos _t) vectorAdd [0, 0, 0.5] } else { (getPosASL _t) vectorAdd [0, 0, 0.3] };
        // Sources : relais intacts et passerelles, puis téléphones du maillage de mon camp (sauf la cible).
        private _src = ((["list"] call comspec_atak_native_fnc_relayNet) select { _x select 6 }) apply { [_x select 1, _x select 4, _x select 5] };
        private _range = missionNamespace getVariable ["COMSPEC_ATAK_MeshRange", 3000];
        private _side = side group player;
        {
            _src pushBack [(eyePos _x) vectorAdd [0, 0, 0.5], _range, 4];
        } forEach ((allPlayers select { alive _x && {_x isNotEqualTo _t} && {side group _x isEqualTo _side} && {[_x] call comspec_atak_native_fnc_hasDevice} }) select [0, 63]);
        private _heard = [];
        {
            _x params ["_ant", "_r", "_mb"];
            private _l = ["link", [_ant, _r, _mb, _tp]] call comspec_atak_native_fnc_relayNet;
            private _q = _l select 0;
            if (_q <= 0) then { continue };
            // Distance lue sur la puissance reçue : erreur de 5 % (lien franc) à 25 % (lien faible).
            private _d = _ant distance2D _tp;
            private _err = 0.05 + 0.2 * (1 - _q);
            _heard pushBack [_ant, (_d * (1 + (random [-1, 0, 1]) * _err)) max 5, _err];
        } forEach _src;
        private _cnt = count _heard;
        if (_cnt isEqualTo 0) exitWith { ["WARNING", format ["%1 : aucun relais ni téléphone ne l'entend", _name], 4, 20] call comspec_atak_native_fnc_notify; };
        // Moindres carrés : on part du barycentre des sources (décalé pour 1 ou 2 sources) puis descente de gradient.
        private _est = [0, 0];
        { _est = _est vectorAdd [(_x select 0) select 0, (_x select 0) select 1]; } forEach _heard;
        _est = _est vectorMultiply (1 / _cnt);
        if (_cnt < 3) then { _est = _est vectorAdd [random [-50, 0, 50], random [-50, 0, 50]]; };
        for "_k" from 1 to 80 do {
            private _g = [0, 0];
            {
                _x params ["_a", "_dm"];
                private _v = _est vectorDiff [_a select 0, _a select 1];
                private _len = (vectorMagnitude _v) max 0.1;
                _g = _g vectorAdd (_v vectorMultiply ((_len - _dm) / _len));
            } forEach _heard;
            _est = _est vectorDiff (_g vectorMultiply (0.5 / _cnt));
        };
        private _res = 0;
        { _res = _res + (((_est distance2D [(_x select 0) select 0, (_x select 0) select 1]) - (_x select 1)) ^ 2); } forEach _heard;
        private _meanD = 0;
        { _meanD = _meanD + (_x select 1) * (_x select 2); } forEach _heard;
        private _rad = ((sqrt (_res / _cnt)) + (_meanD / _cnt) / sqrt _cnt) max 15;
        // Une source : seulement un cercle ; deux : deux points possibles, rayon élargi.
        private _pos = [];
        if (_cnt >= 2) then { _pos = [_est select 0, _est select 1, 0]; };
        if (_cnt isEqualTo 2) then { _rad = _rad * 2.5; };
        private _list = (missionNamespace getVariable ["COMSPEC_ATAK_Trilat", []]) select { (_x select 5) isNotEqualTo (netId _t) && {_now - (_x select 4) < 600} };
        _list pushBack [_name, _pos, round _rad, _heard apply { [_x select 0, _x select 1] }, _now, netId _t];
        missionNamespace setVariable ["COMSPEC_ATAK_Trilat", _list select [((count _list) - 10) max 0, 10]];
        ["SUCCESS", switch (true) do {
            case (_cnt isEqualTo 1): { format ["%1 : une seule source l'entend, cercle de %2 m", _name, round ((_heard select 0) select 1)] };
            case (_cnt isEqualTo 2): { format ["%1 : deux sources, position ambiguë à %2 m près", _name, round _rad] };
            default { format ["%1 localisé par %2 sources à %3 m près (%4)", _name, _cnt, round _rad, [_pos, 8] call comspec_atak_native_fnc_gridRef] };
        }, 5, 30] call comspec_atak_native_fnc_notify;
        _pos
    };
};
