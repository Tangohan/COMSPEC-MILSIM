/*
    Postes PC fixes de TOC (demande de TTRD, 11/11 : « des kits PC fixe complets : clavier, souris, poste fixe + écran,
    double écran, écran incurvé ») : unité centrale, moniteur(s), clavier et souris en un seul objet (toc_pc.hpp,
    tools/model_toc_pc.py). Un poste est reconnu à sa config, jamais à son nom : comspec_tocPost=1.
      - poste TOC : même action « PC du poste TOC » que le PC portable de TOC (fn_tocPost lit comspec_tocPost), mêmes
        écrans du PC (fn_tocLaptop), enregistrable dans une salle (fn_tocHall) ;
      - ses moniteurs sont de vrais écrans TOC : le poste se désigne lui-même à la pose (Eden, Zeus, module, salle
        complète), moniteur principal en PIP du poste (comspec_tocPostView), un contenu par moniteur
        (comspec_screenPanels, comspec_screenPanelViews) ; réglable ensuite comme tout écran (PC, ACE, app Poste TOC) ;
      - poste opérateur (module « Poste opérateur », « station ») : console et fauteuil du mod (tools/
        model_toc_furniture.py ; console voulue par le poste : comspec_tocDesk ; à défaut le bureau et la chaise du jeu de
        base), combiné radio et casque, le poste posé dessus, prêt à servir. Orientation : la direction donnée est celle
        du REGARD de l'opérateur (celle du module) ; les caps viennent de fn_tocHallBuild « facing », la même règle que la
        salle complète : poste et console tournés vers l'opérateur (cap + 180), fauteuil dans le sens du regard. Un poste gardé d'une partie à l'autre (fn_tocPersist) qui se retrouve en
        l'air (son bureau n'est pas gardé) reçoit un bureau et une chaise à la reprise.
    Modes :
      ["classes"]                     classes des postes PC présentes (comspec_tocPost=1), gardées en cache
      ["types"]                       [[nom de l'objet, classe]...] pour les listes des modules
      ["isPost", objet]               true si l'objet est un poste PC
      ["initServer"]                  serveur (fn_tocHall « init ») : désignation des postes à leur création
      ["station", [posATL, direction du regard de l'opérateur, classe, camp, nom]]
                                      serveur (ailleurs : demande au serveur) : pose un poste opérateur complet
      ["furnish", [poste, direction du regard, accessoires]]   serveur : console et fauteuil sous un poste (posé au sol trouvé)
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
// Bureau d'un poste et hauteur de son plateau (le poste a son origine sur le plateau) : la console voulue par le poste
// (comspec_tocDesk, hauteur comspec_tocDeskTop), sinon la console droite du mod, sinon un bureau du jeu de base.
private _deskOf = {
    params [["_pcCls", ""]];
    private _cfg = configFile >> "CfgVehicles";
    private _want = getText (_cfg >> _pcCls >> "comspec_tocDesk");
    private _cands = [[_want, getNumber (_cfg >> _want >> "comspec_tocDeskTop")], ["COMSPEC_ATAK_TocDesk_Single", 0.74],
        ["Land_TableDesk_F", 0.8], ["Land_CampingTable_F", 0.81], ["Land_WoodenTable_large_F", 0.85]];
    _cands param [_cands findIf { (_x select 0) isNotEqualTo "" && {(_x select 1) > 0} && {isClass (_cfg >> (_x select 0))} }, ["", 0.8]]
};
// Cap d'un objet du poste pour un regard d'opérateur donné : la règle de la salle complète (table _facing).
private _head = { params ["_role", "_cls", "_d"]; ["facing", [_role, _cls, _d]] call comspec_atak_native_fnc_tocHallBuild };
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
        _a params [["_pc", objNull], ["_dir", 0], ["_extras", false]];
        if (!isServer || {isNull _pc}) exitWith { [] };
        ([typeOf _pc] call _deskOf) params ["_desk", "_top"];
        private _pick = { params ["_cands"]; (_cands select { isClass (configFile >> "CfgVehicles" >> _x) }) param [0, ""] };
        private _chair = [["COMSPEC_ATAK_TocChair", "Land_OfficeChair_01_F", "Land_CampingChair_V2_F", "Land_ChairWood_F"]] call _pick;
        // Sol sous le poste (plancher, toit, terrain) : le bureau y est posé et le poste remis à la hauteur du plateau.
        private _asl = getPosASL _pc;
        private _hits = (lineIntersectsSurfaces [_asl vectorAdd [0, 0, 0.05], _asl vectorAdd [0, 0, -1.6], _pc, objNull, true, 4, "GEOM", "FIRE"]) select { !((_x select 2) isKindOf "AllVehicles") };
        private _floor = if (_hits isEqualTo []) then { (_asl select 2) - (((getPosATL _pc) select 2) min _top) } else { ((_hits select 0) select 0) select 2 };
        private _base = [_asl select 0, _asl select 1, _floor];
        _pc setPosASL [_asl select 0, _asl select 1, _floor + _top];
        private _out = [];
        private _put = {
            params ["_role", "_cls", "_fwd", "_left", "_up", "_turn"];
            if (_cls isEqualTo "") exitWith { objNull };
            // Repère de l'opérateur : devant lui (_fwd), à sa gauche (_left).
            private _p = [(_base select 0) + _fwd * (sin _dir) - _left * (cos _dir), (_base select 1) + _fwd * (cos _dir) + _left * (sin _dir), (_base select 2) + _up];
            private _o = createVehicle [_cls, ASLToATL _p, [], 0, "CAN_COLLIDE"];
            private _h = ([_role, _cls, _dir] call _head) + _turn;
            _o setPosASL _p;
            _o setVectorDirAndUp [[sin _h, cos _h, 0], [0, 0, 1]];
            if (_role isNotEqualTo "chair") then { _o enableSimulationGlobal false; };
            { _x addCuratorEditableObjects [[_o], false]; } forEach allCurators;
            _out pushBack _o;
            _o
        };
        ["desk", _desk, 0, 0, 0, 0] call _put;
        ["chair", _chair, -0.8, 0, 0, 0] call _put;
        if (_extras) then {
            // Combiné radio à gauche, casque à droite, hors du tapis de bureau ; sans le mobilier du mod : téléphone du jeu.
            private _own = (_desk select [0, 13]) isEqualTo "COMSPEC_ATAK_";
            if (_own) then {
                ["prop", "COMSPEC_ATAK_TocProp_Handset", -0.2, 0.6, _top, 10] call _put;
                ["prop", "COMSPEC_ATAK_TocProp_Headset", -0.16, -0.62, _top, -20] call _put;
            } else {
                ["prop", [["Land_SatellitePhone_F", "Land_PortableLongRangeRadio_F", "Land_FMradio_F"]] call _pick, -0.2, 0.62, _top, -20] call _put;
            };
        };
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
        ([_cls] call _deskOf) params ["", "_top"];
        private _at = [_pos select 0, _pos select 1, (_pos param [2, 0]) + _top];
        private _pc = createVehicle [_cls, _at, [], 0, "CAN_COLLIDE"];
        // Les moniteurs font face à l'opérateur : le poste est tourné à l'opposé de son regard (table _facing).
        _pc setDir (["pc", _cls, _dir] call _head);
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
