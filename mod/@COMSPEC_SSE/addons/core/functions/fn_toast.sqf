/*
    Ce qu'une IA dit ou fait en direct, et le petit indice qui va avec : à l'écran, jamais dans le centre de notifications.
    [_text, _type, _unit, _hint] call comspec_sse_fnc_toast -> true si la bulle au-dessus de la tête est vue
      _text  phrase en français, traduite ici (vide : seul l'indice part en toast)
      _type  INFO | SUCCESS | WARNING | ERROR
      _unit  personne qui parle : bulle de l'ATAK (fn_aiBubble) si elle est à portée et en vue, réglage coché
             objNull : toast seul
      _hint  ce que le joueur peut faire ensuite : toujours dans le toast, jamais dans la bulle
    Repli : notification du jeu de l'ATAK (CfgNotifications), sinon hintSilent si l'ATAK est absent.
    Deux phrases de la même personne à moins de 2 s : la seconde attend, sinon elle remplace la première sans être lue.
*/
params [["_text", "", [""]], ["_type", "INFO", [""]], ["_unit", objNull, [objNull]], ["_hint", "", [""]]];
if (!hasInterface || {_text isEqualTo "" && {_hint isEqualTo ""}}) exitWith { false };
private _tr = { if (_this isEqualTo "") then { "" } else { _this call comspec_sse_fnc_tr } };
private _line = _text call _tr;
private _tip = _hint call _tr;
private _showToast = {
    params ["_t", "_k"];
    if (_t isEqualTo "") exitWith {};
    if (!isNil "comspec_atak_native_fnc_aiOrder") exitWith { ["toast", [_t, _k]] call comspec_atak_native_fnc_aiOrder; };
    hintSilent _t;
};
if (isNull _unit || {_line isEqualTo ""} || {isNil "comspec_atak_native_fnc_aiBubble"}) exitWith {
    [(([_line, _tip] select { _x isNotEqualTo "" }) joinString " · "), _type] call _showToast;
    false
};
private _at = time max (_unit getVariable ["comspec_sse_toastFree", 0]);
_unit setVariable ["comspec_sse_toastFree", _at + 2];
private _go = {
    params ["_line", "_type", "_unit", "_tip", "_showToast"];
    private _seen = !isNull _unit && {["say", [_unit, _line, name _unit, _type]] call comspec_atak_native_fnc_aiBubble};
    // Bulle vue : l'indice seul en toast. Sinon la phrase, avec son auteur, et l'indice.
    private _parts = if (_seen) then { [_tip] } else { [format ["%1 : %2", [name _unit, "?"] select (isNull _unit), _line], _tip] };
    [((_parts select { _x isNotEqualTo "" }) joinString " · "), _type] call _showToast;
    _seen
};
if (_at <= time) exitWith { [_line, _type, _unit, _tip, _showToast] call _go };
[_go, [_line, _type, _unit, _tip, _showToast], _at - time] call CBA_fnc_waitAndExecute;
true
