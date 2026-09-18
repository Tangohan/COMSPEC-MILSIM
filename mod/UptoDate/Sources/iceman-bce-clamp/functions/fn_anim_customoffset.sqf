#include "script_component.hpp"
params ["_ctrl",["_POS",[]],["_update_Components",[]]];

if (
  2 != count _POS
) exitwith {
	ERROR_MSG_1("Invalid Input of ""_POS = %1"" for fnc_Anim_CustomOffset",_POS);
};

_POS params [["_POS_Start",[]],["_POS_End",[]]];

// -Update In when Custom Offsets are changed
if (_update_Components findIf {true} < 0) exitWith {};
_update_Components params [["_type",""],"_instant",["_BG_IDC",0],["_ignore",[]]];

private _queue = (_ctrl getVariable ["Animation_Queue",[]]) select {!isnull _x};

//- Check Queue (If Not Empty)
private _result = if (_queue findIf {true} > -1) then {
  //- If not Empty
  private _endFlag = _ctrl getVariable ["Animation_EndWithOffset_F", []];
  {
    if !(isNil {_x}) then {
      _endFlag set [_forEachIndex, _x];
    };
  } foreach _POS_End;
  _ctrl setVariable ["Animation_EndWithOffset_F", _endFlag];

  _endFlag
} else {
  //- On Empty
  private _endFlag = ctrlPosition _ctrl;
  {
    if !(isNil {_x}) then {
      _endFlag set [_forEachIndex, _x];
    };
  } foreach _POS_End;

  _endFlag
};

if ((count _result) > 2 && {!isNil {_result select 2}} && {(_result select 2) isEqualType 0} && {(_result select 2) < 0.001}) then {
  _result set [2, 0.001];
};
if ((count _result) > 3 && {!isNil {_result select 3}} && {(_result select 3) isEqualType 0} && {(_result select 3) < 0.001}) then {
  _result set [3, 0.001];
};

//- Update Interface
[
  _ctrl,
  _type,
  [_POS_Start,_result,_instant,_BG_IDC],
  _ignore
] call BCE_fnc_Anim_Type;
