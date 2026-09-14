/*
    Affiche une alerte plein écran sur le téléphone ATAK (ouvert ou mini).
    Params: [_issuer, _text]
*/
params [["_issuer", "Poste"], ["_text", ""]];

if (!hasInterface) exitWith {};

_issuer = trim (str _issuer);
_text = trim (str _text);
if (_issuer isEqualTo "") then { _issuer = "Poste"; };
if (_text isEqualTo "") then { _text = "Message du poste de commandement"; };

missionNamespace setVariable ["COMSPEC_Athena_FsAlert", [_issuer, _text, diag_tickTime + 14]];
private _token = diag_tickTime;
missionNamespace setVariable ["COMSPEC_Athena_FsAlertToken", _token];

if (!isNil "comspec_overwatch_atak_athena_fnc_athena_paintFullscreenAlert") then {
    [] call comspec_overwatch_atak_athena_fnc_athena_paintFullscreenAlert;
};

[_token] spawn {
    params ["_token"];
    uiSleep 14.2;
    if ((missionNamespace getVariable ["COMSPEC_Athena_FsAlertToken", -1]) isEqualTo _token) then {
        missionNamespace setVariable ["COMSPEC_Athena_FsAlert", nil];
        if (!isNil "comspec_overwatch_atak_athena_fnc_athena_paintFullscreenAlert") then {
            [] call comspec_overwatch_atak_athena_fnc_athena_paintFullscreenAlert;
        };
    };
};

if (!isNil "CBA_fnc_waitAndExecute") then {
    {
        [
            {
                if (!isNil "comspec_overwatch_atak_athena_fnc_athena_paintFullscreenAlert") then {
                    [] call comspec_overwatch_atak_athena_fnc_athena_paintFullscreenAlert;
                };
            },
            [],
            _x
        ] call CBA_fnc_waitAndExecute;
    } forEach [0.15, 0.5, 1.2];
};
