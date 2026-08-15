#include "..\script_component.hpp"

params ["_ctrlBnts", "_ctrlPOS", "_subMenu", "_interfaceInit"];
_ctrlBnts params ["_bnt_back", "_bnt_Ent", "_bnt_third", "_bnt_result"];

{
    _x ctrlShow true;
    _x ctrlSetPositionX ((_ctrlPOS # 2) * _forEachIndex);
    _x ctrlSetPositionW (_ctrlPOS # 2);
    _x ctrlCommit 0;
} forEach _ctrlBnts;

_bnt_Ent ctrlSetText "Generate";
_bnt_Ent ctrlSetBackgroundColor [0,0,0,0.5];

_bnt_third ctrlSetText "Clear";
_bnt_third ctrlSetBackgroundColor [1,0.25,0.25,0.45];

call Iceman_fnc_elev_updatePanel;
