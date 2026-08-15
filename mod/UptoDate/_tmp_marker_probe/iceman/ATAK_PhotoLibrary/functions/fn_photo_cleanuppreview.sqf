private _cam = uiNamespace getVariable ["Iceman_PhotoLibrary_previewCamera", objNull];
if (!isNull _cam) then {
    _cam cameraEffect ["TERMINATE", "BACK", "Iceman_PhotoLibrary_preview"];
    camDestroy _cam;
};

uiNamespace setVariable ["Iceman_PhotoLibrary_previewCamera", objNull];
true
