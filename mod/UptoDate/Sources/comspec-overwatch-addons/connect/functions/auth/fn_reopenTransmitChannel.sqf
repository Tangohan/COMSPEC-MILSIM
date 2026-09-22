/*
    Après appairage / Entrer réussi : lever le frein Tx et relancer les boucles
    seulement si elles ne tournent pas déjà (évite d’empiler les PFH → gel).
    Si le canal est déjà ouvert mais aucune position n’est partie : forcer
    immédiatement une remontée (évite « Position remontée: pas encore »).
*/
if (!hasInterface) exitWith { false };

missionNamespace setVariable ["COMSPEC_ApiBackoffUntil", 0, false];
missionNamespace setVariable ["COMSPEC_VideoFeedsBackoffUntil", 0, false];
missionNamespace setVariable ["COMSPEC_SendBackoffSec", 0, false];
missionNamespace setVariable ["COMSPEC_ApiBackoffSec", 2, false];
missionNamespace setVariable ["COMSPEC_OperatorProfileBackoffUntil", 0, false];
missionNamespace setVariable ["COMSPEC_LastFactionSettingsBody", "", false];

[] call comspec_overwatch_connect_fnc_applyBootstrap;
[] call comspec_overwatch_connect_fnc_pollAuth;

// Un AuthInvalidated a pu laisser C2_UNAUTHORIZED alors que le jeton est déjà bon.
private _dllState = missionNamespace getVariable ["comspec_overwatch_auth_state", ""];
if (_dllState isEqualTo "READY") then {
    missionNamespace setVariable ["comspec_overwatch_auth_error", "", false];
};

if (!([] call comspec_overwatch_connect_fnc_canStartSync)) exitWith { false };

// Si le canal est utilisable mais AthenaReady encore faux (ex. erreur C2_* obsolète), aligner.
if (!(missionNamespace getVariable ["COMSPEC_AthenaReady", false])) then {
    missionNamespace setVariable ["COMSPEC_AthenaReady", true, false];
    missionNamespace setVariable ["COMSPEC_AthenaReadyAt", diag_tickTime, false];
    if ((missionNamespace getVariable ["COMSPEC_LinkState", ""]) isNotEqualTo "linked") then {
        missionNamespace setVariable ["COMSPEC_LinkState", "linked", false];
    };
    private _err = missionNamespace getVariable ["comspec_overwatch_auth_error", ""];
    if ((_err isEqualType "") && {(toUpper _err) find "C2_" == 0}) then {
        missionNamespace setVariable ["comspec_overwatch_auth_error", "", false];
    };
};

private _fnc_forcePos = {
    if (isNull player || {!alive player}) exitWith {};
    if (isNil "comspec_overwatch_connect_fnc_updatePosition") exitWith {};
    [player, true] call comspec_overwatch_connect_fnc_updatePosition;
};

if (missionNamespace getVariable ["COMSPEC_SyncLoopsStarted", false]) exitWith {
    ["INFO", "Athena", "Canal poste déjà ouvert — freins API levés"] call comspec_overwatch_connect_fnc_log;
    // Boucles déjà là mais jamais de position, OU sync devenue trop vieille :
    // forcer une remontée (évite d’exiger un Resynch manuel pour réapparaître au poste).
    private _lastPos = missionNamespace getVariable ["COMSPEC_LastPositionSync", -1];
    private _needForce = true;
    if ((_lastPos isEqualType 0) && {_lastPos >= 0}) then {
        _needForce = (diag_tickTime - _lastPos) > 45;
    };
    if (_needForce) then {
        [] call _fnc_forcePos;
    };
    true
};

[] call comspec_overwatch_connect_fnc_startSyncLoops;
[] call _fnc_forcePos;
["INFO", "Athena", "Canal poste rouvert — transmissions reprises"] call comspec_overwatch_connect_fnc_log;
true
