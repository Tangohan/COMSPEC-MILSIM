/*
    Télécharge (et met en cache localement) l'image d'une diapositive de briefing.
    Params: [_slide] où _slide = [id, title, sortOrder, imageUrl] (voir fn_getBriefingSlides).

    Retourne : le chemin de fichier local (string), ou "" en cas d'échec.
*/
params [["_slide", [], [[]]]];
if (count _slide < 4) exitWith { "" };

private _id = _slide select 0;
private _imageUrl = _slide select 3;
if (_imageUrl isEqualTo "") exitWith { "" };

private _raw = ["COMSPECExtension" callExtension ["DownloadBriefingSlideImage", [_imageUrl, str _id]]] call comspec_overwatch_connect_fnc_extResult;
private _parts = _raw splitString "|";
private _prefix = if (count _parts >= 1) then { _parts select 0 } else { "" };

if (_prefix != "OK") exitWith {
    diag_log format ["[COMSPEC] Failed to download slide %1 : %2", _id, _raw];
    ""
};

// Chemin local : normaliser \ → / pour RscPicture / setObjectTexture sous Windows.
private _path = if (count _parts >= 2) then { _parts select 1 } else { "" };
if (_path != "") then {
    _path = (_path splitString "\") joinString "/";
};
_path
