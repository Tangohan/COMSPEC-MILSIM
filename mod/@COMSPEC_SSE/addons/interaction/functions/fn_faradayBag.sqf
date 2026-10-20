/*
    Met un support numérique dans un sac de Faraday (consommé) : plus aucune liaison radio,
    l'effacement à distance armé par le réseau adverse ne peut plus l'atteindre
    (comspec_sse_fnc_remoteWipe). L'extraction au DL-7 reste possible (câble).
    [_target, _player] call comspec_sse_fnc_faradayBag
*/
params [["_target", objNull, [objNull]], ["_player", player, [objNull]]];
if (isNull _target) exitWith { false };
if (_target getVariable ["comspec_sse_faraday", false]) exitWith {
    ["Ce support est déjà dans un sac de Faraday.", "WARNING"] call comspec_sse_fnc_notify;
    false
};

[4, "Mise en sac de Faraday...", {
    params ["_target", "_player"];
    if !("COMSPEC_SSE_FaradayBag" in (items _player)) exitWith {
        ["Sac de Faraday requis.", "WARNING"] call comspec_sse_fnc_notify;
    };
    if (_target getVariable ["COMSPEC_ATAK_DataWiped", false]) exitWith {
        ["Trop tard : le support a déjà été effacé à distance.", "WARNING"] call comspec_sse_fnc_notify;
    };
    _player removeItem "COMSPEC_SSE_FaradayBag";
    _target setVariable ["comspec_sse_faraday", true, true];
    ["merge", _target, ["evidenceState", createHashMapFromArray [
        ["faraday", true], ["faradayBy", name _player], ["faradayAt", serverTime]
    ]]] call comspec_sse_fnc_recordOp;
    if (!isNil "comspec_sse_fnc_custody" && {(["piece", [_target]] call comspec_sse_fnc_custody) isNotEqualTo ""}) then {
        ["event", [_target, "SAC DE FARADAY", _player, ""]] call comspec_sse_fnc_custody;
    };
    ["Support isolé dans un sac de Faraday : effacement à distance impossible.", "SUCCESS"] call comspec_sse_fnc_notify;
}, [_target, _player]] call comspec_sse_fnc_progressAction;
true
