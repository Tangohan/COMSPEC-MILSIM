params [["_page","MAP"]]; _page=toUpper _page;
private _allowed=["MAP","HOME","C2","BFT","CHAT","TASK","SSE","INTEL","BDA","BRIEFING","PHOTOS","SETTINGS","STATUS"];
if !(_page in _allowed) then {_page="MAP";};
private _s=uiNamespace getVariable ["COMSPEC_ATAK_State",createHashMap]; private _history=_s getOrDefault ["history",[]]; _history pushBack _page; while {count _history>20} do {_history deleteAt 0;}; _s set ["activePage",_page];
[_page] call comspec_atak_native_fnc_pageRender; ["INFO","UI",format ["Navigate %1",_page]] call comspec_atak_native_fnc_log; true
