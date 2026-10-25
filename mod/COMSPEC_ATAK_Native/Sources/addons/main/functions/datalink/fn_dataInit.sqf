/*
    Extraction de données : branchements côté joueur (appelé une fois depuis XEH_postInitClient).
    - action « Brancher l'ATAK (câble DL-7) » en regardant un support (molette, remise après réapparition) ;
    - action ACE sur chaque support connu (COMSPEC_ATAK_DataNodes), ajoutée quand il apparaît ;
    - tick de la liaison (câble arraché, étapes, transfert, voyants du module) ;
    - fichiers partagés par un équipier, messages pour Zeus.
*/
if (!hasInterface) exitWith {};
if (!isNil "COMSPEC_ATAK_DataInitDone") exitWith {};
COMSPEC_ATAK_DataInitDone = true;

["comspec_atak_native_dataNote", {
    params [["_msg", ""]];
    // Rendu dans le téléphone (centre de notifications), jamais par-dessus l'écran du jeu.
    ["INFO", _msg, 5, 30] call comspec_atak_native_fnc_notify;
    if (!isNull (findDisplay 312)) then { [_msg] call BIS_fnc_showCuratorFeedbackMessage; };
}] call CBA_fnc_addEventHandler;

["comspec_atak_native_dataShare", {
    params [["_from", "?"], ["_files", []]];
    private _lib = missionNamespace getVariable ["COMSPEC_ATAK_DataLib", []];
    {
        if (_x isEqualType [] && {(count _x) >= 5}) then {
            private _e = +_x;
            _e set [7, _from];
            private _i = _lib findIf { (_x select 0) isEqualTo (_e select 0) && {(_x select 1) isEqualTo (_e select 1)} };
            if (_i >= 0) then { _lib set [_i, _e]; } else { _lib pushBack _e; };
        };
    } forEach _files;
    missionNamespace setVariable ["COMSPEC_ATAK_DataLib", _lib];
    ["MESSAGE", format ["%1 vous a partagé %2 fichier(s) (app Extraction)", _from, count _files], 5, 30] call comspec_atak_native_fnc_notify;
}] call CBA_fnc_addEventHandler;

private _addAction = {
    if (!isNil { player getVariable "COMSPEC_ATAK_DataActions" }) exitWith {};
    private _a = player addAction [
        "<img image='\z\comspec_atak_native\addons\main\data\app_datalink.paa'/> Brancher l'ATAK (câble DL-7)",
        { ["connect", cursorObject] call comspec_atak_native_fnc_dataAction; },
        nil, 5.5, true, true, "",
        "['can', cursorObject] call comspec_atak_native_fnc_dataAction", 6
    ];
    private _b = player addAction [
        "<img image='\z\comspec_atak_native\addons\main\data\app_datalink.paa'/> Débrancher le câble DL-7",
        { ["disconnect"] call comspec_atak_native_fnc_dataAction; },
        nil, 5.4, false, true, "",
        "((uiNamespace getVariable ['COMSPEC_ATAK_DataLink', createHashMap]) getOrDefault ['phase', '']) isNotEqualTo ''", 6
    ];
    player setVariable ["COMSPEC_ATAK_DataActions", [_a, _b]];
};
call _addAction;
player addEventHandler ["Respawn", { [{ player setVariable ["COMSPEC_ATAK_DataActions", nil]; [] call (missionNamespace getVariable ["COMSPEC_ATAK_DataAddAction", {}]); }, [], 1] call CBA_fnc_waitAndExecute; }];
missionNamespace setVariable ["COMSPEC_ATAK_DataAddAction", _addAction];

// ACE : action sur chaque support, posée dès qu'il apparaît dans la liste du serveur.
if (!isNil "ace_interact_menu_fnc_createAction") then {
    [{
        private _done = missionNamespace getVariable ["COMSPEC_ATAK_DataAce", []];
        {
            if (!isNull _x && {!(_x in _done)}) then {
                _done pushBack _x;
                private _act = ["COMSPEC_ATAK_DataLink", "Brancher l'ATAK (câble DL-7)", "\z\comspec_atak_native\addons\main\data\app_datalink.paa",
                    { params ["_target"]; ["connect", _target] call comspec_atak_native_fnc_dataAction; },
                    { params ["_target"]; ["can", _target] call comspec_atak_native_fnc_dataAction },
                    {}, [], [0, 0, 0], 3
                ] call comspec_atak_native_fnc_aceAction;
                // Corps / joueur capturé : sous « Interactions » (point au torse) ; à la racine, le point tombait aux pieds
                // de l'unité, hors de portée (2 m de la tête). Objets : racine, portée du câble.
                [_x, 0, [[], ["ACE_MainActions"]] select (_x isKindOf "CAManBase"), _act] call ace_interact_menu_fnc_addActionToObject;
            };
        } forEach (missionNamespace getVariable ["COMSPEC_ATAK_DataNodes", []]);
        missionNamespace setVariable ["COMSPEC_ATAK_DataAce", _done select { !isNull _x }];
    }, 5] call CBA_fnc_addPerFrameHandler;
};

[{ ["tick"] call comspec_atak_native_fnc_dataAction; }, 0.25] call CBA_fnc_addPerFrameHandler;
true
