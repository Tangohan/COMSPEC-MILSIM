/*
    True seulement si un MessageBox Windows est sûr (pas pendant REAPP / JIP / grâce respawn).
*/
if (!hasInterface) exitWith { false };
if (isNull player || {!alive player}) exitWith { false };
if (isNull findDisplay 46) exitWith { false };
if (missionNamespace getVariable ["COMSPEC_DisconnectSent", false]) exitWith { false };
if (missionNamespace getVariable ["COMSPEC_CancelPendingAthenaHelp", false]) exitWith { false };

private _now = diag_tickTime;
if (_now < (missionNamespace getVariable ["COMSPEC_SuppressWinMessageBoxUntil", -1e9])) exitWith { false };
if (_now < (missionNamespace getVariable ["COMSPEC_RespawnGraceUntil", -1e9])) exitWith { false };

// Boot : attendre armement médical (spawn stabilisé)
if (!(missionNamespace getVariable ["COMSPEC_MedicalAlertsArmed", false])) exitWith { false };

if (isMultiplayer) then {
    private _st = getClientStateNumber;
    if (_st < 10 || {_st >= 11}) exitWith { false };
};

true
