/*
    Un joueur vient de trouver un contenu de l'éditeur (SERVEUR). [_unit, _target] call comspec_sse_fnc_editorFound
    Appelé par fn_serverHandleOp (fouille, lecture, collecte, exploitation) ; absent de CfgRemoteExec : un poste ne peut pas
    le déclencher directement. Une seule fois par objet. Prévient les Zeus et administrateurs, dont l'auteur (ligne
    « Trouvé par un joueur » de la bibliothèque), et Athena : clé du serveur, file d'attente si la liaison manque.
*/
params [["_unit", objNull, [objNull]], ["_target", objNull, [objNull]]];
if (!isServer || {isNull _unit} || {isNull _target}) exitWith { false };
private _id = _target getVariable ["comspec_sse_editorItem", ""];
if (_id isEqualTo "" || {_target getVariable ["comspec_sse_editorFoundDone", false]}) exitWith { false };
_target setVariable ["comspec_sse_editorFoundDone", true];

systemTime params ["_y", "_m", "_d", "_h", "_mi"];
private _when = format ["%1%2/%3%4/%5 %6%7:%8%9", ["", "0"] select (_d < 10), _d, ["", "0"] select (_m < 10), _m, _y, ["", "0"] select (_h < 10), _h, ["", "0"] select (_mi < 10), _mi];
private _who = name _unit;
{
    if (local _x) then {
        ["found", [_id, _who, _when]] call comspec_sse_fnc_editor;
    } else {
        ["found", [_id, _who, _when]] remoteExecCall ["comspec_sse_fnc_editor", owner _x];
    };
} forEach (allPlayers select { [_x] call comspec_sse_fnc_isAuthority });

[createHashMapFromArray [
    ["kind", "editor_found"], ["uid", _id], ["player", _who], ["when", _when], ["idempotency_key", format ["found-%1", _id]]
]] spawn {
    params ["_payload"];
    private _sent = false;
    if (["has"] call comspec_sse_fnc_idAthenaPack) then {
        private _json = [_payload] call comspec_sse_fnc_toJsonApprox;
        private _raw = ["SubmitIdCheck", [_json]] call comspec_sse_fnc_extensionCall;
        _sent = (_raw select [0, 3]) isEqualTo "OK|";
    };
    if (_sent) exitWith {};
    [createHashMapFromArray [
        ["kind", "EDITORFOUND"], ["command", "SubmitIdCheck"], ["payload", _payload],
        ["idempotency_key", _payload get "idempotency_key"], ["record_id", _payload get "idempotency_key"], ["txStatus", "PENDING"]
    ]] call comspec_sse_fnc_queueOffline;
};
true
