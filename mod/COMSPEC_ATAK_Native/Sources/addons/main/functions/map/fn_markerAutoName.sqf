/*
    Nom automatique d'un marqueur (ou d'une étape) dans la langue du mod, à l'affichage : les marqueurs gardent le texte
    posé par leur auteur (diffusé à tous, envoyé à Athena), le téléphone le relit dans la langue choisie.
    Reconnaît seulement les noms que le mod pose lui-même, dans une langue ou l'autre :
      palette        « ENI INFANTERIE 3 » <-> « ENY INFANTRY 3 », « OBJECTIF 2 » <-> « OBJECTIVE 2 » (fn_markerDrop)
      détection      « INC BTR-80 (drone 14:02) » (camp seul)
      cible LOCK     « LOCK 098 · BTR-80 (téléphone) » / « … neutralisé » / « … perdu 14:02 » (fn_lockAction)
      étape          « FIN », « FIN 2 » <-> « END » (fn_wpAction)
    Tout autre texte (renommé à la main) est rendu tel quel. Résultat gardé par texte, vidé au changement de langue.
    Params : [texte]. Renvoie le texte à afficher.
*/
params [["_t", ""]];
if (_t isEqualTo "") exitWith { _t };
private _lang = missionNamespace getVariable ["COMSPEC_ATAK_Lang", "en"];
private _fr = _lang isEqualTo "fr";
private _cache = missionNamespace getVariable ["COMSPEC_ATAK_AutoNameCache", []];
if ((_cache param [0, ""]) isNotEqualTo _lang) then {
    // Noms de la palette en capitales (le marqueur est posé en capitales), écrits en français dans le code.
    private _set = createHashMap;
    { { _set set [toUpper (_x select 1), true]; } forEach _y; } forEach (([] call comspec_atak_native_fnc_markerPaletteData) select 1);
    { _set set [toUpper _y, true]; } forEach ([] call comspec_atak_native_fnc_markerLabels);
    _cache = [_lang, createHashMap, _set];
    missionNamespace setVariable ["COMSPEC_ATAK_AutoNameCache", _cache];
};
_cache params ["", "_done", "_set"];
private _hit = _done getOrDefault [_t, ""];
if (_hit isNotEqualTo "") exitWith { _hit };
// Camp en abrégé : français ENI/AMI/NEU/INC, sinon anglais.
private _pfxFr = ["ENI", "AMI", "NEU", "INC"];
private _pfxEn = ["ENY", "FRD", "NEU", "UNK"];
private _pfx = { params ["_p"]; private _i = (_pfxFr find _p) max (_pfxEn find _p); if (_i < 0) exitWith { "" }; [_pfxEn, _pfxFr] select _fr select _i };
private _exact = missionNamespace getVariable ["COMSPEC_ATAK_I18nExact", createHashMap];
// Nom de la palette (capitales) : connu s'il est dans la palette, ou si sa traduction y est (posé dans l'autre langue).
private _label = {
    params ["_l"];
    private _tr = _exact getOrDefault [_l, ""];
    if (_l in _set) exitWith { [_tr, _l] select (_tr isEqualTo "") };
    if (_tr in _set) exitWith { _tr };
    ""
};
private _out = _t;
private _w = _t splitString " ";
switch (true) do {
    case ((_t select [0, 5]) isEqualTo "LOCK "): {
        {
            _x params ["_a", "_b"];
            private _from = [_a, _b] select _fr;
            private _to = [_b, _a] select _fr;
            private _n = count _from;
            if ((count _out) > _n && {(_out select [(count _out) - _n]) isEqualTo _from}) then { _out = (_out select [0, (count _out) - _n]) + _to; };
        } forEach [[" (téléphone)", " (phone)"], [" neutralisé", " neutralized"]];
        private _lost = [[" perdu ", " lost "], [" lost ", " perdu "]] select _fr;
        private _i = _out find (_lost select 0);
        if (_i > 0 && {(count _out) - _i - (count (_lost select 0)) isEqualTo 5}) then { _out = (_out select [0, _i]) + (_lost select 1) + (_out select [_i + count (_lost select 0)]); };
    };
    case ((count _w) isEqualTo 0): {};
    case ((_w select 0) in ["FIN", "END"] && {(count _w) isEqualTo 1 || {(count _w) isEqualTo 2 && {((_w select 1) regexMatch "[0-9]+")}}}): {
        _w set [0, ["END", "FIN"] select _fr];
        _out = _w joinString " ";
    };
    case ((_t select [(count _t) - 1]) isEqualTo ")" && {(_t find " (drone ") > 0}): {
        private _p = [_w select 0] call _pfx;
        if (_p isNotEqualTo "") then { _w set [0, _p]; _out = _w joinString " "; };
    };
    default {
        private _num = "";
        if ((count _w) > 1 && {(_w select ((count _w) - 1)) regexMatch "[0-9]+"}) then { _num = _w deleteAt ((count _w) - 1); };
        private _p = "";
        if ((count _w) > 1) then {
            _p = [_w select 0] call _pfx;
            if (_p isNotEqualTo "") then { _w deleteAt 0; };
        };
        private _l = [_w joinString " "] call _label;
        if (_l isNotEqualTo "") then { _out = ([_p, _l, _num] select { _x isNotEqualTo "" }) joinString " "; };
    };
};
if ((count _done) > 400) then { _done = createHashMap; _cache set [1, _done]; };
_done set [_t, _out];
_out
