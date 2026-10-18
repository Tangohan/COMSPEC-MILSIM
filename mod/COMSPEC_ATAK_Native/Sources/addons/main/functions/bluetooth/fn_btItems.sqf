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
    Params : [action, argument]
      ["list", onglet]          onglet "pho" | "mp3" | "data" | "sheet" → lignes
      ["payload", [kind, ref]]  données envoyées (petites sortes) ; [] si l'élément n'existe plus
      ["apply", [id, kind, titre, données, expéditeur]] → range chez moi ce qui vient d'arriver, renvoie un résumé
      ["sheetLines", [kind, données]] → [[libellé, valeur]...] pour l'affichage d'une fiche reçue
    Reçus : missionNamespace COMSPEC_ATAK_BtInbox = [[id, kind, titre, expéditeur, heure, données]...] (30 derniers).
*/
params [["_act", "list"], ["_arg", ""]];
private _clock = { [dayTime, "HH:MM"] call BIS_fnc_timeToString };
private _seekHist = {
    private _h = missionNamespace getVariable ["COMSPEC_SeekQueryHistory", []];
    if !(_h isEqualType []) then { _h = []; };
    _h select { (_x isEqualType []) && {(count _x) >= 10} }
};
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
                _out
            };
            case "sheet": {
                private _out = (call _sheets) apply {
                    _x params ["_k", "_who", "_lines", "_by", "_hh", "_grid", ["_from", ""]];
                    ["sse", _k, _who, format ["Fiche SSE · %1 · %2%3", _hh, _grid, ["", format [" · reçue de %1", _from]] select (_from isNotEqualTo "")], 0]
                };
                reverse _out;
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
                [{ deleteMarkerLocal _this; }, _m, 600] call CBA_fnc_waitAndExecute;
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
                saveProfileNamespace;
                format ["plan « %1 » ajouté à la bibliothèque de l'app Plan", _n]
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
            default { [] };
        }
    };
};
