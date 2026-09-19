/*
    Coupe Overwatch, puis rallume chaque brique de liaison une par une,
    55 secondes entre chaque. Le bandeau affiche la fonction en cours :
    si le jeu s’arrête, c’est celle-là.
*/
if (!hasInterface) exitWith { false };
if (isNull player) exitWith { false };
if (missionNamespace getVariable ["COMSPEC_DiagIsolateActive", false]) exitWith {
    ["COMSPEC_Warning", ["Un dépannage est déjà en cours."]] call comspec_overwatch_connect_fnc_showNotification;
    false
};

private _steps = [] call comspec_overwatch_connect_fnc_diagIsolateCatalog;
if (!(_steps isEqualType []) || {(count _steps) < 1}) exitWith { false };

private _token = diag_tickTime;
missionNamespace setVariable ["COMSPEC_DiagIsolateToken", _token, false];
missionNamespace setVariable ["COMSPEC_DiagIsolateActive", true, false];
missionNamespace setVariable ["COMSPEC_DiagIsolateAllow", [], false];
missionNamespace setVariable ["COMSPEC_DiagIsolateProbeNote", "", false];
missionNamespace setVariable ["COMSPEC_DiagIsolatePrevEnabled", missionNamespace getVariable ["comspec_overwatch_enabled", true], false];
missionNamespace setVariable ["comspec_overwatch_enabled", false, false];

private _delay = 55;
missionNamespace setVariable ["COMSPEC_DiagIsolateDelay", _delay, false];

["WARN", "Diag", format ["Dépannage liaison démarré — %1 étapes, %2 s chacune", count _steps, _delay]] call comspec_overwatch_connect_fnc_log;

[_token, _steps, _delay] spawn {
    params ["_token", "_steps", "_delay"];
    private _total = count _steps;
    {
        if ((missionNamespace getVariable ["COMSPEC_DiagIsolateToken", -1]) isNotEqualTo _token) exitWith {};
        _x params ["_id", "_label"];

        if (_id isEqualTo "idle") then {
            missionNamespace setVariable ["comspec_overwatch_enabled", false, false];
            missionNamespace setVariable ["COMSPEC_DiagIsolateAllow", [], false];
        } else {
            missionNamespace setVariable ["comspec_overwatch_enabled", true, false];
            private _allow = +(missionNamespace getVariable ["COMSPEC_DiagIsolateAllow", []]);
            if (!(_allow isEqualType [])) then { _allow = []; };
            _allow pushBackUnique _id;
            missionNamespace setVariable ["COMSPEC_DiagIsolateAllow", _allow, false];
        };

        missionNamespace setVariable ["COMSPEC_DiagIsolateHudLabel", _label, false];
        missionNamespace setVariable ["COMSPEC_DiagIsolateHudIndex", _forEachIndex, false];
        missionNamespace setVariable ["COMSPEC_DiagIsolateHudTotal", _total, false];
        missionNamespace setVariable ["COMSPEC_DiagIsolateUntil", diag_tickTime + _delay, false];

        profileNamespace setVariable ["COMSPEC_DiagIsolateLast", [_id, _label]];
        saveProfileNamespace;

        ["WARN", "Diag", format ["Dépannage — étape %1/%2 : %3", _forEachIndex + 1, _total, _label]] call comspec_overwatch_connect_fnc_log;
        [_label, _forEachIndex, _total, _delay] call comspec_overwatch_connect_fnc_diagIsolateHud;
        [_id, _token] call comspec_overwatch_connect_fnc_diagIsolateProbe;

        private _t0 = diag_tickTime;
        while { (diag_tickTime - _t0) < _delay } do {
            if ((missionNamespace getVariable ["COMSPEC_DiagIsolateToken", -1]) isNotEqualTo _token) exitWith {};
            private _left = _delay - (diag_tickTime - _t0);
            [_label, _forEachIndex, _total, _left] call comspec_overwatch_connect_fnc_diagIsolateHud;
            uiSleep 1;
        };
    } forEach _steps;

    if ((missionNamespace getVariable ["COMSPEC_DiagIsolateToken", -1]) isNotEqualTo _token) exitWith {};

    ["INFO", "Diag", "Dépannage liaison : toutes les étapes ont tenu"] call comspec_overwatch_connect_fnc_log;
    ["COMSPEC_Info", ["Dépannage terminé : aucune étape n’a arrêté le jeu."]] call comspec_overwatch_connect_fnc_showNotification;
    [] call comspec_overwatch_connect_fnc_diagIsolateStop;
};

closeDialog 0;
true
