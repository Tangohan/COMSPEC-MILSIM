/*
    Relais client : balises GPS, téléphones PNJ, IA alliées ATAK, contacts ennemis si demandés.
    Un seul joueur (indicatif Steam le plus petit) évite les doublons.
*/
if (!hasInterface) exitWith {};
if (missionNamespace getVariable ["COMSPEC_GpsBeaconsHooked", false]) exitWith {};
missionNamespace setVariable ["COMSPEC_GpsBeaconsHooked", true, false];

private _isBridge = {
    if (isNull player) exitWith { false };
    if (!(missionNamespace getVariable ["COMSPEC_AthenaReady", false])) exitWith { false };
    private _uids = [];
    {
        if (isPlayer _x) then {
            private _u = getPlayerUID _x;
            if (_u isNotEqualTo "") then { _uids pushBack _u };
        };
    } forEach allPlayers;
    if (_uids isEqualTo []) exitWith { true };
    _uids sort true;
    (getPlayerUID player) isEqualTo (_uids select 0)
};

missionNamespace setVariable ["COMSPEC_GpsBeaconIsBridge", _isBridge, false];

private _scan = {
    private _vehs = missionNamespace getVariable ["COMSPEC_GpsBeaconObjects", []];
    if (!(_vehs isEqualType [])) then { _vehs = []; };
    {
        if (isNull _x) then { continue };
        if ([_x, "COMSPEC_GpsBeacon"] call comspec_overwatch_connect_fnc_isObjectFlag) then {
            _vehs pushBackUnique _x;
        };
    } forEach vehicles;
    _vehs = _vehs select { !isNull _x };
    missionNamespace setVariable ["COMSPEC_GpsBeaconObjects", _vehs, false];

    private _phones = missionNamespace getVariable ["COMSPEC_PhoneTrackUnits", []];
    if (!(_phones isEqualType [])) then { _phones = []; };
    private _allies = missionNamespace getVariable ["COMSPEC_AllyTrackUnits", []];
    if (!(_allies isEqualType [])) then { _allies = []; };
    private _allyIds = missionNamespace getVariable ["COMSPEC_AllyTrackNetIds", []];
    if (!(_allyIds isEqualType [])) then { _allyIds = []; };
    {
        if (isNull _x || {!alive _x}) then { continue };
        if (isPlayer _x) then { continue };
        private _nid = netId _x;
        private _listed = _nid in _allyIds;
        if (_listed && {!([_x, "COMSPEC_AllyTrack"] call comspec_overwatch_connect_fnc_isObjectFlag)}) then {
            _x setVariable ["COMSPEC_AllyTrack", true, true];
        };
        if ([_x, "COMSPEC_AllyTrack"] call comspec_overwatch_connect_fnc_isObjectFlag || {_listed}) then {
            _allies pushBackUnique _x;
            _x enableDynamicSimulation false;
        };
        if ([_x, "COMSPEC_PhoneTrack"] call comspec_overwatch_connect_fnc_isObjectFlag) then {
            _phones pushBackUnique _x;
        };
    } forEach allUnits;
    {
        if (!(_x isEqualType "")) then { continue };
        private _obj = objectFromNetId _x;
        if (isNull _obj || {!alive _obj} || {isPlayer _obj}) then { continue };
        _allies pushBackUnique _obj;
        _obj enableDynamicSimulation false;
    } forEach _allyIds;
    _phones = _phones select { !isNull _x && {alive _x} };
    _allies = _allies select { !isNull _x && {alive _x} };
    missionNamespace setVariable ["COMSPEC_PhoneTrackUnits", _phones, false];
    missionNamespace setVariable ["COMSPEC_AllyTrackUnits", _allies, false];
};
missionNamespace setVariable ["COMSPEC_GpsBeaconScan", _scan, false];

[] call _scan;
[{ [] call (missionNamespace getVariable ["COMSPEC_GpsBeaconScan", {}]); }, [], 3] call CBA_fnc_waitAndExecute;

