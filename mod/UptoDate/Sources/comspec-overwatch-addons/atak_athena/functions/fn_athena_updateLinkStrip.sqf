/*
  Ancien bandeau de données en bas de carte (sync, versions, débit).
  Retiré de l’écran opérateur : on masque et on détruit le cadre s’il existe.
*/
if (!hasInterface) exitWith { false };

private _disp = displayNull;
if (!isNil "comspec_overwatch_atak_athena_fnc_athena_phoneDisplay") then {
    _disp = [] call comspec_overwatch_atak_athena_fnc_athena_phoneDisplay;
};
if (isNull _disp) exitWith { false };

private _IDC = 99871;
private _ctrl = _disp displayCtrl _IDC;
if (!isNull _ctrl) then {
    _ctrl ctrlShow false;
    _ctrl ctrlEnable false;
    ctrlDelete _ctrl;
};

false
