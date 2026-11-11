/*
    Bibliothèque de préréglages de salle TOC (feuille de route TOC, Z3), couche de données : la disposition d'une salle
    (ses écrans, leur place les uns par rapport aux autres, leur affichage, ses scènes) gardée sous un nom, reposée
    dans la même salle, dans une autre, ou dans une autre mission. Deux bibliothèques, tenues par le serveur :
      "profile"   profil du serveur (COMSPEC_ATAK_TocPresets, 30 au plus) : sert à toutes les missions
      "mission"   mémoire du serveur pour cette partie (préréglages fournis par la mission, fn_tocApi)
    Sans Athena ni téléphone. Droit « préréglages » (fn_tocAccess) ; chaque action est écrite au journal.
    Préréglage : [1 (format), nom, date "AAAA-MM-JJ", camp, écrans, scènes]
      écran : [classe, [dx, dy, dz] (m, repère de la salle), cap relatif (°), nom, affichage, satellite, escouade,
               éteint, texte de l'affichage TEXTE, options, mosaïque]
    Texte d'échange : « COMSPEC-TOC-PRESET:1: » suivi du tableau, sur une ligne (noms sans guillemet : un préréglage
    sans texte d'affichage se lit aussi comme du JSON). Exemple dans docs/TOC-API.md, rejoué par tests/Unit/AtakTocPresetsTest.php.
    Modes purs :
      ["schema"]                       table des champs d'un écran (ci-dessous)
      ["sanitize", préréglage]         préréglage nettoyé, ou [] s'il est illisible
      ["export", préréglage]           texte d'échange
      ["import", texte]                préréglage nettoyé, ou []
    Serveur (appel local : mission, fn_tocApi) :
      ["list", bibliothèque]           [[nom, camp, nombre d'écrans, date]...]
      ["read", [nom, bibliothèque]]    préréglage ou []
      ["capture", [salle, nom]]        préréglage tiré de la salle
      ["store", [préréglage, bibliothèque]]   range (remplace celui du même nom)
      ["place", [préréglage, salle, "layout" | "spawn", posATL, cap]]
            "layout" : affichages remis sur les écrans de la salle (même classe, même ordre)
            "spawn"  : écrans créés autour du point, liés à la salle (créée si vide) ; rend [salle, posés, remplacés]
    Demandes (client -> serveur, l'émetteur fait foi) :
      ["save", [salle, nom, bibliothèque]]  ["apply", [nom, bibliothèque, salle, manière, posATL, cap]]
      ["delete", [nom, bibliothèque]]       ["importSave", [texte, bibliothèque]]
      ["get"]                               liste des deux bibliothèques : ["view"], événement local comspec_atak_toc_presets
      ["exportReq", [nom, bibliothèque]]    texte d'échange : presse-papiers, événement local comspec_atak_toc_presetText
*/
params [["_mode", "view"], ["_a", []]];
private _fn = comspec_atak_native_fnc_tocPresets;
private _acc = comspec_atak_native_fnc_tocAccess;
private _head = "COMSPEC-TOC-PRESET:1:";
// ---- TABLE _schema (JSON) : champs d'un écran [rang, nom, type S (texte) | N (nombre) | B (booléen) | V (3 nombres) | A (tableau libre), taille maximale].
private _schema = [
[0, "class", "S", 64], [1, "offset", "V", 3], [2, "dir", "N", 0], [3, "name", "S", 32], [4, "view", "S", 16], [5, "sat", "N", 0],
[6, "squad", "S", 40], [7, "off", "B", 0], [8, "text", "A", 1500], [9, "opt", "A", 200], [10, "mosaic", "A", 600]
];
// ---- FIN
private _inside = !isRemoteExecuted || {!isNil "_tocTrusted"};
// Texte sans guillemet ni retour à la ligne (le texte d'échange reste une seule ligne, lisible comme du JSON).
private _clean = { params ["_s", "_n"]; if !(_s isEqualType "") then { _s = ""; }; ((_s regexReplace ["[""\r\n\\\\]", ""]) select [0, _n]) };
private _r3 = { (round (_this * 1000)) / 1000 };
private _sanitize = {
    params [["_p", []]];
    if !(_p isEqualType [] && {(count _p) >= 5} && {(_p select 0) isEqualTo 1} && {(_p select 4) isEqualType []}) exitWith { [] };
    private _name = [_p select 1, 32] call _clean;
    if (_name isEqualTo "") exitWith { [] };
    private _items = [];
    {
        private _it = _x;
        if !(_it isEqualType [] && {(count _it) >= 5}) then { continue };
        private _row = [];
        private _bad = false;
        {
            _x params ["_i", "", "_ty", "_n"];
            private _v = _it param [_i, nil];
            switch (_ty) do {
                case "S": { _row pushBack ([[_v, ""] select (isNil "_v"), _n] call _clean); };
                case "N": { _row pushBack (if (!isNil "_v" && {_v isEqualType 0}) then { _v call _r3 } else { 0 }); };
                case "B": { _row pushBack (!isNil "_v" && {_v isEqualTo true}); };
                case "V": { if (!isNil "_v" && {_v isEqualTypeArray [0, 0, 0]}) then { _row pushBack (_v apply { _x call _r3 }); } else { _bad = true; _row pushBack [0, 0, 0]; }; };
                default { _row pushBack (if (!isNil "_v" && {_v isEqualType []} && {(count str _v) <= _n}) then { +_v } else { [] }); };
            };
        } forEach _schema;
        if (!_bad && {(_row select 0) isNotEqualTo ""}) then { _items pushBack _row; };
    } forEach ((_p select 4) select [0, 40]);
    if (_items isEqualTo []) exitWith { [] };
    private _sc = _p param [5, []];
    if !(_sc isEqualType [] && {(count str _sc) <= 6000}) then { _sc = []; };
    [1, _name, [_p select 2, 10] call _clean, toUpper ([_p select 3, 4] call _clean), _items, +_sc]
};
private _lib = {
    params ["_scope"];
    private _l = if (_scope isEqualTo "mission") then { localNamespace getVariable ["COMSPEC_ATAK_TocPresetsMission", []] } else { profileNamespace getVariable ["COMSPEC_ATAK_TocPresets", []] };
    if !(_l isEqualType []) then { _l = []; };
    _l select { _x isEqualType [] && {(count _x) >= 5} }
};
private _setLib = {
    params ["_scope", "_l"];
    if (_scope isEqualTo "mission") exitWith { localNamespace setVariable ["COMSPEC_ATAK_TocPresetsMission", _l]; };
    profileNamespace setVariable ["COMSPEC_ATAK_TocPresets", _l select [0 max ((count _l) - 30)]];
    [] call comspec_atak_native_fnc_saveProfile;
};
private _scopeOk = { params ["_s"]; [_s, "profile"] select !(_s in ["profile", "mission"]) };
switch (_mode) do {
    case "schema": { +_schema };
    case "sanitize": { [_a] call _sanitize };
    case "export": { private _p = [_a] call _sanitize; if (_p isEqualTo []) then { "" } else { _head + (str _p) } };
    case "import": {
        if !(_a isEqualType "" && {(_a find _head) isEqualTo 0} && {(count _a) <= 60000}) exitWith { [] };
        private _raw = _a select [count _head];
        // Seulement un tableau de valeurs simples (jamais de code) ; un texte abîmé donne [] sans erreur.
        if !((_raw select [0, 1]) isEqualTo "[" && {(_raw select [(count _raw) - 1]) isEqualTo "]"}) exitWith { [] };
        [parseSimpleArray _raw] call _sanitize
    };
    case "list": { ([[_a] call _scopeOk] call _lib) apply { [_x select 1, _x select 3, count (_x select 4), _x select 2] } };
    case "read": {
        _a params [["_name", ""], ["_scope", "profile"]];
        (([[_scope] call _scopeOk] call _lib) select { (_x select 1) isEqualTo _name }) param [0, []]
    };
    case "capture": {
        if (!isServer) exitWith { [] };
        _a params [["_hall", ""], ["_name", ""]];
        private _scr = ["screens", _hall] call comspec_atak_native_fnc_tocHall;
        if (_scr isEqualTo []) exitWith { [] };
        // Repère de la salle : centre des écrans, cap du premier écran.
        private _c = [0, 0, 0];
        { _c = _c vectorAdd (getPosASL _x); } forEach _scr;
        _c = _c vectorMultiply (1 / (count _scr));
        private _d0 = getDir (_scr select 0);
        private _items = _scr apply {
            private _o = _x;
            private _cfg = _o getVariable ["COMSPEC_ATAK_TocScr", []];
            private _v = (getPosASL _o) vectorDiff _c;
            // Écart tourné dans le repère de la salle (cap 0 = cap du premier écran).
            private _off = [(_v select 0) * (cos _d0) - (_v select 1) * (sin _d0), (_v select 0) * (sin _d0) + (_v select 1) * (cos _d0), _v select 2];
            [typeOf _o, _off, ((getDir _o) - _d0 + 360) mod 360, _cfg param [0, ""], _cfg param [3, "SPLIT"], _cfg param [8, 0], _cfg param [9, ""], _o getVariable ["COMSPEC_ATAK_TocOff", false],
                _o getVariable ["COMSPEC_ATAK_TocText", []], _o getVariable ["COMSPEC_ATAK_TocOpt", []], _o getVariable ["COMSPEC_ATAK_TocMosaic", []]]
        };
        private _h = ["get", _hall] call comspec_atak_native_fnc_tocHall;
        systemTimeUTC params ["_y", "_mo", "_dd"];
        private _two = { [str _this, "0" + str _this] select (_this < 10) };
        [[1, [_name, _h param [1, "SALLE"]] select (_name isEqualTo ""), format ["%1-%2-%3", _y, _mo call _two, _dd call _two], _h param [2, ""], _items, _h param [3, []]]] call _sanitize
    };
    case "store": {
        if (!isServer || {!_inside}) exitWith { false };
        _a params [["_p", []], ["_scope", "profile"]];
        _p = [_p] call _sanitize;
        if (_p isEqualTo []) exitWith { false };
        _scope = [_scope] call _scopeOk;
        private _l = ([_scope] call _lib) select { (_x select 1) isNotEqualTo (_p select 1) };
        _l pushBack _p;
        [_scope, _l] call _setLib;
        true
    };
    case "place": {
        if (!isServer || {!_inside}) exitWith { ["", 0, 0] };
        _a params [["_p", []], ["_hall", ""], ["_how", "layout"], ["_pos", [0, 0, 0]], ["_dir", 0]];
        _p = [_p] call _sanitize;
        if (_p isEqualTo []) exitWith { ["", 0, 0] };
        private _set = {
            params ["_o", "_it"];
            ["view", [_o, "sat", _it select 5]] call comspec_atak_native_fnc_tocScreen;
            ["view", [_o, "squad", _it select 6]] call comspec_atak_native_fnc_tocScreen;
            if ((count (_it select 10)) >= 5) then { ["view", [_o, "mosaic", _it select 10]] call comspec_atak_native_fnc_tocScreen; };
            if ((count (_it select 8)) >= 5) then { ["view", [_o, "text", _it select 8]] call comspec_atak_native_fnc_tocScreen; };
            ["view", [_o, "mode", _it select 4]] call comspec_atak_native_fnc_tocScreen;
            if ((count (_it select 9)) >= 2) then { _o setVariable ["COMSPEC_ATAK_TocOpt", _it select 9, true]; };
            _o setVariable ["COMSPEC_ATAK_TocOff", _it select 7, true];
        };
        if (_how isNotEqualTo "spawn") exitWith {
            // Mêmes classes, dans l'ordre : le n-ième écran d'une classe reçoit le n-ième réglage de cette classe.
            private _left = +(_p select 4);
            private _n = 0;
            {
                private _o = _x;
                private _i = _left findIf { (_x select 0) isEqualTo (typeOf _o) };
                if (_i >= 0) then { [_o, _left deleteAt _i] call _set; _n = _n + 1; };
            } forEach (["screens", _hall] call comspec_atak_native_fnc_tocHall);
            ["save"] call comspec_atak_native_fnc_tocPersist;
            [_hall, _n, 0]
        };
        if !(_pos isEqualTypeArray [0, 0, 0] && {_dir isEqualType 0}) exitWith { ["", 0, 0] };
        if (_hall isEqualTo "" || {(["get", _hall] call comspec_atak_native_fnc_tocHall) isEqualTo []}) then {
            _hall = format ["P%1%2", floor (serverTime * 10), floor random 10000];
            ["do", ["create", [_p select 1, _p select 3, objNull, _hall]]] call comspec_atak_native_fnc_tocHall;
        };
        private _n = 0;
        private _repl = 0;
        {
            private _it = _x;
            (_it select 1) params ["_dx", "_dy", "_dz"];
            // Classe absente de cette mission ou de ce serveur : fn_tocScreen pose le premier écran de sa liste et le dit.
            if !(isClass (configFile >> "CfgVehicles" >> (_it select 0))) then { _repl = _repl + 1; };
            private _at = [(_pos select 0) + _dx * (cos _dir) + _dy * (sin _dir), (_pos select 1) - _dx * (sin _dir) + _dy * (cos _dir), ((_pos select 2) + _dz) max 0];
            private _o = ["spawn", [_at, (_dir + (_it select 2)) mod 360, _it select 0, _it select 3, _p select 3, _it select 4, true, _it select 5, _it select 6]] call comspec_atak_native_fnc_tocScreen;
            if (!isNil "_o" && {_o isEqualType objNull} && {!isNull _o}) then {
                ["do", ["link", [_hall, _o]]] call comspec_atak_native_fnc_tocHall;
                [_o, _it] call _set;
                _n = _n + 1;
            };
        } forEach (_p select 4);
        // Scènes du préréglage : elles nomment les écrans d'origine, gardées seulement pour une pose « layout ».
        [_hall, _n, _repl]
    };
    case "save": {
        _a params [["_hall", ""], ["_name", ""], ["_scope", "profile"], ["_claimed", objNull]];
        if (!isServer) exitWith { ["save", [_hall, _name, _scope, player]] remoteExecCall ["comspec_atak_native_fnc_tocPresets", 2]; true };
        if !(_hall isEqualType "" && {_name isEqualType ""} && {_scope isEqualType ""}) exitWith { false };
        private _p = ["capture", [_hall, _name]] call _fn;
        (["actor", _claimed] call _acc) params ["_u"];
        if (_p isEqualTo []) exitWith { ["feedback", [_u, false, "Refusé : cette salle n'a aucun écran à enregistrer", []]] call _acc; false };
        if !(["check", [_claimed, "presets", _hall, "preset.save", _p select 1]] call _acc) exitWith { false };
        private _tocTrusted = true;
        ["store", [_p, _scope]] call _fn;
        ["feedback", [_u, true, "Préréglage enregistré : %1", [_p select 1]]] call _acc;
        true
    };
    case "apply": {
        _a params [["_name", ""], ["_scope", "profile"], ["_hall", ""], ["_how", "layout"], ["_pos", [0, 0, 0]], ["_dir", 0], ["_claimed", objNull]];
        if (!isServer) exitWith { ["apply", [_name, _scope, _hall, _how, _pos, _dir, player]] remoteExecCall ["comspec_atak_native_fnc_tocPresets", 2]; true };
        if !(_name isEqualType "" && {_scope isEqualType ""} && {_hall isEqualType ""} && {_how isEqualType ""}) exitWith { false };
        private _p = ["read", [_name, _scope]] call _fn;
        if (_p isEqualTo []) exitWith { false };
        (["actor", _claimed] call _acc) params ["_u"];
        // Poser des écrans neufs : droit « salle complète » ; remettre des affichages : droit « préréglages ».
        if !(["check", [_claimed, ["presets", "build"] select (_how isEqualTo "spawn"), _hall, "preset.apply", format ["%1 (%2)", _name, _how]]] call _acc) exitWith { false };
        private _tocTrusted = true;
        private _r = ["place", [_p, _hall, _how, _pos, _dir]] call _fn;
        ["feedback", [_u, true, "Préréglage posé : %1 écran(s), %2 remplacé(s) faute de modèle", [_r select 1, _r select 2]]] call _acc;
        ["comspec_atak_toc_presetApplied", [_r select 0, _name, _r select 1, _r select 2]] call CBA_fnc_localEvent;
        true
    };
    case "delete": {
        _a params [["_name", ""], ["_scope", "profile"], ["_claimed", objNull]];
        if (!isServer) exitWith { ["delete", [_name, _scope, player]] remoteExecCall ["comspec_atak_native_fnc_tocPresets", 2]; true };
        if !(_name isEqualType "" && {_scope isEqualType ""}) exitWith { false };
        _scope = [_scope] call _scopeOk;
        private _l = [_scope] call _lib;
        if ((_l findIf { (_x select 1) isEqualTo _name }) < 0) exitWith { false };
        if !(["check", [_claimed, "presets", "", "preset.delete", _name]] call _acc) exitWith { false };
        [_scope, _l select { (_x select 1) isNotEqualTo _name }] call _setLib;
        true
    };
    case "importSave": {
        _a params [["_txt", ""], ["_scope", "profile"], ["_claimed", objNull]];
        if (!isServer) exitWith { ["importSave", [_txt, _scope, player]] remoteExecCall ["comspec_atak_native_fnc_tocPresets", 2]; true };
        private _p = ["import", _txt] call _fn;
        (["actor", _claimed] call _acc) params ["_u"];
        if (_p isEqualTo []) exitWith { ["feedback", [_u, false, "Refusé : ce texte n'est pas un préréglage de salle TOC", []]] call _acc; false };
        if !(["check", [_claimed, "presets", "", "preset.import", _p select 1]] call _acc) exitWith { false };
        private _tocTrusted = true;
        ["store", [_p, _scope]] call _fn;
        ["feedback", [_u, true, "Préréglage importé : %1", [_p select 1]]] call _acc;
        true
    };
    case "get": {
        if (!hasInterface) exitWith { false };
        ["getSrv", player] remoteExecCall ["comspec_atak_native_fnc_tocPresets", 2];
        true
    };
    case "getSrv": {
        if (!isServer) exitWith { false };
        (["actor", _a] call _acc) params ["_u"];
        if (isNull _u || {!(["check", [_u, "view", "", "", ""]] call _acc)}) exitWith { false };
        ["recv", [["list", "profile"] call _fn, ["list", "mission"] call _fn]] remoteExecCall ["comspec_atak_native_fnc_tocPresets", owner _u];
        true
    };
    case "exportReq": {
        _a params [["_name", ""], ["_scope", "profile"], ["_claimed", objNull]];
        if (!isServer) exitWith { ["exportReq", [_name, _scope, player]] remoteExecCall ["comspec_atak_native_fnc_tocPresets", 2]; true };
        if !(_name isEqualType "" && {_scope isEqualType ""}) exitWith { false };
        (["actor", _claimed] call _acc) params ["_u"];
        if (isNull _u || {!(["check", [_u, "presets", "", "", ""]] call _acc)}) exitWith { false };
        ["recvText", [_name, ["export", ["read", [_name, _scope]] call _fn] call _fn]] remoteExecCall ["comspec_atak_native_fnc_tocPresets", owner _u];
        true
    };
    case "recv";
    case "recvText": {
        if (!hasInterface || {isMultiplayer && {isRemoteExecuted} && {!(remoteExecutedOwner in [0, 2])}} || {!(_a isEqualType [])}) exitWith { false };
        if (_mode isEqualTo "recv") exitWith {
            missionNamespace setVariable ["COMSPEC_ATAK_TocPresetsView", [_a param [0, []], _a param [1, []], diag_tickTime]];
            ["comspec_atak_toc_presets", _a] call CBA_fnc_localEvent;
            true
        };
        ["clip", _a param [1, ""]] call comspec_atak_native_fnc_tocDiag;
        ["comspec_atak_toc_presetText", _a] call CBA_fnc_localEvent;
        true
    };
    case "view": { missionNamespace getVariable ["COMSPEC_ATAK_TocPresetsView", [[], [], 0]] };
    default { false };
};
