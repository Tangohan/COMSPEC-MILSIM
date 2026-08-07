private _disp = findDisplay 93300;
if (isNull _disp) exitWith { false };

private _center = [] call comspec_sse_fnc_uiGetRecord;
if (isNull _center) then { _center = player; };

private _pct = if (!isNil "comspec_sse_fnc_siteCompleteness") then { [_center, 50] call comspec_sse_fnc_siteCompleteness } else { 0 };
private _list = if (!isNil "comspec_sse_fnc_listSiteEntities") then { [_center, 50] call comspec_sse_fnc_listSiteEntities } else { [] };
private _tri = if (!isNil "comspec_sse_fnc_triageSite") then { [_center, 50] call comspec_sse_fnc_triageSite } else { [] };

private _now = { (_x getOrDefault ["triage", ""]) == "EXPLOIT_NOW" } count _tri;
private _low = { (_x getOrDefault ["triage", ""]) == "LOW_VALUE" } count _tri;
private _untouched = { (_x getOrDefault ["level", "NONE"]) == "NONE" } count _list;

(_disp displayCtrl 93310) ctrlSetStructuredText parseText format [
    "<t color='#8f8'>SITE EXPLOITATION</t><br/>Complétude: <t color='#ff8'>%1%%</t><br/>Éléments: %2<br/>À exploiter maintenant: %3<br/>Faible valeur: %4<br/>Non traités: %5<br/>Risque: %6",
    _pct, count _list, _now, _low, _untouched,
    if (_now > 2) then {"ÉLEVÉ — prioriser COMMS/HVT"} else {"MODÉRÉ"}
];

private _lb = _disp displayCtrl 93311;
lbClear _lb;
missionNamespace setVariable ["comspec_sse_uiSiteList", _tri];
{
    _lb lbAdd format [
        "[%1] %2 · %3 · V%4",
        _x getOrDefault ["triage", "?"],
        _x getOrDefault ["type", "?"],
        _x getOrDefault ["level", "?"],
        _x getOrDefault ["INTEL_VALUE", 0]
    ];
} forEach _tri;
if (_tri isEqualTo []) then { _lb lbAdd "(aucun élément SSE dans le rayon)"; };

(_disp displayCtrl 93312) ctrlSetStructuredText parseText "<t color='#8f8'>DÉTAIL</t><br/>Sélectionnez un élément à gauche.<br/>Priorités = triage automatique après fouille.";
true
