/*
    Ouverture de l’app État ATAK dans cTab (pattern ATAK_APPs Opened).
*/
params ["_group", ["_interfaceInit", false], "_isDialog", "_settings"];

if (isNull _group) exitWith {};

// Invalide tout refresh précédent (évite d’écrire sur une page déjà quittée)
uiNamespace setVariable ["COMSPEC_ATAK_Status_group", _group];
private _token = diag_tickTime + random 1;
uiNamespace setVariable ["COMSPEC_ATAK_Status_token", _token];

// Forcer une sync terminal / certificat à l’ouverture (sinon champs vides)
0 spawn {
    if ((missionNamespace getVariable ["COMSPEC_LinkState", "offline"]) isEqualTo "linked") then {
        ["", true] call comspec_overwatch_connect_fnc_syncAtakRealism;
    };
    [] call comspec_overwatch_atak_athena_fnc_athena_updateStatus;
    [true] call comspec_overwatch_connect_fnc_logAtakStateChange;
};

[] call comspec_overwatch_atak_athena_fnc_athena_updateStatus;
[true] call comspec_overwatch_connect_fnc_logAtakStateChange;

[_token] spawn {
    params ["_token"];
    while { (uiNamespace getVariable ["COMSPEC_ATAK_Status_token", -1]) isEqualTo _token } do {
        uiSleep 4;
        if ((uiNamespace getVariable ["COMSPEC_ATAK_Status_token", -1]) isNotEqualTo _token) exitWith {};

        private _group = uiNamespace getVariable ["COMSPEC_ATAK_Status_group", controlNull];
        if (isNull _group || {!ctrlShown _group}) exitWith {
            if ((uiNamespace getVariable ["COMSPEC_ATAK_Status_token", -1]) isEqualTo _token) then {
                uiNamespace setVariable ["COMSPEC_ATAK_Status_group", controlNull];
            };
        };

        // Toujours sur la page État ATAK ?
        // BCE : showMenu[0] = « AtakStatus » (classe tiroir), pas le PAGE_CTRL.
        private _page = (["cTab_Android_dlg", "showMenu"] call cTab_fnc_getSettings) param [0, ""];
        if (_page isNotEqualTo "" && {!(_page in ["AtakStatus", "COMSPEC_ATAK_Status", "atak_status", "status"])}) exitWith {
            if ((uiNamespace getVariable ["COMSPEC_ATAK_Status_token", -1]) isEqualTo _token) then {
                uiNamespace setVariable ["COMSPEC_ATAK_Status_token", -1];
                uiNamespace setVariable ["COMSPEC_ATAK_Status_group", controlNull];
            };
        };

        [] call comspec_overwatch_atak_athena_fnc_athena_updateStatus;
    };
};
