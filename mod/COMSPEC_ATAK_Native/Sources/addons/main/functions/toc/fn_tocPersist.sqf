/*
    Écrans TOC persistants (fn_tocScreen) : gardés par le serveur d'une partie à l'autre, sur la même carte et la même
    mission (profil du serveur, COMSPEC_ATAK_TocSave_<carte>_<mission>), si le réglage « Garder les écrans TOC » est coché.
      - écrans posés en cours de partie (module Zeus, objet créé par le module) : recréés à leur place ;
      - écrans de la mission (Eden) : retrouvés à leur place (même classe à 1,5 m), leur affichage et leur cadrage remis ;
      - flux des écrans (comptes rendus, messages du poste) : repris, grisé « partie précédente ».
    Pendant la partie, le registre et l'état des écrans sont des variables publiques : les joueurs qui rejoignent ou
    réapparaissent retrouvent les mêmes écrans.
    Modes (serveur) : ["save"] (écriture regroupée, 10 s après le dernier changement) | ["write"] | ["restore"] | ["clear"]
*/
params [["_mode", "save"]];
if (!isServer) exitWith {};
private _key = format ["COMSPEC_ATAK_TocSave_%1_%2", worldName, missionName];
private _on = missionNamespace getVariable ["comspec_atak_native_toc_persist", true];
switch (_mode) do {
    case "save": {
        // Rien avant la reprise : la sauvegarde de la partie précédente ne doit pas être écrasée par une liste vide.
        if (!_on || {!(missionNamespace getVariable ["COMSPEC_ATAK_TocRestored", false])}) exitWith {};
        if (missionNamespace getVariable ["COMSPEC_ATAK_TocSaveQueued", false]) exitWith {};
        missionNamespace setVariable ["COMSPEC_ATAK_TocSaveQueued", true];
        [{ ["write"] call comspec_atak_native_fnc_tocPersist; }, [], 10] call CBA_fnc_waitAndExecute;
    };
    case "write": {
        missionNamespace setVariable ["COMSPEC_ATAK_TocSaveQueued", false];
        if (!_on || {!(missionNamespace getVariable ["COMSPEC_ATAK_TocRestored", false])}) exitWith {};
        private _scr = ((missionNamespace getVariable ["COMSPEC_ATAK_TocScreens", []]) select { !isNull _x && {alive _x} }) apply {
            [typeOf _x, getPosASL _x, vectorDir _x, vectorUp _x, _x getVariable ["COMSPEC_ATAK_TocScr", []]]
        };
        // Flux de la partie en cours seulement (celui de la partie précédente a déjà été gardé une fois).
        private _ev = [];
        { _ev pushBack [_x, (_y select { !(_x param [8, false]) }) select [0 max ((count _y) - 30)]]; } forEach (["events"] call comspec_atak_native_fnc_tocFeed);
        profileNamespace setVariable [_key, [1, systemTimeUTC, _scr, _ev]];
        // Écriture du profil du serveur regroupée (fn_saveProfile : une par minute au plus, tout de suite en fin de mission).
        [] call comspec_atak_native_fnc_saveProfile;
    };
    case "clear": {
        profileNamespace setVariable [_key, nil];
        [] call comspec_atak_native_fnc_saveProfile;
    };
    case "restore": {
        if (missionNamespace getVariable ["COMSPEC_ATAK_TocRestored", false]) exitWith {};
        missionNamespace setVariable ["COMSPEC_ATAK_TocRestored", true];
        // Fin de mission : dernière écriture.
        { addMissionEventHandler [_x, { ["write"] call comspec_atak_native_fnc_tocPersist; [true] call comspec_atak_native_fnc_saveProfile; }]; } forEach ["Ended", "MPEnded"];
        if (!_on) exitWith {};
        private _data = profileNamespace getVariable [_key, []];
        if !(_data isEqualType [] && {(count _data) >= 4}) exitWith {};
        _data params ["", "", ["_scr", []], ["_ev", []]];
        private _n = 0;
        {
            _x params [["_cls", ""], ["_pos", [0, 0, 0]], ["_vd", [0, 1, 0]], ["_vu", [0, 0, 1]], ["_cfg", []]];
            if (!(isClass (configFile >> "CfgVehicles" >> _cls)) || {(count _cfg) < 8}) then { continue };
            private _o = ((nearestObjects [ASLToAGL _pos, [_cls], 1.5]) select { alive _x }) param [0, objNull];
            if (isNull _o) then {
                // Écran de la mission disparu de la mission (Eden modifié) : pas recréé.
                if !(_cfg select 7) then { continue };
                _o = createVehicle [_cls, ASLToAGL _pos, [], 0, "CAN_COLLIDE"];
                _o setPosASL _pos;
                _o setVectorDirAndUp [_vd, _vu];
                _o setVariable ["COMSPEC_ATAK_TocOwned", true, true];
                { _x addCuratorEditableObjects [[_o], false]; } forEach allCurators;
            };
            ["add", [_o, _cfg select 0, _cfg select 1, _cfg select 2, _cfg select 3, _cfg select 6, _cfg select 7]] call comspec_atak_native_fnc_tocScreen;
            // Cadrage gardé (demi-largeur, centre).
            private _c = +(_o getVariable ["COMSPEC_ATAK_TocScr", []]);
            if ((count _c) >= 8) then { _c set [4, _cfg select 4]; _c set [5, _cfg select 5]; _o setVariable ["COMSPEC_ATAK_TocScr", _c, true]; };
            _n = _n + 1;
        } forEach _scr;
        private _hm = createHashMap;
        { if (_x isEqualType [] && {(count _x) >= 2}) then { _hm set [_x select 0, _x select 1]; }; } forEach _ev;
        ["setEvents", _hm] call comspec_atak_native_fnc_tocFeed;
        ["INFO", "TOC", format ["Écrans TOC repris de la partie précédente : %1", _n]] call comspec_atak_native_fnc_log;
    };
};
true
