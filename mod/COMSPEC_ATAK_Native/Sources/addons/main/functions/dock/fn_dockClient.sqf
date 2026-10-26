/*
    Socle de recharge CS-10, côté joueur (le serveur décide : fn_dockServer).
    Params : [action, arguments]
      "phone"   : objet téléphone porté (catalogue fn_deviceCatalog, le nôtre d'abord), "" sinon
      "free"    : [socle] -> premier emplacement libre (0-9), -1 si plein
      "put"     : [socle, emplacement (-1 : premier libre)] -> pose le téléphone porté, avec sa charge et son état
      "take"    : [socle, emplacement] -> reprend le téléphone (charge et état rendus)
      "status"  : [socle] -> fiche des 10 emplacements
      "deploy"  : pose le kit devant soi (sur une table ou au sol)      "pack" : [socle] -> replie un socle vide
      "result"  : réponse du serveur [action, ok, message, données]
*/
params [["_act", "phone"], ["_a", []]];
// Retour affiché DANS le téléphone (centre de notifications + bandeau à la prochaine ouverture), jamais sur l'écran de jeu.
// Texte traduit à l'affichage (fn_notificationsRender, fn_pageNotifs).
// Sans téléphone sur soi (bandeau jamais vu à temps) : un échec est signalé par le son d'erreur, jamais par un hint.
private _say = {
    params ["_msg", ["_ok", true]];
    [["WARNING", "SUCCESS"] select _ok, _msg, 4, 20] call comspec_atak_native_fnc_notify;
    if (!_ok && {(["phone"] call comspec_atak_native_fnc_dockClient) isEqualTo ""}) then { playSound "addItemFailed"; };
};
// Rend un objet : dans l'inventaire s'il y a la place, sinon posé au sol aux pieds du joueur.
private _give = {
    params ["_item"];
    if (player canAdd _item) exitWith { player addItem _item; };
    private _h = createVehicle ["GroundWeaponHolder", getPosATL player, [], 0, "CAN_COLLIDE"];
    _h addItemCargoGlobal [_item, 1];
    ["Inventaire plein : posé à vos pieds", false] call _say;
};
switch (_act) do {
    case "phone": {
        private _cat = [] call comspec_atak_native_fnc_deviceCatalog;
        private _all = (items player) + (assignedItems player);
        private _i = _all findIf { (toLower _x) isEqualTo "comspec_atak_phone" };
        if (_i < 0) then { _i = _all findIf { (toLower _x) in _cat }; };
        if (_i < 0) exitWith { "" };
        _all select _i
    };
    case "free": {
        _a params ["_dock"];
        (_dock getVariable ["COMSPEC_ATAK_DockSlots", []]) findIf { (count _x) == 0 }
    };
    case "put": {
        _a params ["_dock", ["_slot", -1]];
        private _item = ["phone"] call comspec_atak_native_fnc_dockClient;
        if (_item isEqualTo "") exitWith { ["Pas de téléphone sur vous", false] call _say; };
        if (missionNamespace getVariable ["COMSPEC_ATAK_DockBusy", false]) exitWith {};
        missionNamespace setVariable ["COMSPEC_ATAK_DockBusy", true];
        [] call comspec_atak_native_fnc_close;
        // Retiré tout de suite (pas de double) ; rendu si le serveur refuse.
        if (_item in (items player)) then { player removeItem _item; } else { player unlinkItem _item; };
        private _dev = missionNamespace getVariable ["COMSPEC_ATAK_Device", createHashMap];
        private _lv = missionNamespace getVariable ["COMSPEC_ATAK_Battery", 100];
        ["comspec_atak_native_dockPut", [_dock, player, _slot, _item, _lv, [[], (keys _dev) apply { [_x, _dev get _x] }] select (_dev isEqualType createHashMap),
            profileNamespace getVariable ["COMSPEC_ATAK_ShellTint", "coyote"]]] call CBA_fnc_serverEvent;
        playSound "ClickSoft";
        [{ missionNamespace setVariable ["COMSPEC_ATAK_DockBusy", false]; }, [], 5] call CBA_fnc_waitAndExecute;
    };
    case "take": {
        _a params ["_dock", "_slot"];
        if (missionNamespace getVariable ["COMSPEC_ATAK_DockBusy", false]) exitWith {};
        missionNamespace setVariable ["COMSPEC_ATAK_DockBusy", true];
        ["comspec_atak_native_dockTake", [_dock, player, _slot]] call CBA_fnc_serverEvent;
        [{ missionNamespace setVariable ["COMSPEC_ATAK_DockBusy", false]; }, [], 5] call CBA_fnc_waitAndExecute;
    };
    case "status": {
        // Fiche sur une ligne, dans le téléphone (bandeau + centre de notifications) ; sans téléphone, l'état se lit
        // sur le socle lui-même (écran et voyant de chaque emplacement, fn_dockVisual) et dans les actions « Reprendre ».
        _a params ["_dock"];
        if ((["phone"] call comspec_atak_native_fnc_dockClient) isEqualTo "") exitWith { playSound "ClickSoft"; };
        private _uid = getPlayerUID player;
        private _slots = _dock getVariable ["COMSPEC_ATAK_DockSlots", []];
        // Résumé d'abord (seul lisible dans le bandeau d'une ligne) : emplacements libres et vitesse ; le détail suit (centre).
        private _lines = ["Socle de recharge CS-10"];
        if ((count _slots) > 0) then { _lines pushBack format ["Libre : %1/%2", { (count _x) == 0 } count _slots, count _slots]; };
        _lines pushBack format ["%1 %% par minute", missionNamespace getVariable ["comspec_atak_native_dock_rate", 1.5]];
        {
            if ((count _x) >= 4) then {
                private _n = format ["%1", _forEachIndex + 1];
                if (_forEachIndex < 9) then { _n = "0" + _n; };
                _x params ["_owner", "_name", "_lv"];
                private _who = [_name, "vous"] select (_owner isEqualTo _uid);
                _lines pushBack format ["%1 · %2 · %3 %%%4", _n, _who, _lv, [" · en charge", " · chargé"] select (_lv >= 100)];
            };
        } forEach _slots;
        if ((count _slots) == 0) then { _lines pushBack "Socle en démarrage…"; };
        ["INFO", _lines joinString " · ", 8, 20] call comspec_atak_native_fnc_notify;
    };
    case "deploy": {
        if !("COMSPEC_ATAK_DockKit" in (items player)) exitWith { ["Pas de socle dans l'inventaire", false] call _say; };
        if (missionNamespace getVariable ["COMSPEC_ATAK_DockBusy", false]) exitWith {};
        // Devant soi, à 0,9 m : sur la première surface trouvée (table, caisse, capot) ou au sol.
        private _dir = getDir player;
        private _top = (eyePos player) vectorAdd [0.9 * sin _dir, 0.9 * cos _dir, 0.3];
        private _hit = lineIntersectsSurfaces [_top, _top vectorAdd [0, 0, -3], player, objNull, true, 1, "GEOM", "FIRE"];
        private _pos = if ((count _hit) > 0) then { (_hit select 0) select 0 } else { AGLToASL ((getPosATL player) vectorAdd [0.9 * sin _dir, 0.9 * cos _dir, 0]) };
        _pos set [2, (_pos select 2) + 0.002];
        missionNamespace setVariable ["COMSPEC_ATAK_DockBusy", true];
        player removeItem "COMSPEC_ATAK_DockKit";
        player playActionNow "PutDown";
        // L'avant du socle (voyants, écrans) face au joueur.
        ["comspec_atak_native_dockDeploy", [player, _pos, (_dir + 180) % 360]] call CBA_fnc_serverEvent;
        [{ missionNamespace setVariable ["COMSPEC_ATAK_DockBusy", false]; }, [], 5] call CBA_fnc_waitAndExecute;
    };
    case "pack": {
        _a params ["_dock"];
        if (missionNamespace getVariable ["COMSPEC_ATAK_DockBusy", false]) exitWith {};
        missionNamespace setVariable ["COMSPEC_ATAK_DockBusy", true];
        ["comspec_atak_native_dockPack", [_dock, player]] call CBA_fnc_serverEvent;
        [{ missionNamespace setVariable ["COMSPEC_ATAK_DockBusy", false]; }, [], 5] call CBA_fnc_waitAndExecute;
    };
    case "result": {
        _a params ["_what", "_ok", "_msg", ["_data", []]];
        missionNamespace setVariable ["COMSPEC_ATAK_DockBusy", false];
        [_msg, _ok] call _say;
        switch (_what) do {
            case "put": {
                // Refusé : le téléphone revient (au sol si l'inventaire est plein).
                if (!_ok && {(count _data) > 0}) then { [_data select 0] call _give; };
            };
            case "take": {
                if (!_ok) exitWith {};
                _data params ["_item", "_lv", ["_dev", []], ["_mine", true]];
                [_item] call _give;
                missionNamespace setVariable ["COMSPEC_ATAK_Battery", _lv];
                missionNamespace setVariable ["COMSPEC_ATAK_BatteryTick", diag_tickTime];
                if (_dev isEqualType [] && {(count _dev) > 0}) then { missionNamespace setVariable ["COMSPEC_ATAK_Device", createHashMapFromArray _dev]; };
                playSound "ClickSoft";
            };
            case "deploy": { if (!_ok) then { ["COMSPEC_ATAK_DockKit"] call _give; }; };
            case "pack": { if (_ok) then { ["COMSPEC_ATAK_DockKit"] call _give; player playActionNow "PutDown"; }; };
        };
    };
    default {};
};
