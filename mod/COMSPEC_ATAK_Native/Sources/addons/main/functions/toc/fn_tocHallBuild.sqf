/*
    Pose de la salle TOC complète (module « ATAK · Salle TOC complète », Eden et Zeus) : un seul constructeur, « build »,
    pour Eden (serveur au lancement), Zeus (serveur, à la demande du Zeus) et le repli (poste du Zeus quand le serveur
    est plus ancien ou sans le mod : salle alors non gardée d'une partie à l'autre).
    Retour d'un testeur sur la 4.6.0 : « le module ne pose pas de salle, dans Eden comme dans Zeus : seulement un grand
    écran qui flotte en l'air ». L'ancienne pose (fn_tocHall « spawnRoom ») mettait des écrans muraux et suspendus à la
    hauteur du module, sans mur, sans plafond et sans chercher le sol, en une seule suite d'appels.

    Composition : tables ci-dessous, une par taille. Repère de la salle : origine au sol sous le module, x à droite,
    y vers le mur d'images (devant le module), écrans tournés vers le module.
      - sol : premier obstacle solide sous le module (terrain, plancher ou toit d'un bâtiment), puis sous chaque objet ;
      - mur : obstacle devant le module, à hauteur d'homme ; trouvé : les écrans muraux s'y adossent ; sinon chaque
        écran mural est remplacé par son équivalent sur pied (colonne « sans support ») ou n'est pas posé ;
      - plafond : obstacle au-dessus du sol, entre 3 m et 4,5 m ; sinon les écrans suspendus sont remplacés de même ;
      - chaque objet est posé par un pas à part : un pas raté n'arrête pas les suivants, et le journal le dit
        (« [COMSPEC TOC] salle | objet k/N classe=… ok | échec raison », puis un résumé).
    Encombrement devant le module (largeur x profondeur) : petite 8 x 7 m, moyenne 11 x 8,5 m, grande 16 x 9,5 m.

    Modes :
      ["build", [posATL, direction, taille, nom, camp, id, classe des postes, aligner sur le bâtiment, repli, demandeur]]
                                        pose la salle sur ce poste, un rappel par objet -> [id, 0, prévus, [remarques], nom] ;
                                        le bilan (« finish ») rend [id, posés, prévus, [remarques], nom] au demandeur
      ["step", …] / ["finish", …]       un objet de la salle / bilan de la pose (rappels de « build »)
      ["eden", [module, posATL, direction, taille, nom, camp, postes, supprimer avec le module]]
                                        serveur, après la reprise de la partie précédente : salle déjà reprise pour ce
                                        module (même nom, même endroit, même carte) : gardée ; incomplète : reposée
      ["zeus", [posATL, direction, taille, nom, camp, postes]]   poste du Zeus : demande au serveur, ou repli
      ["done", [résultat, …]]           poste du Zeus : résultat écrit dans la ligne de retour du Zeus (jamais une alerte)
      ["remove", id]                    serveur : retire la salle (écrans posés par elle, mobilier, PC)
      ["plan", [taille, postes, mur, hauteur du plafond (-1 : aucun), décalage]]   -> [objets, non posés] (sans effet)
      ["footprint", taille]             -> [largeur, profondeur] en mètres (salle avec mur et plafond)
      ["selfTest"]                      -> [réussis, échecs] : chaque taille montée à blanc (classes, chevauchements,
                                        comptes), sans rien poser. Exemple : ["selfTest"] call comspec_atak_native_fnc_tocHallBuild;
*/
params [["_mode", "selfTest"], ["_a", []]];
private _fn = comspec_atak_native_fnc_tocHallBuild;
// ---- TABLE _rooms (JSON) : [taille, distance du mur, postes, objets]. Objet : [rôle, classe, x, y, support
// ("wall" | "ceiling" | "floor"), affichage, nom, réglage (texte : dalles 2 et suivantes, ou message de l'affichage
// TEXT ; nombre : rang de l'escouade montrée), classe sans support ("" : non posé), x, y et affichage sans support].
private _rooms = [
["S", 6, 2, [
["wall", "COMSPEC_ATAK_Screen_Wall2x2_Quad", 0, 6, "wall", "SPLIT", "MUR", "SAT1, CLOCK, LOG", "COMSPEC_ATAK_Screen_136_Stand", 0, 6, "SPLIT"],
["clock", "COMSPEC_ATAK_Screen_ClockStrip", 0, 6, "wall", "STRIP", "HORLOGE", "", "COMSPEC_ATAK_Screen_Tripod", -3, 5.6, "CLOCK"],
["plate", "COMSPEC_ATAK_Screen_NamePlate", -1.75, 6, "wall", "PLATE", "ÉLÉMENT 1", 0, "", 0, 0, ""],
["plate", "COMSPEC_ATAK_Screen_NamePlate", 1.75, 6, "wall", "PLATE", "ÉLÉMENT 2", 1, "", 0, 0, ""]
]],
["M", 7, 3, [
["wall", "COMSPEC_ATAK_Screen_Wall3x2", 0, 7, "wall", "SPLIT", "MUR", "SAT1, SAT2, CLOCK, LOG, REQ", "COMSPEC_ATAK_Screen_Led220_Truss", 0, 7, "SPLIT"],
["side", "COMSPEC_ATAK_Screen_98", -3, 7, "wall", "MOSAIC", "CAMÉRAS", "", "COMSPEC_ATAK_Screen_98_Stand", -3.9, 7, "MOSAIC"],
["side", "COMSPEC_ATAK_Screen_98", 3, 7, "wall", "SQUAD", "ESCOUADE", 0, "COMSPEC_ATAK_Screen_98_Stand", 3.9, 7, "SQUAD"],
["clock", "COMSPEC_ATAK_Screen_ClockStrip", 0, 7, "wall", "STRIP", "HORLOGE", "", "COMSPEC_ATAK_Screen_Tripod", 4.6, 4.6, "CLOCK"],
["led", "COMSPEC_ATAK_Screen_LedText8", 3, 7, "wall", "TEXT", "MESSAGE", "{poste} · {dtg}", "", 0, 0, ""],
["plate", "COMSPEC_ATAK_Screen_NamePlate", -3.5, 7, "wall", "PLATE", "ÉLÉMENT 1", 0, "", 0, 0, ""],
["plate", "COMSPEC_ATAK_Screen_NamePlate", -2.5, 7, "wall", "PLATE", "ÉLÉMENT 2", 1, "", 0, 0, ""],
["plate", "COMSPEC_ATAK_Screen_NamePlate", -1.2, 7, "wall", "PLATE", "ÉLÉMENT 3", 2, "", 0, 0, ""],
["plate", "COMSPEC_ATAK_Screen_NamePlate", 1.2, 7, "wall", "PLATE", "ÉLÉMENT 4", 3, "", 0, 0, ""],
["ceiling", "COMSPEC_ATAK_Screen_65_Ceiling2", 0, 4.6, "ceiling", "EXCHECK", "EXÉCUTION", "KILLS", "COMSPEC_ATAK_Screen_Tripod", -4.6, 4.6, "EXCHECK"]
]],
["L", 8, 4, [
["wall", "COMSPEC_ATAK_Screen_Wall4x3", 0, 8, "wall", "SPLIT", "MUR", "SAT1, CLOCK, LOG", "COMSPEC_ATAK_Screen_Led220_Truss", 0, 8, "SPLIT"],
["side", "COMSPEC_ATAK_Screen_98", -3.75, 8, "wall", "MOSAIC", "CAMÉRAS", "", "COMSPEC_ATAK_Screen_98_Stand", -3.75, 8, "MOSAIC"],
["side", "COMSPEC_ATAK_Screen_98", 3.75, 8, "wall", "SQUAD", "ESCOUADE", 0, "COMSPEC_ATAK_Screen_98_Stand", 3.75, 8, "SQUAD"],
["stand", "COMSPEC_ATAK_Screen_136_Stand", -6.2, 5.6, "floor", "SAT", "SATELLITE", "", "", 0, 0, ""],
["stand", "COMSPEC_ATAK_Screen_136_Stand", 6.2, 5.6, "floor", "REQ", "DEMANDES", "", "", 0, 0, ""],
["clock", "COMSPEC_ATAK_Screen_ClockStrip", -4.2, 8, "wall", "STRIP", "HORLOGE", "", "COMSPEC_ATAK_Screen_Tripod", 4.8, 3.4, "CLOCK"],
["led", "COMSPEC_ATAK_Screen_LedText8", 3.75, 8, "wall", "TEXT", "MESSAGE", "{poste} · {dtg}", "", 0, 0, ""],
["plate", "COMSPEC_ATAK_Screen_NamePlate", -5.4, 8, "wall", "PLATE", "ÉLÉMENT 1", 0, "", 0, 0, ""],
["plate", "COMSPEC_ATAK_Screen_NamePlate", -3, 8, "wall", "PLATE", "ÉLÉMENT 2", 1, "", 0, 0, ""],
["plate", "COMSPEC_ATAK_Screen_NamePlate", 5.5, 8, "wall", "PLATE", "ÉLÉMENT 3", 2, "", 0, 0, ""],
["plate", "COMSPEC_ATAK_Screen_NamePlate", 6.5, 8, "wall", "PLATE", "ÉLÉMENT 4", 3, "", 0, 0, ""],
["ceiling", "COMSPEC_ATAK_Screen_65_Ceiling3", 0, 5.2, "ceiling", "EXCHECK", "EXÉCUTION", "KILLS, FEED", "COMSPEC_ATAK_Screen_Tripod", -4.8, 3.4, "EXCHECK"]
]]
];
// ---- FIN
// ---- TABLE _dims (JSON) : boîte de chaque objet dans son repère [classe ou rôle, largeur, y devant le dos, y derrière,
// z bas, z haut] (tools/model_toc_screens.py « closed_boxes », docs/MODELS.md). Écran posé à 180° : il occupe, dans le
// repère de la salle, de y - « y derrière » à y - « y devant ». Mobilier du jeu : par rôle (desk, chair, pc).
private _dims = [
["COMSPEC_ATAK_Screen_Wall2x2_Quad", 2.458, 0, 0.154, 0.885, 2.283],
["COMSPEC_ATAK_Screen_Wall3x2", 3.674, 0, 0.154, 0.885, 2.283],
["COMSPEC_ATAK_Screen_Wall4x3", 4.89, 0, 0.154, 0.885, 2.971],
["COMSPEC_ATAK_Screen_98", 2.194, 0, 0.135, 0.93, 2.182],
["COMSPEC_ATAK_Screen_98_Stand", 2.194, -0.48, 0.52, 0, 2.092],
["COMSPEC_ATAK_Screen_136_Stand", 3.028, -0.62, 0.7, 0, 2.402],
["COMSPEC_ATAK_Screen_Led220_Truss", 4.878, -1, 0.55, 0, 3.244],
["COMSPEC_ATAK_Screen_65_Ceiling2", 2.926, -0.3, 0.3, -1.6, 0],
["COMSPEC_ATAK_Screen_65_Ceiling3", 4.389, -0.3, 0.3, -1.6, 0],
["COMSPEC_ATAK_Screen_ClockStrip", 1.16, 0, 0.085, 2.39, 2.71],
["COMSPEC_ATAK_Screen_NamePlate", 0.9, 0, 0.042, 2.425, 2.675],
["COMSPEC_ATAK_Screen_LedText8", 2.44, 0, 0.085, 2.38, 2.72],
["COMSPEC_ATAK_Screen_Tripod", 1.5, -0.5, 0.5, 0, 1.97],
["desk", 1.6, -0.4, 0.4, 0, 0.8],
["chair", 0.65, -0.33, 0.33, 0, 1.1],
["pc", 1.6, -0.3, 0.3, 0, 0.5]
];
// ---- FIN
// ---- TABLE _furniture (JSON) : mobilier du jeu de base, premier présent retenu. Bureaux : [classe, hauteur du plateau].
private _furniture = [
["desk", [["Land_TableDesk_F", 0.8], ["Land_CampingTable_F", 0.81], ["Land_WoodenTable_large_F", 0.85]]],
["chair", [["Land_OfficeChair_01_F", 0], ["Land_CampingChair_V2_F", 0], ["Land_ChairWood_F", 0]]]
];
// ---- FIN
// ---- TABLE _k (JSON) : rangée des postes (y du bureau, de la chaise, du PC portable, du poste fixe ; pas mini entre
// deux postes), plafond accepté pour un écran suspendu (mini, maxi), mur (marge d'adossement, portée de recherche
// derrière la distance prévue, recul maxi), rayon de reconnaissance d'une salle reprise.
private _k = [["deskY", 2.6], ["chairY", 1.8], ["laptopY", 2.55], ["pcY", 2.6], ["step", 1.7], ["ceilMin", 3], ["ceilMax", 4.5],
    ["wallGap", 0.02], ["wallFar", 3], ["wallNear", 2], ["keepRadius", 40]];
