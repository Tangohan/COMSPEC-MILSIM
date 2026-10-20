/*
    Effacement à distance des téléphones saisis (serveur) : pratique réelle des réseaux adverses.
    Un téléphone armé (tirage dans dl7Auto) déclenche son compte à rebours dès qu'un joueur s'en approche
    (25 m) : 5 à 8 minutes plus tard, s'il n'est ni dans un sac de Faraday ni déjà extrait, ses données
    sont effacées (état WIPED du DL-7) et les joueurs proches le voient s'allumer.
    [_entity] call comspec_sse_fnc_remoteWipe   arme le téléphone et lance la surveillance (une boucle pour tous)
*/
params [["_e", objNull, [objNull]]];
if (!isServer || {isNull _e}) exitWith { false };

private _armed = missionNamespace getVariable ["comspec_sse_wipeArmed", []];
_armed pushBackUnique _e;
missionNamespace setVariable ["comspec_sse_wipeArmed", _armed];
if (!isNil "comspec_sse_wipePfh") exitWith { true };

comspec_sse_wipePfh = [{
    private _armed = (missionNamespace getVariable ["comspec_sse_wipeArmed", []]) select {
        !isNull _x
        && {!(_x getVariable ["comspec_sse_faraday", false])}
        && {!(_x getVariable ["COMSPEC_ATAK_DataWiped", false])}
        && {(_x getVariable ["COMSPEC_ATAK_DataTaken", []]) isEqualTo []}
    };
    missionNamespace setVariable ["comspec_sse_wipeArmed", _armed];
    {
        private _e = _x;
        private _at = _e getVariable ["comspec_sse_wipeAt", -1];
        if (_at < 0) then {
            if ((allPlayers findIf { alive _x && {(_x distance _e) < 25} }) >= 0) then {
                private _delay = 300 + (([[_e] call comspec_sse_fnc_getSeed, "wipeDelay"] call comspec_sse_fnc_hash) mod 180);
                _e setVariable ["comspec_sse_wipeAt", serverTime + _delay];
            };
        } else {
            if (serverTime > _at) then {
                _e setVariable ["COMSPEC_ATAK_DataWiped", true, true];
                ["merge", _e, ["evidenceState", createHashMapFromArray [["remoteWiped", true], ["remoteWipedAt", serverTime]]]] call comspec_sse_fnc_recordOp;
                {
                    if ((_x distance _e) < 50) then {
                        ["L'écran d'un téléphone saisi s'allume : effacement à distance en cours. Un sac de Faraday l'aurait empêché.", "WARNING"] remoteExecCall ["comspec_sse_fnc_notify", owner _x];
                    };
                } forEach allPlayers;
                [format ["Effacement à distance : %1", _e]] call comspec_sse_fnc_log;
            };
        };
    } forEach _armed;
}, 10] call CBA_fnc_addPerFrameHandler;
true
