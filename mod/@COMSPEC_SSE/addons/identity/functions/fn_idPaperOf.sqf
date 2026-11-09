/*
    API publique pour les mods et les scripts (SERVEUR) : identité liée à un papier en jeu.
    [_paper] call comspec_sse_fnc_idPaperOf -> HashMap (vide : objet vierge du catalogue, papier inconnu, appel hors serveur)
      _paper  classe d'exemplaire telle qu'elle est dans l'inventaire (« COMSPEC_ID_Card_007 ») ou docId du registre (« ID0007 »)
    Clés :
      docId, cls, type (id_card | passport | driving_license | military_id), country, number,
      last, first, sex, dob, pob, nat, issued, expires          ce qui est imprimé
      athenaDocId       id du papier sur Athena ("" : papier né en jeu)
      athenaIdentityId  id de l'identité Athena qui le porte       athenaRef  « IDT-000001 » ("" sans identité Athena)
      ownerUid, ownerName   à qui il a été remis (Steam ID, nom ; "" pour une IA)
      holder, holderUid     porteur connu (unité ou conteneur ; objNull si inconnu), son Steam ID
      foreign           vrai : porté par un autre joueur que son propriétaire (ramassé sur un corps, au sol)
      report            "" | vole | perdu | revoque (signalements d'Athena compris, paquet et révocations)
      validity          valid | revoked | suspended… (papier Athena ; "" sinon)
      kind              civil | cover | forged | generated | alias (vérité de jeu : serveur seulement, ne la diffusez pas)
      custody           [[heure, évènement (issued | take | put | transfer), nom, Steam ID]…] (20 derniers)
      role              real | cover (identité réelle ou couverture d'un membre qui porte les deux ; "" sans rôle)
      hasPhoto          vrai : la photo d'identité d'Athena est imprimée (portrait réduit du paquet)
    Un papier ramassé garde l'identité de son propriétaire : c'est elle qu'un contrôle d'identité affiche, jamais celle
    du porteur (la photo reste le visage du propriétaire, fn_idServer : faceNetId).
    Arma n'a pas de variable par objet d'inventaire : chaque exemplaire est une classe libre (CfgWeapons_instances.hpp)
    reliée au document par le registre du serveur (byClass). Voir docs/guide/Controle_identite.md (Papiers reliés à Athena).
*/
params [["_paper", "", [""]]];
if (!isServer || {_paper isEqualTo ""}) exitWith { createHashMap };
private _isCls = (_paper select [0, 11]) isEqualTo "COMSPEC_ID_";
private _d = if (_isCls) then { ["ofClass", [_paper]] call comspec_sse_fnc_idRegistry } else { ["doc", [_paper]] call comspec_sse_fnc_idRegistry };
if ((count _d) isEqualTo 0) exitWith { createHashMap };
private _reg = missionNamespace getVariable ["comspec_sse_idReg", createHashMap];
private _aid = _d getOrDefault ["athenaId", ""];
private _ath = createHashMap;
if (_aid isNotEqualTo "") then {
    private _k = (_reg getOrDefault ["athenaById", createHashMap]) getOrDefault [_aid, ""];
    if (_k isNotEqualTo "") then { _ath = (_reg getOrDefault ["athena", createHashMap]) getOrDefault [_k, createHashMap]; };
};
private _iid = _d getOrDefault ["athenaIdentity", ""];
private _ref = "";
if (_iid isNotEqualTo "") then {
    private _s = _iid;
    while { count _s < 6 } do { _s = "0" + _s; };
    _ref = "IDT-" + _s;
};
// Signalement : la base locale du pays (paquet Athena, révocations, Zeus) l'emporte sur la valeur de délivrance.
private _base = (_reg getOrDefault ["issued", createHashMap]) getOrDefault [format ["%1|%2|%3", _d getOrDefault ["country", ""], _d getOrDefault ["type", ""], _d getOrDefault ["number", ""]], []];
private _report = _base param [3, _d getOrDefault ["report", ""]];
private _validity = _ath getOrDefault ["validity", ""];
if !(_validity isEqualType "") then { _validity = ""; };
if (_validity in ["revoked", "suspended"]) then { _report = "revoque"; };
private _holder = _d getOrDefault ["holder", objNull];
if !(_holder isEqualType objNull) then { _holder = objNull; };
private _holderUid = if (!isNull _holder && {_holder isKindOf "CAManBase"}) then { getPlayerUID _holder } else { "" };
private _owner = _d getOrDefault ["ownerUid", ""];
private _out = createHashMap;
{ _out set [_x, _d getOrDefault [_x, ""]]; } forEach ["type", "country", "number", "last", "first", "sex", "dob", "pob", "nat", "issued", "expires", "kind", "ownerName"];
_out set ["docId", _d getOrDefault ["id", ""]];
_out set ["cls", _d getOrDefault ["cls", ""]];
_out set ["athenaDocId", _aid];
_out set ["athenaIdentityId", _iid];
_out set ["athenaRef", _ref];
_out set ["ownerUid", _owner];
_out set ["holder", _holder];
_out set ["holderUid", _holderUid];
_out set ["foreign", _owner isNotEqualTo "" && {_holderUid isNotEqualTo ""} && {_holderUid isNotEqualTo _owner}];
_out set ["report", _report];
_out set ["validity", _validity];
_out set ["custody", +(_d getOrDefault ["custody", []])];
// Rôle pour un membre qui porte ses deux identités (real | cover ; "" : papier sans rôle) et photo d'Athena transmise.
_out set ["role", _d getOrDefault ["role", ""]];
_out set ["hasPhoto", (_d getOrDefault ["photoMosaic", ""]) isNotEqualTo ""];
_out