// ---- FIN
private _kv = createHashMapFromArray _k;
private _screenRoles = ["wall", "side", "stand", "clock", "led", "plate", "ceiling"];
private _roomOf = {
    params ["_size"];
    private _i = _rooms findIf { (_x select 0) isEqualTo _size };
    _rooms select ([_i, 1] select (_i < 0))
};
private _pickOf = {
    // Premier mobilier présent dans le jeu : [classe, hauteur du plateau] ; ["", 0.8] si aucun.
    params ["_kind"];
    private _cands = (_furniture select (_furniture findIf { (_x select 0) isEqualTo _kind })) select 1;
    _cands param [_cands findIf { isClass (configFile >> "CfgVehicles" >> (_x select 0)) }, ["", 0.8]]
};
// Objets de la salle dans son repère, sans rien poser : [[rôle, classe, x, y, z, support, affichage, nom, réglage]...]
// et les objets non posés faute de support [[rôle, classe, support]...].
private _resolve = {
    params ["_size", "_post", "_hasWall", "_ceil", "_dy"];
    ([_size] call _roomOf) params ["", "", "_seats", "_entries"];
    private _out = [];
    private _skipped = [];
    {
        _x params ["_role", "_cls", "_x0", "_y0", "_mount", "_view", "_name", "_extra", "_alt", "_ax", "_ay", "_av"];
        private _ok = switch (_mount) do {
            case "wall": { _hasWall };
            case "ceiling": { _ceil >= (_kv get "ceilMin") && {_ceil <= (_kv get "ceilMax")} };
            default { true };
        };
        if (_ok) then {
            _out pushBack [_role, _cls, _x0, _y0 + _dy, [0, _ceil] select (_mount isEqualTo "ceiling"), _mount, _view, _name, _extra];
        } else {
            if (_alt isEqualTo "") then {
                _skipped pushBack [_role, _cls, _mount];
            } else {
                // Écran sur pied à la place : une seule dalle, donc sans réglage de dalles ; l'escouade montrée reste.
                _out pushBack [_role, _alt, _ax, _ay + _dy, 0, "floor", _av, _name, ["", _extra] select (_extra isEqualType 0)];
            };
        };
    } forEach _entries;
    // Postes : un bureau, une chaise et un PC par place, rangée face au mur. Poste PC fixe choisi : au milieu du bureau,
    // places écartées selon sa largeur (mapSize).
    private _fixed = _post isEqualType "" && {_post isNotEqualTo ""} && {(getNumber (configFile >> "CfgVehicles" >> _post >> "comspec_tocPost")) > 0};
    private _pcCls = ["COMSPEC_ATAK_TocLaptop", _post] select _fixed;
    private _step = (_kv get "step") max ([0, (getNumber (configFile >> "CfgVehicles" >> _pcCls >> "mapSize")) + 0.1] select _fixed);
    (["desk"] call _pickOf) params ["_desk", "_top"];
    private _chair = (["chair"] call _pickOf) select 0;
    for "_i" from 0 to (_seats - 1) do {
        private _sx = -((_seats - 1) * _step) / 2 + _i * _step;
        _out pushBack ["desk", _desk, _sx, _kv get "deskY", 0, "floor", "", "", ""];
        _out pushBack ["chair", _chair, _sx, _kv get "chairY", 0, "floor", "", "", ""];
        _out pushBack ["pc", _pcCls, _sx, _kv get (["laptopY", "pcY"] select _fixed), _top, "desk", "", format ["POSTE %1", _i + 1], ""];
    };
    [_out, _skipped]
};
// Boîte d'un objet dans le repère de la salle : [x mini, x maxi, y mini, y maxi, z mini, z maxi] ; [] : taille inconnue.
private _boxOf = {
    params ["_row"];
    _row params ["_role", "_cls", "_x0", "_y0", "_z0"];
    private _key = [_cls, _role] select (_role in ["desk", "chair", "pc"]);
    private _i = _dims findIf { (_x select 0) isEqualTo _key };
    if (_i < 0) exitWith { [] };
    (_dims select _i) params ["", "_w", "_d0", "_d1", "_b0", "_b1"];
    [_x0 - _w / 2, _x0 + _w / 2, _y0 - _d1, _y0 - _d0, _z0 + _b0, _z0 + _b1]
};
// Premier obstacle solide entre deux points ASL (terrain, bâtiment, objet posé ; jamais un véhicule ni un personnage) :
// [point, normale, objet (nul : terrain)] ; [] : rien.
private _hit = {
    params ["_p0", "_p1", ["_ign", objNull]];
    private _r = [];
    {
        _x params ["_hp", "_hn", "_ho"];
        if (_r isEqualTo [] && {isNull _ho || {!(_ho isKindOf "AllVehicles")}}) then { _r = [_hp, _hn, _ho]; };
    } forEach (lineIntersectsSurfaces [_p0, _p1, _ign, objNull, true, 4, "GEOM", "FIRE"]);
    _r
};
// Mur ou plafond : seulement un bâtiment ou un mur posé (jamais un arbre, un rocher ni le relief).
private _built = { params ["_o"]; !isNull _o && {_o isKindOf "Building"} };
private _say = { params ["_lvl", "_txt"]; [_lvl, "TOC", _txt] call comspec_atak_native_fnc_log; };
// Une ligne au journal du jeu (« [COMSPEC TOC] … », RPT) et au journal COMSPEC (celui que les joueurs envoient).
private _journal = {
    params ["_txt"];
    diag_log text ("[COMSPEC TOC] " + _txt);
    if (!isNil "comspec_overwatch_connect_fnc_log") then { ["INFO", "ATAK TOC", _txt] call comspec_overwatch_connect_fnc_log; };
};
// Salle inscrite au registre (créée, ou son mobilier mis à jour) ; sur un poste en repli, par la même variable publique.
private _register = {
    params ["_id", "_nm", "_sd", "_props", ["_local", false]];
    private _l = +((missionNamespace getVariable ["COMSPEC_ATAK_TocHalls", []]) select { _x isEqualType [] && {(count _x) >= 5} });
    private _i = _l findIf { (_x select 0) isEqualTo _id };
    if (_i < 0) then {
        _l pushBack ([_id, (toUpper _nm) select [0, 32], toUpper _sd, [], _props] + ([[], ["LOCAL"]] select _local));
    } else {
        (_l select _i) set [4, _props];
    };
    missionNamespace setVariable ["COMSPEC_ATAK_TocHalls", _l, true];
    ["save"] call comspec_atak_native_fnc_tocPersist;
};
private _halls = { +((missionNamespace getVariable ["COMSPEC_ATAK_TocHalls", []]) select { _x isEqualType [] && {(count _x) >= 5} }) };

