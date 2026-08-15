private _record = call Iceman_fnc_photo_getSelectedRecord;
if (_record isEqualTo [] || {(_record param [1, "received"]) != "local"}) exitWith {false};

private _mode = missionNamespace getVariable ["Iceman_PhotoLibrary_previewMode", "original"];
missionNamespace setVariable ["Iceman_PhotoLibrary_previewMode", ["original", "live"] select (_mode == "original")];
call Iceman_fnc_photo_showPreview;
call Iceman_fnc_photo_applyLayout;
true
