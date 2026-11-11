/*
    Liaisons du TOC vues par le Zeus (demande de TTRD, 4.8.0 : « faudrait pouvoir voir en Zeus la liaison entre tous
    les items et le TOC »). Bouton « Liaisons » du tableau des écrans (fn_tocManager) : tant qu'il est allumé, chaque
    écran, poste PC et PC portable de TOC est relié par un trait au centre de sa salle, avec une étiquette (salle,
    nom, ce qu'il montre) ; un objet sans salle porte l'étiquette « non lié » en rouge ; une caméra montrée par un
    écran est reliée à cet écran. Couleurs : écran vert, poste bleu, caméra orange, non lié rouge.
    Fenêtre « Gérer les écrans » (fn_tocManagerUi) : les caméras fixes du camp qu'aucun écran ne montre sont marquées
    « non montrée », le mobilier posé par une salle complète (bureaux, chaises, unités centrales) est relié à sa salle
    par un trait gris sans étiquette, et « list » donne la liste des objets liés et non liés pour la vue SALLES.
    Dessin seulement interface Zeus ouverte, pour les objets à moins de 400 m de la caméra, 80 au plus ; la liste est
    refaite toutes les 2 s, rien n'est calculé quand le bouton est éteint. Aucun message par-dessus le jeu.
    Modes :
      ["toggle"]     allume ou éteint les liaisons -> nouvel état
      ["state"]      allumé ?
      ["items"]      [[objet, genre "screen" | "post" | "cam" | "furn", salle (id), étiquette, cible du trait (objet ou [])]...] (gardé 2 s)
      ["list"]       [[nom, genre en clair, nom de la salle, lié ?]...] : écrans, postes et caméras un par un, mobilier compté par salle
      ["colour", genre ou "none"]   couleur RGBA du genre (table _colours)
      ["draw"]       une image (gestionnaire Draw3D)
*/
params [["_mode", "toggle"], ["_a", ""]];
private _fn = comspec_atak_native_fnc_tocLinks;
// ---- TABLE _colours (JSON) : couleur d'un trait et de son étiquette par genre d'objet [genre, [r, v, b, a]].
private _colours = [["screen", [0.2, 0.83, 0.6, 1]], ["post", [0.38, 0.69, 0.96, 1]], ["cam", [0.96, 0.6, 0.2, 1]], ["furn", [0.6, 0.66, 0.72, 0.7]], ["none", [1, 0.37, 0.34, 1]]];
// ---- FIN
switch (_mode) do {
    case "state": { (missionNamespace getVariable ["COMSPEC_ATAK_TocLinksEh", -1]) >= 0 };
    case "colour": { ((_colours select { (_x select 0) isEqualTo _a }) param [0, ["", [1, 1, 1, 1]]]) select 1 };
    case "toggle": {
        if (!hasInterface) exitWith { false };
        private _eh = missionNamespace getVariable ["COMSPEC_ATAK_TocLinksEh", -1];
        if (_eh >= 0) exitWith {
            removeMissionEventHandler ["Draw3D", _eh];
            missionNamespace setVariable ["COMSPEC_ATAK_TocLinksEh", -1];
            false
        };
        missionNamespace setVariable ["COMSPEC_ATAK_TocLinksEh", addMissionEventHandler ["Draw3D", { ["draw"] call comspec_atak_native_fnc_tocLinks; }]];
        true
    };
    case "items": {
        private _cache = uiNamespace getVariable ["COMSPEC_ATAK_TocLinksItems", [-1e9, []]];
        if (diag_tickTime - (_cache select 0) < 2) exitWith { _cache select 1 };
        private _hn = createHashMap;
        { if (_x isEqualType [] && {(count _x) >= 2}) then { _hn set [_x select 0, _x select 1]; }; } forEach (missionNamespace getVariable ["COMSPEC_ATAK_TocHalls", []]);
        private _out = [];
        private _seen = [];
        {
            private _o = _x;
            if (isNull _o || {!alive _o} || {!isNull (_o getVariable ["COMSPEC_ATAK_TocHost", objNull])} || {_o in _seen}) then { continue };
            _seen pushBack _o;
            private _c = _o getVariable ["COMSPEC_ATAK_TocScr", []];
            private _post = (getNumber (configOf _o >> "comspec_tocPost")) > 0 || {_o isKindOf "COMSPEC_ATAK_TocLaptop"};
            private _hall = _o getVariable ["COMSPEC_ATAK_TocHall", ""];
            private _what = if ((count _c) >= 8) then { ((["title", _c select 3] call comspec_atak_native_fnc_tocScreen) param [1, ""]) call comspec_atak_native_fnc_tr } else { "" };
            private _state = if (_o getVariable ["COMSPEC_ATAK_TocOff", false]) then { ("éteint") call comspec_atak_native_fnc_tr } else { _what };
            private _lbl = format ["%1 · %2", [_hn getOrDefault [_hall, _hall], ("non lié") call comspec_atak_native_fnc_tr] select (_hall isEqualTo ""), _state];
            _out pushBack [_o, ["screen", "post"] select _post, _hall, _lbl, []];
            // Caméra montrée par cet écran : reliée à lui.
            private _cam = objectFromNetId (_o getVariable ["COMSPEC_ATAK_TocCam", ""]);
            if (!isNull _cam && {(_c param [3, ""]) isEqualTo "CAM"}) then { _out pushBack [_cam, "cam", _hall, ("caméra montrée") call comspec_atak_native_fnc_tr, _o]; };
        } forEach ((missionNamespace getVariable ["COMSPEC_ATAK_TocScreens", []]) + (allMissionObjects "COMSPEC_ATAK_TocLaptop"));
        // Caméras fixes du camp qu'aucun écran ne montre : marquées, sans trait.
        private _shown = (_out select { (_x select 1) isEqualTo "cam" }) apply { _x select 0 };
        {
            if (_x isEqualType objNull && {!isNull _x} && {!(_x in _shown)}) then {
                _out pushBack [_x, "cam", "", format ["%1 · %2", _x getVariable ["COMSPEC_ATAK_CamName", ("caméra") call comspec_atak_native_fnc_tr], ("non montrée") call comspec_atak_native_fnc_tr], []];
            };
        } forEach ((missionNamespace getVariable ["COMSPEC_ATAK_Cams", []]) select [0, 40]);
        // Mobilier d'une salle complète (COMSPEC_ATAK_TocRoom, posé par fn_tocHallBuild) : cherché à 40 m des écrans de sa salle.
        private _furn = [];
        {
            _x params ["_o", "_kind", "_hall"];
            if (_hall isNotEqualTo "" && {_kind isEqualTo "screen"} && {(count _furn) < 60}) then {
                {
                    if ((_x getVariable ["COMSPEC_ATAK_TocRoom", ""]) isEqualTo _hall && {!(_x in _seen)} && {!(_x in _furn)} && {(count _furn) < 60}) then { _furn pushBack _x; _out pushBack [_x, "furn", _hall, "", []]; };
                } forEach (_o nearObjects 40);
            };
        } forEach +_out;
        uiNamespace setVariable ["COMSPEC_ATAK_TocLinksItems", [diag_tickTime, _out]];
        _out
    };
    case "list": {
        // Liste pour la fenêtre : un objet lié a une salle (écran, poste, mobilier) ou un écran qui le montre (caméra).
        private _hn = createHashMap;
        { if (_x isEqualType [] && {(count _x) >= 2}) then { _hn set [_x select 0, _x select 1]; }; } forEach (missionNamespace getVariable ["COMSPEC_ATAK_TocHalls", []]);
        private _out = [];
        private _furnN = createHashMap;
        {
            _x params ["_o", "_kind", "_hall", "", "_to"];
            switch (_kind) do {
                case "furn": { _furnN set [_hall, (_furnN getOrDefault [_hall, 0]) + 1]; };
                case "cam": {
                    private _linked = _to isEqualType objNull && {!isNull _to};
                    _out pushBack [_o getVariable ["COMSPEC_ATAK_CamName", ("caméra") call comspec_atak_native_fnc_tr], "caméra", [_hn getOrDefault [_hall, ""], ""] select !_linked, _linked];
                };
                default {
                    private _nm = (_o getVariable ["COMSPEC_ATAK_TocScr", []]) param [0, ""];
                    if (_nm isEqualTo "") then { _nm = ("poste") call comspec_atak_native_fnc_tr; };
                    _out pushBack [_nm, ["écran", "poste"] select (_kind isEqualTo "post"), _hn getOrDefault [_hall, ""], _hall isNotEqualTo ""];
                };
            };
        } forEach (["items"] call _fn);
        { _out pushBack [format [("%1 objets") call comspec_atak_native_fnc_tr, _y], "mobilier", _hn getOrDefault [_x, _x], true]; } forEach _furnN;
        // Les non liés d'abord : ce sont eux qu'on cherche.
        (_out select { !(_x select 3) }) + (_out select { _x select 3 })
    };
    case "draw": {
        // Interface Zeus fermée : rien (le bouton reste allumé pour le retour dans l'interface).
        if (isNull curatorCamera || {isNull (findDisplay 312)}) exitWith {};
        private _items = ["items"] call _fn;
        private _eye = getPosASL curatorCamera;
        // Centre de chaque salle : moyenne de la position de ses objets.
        private _sum = createHashMap;
        {
            _x params ["_o", "_kind", "_hall"];
            if (_hall isNotEqualTo "" && {_kind isNotEqualTo "cam"}) then {
                private _e = _sum getOrDefault [_hall, [[0, 0, 0], 0]];
                _sum set [_hall, [(_e select 0) vectorAdd (getPosASL _o), (_e select 1) + 1]];
            };
        } forEach _items;
        private _n = 0;
        {
            _x params ["_o", "_kind", "_hall", "_lbl", "_to"];
            if (_n >= 80) exitWith {};
            if (isNull _o || {(_eye distance (getPosASL _o)) > 400}) then { continue };
            _n = _n + 1;
            private _p = (ASLToAGL (getPosASL _o)) vectorAdd [0, 0, 1.2];
            private _col = ["colour", [_kind, "none"] select (_hall isEqualTo "" && {_kind isNotEqualTo "cam"})] call _fn;
            private _end = [];
            if (_to isEqualType objNull && {!isNull _to}) then { _end = (ASLToAGL (getPosASL _to)) vectorAdd [0, 0, 1.2]; } else {
                private _e = _sum getOrDefault [_hall, []];
                if ((count _e) isEqualTo 2 && {(_e select 1) > 0}) then { _end = (ASLToAGL ((_e select 0) vectorMultiply (1 / (_e select 1)))) vectorAdd [0, 0, 2.2]; };
            };
            if ((count _end) isEqualTo 3) then { drawLine3D [_p, _end, _col]; };
            if (_lbl isNotEqualTo "") then { drawIcon3D ["", _col, _p, 0, 0, 0, _lbl, 2, 0.032, "RobotoCondensed", "center"]; };
        } forEach _items;
    };
    default { false };
};
