/*
    Ce qu'une IA dit ou fait en direct, et le petit indice qui va avec : à l'écran, jamais dans le centre de notifications.
    [_text, _type, _unit, _hint, _bubbleOnly] call comspec_sse_fnc_toast -> true si la bulle au-dessus de la tête est vue
      _text  phrase en français, traduite ici (vide : seul l'indice part en toast)
      _type  INFO | SUCCESS | WARNING | ERROR
      _unit  personne qui parle : bulle de l'IA RP du SSE (fn_iarpClient « aiLine ») si l'addon est chargé, sinon bulle
             de l'ATAK (fn_aiBubble) si elle est à portée et en vue, réglage coché
             objNull : toast seul
      _hint  ce que le joueur peut faire ensuite : toujours dans le toast, jamais dans la bulle
    Repli : notification du jeu de l'ATAK (CfgNotifications), sinon hintSilent si l'ATAK est absent.
    Deux phrases de la même personne à moins de 2 s : la seconde attend, sinon elle remplace la première sans être lue.
*/
params [["_text", "", [""]], ["_type", "INFO", [""]], ["_unit", objNull, [objNull]], ["_hint", "", [""]], ["_bubbleOnly", false, [false]]];
if (!hasInterface || {_text isEqualTo "" && {_hint isEqualTo ""}}) exitWith { false };
private _tr = { if (_this isEqualTo "") then { "" } else { _this call comspec_sse_fnc_tr } };
private _line = _text call _tr;
private _tip = _hint call _tr;
private _showToast = {
    params ["_t", "_k", ["_who", ""]];
    if (_t isEqualTo "") exitWith {};
    if (!isNil "comspec_atak_native_fnc_aiOrder") exitWith { ["toast", [_t, _k, _who]] call comspec_atak_native_fnc_aiOrder; };
    hintSilent _t;
};
// IA RP chargée : une IA parle dans LA bulle du SSE (fn_iarpBubble, une seule par personne, la nouvelle remplace l'ancienne)
// et dans le fil de « Parler à » si on lui parle ; même nom partout (identité SSE, sinon « Anonyme »). Avant, l'ATAK
// dessinait une seconde bulle au-dessus de la même tête, sous un autre nom (celui d'Arma).
if (!isNull _unit && {_line isNotEqualTo ""} && {!isPlayer _unit} && {!isNil "comspec_sse_fnc_iarpClient"} && {missionNamespace getVariable ["comspec_sse_iarp_enabled", true]}) exitWith {
    private _seen = ["aiLine", [_unit, _line, _type]] call comspec_sse_fnc_iarpClient;
    if (!_seen && {_bubbleOnly}) exitWith { false };
    private _who = ["name", [_unit]] call comspec_sse_fnc_iarpClient;
    private _parts = if (_seen) then { [_tip] } else { [format ["%1 : %2", _who, _line], _tip] };
    private _msg = (_parts select { _x isNotEqualTo "" }) joinString " · ";
    if (_msg isNotEqualTo "") then { [_msg, _type, _who] call _showToast; };
    _seen
};
if (isNull _unit || {_line isEqualTo ""} || {isNil "comspec_atak_native_fnc_aiBubble"}) exitWith {
    [(([_line, _tip] select { _x isNotEqualTo "" }) joinString " · "), _type, [([_unit getVariable ["comspec_sse_idName", ""], "Anonyme" call _tr] select ((_unit getVariable ["comspec_sse_idName", ""]) isEqualTo "")), ""] select (isNull _unit)] call _showToast;
    false
};
private _at = time max (_unit getVariable ["comspec_sse_toastFree", 0]);
_unit setVariable ["comspec_sse_toastFree", _at + 2];
private _go = {
    params ["_line", "_type", "_unit", "_tip", "_showToast"];
    private _seen = !isNull _unit && {["say", [_unit, _line, _unit getVariable ["comspec_sse_idName", name _unit], _type]] call comspec_atak_native_fnc_aiBubble};
    // Bulle vue : l'indice seul en toast. Sinon la phrase, avec son auteur, et l'indice.
    // Témoin (_bubbleOnly) : ce que dit l'IA ne se lit que sur sa bulle, vue de face à portée ; derrière un mur ou le dos tourné, rien.
    if (!_seen && {_bubbleOnly}) exitWith { false };
    private _parts = if (_seen) then { [_tip] } else { [format ["%1 : %2", [_unit getVariable ["comspec_sse_idName", name _unit], "?"] select (isNull _unit), _line], _tip] };
    [((_parts select { _x isNotEqualTo "" }) joinString " · "), _type, [_unit getVariable ["comspec_sse_idName", ""], "Anonyme" call _tr] select ((_unit getVariable ["comspec_sse_idName", ""]) isEqualTo "")] call _showToast;
    _seen
};
if (_at <= time) exitWith { [_line, _type, _unit, _tip, _showToast] call _go };
[_go, [_line, _type, _unit, _tip, _showToast], _at - time] call CBA_fnc_waitAndExecute;
true
