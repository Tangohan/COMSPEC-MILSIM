/*
    Bluetooth : ce que l'on peut envoyer à un appareil appairé, en dehors des sons (fn_btSounds), et réception.
    Une ligne = [kind, ref, titre, catégorie, durée s]. Sortes :
      pho   photo du téléphone (ref = chemin complet, fichier transféré par la DLL, fn_btAction « fbeg »)
      mp3   morceau du dossier musique (ref = nom relatif, fichier transféré par la DLL)
      pos   ma position (grille, repère local 10 min chez le destinataire)
      wp    mon itinéraire de points de passage (app Points de passage)
      plan  plan enregistré de l'app Plan (ref = numéro dans la bibliothèque)
      seek  fiche SEEK : une interrogation du journal du terminal (ref = clé du sujet)
      sse   fiche SSE : personne enregistrée au SEEK (ref = clé de la fiche, missionNamespace COMSPEC_SSE_Sheets)
      note  note du Bloc-notes (ref = id de la note ; reçue : ajoutée aux notes, marquée « reçue de … »)
      idc   fiche CONTRÔLE ID (3.0.2) : un contrôle d'identité (document lu, verdict, niveaux N0 à N3, photo, contrôleur,
            heure, position), ref = clé de la fiche, missionNamespace COMSPEC_ATAK_IdChecks ; partagée par l'app Contrôle ID
            du SSE (["shareIdCheck", données] call comspec_atak_native_fnc_btAction), lue dans une vue dédiée de l'app Bluetooth
    Params : [action, argument]
      ["list", onglet]          onglet "pho" | "mp3" | "data" | "sheet" → lignes
      ["payload", [kind, ref]]  données envoyées (petites sortes) ; [] si l'élément n'existe plus
      ["apply", [id, kind, titre, données, expéditeur]] → range chez moi ce qui vient d'arriver, renvoie un résumé
      ["sheetLines", [kind, données]] → [[libellé, valeur]...] pour l'affichage d'une fiche reçue
      ["idcStore", données]     range une fiche CONTRÔLE ID (HashMap ou paires, voir plus bas) → sa clé ("" si vide)
      ["idcGet", clé]           la fiche (HashMap) ou une HashMap vide        ["idcRows", clé] → lignes de fn_formRender (vue dédiée)
    Fiche CONTRÔLE ID (données de idcStore, toutes facultatives ; le contrôle du SSE fn_idHistory est accepté tel quel) :
      key (ou idem) clé unique · ref « CTRL-42 » (Athena) · verdict ras | signale | recherche | hvt | inconnu | revoque | vole
      | expire | faux | doute · doc HashMap (type, country, number, last, first, dob, expires) ou docType, docCountry,
      docNumber, last, first, dob, expires · levels HashMap niveau (0..3) → HashMap (state ok|doubt|fail|alert|na, verdict,
      lines) ou [[« N0 », état, verdict, texte]...] · photo (image d'un PBO ou d'un fichier) · controller · time (ou clock) ·
      date · grid · pos [x, y, z] · case (dossier SSE)
    Reçus : missionNamespace COMSPEC_ATAK_BtInbox = [[id, kind, titre, expéditeur, heure, données]...] (30 derniers).
*/
params [["_act", "list"], ["_arg", ""]];
private _clock = { [dayTime, "HH:MM"] call BIS_fnc_timeToString };
private _seekHist = {
    private _h = missionNamespace getVariable ["COMSPEC_SeekQueryHistory", []];
    if !(_h isEqualType []) then { _h = []; };
    _h select { (_x isEqualType []) && {(count _x) >= 10} }
};
private _idcs = {
    private _l = missionNamespace getVariable ["COMSPEC_ATAK_IdChecks", []];
    if !(_l isEqualType []) then { _l = []; };
    _l select { _x isEqualType createHashMap }
};
private _idcVerdicts = createHashMapFromArray [
    ["ras", ["RAS", "#5cc76b"]], ["signale", ["Signalé", "#f2ab33"]], ["recherche", ["Recherché", "#e5483a"]],
    ["hvt", ["Cible prioritaire (HVT)", "#e5483a"]], ["inconnu", ["Inconnu des fichiers", "#e5483a"]], ["revoque", ["Papier annulé", "#e5483a"]],
    ["vole", ["Papier volé ou perdu", "#e5483a"]], ["expire", ["Papier expiré", "#f2ab33"]], ["faux", ["Faux document", "#e5483a"]],
    ["doute", ["Doute", "#f2ab33"]]
];
private _idcVerdict = { params ["_v"]; _idcVerdicts getOrDefault [toLower _v, [["Sans verdict", toUpper _v] select (_v isNotEqualTo ""), "#8a9a93"]] };
private _idcDocType = { params ["_t"]; createHashMapFromArray [["id_card", "Carte d'identité"], ["passport", "Passeport"], ["driving_license", "Permis de conduire"], ["military_id", "Carte militaire"]] getOrDefault [_t, ["Document", _t] select (_t isNotEqualTo "")] };
private _idcLevel = { params ["_l"]; createHashMapFromArray [["N0", "N0 · examen visuel"], ["N1", "N1 · bande MRZ"], ["N2", "N2 · puce NFC"], ["N3", "N3 · fichiers"]] getOrDefault [_l, _l] };
private _idcWho = { params ["_c"]; trim format ["%1 %2", toUpper (_c getOrDefault ["last", ""]), _c getOrDefault ["first", ""]] };
private _sheets = {
    private _s = missionNamespace getVariable ["COMSPEC_SSE_Sheets", []];
    if !(_s isEqualType []) then { _s = []; };
    _s select { (_x isEqualType []) && {(count _x) >= 6} }
};
switch (_act) do {
    case "list": {
        switch (_arg) do {
            case "pho": {
                if !([] call comspec_atak_native_fnc_bridge) exitWith { [] };
                if (isNil { uiNamespace getVariable "COMSPEC_ATAK_PhotoLibAt" }) then { ["list"] call comspec_atak_native_fnc_photoLibrary; };
                (uiNamespace getVariable ["COMSPEC_ATAK_PhotoLib", []]) apply { ["pho", _x select 0, _x select 1, "Photo du téléphone", 0] }
            };
            case "mp3": {
                private _ui = uiNamespace getVariable ["COMSPEC_ATAK_BtUi", createHashMap];
                if (isNil { _ui get "mp3" }) then {
                    private _p = (["MusicList"] call comspec_atak_native_fnc_extensionCall) splitString "|";
                    _ui set ["mp3", [[], _p select [2, 300]] select ((_p param [0, ""]) isEqualTo "OK")];
                };
                (_ui get "mp3") apply {
                    private _parts = _x splitString "\/";
                    ["mp3", _x, _parts select ((count _parts) - 1), "Fichier · " + (["Mes morceaux", _parts select 0] select ((count _parts) > 1)), 0]
                }
            };
            case "data": {
                private _out = [["pos", "", "Ma position", format ["Donnée · grille %1", [getPosASL player, 8] call comspec_atak_native_fnc_gridRef], 0]];
                private _wp = ((missionNamespace getVariable ["COMSPEC_ATAK_Waypoints", createHashMap]) getOrDefault ["pts", []]);
                if ((count _wp) > 0) then { _out pushBack ["wp", "", format ["Mon itinéraire (%1 étapes)", count _wp], "Donnée · points de passage", 0]; };
                {
                    _x params [["_n", "Plan"], ["_w", ""], ["_its", []], ["_d", ""]];
                    _out pushBack ["plan", str _forEachIndex, _n, format ["Plan · %1 éléments · %2%3", count _its, _w, ["", format [" · %1", _d]] select (_d isNotEqualTo "")], 0];
                } forEach (profileNamespace getVariable ["COMSPEC_ATAK_PlanLib", []]);
                {
                    _x params [["_nid", ""], ["_nt", ""], ["_nb", ""]];
                    _out pushBack ["note", _nid, [_nt, ("Sans titre") call comspec_atak_native_fnc_tr] select (_nt isEqualTo ""), format [("Note · %1 caractère(s)") call comspec_atak_native_fnc_tr, count _nb], 0];
                } forEach ((profileNamespace getVariable ["COMSPEC_ATAK_Notes", []]) select { _x isEqualType [] && {(count _x) >= 3} });
                _out
            };
            case "sheet": {
                private _out = (call _sheets) apply {
                    _x params ["_k", "_who", "_lines", "_by", "_hh", "_grid", ["_from", ""]];
                    ["sse", _k, _who, format ["Fiche SSE · %1 · %2%3", _hh, _grid, ["", format [" · reçue de %1", _from]] select (_from isNotEqualTo "")], 0]
                };
                reverse _out;
                // Fiches CONTRÔLE ID (les plus récentes d'abord).
                {
                    private _who = [_x] call _idcWho;
                    _out pushBack ["idc", _x get "key", format ["%1 · %2", [_who, "Inconnu"] select (_who isEqualTo ""), ([_x get "verdict"] call _idcVerdict) select 0],
                        format ["Fiche contrôle ID · %1 · %2%3", _x get "time", _x get "grid", ["", format [" · reçue de %1", _x get "from"]] select ((_x get "from") isNotEqualTo "")], 0];
                } forEach ((call _idcs) select [0, 30]);
                {
                    _x params ["", "_hh", "_who", "", "_status", "_conf", "_ref", "_grid", "", "_key"];
                    _out pushBack ["seek", _key, format ["%1 · %2", _who, _status], format ["Fiche SEEK · %1 · %2%3", _hh, _grid, ["", format [" · %1", _ref]] select (_ref isNotEqualTo "")], 0];
                } forEach (call _seekHist);
                _out
            };
            default { [] };
        }
    };
    case "payload": {
        _arg params [["_kind", ""], ["_ref", ""]];
        switch (_kind) do {
            case "pos": { [getPosASL player, [player] call comspec_atak_native_fnc_unitCallsign] };
            case "wp": { +(((missionNamespace getVariable ["COMSPEC_ATAK_Waypoints", createHashMap]) getOrDefault ["pts", []])) };
            case "plan": { +((profileNamespace getVariable ["COMSPEC_ATAK_PlanLib", []]) param [parseNumber _ref, []]) };
            case "seek": { +(((call _seekHist) select { (_x select 9) isEqualTo _ref }) param [0, []]) };
            case "sse": { +(((call _sheets) select { (_x select 0) isEqualTo _ref }) param [0, []]) };
            // Fiche CONTRÔLE ID : paires [clé, valeur] (passe par le réseau), recréée en HashMap à la réception.
            case "idc": { private _c = ["idcGet", _ref] call comspec_atak_native_fnc_btItems; [toArray _c, []] select ((count _c) isEqualTo 0) };
            case "note": {
                private _n = ((profileNamespace getVariable ["COMSPEC_ATAK_Notes", []]) select { _x isEqualType [] && {(_x param [0, ""]) isEqualTo _ref} }) param [0, []];
                [[], [_n select 1, (_n select 2) select [0, 4000]]] select ((count _n) >= 3)
            };
            default { [] };
        }
    };
    case "apply": {
        _arg params [["_id", ""], ["_kind", ""], ["_title", ""], ["_data", []], ["_from", ""]];
        private _sum = switch (_kind) do {
            case "pos": {
                _data params [["_p", []], ["_cs", _from]];
                if ((count _p) < 2) exitWith { "" };
                private _m = createMarkerLocal [format ["COMSPEC_BT_POS_%1", _id], _p];
                _m setMarkerTypeLocal "mil_dot";
                _m setMarkerColorLocal "ColorBLUFOR";
                _m setMarkerTextLocal format ["BT %1", _cs];
                [{ deleteMarkerLocal _this; }, _m, 600] call comspec_atak_native_fnc_waitAndExec;
                format ["position de %1 · %2 (repère 10 min sur la carte)", _cs, [_p, 8] call comspec_atak_native_fnc_gridRef]
            };
            case "wp": {
                if ((count _data) isEqualTo 0) exitWith { "" };
                ["receive", [_data, _from, false]] call comspec_atak_native_fnc_wpAction;
                format ["itinéraire de %1 étapes (app Points de passage)", count _data]
            };
            case "plan": {
                _data params [["_n", "Plan"], ["_w", ""], ["_its", []], ["_d", ""]];
                if !(_its isEqualType []) exitWith { "" };
                private _l = +(profileNamespace getVariable ["COMSPEC_ATAK_PlanLib", []]);
                _l pushBack [format ["%1 (de %2)", _n, _from], _w, _its, [_d, call _clock] select (_d isEqualTo "")];
                profileNamespace setVariable ["COMSPEC_ATAK_PlanLib", _l select [((count _l) - 30) max 0]];
                [] call comspec_atak_native_fnc_saveProfile;
                format ["plan « %1 » ajouté à la bibliothèque de l'app Plan", _n]
            };
            case "note": {
                _data params [["_t", ""], ["_b", ""]];
                if !(_t isEqualType "" && {_b isEqualType ""}) exitWith { "" };
                ["receive", [_t, _b, _from]] call comspec_atak_native_fnc_notesAction
            };
            case "seek": {
                if ((count _data) < 10) exitWith { "" };
                // Ajouté au journal de mon terminal SEEK, marqué comme reçu.
                private _e = +_data;
                _e set [0, diag_tickTime];
                _e set [3, format ["%1reçu de %2", ["", (_e select 3) + " · "] select ((_e select 3) isNotEqualTo ""), _from]];
                private _h = missionNamespace getVariable ["COMSPEC_SeekQueryHistory", []];
                if !(_h isEqualType []) then { _h = []; };
                _h = [_e] + (_h select { !((_x isEqualType []) && {(_x param [9, ""]) isEqualTo (_e select 9)}) });
                missionNamespace setVariable ["COMSPEC_SeekQueryHistory", _h select [0, 40]];
                format ["fiche SEEK de %1 (journal du terminal SEEK)", _e select 2]
            };
            case "idc": {
                if !(_data isEqualType []) exitWith { "" };
                private _c = createHashMapFromArray (_data select { _x isEqualType [] && {(count _x) isEqualTo 2} && {(_x select 0) isEqualType ""} });
                _c set ["from", _from];
                _c set ["at", call _clock];
                private _k = ["idcStore", _c] call comspec_atak_native_fnc_btItems;
                if (_k isEqualTo "") exitWith { "" };
                private _who = [_c] call _idcWho;
                format ["fiche contrôle ID de %1 (%2), vue dédiée dans l'app Bluetooth", [_who, "inconnu"] select (_who isEqualTo ""), ([_c getOrDefault ["verdict", ""]] call _idcVerdict) select 0]
            };
            case "sse": {
                if ((count _data) < 6) exitWith { "" };
                private _e = +_data;
                _e set [6, _from];
                private _s = missionNamespace getVariable ["COMSPEC_SSE_Sheets", []];
                if !(_s isEqualType []) then { _s = []; };
                _s = (_s select { !((_x isEqualType []) && {(_x param [0, ""]) isEqualTo (_e select 0)}) }) + [_e];
                missionNamespace setVariable ["COMSPEC_SSE_Sheets", _s select [((count _s) - 40) max 0]];
                format ["fiche SSE de %1", _e select 1]
            };
            default { "" };
        };
        if (_sum isNotEqualTo "") then {
            private _in = missionNamespace getVariable ["COMSPEC_ATAK_BtInbox", []];
            _in pushBack [_id, _kind, _title, _from, call _clock, _data];
            while { (count _in) > 30 } do { _in deleteAt 0; };
            missionNamespace setVariable ["COMSPEC_ATAK_BtInbox", _in];
        };
        _sum
    };
    case "sheetLines": {
        _arg params [["_kind", ""], ["_data", []]];
        switch (_kind) do {
            case "sse": {
                _data params ["", "", ["_lines", []], ["_by", ""], ["_hh", ""], ["_grid", ""]];
                _lines + [["Opérateur", format ["%1 · %2 · %3", _by, _hh, _grid]]]
            };
            case "seek": {
                _data params ["", ["_hh", ""], ["_who", ""], ["_alias", ""], ["_status", ""], ["_conf", 0], ["_ref", ""], ["_grid", ""], "", "", ["_n", 1]];
                [["Sujet", _who], ["Alias", _alias], ["Verdict", _status], ["Confiance", format ["%1 %2", _conf, "%"]], ["Dossier", _ref], ["Grille", _grid], ["Heure", _hh], ["Interrogations", str _n]] select { (_x select 1) isNotEqualTo "" }
            };
            case "plan": {
                _data params [["_n", ""], ["_w", ""], ["_its", []]];
                [["Plan", _n], ["Carte", _w], ["Éléments", str (count _its)]]
            };
            case "wp": { _data apply { [_x param [1, "WP"], [_x param [0, [0, 0, 0]], 8] call comspec_atak_native_fnc_gridRef] } };
            case "pos": { [["Position", [_data param [0, [0, 0, 0]], 8] call comspec_atak_native_fnc_gridRef], ["De", _data param [1, ""]]] };
            case "note": { [["Titre", _data param [0, ""]], ["Texte", (_data param [1, ""]) select [0, 300]]] };
            case "idc": {
                private _c = createHashMapFromArray (_data select { _x isEqualType [] && {(count _x) isEqualTo 2} });
                [["Personne", [_c] call _idcWho], ["Verdict", ([_c getOrDefault ["verdict", ""]] call _idcVerdict) select 0],
                    ["Document", trim format ["%1 %2 %3", [_c getOrDefault ["docType", ""]] call _idcDocType, _c getOrDefault ["docCountry", ""], _c getOrDefault ["docNumber", ""]]],
                    ["Contrôleur", _c getOrDefault ["controller", ""]], ["Heure", _c getOrDefault ["time", ""]], ["Grille", _c getOrDefault ["grid", ""]]] select { (_x select 1) isNotEqualTo "" }
            };
            default { [] };
        }
    };
    case "idcStore": {
        private _in = switch (true) do {
            case (_arg isEqualType createHashMap): { _arg };
            case (_arg isEqualType []): { createHashMapFromArray (_arg select { _x isEqualType [] && {(count _x) isEqualTo 2} && {(_x select 0) isEqualType ""} }) };
            default { createHashMap };
        };
        if ((count _in) isEqualTo 0) exitWith { "" };
        private _str = { params ["_v"]; if (isNil "_v") exitWith { "" }; if (_v isEqualType "") then { trim _v } else { if (_v isEqualTypeAny [0, true]) then { str _v } else { "" } } };
        private _doc = _in getOrDefault ["doc", createHashMap];
        if !(_doc isEqualType createHashMap) then { _doc = createHashMap; };
        private _pick = { params ["_k", "_dk"]; private _v = [_in getOrDefault [_k, ""]] call _str; if (_v isEqualTo "") then { [_doc getOrDefault [_dk, ""]] call _str } else { _v } };
        private _one = { params ["_k"]; [_in getOrDefault [_k, ""]] call _str };
        private _pos = _in getOrDefault ["pos", []];
        if !(_pos isEqualType [] && {(count _pos) >= 2} && {((_pos select [0, 2]) findIf { !(_x isEqualType 0) }) < 0}) then { _pos = getPosASL player; };
        _pos = [_pos select 0, _pos select 1, [_pos param [2, 0], 0] select !((_pos param [2, 0]) isEqualType 0)];
        // Niveaux : HashMap du SSE (0..3 → état, verdict, constats) ou tableau [[niveau, état, verdict, texte]...].
        private _lv = [];
        private _raw = _in getOrDefault ["levels", []];
        private _txt = { params ["_ln"]; (if (_ln isEqualType []) then { (_ln select { _x isEqualType "" }) joinString " " } else { [_ln] call _str }) select [0, 400] };
        private _lvName = { params ["_n"]; if (_n isEqualType 0) then { format ["N%1", _n] } else { toUpper ([_n] call _str) } };
        if (_raw isEqualType createHashMap) then {
            {
                private _n = [_x] call _lvName;
                if (_y isEqualType createHashMap && {_n in ["N0", "N1", "N2", "N3"]}) then {
                    _lv pushBack [_n, [_y getOrDefault ["state", ""]] call _str, [_y getOrDefault ["verdict", ""]] call _str, [_y getOrDefault ["lines", []]] call _txt];
                };
            } forEach _raw;
        } else {
            if (_raw isEqualType []) then {
                {
                    if (_x isEqualType [] && {(count _x) >= 2}) then {
                        private _n = [_x select 0] call _lvName;
                        if (_n in ["N0", "N1", "N2", "N3"]) then { _lv pushBack [_n, [_x select 1] call _str, [_x param [2, ""]] call _str, [_x param [3, ""]] call _txt]; };
                    };
                } forEach _raw;
            };
        };
        _lv sort true;
        private _key = ["key"] call _one;
        if (_key isEqualTo "") then { _key = ["idem"] call _one; };
        if (_key isEqualTo "") then { _key = format ["idc-%1-%2", getPlayerUID player, round (diag_tickTime * 1000)]; };
        _key = _key select [0, 80];
        private _time = ["time"] call _one;
        if (_time isEqualTo "") then { _time = ["clock"] call _one; };
        private _ctrl = ["controller"] call _one;
        private _grid = ["grid"] call _one;
        private _date = ["date"] call _one;
        // Date du SSE en tableau [année, mois, jour] : gardée en texte AAAA-MM-JJ.
        private _dRaw = _in getOrDefault ["date", ""];
        if (_dRaw isEqualType [] && {(count _dRaw) >= 3}) then {
            _dRaw params ["_yy", "_mm", "_dd"];
            _date = format ["%1-%2-%3", _yy, [str _mm, "0" + str _mm] select (_mm < 10), [str _dd, "0" + str _dd] select (_dd < 10)];
        };
        private _c = createHashMapFromArray [
            ["key", _key], ["ref", (["ref"] call _one) select [0, 40]], ["verdict", toLower (["verdict"] call _one)],
            ["docType", ["docType", "type"] call _pick], ["docCountry", toUpper (["docCountry", "country"] call _pick)], ["docNumber", ["docNumber", "number"] call _pick],
            ["last", (["last", "last"] call _pick) select [0, 60]], ["first", (["first", "first"] call _pick) select [0, 60]], ["dob", ["dob", "dob"] call _pick],
            ["expires", ["expires", "expires"] call _pick], ["photo", ["photo"] call _one],
            ["controller", [_ctrl, name player] select (_ctrl isEqualTo "")],
            ["time", [_time, call _clock] select (_time isEqualTo "")], ["date", _date],
            ["grid", [_grid, [_pos, 8] call comspec_atak_native_fnc_gridRef] select (_grid isEqualTo "")],
            ["pos", _pos], ["case", ["case"] call _one], ["levels", _lv],
            ["from", ["from"] call _one], ["at", ["at"] call _one]
        ];
        private _l = (call _idcs) select { (_x get "key") isNotEqualTo _key };
        _l = [_c] + _l;
        missionNamespace setVariable ["COMSPEC_ATAK_IdChecks", _l select [0, 30]];
        _key
    };
    case "idcGet": { ((call _idcs) select { (_x get "key") isEqualTo _arg }) param [0, createHashMap] };
    case "idcRows": {
        private _c = ["idcGet", _arg] call comspec_atak_native_fnc_btItems;
        private _icon = "\z\comspec_atak_native\addons\main\data\app_sse.paa";
        if ((count _c) isEqualTo 0) exitWith { [["empty", _icon, "Fiche introuvable", "Elle a peut-être été remplacée par une plus récente."]] };
        private _esc = { params ["_t"]; { _t = [_t, _x select 0, _x select 1] call CBA_fnc_replace; } forEach [["&", "&amp;"], ["<", "&lt;"], [">", "&gt;"]]; _t };
        ([_c get "verdict"] call _idcVerdict) params ["_vl", "_vh"];
        private _who = [_c] call _idcWho;
        private _rgb = {
            params ["_h"];
            private _d = "0123456789abcdef";
            private _s = toLower (_h select [1]);
            private _v = [];
            for "_i" from 0 to 4 step 2 do { _v pushBack ((((_d find (_s select [_i, 1])) max 0) * 16 + ((_d find (_s select [_i + 1, 1])) max 0)) / 255); };
            _v + [1]
        };
        private _rows = [
            ["strip", format ["<t font='COMSPEC_BarlowBold' color='#35d6a1'>%1</t>  <t font='COMSPEC_BarlowBold' color='#e3ebe7'>%2</t>", "FICHE CONTRÔLE ID" call comspec_atak_native_fnc_tr, [_c get "ref"] call _esc], ""],
            ["banner", format ["<t font='COMSPEC_BarlowBold' size='1.2' color='#0b0d0c'>%1</t>", toUpper (_vl call comspec_atak_native_fnc_tr)], [_vh] call _rgb]
        ];
        private _docTxt = format ["<t font='COMSPEC_BarlowBold' size='1.1'>%1</t><br/><t size='0.85' color='#b4c1bb'>%2 · %3 · n° %4</t>%5%6",
            [[_who, "Identité non lue"] select (_who isEqualTo "")] call _esc, [_c get "docType"] call _idcDocType, [_c get "docCountry"] call _esc, [_c get "docNumber"] call _esc,
            ["", format ["<br/><t size='0.8' color='#8a9a93'>Né(e) le %1</t>", [_c get "dob"] call _esc]] select ((_c get "dob") isNotEqualTo ""),
            ["", format ["<br/><t size='0.8' color='#8a9a93'>Expire le %1</t>", [_c get "expires"] call _esc]] select ((_c get "expires") isNotEqualTo "")];
        if ((_c get "photo") isNotEqualTo "") then { _rows pushBack ["hero", _c get "photo", _docTxt]; } else {
            _rows pushBack ["person", _icon, _docTxt, [], [_vh] call _rgb];
        };
        _rows pushBack ["section", "Niveaux de contrôle", format ["%1 niveau(x) passé(s)", count (_c get "levels")]];
        if ((count (_c get "levels")) isEqualTo 0) then { _rows pushBack ["text", "<t color='#8a9a93'>Aucun niveau détaillé sur cette fiche.</t>"]; };
        {
            _x params ["_n", "_st", "_v", "_tx"];
            private _lvl = createHashMapFromArray [["ok", "ok"], ["doubt", "warn"], ["fail", "ko"], ["alert", "ko"], ["na", "off"]] getOrDefault [toLower _st, "todo"];
            _rows pushBack ["check", _lvl, [_n] call _idcLevel, [([_v] call _idcVerdict) select 0, "Non fait"] select ((toLower _st) isEqualTo "na"), [_tx] call _esc];
        } forEach (_c get "levels");
        _rows pushBack ["section", "Contrôle", ""];
        {
            _x params ["_lab", "_val"];
            if (_val isNotEqualTo "") then { _rows pushBack ["info", _lab, [_val] call _esc]; };
        } forEach [["Contrôleur", _c get "controller"], ["Heure", trim format ["%1 %2", _c get "date", _c get "time"]], ["Position", _c get "grid"],
            ["Dossier SSE", _c get "case"], ["Référence Athena", _c get "ref"], ["Reçue de", _c get "from"], ["Reçue à", _c get "at"]];
        _rows
    };
};
