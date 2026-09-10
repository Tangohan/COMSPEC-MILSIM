params ["_display"]; disableSerialization; if (!hasInterface || {isNull _display}) exitWith {};
private _state=uiNamespace getVariable ["COMSPEC_ATAK_State",createHashMap]; _state set ["display",_display];
uiNamespace setVariable ["COMSPEC_ATAK_Display",_display];
[] call comspec_atak_native_fnc_layoutApply;
private _railX=safeZoneX+safeZoneW*0.008; private _w=safeZoneW*0.089; private _h=safeZoneH*0.046; private _y=safeZoneY+safeZoneH*0.070;
{ private _b=_display ctrlCreate ["COMSPEC_RscButton",88600+_forEachIndex]; _b ctrlSetText _x; _b ctrlSetPosition [_railX,_y+_forEachIndex*(_h+safeZoneH*0.006),_w,_h]; _b ctrlCommit 0; _b setVariable ["page",_x]; _b ctrlAddEventHandler ["ButtonClick",{ params ["_c"]; [_c getVariable ["page","MAP"]] call comspec_atak_native_fnc_navigate; }]; } forEach ["HOME","MAP","C2","BFT","CHAT","TASK","SSE","INTEL","BDA","BRIEFING","PHOTOS","SETTINGS","STATUS"];
[] call comspec_atak_native_fnc_schedulerStart; ["MAP"] call comspec_atak_native_fnc_navigate;
["INFO","UI","display_created"] call comspec_atak_native_fnc_log; ["INFO","MAP","map_control_ready"] call comspec_atak_native_fnc_log;
["SUCCESS","COMSPEC ATAK NATIVE prêt",4,50] call comspec_atak_native_fnc_notify;
