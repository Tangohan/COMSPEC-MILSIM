/*
    Remontée automatique des Quick Pictures / Photo Library vers Athena.
    Surveille Iceman_fnc_photo_getRecords et upload les nouveaux fichiers.
*/
if (!hasInterface) exitWith {};

private _seen = missionNamespace getVariable ["COMSPEC_Athena_PhotoSeen", []];
if (!(_seen isEqualType [])) then { _seen = []; };

if (isNil "Iceman_fnc_photo_getRecords") exitWith {};
if (isNil "comspec_overwatch_connect_fnc_captureReconImage") exitWith {};
if (!(["iceman_photo"] call comspec_overwatch_connect_fnc_isModModuleEnabled)) exitWith {};

private _records = call Iceman_fnc_photo_getRecords;
if (!(_records isEqualType [])) exitWith {};

{
    if (!(_x isEqualType [])) then { continue };
    if ((count _x) < 4) then { continue };

    private _src = if ((count _x) > 1) then { _x select 1 } else { "local" };
    // Ne remonter que les captures locales (pas les photos reçues d’autres joueurs)
    if (_src isEqualTo "received") then { continue };

    private _filePath = _x select 2;
    private _fileName = if ((count _x) > 3) then { _x select 3 } else { "" };
    if (_filePath isEqualTo "") then { continue };

    private _key = toLower _filePath;
    if (_key in _seen) then { continue };

    _seen pushBack _key;
    while { (count _seen) > 80 } do { _seen deleteAt 0; };
    missionNamespace setVariable ["COMSPEC_Athena_PhotoSeen", _seen, false];

    if (!isNil "comspec_overwatch_atak_athena_fnc_athena_rememberLocalPhoto") then {
        [_filePath, _fileName] call comspec_overwatch_atak_athena_fnc_athena_rememberLocalPhoto;
    };

    // Délai court : laisser Photo Library finaliser l’écriture disque
    [_filePath, _fileName] spawn {
        params ["_path", "_name"];
        uiSleep 0.4;
        [_path, _name] call comspec_overwatch_atak_athena_fnc_athena_bridgeIcemanPhoto;
    };
} forEach _records;