switch (_mode) do {
    case "plan": {
        _a params [["_size", "M"], ["_post", ""], ["_hasWall", true], ["_ceil", 3.4], ["_dy", 0]];
        [_size, _post, _hasWall, _ceil, _dy] call _resolve
    };
    case "footprint": {
        private _rows = ([_a, "", true, 3.4, 0] call _resolve) select 0;
        private _x0 = 0; private _x1 = 0; private _y1 = 0;
        {
            private _b = [_x] call _boxOf;
            if (_b isNotEqualTo []) then { _x0 = _x0 min (_b select 0); _x1 = _x1 max (_b select 1); _y1 = _y1 max (_b select 3); };
        } forEach _rows;
        [_x1 - _x0, _y1]
    };
    case "build": {
        _a params [["_pos", [0, 0, 0]], ["_dir", 0], ["_size", "M"], ["_nm", "SALLE TOC"], ["_sd", "WEST"], ["_id", ""], ["_post", ""], ["_snap", false], ["_fallback", false], ["_by", objNull]];
        // Seul le serveur pose, ou ce poste quand il tient lui-même les écrans (serveur plus ancien : fn_tocConfig « local »).
        if (!isServer && {!(["local", objNull] call comspec_atak_native_fnc_tocConfig)}) exitWith { ["", 0, 0, ["FAIL"], _nm] };
        if !(_size in (_rooms apply { _x select 0 })) then { _size = "M"; };
        if !(_nm isEqualType "" && {_nm isNotEqualTo ""}) then { _nm = "SALLE TOC"; };
        if (_id isEqualTo "") then { _id = format ["H%1%2", floor (serverTime * 10), floor random 10000]; };
        private _flags = [];
        if (!isServer || {_fallback}) then { _flags pushBack "LOCAL"; };
        ([_size] call _roomOf) params ["", "_wallY", "", ""];
        // 1. Sol sous le module : terrain, plancher ou toit ; rien dessous : le terrain.
        private _p = ATLToASL [_pos select 0, _pos select 1, (_pos param [2, 0]) max 0];
        private _f = [_p vectorAdd [0, 0, 1.2], _p vectorAdd [0, 0, -40]] call _hit;
        private _org = if (_f isEqualTo []) then { [_p select 0, _p select 1, getTerrainHeightASL _p] } else { _f select 0 };
        private _floorObj = if (_f isEqualTo []) then { objNull } else { _f select 2 };
        // 2. Plafond : obstacle au-dessus du sol (un bâtiment, jamais le relief).
        private _r = [_org vectorAdd [0, 0, 0.5], _org vectorAdd [0, 0, 12]] call _hit;
        private _ceil = -1;
        private _roofObj = objNull;
        if (_r isNotEqualTo [] && {[_r select 2] call _built}) then { _ceil = ((_r select 0) select 2) - (_org select 2); _roofObj = _r select 2; };
        private _indoor = _ceil > 0;
        if (!_indoor) then { _flags pushBack "OUT"; };
        // 3. Zeus dans un bâtiment : la salle s'aligne sur ses murs (quart de tour le plus proche du choix du Zeus).
        private _bld = [_roofObj, _floorObj] select (isNull _roofObj);
        if (_snap && {_indoor} && {!isNull _bld}) then { _dir = (getDir _bld) + 90 * (round ((_dir - (getDir _bld)) / 90)); };
        private _fw = [sin _dir, cos _dir, 0];
        private _rt = [cos _dir, -(sin _dir), 0];
        private _toWorld = { params ["_x0", "_y0"]; [(_org select 0) + _x0 * (_rt select 0) + _y0 * (_fw select 0), (_org select 1) + _x0 * (_rt select 1) + _y0 * (_fw select 1)] };
        // 4. Mur devant le module, à hauteur d'homme : les écrans muraux s'y adossent.
        private _eye = _org vectorAdd [0, 0, 1.5];
        private _w = [_eye vectorAdd (_fw vectorMultiply 1), _eye vectorAdd (_fw vectorMultiply (_wallY + (_kv get "wallFar")))] call _hit;
        private _wallD = if (_w isNotEqualTo [] && {[_w select 2] call _built}) then { (_w select 0) distance2D _org } else { -1 };
        // Assez haut pour les écrans : le même mur se retrouve à 2,4 m du sol (un muret ou un merlon ne compte pas).
        private _high = _org vectorAdd [0, 0, 2.4];
        private _w2 = [_high vectorAdd (_fw vectorMultiply 1), _high vectorAdd (_fw vectorMultiply (_wallY + (_kv get "wallFar")))] call _hit;
        private _tall = _w2 isNotEqualTo [] && {[_w2 select 2] call _built} && {(abs (((_w2 select 0) distance2D _org) - _wallD)) < 0.6};
        private _hasWall = _tall && {_wallD >= (_wallY - (_kv get "wallNear"))};
        private _dy = 0;
        if (_hasWall) then { _dy = _wallD - (_kv get "wallGap") - _wallY; } else { _flags pushBack "NOWALL"; };
        // Mur trop proche pour la salle : écrans sur pied devant lui, sans jamais entrer dans la rangée des postes.
        if (!_hasWall && {_wallD > 0}) then { _dy = ((_wallD - 1.3) - _wallY) max (4.6 - _wallY); };
        ["INFO", format ["salle | « %1 » (%2) : sol %3, plafond %4, mur %5, direction %6", _nm, _size,
            ["terrain", "bâtiment ou objet"] select (!isNull _floorObj),
            [format ["à %1 m", (round (_ceil * 10)) / 10], "aucun (extérieur)"] select (!_indoor),
            [["aucun", format ["à %1 m, trop proche ou trop bas", (round (_wallD * 10)) / 10]] select (_wallD > 0), format ["à %1 m", (round (_wallD * 10)) / 10]] select _hasWall,
            round _dir]] call _say;
        // 5. Plan complet avant la première pose : sol et mur de chaque objet (rien de posé ne gêne les mesures).
        ([_size, _post, _hasWall, _ceil, _dy] call _resolve) params ["_rows", "_skipped"];
        private _sqs = ["squads", _sd] call comspec_atak_native_fnc_tocScreen;
        if !(_sqs isEqualType []) then { _sqs = []; };
        private _plan = _rows apply {
            _x params ["_role", "_cls", "_x0", "_y0", "_z0", "_mount", "_view", "_name", "_extra"];
            if (_mount isEqualTo "wall") then {
                private _b0 = [_x0, _y0 - 1.5] call _toWorld;
                private _b1 = [_x0, _y0 + 1] call _toWorld;
                private _wh = [[_b0 select 0, _b0 select 1, (_org select 2) + 1.5], [_b1 select 0, _b1 select 1, (_org select 2) + 1.5]] call _hit;
                if (_wh isNotEqualTo [] && {[_wh select 2] call _built}) then { _y0 = (_y0 - 1.5) + ((_wh select 0) distance2D _b0) - (_kv get "wallGap"); };
            };
            private _xy = [_x0, _y0] call _toWorld;
            // Sol sous l'objet : à 1,5 m près autour du sol de la salle ; sinon le sol de la salle (bâtiment) ou le terrain.
            private _g = [[_xy select 0, _xy select 1, (_org select 2) + 1], [_xy select 0, _xy select 1, (_org select 2) - 1.5]] call _hit;
            private _gz = if (_g isNotEqualTo []) then { (_g select 0) select 2 } else { [getTerrainHeightASL _xy, _org select 2] select (_indoor || {!isNull _floorObj}) };
            // Écran suspendu : plafond mesuré au-dessus de lui (le plafond de la salle si rien n'y est trouvé).
            if (_mount isEqualTo "ceiling") then {
                private _ch = [[_xy select 0, _xy select 1, (_org select 2) + 0.5], [_xy select 0, _xy select 1, (_org select 2) + 12]] call _hit;
                _gz = (_org select 2);
                if (_ch isNotEqualTo [] && {[_ch select 2] call _built}) then { _z0 = ((_ch select 0) select 2) - _gz; };
            };
            [_role, _cls, [_xy select 0, _xy select 1, _gz + _z0], _dir + ([180, 0] select (_role isEqualTo "desk")), _view, _name, _extra, _mount]
        };
        // 6. Salle inscrite avant la pose : les écrans s'y lient un par un, un objet raté ne bloque ni elle ni les autres.
        [_id, _nm, _sd, [], "LOCAL" in _flags] call _register;
        // 7. Un rappel par objet (file du mod, fn_waitAndExec) : une erreur de script dans un pas n'arrête que ce pas.
        // Dans une simple boucle, la première erreur arrêtait toute la pose (4.7.0 : journal sans aucune ligne d'objet).
        private _n = count _plan;
        private _ctx = [_id, _nm, _sd, _size, _n, _sqs, [], [], [], _flags, _skipped, _by];
        {
            [{ ["step", _this] call comspec_atak_native_fnc_tocHallBuild; }, [_ctx, _x, _forEachIndex], 0.05 * _forEachIndex] call comspec_atak_native_fnc_waitAndExec;
        } forEach _plan;
        // 8. Bilan une fois tous les pas passés : mobilier gardé avec la salle, résumé, résultat rendu au demandeur.
        [{ ["finish", _this] call comspec_atak_native_fnc_tocHallBuild; }, _ctx, 0.05 * _n + 0.5] call comspec_atak_native_fnc_waitAndExec;
        [_id, 0, _n, _flags, _nm]
    };
    case "step": {
        // Pose d'un objet de la salle : [contexte de la pose, ligne du plan, rang]. Le contexte est partagé par tous les pas.
        _a params ["_ctx", "_row", "_k"];
        _ctx params ["_id", "_nm", "_sd", "", "_n", "_sqs", "_objs", "_props", "_done"];
        private _res = call {
            _row params ["_role", "_cls", "_at", "_d", "_view", "_name", "_extra", "_mount"];
            if (_cls isEqualTo "") exitWith { [objNull, "aucun mobilier du jeu disponible"] };
            if !(isClass (configFile >> "CfgVehicles" >> _cls)) exitWith { [objNull, "classe absente du jeu"] };
            private _o = objNull;
            if (_role in _screenRoles) then {
                private _sq = if (_extra isEqualType 0) then { _sqs param [_extra, ""] } else { "" };
                private _pans = ["", _extra] select (_extra isEqualType "" && {_view isNotEqualTo "TEXT"});
                private _more = [];
                if (_view isEqualTo "TEXT" && {_extra isEqualType ""} && {_extra isNotEqualTo ""}) then {
                    private _txt = ["parse", [_extra]] call comspec_atak_native_fnc_tocText;
                    if (_txt isEqualType [] && {_txt isNotEqualTo []}) then { _more = [["text", _txt]]; };
                };
                _o = ["spawn", [ASLToATL _at, _d, _cls, [_name, _nm] select (_name isEqualTo ""), _sd, _view, false, 0, _sq, _pans, _more]] call comspec_atak_native_fnc_tocScreen;
            } else {
                _o = createVehicle [_cls, ASLToATL _at, [], 0, "CAN_COLLIDE"];
            };
            if (isNil "_o" || {!(_o isEqualType objNull)} || {isNull _o}) exitWith { [objNull, "création refusée par le jeu"] };
            // Position au sol trouvé (ASL) puis orientation droite ; rien ne tombe, rien ne reste en l'air.
            _o setPosASL _at;
            _o setVectorDirAndUp [[sin _d, cos _d, 0], [0, 0, 1]];
            _o setVelocity [0, 0, 0];
            _o setVariable ["COMSPEC_ATAK_TocRoom", _id, true];
            _o setVariable ["COMSPEC_ATAK_TocRoomRole", _role];
            if (_role in _screenRoles) then {
                _o setVariable ["COMSPEC_ATAK_TocHall", _id, true];
            } else {
                // Seul le bureau est figé (il ne glisse pas sous le PC). Jamais un écran ni un PC : sans simulation, un objet
                // peut perdre son menu ACE ; ce sont des objets statiques, ils ne bougent pas.
                if (_role isEqualTo "desk") then { if (isServer) then { _o enableSimulationGlobal false; } else { _o enableSimulation false; }; };
                if (_role isEqualTo "pc") then {
                    _o setVariable ["COMSPEC_ATAK_TocHall", _id, true];
                    _o setVariable ["COMSPEC_ATAK_TocSide", toUpper _sd, true];
                    // Poste PC fixe : ses moniteurs sont des écrans de la salle (PIP du poste par défaut).
                    if ((getNumber (configOf _o >> "comspec_tocPost")) > 0) then {
                        private _pv = getText (configOf _o >> "comspec_tocPostView");
                        ["add", [_o, _name, _sd, 0, [_pv, "TOC"] select (_pv isEqualTo ""), true, false]] call comspec_atak_native_fnc_tocScreen;
                    };
                };
                { _x addCuratorEditableObjects [[_o], false]; } forEach allCurators;
            };
            [_o, ""]
        };
        if (isNil "_res" || {!(_res isEqualType [])}) then { _res = [objNull, "erreur de script (voir plus haut dans le journal)"]; };
        _res params [["_o", objNull], ["_why", ""]];
        _done pushBack _k;
        if (isNull _o) then {
            [format ["salle | objet %1/%2 classe=%3 échec %4", _k + 1, _n, _row select 1, _why]] call _journal;
        } else {
            _objs pushBack _o;
            if !((_row select 0) in _screenRoles) then { _props pushBack [typeOf _o, getPosASL _o, vectorDir _o, vectorUp _o, (_row select 0) isEqualTo "pc"]; };
            [format ["salle | objet %1/%2 classe=%3 ok", _k + 1, _n, _row select 1]] call _journal;
        };
        true
    };
    case "finish": {
        _a params ["_id", "_nm", "_sd", "_size", "_n", "", "_objs", "_props", "_done", "_flags", "_skipped", ["_by", objNull]];
        // Pas arrêté par une erreur de script : il n'a rien noté, le bilan le dit.
        for "_k" from 0 to (_n - 1) do {
            if !(_k in _done) then { [format ["salle | objet %1/%2 échec erreur de script (voir plus haut dans le journal)", _k + 1, _n]] call _journal; };
        };
        { [format ["salle | non posé classe=%1 : pas de %2 à cet endroit", _x select 1, ["plafond", "mur"] select ((_x select 2) isEqualTo "wall")]] call _journal; } forEach _skipped;
        _objs = _objs select { !isNull _x };
        [_id, _nm, _sd, _props, "LOCAL" in _flags] call _register;
        // Premier PC : poste maître (tous les PC de la salle la pilotent).
        private _mi = _objs findIf { (_x getVariable ["COMSPEC_ATAK_TocRoomRole", ""]) isEqualTo "pc" };
        if (_mi >= 0) then { (_objs select _mi) setVariable ["COMSPEC_ATAK_TocHallMaster", true, true]; };
        private _map = missionNamespace getVariable ["COMSPEC_ATAK_TocHallObjs", createHashMap];
        _map set [_id, _objs];
        missionNamespace setVariable ["COMSPEC_ATAK_TocHallObjs", _map];
        private _ok = count _objs;
        if (_ok < _n) then { _flags pushBackUnique "FAIL"; };
        [format ["salle | « %1 » (%2) posée : %3 objet(s) sur %4, %5 non posé(s) faute de mur ou de plafond, remarques %6, salle %7",
            _nm, _size, _ok, _n, count _skipped, _flags, _id]] call _journal;
        // Résultat pour le Zeus qui a demandé la salle : ce poste, ou son poste par le réseau.
        private _res = [_id, _ok, _n, _flags, _nm];
        if (_by isEqualType objNull && {!isNull _by}) then {
            if (hasInterface && {_by isEqualTo player}) then { ["done", [_res]] call _fn; } else { ["comspec_atak_native_tocHallDone", [_res], _by] call CBA_fnc_targetEvent; };
        };
        _res
    };
    case "eden": {
        _a params [["_logic", objNull], ["_pos", [0, 0, 0]], ["_dir", 0], ["_size", "M"], ["_nm", "SALLE TOC"], ["_sd", "WEST"], ["_post", ""], ["_bind", true]];
        if (!isServer) exitWith { [] };
        // Salle de CE module : même nom et même endroit (même carte et même mission : clé de la sauvegarde). Une autre
        // salle du même nom ailleurs sur la carte appartient à un autre module : identifiant à part.
        private _near = { params ["_hid"]; (["screens", _hid] call comspec_atak_native_fnc_tocHall) select { (_x distance2D _pos) < (_kv get "keepRadius") } };
        private _id = format ["E-%1", _nm];
        if ((count (["screens", _id] call comspec_atak_native_fnc_tocHall)) > 0 && {([_id] call _near) isEqualTo []}) then {
            _id = format ["E-%1-%2-%3", _nm, round ((_pos select 0) / 10), round ((_pos select 1) / 10)];
        };
        private _want = count ((([_size] call _roomOf) select 3) select { (_x select 8) isNotEqualTo "" || {(_x select 4) isEqualTo "floor"} });
        private _have = count ([_id] call _near);
        private _known = ((call _halls) findIf { (_x select 0) isEqualTo _id }) >= 0;
        private _out = [];
        if (_known && {_have >= ((ceil (_want / 2)) max 2)}) then {
            ["INFO", format ["salle | « %1 » reprise de la partie précédente (%2 écran(s) à leur place) : pas de nouvelle pose", _nm, _have]] call _say;
            _out = [_id, _have, _have, ["KEPT"], _nm];
        } else {
            if (_known || {_have > 0}) then {
                ["WARNING", format ["salle | « %1 » gardée incomplète (%2 écran(s) sur %3) : retirée puis reposée en entier", _nm, _have, _want]] call _say;
                ["remove", _id] call _fn;
            };
            _out = ["build", [_pos, _dir, _size, _nm, _sd, _id, _post, false, false]] call _fn;
        };
        // Réglage du module « Supprimer la salle avec le module » : le module supprimé (Zeus, script) emporte sa salle.
        if (!(missionNamespace getVariable ["COMSPEC_ATAK_TocRoomEndEh", false])) then {
            missionNamespace setVariable ["COMSPEC_ATAK_TocRoomEndEh", true];
            { addMissionEventHandler [_x, { missionNamespace setVariable ["COMSPEC_ATAK_MissionEnding", true]; }]; } forEach ["Ended", "MPEnded"];
        };
        if (!isNull _logic) then {
            _logic setVariable ["COMSPEC_ATAK_TocRoomId", _id];
            if (_bind) then {
                _logic addEventHandler ["Deleted", {
                    params ["_l"];
                    // Fin de mission : tout est supprimé, la salle reste dans la sauvegarde.
                    if (missionNamespace getVariable ["COMSPEC_ATAK_MissionEnding", false]) exitWith {};
                    ["remove", _l getVariable ["COMSPEC_ATAK_TocRoomId", ""]] call comspec_atak_native_fnc_tocHallBuild;
                }];
            };
        };
        _out
    };
    case "zeus": {
        _a params [["_pos", [0, 0, 0]], ["_dir", 0], ["_size", "M"], ["_nm", "SALLE TOC"], ["_sd", "WEST"], ["_post", ""]];
        if (!hasInterface) exitWith { false };
        private _id = format ["H%1%2", floor (serverTime * 10), floor random 10000];
        // Ce poste est le serveur, ou le serveur est plus ancien (repli) : la salle est posée ici, par le même constructeur.
        if (isServer || {["local", objNull] call comspec_atak_native_fnc_tocConfig}) exitWith {
            ["build", [_pos, _dir, _size, _nm, _sd, _id, _post, true, !isServer, player]] call _fn;
            true
        };
        ["do", ["spawn", [_pos, _dir, _size, _nm, _sd, _id, _post, player, true]]] call comspec_atak_native_fnc_tocHall;
        // Aucune salle de cet identifiant 8 s plus tard : le serveur n'a pas répondu (mod absent ou trop ancien pour
        // la salle complète) ; ce poste la pose lui-même et le dit.
        [{
            params ["_args", "_id"];
            if (((missionNamespace getVariable ["COMSPEC_ATAK_TocHalls", []]) findIf { _x isEqualType [] && {(_x param [0, ""]) isEqualTo _id} }) >= 0) exitWith {};
            ["WARNING", "TOC", "salle | le serveur n'a pas posé la salle en 8 s : pose par ce poste, non gardée d'une partie à l'autre"] call comspec_atak_native_fnc_log;
            missionNamespace setVariable ["COMSPEC_ATAK_TocForceFallback", true];
            _args params ["_pos", "_dir", "_size", "_nm", "_sd", "_post"];
            ["build", [_pos, _dir, _size, _nm, _sd, _id, _post, true, true, player]] call comspec_atak_native_fnc_tocHallBuild;
        }, [[_pos, _dir, _size, _nm, _sd, _post], _id], 8] call comspec_atak_native_fnc_waitAndExec;
        true
    };
    case "done": {
        // Résultat pour le Zeus : ligne de retour du centre Zeus, bandeau ZEN ou téléphone ; jamais une alerte du jeu.
        _a params [["_res", []]];
        if (!hasInterface || {!(_res isEqualType [])} || {(count _res) < 5}) exitWith { false };
        _res params ["", "_ok", "_n", "_flags", "_nm"];
        private _t = format [("ATAK : salle TOC « %1 » posée, %2 objet(s) sur %3") call comspec_atak_native_fnc_tr, _nm, _ok, _n];
        if (_n <= 0) then { _t = format [("ATAK : salle TOC « %1 » non posée, voir le journal") call comspec_atak_native_fnc_tr, _nm]; };
        {
            _x params ["_flag", "_txt"];
            if (_flag in _flags) then { _t = _t + " · " + (_txt call comspec_atak_native_fnc_tr); };
        } forEach [
            ["OUT", "pas de plafond : écrans sur pied à la place des écrans suspendus"],
            ["NOWALL", "pas de mur devant : écrans sur pied, bandeaux et plaques non posés"],
            ["FAIL", "objets manquants : voir le journal"],
            ["LOCAL", "serveur plus ancien : salle posée par ce poste, non gardée d'une partie à l'autre"]
        ];
        ["feedback", [_t]] call comspec_atak_native_fnc_zeusMenu;
        true
    };
    case "remove": {
        private _id = _a;
        if (!isServer || {!(_id isEqualType "")} || {_id isEqualTo ""}) exitWith { false };
        private _l = call _halls;
        private _i = _l findIf { (_x select 0) isEqualTo _id };
        private _map = missionNamespace getVariable ["COMSPEC_ATAK_TocHallObjs", createHashMap];
        private _objs = +(_map getOrDefault [_id, []]);
        // Salle reprise d'une partie précédente : mobilier retrouvé à sa place, écrans par leur lien.
        if (_i >= 0) then {
            {
                _x params [["_cls", ""], ["_p", [0, 0, 0]]];
                { _objs pushBackUnique _x; } forEach ((nearestObjects [ASLToAGL _p, [_cls], 1]) select { alive _x });
            } forEach ((_l select _i) select 4);
        };
        { _objs pushBackUnique _x; } forEach (["screens", _id] call comspec_atak_native_fnc_tocHall);
        private _n = 0;
        {
            if (!(_x isEqualType objNull) || {isNull _x}) then { continue };
            _n = _n + 1;
            // Écran : retiré du registre (ses dalles avec lui), l'objet posé par la salle part avec.
            if ((count (_x getVariable ["COMSPEC_ATAK_TocScr", []])) >= 8) then { ["remove", _x] call comspec_atak_native_fnc_tocScreen; };
            if (!isNull _x) then { deleteVehicle _x; };
        } forEach _objs;
        _map deleteAt _id;
        if (_i >= 0) then { _l deleteAt _i; missionNamespace setVariable ["COMSPEC_ATAK_TocHalls", _l, true]; };
        ["save"] call comspec_atak_native_fnc_tocPersist;
        ["INFO", format ["salle | %1 retirée : %2 objet(s)", _id, _n]] call _say;
        true
    };
    case "selfTest": {
        // Chaque taille montée à blanc, avec et sans mur, avec et sans plafond, PC portable et poste fixe le plus large.
        private _ok = 0;
        private _bad = [];
        private _t = { params ["_name", "_cond"]; if (_cond) then { _ok = _ok + 1; } else { _bad pushBack _name; }; };
        private _pcs = ["classes"] call comspec_atak_native_fnc_tocPc;
        if !(_pcs isEqualType []) then { _pcs = []; };
        private _widest = "";
        { if ((getNumber (configFile >> "CfgVehicles" >> _x >> "mapSize")) > (getNumber (configFile >> "CfgVehicles" >> _widest >> "mapSize"))) then { _widest = _x; }; } forEach _pcs;
        {
            _x params ["_size", "", "_seats", "_entries"];
            {
                _x params ["_wall", "_ceil"];
                {
                    private _post = _x;
                    private _tag = format ["%1 mur %2 plafond %3 postes « %4 »", _size, _wall, _ceil, _post];
                    ([_size, _post, _wall, _ceil, 0] call _resolve) params ["_rows", "_skipped"];
                    [_tag + " : tous les objets prévus ou annoncés non posés", ((count _rows) + (count _skipped)) isEqualTo ((count _entries) + 3 * _seats)] call _t;
                    [_tag + " : un mur d'images ou son remplaçant", (_rows findIf { (_x select 0) isEqualTo "wall" }) >= 0] call _t;
                    [_tag + " : rien de mural sans mur, rien de suspendu sans plafond", (_rows findIf { ((_x select 5) isEqualTo "wall" && {!_wall}) || {(_x select 5) isEqualTo "ceiling" && {_ceil < 3}} }) < 0] call _t;
                    private _miss = (_rows select { (_x select 1) isNotEqualTo "" && {!isClass (configFile >> "CfgVehicles" >> (_x select 1))} }) apply { _x select 1 };
                    [format ["%1 : classes absentes %2", _tag, _miss arrayIntersect _miss], _miss isEqualTo []] call _t;
                    [_tag + " : mobilier du jeu trouvé", (_rows findIf { (_x select 1) isEqualTo "" }) < 0] call _t;
                    private _boxes = _rows apply { [_x] call _boxOf };
                    [_tag + " : taille de chaque objet connue", (_boxes findIf { _x isEqualTo [] }) < 0] call _t;
                    private _clash = [];
                    {
                        private _b = _x;
                        private _i = _forEachIndex;
                        {
                            if (_forEachIndex > _i && {_b isNotEqualTo []} && {_x isNotEqualTo []}
                                && {(_b select 0) < (_x select 1) - 0.001} && {(_x select 0) < (_b select 1) - 0.001}
                                && {(_b select 2) < (_x select 3) - 0.001} && {(_x select 2) < (_b select 3) - 0.001}
                                && {(_b select 4) < (_x select 5) - 0.001} && {(_x select 4) < (_b select 5) - 0.001}) then {
                                _clash pushBack [(_rows select _i) select 1, (_rows select _forEachIndex) select 1];
                            };
                        } forEach _boxes;
                    } forEach _boxes;
                    [format ["%1 : chevauchements %2", _tag, _clash], _clash isEqualTo []] call _t;
                } forEach (["", _widest] arrayIntersect ["", _widest]);
            } forEach [[true, 3.4], [true, -1], [false, 3.4], [false, -1]];
        } forEach _rooms;
        ["INFO", format ["Contrôle de la salle TOC complète : %1 réussi(s), échec(s) : %2", _ok, _bad]] call _say;
        [_ok, _bad]
    };
    default { false };
};
