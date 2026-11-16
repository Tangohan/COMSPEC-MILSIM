/*
    Reprise dans l'app JTAC (onglet 9/5-LINE) des 9-LINE et 5-LINE rédigés par le même JTAC sur le TOC web, la version
    mobile (page /9-line) ou en jeu, pour la carte et la communauté courantes.
    Lecture seule, sans nouvelle fonction DLL : GetCASForCallsign ["~jtac|indicatif|…", carte] (GET /api/cas, préfixe
    ~jtac| d'AirBriefService::authored) renvoie un tableau SQF [[ref, id, format, auteur, heure, [lignes], remarques,
    statut, destinataires, source ("web" | "jeu")]...], le plus récent d'abord. Un serveur qui ne connaît pas le préfixe
    répond une liste vide : rien n'est chargé (mode dégradé).
    Le brief choisi remplit la fiche du téléphone (9-LINE en saisie libre, ou 5-LINE) et les 9 lignes de la fiche CKIN,
    avec l'indicateur « importée du TOC » ; la fiche reste modifiable et part ensuite par l'envoi habituel. Le même brief
    (même id serveur) n'est jamais dupliqué : « METTRE À JOUR DEPUIS LE SERVEUR », avec un second appui demandé si la
    fiche a été modifiée depuis l'import.
    État : uiNamespace COMSPEC_ATAK_Jtac « toc » (liste, heure du relevé, relevé en cours), « tocImport » (brief chargé :
    id, ref, format, heure, source, lignes chargées), « tocConfirm » (id dont le remplacement attend un second appui).
    Params : [action, argument]
      "fetch"           relève la liste sur Athena (une requête à la fois, au clic)
      "load", id        charge le brief dans la fiche            "hide"   referme la liste
      "rows"            → lignes du formulaire de l'onglet 9/5-LINE (bouton, liste, indicateur)
*/
params [["_act", ""], ["_arg", ""]];
private _s = uiNamespace getVariable ["COMSPEC_ATAK_Jtac", createHashMap];
uiNamespace setVariable ["COMSPEC_ATAK_Jtac", _s];
private _toc = _s getOrDefault ["toc", createHashMap];
_s set ["toc", _toc];
private _render = { [{ if (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isEqualTo "JTAC") then { ["JTAC"] call comspec_atak_native_fnc_pageRender; }; }] call comspec_atak_native_fnc_nextFrame; };
private _srcName = { params ["_x0"]; ["TOC / MOBILE", "JEU"] select (_x0 isEqualTo "jeu") };
// Lignes de la fiche du téléphone pour un format : clés de saisie.
private _keysOf = { params ["_f"]; [["jL1", "jL2", "jL3", "jL4", "jL5", "jL6", "jL7", "jL8", "jL9"], ["jF1", "jF2", "jF3", "jF4", "jF5"]] select (_f isEqualTo "5") };

