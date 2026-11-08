/*
    Placement d'un contenu de l'éditeur (client). [_item, [_pos, _obj]] call comspec_sse_fnc_editorPlace
    Position connue (module Zeus), dossier ou envoi au téléphone : part tout de suite. Sinon un clic dans le monde (Zeus :
    sous la souris ; à pied : au centre de l'écran) ; Échap annule. Gestionnaires retirés dès le clic : aucun coût ensuite.
*/
params [["_item", createHashMap, [createHashMap]], ["_ctx", [], [[]]]];
if (!hasInterface || {(count _item) isEqualTo 0}) exitWith { false };
_ctx params [["_pos", [], [[]]], ["_obj", objNull, [objNull]]];

private _send = {
    params ["_it", "_where", "_target"];
    ["place", [player, _it, _where, _target]] remoteExecCall ["comspec_sse_fnc_editorServer", 2];
};
if (_pos isNotEqualTo [] || {!isNull _obj} || {(_item get "type") isEqualTo "case"} || {(_item get "delivery") isEqualTo 2}) exitWith {
    [_item, _pos, _obj] call _send;
    true
};

disableSerialization;
private _zeus = findDisplay 312;
private _display = [findDisplay 46, _zeus] select (!isNull _zeus);
if (isNull _display) exitWith { false };
["Cliquez dans le monde pour placer. Échap : annuler.", "INFO"] call comspec_sse_fnc_notify;
uiNamespace setVariable ["comspec_sse_editorPlacing", [_item, _display, _send]];
private _finish = {
    (uiNamespace getVariable ["comspec_sse_editorPlacingEh", [displayNull, -1, -1]]) params ["_dsp", "_mouse", "_key"];
    if (!isNull _dsp) then {
        _dsp displayRemoveEventHandler ["MouseButtonDown", _mouse];
        _dsp displayRemoveEventHandler ["KeyDown", _key];
    };
    uiNamespace setVariable ["comspec_sse_editorPlacingEh", nil];
};
uiNamespace setVariable ["comspec_sse_editorPlacingEnd", _finish];
private _mouse = _display displayAddEventHandler ["MouseButtonDown", {
    params ["_dsp", "_button"];
    if (_button isNotEqualTo 0) exitWith { false };
    (uiNamespace getVariable ["comspec_sse_editorPlacing", []]) params ["_it", "", "_sendIt"];
    private _inZeus = (ctrlIDD _dsp) isEqualTo 312;
    private _where = screenToWorld (if (_inZeus) then { getMousePosition } else { [0.5, 0.5] });
    private _over = if (_inZeus) then { curatorMouseOver } else { ["OBJECT", cursorObject] };
    private _target = if ((_over param [0, ""]) isEqualTo "OBJECT") then { _over param [1, objNull] } else { objNull };
    call (uiNamespace getVariable ["comspec_sse_editorPlacingEnd", {}]);
    [_it, _where, _target] call _sendIt;
    true
}];
private _key = _display displayAddEventHandler ["KeyDown", {
    params ["", "_code"];
    if (_code isNotEqualTo 1) exitWith { false };
    call (uiNamespace getVariable ["comspec_sse_editorPlacingEnd", {}]);
    ["Placement annulé.", "INFO"] call comspec_sse_fnc_notify;
    true
}];
uiNamespace setVariable ["comspec_sse_editorPlacingEh", [_display, _mouse, _key]];
true
