/*
    État du signal Relais pour le téléphone (barres, couleur, libellé).
    Reflète le mât le plus proche, la portée, la destruction et le brouillage.
    Retour : HashMap bars (0-4), color, color_html, tip, jammed, destroyed, in_range, name
*/
private _out = createHashMap;
_out set ["bars", -1];
_out set ["color", [1, 0.32, 0.26, 1]];
_out set ["color_html", "#FF8A7A"];
_out set ["tip", "Aucun mât Relais"];
_out set ["jammed", false];
_out set ["destroyed", false];
_out set ["in_range", false];
_out set ["name", ""];
if (!hasInterface) exitWith { _out };

private _jammed = false;
private _degradedZone = false;
private _zoneName = "";
private _zoneFx = missionNamespace getVariable ["COMSPEC_ZoneEffects", nil];
if (!isNil "_zoneFx" && {_zoneFx isEqualType createHashMap}) then {
    _jammed = _zoneFx getOrDefault ["force_disconnect", false];
    _zoneName = _zoneFx getOrDefault ["name", ""];
    private _zt = toLower (_zoneFx getOrDefault ["type", ""]);
    if (!_jammed) then {
        _jammed = _zt in ["jammer", "no_coverage"];
    };
    _degradedZone = (!_jammed) && {_zt in ["interference", "degraded"]};
};

private _info = createHashMap;
if (!isNil "comspec_overwatch_connect_fnc_getNearestAtakRelay") then {
    _info = [] call comspec_overwatch_connect_fnc_getNearestAtakRelay;
};
if (!(_info isEqualType createHashMap) || {(count (keys _info)) < 1}) exitWith {
    if (_jammed) then {
        _out set ["bars", 0];
        _out set ["jammed", true];
        _out set ["tip", if (_zoneName isNotEqualTo "") then {
            format ["Zone brouillée — %1", _zoneName]
        } else {
            "Zone brouillée — hors couverture"
        }];
    };
    _out
};

private _alive = _info getOrDefault ["alive", false];
private _inRange = _info getOrDefault ["in_range", false];
private _name = _info getOrDefault ["name", "Relais"];
private _rel = _info getOrDefault ["reliability_pct", 0];
if (!(_rel isEqualType 0)) then { _rel = 0; };
_out set ["name", _name];
_out set ["in_range", _inRange];
_out set ["destroyed", !_alive];
_out set ["jammed", _jammed];

if (_jammed) exitWith {
    _out set ["bars", 0];
    _out set ["color", [1, 0.32, 0.26, 1]];
    _out set ["color_html", "#FF8A7A"];
    _out set ["tip", if (_zoneName isNotEqualTo "") then {
        format ["Brouillé — %1", _zoneName]
    } else {
        format ["Brouillé — hors de %1", _name]
    }];
    _out
};

if (!_alive) exitWith {
    _out set ["bars", 0];
    _out set ["color", [1, 0.32, 0.26, 1]];
    _out set ["color_html", "#FF8A7A"];
    _out set ["tip", format ["%1 détruit — hors réseau", _name]];
    _out
};

if (!_inRange) exitWith {
    _out set ["bars", 0];
    _out set ["color", [1, 0.82, 0.48, 1]];
    _out set ["color_html", "#FFE08A"];
    _out set ["tip", format ["Hors portée de %1", _name]];
    _out
};

private _bars = 1;
if (_rel >= 85) then { _bars = 4; } else {
    if (_rel >= 65) then { _bars = 3; } else {
        if (_rel >= 40) then { _bars = 2; } else { _bars = 1; };
    };
};
if (_degradedZone) then {
    _bars = (_bars - 1) max 1;
};

private _color = [0.55, 0.95, 0.72, 1];
private _html = "#7CFF9A";
private _tip = format ["%1 — signal %2/4", _name, _bars];
if (_degradedZone) then {
    _color = [1, 0.82, 0.48, 1];
    _html = "#FFE08A";
    _tip = if (_zoneName isNotEqualTo "") then {
        format ["%1 — zone dégradée (%2/4)", _zoneName, _bars]
    } else {
        format ["%1 — signal dégradé (%2/4)", _name, _bars]
    };
} else {
    if (_bars <= 1) then {
        _color = [1, 0.82, 0.48, 1];
        _html = "#FFE08A";
        _tip = format ["%1 — signal faible", _name];
    } else {
        if (_bars <= 2) then {
            _color = [1, 0.82, 0.48, 1];
            _html = "#FFE08A";
            _tip = format ["%1 — signal dégradé (%2/4)", _name, _bars];
        };
    };
};

_out set ["bars", _bars];
_out set ["color", _color];
_out set ["color_html", _html];
_out set ["tip", _tip];
_out
