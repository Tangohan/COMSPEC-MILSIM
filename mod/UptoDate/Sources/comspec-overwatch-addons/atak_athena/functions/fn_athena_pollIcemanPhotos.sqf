/*
    Remontée auto Photo Library → Athena : signal « nouvelle photo » uniquement.

    La DLL (COMSPECExtension) file resolve + upload en arrière-plan et surveille
    les dossiers Screenshot. SQF ne doit plus polluer / retry / geler le client.
*/
if (!hasInterface) exitWith {};
if (isNil "Iceman_fnc_photo_getRecords") exitWith {};
if (isNil "comspec_overwatch_connect_fnc_captureReconImage") exitWith {};
if (!(["iceman_photo"] call comspec_overwatch_connect_fnc_isModModuleEnabled)) exitWith {};

private _link = missionNamespace getVariable ["COMSPEC_LinkState", "offline"];
private _ready = missionNamespace getVariable ["COMSPEC_AthenaReady", false];
if (!_ready && {_link isNotEqualTo "linked"}) exitWith {};

private _seen = missionNamespace getVariable ["COMSPEC_Athena_PhotoSeen", []];
if (!(_seen isEqualType [])) then { _seen = []; };

private _records = call Iceman_fnc_photo_getRecords;
if (!(_records isEqualType [])) exitWith {};

// Une seule notification par cycle — le watcher DLL couvre le reste.
private _started = 0;

{
    if (_started >= 1) then { break };
    if (!(_x isEqualType [])) then { continue };
    if ((count _x) < 4) then { continue };

    private _src = if ((count _x) > 1) then { _x select 1 } else { "local" };
    if (_src isEqualTo "received") then { continue };

    private _filePath = _x select 2;
    private _fileName = if ((count _x) > 3) then { _x select 3 } else { "" };
    if (_filePath isEqualTo "") then { continue };

    private _key = toLower _filePath;
    if (_key in _seen) then { continue };

    // Marquer vu immédiatement : chemins Photo Library morts ne doivent jamais re-spammer.
    _seen pushBack _key;
    while { (count _seen) > 120 } do { _seen deleteAt 0; };
    missionNamespace setVariable ["COMSPEC_Athena_PhotoSeen", _seen, false];
    _started = _started + 1;

    if (!isNil "comspec_overwatch_atak_athena_fnc_athena_rememberLocalPhoto") then {
        [_filePath, _fileName] call comspec_overwatch_atak_athena_fnc_athena_rememberLocalPhoto;
    };

    // Signal unique — pas de spawn retry / pas d’attente fichier côté SQF.
    [_filePath, _fileName, true] call comspec_overwatch_atak_athena_fnc_athena_bridgeIcemanPhoto;
} forEach _records;
