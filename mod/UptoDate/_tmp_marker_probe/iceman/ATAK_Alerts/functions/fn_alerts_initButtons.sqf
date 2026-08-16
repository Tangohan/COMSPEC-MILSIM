#include "..\script_component.hpp"

/*
    Remet la barre du bas en mode Reports (pas Tasks : Enter / Live Feed).
*/
params ["_ctrlBnts", "_ctrlPOS", "_subMenu", "_interfaceInit"];
_ctrlBnts params ["_bnt_back", "_bnt_Ent", "_bnt_third", "_bnt_result"];

_bnt_back ctrlShow true;
_bnt_back ctrlSetText localize "STR_disp_Back";
_bnt_back ctrlSetPositionX 0;
_bnt_back ctrlSetPositionW (_ctrlPOS # 2);
_bnt_back ctrlCommit 0;

_bnt_Ent ctrlShow true;
_bnt_Ent ctrlSetText "Locate";
_bnt_Ent ctrlSetBackgroundColor [0, 0, 0, 0.5];
_bnt_Ent ctrlSetPositionX (_ctrlPOS # 2);
_bnt_Ent ctrlSetPositionW (_ctrlPOS # 2);
_bnt_Ent ctrlCommit 0;

_bnt_third ctrlShow true;
_bnt_third ctrlSetText "Clear Local";
_bnt_third ctrlSetBackgroundColor [1, 0.25, 0.25, 0.45];
_bnt_third ctrlSetPositionX ((_ctrlPOS # 2) * 2);
_bnt_third ctrlSetPositionW (_ctrlPOS # 2);
_bnt_third ctrlCommit 0;

_bnt_result ctrlShow false;
_bnt_result ctrlEnable false;

call Iceman_fnc_alerts_updatePanel;
