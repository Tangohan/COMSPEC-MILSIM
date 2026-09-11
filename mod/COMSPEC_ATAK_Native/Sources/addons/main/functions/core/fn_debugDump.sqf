private _s=uiNamespace getVariable ["COMSPEC_ATAK_State",createHashMap]; private _d=uiNamespace getVariable ["COMSPEC_ATAK_Data",createHashMap];
private _u=_d getOrDefault ["units",createHashMap]; private _m=_d getOrDefault ["markers",createHashMap];
private _display=_s getOrDefault ["display",displayNull]; private _controlCount=if (isNull _display) then {0} else {count (allControls _display)};
["INFO","DEBUG",format ["FPS=%1 units=%2 markers=%3 controls=%4 PFH=%5 net=%6 page=%7 tool=%8 selected=%9 queues(messages=%10,notify=%11) ext=%12 mod=1.0.0",round diag_fps,count _u,count _m,_controlCount,_s getOrDefault ["scheduler",-1],_s getOrDefault ["networkState","OFFLINE"],_s getOrDefault ["activePage","MAP"],_s getOrDefault ["mapMode","SELECT"],_s getOrDefault ["selectedEntity",createHashMap],count (_d getOrDefault ["messages",[]]),count (_s getOrDefault ["notifications",[]]),missionNamespace getVariable ["COMSPEC_ExtensionVersion","unknown"]]] call comspec_atak_native_fnc_log;
true
