/*
    Test de résidus de tir (GSR) sur les mains d'une personne, avec un écouvillon du kit.
    Positif si la personne a tiré dans les 45 dernières minutes (horodatage serveur du dernier tir,
    posé par le gestionnaire Fired de l'addon interaction, diffusé au plus toutes les 5 min). La pluie et le temps écoulé dégradent
    les traces : au-delà de 20 min ou sous une forte pluie, le résultat devient « faible ».
    [_target, _player] call comspec_sse_fnc_gsrTest
*/
params [["_target", objNull, [objNull]], ["_player", player, [objNull]]];
if (isNull _target) exitWith { false };

[6, "Prélèvement de résidus de tir...", {
    params ["_target", "_player"];
    if (([_player, "COMSPEC_SSE_SwabKit", 12] call comspec_sse_fnc_kitUse) isEqualTo 0) exitWith {
        ["Kit d'écouvillons requis.", "WARNING"] call comspec_sse_fnc_notify;
    };
    private _last = _target getVariable ["comspec_sse_lastFired", -1];
    private _age = if (_last < 0) then { 1e6 } else { serverTime - _last };
    private _result = switch (true) do {
        case (_age > 2700): { "NÉGATIF" };
        case (_age > 1200 || {rain > 0.6}): { "FAIBLE" };
        default { "POSITIF" };
    };
    ["merge", _target, ["biometrics", createHashMapFromArray [
        ["gsr", _result], ["gsrAt", serverTime], ["gsrBy", name _player]
    ]]] call comspec_sse_fnc_recordOp;
    if (!isNil "comspec_sse_fnc_custody" && {(["piece", [_target]] call comspec_sse_fnc_custody) isNotEqualTo ""}) then {
        ["event", [_target, "TEST GSR", _player, _result]] call comspec_sse_fnc_custody;
    };
    [format ["Résidus de tir : %1", _result], ["INFO", "WARNING"] select (_result isEqualTo "POSITIF")] call comspec_sse_fnc_notify;
    [format ["GSR-%1", netId _target], "gsr", "PERSON", format ["Résidus de tir %1", _result], 100, "LOCAL"] call comspec_sse_fnc_addJournalEntry;
}, [_target, _player]] call comspec_sse_fnc_progressAction;
true
