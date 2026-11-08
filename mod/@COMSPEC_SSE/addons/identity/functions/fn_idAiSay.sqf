/*
    Phrase courte d'une IA contrôlée (SERVEUR) : bulle au-dessus de sa tête, ou toast, pour les joueurs à moins de 15 m
    (fn_idClient « say », traduite chez chacun ; jamais dans le centre de notifications). Entre parenthèses : ce qu'on voit (il s'enfuit, il crie). Arcade : l'état est rappelé.
    [ia, phrase, type, état] call comspec_sse_fnc_idAiSay
*/
params [["_u", objNull, [objNull]], ["_text", "", [""]], ["_type", "INFO", [""]], ["_st", "", [""]]];
if (!isServer || {isNull _u} || {_text isEqualTo ""}) exitWith { false };
private _hint = "";
if ((missionNamespace getVariable ["comspec_sse_id_difficulty", 1]) isEqualTo 0) then {
    _hint = (createHashMapFromArray [
        ["interpelle", "attentif"], ["coopere", "coopère"], ["ment", "trop sûr de lui"], ["nerveux", "nerveux"],
        ["refuse", "fermé"], ["fuit", "en fuite"], ["agressif", "menaçant"], ["rendu", "se rend"], ["renforts", "appelle à l'aide"]
    ]) getOrDefault [_st, ""];
};
private _pl = +allPlayers;
if (hasInterface && {!isNull player}) then { _pl pushBackUnique player; };
{
    if (alive _x && {(_x distance _u) < 15}) then {
        private _args = [name _u, _text, _type, _hint, _u];
        if (local _x && {hasInterface}) then { ["say", _args] call comspec_sse_fnc_idClient; } else { ["say", _args] remoteExecCall ["comspec_sse_fnc_idClient", owner _x]; };
    };
} forEach _pl;
true
