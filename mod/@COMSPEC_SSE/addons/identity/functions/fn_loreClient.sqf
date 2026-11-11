/*
    Lore de session Athena, poste du Zeus ou de l'administrateur (réponses du serveur, fn_loreServer).
    ["result", [texte, genre (info | warn | error), état]] remoteExecCall ["comspec_sse_fnc_loreClient", poste]
      Retour dans la fenêtre du module (rouverte avec le résultat si l'interface Zeus est ouverte et qu'aucun
      formulaire SSE n'est déjà affiché), dans le téléphone et le RPT (comspec_sse_fnc_zeusNotify). Jamais de bulle.
    ["licence"] (XEH_postInitClient) : licence COMSPEC acceptée sur ce poste, signalée au serveur (serveur dédié).
*/
params [["_op", "", [""]], ["_a", [], [[]]]];
if (!hasInterface) exitWith { false };
if (_op isEqualTo "licence") exitWith {
    if (isServer || {isRemoteExecuted}) exitWith { false };
    private _ok = isNil "comspec_overwatch_connect_fnc_licenceStatus" || {([] call comspec_overwatch_connect_fnc_licenceStatus) param [1, false]};
    if (_ok) then { ["licence", [player]] remoteExecCall ["comspec_sse_fnc_loreServer", 2]; };
    _ok
};
// Réponses : du serveur seulement.
if (isRemoteExecuted && {remoteExecutedOwner isNotEqualTo 2}) exitWith { false };
if (_op isEqualTo "result") exitWith {
    _a params [["_txt", "", [""]], ["_kind", "info", [""]]];
    if (_txt isEqualTo "") exitWith { false };
    missionNamespace setVariable ["comspec_sse_loreLastMsg", _txt];
    [format ["%1 %2", localize "STR_COMSPEC_SSE_LORE_Prefix", _txt], _kind] call comspec_sse_fnc_zeusNotify;
    if (!isNull (findDisplay 312) && {isNull (findDisplay 93600)}) then { ["window", [_txt]] call comspec_sse_fnc_loreModule; };
    true
};
false