switch (_act) do {
    case "fetch": {
        if (_toc getOrDefault ["busy", false]) exitWith {};
        if !([] call comspec_atak_native_fnc_bridge) exitWith { ["WARNING", "Téléphone non relié à Athena : reprise du TOC impossible", 4, 20] call comspec_atak_native_fnc_notify; };
        if !(missionNamespace getVariable ["COMSPEC_AthenaReady", false]) exitWith { ["WARNING", "Athena pas encore prêt, réessayez dans un instant", 4, 20] call comspec_atak_native_fnc_notify; };
        if !(["online"] call comspec_atak_native_fnc_offline) exitWith { ["WARNING", "Hors réseau : reprise du TOC impossible", 4, 20] call comspec_atak_native_fnc_notify; };
        // Indicatifs sous lesquels j'ai pu rédiger : JTAC (profil), ATAK, Athena, nom, groupe (cellule JTAC).
        private _names = [];
        {
            private _n = _x;
            if !(_n isEqualType "") then { _n = str _n; };
            _n = trim ((_n splitString "|") joinString " ");
            if (_n isNotEqualTo "" && {!((toLower _n) in ["operateur", "pilote", "jtac"])}) then { _names pushBackUnique (_n select [0, 40]); };
        } forEach [["jtacCs"] call comspec_atak_native_fnc_airBrief, [player] call comspec_atak_native_fnc_unitCallsign,
            [[true] call comspec_overwatch_connect_fnc_getCallsign, ""] select (isNil "comspec_overwatch_connect_fnc_getCallsign"), name player, groupId group player];
        if ((count _names) isEqualTo 0) exitWith {};
        private _mapId = str (missionNamespace getVariable ["COMSPEC_MapId", missionNamespace getVariable ["comspec_overwatch_map_id", 1]]);
        _toc set ["busy", true];
        _toc set ["open", true];
        call _render;
        [format ["~jtac|%1", _names joinString "|"], _mapId] spawn {
            params ["_q", "_mapId"];
            private _r = ["COMSPECExtension", "GetCASForCallsign", [_q, _mapId]] call comspec_atak_native_fnc_extAwait;
            if (_r isEqualType []) then { _r = _r param [0, ""]; };
            private _list = [];
            private _ok = false;
            if (_r isEqualType "" && {(_r select [0, 3]) isEqualTo "OK|"}) then {
                private _p = trim (_r select [3]);
                _ok = true;
                if ((_p select [0, 2]) isEqualTo "[[") then {
                    private _arr = parseSimpleArray _p;
                    if (_arr isEqualType []) then {
                        {
                            if (_x isEqualType [] && {(count _x) >= 6}) then {
                                _x params [["_ref", ""], ["_aid", 0], ["_kind", "9line"], ["_from", ""], ["_at", ""], ["_lines", []], ["_rem", ""], ["_st", ""], ["_to", ""], ["_src", "web"]];
                                if (_aid isEqualType 0 && {_lines isEqualType []}) then {
                                    _list pushBack [_ref, _aid, ["9", "5"] select ((toLower _kind) isEqualTo "5line"), _from, _at, _lines apply { [str _x, _x] select (_x isEqualType "") }, _rem, toUpper _st, _to, _src];
                                };
                            };
                        } forEach _arr;
                    };
                };
            };
            isNil {
                private _t = (uiNamespace getVariable ["COMSPEC_ATAK_Jtac", createHashMap]) getOrDefault ["toc", createHashMap];
                _t set ["busy", false];
                _t set ["at", [dayTime, "HH:MM"] call BIS_fnc_timeToString];
                if (_ok) then { _t set ["list", _list]; };
                if (!_ok) then { ["WARNING", "Athena n'a pas répondu : réessayez", 4, 20] call comspec_atak_native_fnc_notify; };
                if (_ok && {(count _list) isEqualTo 0}) then { ["INFO", "Aucun 9-LINE ni 5-LINE de vous sur le TOC ou la version mobile (12 dernières heures)", 5, 20] call comspec_atak_native_fnc_notify; };
                if (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isEqualTo "JTAC") then { ["JTAC"] call comspec_atak_native_fnc_pageRender; };
            };
        };
    };
    case "hide": { _toc set ["open", false]; call _render; };
    case "load": {
        ["save"] call comspec_atak_native_fnc_jtacAction;
        private _b = (_toc getOrDefault ["list", []]) select { (_x select 1) isEqualTo _arg } param [0, []];
        if ((count _b) isEqualTo 0) exitWith {};
        _b params ["_ref", "_aid", "_fmt", "_from", "_at", "_lines", "_rem", "", "_to", "_src"];
        private _keys = [_fmt] call _keysOf;
        // Même brief déjà chargé et fiche modifiée depuis : remplacement au second appui seulement.
        private _imp = _s getOrDefault ["tocImport", []];
        private _stop = false;
        if ((_imp param [0, -1]) isEqualTo _aid && {(_s getOrDefault ["tocConfirm", -1]) isNotEqualTo _aid}) then {
            private _now = (_keys apply { trim (_s getOrDefault [_x, ""]) }) + [trim (_s getOrDefault [["jRem", ""] select (_fmt isEqualTo "5"), ""])];
            _stop = _now isNotEqualTo ((_imp param [5, []]) + [_imp param [6, ""]]);
        };
        if (_stop) exitWith {
            _s set ["tocConfirm", _aid];
            ["WARNING", "Votre fiche diffère de la version du serveur. Appuyez de nouveau pour la remplacer", 6, 30] call comspec_atak_native_fnc_notify;
            call _render;
        };
        _s set ["tocConfirm", -1];
        _s set ["fmt", _fmt];
        if (_fmt isEqualTo "9") then { _s set ["free", true]; };
        { _s set [_x, trim (_lines param [_forEachIndex, ""])]; } forEach _keys;
        if (_fmt isEqualTo "9") then { _s set ["jRem", trim _rem]; };
        // Destinataires du brief : indicatifs repris tels qu'affichés sur le web.
        if ((trim _to) isNotEqualTo "") then { _s set ["jDest", trim _to]; };
        // Fiche CKIN : les 9 lignes, l'appareil et les remarques (champs vides seulement, rien n'est écrasé).
        if (_fmt isEqualTo "9") then {
            private _h = ["state"] call comspec_atak_native_fnc_jtacCkin;
            private _put = { params ["_k", "_t"]; _t = trim _t; if (_k isNotEqualTo "" && {(trim (_h getOrDefault [_k, ""])) isEqualTo ""} && {!(_t in ["", "-"])}) then { _h set [_k, _t]; }; };
            { [_x, _lines param [_forEachIndex, ""]] call _put; } forEach ["ck1", "ck2", "ck3", "ck4", "ck5", "ck6", "", "ck8", "ck9"];
            private _l7 = trim (_lines param [6, ""]);
            private _code = (_l7 splitString " ") select { _x regexMatch "^[1-8][0-9]{3}$" };
            if (((toUpper _l7) find "LASER") >= 0) then { ["ck7", "LASER"] call _put; } else { ["ck7", toUpper _l7] call _put; };
            if ((count _code) > 0) then { ["ck7c", _code select 0] call _put; };
            ["ckRem", _rem] call _put;
            ["ckAc", (_to splitString ",") param [0, ""]] call _put;
            _s set ["ckClearing", true];
        };
        _s set ["tocImport", [_aid, _ref, _fmt, _at, _src, _keys apply { trim (_s getOrDefault [_x, ""]) }, [trim (_s getOrDefault ["jRem", ""]), ""] select (_fmt isEqualTo "5")]];
        _s set ["clearing", true];
        _s set ["tab", "BRIEF"];
        ["SUCCESS", format ["%1 de %2 (%3) chargé dans la fiche", ["9-LINE", "5-LINE"] select (_fmt isEqualTo "5"), _from, [_src] call _srcName], 4, 20] call comspec_atak_native_fnc_notify;
        [{
            private _j = uiNamespace getVariable ["COMSPEC_ATAK_Jtac", createHashMap];
            if (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isEqualTo "JTAC") then { ["JTAC"] call comspec_atak_native_fnc_pageRender; };
            _j set ["clearing", false];
            _j set ["ckClearing", false];
        }] call comspec_atak_native_fnc_nextFrame;
    };
    case "rows": {
        private _rows = [];
        private _busy = _toc getOrDefault ["busy", false];
        private _imp = _s getOrDefault ["tocImport", []];
        if ((count _imp) > 0) then {
            _imp params ["", "", "_ifmt", "_iat", "_isrc"];
            _rows pushBack ["strip", format ["<t font='EtelkaMonospaceProBold' color='#1a1206'>%1</t>", format ["IMPORTÉE DU TOC · %1", [_isrc] call _srcName]],
                format ["<t font='EtelkaMonospacePro' size='0.8' color='#1a1206'>%1 · %2</t>", ["9-LINE", "5-LINE"] select (_ifmt isEqualTo "5"), _iat], [0.95, 0.67, 0.2, 1]];
        };
        _rows pushBack ["buttons", [[["RÉCUPÉRER DEPUIS LE TOC / MOBILE", "RELEVÉ EN COURS…"] select _busy, ["fetch"] call { params ["_a"]; compile format ["[%1] call comspec_atak_native_fnc_jtacTocImport;", str _a] }, false, !_busy,
            "Vos 9-LINE et 5-LINE rédigés sur le TOC web ou la version mobile (carte courante), à charger dans la fiche"]]];
        private _list = _toc getOrDefault ["list", []];
        if ((_toc getOrDefault ["open", false]) && {(count _list) > 0}) then {
            _rows pushBack ["section", "Depuis le TOC / mobile", format ["relevé à %1 · le plus récent d'abord", _toc getOrDefault ["at", ""]]];
            {
                _x params ["", "_aid", "_fmt", "_from", "_at", "_lines", "", "_st", "_to", "_src"];
                private _same = (_imp param [0, -1]) isEqualTo _aid;
                private _what = if (_fmt isEqualTo "5") then { format ["%1 · %2", _lines param [2, "-"], _lines param [3, "-"]] } else { format ["%1 · %2", _lines param [5, "-"], _lines param [4, "-"]] };
                if (_same) then { _what = _what + " · " + "déjà chargé, appuyez pour mettre à jour depuis le serveur"; } else { if (_to isNotEqualTo "") then { _what = _what + " · " + format ["pour %1", _to]; }; };
                _rows pushBack ["notif", "\z\comspec_atak_native\addons\main\data\app_jtac.paa", [[0.45, 0.5, 0.47, 1], [0.36, 0.78, 0.42, 1]] select _same,
                    format ["%1 · %2 · %3", ["9-LINE", "5-LINE"] select (_fmt isEqualTo "5"), _from, [_src] call _srcName],
                    format ["%1 · %2", _at, [_st, "ENVOYÉ"] select (_st in ["", "SENT"])],
                    _what, compile format ["['load', %1] call comspec_atak_native_fnc_jtacTocImport;", _aid], {}, ["Charger dans la fiche", "Mettre à jour depuis le serveur"] select _same];
            } forEach _list;
            _rows pushBack ["buttons", [["FERMER LA LISTE", { ["hide"] call comspec_atak_native_fnc_jtacTocImport; }]]];
        };
        _rows
    };
};
