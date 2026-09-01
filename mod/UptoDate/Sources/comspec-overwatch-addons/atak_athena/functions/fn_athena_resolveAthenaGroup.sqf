/*
    Groupe PAGE_CTRL Athena uniquement.
    IceMan Reports réutilise les mêmes numéros de contrôles (9700+) :
    un displayCtrl 9700 peut pointer la page Comptes-rendus.
*/
private _fncIsAthena = {
    params ["_g"];
    if (isNull _g) exitWith { false };
    ((ctrlClassName _g) find "COMSPEC_ATAK_Athena") >= 0
};

private _group = uiNamespace getVariable ["COMSPEC_ATAK_Athena_group", controlNull];
if !([_group] call _fncIsAthena) then {
    if (!isNull _group) then {
        uiNamespace setVariable ["COMSPEC_ATAK_Athena_group", controlNull];
    };
    _group = controlNull;
    {
        private _title = _x displayCtrl 9700;
        if (isNull _title) then { continue };
        private _parent = ctrlParentControlsGroup _title;
        if ([_parent] call _fncIsAthena) exitWith {
            _group = _parent;
            uiNamespace setVariable ["COMSPEC_ATAK_Athena_group", _group];
        };
    } forEach allDisplays;
};

_group
