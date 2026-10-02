/*
    Ouverture Fieldwatch (scan RF passif).
*/
params ["_group", ["_interfaceInit", false], "_isDialog", "_settings"];

if (isNull _group) exitWith {};

uiNamespace setVariable ["COMSPEC_ATAK_Fieldwatch_group", _group];
["fieldwatch"] call comspec_overwatch_atak_athena_fnc_athena_hideForeignPages;
_group ctrlShow true;
_group ctrlEnable true;

private _title = _group controlsGroupCtrl 9920;
if (isNull _title) then { _title = _group controlsGroupCtrl 9000; };
if (!isNull _title) then {
    _title ctrlSetText "  Fieldwatch";
};
{
    private _idc = ctrlIDC _x;
    if (_idc in [9010, 9011, 9012, 9013, 9014, 9015, 9020, 9030, 9031, 9032, 9033, 9034, 9035, 9040, 9041, 9043, 9044]) then {
        _x ctrlShow false;
        _x ctrlEnable false;
    };
} forEach (allControls _group);

private _token = diag_tickTime + random 1;
uiNamespace setVariable ["COMSPEC_ATAK_Fieldwatch_token", _token];

[] call comspec_overwatch_atak_athena_fnc_athena_updateFieldwatch;

[_token] spawn {
    params ["_token"];
    while { (uiNamespace getVariable ["COMSPEC_ATAK_Fieldwatch_token", -1]) isEqualTo _token } do {
        uiSleep 2.5;
        if ((uiNamespace getVariable ["COMSPEC_ATAK_Fieldwatch_token", -1]) isNotEqualTo _token) exitWith {};
        private _group = uiNamespace getVariable ["COMSPEC_ATAK_Fieldwatch_group", controlNull];
        if (isNull _group || {!ctrlShown _group}) exitWith {
            if ((uiNamespace getVariable ["COMSPEC_ATAK_Fieldwatch_token", -1]) isEqualTo _token) then {
                uiNamespace setVariable ["COMSPEC_ATAK_Fieldwatch_token", -1];
                uiNamespace setVariable ["COMSPEC_ATAK_Fieldwatch_group", controlNull];
            };
        };
        private _page = toLower ((["cTab_Android_dlg", "showMenu"] call cTab_fnc_getSettings) param [0, ""]);
        if (
            _page isNotEqualTo ""
            && {!(_page in ["atakfieldwatch", "comspec_atak_fieldwatch", "fieldwatch"])}
        ) exitWith {
            if ((uiNamespace getVariable ["COMSPEC_ATAK_Fieldwatch_token", -1]) isEqualTo _token) then {
                uiNamespace setVariable ["COMSPEC_ATAK_Fieldwatch_token", -1];
                uiNamespace setVariable ["COMSPEC_ATAK_Fieldwatch_group", controlNull];
            };
            if (!isNull _group) then {
                _group ctrlShow false;
                _group ctrlEnable false;
            };
            [] call comspec_overwatch_atak_athena_fnc_athena_hideForeignPages;
        };
        [] call comspec_overwatch_atak_athena_fnc_athena_updateFieldwatch;
    };
};
