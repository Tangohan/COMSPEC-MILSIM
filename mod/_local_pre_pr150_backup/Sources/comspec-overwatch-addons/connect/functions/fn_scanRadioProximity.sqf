/*
    Liste les contacts / émissions dans un rayon autour d’un point de référence.
    Retourne tableau de hashMaps :
      callsign, steam_uid, dist, speaking, tx, channel, net, freq, radio_id
    Point de référence :
      - objet COMSPEC_RadioWatchFocus s’il est valide
      - sinon indicatif COMSPEC_RadioWatchFocusCs (joueur correspondant)
      - sinon le joueur local
*/
if (!hasInterface) exitWith { [] };

private _radius = missionNamespace getVariable ["comspec_overwatch_radio_proximity_radius", 75];
if (!(_radius isEqualType 0)) then { _radius = 75; };
_radius = (_radius max 10) min 500;

private _origin = player;
private _focusObj = missionNamespace getVariable ["COMSPEC_RadioWatchFocus", objNull];
if (!isNull _focusObj && {alive _focusObj}) then {
    _origin = _focusObj;
} else {
    private _focusCs = missionNamespace getVariable ["COMSPEC_RadioWatchFocusCs", ""];
    if (_focusCs isEqualType "" && {_focusCs != ""}) then {
        private _match = objNull;
        {
            private _cs = _x getVariable ["COMSPEC_Callsign", ""];
            if (_cs == "") then { _cs = name _x; };
            if ((toLower _cs) isEqualTo (toLower _focusCs)) exitWith { _match = _x; };
        } forEach allPlayers;
        if (!isNull _match) then { _origin = _match; };
    };
};

private _originPos = getPosASL _origin;
private _out = [];

{
    private _u = _x;
    if (isNull _u || {!alive _u}) then { continue };
    private _dist = _originPos distance (getPosASL _u);
    if (_dist > _radius) then { continue };

    private _txState = [_u] call comspec_overwatch_connect_fnc_getRadioTxState;
    _txState params ["_net", "_freq", "_channel", "_speaking", "_tx", "_radioId", "_moduleOk"];

    private _cs = if (_u isEqualTo player) then {
        [] call comspec_overwatch_connect_fnc_getCallsign
    } else {
        private _c = _u getVariable ["COMSPEC_Callsign", ""];
        if (_c == "") then { _c = name _u; };
        _c
    };

    _out pushBack (createHashMapFromArray [
        ["callsign", _cs],
        ["steam_uid", getPlayerUID _u],
        ["dist", round _dist],
        ["speaking", _speaking],
        ["tx", _tx],
        ["channel", _channel],
        ["net", _net],
        ["freq", _freq],
        ["radio_id", _radioId],
        ["module_ok", _moduleOk],
        ["self", _u isEqualTo player]
    ]);
} forEach (allPlayers select { alive _x && {!isNull _x} });

_out = [_out, [], { _x getOrDefault ["dist", 9999] }, "ASCEND"] call BIS_fnc_sortBy;
_out
