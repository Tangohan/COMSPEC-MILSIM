/*
    Chargement du tableau des liens : carte de fond, clics, rafraîchissement.
*/
params [["_disp", displayNull, [displayNull]]];
if (isNull _disp) exitWith {};
uiNamespace setVariable ["comspec_sse_chartDisplay", _disp];
private _st = uiNamespace getVariable ["comspec_sse_chartState", createHashMap];
private _zeus = _st getOrDefault ["zeus", false];

private _map = _disp displayCtrl 93510;
_map ctrlAddEventHandler ["Draw", { _this call comspec_sse_fnc_chartDraw }];
_map ctrlAddEventHandler ["MouseButtonClick", { _this call comspec_sse_fnc_chartPick }];
_map ctrlAddEventHandler ["MouseButtonDblClick", {
    params ["_ctrl"];
    private _st = uiNamespace getVariable ["comspec_sse_chartState", createHashMap];
    private _n = (_st getOrDefault ["nodes", createHashMap]) getOrDefault [_st getOrDefault ["sel", ""], createHashMap];
    if (count _n > 0) then { _ctrl ctrlMapAnimAdd [0.25, (ctrlMapScale _ctrl) * 0.6, _n get "pos"]; ctrlMapAnimCommit _ctrl; };
}];

(_disp displayCtrl 93528) ctrlShow _zeus;
if (_zeus) then {
    (_disp displayCtrl 93501) ctrlSetText (("TABLEAU DES LIENS — VUE ZEUS") call comspec_sse_fnc_tr);
    (_disp displayCtrl 93501) ctrlSetBackgroundColor [0.2, 0.12, 0.04, 1];
};
(_disp displayCtrl 93531) ctrlSetStructuredText parseText ((([
    "<t color='#8f8'>LÉGENDE</t>",
    "<img image='\A3\ui_f\data\map\markers\nato\o_hq.paa'/> organisation (couleur = menace)",
    "<img image='\A3\ui_f\data\map\vehicleicons\iconMan_ca.paa'/> personne",
    "<img image='\A3\ui_f\data\igui\cfg\simpleTasks\types\documents_ca.paa'/> document  <img image='\A3\ui_f\data\igui\cfg\simpleTasks\types\radio_ca.paa'/> appareil",
    "<t color='#ffffff'>plein</t> confirmé · <t color='#f0c060'>?</t> soupçonné" + (["", " · <t color='#777777'>gris</t> inconnu des joueurs"] select _zeus),
    "<t color='#9aa' size='0.85'>Clic : détail. Double-clic : zoom. Molette : échelle.</t>"
] joinString "<br/>")) call comspec_sse_fnc_tr);

[true] call comspec_sse_fnc_chartRefresh;
