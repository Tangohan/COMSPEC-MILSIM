disableSerialization;
private _edit = uiNamespace getVariable ["COMSPEC_ATAK_ChatEdit", controlNull];
if (!isNull _edit) then { uiNamespace setVariable ["COMSPEC_ATAK_ChatDraft", ctrlText _edit]; };
uiNamespace setVariable ["COMSPEC_ATAK_ChatEdit", controlNull];
private _newCh = uiNamespace getVariable ["COMSPEC_ATAK_ChatNewEdit", controlNull];
if (!isNull _newCh) then { uiNamespace setVariable ["COMSPEC_ATAK_ChatNewDraft", ctrlText _newCh]; };
uiNamespace setVariable ["COMSPEC_ATAK_ChatNewEdit", controlNull];
// Live cam : la caméra ne tourne que sur sa page.
[] call comspec_atak_native_fnc_livecamStop;
{ ctrlDelete _x; } forEach (uiNamespace getVariable ["COMSPEC_ATAK_PageControls", []]);
uiNamespace setVariable ["COMSPEC_ATAK_PageControls", []];
// Cartouches de marqueurs de la carte : cachés hors carte (la carte les réaffiche à son prochain dessin).
{ if (!isNull _x) then { _x ctrlShow false; }; } forEach (uiNamespace getVariable ["COMSPEC_ATAK_MarkerTagPool", []]);
uiNamespace setVariable ["COMSPEC_ATAK_Form", createHashMap];
private _d = ([] call comspec_atak_native_fnc_display);
if (!isNull _d) then { (_d displayCtrl 88531) ctrlSetScrollValues [0, 0]; };
true
