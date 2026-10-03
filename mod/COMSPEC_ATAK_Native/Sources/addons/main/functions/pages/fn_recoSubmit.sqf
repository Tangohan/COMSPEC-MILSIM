/* Envoie la note de reco par Overwatch (repère d'équipe + Athena). */
[] call comspec_atak_native_fnc_recoDraftSave;
private _d = uiNamespace getVariable ["COMSPEC_ATAK_RecoDraft", createHashMap];
private _pos = if ((_d getOrDefault ["where", "look"]) isEqualTo "me") then { getPosASL player } else { [] call comspec_overwatch_connect_fnc_reconLookPos };
private _ok = [trim (_d getOrDefault ["text", ""]), _d getOrDefault ["tag", "other"], _d getOrDefault ["confidence", "vu_direct"], _pos] call comspec_overwatch_connect_fnc_reconPushNote;
if (_ok isEqualTo true) then {
    _d set ["text", ""];
    uiNamespace setVariable ["COMSPEC_ATAK_RecoHint", ["Note envoyée et repère posé.", false]];
} else {
    uiNamespace setVariable ["COMSPEC_ATAK_RecoHint", ["Note non envoyée (voir le message à l'écran).", true]];
};
uiNamespace setVariable ["COMSPEC_ATAK_RecoClearing", true];
[{ ["RECO"] call comspec_atak_native_fnc_pageRender; uiNamespace setVariable ["COMSPEC_ATAK_RecoClearing", false]; }] call CBA_fnc_execNextFrame;
