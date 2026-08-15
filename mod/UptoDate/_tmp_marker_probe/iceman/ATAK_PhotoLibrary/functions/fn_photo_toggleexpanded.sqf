private _record = call Iceman_fnc_photo_getSelectedRecord;
if (_record isEqualTo []) exitWith {
    ["PHOTOS", "Take or select a picture first.", 3] call cTab_fnc_addNotification;
    false
};

private _expanded = !(missionNamespace getVariable ["Iceman_PhotoLibrary_expanded", false]);
missionNamespace setVariable ["Iceman_PhotoLibrary_expanded", _expanded];
call Iceman_fnc_photo_applyLayout;
call Iceman_fnc_photo_showPreview;
true
