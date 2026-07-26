/*
    Update CAS dialog controls with current CAS data from namespace.
*/
private _disp = uiNamespace getVariable ["COMSPEC_CAS_Display", displayNull];
if (isNull _disp) exitWith {};

private _id = missionNamespace getVariable ["COMSPEC_CurrentCASId", ""];
private _status = missionNamespace getVariable ["COMSPEC_CurrentCASStatus", ""];
private _assigned = missionNamespace getVariable ["COMSPEC_CurrentCASAssigned", "—"];
private _lines = missionNamespace getVariable ["COMSPEC_CurrentCASLines", ["—","—","—","—","—","—","—","—","—"]];

(_disp displayCtrl 8002) ctrlSetText ("Assigned: " + _assigned);
(_disp displayCtrl 8003) ctrlSetText ("Status: " + _status);

private _lineLabels = ["1. IP/BP","2. Heading","3. Distance","4. Target Elev","5. Target Desc","6. Target Location","7. Mark","8. Friendlies","9. Egress"];
private _html = "";
for "_i" from 0 to 8 do {
    _html = _html + "<t color='#aaa'>" + (_lineLabels select _i) + "</t>: " + (_lines select _i) + "<br/>";
};
(_disp displayCtrl 8010) ctrlSetStructuredText (parseText _html);

// Laser from line 7 if it looks like a code
private _line7 = _lines param [6, "—"];
(_disp displayCtrl 8011) ctrlSetText ("Laser / Mark: " + _line7);
