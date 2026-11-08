/*
    Ouvre la fenêtre de configuration des IA (éditeur SSE, page « IA de la zone »), pour le Centre Zeus et les autres appelants.
    [unité ou liste d'unités, position] call comspec_sse_fnc_aiConfigOpen
    Sans unité : sélection du Zeus, sinon les IA autour de la position.
*/
params [["_what", objNull, [objNull, []]], ["_pos", [], [[]]]];
if (!hasInterface) exitWith { false };
private _list = if (_what isEqualType objNull) then { [_what] } else { _what };
_list = _list select { !isNull _x && {_x isKindOf "CAManBase"} && {!isPlayer _x} };
private _first = _list param [0, objNull];
if (_pos isEqualTo [] && {!isNull _first}) then { _pos = getPosATL _first; };
uiNamespace setVariable ["comspec_sse_editorAiUnits", _list];
["open", [_pos, _first, "ai"]] call comspec_sse_fnc_editor;
true
