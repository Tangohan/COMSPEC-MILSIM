/*
    Applique les couches locales aux marqueurs (vue du joueur, pas les modules Zeus).
*/
if (!hasInterface) exitWith {};
private _layers = missionNamespace getVariable ["COMSPEC_MapLayers", createHashMap];
if (!(_layers isEqualType createHashMap)) then { _layers = createHashMap; };
private _filter = missionNamespace getVariable ["COMSPEC_MapFilter", "ALL"];
private _grp = group player;
private _showEnemy = _layers getOrDefault ["enemy_ai", true];

private _prevLocal = missionNamespace getVariable ["COMSPEC_LocalEnemyMk", []];
if (!(_prevLocal isEqualType [])) then { _prevLocal = []; };
if (!_showEnemy) then {
    { deleteMarkerLocal _x; } forEach _prevLocal;
    missionNamespace setVariable ["COMSPEC_LocalEnemyMk", [], false];
} else {
    private _last = missionNamespace getVariable ["COMSPEC_LocalEnemyMkAt", -10];
    if (diag_tickTime - _last > 1.6) then {
        missionNamespace setVariable ["COMSPEC_LocalEnemyMkAt", diag_tickTime, false];
        private _wanted = [];
        private _origin = getPosATL player;
        {
            if ((count _wanted) >= 24) then { break };
            if (!([side player, side _x] call BIS_fnc_sideIsEnemy)) then { continue };
            private _leader = leader _x;
            if (isNull _leader || {!alive _leader}) then { continue };
            if (isPlayer _leader) then { continue };
            if (!(_leader isKindOf "CAManBase")) then { continue };
            if ((_leader distance2D _origin) > 2500) then { continue };
            _wanted pushBack (getPosATL _leader);
        } forEach allGroups;
        private _created = [];
        private _i = 0;
        {
            private _id = format ["comspec_ai_local_%1", _i];
            if ((allMapMarkers find _id) < 0) then {
                createMarkerLocal [_id, _x];
                _id setMarkerTypeLocal "o_inf";
                _id setMarkerColorLocal "ColorRed";
                _id setMarkerSizeLocal [0.7, 0.7];
                _id setMarkerTextLocal "Contact";
            } else {
                _id setMarkerPosLocal _x;
            };
            _id setMarkerAlphaLocal 1;
            _created pushBack _id;
            _i = _i + 1;
        } forEach _wanted;
        {
            if (!(_x in _created)) then { deleteMarkerLocal _x; };
        } forEach _prevLocal;
        missionNamespace setVariable ["COMSPEC_LocalEnemyMk", _created, false];
    };
};

{
    private _name = _x;
    private _low = toLower _name;
    private _txt = toLower (markerText _name);
    private _col = toLower (markerColor _name);
    private _isTracked = (_low find "comspec") >= 0
        || {(_low find "_comspec") >= 0}
        || {(_low find "qrf_contact_") == 0}
        || {(_low find "ctab_u_") == 0};
    if (!_isTracked) then { continue };

    private _kind = "player_markers";
    if ((_low find "comspec_relay_") == 0) then { _kind = "relays"; };
    if ((_low find "comspec_recon_") == 0) then { _kind = "recon_notes"; };
    if ((_low find "comspec_roleplay_zone_") == 0) then { _kind = "network_zones"; };
    if ((_low find "comspec_ai_local_") == 0 || {(_low find "qrf_contact_") == 0}) then { _kind = "enemy_ai"; };
    if ((_low find "photo") >= 0) then { _kind = "photos"; };
    if ((_low find "intel") >= 0 || {(_low find "ping") >= 0} || {(_low find "sitrep") >= 0}) then { _kind = "intel"; };
    if ((_low find "jtac") >= 0 || {(_low find "nine") >= 0} || {(_low find "laser") >= 0}) then { _kind = "jtac"; };
    if ((_low find "cas") >= 0) then { _kind = "cas"; };
    if (_kind isEqualTo "player_markers" && {(_low find "zone") >= 0 || {(_low find "obj") >= 0}}) then { _kind = "objectives"; };
    if ((_low find "route") >= 0) then { _kind = "athena"; };
    if ((_low find "sigint") >= 0) then { _kind = "sigint"; };
    if ((_low find "log") >= 0) then { _kind = "logistics"; };
    if ((_low find "ctab_u_") == 0) then {
        if ((_col find "east") >= 0 || {(_col find "red") >= 0} || {(_txt find "eny") >= 0}) then {
            _kind = "enemy_ai";
        } else {
            if ((_col find "west") >= 0 || {(_col find "blue") >= 0} || {(_col find "green") >= 0}) then {
                _kind = "units";
            };
        };
    };

    private _on = _layers getOrDefault [_kind, true];
    if (_filter isEqualTo "INTEL" && {!(_kind in ["intel", "photos", "athena"])}) then { _on = false; };
    if (_filter isEqualTo "AIR" && {!(_kind in ["cas", "jtac"])}) then { _on = false; };
    if (_filter isEqualTo "JTAC" && {!(_kind in ["jtac", "cas"])}) then { _on = false; };
    if (_filter isEqualTo "MY GROUP" && {_isTracked}) then {
        if ((_txt find (toLower (groupId _grp))) < 0) then { _on = false; };
    };
    private _stored = missionNamespace getVariable ["COMSPEC_LayerAlphaOrig", createHashMap];
    if (!(_stored isEqualType createHashMap)) then { _stored = createHashMap; };
    private _curA = markerAlpha _name;
    if (_curA > 0.05 && {!(_name in _stored)}) then {
        _stored set [_name, _curA];
    };
    missionNamespace setVariable ["COMSPEC_LayerAlphaOrig", _stored, false];
    private _alphaOn = _stored getOrDefault [_name, [1, 0.42] select (_kind isEqualTo "network_zones")];
    _name setMarkerAlphaLocal ([0, _alphaOn] select _on);
} forEach (+allMapMarkers);
