/*
    Accroche le Super ping : dessin, SHIFT+clic, relevé des pings du poste.
*/
if (!hasInterface) exitWith {};
if (missionNamespace getVariable ["COMSPEC_SuperPingHooked", false]) exitWith {};
missionNamespace setVariable ["COMSPEC_SuperPingHooked", true, false];
missionNamespace setVariable ["COMSPEC_SuperPingPulses", [], false];
missionNamespace setVariable ["COMSPEC_SuperPingSeen", [], false];
missionNamespace setVariable ["COMSPEC_SuperPingPrimed", false, false];

private _attachVanilla = {
    if !(visibleMap) exitWith {};
    private _disp = findDisplay 12;
    if (isNull _disp) exitWith {};
    private _map = _disp displayCtrl 51;
    if (isNull _map) exitWith {};
    if (_map getVariable ["COMSPEC_SuperPingDraw", false]) exitWith {};
    _map ctrlAddEventHandler ["Draw", {
        [_this select 0] call comspec_overwatch_connect_fnc_superPingDraw;
    }];
    _map ctrlAddEventHandler ["MouseButtonDown", {
        params ["_mapCtrl", "_button", "_xC", "_yC", ["_shift", false]];
        if (_button != 0 || {!_shift}) exitWith { false };
        private _world = _mapCtrl ctrlMapScreenToWorld [_xC, _yC];
        [_world] call comspec_overwatch_connect_fnc_superPingSend
    }];
    _map setVariable ["COMSPEC_SuperPingDraw", true];
};

[] call _attachVanilla;
localNamespace setVariable ["COMSPEC_SuperPingAttachVanilla", _attachVanilla];
[] call comspec_overwatch_connect_fnc_superPingPoll;
[{
    [] call (localNamespace getVariable ["COMSPEC_SuperPingAttachVanilla", {}]);
    // Pulses expirés (> 7 s) purgés ici aussi : seul le Draw de la carte vanilla les retirait, et un pulse
    // resté en liste gardait le relevé GetPings actif toute la mission.
    private _pulses = missionNamespace getVariable ["COMSPEC_SuperPingPulses", []];
    if (_pulses isEqualType [] && {(count _pulses) > 0}) then {
        private _now = diag_tickTime;
        private _live = _pulses select { _x isEqualType [] && {(count _x) >= 4} && {(_now - (_x select 3)) <= 7} };
        if ((count _live) != (count _pulses)) then {
            missionNamespace setVariable ["COMSPEC_SuperPingPulses", _live, false];
        };
    };
    private _open = !(isNil "cTabIfOpen")
        || {!isNull (findDisplay 9973)}
        || {!isNull (findDisplay 9974)}
        || {visibleMap}
        || {((count (missionNamespace getVariable ["COMSPEC_SuperPingPulses", []])) > 0)};
    if (!_open) exitWith {};
    [] call comspec_overwatch_connect_fnc_superPingPoll;
}, 1.6, []] call CBA_fnc_addPerFrameHandler;