[{
    if (missionNamespace getVariable ["COMSPEC_DisconnectSent", false]) exitWith {};
    [] call (missionNamespace getVariable ["COMSPEC_GpsBeaconScan", {}]);
    if (!(missionNamespace getVariable ["COMSPEC_AthenaReady", false])) exitWith {};

    private _vehs = missionNamespace getVariable ["COMSPEC_GpsBeaconObjects", []];
    if (_vehs isEqualType []) then {
        private _keep = [];
        {
            if (isNull _x) then { continue };
            _keep pushBack _x;
            if !([_x] call comspec_overwatch_connect_fnc_isNearestAtakReporter) then { continue };
            [_x] call comspec_overwatch_connect_fnc_updateVehicleTracking;
            [_x] call comspec_overwatch_connect_fnc_reportGpsBeacon;
        } forEach _vehs;
        if ((count _keep) isNotEqualTo (count _vehs)) then {
            missionNamespace setVariable ["COMSPEC_GpsBeaconObjects", _keep, false];
        };
    };

    private _allies = missionNamespace getVariable ["COMSPEC_AllyTrackUnits", []];
    private _seenNids = [];
    if (_allies isEqualType []) then {
        private _keepA = [];
        {
            if (isNull _x || {!alive _x}) then { continue };
            if (isPlayer _x) then { continue };
            if !([_x, "COMSPEC_AllyTrack"] call comspec_overwatch_connect_fnc_isObjectFlag) then { continue };
            _keepA pushBack _x;
            _seenNids pushBackUnique (netId _x);
            if !([_x] call comspec_overwatch_connect_fnc_isNearestAtakReporter) then { continue };
            [_x] call comspec_overwatch_connect_fnc_reportAllyPosition;
        } forEach _allies;
        if ((count _keepA) isNotEqualTo (count _allies)) then {
            missionNamespace setVariable ["COMSPEC_AllyTrackUnits", _keepA, false];
        };
    };

    if (call (missionNamespace getVariable ["COMSPEC_GpsBeaconIsBridge", { false }])) then {
        private _snaps = missionNamespace getVariable ["COMSPEC_AllyTrackSnapshots", []];
        if (_snaps isEqualType []) then {
            {
                if (!(_x isEqualType []) || {(count _x) < 5}) then { continue };
                _x params ["_nid", "_px", "_py", "_dir", "_asl"];
                if (_nid in _seenNids) then { continue };
                [_nid, _px, _py, _dir, _asl] call comspec_overwatch_connect_fnc_reportAllySnapshot;
            } forEach _snaps;
        };
    };

    private _phones = missionNamespace getVariable ["COMSPEC_PhoneTrackUnits", []];
    if (_phones isEqualType []) then {
        private _keepU = [];
        {
            if (isNull _x || {!alive _x}) then { continue };
            if (isPlayer _x) then { continue };
            if ([_x, "COMSPEC_AllyTrack"] call comspec_overwatch_connect_fnc_isObjectFlag) then { continue };
            _keepU pushBack _x;
            if !([_x] call comspec_overwatch_connect_fnc_isNearestAtakReporter) then { continue };
            [_x] call comspec_overwatch_connect_fnc_reportPhonePosition;
        } forEach _phones;
        if ((count _keepU) isNotEqualTo (count _phones)) then {
            missionNamespace setVariable ["COMSPEC_PhoneTrackUnits", _keepU, false];
        };
    };

    if (missionNamespace getVariable ["COMSPEC_AtakShowEnemyAi", false]) then {
        if (!isNil "comspec_overwatch_connect_fnc_reportEnemyAiPositions") then {
            [] call comspec_overwatch_connect_fnc_reportEnemyAiPositions;
        };
    };

    if (!isNil "comspec_overwatch_connect_fnc_reportCrewedAirAssets") then {
        [] call comspec_overwatch_connect_fnc_reportCrewedAirAssets;
    };
}, 3, []] call CBA_fnc_addPerFrameHandler;

[{
    if (missionNamespace getVariable ["COMSPEC_DisconnectSent", false]) exitWith {};
    [] call (missionNamespace getVariable ["COMSPEC_GpsBeaconScan", {}]);
}, 60, []] call CBA_fnc_addPerFrameHandler;

["INFO", "Tracking", "Balises GPS, téléphones, IA alliées et contacts ennemis ATAK actifs"] call comspec_overwatch_connect_fnc_log;
