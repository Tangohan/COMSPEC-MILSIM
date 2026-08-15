params ["_list", "_row"];

if (isNull _list || {_row < 0} || {_list getVariable ["IcemanPhotoUpdating", false]}) exitWith {false};

private _id = _list lbData _row;
if (_id == "") exitWith {false};

missionNamespace setVariable ["Iceman_PhotoLibrary_selected", _id];
missionNamespace setVariable ["Iceman_PhotoLibrary_deleteConfirm", ""];

private _record = call Iceman_fnc_photo_getSelectedRecord;
private _source = _record param [1, "received"];
missionNamespace setVariable ["Iceman_PhotoLibrary_previewMode", ["live", "original"] select (_source == "local")];

call Iceman_fnc_photo_showPreview;
call Iceman_fnc_photo_applyLayout;
true
