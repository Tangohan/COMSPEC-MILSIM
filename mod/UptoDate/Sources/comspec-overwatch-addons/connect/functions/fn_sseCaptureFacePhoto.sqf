/*
    Capture une photo de visage pour la fiche SEEK en cours.
    Pose le flag PhotoPending + un stem de fichier pour UploadSsePhoto
    (évite de dépendre d’une capture Steam manuelle).
*/
if (!hasInterface) exitWith { false };

private _disp = uiNamespace getVariable ["COMSPEC_SsePerson_Display", displayNull];
if (isNull _disp) then { _disp = findDisplay 9991; };

private _stem = format ["COMSPEC_SSE_Face_%1", floor (diag_tickTime * 1000)];
screenshot _stem;

uiNamespace setVariable ["COMSPEC_SsePerson_PhotoPending", true];
uiNamespace setVariable ["COMSPEC_SsePerson_PhotoStem", _stem];
uiNamespace setVariable ["COMSPEC_SsePerson_PhotoTakenAt", diag_tickTime];

if (!isNull _disp) then {
    private _lcd = _disp displayCtrl 9525;
    if (!isNull _lcd) then {
        _lcd ctrlSetStructuredText parseText "<t size='0.38' color='#9ed8b4' align='center'>CAPTURE ENREGISTREE</t>";
    };
};

[] call comspec_overwatch_connect_fnc_ssePersonRefreshPanels;

[
    "Photo du visage capturée — elle sera jointe à la transmission de la fiche.",
    "tactical",
    "info"
] call comspec_overwatch_connect_fnc_announce;

true
