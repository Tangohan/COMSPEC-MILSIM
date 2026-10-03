/* Envoie la note de reco par Overwatch (repère d'équipe + Athena). */
[] call comspec_atak_native_fnc_recoDraftSave;
private _d = uiNamespace getVariable ["COMSPEC_ATAK_RecoDraft", createHashMap];
private _grid = ((_d getOrDefault ["grid", ""]) splitString " ") joinString "";
private _pos = getPosASL player;
if (_grid isNotEqualTo "") then {
    private _p = ([_grid] call BIS_fnc_gridToPos) param [0, []];
    if ((count _p) >= 2) then { _pos = [_p select 0, _p select 1, getTerrainHeightASL [_p select 0, _p select 1]]; };
};
private _ok = [trim (_d getOrDefault ["text", ""]), _d getOrDefault ["tag", "other"], _d getOrDefault ["confidence", "vu_direct"], _pos] call comspec_overwatch_connect_fnc_reconPushNote;
if (_ok isEqualTo true) then {
    _d set ["text", ""];
    uiNamespace setVariable ["COMSPEC_ATAK_RecoHint", ["Repère posé. Transmission au poste : voir le message à l'écran.", false]];
} else {
    uiNamespace setVariable ["COMSPEC_ATAK_RecoHint", ["Note non envoyée (voir le message à l'écran).", true]];
};
uiNamespace setVariable ["COMSPEC_ATAK_RecoClearing", true];
[{ ["RECO"] call comspec_atak_native_fnc_pageRender; uiNamespace setVariable ["COMSPEC_ATAK_RecoClearing", false]; }] call CBA_fnc_execNextFrame;
