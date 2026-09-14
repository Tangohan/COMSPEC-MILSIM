/*
    Sélectionne (ou retire) un contact pour la zone de déplacement possible.
    Params : [_rowOrCs, _toggle]
*/
params [["_src", ""], ["_toggle", true]];

private _row = [];
if (_src isEqualType []) then {
    _row = _src;
} else {
    private _key = toLower (trim str _src);
    if (_key isEqualTo "") exitWith {};
    private _rows = missionNamespace getVariable ["COMSPEC_ReachCache", []];
    {
        if ((_x isEqualType []) && {(count _x) >= 1} && {(toLower (_x select 0)) isEqualTo _key}) exitWith { _row = _x };
    } forEach _rows;
};

if ((count _row) < 1) exitWith {
    missionNamespace setVariable ["COMSPEC_ReachSelectedCs", "", false];
    missionNamespace setVariable ["COMSPEC_ReachSelectedRow", [], false];
    [] call comspec_overwatch_connect_fnc_reachOverlayUpdateMarkers;
    false
};

private _cs = _row select 0;
private _cur = missionNamespace getVariable ["COMSPEC_ReachSelectedCs", ""];
if (_toggle && {(toLower _cs) isEqualTo (toLower _cur)}) then {
    missionNamespace setVariable ["COMSPEC_ReachSelectedCs", "", false];
    missionNamespace setVariable ["COMSPEC_ReachSelectedRow", [], false];
    [] call comspec_overwatch_connect_fnc_reachOverlayUpdateMarkers;
    false
} else {
    missionNamespace setVariable ["COMSPEC_ReachSelectedCs", _cs, false];
    missionNamespace setVariable ["COMSPEC_ReachSelectedRow", _row, false];
    [] call comspec_overwatch_connect_fnc_reachOverlayUpdateMarkers;
    if ((count _row) >= 6) then {
        private _wx = _row select 4;
        private _wy = _row select 5;
        if ((abs _wx) >= 1 || {(abs _wy) >= 1}) then {
            {
                private _disp = uiNamespace getVariable [_x, displayNull];
                if (isNull _disp) then { continue };
                private _map = controlNull;
                {
                    private _c = _disp displayCtrl _x;
                    if (!isNull _c && {ctrlType _c == 101}) exitWith { _map = _c; };
                } forEach [1200, 1201, 1773, 10, 50, 51, 100, 26109, 26110, 9410];
                if (isNull _map) then { continue };
                _map ctrlMapAnimAdd [0.35, ctrlMapScale _map, [_wx, _wy]];
                ctrlMapAnimCommit _map;
            } forEach ["cTab_Android_dlg", "cTab_Tablet_dlg", "cTab_microDAGR_dlg"];
            private _native = findDisplay 9974;
            if (isNull _native) then { _native = findDisplay 9973; };
            if (!isNull _native) then {
                private _nmap = _native displayCtrl 9410;
                if (!isNull _nmap && {ctrlShown _nmap}) then {
                    _nmap ctrlMapAnimAdd [0.35, ctrlMapScale _nmap, [_wx, _wy]];
                    ctrlMapAnimCommit _nmap;
                };
            };
        };
    };
    true
};
