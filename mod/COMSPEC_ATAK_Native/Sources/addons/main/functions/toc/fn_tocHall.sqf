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
        sceneSave [id, nom]  sceneApply [id, nom]  sceneDel [id, nom]  spawn [posATL, dir, taille, nom, camp]
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
                _args params [["_pos", [0, 0, 0]], ["_dir", 0], ["_size", "M"], ["_nm", "SALLE TOC"], ["_sd", "WEST"], ["_id", ""]];
                ["spawnRoom", [_pos, _dir, _size, _nm, _sd, _id]] call _fn
            };
        };
        true
    };
    case "spawnRoom": {
        // Salle complète posée d'un coup (module « Salle TOC complète ») : mur d'images, grands écrans, écrans suspendus,
        // bandeau horloge, plaques de nom, bureaux, chaises et PC du jeu de base ; tout lié à la salle. Repère : le module,
        // le mur devant lui (+Y), écrans tournés vers lui.
        _a params ["_pos", "_dir", "_size", "_nm", "_sd", ["_id", ""]];
        if (!isServer) exitWith { "" };
        if (_id isEqualTo "") then { _id = format ["H%1%2", floor (serverTime * 10), floor random 10000]; };
        ["do", ["create", [_nm, _sd, objNull, _id]]] call _fn;
        private _pick = { params ["_cands"]; (_cands select { isClass (configFile >> "CfgVehicles" >> _x) }) param [0, ""] };
        private _desk = [["Land_TableDesk_F", "Land_CampingTable_F", "Land_WoodenTable_large_F"]] call _pick;
        private _chair = [["Land_OfficeChair_01_F", "Land_CampingChair_V2_F", "Land_ChairWood_F"]] call _pick;
        private _at = {
            // Point du repère de la salle (x à droite, y vers le mur, z en hauteur) vers le monde.
            params ["_x0", "_y0", ["_z0", 0]];
            private _p = _pos getPos [sqrt (_x0 ^ 2 + _y0 ^ 2), _dir + (_x0 atan2 _y0)];
            [_p select 0, _p select 1, (_pos select 2) + _z0]
        };
        private _sqs = ["squads", _sd] call comspec_atak_native_fnc_tocScreen;
        private _scr = [];
        private _props = [];
        private _put = {
            params ["_cls", "_x0", "_y0", "_z0", "_view", ["_sq", ""], ["_pans", ""], ["_name", ""]];
            if !(isClass (configFile >> "CfgVehicles" >> _cls)) exitWith { objNull };
            private _o = ["spawn", [[_x0, _y0, _z0] call _at, _dir + 180, _cls, [_name, _nm] select (_name isEqualTo ""), _sd, _view, false, 0, _sq, _pans]] call comspec_atak_native_fnc_tocScreen;
            if (!isNull _o) then { _o setPosATL ([_x0, _y0, _z0] call _at); _o setVariable ["COMSPEC_ATAK_TocHall", _id, true]; _scr pushBack _o; };
            _o
        };
        private _prop = {
            params ["_cls", "_x0", "_y0", "_z0", "_d", ["_pc", false]];
            if (_cls isEqualTo "" || {!isClass (configFile >> "CfgVehicles" >> _cls)}) exitWith { objNull };
            private _o = createVehicle [_cls, [_x0, _y0, _z0] call _at, [], 0, "CAN_COLLIDE"];
            _o setDir (_dir + _d);
            _o setPosATL ([_x0, _y0, _z0] call _at);
            if (_pc) then { _o setVariable ["COMSPEC_ATAK_TocHall", _id, true]; _o setVariable ["COMSPEC_ATAK_TocSide", _sd, true]; };
            { _x addCuratorEditableObjects [[_o], false]; } forEach allCurators;
            _props pushBack [_cls, getPosASL _o, vectorDir _o, vectorUp _o, _pc];
            _o
        };
        private _big = _size isNotEqualTo "S";
        // Mur central : 3x2 (6 dalles) en moyenne et grande taille, 2x2 (4 dalles) en petite.
        if (_big) then {
            ["COMSPEC_ATAK_Screen_Wall3x2", 0, 7, 0, "SPLIT", "", "SAT1, SAT2, CLOCK, LOG, REQ", "MUR"] call _put;
            ["COMSPEC_ATAK_Screen_98", -3.0, 6.9, 0, "MOSAIC", "", "", "CAMÉRAS"] call _put;
            ["COMSPEC_ATAK_Screen_98", 3.0, 6.9, 0, "SQUAD", _sqs param [0, ""], "", _sqs param [0, "ESCOUADE"]] call _put;
            ["COMSPEC_ATAK_Screen_ClockStrip", 0, 6.95, 0.05, "STRIP", "", "", "HORLOGE"] call _put;
        } else {
            ["COMSPEC_ATAK_Screen_Wall2x2_Quad", 0, 6, 0, "SPLIT", "", "SAT1, CLOCK, LOG", "MUR"] call _put;
            ["COMSPEC_ATAK_Screen_ClockStrip", 0, 5.95, 0.05, "STRIP", "", "", "HORLOGE"] call _put;
        };
        // Plaques de nom des éléments, au-dessus des places des opérateurs.
        private _nPl = [2, 4] select _big;
        for "_i" from 0 to (_nPl - 1) do {
            ["COMSPEC_ATAK_Screen_NamePlate", -((_nPl - 1) * 1.6) / 2 + _i * 1.6, 4.2, 0, "PLATE", _sqs param [_i, ""], "", format ["ÉLÉMENT %1", _i + 1]] call _put;
        };
        if (_size isEqualTo "L") then {
            ["COMSPEC_ATAK_Screen_65_Ceiling", -2.4, 4.6, 3.2, "EXCHECK", "", "", "EXÉCUTION"] call _put;
            ["COMSPEC_ATAK_Screen_65_Ceiling", 2.4, 4.6, 3.2, "KILLS", "", "", "PERTES"] call _put;
            ["COMSPEC_ATAK_Screen_98_Stand", -5.6, 5.2, 0, "SAT", "", "", "SAT 2"] call _put;
            ["COMSPEC_ATAK_Screen_98_Stand", 5.6, 5.2, 0, "REQ", "", "", "DEMANDES"] call _put;
        };
        if (_size isEqualTo "M") then {
            ["COMSPEC_ATAK_Screen_65_Ceiling", 0, 4.6, 3.2, "EXCHECK", "", "", "EXÉCUTION"] call _put;
        };
        // Postes : un bureau, une chaise, un PC de TOC par place ; rangée face au mur.
        private _nPc = (createHashMapFromArray [["S", 2], ["M", 3], ["L", 4]]) getOrDefault [_size, 3];
        for "_i" from 0 to (_nPc - 1) do {
            private _x0 = -((_nPc - 1) * 1.7) / 2 + _i * 1.7;
            [_desk, _x0, 2.6, 0, 0] call _prop;
            [_chair, _x0, 1.8, 0, 180] call _prop;
            ["COMSPEC_ATAK_TocLaptop", _x0, 2.55, 0.8, 180, true] call _prop;
        };
        private _i = (call _all) findIf { (_x select 0) isEqualTo _id };
        if (_i >= 0) then {
            private _l = +(call _all);
            (_l select _i) set [4, _props];
            [_l] call _publish;
        };
        ["INFO", "TOC", format ["Salle TOC complète « %1 » posée (%2) : %3 écran(s), %4 objet(s)", _nm, _size, count _scr, count _props]] call comspec_atak_native_fnc_log;
        _id
    };
    default { false };
};
