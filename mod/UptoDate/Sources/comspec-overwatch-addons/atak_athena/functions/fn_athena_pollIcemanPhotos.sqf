/*

    Remontée automatique des Quick Pictures / Photo Library vers Athena (ATAK web).

    Surveille Iceman_fnc_photo_getRecords et upload les nouveaux fichiers sans action joueur.

    Ne marque une photo comme traitée qu’après un envoi réussi (reprise auto en cas d’échec).

*/

if (!hasInterface) exitWith {};



if (isNil "Iceman_fnc_photo_getRecords") exitWith {};

if (isNil "comspec_overwatch_connect_fnc_captureReconImage") exitWith {};

if (!(["iceman_photo"] call comspec_overwatch_connect_fnc_isModModuleEnabled)) exitWith {};



// Attendre une liaison utilisable (pas seulement le handshake démarré)

private _link = missionNamespace getVariable ["COMSPEC_LinkState", "offline"];

private _ready = missionNamespace getVariable ["COMSPEC_AthenaReady", false];

if (!_ready && {_link isNotEqualTo "linked"}) exitWith {};



private _seen = missionNamespace getVariable ["COMSPEC_Athena_PhotoSeen", []];

if (!(_seen isEqualType [])) then { _seen = []; };

private _inflight = missionNamespace getVariable ["COMSPEC_Athena_PhotoInflight", []];

if (!(_inflight isEqualType [])) then { _inflight = []; };



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

    if (_key in _inflight) then { continue };



    _inflight pushBack _key;

    while { (count _inflight) > 40 } do { _inflight deleteAt 0; };

    missionNamespace setVariable ["COMSPEC_Athena_PhotoInflight", _inflight, false];



    if (!isNil "comspec_overwatch_atak_athena_fnc_athena_rememberLocalPhoto") then {

        [_filePath, _fileName] call comspec_overwatch_atak_athena_fnc_athena_rememberLocalPhoto;

    };



    // Délai : laisser Photo Library finaliser l’écriture disque

    [_filePath, _fileName, _key] spawn {

        params ["_path", "_name", "_key"];

        uiSleep 1.5;



        private _ok = [_path, _name, true] call comspec_overwatch_atak_athena_fnc_athena_bridgeIcemanPhoto;

        if (!(_ok isEqualType true)) then { _ok = false; };



        // Repli court si le fichier n’était pas encore écrit

        if (!_ok) then {

            private _detail = toLower (str (missionNamespace getVariable ["COMSPEC_LastReconUploadDetail", ""]));

            if ((_detail find "file_not_found") >= 0) then {

                uiSleep 2.5;

                _ok = [_path, _name, true] call comspec_overwatch_atak_athena_fnc_athena_bridgeIcemanPhoto;

                if (!(_ok isEqualType true)) then { _ok = false; };

            };

        };



        private _inf = missionNamespace getVariable ["COMSPEC_Athena_PhotoInflight", []];

        if (_inf isEqualType []) then {

            _inf = _inf - [_key];

            missionNamespace setVariable ["COMSPEC_Athena_PhotoInflight", _inf, false];

        };



        if (_ok) then {

            private _done = missionNamespace getVariable ["COMSPEC_Athena_PhotoSeen", []];

            if (!(_done isEqualType [])) then { _done = []; };

            if !(_key in _done) then {

                _done pushBack _key;

                while { (count _done) > 100 } do { _done deleteAt 0; };

                missionNamespace setVariable ["COMSPEC_Athena_PhotoSeen", _done, false];

            };

            private _uploaded = missionNamespace getVariable ["COMSPEC_Athena_PhotoUploaded", []];

            if (!(_uploaded isEqualType [])) then { _uploaded = []; };

            if !(_key in _uploaded) then {

                _uploaded pushBack _key;

                while { (count _uploaded) > 100 } do { _uploaded deleteAt 0; };

                missionNamespace setVariable ["COMSPEC_Athena_PhotoUploaded", _uploaded, false];

            };

        };

        // Échec : hors inflight et hors seen → le prochain poll réessaie

    };

} forEach _records;

