/*
    onDraw de la carte native tablette (idc 9410) : icônes unités + libellés.
*/
params ["_ctrl"];

if (isNull _ctrl) exitWith {};
if !(missionNamespace getVariable ["COMSPEC_WebBrowser_MapVisible", false]) exitWith {};

private _showNames = missionNamespace getVariable ["COMSPEC_WebBrowser_MapShowNames", true];
private _units = missionNamespace getVariable ["COMSPEC_WebBrowser_MapUnits", []];

{
    if ((_x isEqualType []) && {(count _x) >= 5}) then {
        _x params ["_cs", "_gx", "_gy", ["_isSelf", false], ["_wx", 0], ["_wy", 0], ["_role", ""]];
        if (!(_wx isEqualTo 0 && {_wy isEqualTo 0})) then {
            private _pos = [_wx, _wy];
            private _icon = if (_isSelf) then {
                "\A3\ui_f\data\map\vehicleicons\iconManVirtual_ca.paa"
            } else {
                if (
                    ((toLower _role) isEqualTo "telephone")
                    || {(toLower _role) isEqualTo "téléphone"}
                    || {((toLower _cs) find "tél.") == 0}
                    || {((toLower _cs) find "tel.") == 0}
                ) then {
                    "\a3\ui_f\data\igui\cfg\simpletasks\types\radio_ca.paa"
                } else {
                    "\A3\ui_f\data\map\vehicleicons\iconMan_ca.paa"
                }
            };
            private _color = if (_isSelf) then {
                [0.05, 0.85, 0.25, 1]
            } else {
                if (
                    ((toLower _role) isEqualTo "telephone")
                    || {(toLower _role) isEqualTo "téléphone"}
                    || {((toLower _cs) find "tél.") == 0}
                    || {((toLower _cs) find "tel.") == 0}
                ) then {
                    [0.09, 0.75, 0.85, 1]
                } else {
                    [0.15, 0.35, 0.95, 1]
                }
            };
            private _label = if (_showNames) then {
                if (_role isEqualTo "") then { _cs } else { format ["%1 - %2", _cs, _role] }
            } else {
                ""
            };

            _ctrl drawIcon [
                _icon,
                _color,
                _pos,
                22,
                22,
                0,
                _label,
                1,
                0.028,
                "RobotoCondensedBold",
                "right"
            ];
        };
    };
} forEach _units;
