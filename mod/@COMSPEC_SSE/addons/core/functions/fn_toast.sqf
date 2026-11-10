/*
    Ce qu'une IA dit ou fait en direct, et le petit indice qui va avec : bulle au-dessus de la tête, sinon téléphone.
    [_text, _type, _unit, _hint, _bubbleOnly] call comspec_sse_fnc_toast -> true si la bulle au-dessus de la tête est vue
      _text  phrase en français, traduite ici (vide : seul l'indice part en notification)
      _type  INFO | SUCCESS | WARNING | ERROR
      _unit  personne qui parle : bulle de l'ATAK (fn_aiBubble) si elle est à portée et en vue, réglage coché
             objNull : notification seule
      _hint  ce que le joueur peut faire ensuite : toujours dans la notification, jamais dans la bulle
    Jamais d'alerte de l'interface d'Arma (ni notification du jeu, ni hint) : bulle non vue, ou pas d'unité,
    le texte « Qui : phrase · indice » part dans le centre de notifications du téléphone (comspec_sse_fnc_notify,
    bandeau seulement si le téléphone est affiché) et au RPT (comspec_sse_fnc_log) ; sans ATAK natif, RPT seul.
    Deux phrases de la même personne à moins de 2 s : la seconde attend, sinon elle remplace la première sans être lue.
*/
params [["_text", "", [""]], ["_type", "INFO", [""]], ["_unit", objNull, [objNull]], ["_hint", "", [""]], ["_bubbleOnly", false, [false]]];
if (!hasInterface || {_text isEqualTo "" && {_hint isEqualTo ""}}) exitWith { false };
private _tr = { if (_this isEqualTo "") then { "" } else { _this call comspec_sse_fnc_tr } };
private _line = _text call _tr;
private _tip = _hint call _tr;
// Notification du téléphone + RPT : _who (identité, « Anonyme », ou vide sans unité) préfixe le texte.
private _showToast = {
    params ["_t", "_k", ["_who", ""]];
    if (_t isEqualTo "") exitWith {};
    private _msg = if (_who isEqualTo "") then { _t } else { format ["%1 : %2", _who, _t] };
    private _sent = [_msg, _k, "COMSPEC SSE", "SSE"] call comspec_sse_fnc_notify;
    [format ["[TOAST] %1", _msg], ["INFO", "WARN"] select (_k in ["WARNING", "ERROR"])] call comspec_sse_fnc_log;
    if (!_sent) then { diag_log text format ["[COMSPEC SSE] %1", _msg]; };
};
// Appelé aussi en différé (CBA_fnc_waitAndExecute) : aucune variable locale du script n'est capturée, d'où comspec_sse_fnc_tr direct.
private _whoOf = { params ["_u"]; if (isNull _u) then { "" } else { [_u getVariable ["comspec_sse_idName", ""], "Anonyme" call comspec_sse_fnc_tr] select ((_u getVariable ["comspec_sse_idName", ""]) isEqualTo "") } };
if (isNull _unit || {_line isEqualTo ""} || {isNil "comspec_atak_native_fnc_aiBubble"}) exitWith {
    [(([_line, _tip] select { _x isNotEqualTo "" }) joinString " · "), _type, [_unit] call _whoOf] call _showToast;
    false
};
private _at = time max (_unit getVariable ["comspec_sse_toastFree", 0]);
_unit setVariable ["comspec_sse_toastFree", _at + 2];
private _go = {
    params ["_line", "_type", "_unit", "_tip", "_showToast", "_whoOf", "_bubbleOnly"];
    private _seen = !isNull _unit && {["say", [_unit, _line, _unit getVariable ["comspec_sse_idName", name _unit], _type]] call comspec_atak_native_fnc_aiBubble};
    // Bulle vue : l'indice seul en notification. Sinon la phrase et l'indice, préfixés de l'auteur.
    // Témoin (_bubbleOnly) : ce que dit l'IA ne se lit que sur sa bulle, vue de face à portée ; derrière un mur ou le dos tourné, rien.
    if (!_seen && {_bubbleOnly}) exitWith { false };
    private _parts = if (_seen) then { [_tip] } else { [_line, _tip] };
    [((_parts select { _x isNotEqualTo "" }) joinString " · "), _type, [_unit] call _whoOf] call _showToast;
    _seen
};
if (_at <= time) exitWith { [_line, _type, _unit, _tip, _showToast, _whoOf, _bubbleOnly] call _go };
[_go, [_line, _type, _unit, _tip, _showToast, _whoOf, _bubbleOnly], _at - time] call CBA_fnc_waitAndExecute;
true
