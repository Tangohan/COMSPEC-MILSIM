/*
    Identifiant stable d'une IA pour le lore de session Athena (ICD-SSE-LORE-001), posé par le SERVEUR.
    Variable publique de l'unité : comspec_sse_aiUid (texte, 2 à 64 caractères A-Z a-z 0-9 _ . : -). Lue aussi par
    l'ATAK (téléphone saisi rattaché à son propriétaire IA) : ne pas changer la règle sans prévenir.

    Règle (la première qui donne une valeur l'emporte), pour retrouver la même personne d'une session à l'autre :
      1. attribut Eden « Identifiant Athena (lore) » (COMSPEC_ID_AiUid), ou valeur posée par un script avant l'apparition ;
      2. identifiant biométrique fixé (COMSPEC_SSE_BioId)                 -> « BIO-<ID> » ;
      3. nom de variable Eden de l'unité (vehicleVarName)                  -> « VAR-<CARTE>-<nom> » ;
      4. nom déclaré SSE (COMSPEC_SSE_FirstName / LastName, fiche « identity » du générateur)
                                                                          -> « NOM-<CARTE>-<empreinte prénom|nom> » ;
      5. sinon : carte, mission, classe et position d'apparition (au mètre) -> « POS-<CARTE>-<empreinte> ».
    Les règles 1 à 4 tiennent d'une partie à l'autre ; la 5 seulement si la mission n'a pas bougé (même unité au même
    endroit). Pour une personne qui compte, donnez un nom de variable ou un identifiant Eden.
    Deux unités qui tomberaient sur le même identifiant : la seconde reçoit « -2 », « -3 »… (ordre d'apparition).

    [_op, _unit] call comspec_sse_fnc_loreUid
      "assign" : pose comspec_sse_aiUid si absent (serveur, IA seulement) -> identifiant ("" pour un joueur)
      "of"     : identifiant déjà posé ("" sinon), toute machine
      "clean"  : _unit est un texte -> texte nettoyé (caractères autorisés, 64 au plus)
    Registre serveur : missionNamespace comspec_sse_loreUids (identifiant -> unité), jamais diffusé.
*/
params [["_op", "of", [""]], ["_u", objNull, [objNull, ""]]];
private _clean = {
    private _s = toString ((toArray _this) select {
        (_x >= 48 && {_x <= 57}) || {_x >= 65 && {_x <= 90}} || {_x >= 97 && {_x <= 122}} || {_x in [45, 46, 58, 95]}
    });
    _s select [0, 64]
};
if (_op isEqualTo "clean") exitWith { if (_u isEqualType "") then { _u call _clean } else { "" } };
if !(_u isEqualType objNull) exitWith { "" };
if (isNull _u) exitWith { "" };
private _have = _u getVariable ["comspec_sse_aiUid", ""];
if (_op isEqualTo "of") exitWith { [_have, ""] select !(_have isEqualType "") };
if (_op isNotEqualTo "assign" || {!isServer}) exitWith { "" };
if (_have isEqualType "" && {(count _have) >= 2}) exitWith { _have };
// Joueurs et emplacements jouables (une IA de slot est un joueur absent) : pas de lore d'IA.
if (!(_u isKindOf "CAManBase") || {isPlayer _u} || {(getPlayerUID _u) isNotEqualTo ""} || {_u in playableUnits} || {_u in switchableUnits}) exitWith { "" };

private _world = (toUpper worldName) call _clean;
private _two = {
    // Empreinte sur 12 chiffres hexadécimaux : deux hachages 24 bits (fn_hash) de la même chaîne.
    private _h1 = [7, _this] call comspec_sse_fnc_hash;
    private _h2 = [13, _this + "|2"] call comspec_sse_fnc_hash;
    private _hex = {
        private _n = _this;
        private _o = "";
        for "_i" from 1 to 6 do { _o = ("0123456789ABCDEF" select [_n mod 16, 1]) + _o; _n = floor (_n / 16); };
        _o
    };
    (_h1 call _hex) + (_h2 call _hex)
};
private _uid = "";
private _eden = _u getVariable ["COMSPEC_ID_AiUid", ""];
if (_eden isEqualType "") then { _uid = (trim _eden) call _clean; };
if ((count _uid) < 2) then { _uid = ""; };
if (_uid isEqualTo "") then {
    private _bio = _u getVariable ["COMSPEC_SSE_BioId", ""];
    if (_bio isEqualType "" && {((trim _bio) call _clean) isNotEqualTo ""}) then { _uid = ("BIO-" + toUpper (trim _bio)) call _clean; };
};
if (_uid isEqualTo "") then {
    private _var = vehicleVarName _u;
    if ((_var call _clean) isNotEqualTo "") then { _uid = (format ["VAR-%1-%2", _world, _var]) call _clean; };
};
if (_uid isEqualTo "") then {
    private _f = _u getVariable ["COMSPEC_SSE_FirstName", ""];
    private _l = _u getVariable ["COMSPEC_SSE_LastName", ""];
    private _sec = [_u, "identity"] call comspec_sse_fnc_getSection;
    if (!isNil "_sec" && {_sec isEqualType createHashMap}) then {
        if !(_f isEqualType "" && {_f isNotEqualTo ""}) then { _f = _sec getOrDefault ["first_name", ""]; };
        if !(_l isEqualType "" && {_l isNotEqualTo ""}) then { _l = _sec getOrDefault ["last_name", ""]; };
    };
    if (_f isEqualType "" && {_l isEqualType ""} && {(trim _l) isNotEqualTo ""}) then {
        _uid = format ["NOM-%1-%2", _world, (toUpper format ["%1|%2", trim _f, trim _l]) call _two];
    };
};
if (_uid isEqualTo "") then {
    private _p = getPosATL _u;
    _uid = format ["POS-%1-%2", _world, (format ["%1|%2|%3|%4", missionName, typeOf _u, round (_p select 0), round (_p select 1)]) call _two];
};
// Doublon (même identifiant porté par une autre unité vivante de la partie) : suffixe dans l'ordre d'apparition.
private _all = missionNamespace getVariable "comspec_sse_loreUids";
if (isNil "_all") then { _all = createHashMap; missionNamespace setVariable ["comspec_sse_loreUids", _all]; };
private _base = _uid select [0, 60];
private _n = 1;
while { private _o = _all getOrDefault [_uid, objNull]; !isNull _o && {alive _o} && {_o isNotEqualTo _u} && {_n < 99} } do {
    _n = _n + 1;
    _uid = format ["%1-%2", _base, _n];
};
_all set [_uid, _u];
_u setVariable ["comspec_sse_aiUid", _uid, true];
_uid
