/*
    Fenêtre « Papiers de l'IA » du Zeus (SERVEUR) : tout ce que la fenêtre affiche, lu dans le registre et envoyé au seul
    poste du Zeus qui l'a demandé (fn_idServer « truth », demandeur vérifié par fn_isAuthority). Rien n'est diffusé.
    [_cible, _pub] call comspec_sse_fnc_idZeusPapersData -> HashMap
      _cible  personne (IA ou joueur), ou objet / porte-objets au sol qui contient des papiers
      _pub    code de fn_idServer qui rend le document imprimé (ce que voit un joueur, jamais la vérité)
    Résultat : target (netId), man, player, unitName, given, true, truth (fn_aiWho si l'éditeur est chargé), cover
      [état, motif, cohérence, raisons, soupçons] ou [], hvt [réf, nom, prénom, étape] ou [], boloFace [réf, niveau] ou [],
      decided, docs [[docId, imprimé (HashMap), analyse (fn_idZeusAnalysis), autres (HashMap)]…], at (heure du jeu)
      autres d'un document : cls (classe de l'exemplaire, "" : virtuel), foreign (papier d'une autre personne trouvé
      sur elle), owner (nom de son propriétaire), bolo [réf, niveau] ou [], label (type, pays, numéro)
*/
params [["_t", objNull, [objNull]], ["_pub", {}, [{}]]];
if (!isServer || {isNull _t}) exitWith { createHashMap };
private _reg = ["init"] call comspec_sse_fnc_idRegistry;
private _man = _t isKindOf "CAManBase";
private _out = createHashMapFromArray [["target", netId _t], ["man", _man], ["player", _man && {isPlayer _t}], ["unitName", [getText ((configOf _t) >> "displayName"), name _t] select _man]];
date params ["_yy", "_mm", "_dd"];
_out set ["at", [dayTime, "HH:MM"] call BIS_fnc_timeToString];

// Papiers de la personne (décidés au premier appel, comme pour un contrôle), puis papiers trouvés sur elle ou dans l'objet.
private _ids = if (_man) then { ["unitDocs", [_t]] call comspec_sse_fnc_idRegistry } else { [] };
private _key = if (_man) then { [_t] call comspec_sse_fnc_bioPersonKey } else { "" };
private _carried = (if (_man) then { items _t } else { (weaponCargo _t) + (itemCargo _t) }) select { (_x select [0, 11]) isEqualTo "COMSPEC_ID_" };
{
    private _d = ["ofClass", [_x]] call comspec_sse_fnc_idRegistry;
    if ((count _d) > 0) then { _ids pushBackUnique (_d get "id"); };
} forEach _carried;
_out set ["decided", !_man || {(_reg get "decided") getOrDefault [_key, false]}];

private _docs = [];
{
    private _d = ["doc", [_x]] call comspec_sse_fnc_idRegistry;
    if ((count _d) isEqualTo 0) then { continue };
    private _k = format ["%1|%2|%3", _d get "country", _d get "type", _d get "number"];
    private _base = (_reg get "issued") getOrDefault [_k, []];
    private _ath = (_reg getOrDefault ["athena", createHashMap]) getOrDefault [_k, createHashMap];
    // Papier connu d'Athena seulement : la base le connaît (nom imprimé), signalement d'Athena.
    if (_base isEqualTo [] && {(count _ath) > 0}) then { _base = [_x, _d get "last", _d get "first", _ath getOrDefault ["report", ""]]; };
    private _tdef = ["get", _d get "type"] call comspec_sse_fnc_idDocTypes;
    private _an = [_d, [_yy, _mm, _dd], createHashMapFromArray [["base", _base], ["hasMrz", (_tdef getOrDefault ["mrz", ""]) isNotEqualTo ""]]] call comspec_sse_fnc_idZeusAnalysis;
    private _more = createHashMapFromArray [
        ["cls", _d getOrDefault ["cls", ""]],
        ["foreign", _man && {(_d getOrDefault ["holderKey", ""]) isNotEqualTo _key}],
        ["owner", _d getOrDefault ["ownerName", ""]],
        ["bolo", ["docFlag", [_d]] call comspec_sse_fnc_idHvt]
    ];
    _docs pushBack [_x, [_d] call _pub, _an, _more];
} forEach _ids;
_out set ["docs", _docs];

if (_man) then {
    // Nom donné et vrai nom (même règle que la page « IA de la zone » de l'éditeur, s'il est chargé).
    if (!isNil "comspec_sse_fnc_aiWho") then {
        private _w = [_t] call comspec_sse_fnc_aiWho;
        _out set ["given", _w getOrDefault ["given", ""]];
        _out set ["true", _w getOrDefault ["true", ""]];
        _out set ["truth", _w getOrDefault ["truth", ""]];
    };
    private _cs = ["state", [_t]] call comspec_sse_fnc_idCover;
    if ((count _cs) > 0) then {
        (["score", [_t]] call comspec_sse_fnc_idCover) params [["_sc", 0], ["_why", []]];
        _out set ["cover", [["off", ["on", "burnt"] select (_cs getOrDefault ["burnt", false])] select ((_cs getOrDefault ["on", false]) || {_cs getOrDefault ["burnt", false]}),
            _cs getOrDefault ["burnWhy", ""], _sc, _why, round (_cs getOrDefault ["susp", 0])]];
    };
    private _hv = ["of", [_t]] call comspec_sse_fnc_idHvt;
    if ((count _hv) > 0) then { _out set ["hvt", [_hv getOrDefault ["ref", ""], _hv getOrDefault ["last", ""], _hv getOrDefault ["first", ""], _hv getOrDefault ["stage", ""]]]; };
    _out set ["boloFace", ["faceFlag", [_t]] call comspec_sse_fnc_idHvt];
};
_out
