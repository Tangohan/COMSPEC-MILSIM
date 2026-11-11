/*
    Salles TOC (demande de TTRD, 11/11 : « un vrai espace TOC et un PC où on peut link tous les écrans ») : un poste
    (PC de TOC) possède une salle, la salle possède ses écrans liés. Plusieurs salles par camp, chacune avec son nom ;
    un écran appartient à une seule salle. Depuis le PC maître (fn_tocLaptop, onglet SALLE) : plan du mur, contenu,
    nom, allumage et identification de chaque écran ou dalle, scènes enregistrées, tout éteindre / tout allumer.
    Tenu par le serveur ; sauvegardé avec le reste (fn_tocPersist).
    Variables publiques :
      missionNamespace COMSPEC_ATAK_TocHalls = [[id, nom, camp, scènes, mobilier]...]
        scène : [nom, [[uid de l'écran, dalle, affichage, satellite, escouade, mosaïque, éteint]...]]
        mobilier : objets posés par la salle complète [[classe, posASL, dir, haut, PC]...] (recréés à la reprise)
      objet (PC ou écran hôte) COMSPEC_ATAK_TocHall = id de sa salle ; écran COMSPEC_ATAK_TocUid (identifiant stable),
      COMSPEC_ATAK_TocOff (éteint)
    Modes :
      ["init"]                                   serveur (fn_tocScreen initServer) ; ["initClient"] client (clignotement)
      ["halls", camp]                            salles du camp           ["get", id]   salle ou []
      ["of", objet]                              id de la salle d'un PC ou d'un écran (relais : celle de son hôte)
      ["screens", id]                            écrans hôtes liés à la salle
      ["uid", écran]                             identifiant stable (posé par le serveur)
      ["do", [action, args]]                     client -> serveur ; serveur : actions
        create [nom, camp, PC, id imposé]  link [id, écran]  unlink écran  linkNear [id, [x, y, z], rayon]
        rename [id, nom]  power [écran, allumé]  powerAll [id, allumé]  blink écran
        sceneSave [id, nom]  sceneApply [id, nom]  sceneDel [id, nom]
        spawn [posATL, dir, taille, nom, camp, id, classe des postes, demandeur, aligner]  roomRemove id (fn_tocHallBuild)
      ["dump"] / ["load", données] / ["saved", id] serveur (fn_tocPersist, module « Salle TOC complète »)
*/
params [["_mode", "halls"], ["_a", []]];
private _fn = comspec_atak_native_fnc_tocHall;
private _all = { (missionNamespace getVariable ["COMSPEC_ATAK_TocHalls", []]) select { _x isEqualType [] && {(count _x) >= 5} } };
private _publish = {
    params ["_list"];
    missionNamespace setVariable ["COMSPEC_ATAK_TocHalls", _list, true];
    ["save"] call comspec_atak_native_fnc_tocPersist;
};
private _host = { params ["_o"]; private _h = _o getVariable ["COMSPEC_ATAK_TocHost", objNull]; [_o, _h] select (!isNull _h) };
switch (_mode) do {
    case "halls": { (call _all) select { (_x select 2) in ["", _a] } };
    case "get": { private _l = (call _all) select { (_x select 0) isEqualTo _a }; _l param [0, []] };
    case "of": { ([_a] call _host) getVariable ["COMSPEC_ATAK_TocHall", ""] };
    case "uid": { ([_a] call _host) getVariable ["COMSPEC_ATAK_TocUid", ""] };
    case "screens": {
        (missionNamespace getVariable ["COMSPEC_ATAK_TocScreens", []]) select {
            !isNull _x && {isNull (_x getVariable ["COMSPEC_ATAK_TocHost", objNull])} && {(_x getVariable ["COMSPEC_ATAK_TocHall", ""]) isEqualTo _a}
        }
    };
    case "initClient": {
        if (!hasInterface) exitWith {};
        // Salle complète posée par le serveur à la demande de ce Zeus : résultat dans sa ligne de retour.
        ["comspec_atak_native_tocHallDone", { ["done", _this] call comspec_atak_native_fnc_tocHallBuild; }] call CBA_fnc_addEventHandler;
        // Identifier un écran : il clignote en ambre 2 s chez tous les joueurs proches, puis reprend son image.
        ["comspec_atak_native_tocBlink", {
            params [["_o", objNull]];
            if (isNull _o || {(player distance _o) > 200}) exitWith {};
            _o setVariable ["COMSPEC_ATAK_TocBlinkEnd", diag_tickTime + 2];
            [{
                params ["_args", "_h"];
                _args params ["_o"];
                (_o getVariable ["COMSPEC_ATAK_TocPainted", []]) params [["_sel", 0], ["_tex", ""]];
                private _end = _o getVariable ["COMSPEC_ATAK_TocBlinkEnd", 0];
                if (isNull _o || {diag_tickTime > _end}) exitWith {
                    [_h] call CBA_fnc_removePerFrameHandler;
                    if (!isNull _o) then { ["paint", [_o, _sel, _tex]] call comspec_atak_native_fnc_tocScreen; };
                };
                private _on = ((floor (diag_tickTime * 4)) mod 2) isEqualTo 0;
                private _sv = _o getVariable ["COMSPEC_ATAK_TocPainted", []];
                ["paint", [_o, _sel, ["#(argb,8,8,3)color(0.06,0.06,0.06,1)", "#(argb,8,8,3)color(1,0.62,0.1,1)"] select _on]] call comspec_atak_native_fnc_tocScreen;
                // « paint » note la dernière image : on remet celle d'avant le clignotement.
                _o setVariable ["COMSPEC_ATAK_TocPainted", _sv];
            }, 0.12, [_o]] call CBA_fnc_addPerFrameHandler;
        }] call CBA_fnc_addEventHandler;
    };
    case "init": {
        if (!isServer || {missionNamespace getVariable ["COMSPEC_ATAK_TocHallInit", false]}) exitWith {};
        missionNamespace setVariable ["COMSPEC_ATAK_TocHallInit", true];
        ["comspec_atak_native_tocHall", { ["do", _this] call comspec_atak_native_fnc_tocHall; }] call CBA_fnc_addEventHandler;
        // Postes PC fixes (fn_tocPc) : désignés comme écrans TOC à leur création.
        ["initServer"] call comspec_atak_native_fnc_tocPc;
    };
    case "dump": { +(call _all) };
    case "saved": {
        private _d = profileNamespace getVariable [format ["COMSPEC_ATAK_TocSave_%1_%2", worldName, missionName], []];
        (missionNamespace getVariable ["comspec_atak_native_toc_persist", true]) && {_d isEqualType []} && {((_d param [5, []]) findIf { (_x param [0, ""]) isEqualTo _a }) >= 0}
    };
    case "load": {
        if (!isServer || {!(_a isEqualType [])}) exitWith {};
        private _l = call _all;
        {
            if !(_x isEqualType [] && {(count _x) >= 5}) then { continue };
            _x params ["_id", "_nm", "_sd", "_sc", "_props"];
            private _i = _l findIf { (_x select 0) isEqualTo _id };
            if (_i >= 0) then { (_l select _i) set [3, _sc]; } else {
                // Mobilier et PC de la salle complète : recréés à leur place (retrouvés s'ils y sont déjà).
                private _np = [];
                {
                    _x params ["_cls", "_pos", "_vd", "_vu", ["_pc", false]];
                    if !(isClass (configFile >> "CfgVehicles" >> _cls)) then { continue };
                    private _o = ((nearestObjects [ASLToAGL _pos, [_cls], 1]) select { alive _x }) param [0, objNull];
                    if (isNull _o) then {
                        _o = createVehicle [_cls, ASLToAGL _pos, [], 0, "CAN_COLLIDE"];
                        _o setPosASL _pos; _o setVectorDirAndUp [_vd, _vu];
                        { _x addCuratorEditableObjects [[_o], false]; } forEach allCurators;
                    };
                    if (_pc) then { _o setVariable ["COMSPEC_ATAK_TocHall", _id, true]; _o setVariable ["COMSPEC_ATAK_TocSide", _sd, true]; };
                    _np pushBack [_cls, _pos, _vd, _vu, _pc];
                } forEach _props;
                _l pushBack [_id, _nm, _sd, _sc, _np];
            };
        } forEach _a;
        missionNamespace setVariable ["COMSPEC_ATAK_TocHalls", _l, true];
    };
    case "do": {
        _a params [["_act", ""], ["_args", []]];
        if (!isServer) exitWith { ["comspec_atak_native_tocHall", _a] call CBA_fnc_serverEvent; true };
        private _l = +(call _all);
        private _find = { params ["_id"]; _l findIf { (_x select 0) isEqualTo _id } };
        switch (_act) do {
            case "create": {
                _args params [["_nm", "SALLE TOC"], ["_sd", ""], ["_pc", objNull], ["_id", ""]];
                if (_id isEqualTo "") then { _id = format ["H%1%2", floor (serverTime * 10), floor random 10000]; };
                if (([_id] call _find) < 0) then { _l pushBack [_id, (toUpper _nm) select [0, 32], toUpper _sd, [], []]; };
                if (!isNull _pc) then { _pc setVariable ["COMSPEC_ATAK_TocHall", _id, true]; };
                [_l] call _publish;
                _id
            };
            case "rename": {
                _args params ["_id", "_nm"];
                private _i = [_id] call _find;
                if (_i < 0) exitWith {};
                (_l select _i) set [1, (toUpper _nm) select [0, 32]];
                [_l] call _publish;
            };
            case "link": {
                _args params ["_id", "_o"];
                _o = [_o] call _host;
                if (isNull _o || {([_id] call _find) < 0}) exitWith {};
                _o setVariable ["COMSPEC_ATAK_TocHall", _id, true];
                ["save"] call comspec_atak_native_fnc_tocPersist;
            };
            case "unlink": {
                private _o = [_args] call _host;
                if (isNull _o) exitWith {};
                _o setVariable ["COMSPEC_ATAK_TocHall", "", true];
                ["save"] call comspec_atak_native_fnc_tocPersist;
            };
            case "linkNear": {
                _args params ["_id", "_c", ["_r", 25]];
                private _i = [_id] call _find;
                if (_i < 0) exitWith {};
                private _sd = (_l select _i) select 2;
                {
                    if (isNull (_x getVariable ["COMSPEC_ATAK_TocHost", objNull]) && {(_x distance _c) < _r}
                        && {(((_x getVariable ["COMSPEC_ATAK_TocScr", []]) param [1, ""]) in ["", _sd]) || {_sd isEqualTo ""}}) then {
                        _x setVariable ["COMSPEC_ATAK_TocHall", _id, true];
                    };
                } forEach (missionNamespace getVariable ["COMSPEC_ATAK_TocScreens", []]);
                ["save"] call comspec_atak_native_fnc_tocPersist;
            };
            case "power": {
                _args params ["_o", ["_on", true]];
                if (isNull _o) exitWith {};
                _o setVariable ["COMSPEC_ATAK_TocOff", !_on, true];
                ["save"] call comspec_atak_native_fnc_tocPersist;
            };
            case "powerAll": {
                _args params ["_id", ["_on", true]];
                {
                    _x setVariable ["COMSPEC_ATAK_TocOff", !_on, true];
                    { if (!isNull _x) then { _x setVariable ["COMSPEC_ATAK_TocOff", !_on, true]; }; } forEach (_x getVariable ["COMSPEC_ATAK_TocPanels", []]);
                } forEach (["screens", _id] call _fn);
                ["save"] call comspec_atak_native_fnc_tocPersist;
            };
            case "blink": { if (!isNull _args) then { ["comspec_atak_native_tocBlink", [_args]] call CBA_fnc_globalEvent; }; };
            case "sceneSave": {
                _args params ["_id", "_nm"];
                private _i = [_id] call _find;
                if (_i < 0 || {_nm isEqualTo ""}) exitWith {};
                private _items = [];
                {
                    private _h = _x;
                    private _uid = _h getVariable ["COMSPEC_ATAK_TocUid", ""];
                    {
                        private _c = _x getVariable ["COMSPEC_ATAK_TocScr", []];
                        _items pushBack [_uid, _x getVariable ["COMSPEC_ATAK_TocPanel", 0], _c param [3, "SPLIT"], _c param [8, 0], _c param [9, ""],
                            _x getVariable ["COMSPEC_ATAK_TocMosaic", []], _x getVariable ["COMSPEC_ATAK_TocOff", false]];
                    } forEach ([_h] + ((_h getVariable ["COMSPEC_ATAK_TocPanels", []]) select { !isNull _x }));
                } forEach (["screens", _id] call _fn);
                private _sc = ((_l select _i) select 3) select { (_x select 0) isNotEqualTo (toUpper _nm) };
                _sc pushBack [(toUpper _nm) select [0, 24], _items];
                (_l select _i) set [3, _sc];
                [_l] call _publish;
            };
            case "sceneDel": {
                _args params ["_id", "_nm"];
                private _i = [_id] call _find;
                if (_i < 0) exitWith {};
                (_l select _i) set [3, ((_l select _i) select 3) select { (_x select 0) isNotEqualTo _nm }];
                [_l] call _publish;
            };
            case "sceneApply": {
                _args params ["_id", "_nm"];
                private _i = [_id] call _find;
                if (_i < 0) exitWith {};
                private _sc = (((_l select _i) select 3) select { (_x select 0) isEqualTo _nm }) param [0, []];
                private _scr = ["screens", _id] call _fn;
                {
                    _x params ["_uid", "_k", "_v", "_sl", "_sq", "_mos", "_off"];
                    private _h = (_scr select { (_x getVariable ["COMSPEC_ATAK_TocUid", ""]) isEqualTo _uid }) param [0, objNull];
                    private _o = if (isNull _h) then { objNull } else { ["panelOf", [_h, _k]] call comspec_atak_native_fnc_tocScreen };
                    if (!isNull _o) then {
                        ["view", [_o, "sat", _sl]] call comspec_atak_native_fnc_tocScreen;
                        ["view", [_o, "squad", _sq]] call comspec_atak_native_fnc_tocScreen;
                        if ((count _mos) >= 5) then { ["view", [_o, "mosaic", _mos]] call comspec_atak_native_fnc_tocScreen; };
                        ["view", [_o, "mode", _v]] call comspec_atak_native_fnc_tocScreen;
                        _o setVariable ["COMSPEC_ATAK_TocOff", _off, true];
                    };
                } forEach (_sc param [1, []]);
            };
            case "spawn": {
                // Demande du Zeus (fn_tocHallBuild « zeus ») : même constructeur que le module Eden. _by : joueur à qui
                // le résultat est rendu (ligne de retour du Zeus) ; _snap : alignement sur les murs du bâtiment.
                _args params [["_pos", [0, 0, 0]], ["_dir", 0], ["_size", "M"], ["_nm", "SALLE TOC"], ["_sd", "WEST"], ["_id", ""], ["_post", ""], ["_by", objNull], ["_snap", false]];
                private _res = ["build", [_pos, _dir, _size, _nm, _sd, _id, _post, _snap, false]] call comspec_atak_native_fnc_tocHallBuild;
                if (_by isEqualType objNull && {!isNull _by}) then { ["comspec_atak_native_tocHallDone", [_res], _by] call CBA_fnc_targetEvent; };
            };
            // Salle complète retirée (module supprimé, fn_tocHallBuild « remove ») : écrans, mobilier et PC posés par elle.
            case "roomRemove": { ["remove", _args] call comspec_atak_native_fnc_tocHallBuild; };
        };
        true
    };
    case "spawnRoom": {
        // Salle complète posée d'un coup : table des objets, sol, mur et plafond cherchés, un pas par objet
        // (fn_tocHallBuild « build », seul constructeur pour Eden, Zeus et le repli). Rend l'identifiant de la salle.
        _a params ["_pos", "_dir", "_size", "_nm", "_sd", ["_id", ""], ["_post", ""]];
        (["build", [_pos, _dir, _size, _nm, _sd, _id, _post, false, false]] call comspec_atak_native_fnc_tocHallBuild) param [0, ""]
    };
    default { false };
};
