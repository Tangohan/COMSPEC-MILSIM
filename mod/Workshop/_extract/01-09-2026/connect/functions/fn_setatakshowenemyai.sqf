/*
    Affiche ou masque les IA ennemies sur le poste ATAK (drapeau de mission).
    Par défaut masqué. Zeus / Eden basculent ce réglage.
*/
params [
    ["_on", true]
];

private _flag = _on;
if (_flag isEqualType 0) then { _flag = _flag > 0 };
if (_flag isEqualType "") then { _flag = (toLower (trim _flag)) in ["1", "true", "yes", "oui", "show", "start"] };
if (!(_flag isEqualType true)) then { _flag = false };

missionNamespace setVariable ["COMSPEC_AtakShowEnemyAi", _flag, true];

if (_flag) then {
    if (!isNil "comspec_overwatch_connect_fnc_reportEnemyAiPositions") then {
        [] call comspec_overwatch_connect_fnc_reportEnemyAiPositions;
    };
    ["Les IA ennemies apparaissent sur la carte du poste.", "system", "info"] call comspec_overwatch_connect_fnc_ambientHint;
} else {
    ["Les contacts ennemis sont masqués sur la carte du poste.", "system", "info"] call comspec_overwatch_connect_fnc_ambientHint;
};

_flag
