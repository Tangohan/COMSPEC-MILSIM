/* Met à jour les superpositions de la carte (carte « moi », panneau curseur, état des outils). */
params [["_cursorOnly", false]];
disableSerialization;
private _ov = uiNamespace getVariable ["COMSPEC_ATAK_MapOverlay", createHashMap];
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
private _cardinal = {
    params ["_a"];
    ["N", "NE", "E", "SE", "S", "SO", "O", "NO"] select ((round (_a / 45)) mod 8)
};

private _cur = _ov getOrDefault ["cursor", controlNull];
if (!isNull _cur) then {
    private _p = _s getOrDefault ["cursorPos", []];
    private _measure = _s getOrDefault ["mapMeasure", []];
    private _text = if ((count _measure) >= 2) then {
        _measure params ["_a", "_b"];
        private _az = _a getDir _b;
        format ["<t size='0.85' color='#f2ab33'>MESURE</t><br/>%1 m · %2° %3", round (_a distance2D _b), round _az, [_az] call _cardinal]
    } else {
        if ((count _p) < 2) then { "<t size='0.85' color='#8a9a93'>Survolez la carte</t>" } else {
            private _az = player getDir _p;
            format ["<t size='0.85' color='#8a9a93'>CURSEUR</t> %1 · %2 m<br/>%3 m · %4° %5", mapGridPosition _p, round (getTerrainHeightASL _p), round (player distance2D _p), round _az, [_az] call _cardinal]
        }
    };
    _cur ctrlSetStructuredText parseText _text;
};
if (_cursorOnly) exitWith { true };

private _me = _ov getOrDefault ["me", controlNull];
if (!isNull _me) then {
    private _h = getDir (vehicle player);
    _me ctrlSetStructuredText parseText format [
        "<t font='RobotoCondensedBold' color='#5cc76b'>%1</t><br/><t size='0.85'>%2 · %3° %4 · %5 m</t>",
        [player] call comspec_atak_native_fnc_unitCallsign, mapGridPosition player, round _h, [_h] call _cardinal, round ((getPosASL player) select 2)
    ];
};

// Outils bascule : vert quand actifs.
private _on = [0.36, 0.78, 0.42, 1];
private _off = [0.90, 0.94, 0.91, 1];
private _mode = _s getOrDefault ["mapMode", "SELECT"];
{
    _x params ["_key", "_active"];
    private _b = _ov getOrDefault ["tool_" + _key, controlNull];
    if (!isNull _b) then { _b ctrlSetTextColor ([_off, _on] select _active); };
} forEach [
    ["follow", _s getOrDefault ["mapFollow", false]],
    ["MARKER", _mode isEqualTo "MARKER"],
    ["MEASURE", _mode isEqualTo "MEASURE"],
    ["labels", profileNamespace getVariable ["COMSPEC_ATAK_Labels", true]]
];
true
