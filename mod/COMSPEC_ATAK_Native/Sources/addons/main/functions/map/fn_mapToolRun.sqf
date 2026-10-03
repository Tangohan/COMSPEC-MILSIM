/*
    Outils carte à clic (résultats locaux, dessinés par mapOnDraw, effacés par « Tout effacer ») :
    HOUSES : numérote les bâtiments enterables autour du clic (CQB).
    HEIGHT : relève l'altitude du point et l'écart avec le joueur.
    FLAT   : cherche des zones plates et dégagées (posé hélico) autour du clic.
    LOS    : ligne de vue depuis les yeux du joueur vers le point (+1,7 m).
*/
params ["_tool", "_pos"];
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
switch (_tool) do {
    case "HOUSES": {
        private _houses = (nearestObjects [_pos, ["House", "Building"], 150]) select { (count (_x buildingPos -1)) > 0 && {!isObjectHidden _x} };
        _houses = _houses select [0, 40];
        private _keyed = _houses apply { [round (((getPosASL _x) select 1) / -25), (getPosASL _x) select 0, _x] };
        _keyed sort true;
        private _list = [];
        { _list pushBack [_x select 2, format ["B%1", _forEachIndex + 1]]; } forEach _keyed;
        _s set ["mapHouses", _list];
        ["TACTICAL", format ["%1 bâtiments numérotés", count _list], 3, 20] call comspec_atak_native_fnc_notify;
    };
    case "HEIGHT": {
        private _alt = getTerrainHeightASL _pos;
        private _list = +(_s getOrDefault ["mapHeights", []]);
        _list pushBack [_pos, _alt, _alt - ((getPosASL player) select 2)];
        _s set ["mapHeights", _list select [((count _list) - 12) max 0]];
    };
    case "FLAT": {
        // Clic sur une LZ déjà trouvée : elle devient un vrai marqueur (partagé, envoyé au web, supprimable).
        private _cur = +(_s getOrDefault ["mapFlat", []]);
        private _hit = _cur findIf { (_x distance2D _pos) < 30 };
        if (_hit >= 0) exitWith {
            private _lz = _cur deleteAt _hit;
            _s set ["mapFlat", _cur];
            private _index = (missionNamespace getVariable ["COMSPEC_ATAK_MarkerIndex", 0]) + 1;
            missionNamespace setVariable ["COMSPEC_ATAK_MarkerIndex", _index];
            private _m = createMarker [format ["_USER_DEFINED #%1/%2/%3", clientOwner, 9000 + _index, currentChannel], _lz, currentChannel, player];
            if (_m isEqualTo "") exitWith { ["WARNING", "Marqueur refusé sur ce canal", 3, 20] call comspec_atak_native_fnc_notify; };
            _m setMarkerTypeLocal (["mil_pickup", "mil_flag"] select !(isClass (configFile >> "CfgMarkers" >> "mil_pickup")));
            _m setMarkerColorLocal "ColorGreen";
            _m setMarkerText format ["LZ %1", _index];
            [_m] call comspec_atak_native_fnc_markerWeb;
            ["SUCCESS", format ["LZ %1 posée en marqueur · %2", _index, [_lz, 8] call comspec_atak_native_fnc_gridRef], 3, 20] call comspec_atak_native_fnc_notify;
        };
        private _found = [];
        for "_r" from 0 to 200 step 25 do {
            private _n = [1, round (_r / 6)] select (_r > 0);
            for "_i" from 0 to (_n - 1) do {
                private _p = _pos getPos [_r, _i * 360 / _n];
                if ((count (_p isFlatEmpty [7, -1, 0.12, 7, 0, false, objNull])) > 0 && {_found findIf { (_x distance2D _p) < 40 } < 0}) then { _found pushBack _p; };
                if ((count _found) >= 5) exitWith {};
            };
            if ((count _found) >= 5) exitWith {};
        };
        _s set ["mapFlat", _found];
        ["TACTICAL", ["Aucune zone plate trouvée à 200 m", format ["%1 zone(s) plate(s) trouvée(s)", count _found]] select ((count _found) > 0), 3, 20] call comspec_atak_native_fnc_notify;
    };
    case "LOS": {
        private _from = eyePos player;
        private _to = AGLToASL [_pos select 0, _pos select 1, 1.7];
        private _hits = lineIntersectsSurfaces [_from, _to, player, vehicle player, true, 1, "VIEW", "FIRE"];
        private _terrain = terrainIntersectAtASL [_from, _to];
        private _block = [];
        if ((count _hits) > 0) then { _block = (_hits select 0) select 0; };
        if (_terrain isNotEqualTo [0, 0, 0] && {(count _block) isEqualTo 0 || {(_from distance _terrain) < (_from distance _block)}}) then { _block = _terrain; };
        _s set ["mapLos", [ASLToAGL _from, _pos, _block]];
        ["TACTICAL", ["Ligne de vue dégagée", format ["Vue bloquée à %1 m", round (_from distance2D _block)]] select ((count _block) > 0), 3, 20] call comspec_atak_native_fnc_notify;
    };
};
true
