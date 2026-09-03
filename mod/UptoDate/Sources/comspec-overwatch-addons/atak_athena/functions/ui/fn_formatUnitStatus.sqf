/*
    État médical résumé pour la carte : NOMINAL / WOUNDED / CRITICAL / UNCONSCIOUS.
    Le détail ACE n’apparaît que dans l’inspecteur.
*/
params [["_unit", objNull]];
if (isNull _unit || {!alive _unit}) exitWith { "CRITICAL" };
if (!isNil "ace_medical_status_fnc_isUnconscious") then {
    if ([_unit] call ace_medical_status_fnc_isUnconscious) exitWith { "UNCONSCIOUS" };
} else {
    if (_unit getVariable ["ACE_isUnconscious", false]) exitWith { "UNCONSCIOUS" };
};
private _hp = 1;
if (!isNil "ace_medical_status_fnc_getBloodVolume") then {
    private _bv = [_unit] call ace_medical_status_fnc_getBloodVolume;
    if (_bv isEqualType 0) then { _hp = (_bv / 6) min 1; };
} else {
    _hp = 1 - (damage _unit);
};
if (_hp < 0.45) exitWith { "CRITICAL" };
if (_hp < 0.85 || {damage _unit > 0.15}) exitWith { "WOUNDED" };
"NOMINAL"
