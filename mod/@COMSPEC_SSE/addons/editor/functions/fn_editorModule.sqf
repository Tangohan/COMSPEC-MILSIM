/*
    Module Zeus « Éditeur SSE » (sans ZEN). Ouvre l'éditeur sur le poste du Zeus qui l'a posé ; la logique est retirée tout de suite.
    Posé sur une IA, ou IA sélectionnées dans le Zeus : page « IA de la zone ».
*/
private _args = _this call comspec_sse_fnc_moduleArgs;
if (_args isEqualTo []) exitWith { true };
_args params ["_logic", "_units", "_activated"];
if (!_activated || {isNull _logic}) exitWith { false };
if (!hasInterface || {!local _logic}) exitWith { true };
private _pos = getPosATL _logic;
private _obj = _logic getVariable ["bis_fnc_curatorAttachObject", objNull];
if (isNull _obj) then { _obj = attachedTo _logic; };
if (isNull _obj) then { _obj = ((curatorSelected param [0, []]) select { _x isKindOf "CAManBase" && {!isPlayer _x} }) param [0, objNull]; };
deleteVehicle _logic;
[{ ["open", _this] call comspec_sse_fnc_editor; }, [_pos, _obj]] call CBA_fnc_execNextFrame;
true
