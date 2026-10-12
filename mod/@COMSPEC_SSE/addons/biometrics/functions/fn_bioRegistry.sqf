/*
    Registre des identités biologiques (serveur) : une identité = une clé = une
    biométrie, jamais partagée par deux personnages différents.

    Le serveur attribue la clé de chaque personne dès son apparition et la publie
    (COMSPEC_SSE_BioKey) : tous les postes calculent alors le même profil.
      - identifiant fixé par le chef de mission (COMSPEC_SSE_BioId) : la clé est cet
        identifiant ; c'est le seul cas où un personnage se retrouve d'une mission à
        l'autre (même graine de campagne) ;
      - graine posée dans Eden (COMSPEC_SSE_Seed) : « P<graine> », stable dans la mission ;
      - sinon une clé propre à cette partie, « S<partie>-<n> » : un PNJ anonyme ne
        réapparaît jamais avec les empreintes d'un autre dans une mission suivante.
    Deux personnes différentes qui tomberaient sur la même clé (graine recopiée,
    identifiant saisi deux fois) sont séparées : la seconde reçoit une clé dérivée et
    le Zeus est prévenu. Les gabarits d'empreintes et d'iris sont vérifiés uniques sur
    toute la partie (et sur la campagne mémorisée).

    Params: [_op, _unit]
      "assign" : attribue (ou relit) la clé de l'unité ; retourne la clé
      "owner"  : retourne l'unité qui porte une clé (objNull si aucune)
*/
params [["_op", "assign", [""]], ["_unit", objNull, [objNull, ""]]];

private _reg = missionNamespace getVariable "comspec_sse_bioReg";
if (isNil "_reg") then {
    // Partie : identifiant unique de ce lancement de mission (heure réelle + mission).
    private _t = systemTimeUTC;
    private _tag = [format ["%1|%2|%3", missionName, _t, random 1e6], "session", 6] call comspec_sse_fnc_bioHex;
    _reg = createHashMapFromArray [
        ["session", _tag], ["n", 0],
        ["owners", createHashMap],      // clé -> unité
        ["templates", createHashMap]    // gabarit -> clé
    ];
    // Gabarits des personnages récurrents connus de la campagne : réservés.
    {
        private _k = _x;
        { (_reg get "templates") set [_x, _k]; } forEach ([_k] call comspec_sse_fnc_bioTemplates);
    } forEach (keys (missionNamespace getVariable ["comspec_sse_bioRuntime", createHashMap]));
    missionNamespace setVariable ["comspec_sse_bioReg", _reg];
};
private _owners = _reg get "owners";
private _templates = _reg get "templates";

if (_op isEqualTo "owner") exitWith {
    private _o = _owners getOrDefault [toUpper _unit, objNull];
    if (_o isEqualType objNull) then { _o } else { objNull }
};

if (!(_unit isEqualType objNull) || {isNull _unit} || {!(_unit isKindOf "CAManBase")}) exitWith { "" };
private _have = _unit getVariable ["COMSPEC_SSE_BioKey", ""];
if (_have isNotEqualTo "" && {(_owners getOrDefault [_have, objNull]) isEqualTo _unit}) exitWith { _have };

private _base = "";
private _fixed = false;
private _id = _unit getVariable ["COMSPEC_SSE_BioId", ""];
if (_id isEqualType "" && {(trim _id) isNotEqualTo ""}) then {
    _base = toUpper (trim _id);
    _fixed = true;
} else {
    private _seed = _unit getVariable ["COMSPEC_SSE_Seed", 0];
    if (_seed isEqualType 0 && {_seed > 0} && {_unit getVariable ["COMSPEC_SSE_SeedAuthored", false]}) then {
        _base = format ["P%1", round _seed];
    } else {
        _reg set ["n", (_reg get "n") + 1];
        _base = format ["S%1-%2", _reg get "session", _reg get "n"];
    };
};

// Clé déjà portée par quelqu'un d'autre (vivant ou mort) : on sépare.
private _key = _base;
private _clash = "";
private _i = 0;
while {
    private _o = _owners getOrDefault [_key, objNull];
    (!isNull _o && {_o != _unit})
} do {
    _i = _i + 1;
    _clash = _base;
    _key = format ["%1~%2", _base, _i];
};

// Gabarit déjà attribué à une autre identité (collision de hachage) : nouvelle clé.
private _tries = 0;
while {
    private _k = _key;
    ((([_key] call comspec_sse_fnc_bioTemplates) findIf { private _o = _templates getOrDefault [_x, ""]; _o isNotEqualTo "" && {_o isNotEqualTo _k} }) >= 0) && {_tries < 8}
} do {
    _tries = _tries + 1;
    _key = format ["%1#%2", _base, _tries];
};

_owners set [_key, _unit];
{ _templates set [_x, _key]; } forEach ([_key] call comspec_sse_fnc_bioTemplates);
_unit setVariable ["COMSPEC_SSE_BioKey", _key, true];

if (_clash isNotEqualTo "") then {
    private _msg = format ["Biométrie : %1 portait déjà l'identité %2 (%3). Identité distincte attribuée : %4. Vérifiez les identifiants biométriques et les graines.", name _unit, _clash, ["graine recopiée", "identifiant en double"] select _fixed, _key];
    diag_log text ("[COMSPEC SSE] " + _msg);
    ["comspec_sse_bioZeusNote", [_msg]] call CBA_fnc_globalEvent;
};
_key
