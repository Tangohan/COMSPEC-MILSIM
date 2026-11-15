/*
    Bouton « SIGNALER » de la carte « AVIS DE RECHERCHE » du contrôle d'identité : signale au portail que la personne recherchée vient
    d'être contrôlée (ICD-SSE-ALERTES-001, kind « sse_alertes », op « report »), avec la position et la grille DU CONTRÔLE.
      - ATAK natif avec l'app « Avis et vigilance » : son écran de signalement s'ouvre, déjà rempli (certitude, direction, véhicule,
        envoi suivi, RENVOYER sans doublon) ;
      - sinon : envoi direct par le canal « kind » de SubmitIdCheck, sans nouvelle DLL. L'identifiant client « cid » est tiré une fois
        et gardé sur la fiche : un nouvel essai ne crée pas de doublon.
    Aucun toast : l'état s'écrit sur la fiche (clé « alertReport » : [état wait | ok | ko, numéro ou raison]) et la carte se redessine.

    [fiche de fn_idHistory] call comspec_sse_fnc_idAlertReport    -> true si le signalement est ouvert ou parti
*/
params [["_rec", createHashMap, [createHashMap]]];
private _w = _rec getOrDefault ["wanted", []];
if (!(_w isEqualType []) || {(count _w) < 1} || {!((_w select 0) isEqualType "")} || {(_w select 0) isEqualTo ""}) exitWith { false };
private _ref = _w select 0;
private _pos = _rec getOrDefault ["pos", getPosASL player];
if (!(_pos isEqualType []) || {(count _pos) < 2}) then { _pos = getPosASL player; };
private _grid = _rec getOrDefault ["grid", mapGridPosition _pos];
if !(_grid isEqualType "") then { _grid = mapGridPosition _pos; };
// App « Avis et vigilance » chargée : c'est elle qui porte le signalement.
if (!isNil "comspec_atak_native_fnc_alertesDraft") exitWith {
    ["reportOpen", [_ref, [_pos select 0, _pos select 1], _grid]] call comspec_atak_native_fnc_alertesDraft;
    true
};
private _state = (_rec getOrDefault ["alertReport", []]) param [0, ""];
if (_state in ["wait", "ok"]) exitWith { false };
private _cid = _rec getOrDefault ["alertCid", ""];
if (_cid isEqualTo "") then {
    private _uid = toString ((toArray (getPlayerUID player)) select { (_x >= 48 && {_x <= 57}) || {_x >= 65 && {_x <= 90}} || {_x >= 97 && {_x <= 122}} });
    systemTimeUTC params ["_y", "_mo", "_d", "_h", "_mi", "_se"];
    _cid = format ["IDC-%1-%2%3%4%5%6%7", _uid select [0, 20], _y, _mo + 10, _d + 10, _h + 10, _mi + 10, _se + 10];
    _rec set ["alertCid", _cid];
};
_rec set ["alertReport", ["wait", ""]];
[_rec, _ref, _pos, _grid, _cid] spawn {
    params ["_rec", "_ref", "_pos", "_grid", "_cid"];
    private _clean = { params ["_t"]; (_t regexReplace ["[^A-Za-z0-9 ._/:+-]", " "]) select [0, 40] };
    private _json = format ['{"kind":"sse_alertes","op":"report","ref":"%1","what":"seen","when":"","grid":"%2","pos":[%3,%4],"text":"Personne vue au poste de controle, grille %2","sure":"high","cid":"%5"}',
        [_ref] call _clean, [_grid] call _clean, (_pos select 0) toFixed 1, (_pos select 1) toFixed 1, _cid];
    private _raw = ["SubmitIdCheck", [_json]] call comspec_sse_fnc_extensionCall;
    if !(_raw isEqualType "") then { _raw = ""; };
    // OK|id|page : [1, "report", numéro, référence de l'alerte, "new", replay]
    private _num = "";
    if ((_raw select [0, 3]) isEqualTo "OK|") then {
        private _rest = _raw select [3];
        private _text = _rest select [(_rest find "|") + 1];
        if ((_text select [0, 1]) isEqualTo "[") then {
            private _p = parseSimpleArray _text;
            if (_p isEqualType [] && {(count _p) >= 3} && {(_p select 1) isEqualTo "report"} && {(_p select 2) isEqualType ""}) then { _num = _p select 2; };
        };
    };
    if (_num isNotEqualTo "") then {
        _rec set ["alertReport", ["ok", _num]];
    } else {
        _rec set ["alertReport", ["ko", [_raw select [0, 40], "sans réponse"] select (_raw isEqualTo "")]];
    };
    [{ if (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isEqualTo "IDCHECK") then { ["IDCHECK"] call comspec_atak_native_fnc_pageRender; }; }] call CBA_fnc_execNextFrame;
};
true
