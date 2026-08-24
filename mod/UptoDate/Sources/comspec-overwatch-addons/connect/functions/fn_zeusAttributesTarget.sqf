/*
    Objet actuellement édité dans le panneau Zeus (vanilla / ZEN).
*/
params [["_display", displayNull]];

private _asEntity = {
    params ["_v"];
    if (_v isEqualType [] && {count _v > 0}) then { _v = _v select 0 };
    if (_v isEqualType grpNull) exitWith {
        if (isNull _v) then { objNull } else { leader _v }
    };
    if (_v isEqualType objNull) exitWith { _v };
    objNull
};

private _obj = objNull;
if (!isNull _display) then {
    _obj = [_display getVariable ["COMSPEC_AttrEntity", objNull]] call _asEntity;
};
if (isNull _obj) then {
    _obj = [uiNamespace getVariable ["bis_fnc_curatorAttributes_target", objNull]] call _asEntity;
};
if (isNull _obj) then {
    _obj = [missionNamespace getVariable ["zen_attributes_target", objNull]] call _asEntity;
};
if (isNull _obj) then {
    _obj = [uiNamespace getVariable ["zen_common_attributesTarget", objNull]] call _asEntity;
};
if (isNull _obj) then {
    private _sel = [] call comspec_overwatch_connect_fnc_curatorSelectedObjects;
    if (_sel isEqualType [] && { _sel isNotEqualTo [] }) then {
        _obj = [_sel select 0] call _asEntity;
    };
};
if (!(_obj isEqualType objNull)) then { _obj = objNull };
_obj
