/*
    Postes PC fixes de TOC (demande de TTRD, 11/11 : « des kits PC fixe complets : clavier, souris, poste fixe + écran,
    double écran, écran incurvé ») : unité centrale, moniteur(s), clavier et souris en un seul objet (toc_pc.hpp,
    tools/model_toc_pc.py). Un poste est reconnu à sa config, jamais à son nom : comspec_tocPost=1.
      - poste TOC : même action « PC du poste TOC » que le PC portable de TOC (fn_tocPost lit comspec_tocPost), mêmes
        écrans du PC (fn_tocLaptop), enregistrable dans une salle (fn_tocHall) ;
      - ses moniteurs sont de vrais écrans TOC : le poste se désigne lui-même à la pose (Eden, Zeus, module, salle
        complète), moniteur principal en PIP du poste (comspec_tocPostView), un contenu par moniteur
        (comspec_screenPanels, comspec_screenPanelViews) ; réglable ensuite comme tout écran (PC, ACE, app Poste TOC) ;
      - poste opérateur (module « Poste opérateur », « station ») : bureau, chaise et téléphone de table du jeu de base,
        le poste posé dessus, prêt à servir. Un poste gardé d'une partie à l'autre (fn_tocPersist) qui se retrouve en
        l'air (son bureau n'est pas gardé) reçoit un bureau et une chaise à la reprise.
    Modes :
      ["classes"]                     classes des postes PC présentes (comspec_tocPost=1), gardées en cache
      ["types"]                       [[nom de l'objet, classe]...] pour les listes des modules
      ["isPost", objet]               true si l'objet est un poste PC
      ["initServer"]                  serveur (fn_tocHall « init ») : désignation des postes à leur création
      ["station", [posATL, direction du regard de l'opérateur, classe, camp, nom]]
                                      serveur (ailleurs : demande au serveur) : pose un poste opérateur complet
      ["furnish", [poste, direction du regard]]   serveur : bureau, chaise et téléphone sous un poste
*/
params [["_mode", "classes"], ["_a", []]];
private _fn = comspec_atak_native_fnc_tocPc;
private _classes = {
    private _c = missionNamespace getVariable "COMSPEC_ATAK_TocPcClasses";
    if (isNil "_c") then {
        _c = ("(getNumber (_x >> 'comspec_tocPost')) > 0 && {(getNumber (_x >> 'scope')) > 0}" configClasses (configFile >> "CfgVehicles")) apply { configName _x };
        missionNamespace setVariable ["COMSPEC_ATAK_TocPcClasses", _c];
    };
    _c
};
// Hauteur du plateau du bureau du jeu choisi (le poste a son origine sur le plateau).
private _deskOf = {
    private _cands = [["Land_TableDesk_F", 0.8], ["Land_CampingTable_F", 0.81], ["Land_WoodenTable_large_F", 0.85]];
    _cands param [_cands findIf { isClass (configFile >> "CfgVehicles" >> (_x select 0)) }, ["", 0.8]]
};
switch (_mode) do {
    case "classes": { call _classes };
    case "types": { (call _classes) apply { [getText (configFile >> "CfgVehicles" >> _x >> "displayName"), _x] } };
    case "isPost": { _a isEqualType objNull && {!isNull _a} && {(getNumber (configOf _a >> "comspec_tocPost")) > 0} };
    case "initServer": {
        if (!isServer || {missionNamespace getVariable ["COMSPEC_ATAK_TocPcInit", false]}) exitWith { false };
        missionNamespace setVariable ["COMSPEC_ATAK_TocPcInit", true];
        ["comspec_atak_native_tocPc", { _this call comspec_atak_native_fnc_tocPc; }] call CBA_fnc_addEventHandler;
        {
            [_x, "init", {
                params ["_o"];
                // Après la reprise de la partie précédente (3 s) et les attributs Eden : un poste déjà réglé n'est pas touché.
                [{
                    params ["_o"];
                    if (isNull _o || {!alive _o}) exitWith {};
                    if ((count (_o getVariable ["COMSPEC_ATAK_TocScr", []])) < 8) then {
                        private _cfg = configOf _o;
                        private _view = getText (_cfg >> "comspec_tocPostView");
                        ["add", [_o, (getText (_cfg >> "displayName")) select [0, 32], _o getVariable ["COMSPEC_ATAK_TocSide", ""], 0, [_view, "TOC"] select (_view isEqualTo ""), true, false]] call comspec_atak_native_fnc_tocScreen;
                    };
                    // Poste repris d'une partie précédente sans son bureau (seul le poste est gardé) : en l'air, rien dessous.
                    private _p = getPosASL _o;
                    if (time < 60 && {((getPosATL _o) select 2) > 0.5} && {(lineIntersectsSurfaces [_p vectorAdd [0, 0, 0.05], _p vectorAdd [0, 0, -0.35], _o, objNull, true, 1, "GEOM", "NONE"]) isEqualTo []}) then {
                        ["furnish", [_o, (getDir _o) + 180]] call comspec_atak_native_fnc_tocPc;
                    };
                }, [_o], 5] call comspec_atak_native_fnc_waitAndExec;
            }, false, [], true] call CBA_fnc_addClassEventHandler;
        } forEach (call _classes);
        true
    };
    case "furnish": {
        _a params [["_pc", objNull], ["_dir", 0], ["_phone", false]];
        if (!isServer || {isNull _pc}) exitWith { [] };
        (call _deskOf) params ["_desk", "_top"];
        private _pick = { params ["_cands"]; (_cands select { isClass (configFile >> "CfgVehicles" >> _x) }) param [0, ""] };
        private _chair = [["Land_OfficeChair_01_F", "Land_CampingChair_V2_F", "Land_ChairWood_F"]] call _pick;
        private _pos = getPosATL _pc;
        private _base = [_pos select 0, _pos select 1, ((_pos select 2) - _top) max 0];
        private _out = [];
        private _put = {
            params ["_cls", "_fwd", "_left", "_up", "_d"];
            if (_cls isEqualTo "") exitWith { objNull };
            // Repère de l'opérateur : devant lui (_fwd), à sa gauche (_left).
            private _p = _base getPos [_fwd, _dir];
            _p = _p getPos [_left, _dir - 90];
            private _o = createVehicle [_cls, [_p select 0, _p select 1, (_base select 2) + _up], [], 0, "CAN_COLLIDE"];
            _o setDir (_dir + _d);
            _o setPosATL [_p select 0, _p select 1, (_base select 2) + _up];
            { _x addCuratorEditableObjects [[_o], false]; } forEach allCurators;
            _out pushBack _o;
            _o
        };
        [_desk, 0, 0, 0, 0] call _put;
        [_chair, -0.85, 0, 0, 180] call _put;
        if (_phone) then { [[["Land_SatellitePhone_F", "Land_PortableLongRangeRadio_F", "Land_FMradio_F"]] call _pick, -0.2, 0.62, _top, 160] call _put; };
        _out
    };
    case "station": {
        _a params [["_pos", [0, 0, 0]], ["_dir", 0], ["_cls", ""], ["_side", ""], ["_name", ""]];
        if (!isServer) exitWith { ["comspec_atak_native_tocPc", ["station", _a]] call CBA_fnc_serverEvent; objNull };
        private _all = call _classes;
        if !(_cls in _all) then { _cls = _all param [0, ""]; };
        if (_cls isEqualTo "") exitWith {
            ["WARNING", "TOC", "Poste opérateur : aucun poste PC disponible sur ce serveur"] call comspec_atak_native_fnc_log;
            objNull
        };
        (call _deskOf) params ["", "_top"];
        private _at = [_pos select 0, _pos select 1, (_pos param [2, 0]) + _top];
        private _pc = createVehicle [_cls, _at, [], 0, "CAN_COLLIDE"];
        // Les moniteurs font face à l'opérateur : le poste est tourné à l'opposé de son regard.
        _pc setDir (_dir + 180);
        _pc setPosATL _at;
        if (_side isNotEqualTo "") then { _pc setVariable ["COMSPEC_ATAK_TocSide", toUpper _side, true]; };
        { _x addCuratorEditableObjects [[_pc], false]; } forEach allCurators;
        ["furnish", [_pc, _dir, true]] call _fn;
        // Désigné tout de suite (sans attendre l'événement de création) : nom donné, moniteur principal en PIP du poste.
        private _view = getText (configOf _pc >> "comspec_tocPostView");
        ["add", [_pc, ([_name, getText (configOf _pc >> "displayName")] select (_name isEqualTo "")) select [0, 32], _side, 0, [_view, "TOC"] select (_view isEqualTo ""), true, false]] call comspec_atak_native_fnc_tocScreen;
        ["INFO", "TOC", format ["Poste opérateur posé : %1 (%2)", _cls, getText (configOf _pc >> "displayName")]] call comspec_atak_native_fnc_log;
        _pc
    };
    default { false };
};
