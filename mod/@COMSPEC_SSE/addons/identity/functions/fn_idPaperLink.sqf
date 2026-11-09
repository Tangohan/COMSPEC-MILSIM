/*
    API publique pour les mods et les scripts (SERVEUR) : relie un papier en jeu à une identité Athena et à un membre.
    [_paper, _link] call comspec_sse_fnc_idPaperLink -> Boolean (faux : papier inconnu, appel hors serveur, papier Athena
      d'un autre type)
      _paper  classe d'exemplaire (« COMSPEC_ID_Card_007 ») ou docId (« ID0007 »)
      _link   HashMap, tout facultatif :
        athenaDocId     id d'un papier du paquet Athena : le papier en reprend aussi l'identité imprimée (nom, naissance,
                        numéro, dates, signalement) s'il est du même type ; sinon refusé
        athenaIdentity  id de l'identité Athena (sans « IDT- »)
        owner           unité : son Steam ID et son nom deviennent ceux du propriétaire
        ownerUid, ownerName   propriétaire donné directement
    Le registre garde la trace (chaîne de possession « link ») ; évènement CBA global comspec_sse_identity_paperLinked
    [classe, docId]. Exemple : [_cls, createHashMapFromArray [["athenaDocId", "42"], ["owner", _unit]]] call comspec_sse_fnc_idPaperLink.
*/
params [["_paper", "", [""]], ["_link", createHashMap, [createHashMap]]];
if (!isServer || {_paper isEqualTo ""}) exitWith { false };
private _isCls = (_paper select [0, 11]) isEqualTo "COMSPEC_ID_";
private _d = if (_isCls) then { ["ofClass", [_paper]] call comspec_sse_fnc_idRegistry } else { ["doc", [_paper]] call comspec_sse_fnc_idRegistry };
if ((count _d) isEqualTo 0) exitWith { false };
private _reg = ["init"] call comspec_sse_fnc_idRegistry;
private _aidIn = _link getOrDefault ["athenaDocId", ""];
if (_aidIn isEqualType 0) then { _aidIn = str _aidIn; };
if !(_aidIn isEqualType "") then { _aidIn = ""; };
if (_aidIn isNotEqualTo "") then {
    private _k = (_reg getOrDefault ["athenaById", createHashMap]) getOrDefault [_aidIn, ""];
    if (_k isEqualTo "") exitWith { _aidIn = "?"; };
    private _spec = ["athenaSpec", _k] call comspec_sse_fnc_idModuleGive;
    if ((count _spec) isEqualTo 0 || {(_spec getOrDefault ["type", ""]) isNotEqualTo (_d get "type")}) exitWith { _aidIn = "?"; };
    // Ancienne entrée de la base locale (numéro né en jeu) retirée, puis le papier reprend l'identité imprimée d'Athena.
    (_reg get "issued") deleteAt format ["%1|%2|%3", _d get "country", _d get "type", _d get "number"];
    { if ((_spec getOrDefault [_x, ""]) isNotEqualTo "") then { _d set [_x, _spec get _x]; }; } forEach ["country", "number", "last", "first", "sex", "dob", "pob", "issued", "expires", "report"];
    _d set ["trueLast", _d get "last"];
    _d set ["trueFirst", _d get "first"];
    _d set ["athenaId", _spec get "athenaId"];
    _d set ["athenaIdentity", _spec getOrDefault ["athenaIdentity", ""]];
    if ((_spec getOrDefault ["kind", ""]) isNotEqualTo "") then { _d set ["kind", _spec get "kind"]; };
    // Base locale du pays (contrôle N3 hors ligne) : comme au chargement du paquet (un faux n'y est pas).
    if ((_d get "kind") isNotEqualTo "forged") then {
        (_reg get "issued") set [_k, [_d get "id", _d get "last", _d get "first", _d getOrDefault ["report", ""]]];
    };
};
if (_aidIn isEqualTo "?") exitWith { false };
private _iid = _link getOrDefault ["athenaIdentity", ""];
if (_iid isEqualType 0) then { _iid = str _iid; };
// « IDT-000001 » ou « 1 » : même identité (le paquet donne l'id sans zéros).
if (_iid isEqualType "" && {_iid isNotEqualTo ""}) then {
    private _n = parseNumber ((_iid splitString "IDTidt-") joinString "");
    if (_n > 0) then { _d set ["athenaIdentity", _n toFixed 0]; };
};
private _who = _link getOrDefault ["owner", objNull];
if (_who isEqualType objNull && {!isNull _who}) then {
    _d set ["ownerUid", getPlayerUID _who];
    _d set ["ownerName", name _who];
};
{ if ((_link getOrDefault [_x, ""]) isEqualType "" && {(_link getOrDefault [_x, ""]) isNotEqualTo ""}) then { _d set [_x, _link get _x]; }; } forEach ["ownerUid", "ownerName"];
["log", [_d, "link", objNull]] call comspec_sse_fnc_idPaperTrack;
["comspec_sse_identity_paperLinked", [_d getOrDefault ["cls", ""], _d get "id"]] call CBA_fnc_globalEvent;
true
