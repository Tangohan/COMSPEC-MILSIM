/*
    Points de passage : itinéraire en étapes nommées et typées, partagé en direct au groupe (ou au camp), navigation
    étape par étape.
    État : missionNamespace COMSPEC_ATAK_Waypoints (HashMap) : pts [[pos, nom, type]...], idx (étape active), nav (bool),
    from (auteur de la dernière version reçue). Une étape d'une ancienne version [pos, nom] vaut le type "WP".
    Fiche de pose sur la carte (fn_wpCard) : COMSPEC_ATAK_State wpAnchor (point choisi d'un clic droit), wpSel (étape
    modifiée, -1 : nouvelle étape), wpType (type des prochaines étapes), wpName (nom saisi, vide : nom automatique).
    Profil : COMSPEC_ATAK_WpShare (0 moi seul, 1 groupe, 2 camp ; défaut groupe), COMSPEC_ATAK_WpSeries (pose à la suite,
    défaut oui).
    Params : [action, argument]
      "types" -> [[clé, libellé, classe CfgMarkers, couleur, préfixe du nom]...]   "type" clé -> la ligne du type
      "icon" clé -> icône du type     "autoName" clé -> prochain nom automatique (CP 3, RP 1, SP…)
      "open" pos (fiche au point, menu du clic droit) | "anchor" pos (clic droit en mode étapes) | "edit" index
      "place" (VALIDER : pose au point choisi) | "add" pos (pose tout de suite, clic gauche) | "new" (fiche d'une
      nouvelle étape) | "close"
      "rename" [index, nom] | "retype" [index, clé] | "move" index (au point choisi) | "up" / "down" index | "reverse"
      "del" index | "clear" | "nav" (démarre / arrête) | "next" | "prev" | "goto" index | "gps" (itinéraire routier)
      "setType" clé | "setShare" n | "series" (bascule la pose à la suite) | "shareName" n -> libellé du partage
      "sync" (envoi groupé de la version courante, une seconde après la dernière modification) | "syncNow"
      "push" (envoie et lance la navigation chez tous) | "receive" [pts, auteur, lancer, en direct] (événement) | "tick"
*/
params [["_act", ""], ["_arg", []]];
private _w = missionNamespace getVariable ["COMSPEC_ATAK_Waypoints", createHashMap];
if ((count _w) isEqualTo 0) then { _w = createHashMapFromArray [["pts", []], ["idx", 0], ["nav", false], ["from", ""]]; missionNamespace setVariable ["COMSPEC_ATAK_Waypoints", _w]; };
private _pts = _w get "pts";
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
private _rerender = { [{ private _p = (uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]; if (_p in ["WAYPOINTS", "MAP"]) then { [_p] call comspec_atak_native_fnc_pageRender; }; }] call CBA_fnc_execNextFrame; };
// Modification de l'itinéraire : redessin, puis envoi en direct si l'itinéraire est partagé.
private _changed = { call _rerender; if ((profileNamespace getVariable ["COMSPEC_ATAK_WpShare", 1]) > 0) then { ["sync"] call comspec_atak_native_fnc_wpAction; }; };
private _types = [
    ["WP", "Étape", "mil_flag", [0.95, 0.75, 0.18, 1], "WP"],
    ["SP", "Départ", "mil_start", [0.36, 0.85, 0.42, 1], "SP"],
    ["CP", "Point de contrôle", "mil_triangle", [0.30, 0.80, 0.95, 1], "CP"],
    ["RP", "Ralliement", "mil_join", [1, 0.55, 0.15, 1], "RP"],
    ["OBJ", "Objectif", "mil_objective", [0.95, 0.30, 0.25, 1], "OBJ"],
    ["LZ", "Poser / LZ", "mil_pickup", [0.75, 0.55, 1, 1], "LZ"],
    ["END", "Arrivée", "mil_end", [0.92, 0.94, 0.92, 1], "FIN"]
];
private _typeRow = { params ["_k"]; _types select ((_types findIf { (_x select 0) isEqualTo _k }) max 0) };
private _autoName = {
    params ["_k"];
    private _pre = ([_k] call _typeRow) select 4;
    // Arrivée : FIN en français, END sinon (les autres codes sont les mêmes).
    if (_pre isEqualTo "FIN" && {(missionNamespace getVariable ["COMSPEC_ATAK_Lang", "en"]) isNotEqualTo "fr"}) then { _pre = "END"; };
    // Départ et arrivée : un seul en général, sans numéro ; les autres numérotés par type.
    private _n = { (_x param [2, "WP"]) isEqualTo _k } count _pts;
    if (_k in ["SP", "END"] && {_n isEqualTo 0}) exitWith { _pre };
    format ["%1 %2", _pre, _n + 1]
};
private _clean = { params ["_t"]; ((trim _t) regexReplace ["[<>""]", ""]) select [0, 24] };
switch (_act) do {
    case "types": { _types };
    case "type": { [_arg] call _typeRow };
    case "icon": {
        private _cache = uiNamespace getVariable ["COMSPEC_ATAK_WpIcons", createHashMap];
        private _i = _cache getOrDefault [_arg, ""];
        if (_i isEqualTo "") then {
            _i = getText (configFile >> "CfgMarkers" >> (([_arg] call _typeRow) select 2) >> "icon");
            _cache set [_arg, _i];
            uiNamespace setVariable ["COMSPEC_ATAK_WpIcons", _cache];
        };
        _i
    };
    case "autoName": { [_arg] call _autoName };
    case "shareName": { (["Moi seul", "Groupe", "Camp"] param [_arg, "Groupe"]) call comspec_atak_native_fnc_tr };
    case "open": {
        if ((count _arg) < 2) exitWith { false };
        if ((_s getOrDefault ["wpSel", -1]) >= 0) then { _s set ["wpName", ""]; };
        _s set ["wpSel", -1];
        _s set ["wpAnchor", [_arg select 0, _arg select 1, 0]];
        _s set ["mapToolsOpen", false];
        if ((_s getOrDefault ["mapMode", "SELECT"]) isNotEqualTo "WP") then { ["WP"] call comspec_atak_native_fnc_mapToolSet; };
        _s set ["wpAnchor", [_arg select 0, _arg select 1, 0]];
        call _rerender;
        true
    };
    case "anchor": {
        if ((count _arg) < 2) exitWith { false };
        _s set ["wpAnchor", [_arg select 0, _arg select 1, 0]];
        playSound "ClickSoft";
        call _rerender;
        true
    };
    case "edit": {
        if !(_arg isEqualType 0 && {_arg >= 0} && {_arg < count _pts}) exitWith { false };
        if ((_s getOrDefault ["mapMode", "SELECT"]) isNotEqualTo "WP") then { ["WP"] call comspec_atak_native_fnc_mapToolSet; };
        _s set ["wpSel", _arg];
        _s set ["wpName", (_pts select _arg) select 1];
        _s set ["wpAnchor", []];
        _s set ["mapToolsOpen", false];
        playSound "ClickSoft";
        call _rerender;
        true
    };
    case "new": {
        _s set ["wpSel", -1];
        _s set ["wpName", ""];
        playSound "ClickSoft";
        call _rerender;
        true
    };
    case "close": {
        _s set ["wpSel", -1];
        _s set ["wpName", ""];
        _s set ["wpAnchor", []];
        ["SELECT"] call comspec_atak_native_fnc_mapToolSet;
        playSound "ClickSoft";
        call _rerender;
        true
    };
    case "setType": {
        _s set ["wpType", _arg];
        playSound "ClickSoft";
        call _rerender;
        true
    };
    case "setShare": {
        private _was = profileNamespace getVariable ["COMSPEC_ATAK_WpShare", 1];
        profileNamespace setVariable ["COMSPEC_ATAK_WpShare", _arg];
        playSound "ClickSoft";
        call _rerender;
        // Partage ouvert (ou élargi) : les autres reçoivent tout de suite l'itinéraire en cours.
        if (_arg > _was && {(count _pts) > 0}) then { ["sync"] call comspec_atak_native_fnc_wpAction; };
        true
    };
    case "series": {
        profileNamespace setVariable ["COMSPEC_ATAK_WpSeries", !(profileNamespace getVariable ["COMSPEC_ATAK_WpSeries", true])];
        playSound "ClickSoft";
        call _rerender;
        true
    };
    case "add": {
        if ((count _arg) < 2) exitWith { -1 };
        // Clic gauche pendant la modification d'une étape : on revient à la pose d'une nouvelle étape.
        if ((_s getOrDefault ["wpSel", -1]) >= 0) then { _s set ["wpSel", -1]; _s set ["wpName", ""]; };
        if ((count _pts) >= 30) exitWith { ["WARNING", "30 étapes au maximum", 3, 20] call comspec_atak_native_fnc_notify; -1 };
        private _type = _s getOrDefault ["wpType", "WP"];
        if ((_types findIf { (_x select 0) isEqualTo _type }) < 0) then { _type = "WP"; };
        private _name = [_s getOrDefault ["wpName", ""]] call _clean;
        if (_name isEqualTo "") then { _name = [_type] call _autoName; };
        _pts pushBack [[_arg select 0, _arg select 1, 0], _name, _type];
        // Le nom saisi vaut pour une étape : la suivante reprend un nom automatique.
        _s set ["wpName", ""];
        private _msg = format ["Étape %1 : %2", count _pts, _name];
        if !(([_msg, 2] call comspec_atak_native_fnc_toast)) then { ["INFO", _msg, 2, 10] call comspec_atak_native_fnc_notify; };
        call _changed;
        (count _pts) - 1
    };
    case "place": {
        private _pos = _s getOrDefault ["wpAnchor", []];
        if ((count _pos) < 2) exitWith {
            ["INFO", "Clic droit sur la carte pour choisir le point, puis VALIDER (ou clic gauche : posée tout de suite)", 4, 20] call comspec_atak_native_fnc_notify;
            false
        };
        if ((["add", _pos] call comspec_atak_native_fnc_wpAction) < 0) exitWith { false };
        _s set ["wpAnchor", []];
        if !(profileNamespace getVariable ["COMSPEC_ATAK_WpSeries", true]) then { ["SELECT"] call comspec_atak_native_fnc_mapToolSet; };
        true
    };
    case "rename": {
        _arg params [["_i", -1], ["_n", ""]];
        if (_i < 0 || {_i >= count _pts}) exitWith { false };
        _n = [_n] call _clean;
        if (_n isEqualTo "" || {_n isEqualTo ((_pts select _i) select 1)}) exitWith { false };
        (_pts select _i) set [1, _n];
        call _changed;
        true
    };
    case "retype": {
        _arg params [["_i", -1], ["_k", "WP"]];
        if (_i < 0 || {_i >= count _pts}) exitWith { false };
        private _p = _pts select _i;
        private _old = _p param [2, "WP"];
        if (_old isEqualTo _k) exitWith { false };
        // Nom automatique de l'ancien type (« WP 2 ») : remplacé par celui du nouveau ; un nom saisi est gardé.
        private _oldPre = ([_old] call _typeRow) select 4;
        if (((_p select 1) find _oldPre) isEqualTo 0 && {(count (_p select 1)) <= (count _oldPre) + 3}) then { _p set [1, [_k] call _autoName]; };
        _p set [2, _k];
        _s set ["wpType", _k];
        playSound "ClickSoft";
        call _changed;
        true
    };
    case "move": {
        private _pos = _s getOrDefault ["wpAnchor", []];
        if (_arg < 0 || {_arg >= count _pts} || {(count _pos) < 2}) exitWith { false };
        (_pts select _arg) set [0, [_pos select 0, _pos select 1, 0]];
        _s set ["wpAnchor", []];
        ["INFO", format ["%1 déplacée", (_pts select _arg) select 1], 2, 10] call comspec_atak_native_fnc_notify;
        call _changed;
        true
    };
    case "up";
    case "down": {
        private _j = _arg + ([-1, 1] select (_act isEqualTo "down"));
        if (_arg < 0 || {_arg >= count _pts} || {_j < 0} || {_j >= count _pts}) exitWith { false };
        private _a = _pts select _arg;
        _pts set [_arg, _pts select _j];
        _pts set [_j, _a];
        if ((_s getOrDefault ["wpSel", -1]) isEqualTo _arg) then { _s set ["wpSel", _j]; };
        call _changed;
        true
    };
    case "reverse": {
        if ((count _pts) < 2) exitWith { false };
        reverse _pts;
        _w set ["idx", 0];
        _s set ["wpSel", -1];
        ["INFO", "Itinéraire inversé", 2, 10] call comspec_atak_native_fnc_notify;
        call _changed;
        true
    };
    case "del": {
        if !(_arg isEqualType 0 && {_arg >= 0} && {_arg < count _pts}) exitWith { false };
        _pts deleteAt _arg;
        private _i = _w get "idx";
        if (_arg < _i) then { _i = _i - 1; };
        _w set ["idx", (_i min ((count _pts) - 1)) max 0];
        if ((count _pts) isEqualTo 0) then { _w set ["nav", false]; };
        // Fiche ouverte sur l'étape supprimée : retour à la pose d'une nouvelle étape.
        private _sel = _s getOrDefault ["wpSel", -1];
        _s set ["wpSel", [[_sel, _sel - 1] select (_arg < _sel), -1] select (_sel isEqualTo _arg)];
        if (_sel isEqualTo _arg) then { _s set ["wpName", ""]; };
        call _changed;
        true
    };
    case "clear": {
        _w set ["pts", []]; _w set ["idx", 0]; _w set ["nav", false]; _w set ["from", ""];
        _s set ["wpSel", -1];
        _s set ["wpName", ""];
        call _changed;
        true
    };
    case "nav": {
        if ((count _pts) isEqualTo 0) exitWith {};
        _w set ["nav", !(_w get "nav")];
        if (_w get "nav") then { ["SUCCESS", format ["Navigation : %1 (étape %2 / %3)", (_pts select (_w get "idx")) select 1, (_w get "idx") + 1, count _pts], 3, 20] call comspec_atak_native_fnc_notify; };
        call _rerender;
    };
    case "next": { _w set ["idx", ((_w get "idx") + 1) min (((count _pts) - 1) max 0)]; call _rerender; };
    case "prev": { _w set ["idx", ((_w get "idx") - 1) max 0]; call _rerender; };
    case "goto": {
        if !(_arg isEqualType 0 && {_arg >= 0} && {_arg < count _pts}) exitWith {};
        _w set ["idx", _arg]; _w set ["nav", true];
        ["SUCCESS", format ["Cap sur %1", (_pts select _arg) select 1], 2, 10] call comspec_atak_native_fnc_notify;
        call _rerender;
    };
    case "gps": {
        if ((count _pts) isEqualTo 0) exitWith {};
        (_pts select (_w get "idx")) params ["_p", "_n"];
        [_p, _n] spawn comspec_atak_native_fnc_routeCompute;
        ["MAP"] call comspec_atak_native_fnc_navigate;
    };
    case "sync": {
        // Plusieurs étapes posées d'affilée partent en un seul envoi.
        private _tok = (missionNamespace getVariable ["COMSPEC_ATAK_WpSyncTok", 0]) + 1;
        missionNamespace setVariable ["COMSPEC_ATAK_WpSyncTok", _tok];
        [{ if (_this isEqualTo (missionNamespace getVariable ["COMSPEC_ATAK_WpSyncTok", 0])) then { ["syncNow"] call comspec_atak_native_fnc_wpAction; }; }, _tok, 1] call CBA_fnc_waitAndExecute;
    };
    case "syncNow";
    case "push": {
        private _push = _act isEqualTo "push";
        private _share = profileNamespace getVariable ["COMSPEC_ATAK_WpShare", 1];
        if (_push && {(count _pts) isEqualTo 0}) exitWith {};
        if (!_push && {_share isEqualTo 0}) exitWith {};
        private _to = if (_share >= 2) then {
            allPlayers select { _x isNotEqualTo player && {side group _x isEqualTo side group player} }
        } else {
            (units group player) select { isPlayer _x && {_x isNotEqualTo player} }
        };
        private _who = [player] call comspec_atak_native_fnc_unitCallsign;
        _w set ["from", ""];
        [{
            params ["_pts", "_to", "_who", "_push"];
            if ((count _to) > 0) then { ["comspec_atak_native_waypoints", [_pts, _who, _push, !_push], _to] call CBA_fnc_targetEvent; };
            ["comspec_atak_native_wpStore", [netId group player, _pts, _who]] call CBA_fnc_serverEvent;
        }, [+_pts, _to, _who, _push], "Itinéraire", 2] call comspec_atak_native_fnc_netSend;
        if (_push) then {
            _w set ["nav", true];
            if ((count _to) isEqualTo 0) exitWith { ["WARNING", "Personne d'autre à qui l'envoyer", 3, 20] call comspec_atak_native_fnc_notify; };
            ["SUCCESS", format ["Itinéraire envoyé à %1 joueur(s), navigation lancée chez eux", count _to], 3, 20] call comspec_atak_native_fnc_notify;
        };
    };
    case "receive": {
        _arg params ["_in", "_who", ["_start", true], ["_live", false]];
        if !(_in isEqualType []) exitWith {};
        private _hadPts = (count _pts) > 0;
        _w set ["pts", _in]; _w set ["from", _who];
        if (_live) then {
            // Mise à jour en direct par un équipier : ma navigation continue, sans vibreur.
            _w set ["idx", ((_w get "idx") min ((count _in) - 1)) max 0];
            if ((count _in) isEqualTo 0) then { _w set ["nav", false]; };
            private _sel = _s getOrDefault ["wpSel", -1];
            if (_sel >= count _in) then { _s set ["wpSel", -1]; };
            private _msg = [format ["Itinéraire mis à jour par %1 : %2 étape(s)", _who, count _in], format ["Itinéraire effacé par %1", _who]] select ((count _in) isEqualTo 0);
            if (!_hadPts && {(count _in) > 0}) then {
                ["INFO", format ["Itinéraire partagé par %1 : %2 étape(s)", _who, count _in], 4, 30] call comspec_atak_native_fnc_notify;
            } else {
                if !(([_msg, 2] call comspec_atak_native_fnc_toast)) then { ["INFO", _msg, 2, 10] call comspec_atak_native_fnc_notify; };
            };
        } else {
            _w set ["idx", 0]; _w set ["nav", _start && {(count _in) > 0}];
            ["WARNING", format ["Itinéraire reçu de %1 : %2 étape(s)%3", _who, count _in, ["", ", navigation lancée"] select (_start && {(count _in) > 0})], 6, 60] call comspec_atak_native_fnc_notify;
            [] call comspec_atak_native_fnc_vibrate;
        };
        call _rerender;
    };
    case "tick": {
        if !(_w get "nav") exitWith {};
        if ((count _pts) isEqualTo 0) exitWith { _w set ["nav", false]; };
        private _i = (_w get "idx") min ((count _pts) - 1);
        (_pts select _i) params ["_p", "_n"];
        if ((player distance2D _p) < 25) then {
            if (_i >= ((count _pts) - 1)) then {
                _w set ["nav", false];
                ["SUCCESS", format ["Dernière étape atteinte (%1)", _n], 5, 40] call comspec_atak_native_fnc_notify;
            } else {
                _w set ["idx", _i + 1];
                ["INFO", format ["%1 atteinte, cap sur %2", _n, (_pts select (_i + 1)) select 1], 4, 30] call comspec_atak_native_fnc_notify;
            };
            [] call comspec_atak_native_fnc_vibrate;
            call _rerender;
        };
    };
};
