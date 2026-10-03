disableSerialization;
private _edit = uiNamespace getVariable ["COMSPEC_ATAK_ChatEdit", controlNull];
if (!isNull _edit) then { uiNamespace setVariable ["COMSPEC_ATAK_ChatDraft", ctrlText _edit]; };
uiNamespace setVariable ["COMSPEC_ATAK_ChatEdit", controlNull];
{ ctrlDelete _x; } forEach (uiNamespace getVariable ["COMSPEC_ATAK_PageControls", []]);
uiNamespace setVariable ["COMSPEC_ATAK_PageControls", []];
private _d = findDisplay 88500;
if (!isNull _d) then { (_d displayCtrl 88531) ctrlSetScrollValues [0, 0]; };
true
