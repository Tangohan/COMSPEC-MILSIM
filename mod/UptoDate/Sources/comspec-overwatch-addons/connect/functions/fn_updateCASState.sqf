/*
    Update CAS dialog controls with current CAS data from namespace.
*/
private _disp = uiNamespace getVariable ["COMSPEC_CAS_Display", displayNull];
if (isNull _disp) exitWith {};

private _id = missionNamespace getVariable ["COMSPEC_CurrentCASId", ""];
private _status = missionNamespace getVariable ["COMSPEC_CurrentCASStatus", ""];
private _assigned = missionNamespace getVariable ["COMSPEC_CurrentCASAssigned", "—"];
private _lines = missionNamespace getVariable ["COMSPEC_CurrentCASLines", ["—","—","—","—","—","—","—","—","—"]];

(_disp displayCtrl 8002) ctrlSetText ("Appareil assigné : " + _assigned);
(_disp displayCtrl 8003) ctrlSetText ("État : " + _status);

private _lineLabels = ["1. IP/BP","2. Cap","3. Distance","4. Élév. cible","5. Desc. cible","6. Position","7. Marquage","8. Amis","9. Sortie"];
private _html = "";
for "_i" from 0 to 8 do {
    _html = _html + "<t color='#aaa'>" + (_lineLabels select _i) + "</t>: " + (_lines select _i) + "<br/>";
};
(_disp displayCtrl 8010) ctrlSetStructuredText (parseText _html);

private _line7 = _lines param [6, "—"];
(_disp displayCtrl 8011) ctrlSetText ("Laser / marquage : " + _line7);
