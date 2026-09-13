/*
    True lorsque le joueur local est réellement en mission et stabilisé
    (évite faux KO / spam au JIP / spawn carte / REAPP).
*/
if (!hasInterface) exitWith { false };
if (isNull player || {!alive player}) exitWith { false };
if (isNull findDisplay 46) exitWith { false };
if (missionNamespace getVariable ["COMSPEC_DisconnectSent", false]) exitWith { false };

if (diag_tickTime < (missionNamespace getVariable ["COMSPEC_RespawnGraceUntil", -1e9])) exitWith { false };
if (missionNamespace getVariable ["COMSPEC_DeathThenRespawn", false]) exitWith { false };

if (isMultiplayer) then {
    private _st = getClientStateNumber;
    // BI : 10 = briefing lu / en jeu ; ≥ 11 = sortie / débrief
    if (_st < 10 || {_st >= 11}) exitWith { false };
};

private _pos = getPosWorld player;
if ((abs (_pos select 0) < 1) && {abs (_pos select 1) < 1}) exitWith { false };

true
